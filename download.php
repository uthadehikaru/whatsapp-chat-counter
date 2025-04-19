<?php
session_start();

// Check if messages exist in session
if (!isset($_SESSION['messages']) || empty($_SESSION['messages'])) {
    die("No chat messages available to download.");
}

$messages = $_SESSION['messages'];
$members = $_SESSION['members'] ?? [];

// Create temporary directory
$temp_dir = sys_get_temp_dir() . '/chat_export_' . time();
mkdir($temp_dir);

// Create chat messages file
$chat_file = $temp_dir . '/chat_messages.txt';
$chat_content = '';
foreach ($messages as $message) {
    $chat_content .= $message['datetime'] . " - " . $message['sender'] . ": " . $message['message'] . "\n";
}
file_put_contents($chat_file, $chat_content);

// Create member list CSV
$csv_file = $temp_dir . '/member_list.csv';
$csv_handle = fopen($csv_file, 'w');
foreach ($members as $member) {
    fputcsv($csv_handle, [$member]);
}
fclose($csv_handle);

// Create ZIP file
$zip_file = $temp_dir . '/chat_export.zip';
$zip = new ZipArchive();
if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
    $zip->addFile($chat_file, 'chat_messages.txt');
    $zip->addFile($csv_file, 'member_list.csv');
    $zip->close();
}

// Set headers for ZIP file download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="chat_export.zip"');
header('Content-Length: ' . filesize($zip_file));

// Output the ZIP file
readfile($zip_file);

// Clean up temporary files
unlink($chat_file);
unlink($csv_file);
unlink($zip_file);
rmdir($temp_dir);
?> 