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

    // Kullanıcıyı çek
    $stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_user) {
        $error = "Kullanıcı bulunamadı.";
    }
    // Admin kullanıcıların rolü değiştirilemez
    elseif ($target_user['role'] === 'admin') {
        $error = "Admin kullanıcıların rolü değiştirilemez!";
    }
    // Firma yetkilisi olacaksa firma seçimi zorunlu
    elseif ($new_role === 'company' && !$company_id) {
        $error = "Firma yetkilisi atamak için bir firma seçmelisiniz!";
    }
    // Normal kullanıcıya firma atanamaz
    elseif ($new_role === 'user' && $company_id) {
        $error = "Normal kullanıcılara firma atanamaz!";
    }
    else {
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
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #eef1f5;
            margin: 0;
            padding: 0;
        }

        header {
            background: #1d3557;
            color: #fff;
            padding: 20px 0;
            text-align: center;
            font-size: 24px;
            letter-spacing: 1px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #1d3557;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        th {
            background-color: #f1f1f1;
            color: #333;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        select, button {
            padding: 6px 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        button {
            background-color: #457b9d;
            color: white;
            cursor: pointer;
            border: none;
            transition: 0.2s;
        }

        button:hover {
            background-color: #1d3557;
        }

        .alert {
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<header>
    Admin Paneli - Rol Yönetimi
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
            <th>Atanacak Firma</th>
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
                            echo htmlspecialchars($stmt->fetchColumn() ?: '-');
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($user['role'] === 'admin'): ?>
                            <strong>Admin</strong>
                        <?php else: ?>
                            <select name="new_role">
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="company" <?= $user['role'] === 'company' ? 'selected' : '' ?>>Firma Yetkilisi</option>
                            </select>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['role'] === 'admin'): ?>
                            -
                        <?php else: ?>
                            <select name="company_id">
                                <option value="">Seçim Yok</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $user['company_id'] == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['role'] !== 'admin'): ?>
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit" name="update_role">Güncelle</button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
