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

// --- Kuponları çek ---
if ($user['role'] === 'admin') {
    $stmt = $db->prepare("
        SELECT c.*, b.name AS company_name
        FROM Coupons c
        LEFT JOIN Bus_Company b ON c.company_id = b.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
} else { // company yetkilisi
    $stmt = $db->prepare("
        SELECT c.*, b.name AS company_name
        FROM Coupons c
        LEFT JOIN Bus_Company b ON c.company_id = b.id
        WHERE c.company_id = :company_id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([':company_id' => $user['company_id']]);
}

$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kupon Listesi</title>
<style>
body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
.container { max-width: 900px; margin: 50px auto; background:white; padding:25px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1);}
h2 { text-align:center; margin-bottom:20px; }
table { width:100%; border-collapse: collapse; }
th, td { padding:12px; text-align:left; border-bottom:1px solid #ccc; }
th { background:#007bff; color:white; }
a.button { background:#007bff; color:white; padding:6px 12px; border-radius:5px; text-decoration:none; }
a.button:hover { background:#0056b3; }
</style>
</head>
<body>
<div class="container">
<h2>Kupon Listesi</h2>
<table>
    <thead>
        <tr>
            <th>Kod</th>
            <th>İndirim (%)</th>
            <th>Kullanım Limiti</th>
            <th>Son Kullanma</th>
            <th>Firma</th>
            <th>İşlem</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($coupons as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['code']) ?></td>
            <td><?= htmlspecialchars($c['discount']) ?></td>
            <td><?= htmlspecialchars($c['usage_limit']) ?></td>
            <td><?= date('d-m-Y H:i', strtotime($c['expire_date'])) ?></td>
            <td><?= htmlspecialchars($c['company_name'] ?? '-') ?></td>
            <td><a class="button" href="edit_coupon.php?id=<?= urlencode($c['id']) ?>">Düzenle</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($coupons)): ?>
        <tr><td colspan="6" style="text-align:center;">Kupon bulunamadı.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
</body>
</html>
