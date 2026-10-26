<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    die("Please login first");
}

echo "<h2>🔍 Database Structure Checker</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; max-width: 800px; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #2563eb; color: white; }
    .info { background: #dbeafe; padding: 15px; border-radius: 8px; margin: 15px 0; }
    .success { background: #dcfce7; padding: 15px; border-radius: 8px; margin: 15px 0; }
    .error { background: #fee2e2; padding: 15px; border-radius: 8px; margin: 15px 0; }
</style>";

// 1. Cek struktur tabel RW
echo "<h3>1️⃣ Struktur Tabel RW:</h3>";
$query = "SHOW COLUMNS FROM rw";
$result = mysqli_query($conn, $query);

if ($result) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ Error: " . mysqli_error($conn) . "</div>";
}

// 2. Cek data RW user yang login
echo "<h3>2️⃣ Data RW User Login (Username: {$_SESSION['username']}):</h3>";
$check_query = "SELECT * FROM rw r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE u.username = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rw_data = mysqli_fetch_assoc($result);

if ($rw_data) {
    echo "<div class='success'>✅ Data RW ditemukan:</div>";
    echo "<table>";
    foreach ($rw_data as $key => $value) {
        echo "<tr><td><strong>$key</strong></td><td>" . ($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
    
    // Identifikasi kolom RW
    echo "<div class='info'>";
    echo "<strong>🔍 Identifikasi Kolom Nomor RW:</strong><br>";
    if (isset($rw_data['nomor_rw'])) {
        echo "✅ Kolom 'nomor_rw' ada: " . $rw_data['nomor_rw'];
    } elseif (isset($rw_data['no_rw'])) {
        echo "✅ Kolom 'no_rw' ada: " . $rw_data['no_rw'];
    } elseif (isset($rw_data['rw'])) {
        echo "✅ Kolom 'rw' ada: " . $rw_data['rw'];
    } elseif (isset($rw_data['nama_rw'])) {
        echo "⚠️ Hanya ada kolom 'nama_rw': " . $rw_data['nama_rw'];
    } else {
        echo "❌ Tidak ada kolom yang cocok untuk nomor RW";
    }
    echo "</div>";
} else {
    echo "<div class='error'>❌ Data RW tidak ditemukan untuk user ini</div>";
}

// 3. Cek struktur tabel data_jumantik
echo "<h3>3️⃣ Struktur Tabel data_jumantik:</h3>";
$query = "SHOW COLUMNS FROM data_jumantik";
$result = mysqli_query($conn, $query);

if ($result) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    $has_status_konfirmasi = false;
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['Field'] == 'status_konfirmasi') {
            $has_status_konfirmasi = true;
        }
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($has_status_konfirmasi) {
        echo "<div class='success'>✅ Kolom status_konfirmasi sudah ada</div>";
    } else {
        echo "<div class='error'>❌ Kolom status_konfirmasi belum ada - perlu ditambahkan</div>";
        echo "<div class='info'>Run SQL ini:<br><code>ALTER TABLE data_jumantik ADD COLUMN status_konfirmasi ENUM('Menunggu', 'Disetujui', 'Ditolak') DEFAULT 'Menunggu' AFTER foto_rumah;</code></div>";
    }
}

// 4. Sample data jumantik
echo "<h3>4️⃣ Sample Data Jumantik (3 baris):</h3>";
$sample = "SELECT id, rt, rw, nama_kepala_keluarga, status_konfirmasi FROM data_jumantik LIMIT 3";
$result = mysqli_query($conn, $sample);

if ($result && mysqli_num_rows($result) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>RT</th><th>RW</th><th>Nama KK</th><th>Status Konfirmasi</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['rt'] . "</td>";
        echo "<td>" . $row['rw'] . "</td>";
        echo "<td>" . $row['nama_kepala_keluarga'] . "</td>";
        echo "<td>" . ($row['status_konfirmasi'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

mysqli_close($conn);

echo "<hr>";
echo "<p><a href='laporan-rw.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; display: inline-block;'>← Kembali ke Laporan RW</a></p>";
?>