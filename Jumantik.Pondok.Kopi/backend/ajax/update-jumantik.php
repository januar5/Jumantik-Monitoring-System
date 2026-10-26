<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

require_once '../config.php';

// Validasi session
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kader') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// ===== FUNGSI NORMALISASI RT/RW =====
function normalizeRTRW($value) {
    // Ambil hanya angka
    $digits = preg_replace('/[^0-9]/', '', trim($value));
    if (empty($digits)) return '';
    $num = intval($digits);
    // Format 2 digit: 01, 02, 03, dst
    return str_pad($num, 2, '0', STR_PAD_LEFT);
}

// Ambil data POST
$id = intval($_POST['id'] ?? 0);
$rt_raw = $_POST['rt'] ?? '';
$rw_raw = $_POST['rw'] ?? '';

// NORMALISASI RT/RW
$rt = normalizeRTRW($rt_raw);
$rw = normalizeRTRW($rw_raw);

error_log("UPDATE - RT RAW: $rt_raw -> NORMALIZED: $rt");
error_log("UPDATE - RW RAW: $rw_raw -> NORMALIZED: $rw");

// Data rekap per tatanan (sama seperti process-jumantik.php)
$tatanan_inputs = [];
$tatanan_list = [
    'pemukiman','institusi_pendidikan','tempat_kerja','ttu','tpm','sarana_olahraga','sarana_kesehatan'
];
foreach ($tatanan_list as $t) {
    $d = isset($_POST[$t . '_diperiksa']) ? intval($_POST[$t . '_diperiksa']) : 0;
    $j = isset($_POST[$t . '_jentik']) ? intval($_POST[$t . '_jentik']) : 0;
    $tatanan_inputs[$t] = ['diperiksa' => $d, 'jentik' => $j];
}

// Minggu dan bulan untuk menentukan tanggal
$minggu = isset($_POST['minggu']) ? intval($_POST['minggu']) : 0; // 1..6
$bulan_n = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0;  // 1..12

// Hitung tanggal_pemeriksaan sama seperti saat create
$current_year = date('Y');
$tanggal_pemeriksaan = null;
if ($minggu >= 1 && $minggu <= 6 && $bulan_n >=1 && $bulan_n <= 12) {
    $datetime = new DateTime("$current_year-" . str_pad($bulan_n, 2, '0', STR_PAD_LEFT) . "-01");
    $datetime->add(new DateInterval("P" . (($minggu - 1) * 7) . "D"));
    $tanggal_pemeriksaan = $datetime->format('Y-m-d');
}

$catatan_text = trim($_POST['catatan'] ?? '');

// Validasi dasar
if (empty($id) || empty($rt) || empty($rw) || empty($tanggal_pemeriksaan)) {
    echo json_encode(['success' => false, 'message' => 'Data wajib (id, RT, RW, minggu/bulan) tidak lengkap']);
    exit;
}

// Susun JSON summary rekap + catatan
$tatanan_labels_map = [
    'pemukiman' => 'Pemukiman',
    'institusi_pendidikan' => 'Institusi Pendidikan',
    'tempat_kerja' => 'Tempat Kerja',
    'ttu' => 'TTU',
    'tpm' => 'TPM',
    'sarana_olahraga' => 'Sarana Olahraga',
    'sarana_kesehatan' => 'Sarana Kesehatan'
];
$summary = [];
foreach ($tatanan_inputs as $key => $val) {
    $name = $tatanan_labels_map[$key] ?? ucwords(str_replace(['_'], [' '], $key));
    $summary[$name] = $val;
}
if (!empty($catatan_text)) {
    $summary['_catatan'] = $catatan_text;
}
$summary_json = json_encode($summary);

// Tentukan jentik_ditemukan overall
$any_jentik = false;
foreach ($tatanan_inputs as $v) { if ($v['jentik'] > 0) { $any_jentik = true; break; } }
$jentik_ditemukan_overall = $any_jentik ? 'Ya' : 'Tidak';

$kader_id = $_SESSION['user_id'];

// Update hanya field yang relevan rekap
$query = "UPDATE data_jumantik SET 
    tanggal_pemeriksaan = ?,
    rt = ?,
    rw = ?,
    jentik_ditemukan = ?,
    catatan = ?
    WHERE id = ? AND kader_id IN (SELECT id FROM kader WHERE user_id = ?)";

$stmt = mysqli_prepare($conn, $query);
// 5 string (tanggal, rt, rw, jentik, catatan) + 2 integer (id, user_id)
mysqli_stmt_bind_param($stmt, "sssssii", 
    $tanggal_pemeriksaan, 
    $rt,  // SUDAH DINORMALISASI
    $rw,  // SUDAH DINORMALISASI
    $jentik_ditemukan_overall, 
    $summary_json, 
    $id, 
    $kader_id
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data: ' . mysqli_stmt_error($stmt)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>