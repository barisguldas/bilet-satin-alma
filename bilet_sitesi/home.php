<?php
session_start();
require_once 'includes/config.php'; // Veritabanı bağlantısı

$trips = [];
$message = "";

// Form gönderildiyse filtreleme
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
    // Form gönderilmemişse tüm seferleri göster
    $stmt = $db->query("SELECT t.*, b.name AS company_name 
                        FROM Trips t 
                        JOIN Bus_Company b ON t.company_id = b.id
                        ORDER BY t.departure_time ASC");
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Kullanıcı rolünü sessiona eklediyseniz kullanın
if(isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    $stmt_role = $db->prepare("SELECT role FROM User WHERE id = :id");
    $stmt_role->execute([':id' => $_SESSION['user_id']]);
    $user_role = $stmt_role->fetchColumn();
    $_SESSION['role'] = $user_role;
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
        .logout { position: absolute; top: 15px; right: 15px; }
        .btn-link { text-decoration: none; color: #4CAF50; font-weight: bold; }
        .btn-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header>
    <h1>Otobüs Bilet Platformu</h1>
    <?php if(isset($_SESSION['user_id'])): ?>
        <span>Hoş geldin, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
        <a class="logout" href="logout.php" style="color:white;">Çıkış yap</a>
    <?php else: ?>
        <a class="logout" href="login.php" style="color:white;">Giriş Yap / Kayıt Ol</a>
    <?php endif; ?>
</header>

<div class="container">
    <h2>Sefer Ara</h2>
    <form method="POST">
        <input type="text" name="departure" placeholder="Kalkış Şehri" required>
        <input type="text" name="arrival" placeholder="Varış Şehri" required>
        <button type="submit" name="search_trip">Ara</button>
    </form>

    <?php if($message): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if($trips): ?>
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
            <?php foreach($trips as $trip): ?>
                <tr>
                    <td><?php echo htmlspecialchars($trip['company_name']); ?></td>
                    <td><?php echo htmlspecialchars($trip['departure_city']); ?></td>
                    <td><?php echo htmlspecialchars($trip['destination_city']); ?></td>
                    <td><?php echo date('d-m-Y', strtotime($trip['departure_time'])); ?></td>
                    <td><?php echo date('H:i', strtotime($trip['departure_time'])); ?></td>
                    <td><?php echo $trip['price']; ?> ₺</td>
                    <td>
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                            <a class="btn-link" href="buy_ticket.php?trip_id=<?php echo $trip['id']; ?>">Bilet Al</a>
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
