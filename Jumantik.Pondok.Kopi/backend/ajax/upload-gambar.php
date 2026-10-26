<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Cek user login
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'rw') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['id']) || !isset($_FILES['foto_rumah'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$id = intval($_POST['id']);
$file = $_FILES['foto_rumah'];

// Validasi file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error upload file']);
    exit;
}

// Validasi tipe file
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan. Hanya JPG, PNG, GIF']);
    exit;
}

// Validasi ukuran (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB']);
    exit;
}

// Buat nama file unik
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = 'foto_' . time() . '_' . uniqid() . '.' . $extension;
$upload_dir = '../uploads/';

// Buat folder jika belum ada
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$target_path = $upload_dir . $new_filename;

// Ambil foto lama untuk dihapus
$stmt = mysqli_prepare($conn, "SELECT foto_rumah FROM data_jumantik WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$old_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Upload file baru
if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Update database
    $stmt = mysqli_prepare($conn, "UPDATE data_jumantik SET foto_rumah = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $new_filename, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Hapus foto lama jika ada
        if (!empty($old_data['foto_rumah'])) {
            $old_file = $upload_dir . $old_data['foto_rumah'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Gambar berhasil diupload']);
    } else {
        mysqli_stmt_close($stmt);
        unlink($target_path); // Hapus file yang baru diupload
        echo json_encode(['success' => false, 'message' => 'Gagal update database']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal upload file']);
}
?>