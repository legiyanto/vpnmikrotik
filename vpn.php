<?php
require 'routeros_api.class.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $duration = $_POST['duration'];
    $port = $_POST['port']; // Port yang diinginkan oleh pengguna

    $API = new RouterosAPI();

    // Koneksi ke Mikrotik --> isi ip, username dan password 
    if ($API->connect('', 'admin', '', 8728)) {

        // Mendapatkan daftar secret PPP
        $API->write('/ppp/secret/print');
        $responses = $API->read();

        // Memeriksa apakah username sudah ada
        $usernameExists = false;
        foreach ($responses as $response) {
            if ($response['name'] === $username) {
                $usernameExists = true;
                break;
            }
        }

        if ($usernameExists) {
            $message = "Username sudah ada. Silakan pilih username lain.";
        } else {
            // Mendapatkan IP address berikutnya, mulai dari 192.168.25.2
            $baseIP = '192.168.25.';
            $usedIPs = [];

            // Mengumpulkan IP yang sudah digunakan
            foreach ($responses as $response) {
                if (isset($response['remote-address'])) {
                    $usedIPs[] = intval(explode('.', $response['remote-address'])[3]);
                }
            }

            // Mencari alamat IP yang belum terpakai
            $newIPIndex = 2; // Dimulai dari 2 (192.168.25.2)
            while (in_array($newIPIndex, $usedIPs)) {
                $newIPIndex++;
            }

            // Alamat IP baru
            $newIP = $baseIP . $newIPIndex;

            // Membuat secret PPP baru
            $API->write('/ppp/secret/add', false);
            $API->write('=name=' . $username, false);
            $API->write('=password=' . $password, false);
            $API->write('=service=any', false);
            $API->write('=remote-address=' . $newIP, false);
            $API->write('=local-address=192.168.25.1');
            $API->read();

            // Tentukan waktu kadaluwarsa berdasarkan durasi yang dipilih
            $now = time();
            switch ($duration) {
                case '1d':
                    $expireTime = $now + (1 * 24 * 60 * 60); // 1 hari
                    break;
                case '1w':
                    $expireTime = $now + (7 * 24 * 60 * 60); // 1 minggu
                    break;
                case '1m':
                    $expireTime = strtotime("+1 month", $now); // 1 bulan
                    break;
                case '3m':
                    $expireTime = strtotime("+3 months", $now); // 3 bulan
                    break;
                case '6m':
                    $expireTime = strtotime("+6 months", $now); // 6 bulan
                    break;
                case '1y':
                    $expireTime = strtotime("+1 year", $now); // 1 tahun
                    break;
                default:
                    $expireTime = $now; // Jika ada kesalahan, segera kadaluarsa
            }

            // Konversi waktu kadaluwarsa ke format Mikrotik
            $expireDate = date('M/d/Y', $expireTime);
            $expireTimeFormatted = date('H:i:s', $expireTime);

            // Tambahkan scheduler di Mikrotik untuk menghapus user setelah masa aktif habis
            $API->write('/system/scheduler/add', false);
            $API->write('=name=remove_' . $username, false);
            $API->write('=start-date=' . $expireDate, false);
            $API->write('=start-time=' . $expireTimeFormatted, false);
            $API->write('=on-event=/ppp/secret/remove [find name="' . $username . '"]', false);
            $API->write('=comment=Remove user ' . $username . ' after ' . $duration);
            $API->read();

            // Atur port forwarding dengan dst-nat
            $randomDstPort = rand(60000, 61000); // Acak dst-port antara 60000 dan 61000

            $API->write('/ip/firewall/nat/add', false);
            $API->write('=chain=dstnat', false);
            $API->write('=dst-address=10.12.45.161', false);
            $API->write('=protocol=tcp', false);
            $API->write('=dst-port=' . $randomDstPort, false); // Gunakan port acak untuk dst-port
            $API->write('=action=dst-nat', false);
            $API->write('=to-addresses=' . $newIP, false);
            $API->write('=to-ports=' . $port); // Gunakan port yang diinginkan pengguna untuk to-ports
            $API->read();

            // Pesan untuk ditampilkan
            $message = "<b>Hostname : vpslegi.my.id</b><br>";
            $message .= "<br>";
            $message .= "Username: $username<br>";
            $message .= "Password: $password<br>";
            $message .= "Masa aktif: " . getDurationText($duration) . "<br>"; // Ubah di sini
            $message .= "Port yang Di masukan: $port<br>";
            $message .= "Port Forwarding Ke: $randomDstPort<br>";
        }

        $API->disconnect();
    } else {
        $message = 'Error: Could not connect to Mikrotik.';
    }
}

// Fungsi untuk mengonversi durasi ke dalam bahasa Indonesia
function getDurationText($duration) {
    switch ($duration) {
        case '1d':
            return '1 Hari';
        case '1w':
            return '1 Minggu';
        case '1m':
            return '1 Bulan';
        case '3m':
            return '3 Bulan';
        case '6m':
            return '6 Bulan';
        case '1y':
            return '1 Tahun';
        default:
            return 'Tidak Diketahui';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTOMASI VPN</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        h1 {
            font-size: 1.5em;
            margin-bottom: 20px;
            text-align: center;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 1em;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        .message {
            margin-top: 20px;
            padding: 10px;
            background-color: #e2e3e5;
            border: 1px solid #d6d8db;
            border-radius: 4px;
            text-align: center;
        }
        .error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OTOMASI VPN</h1>
        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
            <br>
            <label for="password">Password:</label>
            <input type="text" id="password" name="password" required>
            <br>
            <label for="duration">Durasi Aktif:</label>
            <select id="duration" name="duration" required>
                <option value="1d">1 Hari</option>
                <option value="1w">1 Minggu</option>
                <option value="1m">1 Bulan</option>
                <option value="3m">3 Bulan</option>
                <option value="6m">6 Bulan</option>
                <option value="1y">1 Tahun</option>
            </select>
            <br>
            <label for="port">Port:</label>
            <input type="text" id="port" name="port" required>
            <br>
            <button type="submit">SUBMIT</button>
        </form>
    </div>
</body>
</html>
