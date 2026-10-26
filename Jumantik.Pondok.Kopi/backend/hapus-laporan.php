<?php
require_once 'config.php';
$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM laporan_pemeriksaan WHERE id = '$id'");
header("Location: laporan.php");
exit;
?>
