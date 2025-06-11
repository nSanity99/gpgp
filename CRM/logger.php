<?php
require_once 'db_config.php';
function logUserAction($message) {
    $logFile = __DIR__ . '/user_actions.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}
?>
