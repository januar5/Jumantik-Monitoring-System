<?php
require_once 'config.php';

echo "<h2>Testing Detail Report Query</h2>";

// Test 1: Check total data summary
echo "<h3>Test 1: All data for Feb 2026</h3>";
$query = "SELECT COUNT(*) as total FROM data_jumantik WHERE DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '2026-02'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "<p>Total records for Feb 2026: " . $row['total'] . "</p>";

// Test 2: Check breakdown by tatanan_diperiksa
echo "<h3>Test 2: Count by tatanan_diperiksa</h3>";
$query = "SELECT tatanan_diperiksa, COUNT(*) as cnt FROM data_jumantik WHERE DATE_FORMAT(tanggal_pemeriksaan, '%Y-%m') = '2026-02' GROUP BY tatanan_diperiksa";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    echo "<p>" . htmlspecialchars($row['tatanan_diperiksa']) . ": " . $row['cnt'] . "</p>";
}

// Test 3: Show sample of each tatanan
echo "<h3>Test 3: Sample records by tatanan</h3>";
$query = "SELECT tatanan_diperiksa, dj.id, k.rw as kader_rw, dj.rw as jumantik_rw FROM data_jumantik dj LEFT JOIN kader k ON dj.kader_id = k.id WHERE DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '2026-02' LIMIT 1 OFFSET 0";
$result = mysqli_query($conn, $query);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>tatanan_diperiksa</th><th>id</th><th>kader_rw</th><th>jumantik_rw</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['tatanan_diperiksa']) . "</td>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['kader_rw']) . "</td>";
    echo "<td>" . htmlspecialchars($row['jumantik_rw']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 4: Test RW matching
echo "<h3>Test 4: Test RW matching for RW 01</h3>";
$nomor_rw = '01';
$nomor_rw_filter_int = 1;
$query = "
    SELECT 
        dj.id,
        dj.rw,
        CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) as dj_rw_extracted,
        k.rw,
        CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) as k_rw_extracted,
        dj.tatanan_diperiksa
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '2026-02'
    LIMIT 10
";
$result = mysqli_query($conn, $query);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>dj.rw</th><th>extracted</th><th>k.rw</th><th>extracted</th><th>tatanan</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    $dj_matches = ($row['dj_rw_extracted'] == $nomor_rw_filter_int) ? "✓" : "";
    $k_matches = ($row['k_rw_extracted'] == $nomor_rw_filter_int) ? "✓" : "";
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['rw']) . " $dj_matches</td>";
    echo "<td>" . $row['dj_rw_extracted'] . "</td>";
    echo "<td>" . htmlspecialchars($row['k.rw']) . " $k_matches</td>";
    echo "<td>" . $row['k_rw_extracted'] . "</td>";
    echo "<td>" . htmlspecialchars($row['tatanan_diperiksa']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 5: Test actual detail query with RW filter
echo "<h3>Test 5: Detail query with RW filter (like in laporan-rw.php)</h3>";
$query = "
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
    LEFT JOIN users uk ON k.user_id = uk.id
    WHERE (CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = $nomor_rw_filter_int OR CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) = $nomor_rw_filter_int)
      AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '2026-02'
      AND dj.tatanan_diperiksa != 'Rekap'
    ORDER BY dj.tanggal_pemeriksaan DESC, dj.id DESC
";
echo "<p>Query: " . htmlspecialchars($query) . "</p>";
$result = mysqli_query($conn, $query);
$count = mysqli_num_rows($result);
echo "<p>Count: <strong>$count</strong></p>";

if ($count > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>RT</th><th>Kader</th><th>Tanggal</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['rt']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kader']) . "</td>";
        echo "<td>" . $row['tanggal_pemeriksaan'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No data found with this filter</p>";
}

// Test 6: Check without tatanan filter
echo "<h3>Test 6: Same query but without tatanan_diperiksa filter</h3>";
$query = "
    SELECT COUNT(*) as cnt
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE (CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = $nomor_rw_filter_int OR CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) = $nomor_rw_filter_int)
      AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '2026-02'
";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
echo "<p>Count (any tatanan): <strong>" . $row['cnt'] . "</strong></p>";

?>
