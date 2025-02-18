<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Periksa apakah file diunggah tanpa error
    if (isset($_FILES["zipfile"]) && $_FILES["zipfile"]["error"] == 0) {
        $allowed = array("zip" => "application/x-zip-compressed");
        $filename = $_FILES["zipfile"]["name"];
        $filetype = $_FILES["zipfile"]["type"];
        $filesize = $_FILES["zipfile"]["size"];

        // Ambil tanggal mulai
        $start_date = null;
        if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
            $start_date = strtotime($_POST['start_date']);
        }

        // Ambil tanggal akhir
        $end_date = null;
        if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
            $end_date = strtotime($_POST['end_date'] . ' 23:59:59'); // Termasuk seluruh tanggal akhir
        }

        // Verifikasi tipe file
        if (!in_array($filetype, $allowed)) {
            die("Error: Silakan pilih file ZIP yang valid.");
        }

        // Verifikasi ukuran file - batas 50MB
        $maxsize = 50 * 1024 * 1024;
        if ($filesize > $maxsize) {
            die("Error: Ukuran file lebih besar dari batas yang diizinkan (50MB).");
        }

        // Buat direktori sementara untuk ekstraksi
        $temp_dir = "temp_" . time();
        mkdir($temp_dir);

        // Pindahkan file yang diunggah
        $upload_path = $temp_dir . "/" . $filename;
        move_uploaded_file($_FILES["zipfile"]["tmp_name"], $upload_path);

        // Ekstrak file ZIP
        $zip = new ZipArchive;
        $res = $zip->open($upload_path);
        
        if ($res === TRUE) {
            $zip->extractTo($temp_dir);
            $zip->close();

            $text_contents = [];
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_dir)
            );

            $messages = [];
            $current_message = null;

            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() == 'txt') {
                    $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
                    
                    foreach ($lines as $line) {
                        // Periksa apakah baris dimulai dengan pola tanggal (MM/DD/YY, HH:mm)
                        if (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{2,4},\s\d{1,2}:\d{2})\s-\s([^:]+):\s(.+)/', $line, $matches)) {
                            // Konversi tanggal pesan ke timestamp untuk perbandingan
                            $message_date = strtotime(str_replace(',', '', $matches[1]));
                            
                            // Lewati pesan di luar rentang tanggal
                            if (($start_date && $message_date < $start_date) || 
                                ($end_date && $message_date > $end_date)) {
                                $current_message = null;
                                continue;
                            }
                            
                            // Jika ada pesan sebelumnya, simpan
                            if ($current_message) {
                                $messages[] = $current_message;
                            }
                            
                            $sender = trim($matches[2]);
                            
                            // Mulai pesan baru
                            $current_message = [
                                'datetime' => $matches[1],
                                'sender' => $sender,
                                'message' => $matches[3],
                            ];
                        } elseif ($current_message) {
                            // Tambahkan ke pesan saat ini jika ini adalah lanjutan
                            $current_message['message'] .= "\n" . $line;
                        }
                    }
                    
                    // Tambahkan pesan terakhir
                    if ($current_message) {
                        $messages[] = $current_message;
                    }
                }
            }

            // Hapus direktori temporary dan isinya
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($temp_dir);

            $message_count = [];
            foreach ($messages as $message) {
                $sender = $message['sender'];
                if (!isset($message_count[$sender])) {
                    $message_count[$sender] = 0;
                }
                $message_count[$sender]++;
            }

            // Urutkan pengirim berdasarkan nama secara ascending
            ksort($message_count);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isi Chat WhatsApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-4">Isi Chat WhatsApp 
                <?php 
                if ($start_date && $end_date) {
                    echo "dari " . date('d F Y', $start_date) . " sampai " . date('d F Y', $end_date);
                } elseif ($start_date) {
                    echo "dari " . date('d F Y', $start_date);
                } elseif ($end_date) {
                    echo "sampai " . date('d F Y', $end_date);
                }
                ?>
            </h1>
            
            <div class="mb-4 p-4 bg-gray-50 rounded-md">
                <h2 class="text-sm font-medium text-gray-700 mb-2">Total Pengirim:</h2>
                <p class="text-sm text-gray-700"><?php echo count($message_count); ?> pengirim</p>
            </div>

            <div class="mb-4 p-4 bg-gray-50 rounded-md">
                <h2 class="text-sm font-medium text-gray-700 mb-2">Pesan per Pengirim:</h2>
                <ul class="list-disc pl-5">
                    <?php foreach ($message_count as $sender => $count): ?>
                        <li class="text-sm text-gray-700">
                            <?php echo htmlspecialchars($sender); ?>: <?php echo $count; ?> pesan
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal & Waktu</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengirim</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pesan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($messages as $message): ?>
                        <tr class="">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 align-top">
                                <?php echo htmlspecialchars($message['datetime']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 align-top">
                                <?php echo htmlspecialchars($message['sender']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 whitespace-pre-line align-top">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <a href="index.php" class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                Unggah File Lain
            </a>
        </div>
    </div>
</body>
</html>
<?php
        } else {
            echo "Gagal membuka file ZIP";
        }
    } else {
        echo "Error mengunggah file";
    }
} else {
    header("Location: index.php");
    exit();
}
?>
