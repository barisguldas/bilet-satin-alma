<?php
require_once "includes/config.php"; // PDO bağlantısı burada olmalı

$message = ""; // kullanıcıya gösterilecek mesaj

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_id = uniqid('user_'); // Benzersiz ID

    if (empty($username) || empty($email) || empty($_POST['password'])) {
        $message = "Lütfen tüm alanları doldurun!";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO User (id, full_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $username, $email, $password, 'user'])) {
                $message = "Kayıt başarılı! Giriş yapabilirsiniz.";
            } else {
                $message = "Kayıt sırasında bir hata oluştu.";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                $message = "Bu e-posta zaten kayıtlı.";
            } else {
                $message = "Hata: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kayıt Ol</title>
<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f5f5f5;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}
.container {
    background-color: #fff;
    padding: 30px 40px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 400px;
}
h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #333;
}
input[type="text"],
input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 12px 15px;
    margin: 8px 0 16px 0;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
}
button {
    width: 100%;
    padding: 12px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
}
button:hover {
    background-color: #45a049;
}
.login-link {
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
}
.login-link a {
    color: #4CAF50;
    text-decoration: none;
}
.login-link a:hover {
    text-decoration: underline;
}
.message {
    text-align: center;
    margin-bottom: 15px;
    color: #4CAF50;
    font-weight: bold;
}
</style>
</head>
<body>
<div class="container">
<h2>Kayıt Ol</h2>

<?php if($message): ?>
    <div class="message"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<form action="register.php" method="post">
    <input type="text" name="username" placeholder="Kullanıcı Adı" required>
    <input type="email" name="email" placeholder="E-posta" required>
    <input type="password" name="password" placeholder="Şifre" required>
    <button type="submit">Kayıt Ol</button>
</form>

<div class="login-link">
    Zaten üye misiniz? <a href="login.php">Giriş Yap</a>
</div>
</div>
</body>
</html>
