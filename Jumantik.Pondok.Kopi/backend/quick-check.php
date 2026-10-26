<?php
require_once 'config.php';

echo "<h2>Quick Database Check</h2>";

// Test 1: Check ALL records - raw count
$query = "SELECT COUNT(*) as total FROM data_jumantik";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "<p>Total records in DB: " . $row['total'] . "</p>";

// Test 2: Check Rekap vs non-Rekap
echo "<h3>Rekap vs Detail breakdown</h3>";
$query = "SELECT tatanan_diperiksa, COUNT(*) as cnt FROM data_jumantik GROUP BY tatanan_diperiksa";
$result = mysqli_query($conn, $query);
$total_rekap = 0;
$total_non_rekap = 0;
while ($row = mysqli_fetch_assoc($result)) {
    echo "<p>" . htmlspecialchars($row['tatanan_diperiksa']) . ": " . $row['cnt'] . "</p>";
    if ($row['tatanan_diperiksa'] === 'Rekap') {
        $total_rekap = $row['cnt'];
    } else {
        $total_non_rekap += $row['cnt'];
    }
}
echo "<p><strong>Summary:</strong></p>";
echo "<p>Rekap records: " . $total_rekap . "</p>";
echo "<p>Non-Rekap records: " . $total_non_rekap . "</p>";

// Test 3: Check date range
echo "<h3>Date range in database</h3>";
$query = "SELECT MIN(tanggal_pemeriksaan) as min_date, MAX(tanggal_pemeriksaan) as max_date FROM data_jumantik";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "<p>Min date: " . $row['min_date'] . "</p>";
echo "<p>Max date: " . $row['max_date'] . "</p>";

// Test 4: Non-Rekap in Feb 2026
echo "<h3>Non-Rekap records for Feb 2026</h3>";
$query = "SELECT COUNT(*) as cnt FROM data_jumantik WHERE tatanan_diperiksa != 'Rekap' AND DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '2026-02'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "<p>Count: " . $row['cnt'] . "</p>";

// Test 5: ALL dates for non-Rekap data
echo "<h3>All dates with non-Rekap data</h3>";
$query = "SELECT DISTINCT DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') as date_month FROM data_jumantik WHERE tatanan_diperiksa != 'Rekap' ORDER BY date_month";
$result = mysqli_query($conn, $query);
$count = mysqli_num_rows($result);
if ($count > 0) {
    echo "<p>Found $count months with non-Rekap data:</p>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<p>" . htmlspecialchars($row['date_month']) . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ NO non-Rekap records found in database at all!</p>";
}

?>
