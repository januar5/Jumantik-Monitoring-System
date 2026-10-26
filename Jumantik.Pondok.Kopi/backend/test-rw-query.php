<?php
require_once 'config.php';

// Ambil user RW yang sedang login (gunakan user ID 1 untuk testing)
$username = 'rw01'; // biasanya nama user RW

// Ambil data RW
$query = "SELECT r.*, u.username FROM rw r 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE u.username = ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data_rw = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    $data_rw = null;
}

if (!$data_rw) {
    die("RW dengan username '$username' tidak ditemukan!");
}

$nomor_rw = $data_rw['nomor_rw'] ?? '-';
$nomor_rw_filter = preg_replace('/[^0-9]/', '', (string)$nomor_rw);
$nomor_rw_filter_int = !empty($nomor_rw_filter) ? intval($nomor_rw_filter) : null;

echo "<h2>Debug: Laporan RW " . htmlspecialchars($nomor_rw) . "</h2>";
echo "<p>nomor_rw_filter_int: " . $nomor_rw_filter_int . "</p>";

// Test 1: Cek total rekap records
echo "<h3>Test 1: Total Rekap Records</h3>";
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap'");
$row = mysqli_fetch_assoc($result);
echo "<p>Total: " . $row['cnt'] . "</p>";

// Test 2: Cek rekap untuk bulan ini
echo "<h3>Test 2: Rekap Records for February 2026</h3>";
$bulan = '2026-02';
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap' AND DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '$bulan'");
$row = mysqli_fetch_assoc($result);
echo "<p>Total untuk bulan: " . $row['cnt'] . "</p>";

// Test 3: Query dengan RW filter seperti di laporan-rw
echo "<h3>Test 3: Dengan RW Filter</h3>";
$rw_patterns = [];
if ($nomor_rw_filter_int !== null) {
    $rw_patterns[] = "CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = " . $nomor_rw_filter_int;
    $rw_patterns[] = "CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) = " . $nomor_rw_filter_int;
}
$where_rw = !empty($rw_patterns) ? " AND (" . implode(" OR ", $rw_patterns) . ")" : "";
$test_query = "SELECT dj.id, dj.rt, dj.rw, k.rw as kader_rw FROM data_jumantik dj LEFT JOIN kader k ON dj.kader_id = k.id WHERE dj.tatanan_diperiksa = 'Rekap' AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '$bulan'" . $where_rw;
echo "<p>Query: " . htmlspecialchars($test_query) . "</p>";
$result = mysqli_query($conn, $test_query);
echo "<p>Count: " . mysqli_num_rows($result) . "</p>";
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>RT</th><th>dj.rw</th><th>k.rw</th></tr>";
    while($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['rt']) . "</td>";
        echo "<td>" . htmlspecialchars($row['rw']) . "</td>";
        echo "<td>" . htmlspecialchars($row['kader_rw']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 4: Cek format RW di database
echo "<h3>Test 4: Sample RW Formats dalam Database</h3>";
$result = mysqli_query($conn, "SELECT DISTINCT rw FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap' LIMIT 5");
if (mysqli_num_rows($result) > 0) {
    echo "<ul>";
    while($row = mysqli_fetch_assoc($result)) {
        echo "<li>'" . htmlspecialchars($row['rw']) . "'</li>";
    }
    echo "</ul>";
} else {
    echo "Tidak ada data!";
}

?>
