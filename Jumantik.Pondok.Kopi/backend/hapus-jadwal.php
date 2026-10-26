<?php
require_once 'config.php';

$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM jadwal_pemeriksaan WHERE id = '$id'");

header("Location: jadwal.php");
exit;
?>
