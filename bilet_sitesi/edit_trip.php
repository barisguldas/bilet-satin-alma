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

if (!in_array($current_user['role'], ['admin', 'company'])) {
    http_response_code(403);
    die("<h3 style='color:red; text-align:center;'>Bu sayfaya erişim yetkiniz yok!</h3>");
}

// Sefer ID kontrolü
if (!isset($_GET['id'])) {
    die("Geçersiz istek!");
}

$trip_id = $_GET['id'];

// Seferi getir
$stmt = $db->prepare("SELECT * FROM Trips WHERE id = :id");
$stmt->execute([':id' => $trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    die("Sefer bulunamadı!");
}

// Eğer kullanıcı firma yetkilisiyse sadece kendi firmasına ait seferleri düzenleyebilir
if ($current_user['role'] === 'company' && $trip['company_id'] !== $current_user['company_id']) {
    die("<h3 style='color:red; text-align:center;'>Bu seferi düzenleme yetkiniz yok!</h3>");
}

// Form gönderildiyse güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departure_city = $_POST['departure_city'];
    $destination_city = $_POST['destination_city'];
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = $_POST['price'];
    $capacity = $_POST['capacity'];

    // Firma yetkilisi sadece kendi seferini güncelleyebilir
    if ($current_user['role'] === 'company') {
        $update = $db->prepare("UPDATE Trips 
                                SET departure_city = :dep,
                                    destination_city = :dest,
                                    departure_time = :dep_time,
                                    arrival_time = :arr_time,
                                    price = :price,
                                    capacity = :cap
                                WHERE id = :id AND company_id = :company_id");
        $update->execute([
            ':dep' => $departure_city,
            ':dest' => $destination_city,
            ':dep_time' => $departure_time,
            ':arr_time' => $arrival_time,
            ':price' => $price,
            ':cap' => $capacity,
            ':id' => $trip_id,
            ':company_id' => $current_user['company_id']
        ]);
    } else {
        // Admin ise tüm seferleri güncelleyebilir
        $update = $db->prepare("UPDATE Trips 
                                SET departure_city = :dep,
                                    destination_city = :dest,
                                    departure_time = :dep_time,
                                    arrival_time = :arr_time,
                                    price = :price,
                                    capacity = :cap
                                WHERE id = :id");
        $update->execute([
            ':dep' => $departure_city,
            ':dest' => $destination_city,
            ':dep_time' => $departure_time,
            ':arr_time' => $arrival_time,
            ':price' => $price,
            ':cap' => $capacity,
            ':id' => $trip_id
        ]);
    }

    header("Location: trips_list.php?updated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Düzenle</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #eef2f3;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #333;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #555;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
        }

        button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            color: #4CAF50;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Sefer Düzenle</h2>
    <form method="POST">
        <label>Kalkış Şehri</label>
        <input type="text" name="departure_city" value="<?php echo htmlspecialchars($trip['departure_city']); ?>" required>

        <label>Varış Şehri</label>
        <input type="text" name="destination_city" value="<?php echo htmlspecialchars($trip['destination_city']); ?>" required>

        <label>Kalkış Zamanı</label>
        <input type="datetime-local" name="departure_time" value="<?php echo date('Y-m-d\TH:i', strtotime($trip['departure_time'])); ?>" required>

        <label>Varış Zamanı</label>
        <input type="datetime-local" name="arrival_time" value="<?php echo date('Y-m-d\TH:i', strtotime($trip['arrival_time'])); ?>" required>

        <label>Fiyat (₺)</label>
        <input type="number" name="price" value="<?php echo htmlspecialchars($trip['price']); ?>" required>

        <label>Koltuk Kapasitesi</label>
        <input type="number" name="capacity" value="<?php echo htmlspecialchars($trip['capacity']); ?>" required>

        <button type="submit">Değişiklikleri Kaydet</button>
    </form>

    <a href="trips_list.php">← Sefer Listesine Dön</a>
</div>
</body>
</html>
