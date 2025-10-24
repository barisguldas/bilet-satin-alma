<?php
// includes/config.php
$db = new PDO('sqlite:' . __DIR__ . '/../db/gorev_veritabani.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
