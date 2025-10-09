<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcıyı çek
$stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Kullanıcı bulunamadı.");
}

// 🎯 Bilet iptal işlemi
if (isset($_GET['cancel_ticket'])) {
    $ticket_id = $_GET['cancel_ticket'];

    // Bilet bilgilerini çek
    $stmt = $db->prepare("
        SELECT t.id, t.total_price, tr.departure_time 
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        WHERE t.id = :tid AND t.user_id = :uid
    ");
    $stmt->execute([
        ':tid' => $ticket_id,
        ':uid' => $user['id']
    ]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ticket) {
        $departure_time = strtotime($ticket['departure_time']);
        $current_time = time();

        // 🚫 Eğer yolculuğa 1 saatten az kalmışsa iptal yasak
        if ($departure_time - $current_time < 3600) {
            $error_message = "Yolculuğa 1 saatten az kaldığı için bu bileti iptal edemezsiniz.";
        } else {
            // 💰 İade işlemi: bilet fiyatı kullanıcıya geri yüklenir
            $db->beginTransaction();

            try {
                // Kullanıcının bakiyesini güncelle
                $updateBalance = $db->prepare("UPDATE User SET balance = balance + :amount WHERE id = :uid");
                $updateBalance->execute([
                    ':amount' => $ticket['total_price'],
                    ':uid' => $user['id']
                ]);

                // Bilet ve koltuk kaydını sil
                $deleteSeats = $db->prepare("DELETE FROM Booked_Seats WHERE ticket_id = :tid");
                $deleteSeats->execute([':tid' => $ticket_id]);

                $deleteTicket = $db->prepare("DELETE FROM Tickets WHERE id = :tid");
                $deleteTicket->execute([':tid' => $ticket_id]);

                $db->commit();

                header("Location: my_tickets.php?cancelled=1");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "Bilet iptali sırasında bir hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Kullanıcının biletlerini çek
$stmt = $db->prepare("
    SELECT t.id AS ticket_id, t.total_price, tr.departure_city, tr.destination_city,
           tr.departure_time, tr.arrival_time, b.name AS company_name
    FROM Tickets t
    JOIN Trips tr ON t.trip_id = tr.id
    JOIN Bus_Company b ON tr.company_id = b.id
    WHERE t.user_id = :uid
    ORDER BY tr.departure_time DESC
");
$stmt->execute([':uid' => $user['id']]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletlerim</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #eef2f7;
            margin: 0;
            padding: 0;
        }
        header {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            text-align: center;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
        }
        h2 {
            text-align: center;
            margin-bottom: 15px;
        }
        .balance {
            text-align: right;
            margin-bottom: 20px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        th {
            background-color: #f8f8f8;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .cancel-btn {
            background-color: #f44336;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
        }
        .cancel-btn:hover {
            background-color: #d32f2f;
        }
        .alert {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
<header>
    <h1>Biletlerim</h1>
</header>

<div class="container">
    <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert">Bilet başarıyla iptal edildi ve ücret bakiyenize eklendi.</div>
    <?php elseif (isset($error_message)): ?>
        <div class="error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="balance">Bakiyeniz: <?= $user['balance'] ?> ₺</div>

    <h2>Satın Aldığınız Biletler</h2>

    <table>
        <tr>
            <th>Firma</th>
            <th>Kalkış</th>
            <th>Varış</th>
            <th>Kalkış Zamanı</th>
            <th>Varış Zamanı</th>
            <th>Fiyat</th>
            <th>İşlem</th>
        </tr>
        <?php if (count($tickets) > 0): ?>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= htmlspecialchars($ticket['company_name']) ?></td>
                    <td><?= htmlspecialchars($ticket['departure_city']) ?></td>
                    <td><?= htmlspecialchars($ticket['destination_city']) ?></td>
                    <td><?= date('d-m-Y H:i', strtotime($ticket['departure_time'])) ?></td>
                    <td><?= date('d-m-Y H:i', strtotime($ticket['arrival_time'])) ?></td>
                    <td><?= htmlspecialchars($ticket['total_price']) ?> ₺</td>
                    <td>
                        <?php if (strtotime($ticket['departure_time']) - time() >= 3600): ?>
                            <a href="?cancel_ticket=<?= $ticket['ticket_id'] ?>" class="cancel-btn" onclick="return confirm('Bu bileti iptal etmek istediğinize emin misiniz?')">İptal Et</a>
                        <?php else: ?>
                            <span style="color: gray;">İptal Edilemez</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7">Henüz satın aldığınız bilet bulunmuyor.</td></tr>
        <?php endif; ?>
    </table>
</div>

</body>
</html>
