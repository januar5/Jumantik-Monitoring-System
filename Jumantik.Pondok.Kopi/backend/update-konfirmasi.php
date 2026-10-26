<?php
session_start();
require_once 'config.php';

if ($_SESSION['role'] !== 'rw') {
    die("Akses ditolak!");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['aksi'])) {
    $id = intval($_POST['id']);
    $aksi = $_POST['aksi'];

    $status = ($aksi === 'setuju') ? 'Disetujui' : 'Ditolak';

    $query = "UPDATE data_jumantik SET status_konfirmasi = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    mysqli_stmt_execute($stmt);

    header("Location: data-jumantik.php");
    exit();
}
?>
