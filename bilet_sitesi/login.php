<?php
session_start();
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM User WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        header('Location: home.php');
        exit;
    } else {
        echo "<p>Hatalı e-posta veya şifre!</p>";
    }
}
?>

<form method="POST">
    <h2>Giriş Yap</h2>
    <label>E-posta: <input type="email" name="email" required></label><br>
    <label>Şifre: <input type="password" name="password" required></label><br>
    <button type="submit">Giriş Yap</button>
</form>
