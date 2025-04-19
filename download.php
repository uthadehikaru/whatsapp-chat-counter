<?php
session_start();

// Check if messages exist in session
if (!isset($_SESSION['messages']) || empty($_SESSION['messages'])) {
    die("No chat messages available to download.");
}

$messages = $_SESSION['messages'];

// Set headers for file download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="chat_export.txt"');

// Output each message
foreach ($messages as $message) {
    echo $message['datetime'] . " - " . $message['sender'] . ": " . $message['message'] . "\n";
}
?> 