<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<h2>Hoş geldin, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
<p><a href="logout.php">Çıkış yap</a></p>
