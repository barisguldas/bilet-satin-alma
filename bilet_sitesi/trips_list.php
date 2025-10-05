<?php
session_start();
require_once 'includes/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcıyı veritabanından çek
$stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    die("Kullanıcı bulunamadı.");
}

// Rol kontrolü — sadece admin ve firma yetkilileri erişebilir
if (!in_array($current_user['role'], ['admin', 'company'])) {
    http_response_code(403);
    die("<h3 style='color:red; text-align:center;'>Bu sayfaya erişim yetkiniz yok!</h3>");
}

// Sefer silme işlemi
if (isset($_GET['delete_id'])) {
    $trip_id = $_GET['delete_id'];

    // Firma yetkilisi ise sadece kendi seferini silebilir
    if ($current_user['role'] === 'company') {
        $stmtCheck = $db->prepare("SELECT * FROM Trips WHERE id = :id AND company_id = :company_id");
        $stmtCheck->execute([':id' => $trip_id, ':company_id' => $current_user['company_id']]);
        $trip = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            http_response_code(403);
            die("<h3 style='color:red; text-align:center;'>Bu seferi silme yetkiniz yok!</h3>");
        }
    }

    // Silme işlemi
    $stmtDelete = $db->prepare("DELETE FROM Trips WHERE id = :id");
    $stmtDelete->execute([':id' => $trip_id]);

    header("Location: trips_list.php?deleted=1");
    exit;
}

// Seferleri çek — admin tümünü, company sadece kendi firması
if ($current_user['role'] === 'admin') {
    $stmt = $db->query("
        SELECT t.*, b.name AS company_name 
        FROM Trips t 
        JOIN Bus_Company b ON t.company_id = b.id
        ORDER BY t.departure_time ASC
    ");
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("
        SELECT t.*, b.name AS company_name 
        FROM Trips t 
        JOIN Bus_Company b ON t.company_id = b.id
        WHERE t.company_id = :company_id
        ORDER BY t.departure_time ASC
    ");
    $stmt->execute([':company_id' => $current_user['company_id']]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Listesi</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f3f7; margin: 0; padding: 0; }
        header { background-color: #4CAF50; color: white; text-align: center; padding: 15px; }
        .container { max-width: 1000px; margin: 40px auto; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 25px; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #f8f8f8; }
        tr:hover { background-color: #f1f1f1; }
        .btn { padding: 8px 15px; border: none; border-radius: 6px; cursor: pointer; color: white; text-decoration: none; }
        .edit-btn { background-color: #2196F3; }
        .delete-btn { background-color: #f44336; }
        .edit-btn:hover { background-color: #0b7dda; }
        .delete-btn:hover { background-color: #d32f2f; }
        .top-buttons { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .add-btn { background-color: #4CAF50; padding: 10px 18px; color: white; border-radius: 6px; text-decoration: none; }
        .add-btn:hover { background-color: #45a049; }
        .alert { text-align: center; background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
<header>
    <h1>Sefer Yönetim Paneli</h1>
</header>

<div class="container">
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert">Sefer başarıyla silindi.</div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="alert">Sefer bilgileri güncellendi.</div>
    <?php endif; ?>

    <div class="top-buttons">
        <h2>Tüm Seferler</h2>
        <a href="create_trip.php" class="add-btn">+ Yeni Sefer Ekle</a>
    </div>

    <table>
        <tr>
            <th>Firma</th>
            <th>Kalkış</th>
            <th>Varış</th>
            <th>Kalkış Zamanı</th>
            <th>Varış Zamanı</th>
            <th>Fiyat (₺)</th>
            <th>Kapasite</th>
            <th>İşlemler</th>
        </tr>
        <?php if (count($trips) > 0): ?>
            <?php foreach ($trips as $trip): ?>
                <tr>
                    <td><?= htmlspecialchars($trip['company_name']) ?></td>
                    <td><?= htmlspecialchars($trip['departure_city']) ?></td>
                    <td><?= htmlspecialchars($trip['destination_city']) ?></td>
                    <td><?= date('d-m-Y H:i', strtotime($trip['departure_time'])) ?></td>
                    <td><?= date('d-m-Y H:i', strtotime($trip['arrival_time'])) ?></td>
                    <td><?= htmlspecialchars($trip['price']) ?></td>
                    <td><?= htmlspecialchars($trip['capacity']) ?></td>
                    <td>
                        <?php if ($current_user['role'] === 'admin' || ($current_user['role'] === 'company' && $trip['company_id'] === $current_user['company_id'])): ?>
                            <a href="edit_trip.php?id=<?= $trip['id'] ?>" class="btn edit-btn">Düzenle</a>
                            <a href="trips_list.php?delete_id=<?= $trip['id'] ?>" class="btn delete-btn" onclick="return confirm('Bu seferi silmek istediğine emin misin?');">Sil</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8">Henüz sefer eklenmemiş.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
