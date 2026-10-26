<?php
require_once 'config.php';
$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM data_jumantik WHERE id = $id");
header("Location: data-jumantik.php");
exit;
?>
