<?php
require_once 'config.php';

// Test 1: Cek total rekap records
echo "=== TEST 1: Total Rekap Records ===\n";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap'");
$row = mysqli_fetch_assoc($result);
echo "Total rekap: " . $row['cnt'] . "\n\n";

// Test 2: Cek RW list dalam database
echo "=== TEST 2: RW Values in Rekap Records ===\n";
$result = mysqli_query($conn, "SELECT DISTINCT rw FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap' LIMIT 10");
while($row = mysqli_fetch_assoc($result)) {
    echo "RW: " . $row['rw'] . "\n";
}
echo "\n";

// Test 3: Cek data dengan filter bulan
echo "=== TEST 3: Rekap Records for February 2026 ===\n";
$result = mysqli_query($conn, "SELECT id, rt, rw, kader_id, tanggal_pemeriksaan FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap' AND DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '2026-02' LIMIT 10");
echo "Count: " . mysqli_num_rows($result) . "\n";
while($row = mysqli_fetch_assoc($result)) {
    echo "ID: {$row['id']}, RT: {$row['rt']}, RW: {$row['rw']}, Kader: {$row['kader_id']}, Tgl: {$row['tanggal_pemeriksaan']}\n";
}
echo "\n";

// Test 4: Cek actual query yang dijalankan
echo "=== TEST 4: Sample JSON Catatan ===\n";
$result = mysqli_query($conn, "SELECT id, rt, rw, catatan FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap' LIMIT 2");
while($row = mysqli_fetch_assoc($result)) {
    echo "ID {$row['id']} (RT {$row['rt']}/RW {$row['rw']}):\n";
    echo "Catatan: " . substr($row['catatan'], 0, 100) . "...\n";
    $decoded = json_decode($row['catatan'], true);
    echo "Decoded keys: " . implode(", ", array_keys($decoded)) . "\n\n";
}

?>
