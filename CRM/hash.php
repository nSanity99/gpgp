<?php
require_once 'db_config.php';
$password_per_admin = 'users';
$nuovo_hash_corretto = password_hash($password_per_admin, PASSWORD_DEFAULT);
echo "Il nuovo hash CORRETTO per la password 'admin' è: <br><b>" . htmlspecialchars($nuovo_hash_corretto) . "</b><br>";
echo "Lunghezza: " . strlen($nuovo_hash_corretto);
?>