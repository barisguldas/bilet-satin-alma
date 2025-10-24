<?php
session_start();
require_once 'includes/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔒 Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🧍 Kullanıcı bilgisi
$stmt = $db->prepare("SELECT full_name, balance FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Kullanıcı bulunamadı.");

// 🎟️ Kullanıcının biletleri
$stmt = $db->prepare("
    SELECT 
        t.id AS ticket_id,
        t.total_price,
        t.status,
        t.created_at,
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tr.arrival_time,
        tr.price,
        bc.name AS company_name,
        GROUP_CONCAT(bs.seat_number, ', ') AS seats
    FROM Tickets t
    JOIN Trips tr ON t.trip_id = tr.id
    JOIN Bus_Company bc ON tr.company_id = bc.id
    LEFT JOIN Booked_Seats bs ON t.id = bs.ticket_id
    WHERE t.user_id = :user_id
    GROUP BY t.id
    ORDER BY t.created_at DESC
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
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
            font-family: 'Segoe UI', sans-serif;
            background-color: #f2f3f7;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #1976d2;
            color: white;
            text-align: center;
            padding: 15px;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .ticket {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-left: 5px solid #1976d2;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
        }

        .ticket strong {
            color: #1976d2;
        }

        .ticket small {
            color: #555;
        }

        .no-ticket {
            text-align: center;
            font-size: 1.1em;
            color: #666;
            margin-top: 30px;
        }

        .balance {
            text-align: right;
            margin-bottom: 15px;
            font-size: 1.1em;
            color: #444;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            text-decoration: none;
            color: #1976d2;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
        }

        .ticket-actions {
            margin-top: 10px;
        }

        .ticket-actions a {
            display: inline-block;
            margin-right: 10px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            text-decoration: none;
            color: white;
        }

        .btn-pdf {
            background-color: #1976d2;
        }

        .btn-cancel {
            background-color: #d32f2f;
        }

        .btn-disabled {
            background-color: #aaa;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<header>
    <h1>Biletlerim</h1>
</header>

<div class="container">
    <?php if (isset($_GET['success'])): ?>
        <div class="success">✅ Bilet başarıyla satın alındı!</div>
    <?php elseif (isset($_GET['canceled'])): ?>
        <div class="success">❌ Bilet başarıyla iptal edildi, ücret hesabınıza iade edildi.</div>
    <?php endif; ?>

    <div class="balance">
        💰 Mevcut Bakiye: <strong><?= htmlspecialchars($user['balance']) ?> ₺</strong>
    </div>

    <h2>Satın Aldığınız Biletler</h2>

    <?php if (count($tickets) > 0): ?>
        <?php foreach ($tickets as $ticket): ?>
            <?php
                $departure_time = strtotime($ticket['departure_time']);
                $now = time();
                $time_diff = $departure_time - $now;
                $can_cancel = ($time_diff > 3600) && $ticket['status'] === 'active';
            ?>
            <div class="ticket">
                <strong><?= htmlspecialchars($ticket['company_name']) ?></strong><br>
                <?= htmlspecialchars($ticket['departure_city']) ?> → <?= htmlspecialchars($ticket['destination_city']) ?><br>
                 Kalkış: <?= date('d-m-Y H:i', $departure_time) ?><br>
                 Varış: <?= date('d-m-Y H:i', strtotime($ticket['arrival_time'])) ?><br>
                 Koltuk(lar): <?= htmlspecialchars($ticket['seats'] ?? '-') ?><br>
                 Ödenen Tutar: <?= htmlspecialchars($ticket['total_price']) ?> ₺<br>
                 Satın Alım: <?= date('d-m-Y H:i', strtotime($ticket['created_at'])) ?><br>
                 Durum: <?= htmlspecialchars($ticket['status']) ?>

                <div class="ticket-actions">
                    <a href="ticket_pdf.php?id=<?= urlencode($ticket['ticket_id']) ?>" class="btn-pdf">📄 PDF Görüntüle</a>

                    <?php if ($can_cancel): ?>
                        <a href="cancel_ticket.php?id=<?= urlencode($ticket['ticket_id']) ?>" class="btn-cancel" onclick="return confirm('Bu bileti iptal etmek istediğinize emin misiniz?')">❌ Bileti İptal Et</a>
                    <?php else: ?>
                        <a class="btn-cancel btn-disabled">⏰ İptal Edilemez</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-ticket">Henüz bir bilet satın almadınız.</div>
    <?php endif; ?>

    <a href="home.php" class="back-link">← Ana Sayfaya Dön</a>
</div>
</body>
</html>
