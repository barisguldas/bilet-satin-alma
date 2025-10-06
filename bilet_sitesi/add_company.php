<?php
session_start();
require_once 'includes/config.php';

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcı bilgilerini al
$stmt = $db->prepare('SELECT * FROM User WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Sadece adminler erişebilsin
if ($current_user['role'] !== 'admin') {
    http_response_code(403);
    die("<h3 style='color:red; text-align:center;'>Bu sayfaya erişim yetkiniz yok!</h3>");
}

// Firma ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $logo_path = null;

    // logo yükleme (opsiyonel)
    if (!empty($_FILES['logo']['name'])) {
        $target_dir = "uploads/logos/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = uniqid() . "_" . basename($_FILES['logo']['name']);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
            $logo_path = $target_file;
        }
    }

    if ($company_name === '') {
        $error = "Lütfen firma adını giriniz.";
    } else {
        try {
            $stmt = $db->prepare('INSERT INTO Bus_Company (id, name, logo_path) VALUES (?, ?, ?)');
            $stmt->execute([uniqid(), $company_name, $logo_path]);
            $success = "Yeni otobüs firması başarıyla eklendi!";
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $error = "Bu firma adı zaten mevcut.";
            } else {
                $error = "Bir hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Firma Ekle</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background-color: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #fff;
            padding: 35px 45px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 420px;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: 500;
            color: #555;
        }

        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            margin-top: 25px;
            background-color: #007bff;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background-color: #0056b3;
        }

        .message {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }

        .error {
            color: red;
        }

        .success {
            color: green;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #007bff;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Yeni Otobüs Firması Ekle</h2>

    <?php if (!empty($error)) echo "<p class='message error'>$error</p>"; ?>
    <?php if (!empty($success)) echo "<p class='message success'>$success</p>"; ?>

    <form method="POST" enctype="multipart/form-data">
        <label for="company_name">Firma Adı</label>
        <input type="text" name="company_name" id="company_name" required>

        <label for="logo">Firma Logosu (opsiyonel)</label>
        <input type="file" name="logo" id="logo" accept="image/*">

        <button type="submit">Firmayı Ekle</button>
    </form>

    <a class="back" href="dashboard.php">← Geri Dön</a>
</div>
</body>
</html>
