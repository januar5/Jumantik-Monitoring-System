<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Anda harus login terlebih dahulu!'
    ]);
    exit;
}

// Cek apakah user adalah RW (case-insensitive)
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'rw') {
    echo json_encode([
        'success' => false,
        'message' => 'Hanya RW yang dapat melakukan konfirmasi!'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $status = mysqli_real_escape_string($conn, $_POST['status_konfirmasi']);
    
    // Validasi status
    $valid_status = ['Menunggu', 'Disetujui', 'Ditolak'];
    if (!in_array($status, $valid_status)) {
        echo json_encode([
            'success' => false,
            'message' => 'Status konfirmasi tidak valid!'
        ]);
        exit;
    }
    
    // Update status konfirmasi
    $query = "UPDATE data_jumantik SET status_konfirmasi = '$status' WHERE id = '$id'";
    
    if (mysqli_query($conn, $query)) {
        // Log aktivitas (opsional)
        $user_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO log_aktivitas (user_id, aktivitas, keterangan, created_at) 
                      VALUES ('$user_id', 'Update Konfirmasi', 'Mengubah status konfirmasi data ID: $id menjadi $status', NOW())";
        mysqli_query($conn, $log_query);
        
        echo json_encode([
            'success' => true,
            'message' => 'Status konfirmasi berhasil diubah menjadi ' . $status . '!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>