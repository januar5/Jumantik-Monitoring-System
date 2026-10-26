<?php
require_once 'config.php';

// Simulate RW 01 user login
$username = 'rw01';
$query = "SELECT r.*, u.username FROM rw r 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE u.username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data_rw = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$data_rw) {
    die("RW tidak ditemukan");
}

$nomor_rw = $data_rw['nomor_rw'] ?? '-';
$nomor_rw_filter = preg_replace('/[^0-9]/', '', (string)$nomor_rw);
$nomor_rw_filter_int = !empty($nomor_rw_filter) ? intval($nomor_rw_filter) : null;

echo "<h2>Debug: Query Laporan Detail RW " . htmlspecialchars($nomor_rw) . "</h2>";
echo "<p>nomor_rw_filter_int: " . $nomor_rw_filter_int . "</p>\n";

// Test 1: Check data non-rekap untuk periode
echo "<h3>Test 1: Total non-Rekap data untuk Februari 2026</h3>";
$test_query = "SELECT COUNT(*) as cnt FROM data_jumantik WHERE tatanan_diperiksa != 'Rekap' AND DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '2026-02'";
$result = mysqli_query($conn, $test_query);
$row = mysqli_fetch_assoc($result);
echo "<p>Total: " . $row['cnt'] . "</p>\n";

// Test 2: Query dengan RW filter untuk laporan detail (seperti di laporan-rw.php)
echo "<h3>Test 2: Query dengan RW Filter (seperti di laporan-rw.php)</h3>";
$bulan = '2026-02';
$minggu = 1;
$test_query = "
    SELECT 
        dj.id,
        dj.rt,
        dj.rw,
        k.nama_kader,
        dj.catatan,
        dj.foto_rumah,
        dj.tanggal_pemeriksaan
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE (CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = " . $nomor_rw_filter_int . " OR CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) = " . $nomor_rw_filter_int . ")
      AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '" . $bulan . "'
      AND (FLOOR((DAY(dj.tanggal_pemeriksaan)-1)/7)+1) = " . $minggu . "
      AND dj.tatanan_diperiksa != 'Rekap'
    ORDER BY dj.tanggal_pemeriksaan DESC, dj.id DESC
";
echo "<p>Query: " . htmlspecialchars($test_query) . "</p>\n";
$result = mysqli_query($conn, $test_query);
echo "<p>Count: " . mysqli_num_rows($result) . "</p>\n";

if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr style='background: #ccc;'><th>ID</th><th>RT</th><th>RW</th><th>Nama Kader</th><th>Tanggal</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['rt']) . "</td>";
        echo "<td>" . htmlspecialchars($row['rw']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kader']) . "</td>";
        echo "<td>" . $row['tanggal_pemeriksaan'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Without tatanan filter
echo "<h3>Test 3: Tanpa filter tatanan</h3>";
$test_query2 = "
    SELECT COUNT(*) as cnt
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE (CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = " . $nomor_rw_filter_int . " OR CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) = " . $nomor_rw_filter_int . ")
      AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '2026-02'
";
$result = mysqli_query($conn, $test_query2);
$row = mysqli_fetch_assoc($result);
echo "<p>Count: " . $row['cnt'] . "</p>\n";

// Test 4: Check what RW values exist for RW 01 data
echo "<h3>Test 4: Sample RW values in database</h3>";
$test_query3 = "SELECT DISTINCT dj.rw, k.rw as kader_rw FROM data_jumantik dj LEFT JOIN kader k ON dj.kader_id = k.id WHERE DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '2026-02' LIMIT 10";
$result = mysqli_query($conn, $test_query3);
echo "<table border='1' cellpadding='10'>";
echo "<tr style='background: #ccc;'><th>dj.rw</th><th>k.rw</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['rw']) . "</td>";
    echo "<td>" . htmlspecialchars($row['kader_rw']) . "</td>";
    echo "</tr>";
}
echo "</table>";

?>
