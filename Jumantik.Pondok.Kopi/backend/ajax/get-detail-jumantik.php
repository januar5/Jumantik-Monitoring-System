<?php
session_start();
require_once '../config.php'; // ← Pastikan path ini benar

header('Content-Type: application/json');

// Pastikan hanya kader yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kader') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data kader
$stmt = mysqli_prepare($conn, "SELECT id FROM kader WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kader = mysqli_fetch_assoc($result);

if (!$kader) {
    echo json_encode(['success' => false, 'message' => 'Kader tidak ditemukan']);
    exit;
}

$kader_id = $kader['id'];
mysqli_stmt_close($stmt);

// Ambil ID dari GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$id = intval($_GET['id']);

// Ambil detail data jumantik
$query = "SELECT * FROM data_jumantik WHERE id = ? AND kader_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $id, $kader_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if ($data) {
    // Format tanggal untuk input date
    if (isset($data['tanggal_pemeriksaan'])) {
        $data['tanggal_pemeriksaan'] = date('Y-m-d', strtotime($data['tanggal_pemeriksaan']));
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak ditemukan'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>