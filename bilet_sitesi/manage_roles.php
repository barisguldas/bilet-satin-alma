<?php
session_start();
require_once 'includes/config.php';

// --- Rol kontrolü (sadece admin erişebilir) ---
$stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user || $current_user['role'] !== 'admin') {
    http_response_code(403);
    die("<h3 style='color:red; text-align:center;'>Bu sayfaya erişim yetkiniz yok!</h3>");
}

// --- Rol Güncelleme İşlemi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : null;

    // Firma yetkilisi olacaksa company_id zorunlu
    if ($new_role === 'company' && !$company_id) {
        $error = "Firma yetkilisi atamak için bir firma seçmelisiniz!";
    } else {
        $stmt = $db->prepare("UPDATE User SET role = :role, company_id = :company_id WHERE id = :id");
        $stmt->execute([
            ':role' => $new_role,
            ':company_id' => $company_id,
            ':id' => $user_id
        ]);
        $success = "Kullanıcı rolü başarıyla güncellendi.";
    }
}

// --- Kullanıcılar ve firmaları çek ---
$users = $db->query("SELECT * FROM User ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$companies = $db->query("SELECT * FROM Bus_Company ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rol Yönetimi - Admin Paneli</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
        }
        header {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            text-align: center;
        }
        .container {
            max-width: 1000px;
            background: white;
            margin: 40px auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        th, td {
            border: 1px solid #ddd;
            text-align: center;
            padding: 12px;
        }
        th {
            background-color: #f2f2f2;
        }
        select, button {
            padding: 6px 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .alert {
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<header>
    <h1>Admin Paneli - Rol Yönetimi</h1>
</header>

<div class="container">
    <h2>Kullanıcı Rolleri</h2>

    <?php if (isset($success)): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Ad Soyad</th>
            <th>E-posta</th>
            <th>Şu Anki Rol</th>
            <th>Firma</th>
            <th>Yeni Rol</th>
            <th>Atanacak Firma (Opsiyonel)</th>
            <th>İşlem</th>
        </tr>

        <?php foreach ($users as $user): ?>
            <tr>
                <form method="POST">
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td>
                        <?php
                        if ($user['company_id']) {
                            $stmt = $db->prepare("SELECT name FROM Bus_Company WHERE id = :id");
                            $stmt->execute([':id' => $user['company_id']]);
                            $company = $stmt->fetchColumn();
                            echo htmlspecialchars($company ?: '-');
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <select name="new_role">
                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="company" <?= $user['role'] === 'company' ? 'selected' : '' ?>>Firma Yetkilisi</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </td>
                    <td>
                        <select name="company_id">
                            <option value="">Seçim Yok</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $user['company_id'] === $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" name="update_role">Güncelle</button>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
