<?php
require_once '../config.php';

header('Content-Type: application/json');

$rw = $_GET['rw'] ?? '';

if (empty($rw)) {
    echo json_encode(['success' => false, 'message' => 'RW tidak boleh kosong']);
    exit;
}

function normalizeNumber($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^0-9]/', '', $value);
    return ltrim($value, '0');
}

function normalizeRwSql($column) {
    return "CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST($column AS CHAR) COLLATE utf8mb4_general_ci), 'rw', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED)";
}

$rw_int = intval(normalizeNumber($rw));

$query = "
    SELECT DISTINCT k.rt
    FROM kader k
    WHERE " . normalizeRwSql('k.rw') . " = $rw_int
    ORDER BY k.rt ASC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

$rt_list = [];
$rt_seen = []; // Untuk menghindari duplikasi

// Ambil dari kader
while ($row = mysqli_fetch_assoc($result)) {
    if (!empty($row['rt'])) {
        $rt_value = trim($row['rt']);
        $rt_key = strtolower($rt_value);
        if (!isset($rt_seen[$rt_key])) {
            $rt_list[] = $rt_value;
            $rt_seen[$rt_key] = true;
        }
    }
}

// Juga ambil dari data_jumantik untuk memastikan semua RT terbaca
$query2 = "
    SELECT DISTINCT dj.rt
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE " . normalizeRwSql("COALESCE(NULLIF(dj.rw, ''), k.rw)") . " = $rw_int
    AND dj.rt IS NOT NULL AND dj.rt != ''
    ORDER BY dj.rt ASC
";

$result2 = mysqli_query($conn, $query2);
if ($result2) {
    while ($row2 = mysqli_fetch_assoc($result2)) {
        if (!empty($row2['rt'])) {
            $rt_value = trim($row2['rt']);
            $rt_key = strtolower($rt_value);
            if (!isset($rt_seen[$rt_key])) {
                $rt_list[] = $rt_value;
                $rt_seen[$rt_key] = true;
            }
        }
    }
}

// Sort ulang setelah menggabungkan
sort($rt_list);

echo json_encode([
    'success' => true,
    'rt_list' => $rt_list
]);
?>

