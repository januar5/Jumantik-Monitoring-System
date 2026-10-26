<?php
session_start();
require_once 'config.php';

// Pastikan user sudah login dan role-nya kader
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'kader') {
    header("Location: login.php");
    exit;
}

// Ambil user_id dari session
$user_id = $_SESSION['user_id'];

// Ambil data dari tabel kader
$stmt = mysqli_prepare($conn, "SELECT * FROM kader WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kader = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Validasi jika kader tidak ditemukan
if (!$kader) {
    die("Error: Data kader tidak ditemukan. User ID: " . $user_id);
}

// Tentukan nama yang akan ditampilkan
$nama_display = 'Kader';
if (isset($kader['nama_kader']) && !empty($kader['nama_kader']) && $kader['nama_kader'] !== null) {
    $nama_display = $kader['nama_kader'];
} elseif (isset($_SESSION['nama']) && !empty($_SESSION['nama']) && $_SESSION['nama'] !== null) {
    $nama_display = $_SESSION['nama'];
} elseif (isset($_SESSION['username']) && !empty($_SESSION['username']) && $_SESSION['username'] !== null) {
    $nama_display = $_SESSION['username'];
}

// Statistik dinamis menggunakan kader_id
$kader_id = $kader['id'] ?? null;
$total_pemeriksaan = 0;
$total_jentik = 0;
$total_bebas = 0;

if ($kader_id) {
    // Total pemeriksaan
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS tot FROM data_jumantik WHERE kader_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $kader_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_pemeriksaan = intval(mysqli_fetch_assoc($result)['tot']);
    mysqli_stmt_close($stmt);

    // Rumah dengan jentik
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS tot FROM data_jumantik WHERE kader_id = ? AND jentik_ditemukan = 'Ya'");
    mysqli_stmt_bind_param($stmt, "i", $kader_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_jentik = intval(mysqli_fetch_assoc($result)['tot']);
    mysqli_stmt_close($stmt);

    // Rumah bebas jentik
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS tot FROM data_jumantik WHERE kader_id = ? AND jentik_ditemukan = 'Tidak'");
    mysqli_stmt_bind_param($stmt, "i", $kader_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_bebas = intval(mysqli_fetch_assoc($result)['tot']);
    mysqli_stmt_close($stmt);
}

// Hitung persentase bebas jentik
$persentase_bebas = $total_pemeriksaan > 0 ? round(($total_bebas / $total_pemeriksaan) * 100, 1) : 0;

// Format RT/RW display
$rt_display = (isset($kader['rt']) && $kader['rt'] !== null && $kader['rt'] !== '') ? $kader['rt'] : '-';
$rw_display = (isset($kader['rw']) && $kader['rw'] !== null && $kader['rw'] !== '') ? $kader['rw'] : '-';
$rt_rw_display = $rt_display . ' / ' . $rw_display;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Kader | Jumantik Pondok Kopi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f1f5f9;
            overflow-x: hidden;
        }
        
        /* === NAVBAR === */
        .navbar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            /* Remove heavy shadow to avoid thin visible line; keep background solid */
            box-shadow: none;
            border-bottom: none;
        }

        /* Fix: keep the main dashboard background consistent to avoid showing body gradient */
        .dashboard {
            background: #f8fafc !important;
        }

        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 700;
            text-decoration: none;
            color: white;
        }

        .navbar-brand span {
            color: #fbbf24;
        }

        .navbar-menu {
            display: flex;
            gap: 5px;
            list-style: none;
        }

        .navbar-menu li a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 15px;
        }

        .navbar-menu li a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .navbar-menu li a.active {
            background: rgba(251, 191, 36, 0.2);
            border: 2px solid #fbbf24;
        }

        .navbar-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 5px;
            background: none;
            border: none;
        }

        .navbar-toggle span {
            width: 28px;
            height: 3px;
            background: white;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .navbar-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .navbar-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .navbar-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        .navbar-mobile {
            display: none;
            position: fixed;
            top: 62px; /* aligns with navbar height */
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            padding: 15px;
            box-shadow: none;
            border-top: none;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            z-index: 999;
        }

        .navbar-mobile.active {
            max-height: 350px;
        }

        .navbar-mobile ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .navbar-mobile ul li a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 15px;
            background: rgba(255, 255, 255, 0.05);
        }

        .navbar-mobile ul li a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .navbar-mobile ul li a.active {
            background: rgba(251, 191, 36, 0.2);
            border-left: 4px solid #fbbf24;
        }
        
        /* === MAIN CONTENT === */
        .main-content {
            margin-top: 90px;
            padding: 20px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .header h1 {
            color: #1e293b;
            font-size: 1.8rem;
            margin: 0;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            display: inline-block;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        /* === WELCOME BANNER === */
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-banner h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: white;
        }
        
        .welcome-banner p {
            font-size: 1.1rem;
            opacity: 0.95;
            color: white;
            margin: 5px 0;
        }
        
        .percentage-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 25px;
            margin-top: 10px;
            font-weight: 600;
        }
        
        /* === INFO GRID === */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .info-card h3 {
            font-size: 1rem;
            color: #64748b;
            margin: 0 0 15px 0;
            font-weight: 600;
        }
        
        .info-card p {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin: 0;
        }
        
        .info-card:nth-child(1) {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid #667eea;
        }
        
        .info-card:nth-child(2) {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
            border-left: 4px solid #dc2626;
        }
        
        .info-card:nth-child(2) p {
            color: #dc2626;
        }
        
        .info-card:nth-child(3) {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(22, 163, 74, 0.1) 100%);
            border-left: 4px solid #16a34a;
        }
        
        .info-card:nth-child(3) p {
            color: #16a34a;
        }
        
        .info-card:nth-child(4) {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
            border-left: 4px solid #2563eb;
        }
        
        .info-card:nth-child(4) p {
            color: #2563eb;
        }
        
        /* === INFO SECTION === */
        .info-section {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .info-section h3 {
            margin: 0 0 15px 0;
            color: #1e293b;
            font-size: 1.3rem;
        }
        
        .info-section ul {
            line-height: 2;
            color: #64748b;
            margin: 0;
            padding-left: 20px;
        }
        
        .info-section ul li {
            margin-bottom: 8px;
        }
        
        /* === RESPONSIVE DESIGN === */
        @media (max-width: 1024px) {
            .header h1 {
                font-size: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .welcome-banner h2 {
                font-size: 1.6rem;
            }
        }
        
        @media (max-width: 768px) {
            .navbar-menu {
                display: none;
            }

            .navbar-toggle {
                display: flex;
            }

            .navbar-mobile {
                display: block;
            }

            .navbar-brand {
                font-size: 20px;
            }

            .main-content {
                margin-top: 75px;
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
                gap: 10px;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
            
            .logout-btn {
                width: auto;
                padding: 8px 20px;
                font-size: 14px;
            }
            
            .welcome-banner {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .welcome-banner h2 {
                font-size: 1.4rem;
                margin-bottom: 8px;
            }
            
            .welcome-banner p {
                font-size: 1rem;
                margin: 3px 0;
            }
            
            .percentage-badge {
                font-size: 0.9rem;
                padding: 6px 12px;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .info-card {
                padding: 20px 15px;
            }
            
            .info-card h3 {
                font-size: 0.85rem;
            }
            
            .info-card p {
                font-size: 2rem;
            }
            
            .info-section {
                padding: 20px;
            }
            
            .info-section h3 {
                font-size: 1.1rem;
            }
            
            .info-section ul {
                font-size: 0.9rem;
                line-height: 1.8;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .header {
                padding: 12px;
            }
            
            .header h1 {
                font-size: 1.1rem;
            }
            
            .welcome-banner {
                padding: 15px;
            }
            
            .welcome-banner h2 {
                font-size: 1.2rem;
            }
            
            .welcome-banner p {
                font-size: 0.9rem;
            }
            
            .percentage-badge {
                font-size: 0.85rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .info-card {
                padding: 18px 12px;
            }
            
            .info-card h3 {
                font-size: 0.8rem;
            }
            
            .info-card p {
                font-size: 1.8rem;
            }
            
            .info-section {
                padding: 15px;
            }
            
            .info-section h3 {
                font-size: 1rem;
            }
            
            .info-section ul {
                font-size: 0.85rem;
                padding-left: 18px;
            }

            .navbar-container {
                padding: 12px 15px;
            }

            .navbar-brand {
                font-size: 18px;
            }

            .navbar-mobile ul li a {
                padding: 12px 15px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 360px) {
            .welcome-banner h2 {
                font-size: 1.1rem;
            }
            
            .info-card p {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="navbar-brand">
                <span>🦟</span> Jumantik
            </a>

            <!-- Desktop Menu -->
            <ul class="navbar-menu">
                <li><a href="dashboard-kader.php" class="active">🏠 Beranda</a></li>
                <li><a href="data-jumantik.php">🔍 Data Jumantik</a></li>
                <li><a href="laporan-kader.php">📋 Laporan</a></li>
                <li><a href="logout.php">🚪 Keluar</a></li>
            </ul>

            <!-- Mobile Toggle -->
            <button class="navbar-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="navbar-mobile" id="mobileMenu">
        <ul>
            <li><a href="dashboard-kader.php" class="active">🏠 Beranda</a></li>
            <li><a href="data-jumantik.php">🔍 Data Jumantik</a></li>
            <li><a href="laporan-kader.php">📋 Laporan</a></li>
            <li><a href="logout.php">🚪 Keluar</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="header">
            <h1>Dashboard Kader Jumantik</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <section class="welcome-banner">
            <h2>Selamat Datang, <?= htmlspecialchars($nama_display); ?> 👋</h2>
            <p>Anda login sebagai <strong>Kader Jumantik</strong></p>
            <p><?= htmlspecialchars($rt_rw_display); ?></p>
            <span class="percentage-badge">✨ Tingkat Bebas Jentik: <?= $persentase_bebas; ?>%</span>
        </section>

        <section class="info-grid">
            <div class="info-card">
                <h3>📋 Total Pemeriksaan</h3>
                <p><?= number_format($total_pemeriksaan); ?></p>
            </div>
            <div class="info-card">
                <h3>🦟 Rumah Ada Jentik</h3>
                <p><?= number_format($total_jentik); ?></p>
            </div>
            <div class="info-card">
                <h3>✅ Rumah Bebas Jentik</h3>
                <p><?= number_format($total_bebas); ?></p>
            </div>
            <div class="info-card">
                <h3>📊 Persentase Bebas</h3>
                <p><?= $persentase_bebas; ?>%</p>
            </div>
        </section>

        <section class="info-section">
            <h3>📌 Informasi Penting</h3>
            <ul>
                <li>🔍 Lakukan pemeriksaan rutin setiap minggu</li>
                <li>📝 Catat setiap temuan dengan detail</li>
                <li>🏘️ Pastikan seluruh area RT/RW tercover</li>
                <li>💡 Edukasi warga tentang 3M Plus</li>
            </ul>
        </section>
    </main>

<script>
function toggleMobileMenu() {
    const toggle = document.querySelector('.navbar-toggle');
    const mobileMenu = document.getElementById('mobileMenu');
    
    toggle.classList.toggle('active');
    mobileMenu.classList.toggle('active');
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const navbar = document.querySelector('.navbar');
    const mobileMenu = document.getElementById('mobileMenu');
    const toggle = document.querySelector('.navbar-toggle');
    
    if (!navbar.contains(event.target) && mobileMenu.classList.contains('active')) {
        toggle.classList.remove('active');
        mobileMenu.classList.remove('active');
    }
});

// Close mobile menu when resizing to desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.querySelector('.navbar-toggle').classList.remove('active');
        document.getElementById('mobileMenu').classList.remove('active');
    }
});
</script>
</body>
</html>