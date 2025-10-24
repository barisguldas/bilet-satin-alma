<?php
session_start();
require_once 'includes/config.php';

// --- Giriş kontrolü ---
if (!isset($_SESSION['user_id'])) {
    die("<h3 style='color:red; text-align:center;'>Bu sayfayı görmek için giriş yapmalısınız.</h3>");
}

// --- Kullanıcı bilgilerini al ---
$stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    die("<h3 style='color:red; text-align:center;'>Kullanıcı bulunamadı.</h3>");
}

// --- Rol kontrolü ---
if (!in_array($current_user['role'], ['admin', 'company'])) {
    die("<h3 style='color:red; text-align:center;'>Bu sayfaya erişim yetkiniz yok!</h3>");
}

// --- Kupon oluşturma işlemi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $discount = floatval($_POST['discount']);
    $usage_limit = intval($_POST['usage_limit']);
    $expire_date = $_POST['expire_date'];

    // Firma seçimi sadece admin için geçerli
    $company_id = null;
    if ($current_user['role'] === 'admin') {
        $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : null;
    } else {
        // Firma yetkilisi kendi firması ile sınırlı
        $company_id = $current_user['company_id'];
    }

    if (empty($code) || $discount <= 0 || $usage_limit <= 0 || empty($expire_date)) {
        $error = "Lütfen tüm alanları doğru şekilde doldurun!";
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO Coupons (id, code, discount, usage_limit, expire_date, company_id)
                VALUES (:id, :code, :discount, :limit, :expire, :company_id)
            ");
            $stmt->execute([
                ':id' => uniqid(),
                ':code' => strtoupper($code),
                ':discount' => $discount,
                ':limit' => $usage_limit,
                ':expire' => $expire_date,
                ':company_id' => $company_id
            ]);

            $success = "Kupon başarıyla oluşturuldu.";
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// --- Firmaları çek (sadece admin için) ---
$companies = [];
if ($current_user['role'] === 'admin') {
    $companies = $db->query("SELECT id, name FROM Bus_Company ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Oluştur</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        header { background-color: #007bff; color: white; padding: 15px; text-align: center; }
        .container { max-width: 600px; background: white; margin: 40px auto; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; color: #333; }
        label { display: block; margin-top: 15px; color: #555; }
        input, select { width: 100%; padding: 10px; margin-top: 5px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; }
        button { margin-top: 20px; background-color: #007bff; color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .alert { text-align: center; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<header>
    <h1>Kupon Oluştur</h1>
</header>

<div class="container">
    <h2>Yeni Kupon Bilgileri</h2>

    <?php if (isset($success)): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="code">Kupon Kodu:</label>
        <input type="text" id="code" name="code" placeholder="Örn: INDIRIM10" required>

        <label for="discount">İndirim Oranı (%):</label>
        <input type="number" id="discount" name="discount" min="1" max="100" step="0.1" required>

        <label for="usage_limit">Kullanım Limiti:</label>
        <input type="number" id="usage_limit" name="usage_limit" min="1" required>

        <label for="expire_date">Son Kullanma Tarihi:</label>
        <input type="datetime-local" id="expire_date" name="expire_date" required>

        <?php if ($current_user['role'] === 'admin'): ?>
            <label for="company_id">Firma (boş bırakılırsa tüm firmalar için geçerli):</label>
            <select id="company_id" name="company_id">
                <option value="">Tüm Firmalar</option>
                <?php foreach($companies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="submit">Kuponu Oluştur</button>
    </form>
</div>
</body>
</html>
