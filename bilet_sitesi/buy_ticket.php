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

// POST işlemi: Bilet satın al
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seat_number'])) {
    $seat_number = (int)$_POST['seat_number'];
    $price = (float)$trip['price'];

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

        header("Location: my_tickets.php?success=1");
        exit;
    }
}

// Koltuk kapasitesi (örneğin 30 koltuk)
$total_seats = (int)$trip['capacity'];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Satın Al</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f2f3f7;
            margin: 0;
            padding: 0;
        }

        header {
            background-color: #4CAF50;
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
        }

        .trip-info {
            text-align: center;
            background-color: #e8f5e9;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 20px;
        }

        .balance {
            text-align: center;
            font-size: 1.1em;
            margin-bottom: 15px;
        }

        .coupon {
            text-align: center;
            margin-bottom: 20px;
        }

        .coupon input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            width: 180px;
        }

        .seat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            justify-items: center;
        }

        .seat {
            width: 60px;
            height: 60px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        .seat:hover {
            background-color: #45a049;
        }

        .seat.booked {
            background-color: #d32f2f;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .seat.selected {
            background-color: #1976d2;
        }

        .error {
            text-align: center;
            color: red;
            margin-top: 15px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: #4CAF50;
        }
    </style>
</head>
<body>
<header>
    <h1>Bilet Satın Al</h1>
</header>

<div class="container">
    <div class="trip-info">
        <strong><?= htmlspecialchars($trip['company_name']) ?></strong><br>
        <?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['destination_city']) ?><br>
        <?= date('d-m-Y H:i', strtotime($trip['departure_time'])) ?><br>
        Fiyat: <?= htmlspecialchars($trip['price']) ?> ₺
    </div>

    <div class="balance">
        💰 Bakiye: <strong><?= htmlspecialchars($user['balance']) ?> ₺</strong>
    </div>

    <div class="coupon">
        (Yakında) 🎟️ Kupon Kodu: <input type="text" placeholder="Kupon gir... (yakında)">
    </div>

    <form method="POST">
        <div class="seat-grid">
            <?php for ($i = 1; $i <= $total_seats; $i++): ?>
                <?php $is_booked = in_array($i, $booked_seats); ?>
                <button type="submit" name="seat_number" value="<?= $i ?>" 
                        class="seat <?= $is_booked ? 'booked' : '' ?>"
                        <?= $is_booked ? 'disabled' : '' ?>>
                    <?= $i ?>
                </button>
            <?php endfor; ?>
        </div>
    </form>

    <?php if (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <a href="home.php" class="back-link">← Ana Sayfaya Dön</a>
</div>
</body>
</html>
