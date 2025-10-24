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
$stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Kullanıcı bulunamadı.");

// 🚍 Sefer bilgisi
if (!isset($_GET['trip_id'])) {
    die("Sefer ID belirtilmemiş.");
}

$stmt = $db->prepare("
    SELECT t.*, b.name AS company_name
    FROM Trips t
    JOIN Bus_Company b ON t.company_id = b.id
    WHERE t.id = :id
");
$stmt->execute([':id' => $_GET['trip_id']]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) die("Sefer bulunamadı.");

// 💺 Dolu koltukları çek
$stmt = $db->prepare("
    SELECT seat_number FROM Booked_Seats
    WHERE ticket_id IN (SELECT id FROM Tickets WHERE trip_id = :trip_id)
");
$stmt->execute([':trip_id' => $trip['id']]);
$booked_seats = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 🎟️ Kupon işlemleri
$discount = 0;
$applied_coupon = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $coupon_code = trim($_POST['coupon_code']);

    // Kuponu al ve hangi firmaya ait olduğunu öğren
    $stmt = $db->prepare("SELECT * FROM Coupons WHERE code = :code");
    $stmt->execute([':code' => $coupon_code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        $error = "Kupon bulunamadı.";
    } elseif (strtotime($coupon['expire_date']) < time()) {
        $error = "Kuponun süresi dolmuş.";
    } elseif ($coupon['company_id'] && $coupon['company_id'] !== $trip['company_id'] && $user['role'] !== 'admin') {
        // Adminler tüm kuponları kullanabilir
        $error = "Bu kupon bu firmaya ait değil.";
    } else {
        // Kullanım sayısını kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = :cid");
        $stmt->execute([':cid' => $coupon['id']]);
        $usage_count = $stmt->fetchColumn();

        if ($usage_count >= $coupon['usage_limit']) {
            $error = "Kupon kullanım limiti dolmuş.";
        } else {
            $discount = $coupon['discount'];
            $applied_coupon = $coupon;
            $success = "Kupon başarıyla uygulandı! %" . $discount . " indirim.";
        }
    }
}


// POST işlemi: Bilet satın al
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seat_number'])) {
    $seat_number = (int)$_POST['seat_number'];
    $price = (float)$trip['price'];

    // Uygulanan kupon varsa indirimi uygula
    if (!empty($_POST['applied_coupon_id'])) {
        $stmt = $db->prepare("SELECT * FROM Coupons WHERE id = :id");
        $stmt->execute([':id' => $_POST['applied_coupon_id']]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($coupon) {
            $price = $price * (1 - ($coupon['discount'] / 100));
        }
    }

    if (in_array($seat_number, $booked_seats)) {
        $error = "Bu koltuk zaten alınmış.";
    } elseif ($user['balance'] < $price) {
        $error = "Yetersiz bakiye. Lütfen bakiyenizi artırın.";
    } else {
        // 🧾 Yeni ticket oluştur
        $ticket_id = uniqid("tkt_");
        $stmt = $db->prepare("INSERT INTO Tickets (id, trip_id, user_id, total_price) VALUES (:id, :trip_id, :user_id, :price)");
        $stmt->execute([
            ':id' => $ticket_id,
            ':trip_id' => $trip['id'],
            ':user_id' => $user['id'],
            ':price' => $price
        ]);

        // 💺 Koltuğu kaydet
        $stmt = $db->prepare("INSERT INTO Booked_Seats (id, ticket_id, seat_number) VALUES (:id, :ticket_id, :seat)");
        $stmt->execute([
            ':id' => uniqid("seat_"),
            ':ticket_id' => $ticket_id,
            ':seat' => $seat_number
        ]);

        // 💰 Kullanıcı bakiyesini düş
        $stmt = $db->prepare("UPDATE User SET balance = balance - :price WHERE id = :id");
        $stmt->execute([':price' => $price, ':id' => $user['id']]);

        // 🎟️ Kupon kullanıldıysa kaydet
        if (!empty($_POST['applied_coupon_id'])) {
            $stmt = $db->prepare("INSERT INTO User_Coupons (id, coupon_id, user_id) VALUES (:id, :coupon_id, :user_id)");
            $stmt->execute([
                ':id' => uniqid("ucp_"),
                ':coupon_id' => $_POST['applied_coupon_id'],
                ':user_id' => $user['id']
            ]);
        }

        header("Location: my_tickets.php?success=1");
        exit;
    }
}

$total_seats = (int)$trip['capacity'];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Bilet Satın Al</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #f2f3f7; margin: 0; }
    header { background: #4CAF50; color: white; text-align: center; padding: 15px; }
    .container { max-width: 900px; margin: 40px auto; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
    h2 { text-align: center; }
    .trip-info { text-align: center; background: #e8f5e9; border-radius: 10px; padding: 10px; margin-bottom: 20px; }
    .balance { text-align: center; margin-bottom: 15px; }
    .coupon { text-align: center; margin-bottom: 20px; }
    .coupon input { padding: 8px; border: 1px solid #ccc; border-radius: 6px; width: 180px; }
    .seat-map { display: flex; flex-direction: column; align-items: center; gap: 15px; }
    .row { display: flex; gap: 15px; }
    .left, .right { display: flex; gap: 10px; }
    .aisle { width: 40px; }
    .seat { width: 50px; height: 50px; background: #4CAF50; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
    .seat:hover { background: #45a049; }
    .seat.booked { background: #d32f2f; cursor: not-allowed; opacity: 0.6; }
    .seat.selected { background: #1976d2; }
    .error, .success { text-align: center; margin-top: 15px; font-weight: bold; }
    .error { color: red; }
    .success { color: green; }
</style>
</head>
<body>
<header><h1>Bilet Satın Al</h1></header>

<div class="container">
    <div class="trip-info">
        <strong><?= htmlspecialchars($trip['company_name']) ?></strong><br>
        <?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['destination_city']) ?><br>
        <?= date('d-m-Y H:i', strtotime($trip['departure_time'])) ?><br>
        Fiyat: <?= htmlspecialchars($trip['price']) ?> ₺
    </div>

    <div class="balance">💰 Bakiye: <strong><?= htmlspecialchars($user['balance']) ?> ₺</strong></div>

    <form method="POST">
        <div class="coupon">
            🎟️ Kupon Kodu:
            <input type="text" name="coupon_code" value="<?= htmlspecialchars($_POST['coupon_code'] ?? '') ?>">
            <button type="submit" name="apply_coupon">Uygula</button>
        </div>

        <?php if (isset($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>

        <?php if ($applied_coupon): ?>
            <input type="hidden" name="applied_coupon_id" value="<?= htmlspecialchars($applied_coupon['id']) ?>">
        <?php endif; ?>

        <div class="seat-map">
            <?php
            $seat = 1;
            for ($row = 1; $row <= ceil($total_seats / 4); $row++):
            ?>
                <div class="row">
                    <div class="left">
                        <?php for ($i = 0; $i < 2 && $seat <= $total_seats; $i++, $seat++): 
                            $is_booked = in_array($seat, $booked_seats); ?>
                            <button type="submit" name="seat_number" value="<?= $seat ?>"
                                class="seat <?= $is_booked ? 'booked' : '' ?>" <?= $is_booked ? 'disabled' : '' ?>>
                                <?= $seat ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="aisle"></div>
                    <div class="right">
                        <?php for ($i = 0; $i < 2 && $seat <= $total_seats; $i++, $seat++): 
                            $is_booked = in_array($seat, $booked_seats); ?>
                            <button type="submit" name="seat_number" value="<?= $seat ?>"
                                class="seat <?= $is_booked ? 'booked' : '' ?>" <?= $is_booked ? 'disabled' : '' ?>>
                                <?= $seat ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </form>
</div>
</body>
</html>
