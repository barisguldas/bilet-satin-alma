<?php
session_start();
require_once 'includes/config.php';

// --- Giriş kontrolü ---
if (!isset($_SESSION['user_id'])) {
    die("Giriş yapmalısınız!");
}

// --- Kullanıcı bilgisi ---
$stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Kullanıcı bulunamadı.");

// --- Rol kontrolü ---
if (!in_array($user['role'], ['admin', 'company'])) {
    die("Bu sayfaya erişim yetkiniz yok!");
}

// --- Kupon bilgisi ---
if (!isset($_GET['id'])) {
    die("Kupon ID belirtilmemiş.");
}

$coupon_id = $_GET['id'];

// Firma yetkilisi sadece kendi kuponunu görebilsin
if ($user['role'] === 'admin') {
    $stmt = $db->prepare("SELECT * FROM Coupons WHERE id = :id");
    $stmt->execute([':id' => $coupon_id]);
} else {
    $stmt = $db->prepare("SELECT * FROM Coupons WHERE id = :id AND company_id = :company_id");
    $stmt->execute([':id' => $coupon_id, ':company_id' => $user['company_id']]);
}

$coupon = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$coupon) die("Kupon bulunamadı veya yetkiniz yok.");

// --- Güncelleme ---
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        $code = trim($_POST['code']);
        $discount = floatval($_POST['discount']);
        $usage_limit = intval($_POST['usage_limit']);
        $expire_date = $_POST['expire_date'];

        if (empty($code) || $discount <= 0 || $usage_limit <= 0 || empty($expire_date)) {
            $message = "Lütfen tüm alanları doğru şekilde doldurun!";
        } else {
            $stmt = $db->prepare("
                UPDATE Coupons SET code = :code, discount = :discount, usage_limit = :limit, expire_date = :expire
                WHERE id = :id
            ");
            $stmt->execute([
                ':code' => strtoupper($code),
                ':discount' => $discount,
                ':limit' => $usage_limit,
                ':expire' => $expire_date,
                ':id' => $coupon['id']
            ]);
            $message = "Kupon başarıyla güncellendi.";
            // Güncel veriyi tekrar çek
            $stmt = $db->prepare("SELECT * FROM Coupons WHERE id = :id");
            $stmt->execute([':id' => $coupon['id']]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } elseif (isset($_POST['delete'])) {
        $stmt = $db->prepare("DELETE FROM Coupons WHERE id = :id");
        $stmt->execute([':id' => $coupon['id']]);
        header("Location: coupons_list.php?deleted=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kupon Düzenle</title>
<style>
body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
.container { max-width: 500px; margin: 50px auto; background:white; padding:25px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1);}
h2 { text-align:center; margin-bottom:20px; }
input[type="text"], input[type="number"], input[type="datetime-local"] {
    width:100%; padding:10px; margin:8px 0 15px 0; border:1px solid #ccc; border-radius:5px; box-sizing:border-box;
}
button { padding:12px 20px; border:none; border-radius:5px; cursor:pointer; margin-right:10px; font-size:16px;}
button.update { background:#007bff; color:white; }
button.update:hover { background:#0056b3; }
button.delete { background:#d32f2f; color:white; }
button.delete:hover { background:#b71c1c; }
.message { text-align:center; font-weight:bold; margin-bottom:15px; color:green; }
</style>
</head>
<body>
<div class="container">
<h2>Kupon Düzenle</h2>

<?php if($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST">
    <label for="code">Kupon Kodu:</label>
    <input type="text" name="code" id="code" value="<?= htmlspecialchars($coupon['code']) ?>" required>

    <label for="discount">İndirim Oranı (%):</label>
    <input type="number" name="discount" id="discount" min="1" max="100" step="0.1" value="<?= htmlspecialchars($coupon['discount']) ?>" required>

    <label for="usage_limit">Kullanım Limiti:</label>
    <input type="number" name="usage_limit" id="usage_limit" min="1" value="<?= htmlspecialchars($coupon['usage_limit']) ?>" required>

    <label for="expire_date">Son Kullanma Tarihi:</label>
    <input type="datetime-local" name="expire_date" id="expire_date" value="<?= date('Y-m-d\TH:i', strtotime($coupon['expire_date'])) ?>" required>

    <div style="text-align:center;">
        <button type="submit" name="update" class="update">Güncelle</button>
        <button type="submit" name="delete" class="delete" onclick="return confirm('Bu kuponu silmek istediğinize emin misiniz?');">Sil</button>
    </div>
</form>
</div>
</body>
</html>
