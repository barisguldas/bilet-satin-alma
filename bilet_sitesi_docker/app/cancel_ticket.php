<?php
// cancel_tickets.php
session_start();
require_once 'includes/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcı bilgisi
$stmt = $db->prepare("SELECT id, full_name, balance FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Kullanıcı bulunamadı.");

// --- Bilet iptal işlemi (POST) ---
$successMsg = null;
$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket_id'])) {
    $ticketId = $_POST['cancel_ticket_id'];

    // Bileti çek
    $stmt = $db->prepare("
        SELECT t.*, tr.departure_time 
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        WHERE t.id = :tid AND t.user_id = :uid
    ");
    $stmt->execute([':tid' => $ticketId, ':uid' => $user['id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $errorMsg = "Bilet bulunamadı.";
    } elseif ($ticket['status'] !== 'active') {
        $errorMsg = "Bu bilet zaten iptal edilmiş.";
    } else {
        // 1 saat kontrolü
        $departure = new DateTime($ticket['departure_time']);
        $now = new DateTime();
        $diff = $departure->getTimestamp() - $now->getTimestamp();

        if ($diff < 3600) {
            $errorMsg = "Kalkışa 1 saatten az kaldığı için iptal edilemez.";
        } else {
            // İptal işlemi
            $db->beginTransaction();
            try {
                // Bilet durumunu güncelle
                $update = $db->prepare("UPDATE Tickets SET status = 'canceled' WHERE id = :tid");
                $update->execute([':tid' => $ticketId]);

                // Koltukları boşalt
                $delSeats = $db->prepare("DELETE FROM Booked_Seats WHERE ticket_id = :tid");
                $delSeats->execute([':tid' => $ticketId]);

                // Kullanıcının bakiyesini iade et
                $refund = $db->prepare("UPDATE User SET balance = balance + :price WHERE id = :uid");
                $refund->execute([':price' => $ticket['total_price'], ':uid' => $user['id']]);

                $db->commit();
                $successMsg = "Bilet başarıyla iptal edildi. Ücret bakiyenize iade edildi.";
            } catch (Exception $e) {
                $db->rollBack();
                $errorMsg = "İptal sırasında hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Kullanıcının biletlerini çek
$stmt = $db->prepare("
    SELECT 
        t.id AS ticket_id,
        t.total_price,
        t.status,
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tr.arrival_time,
        bc.name AS company_name,
        GROUP_CONCAT(bs.seat_number, ', ') AS seats
    FROM Tickets t
    JOIN Trips tr ON t.trip_id = tr.id
    JOIN Bus_Company bc ON tr.company_id = bc.id
    LEFT JOIN Booked_Seats bs ON t.id = bs.ticket_id
    WHERE t.user_id = :uid
    GROUP BY t.id
    ORDER BY tr.departure_time ASC
");
$stmt->execute([':uid' => $user['id']]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bilet İptal Paneli</title>
<style>
    body { font-family: Arial, sans-serif; background:#f5f7fa; margin:0; padding:0; }
    header { background:#4CAF50; color:#fff; padding:14px 10px; text-align:center; }
    .container { max-width:1000px; margin:30px auto; background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.08); }
    .top { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px; }
    .balance { font-weight:700; color:#333; }
    .msg { padding:10px; border-radius:6px; margin-bottom:12px; text-align:center; }
    .success { background:#d4edda; color:#155724; }
    .error { background:#f8d7da; color:#721c24; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:12px; border:1px solid #e6e8eb; text-align:center; }
    th { background:#fafafa; }
    tr:hover { background:#fbfdff; }
    .btn { display:inline-block; padding:8px 12px; border-radius:6px; color:#fff; text-decoration:none; cursor:pointer; border:none; }
    .cancel-btn { background:#f44336; }
    .cancel-btn:hover { background:#d32f2f; }
    .disabled { color:gray; }
    @media (max-width:720px){ .top { flex-direction:column; align-items:flex-start } table { font-size:14px } }
</style>
</head>
<body>
<header><h1>Bilet İptal Paneli</h1></header>

<div class="container">
    <div class="top">
        <div>
            <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
            <span class="balance">Bakiye: <?= htmlspecialchars($user['balance']) ?> ₺</span>
        </div>
        <div>
            <a href="my_tickets.php">← Biletlerim</a>
        </div>
    </div>

    <?php if($successMsg): ?>
        <div class="msg success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
        <div class="msg error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Firma</th>
            <th>Kalkış</th>
            <th>Varış</th>
            <th>Kalkış Zamanı</th>
            <th>Koltuk(lar)</th>
            <th>Fiyat (₺)</th>
            <th>Durum</th>
            <th>İşlem</th>
        </tr>

        <?php if(count($tickets) === 0): ?>
            <tr><td colspan="8">Henüz biletiniz yok.</td></tr>
        <?php else: ?>
            <?php foreach($tickets as $tk): ?>
                <?php
                    $departure = new DateTime($tk['departure_time']);
                    $now = new DateTime();
                    $canCancel = ($tk['status'] === 'active' && $departure->getTimestamp() - $now->getTimestamp() >= 3600);
                ?>
                <tr>
                    <td><?= htmlspecialchars($tk['company_name']) ?></td>
                    <td><?= htmlspecialchars($tk['departure_city']) ?></td>
                    <td><?= htmlspecialchars($tk['destination_city']) ?></td>
                    <td><?= $departure->format('d-m-Y H:i') ?></td>
                    <td><?= htmlspecialchars($tk['seats'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($tk['total_price']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($tk['status'])) ?></td>
                    <td>
                        <?php if($canCancel): ?>
                            <form method="POST" onsubmit="return confirm('Bu bileti iptal etmek istediğinize emin misiniz?');" style="margin:0;">
                                <input type="hidden" name="cancel_ticket_id" value="<?= htmlspecialchars($tk['ticket_id']) ?>">
                                <button type="submit" class="btn cancel-btn">İptal Et</button>
                            </form>
                        <?php elseif($tk['status'] === 'canceled'): ?>
                            <span class="disabled">İptal Edildi</span>
                        <?php else: ?>
                            <span class="disabled">İptal Süresi Geçti</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
