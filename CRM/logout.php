<?php
session_start(); // Accedi alla sessione
session_unset(); // Rimuovi tutte le variabili di sessione
session_destroy(); // Distruggi la sessione
header("Location: login.php"); // Reindirizza alla pagina di login
exit;
?>