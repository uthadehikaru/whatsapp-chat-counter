<?php
session_start(); // Start the session

$messages = $_SESSION['messages'] ?? [];
$start_date = $_SESSION['start_date'] ?? null;
$end_date = $_SESSION['end_date'] ?? null;
$members = $_SESSION['members'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_members'])) {
    $members = explode(',', $_POST['members']);
    $members = array_map('trim', $members);
    $_SESSION['members'] = $members;
}elseif($_SERVER["REQUEST_METHOD"] == "POST"){
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

        // Ambil daftar anggota
        $members = isset($_POST['members']) ? explode(',', $_POST['members']) : [];
        $members = array_map('trim', $members);

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

            $_SESSION['messages'] = $messages; // Store messages in session
            $_SESSION['start_date'] = $start_date;
            $_SESSION['end_date'] = $end_date;
            $_SESSION['members'] = $members;

        }else{
            echo "Gagal membuka file ZIP";
            exit();
        }
    }else{
        echo "Error mengunggah file";
        exit();
    }
}

$message_count = [];
if($members){
    foreach($members as $member){
        $message_count[$member] = 0;
    }
}

// Count messages for each sender
foreach ($messages as $message) {
    $sender = $message['sender'];
    if (!isset($message_count[$sender])) {
        $message_count[$sender] = 0;
    }
    $message_count[$sender]++;
}

$sort = $_GET['sort'] ?? 'asc';
if($sort){
    if($sort == 'asc'){
        asort($message_count);
    }elseif($sort == 'desc'){
        arsort($message_count);
    }elseif($sort == 'sender'){
        ksort($message_count);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isi Chat WhatsApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.css">
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.js"></script>
    <!-- Include DataTables Responsive CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css">
    <!-- Include DataTables Responsive JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script>

        function toggleMessageCount() {
            const messageCountDiv = document.getElementById('messageCount');
            const icon = document.getElementById('toggleIcon');
            if (messageCountDiv.style.display === 'none') {
                messageCountDiv.style.display = 'block';
                icon.textContent = '-'; // Change to minus icon
            } else {
                messageCountDiv.style.display = 'none';
                icon.textContent = '+'; // Change to plus icon
            }
        }
        // Initialize DataTable
        $(document).ready(function() {
            const table = $('#chatTable').DataTable({
                responsive: {
                    details: {
                        type: 'column',
                        target: -1 // This targets the last column for responsive control
                    }
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 2 } // Ensure the "Pesan" column (index 2) is prioritized for visibility
                ]
            });

            // Add click event to each sender's message count
            $('#messageCount li').on('click', function() {
                const sender = $(this).data('sender');
                table.search(sender).draw();
                toggleMessageCount();
            });
        });
    </script>
</head>
<body class="bg-gray-100 p-8">
    <div class="mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col md:flex-row justify-between mb-4">
                <h1 class="text-2xl font-bold">Isi Chat WhatsApp 
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
                
                <div class="flex gap-2">
                    <form action="download.php" method="post" class="inline-block">
                        <button type="submit" class="text-sm md:text-base p-1 md:px-4 md:py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Download Chat
                        </button>
                    </form>
                    <a href="index.php?clear=true" class="inline-block text-sm md:text-base p-1 md:px-4 md:py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Unggah File Lain
                    </a>
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row justify-end">

                <div class="w-full md:w-1/3 px-2">
                    <div class="mb-4 p-4 bg-gray-50 rounded-md">
                        <h2 class="text-sm font-medium text-gray-700 mb-2">Total Anggota: <?php echo count($members); ?></h2>
                        <h2 class="text-sm font-medium text-gray-700 mb-2">Total Pengirim : <?php echo count(array_filter($message_count, function($count) { return $count > 0; })); ?></h2>
                        <h2 class="text-sm font-medium text-gray-700 mb-2">Tidak hadir : <?php echo count(array_filter($message_count, function($count) { return $count == 0; })); ?></h2>
                        <?php if(count($message_count) > count($members)){ ?>
                            <h2 class="text-sm font-medium text-red-700 mb-2">Terdapat pengirim yang tidak ada dalam daftar anggota. Sesuaikan nama anggota dengan pengirim. </h2>
                        <?php } ?>
                    </div>
                    <div class="flex flex-col md:flex-row justify-around">
                        <a href="process.php?sort=asc" class="mb-1 md:mb-0 <?php echo $sort == 'asc' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?> rounded-md px-2 py-1 text-sm">urut terkecil</a>
                        <a href="process.php?sort=desc" class="mb-1 md:mb-0 <?php echo $sort == 'desc' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?> rounded-md px-2 py-1 text-sm">urut terbesar</a>
                        <a href="process.php?sort=sender" class="mb-1 md:mb-0 <?php echo $sort == 'sender' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?> rounded-md px-2 py-1 text-sm">urut pengirim</a>
                    </div>
                    <div class="mb-4 p-4 bg-gray-50 rounded-md">
                        <h2 onclick="toggleMessageCount()" class="text-sm font-medium text-blue-500 mb-2 underline cursor-pointer">
                            Pesan tiap anggota <span id="toggleIcon">-</span>
                        </h2>
                        <ul id="messageCount" class="flex flex-wrap">
                            <?php foreach ($message_count as $sender => $count): ?>
                                <li class="text-sm text-gray-700 cursor-pointer p-1 mb-1" data-sender="<?php echo htmlspecialchars($sender); ?>">
                                    <?php 
                                    if (in_array($sender, $members)) {
                                        echo "<span class='text-green-500'>" . htmlspecialchars($sender) . "</span>";
                                    } else {
                                        echo "<span class='text-red-500'>" . htmlspecialchars($sender) . "</span>";
                                    }
                                    if($count > 0){
                                        echo ' <span class="bg-blue-500 text-white rounded-md px-1">' . $count . '</span>';
                                    }else{
                                        echo ' <span class="bg-red-500 text-white rounded-md px-1">' . $count . '</span>';
                                    }
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="w-full md:w- 2/3 overflow-x-auto">
                    <form action="process.php" method="post" class="mb-4">
                        <textarea name="members" id="members" class="w-full h-20 p-2 border border-gray-300 rounded-md"><?php echo implode(',', $members); ?></textarea>
                        <button type="submit" name="update_members" class="bg-indigo-600 text-white rounded-md px-2 py-1 text-sm">Perbaharui anggota</button>
                    </form>
                    <table id="chatTable" class="min-w-full divide-y divide-gray-200">
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

            </div>

        </div>
    </div>
</body>
</html>