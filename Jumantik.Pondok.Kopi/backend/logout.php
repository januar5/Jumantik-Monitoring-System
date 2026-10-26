<?php
session_start();

// Hapus semua session data
session_unset();
session_destroy();

// Redirect ke index.php
header("Location: index.php");
exit;
?>