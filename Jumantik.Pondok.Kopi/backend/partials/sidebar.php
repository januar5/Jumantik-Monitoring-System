<?php
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Jumantik</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="sidebar">
    <h2><i class="fa-solid fa-mosquito"></i> Jumantik</h2>
    <ul>
        <!-- Menu Beranda -->
        <li>
            <a href="dashboard-<?php echo $_SESSION['role']; ?>.php">
                <i class="fa fa-home"></i> Beranda
            </a>
        </li>

        <!-- Menu untuk Kader -->
        <?php if ($_SESSION['role'] == 'kader'): ?>
            <li><a href="data-jumantik.php"><i class="fa fa-database"></i> Data Jumantik</a></li>
            <li><a href="jadwal.php"><i class="fa fa-calendar"></i> Jadwal</a></li>
            <li><a href="laporan.php"><i class="fa fa-file"></i> Laporan</a></li>

        <!-- Menu untuk Admin -->
        <?php elseif ($_SESSION['role'] == 'admin'): ?>
            <li><a href="data-petugas.php"><i class="fa fa-users"></i> Data Petugas</a></li>
            <li><a href="data-rw.php"><i class="fa fa-user-shield"></i> Data RW</a></li>
            <li><a href="rekap.php"><i class="fa fa-chart-bar"></i> Rekap</a></li>

        <!-- Menu untuk RW -->
        <?php elseif ($_SESSION['role'] == 'rw'): ?>
            <li><a href="laporan.php"><i class="fa fa-file-lines"></i> Laporan RW</a></li>
        <?php endif; ?>

        <!-- Tombol Logout -->
        <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Keluar</a></li>
    </ul>
</div>
