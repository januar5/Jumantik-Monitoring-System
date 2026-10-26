<?php
require_once 'config.php';

echo "<h2>Detail Query Debug - Exact Mimic of laporan-rw.php</h2>";

// Simulate RW user
$nomor_rw_filter_int = 1; // RW 01
echo "<p><strong>RW Filter:</strong> " . $nomor_rw_filter_int . "</p>";

// Simulate request parameters
// Default: mingguan, minggu 1, current month
$_GET['periode'] = isset($_GET['periode']) ? $_GET['periode'] : 'mingguan';
$_GET['minggu'] = isset($_GET['minggu']) ? $_GET['minggu'] : 1;
$_GET['bulan'] = isset($_GET['bulan']) ? $_GET['bulan'] : '2026-02'; // Testing with Feb 2026

echo "<h3>Request Params</h3>";
echo "<p>periode: " . htmlspecialchars($_GET['periode']) . "</p>";
echo "<p>minggu: " . htmlspecialchars($_GET['minggu']) . "</p>";
echo "<p>bulan: " . htmlspecialchars($_GET['bulan']) . "</p>";

// Build detail query conditions - EXACTLY from laporan-rw.php
$params = [];
$types = "";
$query_conditions = [];

echo "<h3>Building RW Filter</h3>";
// Filter berdasarkan RW
if ($nomor_rw_filter_int !== null) {
    $rw_patterns = [
        "CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = ?",
        "CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) = ?"
    ];
    $query_conditions[] = "(" . implode(" OR ", $rw_patterns) . ")";
    $params[] = $nomor_rw_filter_int;
    $params[] = $nomor_rw_filter_int;
    $types .= "ii";
}
echo "<p>Condition: " . end($query_conditions) . "</p>";
echo "<p>Params so far: " . json_encode($params) . "</p>";
echo "<p>Types so far: " . $types . "</p>";

echo "<h3>Building Periode Filter</h3>";
// Filter Periode (Mingguan atau Bulanan)
$periode = $_GET['periode'] ?? 'mingguan';

if ($periode == 'bulanan' && !empty($_GET['bulan'])) {
    echo "<p>BULANAN mode</p>";
    $query_conditions[] = "DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?";
    $params[] = $_GET['bulan'];
    $types .= "s";
    echo "<p>Condition: " . end($query_conditions) . "</p>";
} elseif ($periode == 'mingguan' && isset($_GET['minggu'])) {
    echo "<p>MINGGUAN mode</p>";
    $minggu = intval($_GET['minggu']);
    if ($minggu < 1) $minggu = 1; if ($minggu > 6) $minggu = 6;
    $bulan_for_week = $_GET['bulan'] ?? date('Y-m');
    
    echo "<p>Minggu: " . $minggu . "</p>";
    echo "<p>Bulan for week: " . htmlspecialchars($bulan_for_week) . "</p>";
    
    $query_conditions[] = "DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?";
    $params[] = $bulan_for_week;
    $types .= "s";
    echo "<p>Condition 1: " . "DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?" . "</p>";
    
    $query_conditions[] = "(FLOOR((DAY(dj.tanggal_pemeriksaan)-1)/7)+1) = ?";
    $params[] = $minggu;
    $types .= "i";
    echo "<p>Condition 2: " . end($query_conditions) . "</p>";
} else {
    echo "<p>NO periode filter applied!</p>";
}

echo "<p>Params after periode: " . json_encode($params) . "</p>";
echo "<p>Types after periode: " . $types . "</p>";

echo "<h3>Building Tatanan Filter</h3>";
// Filter tatanan
if (!empty($_GET['tatanan'])) {
    echo "<p>Tatanan filter: " . htmlspecialchars($_GET['tatanan']) . "</p>";
    $tatanan_filter = $_GET['tatanan'];
    $query_conditions[] = "dj.tatanan_diperiksa = ?";
    $params[] = $tatanan_filter;
    $types .= "s";
} else {
    echo "<p>No specific tatanan - excluding Rekap</p>";
    // Jika tidak memilih tatanan khusus, exclude baris 'Rekap'
    $query_conditions[] = "dj.tatanan_diperiksa != 'Rekap'";
}

echo "<h3>Final Query</h3>";
// Build WHERE clause
$where_clause = !empty($query_conditions) ? "WHERE " . implode(" AND ", $query_conditions) : "";

$query = "
    SELECT 
        dj.id,
        dj.rt,
        dj.rw,
        k.nama_kader,
        dj.catatan,
        dj.foto_rumah,
        dj.tanggal_pemeriksaan,
        dj.tatanan_diperiksa
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    LEFT JOIN users uk ON k.user_id = uk.id
    {$where_clause}
    ORDER BY dj.tanggal_pemeriksaan DESC, dj.id DESC
";

echo "<p><strong>WHERE Conditions:</strong></p>";
foreach ($query_conditions as $i => $cond) {
    echo "<p>  " . ($i+1) . ". " . htmlspecialchars($cond) . "</p>";
}
echo "<p><strong>Parameters Count:</strong> " . count($params) . "</p>";
echo "<p><strong>Parameters:</strong></p>";
foreach ($params as $i => $param) {
    echo "<p>  " . ($i+1) . ". " . htmlspecialchars($param) . " [type: " . $types[$i] . "]</p>";
}
echo "<p><strong>Results from query using prepared statement:</strong></p>";

// Execute with prepared statement
$stmt = mysqli_prepare($conn, $query);
if ($stmt === false) {
    echo "<p style='color:red;'>❌ Error preparing statement: " . mysqli_error($conn) . "</p>";
} else {
    if (!empty($params)) {
        if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
            echo "<p style='color:red;'>❌ Error binding params: " . mysqli_stmt_error($stmt) . "</p>";
        }
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        echo "<p style='color:red;'>❌ Error executing: " . mysqli_stmt_error($stmt) . "</p>";
    } else {
        $result = mysqli_stmt_get_result($stmt);
        $row_count = mysqli_num_rows($result);
        echo "<p><strong>Total rows found:</strong> " . $row_count . "</p>";
        
        if ($row_count > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>RT</th><th>RW</th><th>Kader</th><th>Tatanan</th><th>Tanggal</th></tr>";
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['rt']) . "</td>";
                echo "<td>" . htmlspecialchars($row['rw']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_kader'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['tatanan_diperiksa']) . "</td>";
                echo "<td>" . $row['tanggal_pemeriksaan'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ No data found - Check: (1) Data exists in DB, (2) RW matches, (3) Periode matches</p>";
        }
    }
    mysqli_stmt_close($stmt);
}

?>
