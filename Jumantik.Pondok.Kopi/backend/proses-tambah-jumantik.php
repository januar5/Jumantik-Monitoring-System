<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak");
}

$petugas_id = $_SESSION['user_id'];
$nama_kepala_keluarga = $_POST['nama_kepala_keluarga'];
$alamat = $_POST['alamat'];
$rt = $_POST['rt'];
$rw = $_POST['rw'];
$jentik_ditemukan = $_POST['jentik_ditemukan'];
$tanggal_pemeriksaan = date('Y-m-d');
$status_konfirmasi = "Menunggu";

// Upload foto
$target_dir = "uploads/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$file_name = basename($_FILES["foto_rumah"]["name"]);
$target_file = $target_dir . time() . "_" . $file_name;
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','gif'];

if (!in_array($imageFileType, $allowed)) {
    die("Format gambar tidak didukung!");
}

if (move_uploaded_file($_FILES["foto_rumah"]["tmp_name"], $target_file)) {
    $file_to_save = basename($target_file);

    $query = "INSERT INTO data_jumantik 
        (petugas_id, nama_kepala_keluarga, alamat, rt, rw, jentik_ditemukan, tanggal_pemeriksaan, foto_rumah, status_konfirmasi)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "issssssss", 
        $petugas_id, 
        $nama_kepala_keluarga, 
        $alamat, 
        $rt, 
        $rw, 
        $jentik_ditemukan, 
        $tanggal_pemeriksaan, 
        $file_to_save, 
        $status_konfirmasi
    );

    if (mysqli_stmt_execute($stmt)) {
        header("Location: data-jumantik.php");
        exit();
    } else {
        echo "Gagal menyimpan data: " . mysqli_error($conn);
    }
} else {
    echo "Gagal upload foto!";
}
?>
