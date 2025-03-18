<?php
session_start();

if(isset($_GET['clear']) && $_GET['clear'] == 'true'){
    unset($_SESSION['messages']);
}
if (isset($_SESSION['messages'])) {
    header("Location: process.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengolah Chat WhatsApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-4">Unggah Chat WhatsApp</h1>
            
            <form action="process.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Pilih file ZIP:</label>
                    <input type="file" name="zipfile" accept=".zip" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">Ukuran maksimal file: 50MB</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tanggal Mulai:</label>
                    <input type="date" name="start_date" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tanggal Akhir:</label>
                    <input type="date" name="end_date" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Daftar Anggota (optional)</label>
                    <textarea name="members" 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Masukkan nomor/nama anggota, pisahkan dengan koma"></textarea>
                </div>
                <button type="submit" 
                        class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    Unggah dan Proses
                </button>
            </form>
        </div>
    </div>
</body>
</html>
