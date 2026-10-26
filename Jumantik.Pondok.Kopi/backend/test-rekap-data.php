<?php
require_once 'config.php';

?>
<!DOCTYPE html>
<html>
<head><title>Test Rekap Data</title></head>
<body>
<h1>Test: Cek Rekap Data di Database</h1>

<?php

// Test 1: Total rekap
echo "<h2>1. Total Rekap Records</h2>";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap'");
$row = mysqli_fetch_assoc($result);
echo "<p>Jumlah: <strong>" . $row['cnt'] . "</strong></p>";

if ($row['cnt'] == 0) {
    echo "<p style='color: red;'><strong>⚠️ MASALAH: Belum ada data dengan tatanan_diperiksa = 'Rekap'!</strong></p>";
    echo "<p>Kemungkinan kader belum membuat rekap mingguan/bulanan di form 'Form Rekapitulasi' di data-jumantik.php</p>";
} else {
    echo "<p style='color: green;'><strong>✓ Ada data rekap!</strong></p>";
}

// Test 2: Rekap untuk Februari 2026
echo "<h2>2. Rekap untuk Februari 2026</h2>";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap' AND DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '2026-02'");
$row = mysqli_fetch_assoc($result);
echo "<p>Jumlah: <strong>" . $row['cnt'] . "</strong></p>";

// Test 3: List semua rekap records dengan detail
echo "<h2>3. Detail Semua Rekap Records</h2>";
$result = mysqli_query($conn, "SELECT dj.id, dj.rt, dj.rw, dj.kader_id, dj.tanggal_pemeriksaan, k.nama_kader, k.rw as kader_rw FROM data_jumantik dj LEFT JOIN kader k ON dj.kader_id = k.id WHERE dj.tatanan_diperiksa = 'Rekap' ORDER BY dj.tanggal_pemeriksaan DESC LIMIT 20");

if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellspacing='0' cellpadding='10'>";
    echo "<tr style='background: #ccc;'>";
    echo "<th>ID</th>";
    echo "<th>RT</th>";
    echo "<th>RW (dari data_jumantik.rw)</th>";
    echo "<th>Kader ID</th>";
    echo "<th>Nama Kader</th>";
    echo "<th>RW (dari kader.rw)</th>";
    echo "<th>Tanggal</th>";
    echo "</tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['rt']) . "</td>";
        echo "<td>" . htmlspecialchars($row['rw']) . "</td>";
        echo "<td>" . $row['kader_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kader']) . "</td>";
        echo "<td>" . htmlspecialchars($row['kader_rw']) . "</td>";
        echo "<td>" . $row['tanggal_pemeriksaan'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>Tidak ada data rekap!</p>";
}

// Test 4: Cek kader mana saja yang ada
echo "<h2>4. Daftar Kader</h2>";
$result = mysqli_query($conn, "SELECT id, nama_kader, rw FROM kader LIMIT 10");
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellspacing='0' cellpadding='10'>";
    echo "<tr style='background: #ccc;'><th>ID</th><th>Nama</th><th>RW</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kader']) . "</td>";
        echo "<td>" . htmlspecialchars($row['rw']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 5: Sample data_jumantik untuk Februari (non-rekap)
echo "<h2>5. Sample Data Jumantik untuk Februari 2026 (non-Rekap)</h2>";
$result = mysqli_query($conn, "SELECT id, rt, rw, tatanan_diperiksa, tanggal_pemeriksaan FROM data_jumantik WHERE DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '2026-02' AND tatanan_diperiksa != 'Rekap' LIMIT 5");
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellspacing='0' cellpadding='10'>";
    echo "<tr style='background: #ccc;'><th>ID</th><th>RT</th><th>RW</th><th>Tatanan</th><th>Tanggal</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['rt']) . "</td>";
        echo "<td>" . htmlspecialchars($row['rw']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tatanan_diperiksa']) . "</td>";
        echo "<td>" . $row['tanggal_pemeriksaan'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Tidak ada data non-rekap untuk Februari</p>";
}

?>

</body> 
</html>
