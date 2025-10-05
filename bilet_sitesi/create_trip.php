<?php
session_start();
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_id = $_POST['company_id'];
    $departure_city = $_POST['departure_city'];
    $destination_city = $_POST['destination_city'];
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = $_POST['price'];
    $capacity = $_POST['capacity'];

    try {
        $stmt = $db->prepare("INSERT INTO Trips 
            (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity)
            VALUES (:id, :company_id, :departure_city, :destination_city, :departure_time, :arrival_time, :price, :capacity)");

        $id = uniqid('', true);
        $stmt->execute([
            ':id' => $id,
            ':company_id' => $company_id,
            ':departure_city' => $departure_city,
            ':destination_city' => $destination_city,
            ':departure_time' => $departure_time,
            ':arrival_time' => $arrival_time,   
            ':price' => $price,
            ':capacity' => $capacity
        ]);

        $success = "Yeni sefer başarıyla eklendi!";
    } catch (PDOException $e) {
        $error = "Hata: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Sefer Oluştur</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f6f9fc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .form-container {
            background: #fff;
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 400px;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #444;
            margin-bottom: 6px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: 0.2s;
        }

        input:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 4px rgba(74,144,226,0.4);
        }

        button {
            width: 100%;
            background-color: #4a90e2;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            transition: 0.2s;
        }

        button:hover {
            background-color: #357ABD;
        }

        .message {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .success {
            color: #28a745;
        }

        .error {
            color: #d9534f;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #4a90e2;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Yeni Sefer Oluştur</h2>

    <?php if (isset($success)): ?>
        <p class="message success"><?php echo $success; ?></p>
    <?php elseif (isset($error)): ?>
        <p class="message error"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Firma ID</label>
        <input type="text" name="company_id" required>

        <label>Kalkış Şehri</label>
        <input type="text" name="departure_city" required>

        <label>Varış Şehri</label>
        <input type="text" name="destination_city" required>

        <label>Kalkış Zamanı</label>
        <input type="datetime-local" name="departure_time" required>

        <label>Varış Zamanı</label>
        <input type="datetime-local" name="arrival_time" required>

        <label>Fiyat</label>
        <input type="number" name="price" required>

        <label>Kapasite</label>
        <input type="number" name="capacity" required>

        <button type="submit">Seferi Oluştur</button>
    </form>

    <a href="index.php">← Ana Sayfaya Dön</a>
</div>

</body>
</html>
