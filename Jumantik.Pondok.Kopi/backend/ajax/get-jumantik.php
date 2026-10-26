<?php
error_reporting(0); // Nonaktifkan error display
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

require_once '../config.php';

// Validasi session
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kader') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
    exit;
}

$id = intval($_GET['id']);
$kader_id = $_SESSION['user_id'];

// Ambil data
$stmt = mysqli_prepare($conn, "SELECT * FROM data_jumantik WHERE id = ? AND kader_id IN (SELECT id FROM kader WHERE user_id = ?)");
mysqli_stmt_bind_param($stmt, "ii", $id, $kader_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if ($data) {
    // Format tanggal
    if (isset($data['tanggal_pemeriksaan'])) {
        $data['tanggal_pemeriksaan'] = date('Y-m-d', strtotime($data['tanggal_pemeriksaan']));
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>