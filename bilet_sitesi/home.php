<?php
session_start();
require_once 'includes/config.php'; // Veritabanı bağlantısı

$trips = [];
$message = "";

// 🔹 Sefer arama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_trip'])) {
    $departure = $_POST['departure'];
    $arrival = $_POST['arrival'];

    $stmt = $db->prepare("SELECT t.*, b.name AS company_name 
                          FROM Trips t 
                          JOIN Bus_Company b ON t.company_id = b.id
                          WHERE t.departure_city LIKE :dep AND t.destination_city LIKE :arr
                          ORDER BY t.departure_time ASC");
    $stmt->execute([
        ':dep' => "%$departure%",
        ':arr' => "%$arrival%"
    ]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$trips) {
        $message = "Aradığınız kriterlere uygun sefer bulunamadı.";
    }
} else {
    $stmt = $db->query("SELECT t.*, b.name AS company_name 
                        FROM Trips t 
                        JOIN Bus_Company b ON t.company_id = b.id
                        ORDER BY t.departure_time ASC");
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 🔹 Rol kontrolü
if (isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    $stmt_role = $db->prepare("SELECT role, full_name FROM User WHERE id = :id");
    $stmt_role->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt_role->fetch(PDO::FETCH_ASSOC);

    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa - Otobüs Bilet</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        header { background-color: #4CAF50; color: white; padding: 15px; text-align: center; position: relative; }
        .container { max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 8px; }
        input[type="text"] { padding: 10px; width: 200px; margin-right: 10px; }
        button { padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #45a049; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #f2f2f2; }
        .message { color: red; margin-top: 20px; text-align: center; }
        .logout { position: absolute; top: 15px; right: 15px; color: white; }
        .btn-link { text-decoration: none; color: #4CAF50; font-weight: bold; }
        .btn-link:hover { text-decoration: underline; }
        .nav-buttons { text-align: center; margin: 20px 0; }
        .nav-buttons a {
            display: inline-block;
            margin: 8px;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: 0.2s;
        }
        .nav-buttons a:hover { background-color: #0b7dda; }
    </style>
</head>
<body>

<header>
    <h1>Otobüs Bilet Platformu</h1>
    <?php if (isset($_SESSION['user_id'])): ?>
        <span>Hoş geldin, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
        <a class="logout" href="logout.php">Çıkış yap</a>
    <?php else: ?>
        <a class="logout" href="login.php">Giriş Yap / Kayıt Ol</a>
    <?php endif; ?>
</header>

<div class="container">
    <!-- 🔹 Navigasyon butonları -->
    <div class="nav-buttons">
        <a href="login.php">Giriş / Kayıt</a>
        <a href="logout.php">Çıkış</a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
            <a href="my_tickets.php">Biletlerim</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'company'): ?>
            <a href="trips_list.php">Sefer Listesi</a>
            <a href="create_coupon.php">Kupon Olusturma</a>
            <a href="coupons_list.php">Kupon Listesi</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="add_company.php">Şirket Ekle</a>
            <a href="manage_roles.php">Rol Yönetimi</a>
            <a href="create_coupon.php">Kupon Olusturma</a>
            <a href="coupons_list.php">Kupon Listesi</a>
        <?php endif; ?>
    </div>

    <h2>Sefer Ara</h2>
    <form method="POST">
        <input type="text" name="departure" placeholder="Kalkış Şehri" required>
        <input type="text" name="arrival" placeholder="Varış Şehri" required>
        <button type="submit" name="search_trip">Ara</button>
    </form>

    <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($trips): ?>
        <table>
            <tr>
                <th>Firma</th>
                <th>Kalkış</th>
                <th>Varış</th>
                <th>Tarih</th>
                <th>Saat</th>
                <th>Fiyat</th>
                <th>İşlem</th>
            </tr>
            <?php foreach ($trips as $trip): ?>
                <tr>
                    <td><?= htmlspecialchars($trip['company_name']); ?></td>
                    <td><?= htmlspecialchars($trip['departure_city']); ?></td>
                    <td><?= htmlspecialchars($trip['destination_city']); ?></td>
                    <td><?= date('d-m-Y', strtotime($trip['departure_time'])); ?></td>
                    <td><?= date('H:i', strtotime($trip['departure_time'])); ?></td>
                    <td><?= $trip['price']; ?> ₺</td>
                    <td>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                            <a class="btn-link" href="buy_ticket.php?trip_id=<?= $trip['id']; ?>">Bilet Al</a>
                        <?php else: ?>
                            <a class="btn-link" href="login.php">Giriş Yap</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="text-align:center; margin-top:20px;">Henüz sefer bulunmamaktadır.</p>
    <?php endif; ?>
</div>

</body>
</html>
