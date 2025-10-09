<?php
session_start();
require_once 'includes/config.php';

// Hata gösterimi (geliştirme için)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT * FROM User WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    die("Kullanıcı bulunamadı.");
}

// Sadece firma yetkilileri erişebilsin
if ($current_user['role'] !== 'company') {
    http_response_code(403);
    die("<h3 style='color:red; text-align:center;'>Bu sayfaya sadece firma yetkilileri erişebilir.</h3>");
}

// Kullanıcının bağlı olduğu firmayı çek
if (empty($current_user['company_id'])) {
    die("<h3 style='color:red; text-align:center;'>Bu kullanıcıya ait bir firma bulunamadı. Lütfen yöneticinize başvurun.</h3>");
}

$stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = :id");
$stmt->execute([':id' => $current_user['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die("<h3 style='color:red; text-align:center;'>Firma bilgisi bulunamadı.</h3>");
}

// Sefer ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departure_city = trim($_POST['departure_city']);
    $destination_city = trim($_POST['destination_city']);
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = $_POST['price'];
    $capacity = $_POST['capacity'];

    if (!$departure_city || !$destination_city || !$departure_time || !$arrival_time || !$price || !$capacity) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        $stmt = $db->prepare("INSERT INTO Trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity)
                              VALUES (:id, :company_id, :dep, :dest, :dep_time, :arr_time, :price, :cap)");
        $stmt->execute([
            ':id' => uniqid(),
            ':company_id' => $current_user['company_id'], // otomatik firma id
            ':dep' => $departure_city,
            ':dest' => $destination_city,
            ':dep_time' => $departure_time,
            ':arr_time' => $arrival_time,
            ':price' => $price,
            ':cap' => $capacity
        ]);
        header("Location: trips_list.php?created=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Sefer Ekle</title>
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
            padding: 15px;
            text-align: center;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        button {
            width: 100%;
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background-color: #45a049;
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 10px;
        }

        .company-info {
            text-align: center;
            background-color: #e8f5e9;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
<header>
    <h1>Yeni Sefer Oluştur</h1>
</header>

<div class="container">
    <div class="company-info">
        Firma: <?= htmlspecialchars($company['name']) ?>
    </div>

    <?php if (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="departure_city">Kalkış Şehri</label>
        <input type="text" id="departure_city" name="departure_city" required>

        <label for="destination_city">Varış Şehri</label>
        <input type="text" id="destination_city" name="destination_city" required>

        <label for="departure_time">Kalkış Zamanı</label>
        <input type="datetime-local" id="departure_time" name="departure_time" required>

        <label for="arrival_time">Varış Zamanı</label>
        <input type="datetime-local" id="arrival_time" name="arrival_time" required>

        <label for="price">Bilet Fiyatı (₺)</label>
        <input type="number" id="price" name="price" required min="0" step="0.01">

        <label for="capacity">Kapasite</label>
        <input type="number" id="capacity" name="capacity" required min="1">

        <button type="submit">Seferi Kaydet</button>
    </form>
</div>
</body>
</html>
