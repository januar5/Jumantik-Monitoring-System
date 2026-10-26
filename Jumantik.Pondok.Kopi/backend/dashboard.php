<?php
session_start();
require_once 'config.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

// Ambil statistik
$stats = [];

// Total Petugas
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM petugas");
$stats['petugas'] = mysqli_fetch_assoc($result)['total'];

// Total RT/RW
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM rt_rw");
$stats['rt_rw'] = mysqli_fetch_assoc($result)['total'];

// Total Data Jumantik
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM data_jumantik");
$stats['jumantik'] = mysqli_fetch_assoc($result)['total'];

// Jentik Ditemukan
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM data_jumantik WHERE jentik_ditemukan = 'Ya'");
$stats['jentik_positif'] = mysqli_fetch_assoc($result)['total'];

// Jentik Negatif
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM data_jumantik WHERE jentik_ditemukan = 'Tidak'");
$stats['jentik_negatif'] = mysqli_fetch_assoc($result)['total'];

// Data Terbaru
$recent_data = mysqli_query($conn, "
    SELECT dj.*, p.nama_lengkap 
    FROM data_jumantik dj 
    LEFT JOIN petugas p ON dj.petugas_id = p.id 
    ORDER BY dj.created_at DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Jumantik Pondok Kopi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <i class="fas fa-bug"></i> Jumantik.pk
                </div>
                <div class="sidebar-user">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h4><?php echo $_SESSION['nama']; ?></h4>
                        <p><?php echo ucfirst($_SESSION['role']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-home"></i> Beranda
                </a>
                <a href="data-petugas.php" class="menu-item">
                    <i class="fas fa-users"></i> Data Petugas
                </a>
                <a href="data-jumantik.php" class="menu-item">
                    <i class="fas fa-clipboard-list"></i> Data Jumantik
                </a>
                <a href="galeri.php" class="menu-item">
                    <i class="fas fa-images"></i> Galeri
                </a>
                <a href="jadwal.php" class="menu-item">
                    <i class="fas fa-calendar"></i> Jadwal
                </a>
                <a href="laporan.php" class="menu-item">
                    <i class="fas fa-file-alt"></i> Laporan
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i> Keluar
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="topbar">
                <h2>Dashboard</h2>
                <div class="topbar-right">
                    <span><?php echo date('d F Y'); ?></span>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['petugas']; ?></h4>
                        <p>Total Petugas</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['rt_rw']; ?></h4>
                        <p>Total RT/RW</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['jumantik']; ?></h4>
                        <p>Data Jumantik</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-bug"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['jentik_positif']; ?></h4>
                        <p>Jentik (+)</p>
                    </div>
                </div>
            </div>
            
            <!-- Info Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Sistem Pendataan Jumantik</h3>
                </div>
                <div class="card-body">
                    <p>Website ini dibuat untuk membantu kegiatan para Juru Pemantau Jentik (Jumantik), di Kelurahan Pondok Kopi. Aplikasi ini untuk memudahkan proses pencatatan digital dengan fitur-fitur penyimpanan data, dan statistik.</p>
                </div>
            </div>
            
            <!-- Recent Data -->
            <div class="card">
                <div class="card-header flex-between">
                    <h3><i class="fas fa-list"></i> Data Terbaru</h3>
                    <a href="data-jumantik.php" class="btn btn-primary btn-sm">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>RT/RW</th>
                                    <th>Nama KK</th>
                                    <th>Alamat</th>
                                    <th>Status</th>
                                    <th>Jentik</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while($row = mysqli_fetch_assoc($recent_data)): 
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo $row['rt_rw']; ?></td>
                                    <td><?php echo $row['nama_kepala_keluarga']; ?></td>
                                    <td><?php echo substr($row['alamat'], 0, 30) . '...'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status_pemeriksaan'] == 'Sudah' ? 'success' : 'warning'; ?>">
                                            <?php echo $row['status_pemeriksaan']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['jentik_ditemukan'] == 'Ya' ? 'danger' : 'success'; ?>">
                                            <?php echo $row['jentik_ditemukan']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($row['tanggal_pemeriksaan'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>