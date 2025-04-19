<?php
session_start();

// Check if messages exist in session
if (!isset($_SESSION['messages']) || empty($_SESSION['messages'])) {
    die("No chat messages available to download.");
}

$messages = $_SESSION['messages'];

function hide($text) {
    if(str_starts_with($text, '+62')) {
        $parts = explode('-', $text);
        if (count($parts) > 1) {
            return trim(end($parts));
        }
        return $text;
    }
    $parts = explode(' ', $text);
    if (count($parts) > 1) {
        return trim(end($parts));
    }
    return $text;
}

// Set headers for file download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="chat_export.txt"');

// Output each message
foreach ($messages as $message) {
    echo $message['datetime'] . " - " . hide($message['sender']) . ": " . $message['message'] . "\n";
}
?> 