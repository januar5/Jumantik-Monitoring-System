<?php
session_start();
require_once 'config.php';

$stats = [
    'kader_aktif' => 0,
    'jumlah_rw' => 0
];

$debug_mode = false;
$error = '';
$show_login = true;

// Cek koneksi database
if (!isset($conn) || !$conn) {
    die("Error: Database connection tidak tersedia. Pastikan config.php sudah benar.");
}

// Jika user sudah login, redirect ke dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: dashboard-admin.php");
            break;
        case 'kader':
            header("Location: dashboard-kader.php");
            break;
        case 'rw':
            header("Location: dashboard-rw.php");
            break;
    }
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        $query = "SELECT * FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                $password_valid = false;
                
                if (password_get_info($user['password'])['algo'] !== null) {
                    $password_valid = password_verify($password, $user['password']);
                } else {
                    $password_valid = ($password === $user['password']);
                    
                    if ($password_valid) {
                        $hashed = password_hash($password, PASSWORD_BCRYPT);
                        $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                        mysqli_stmt_bind_param($update_stmt, "si", $hashed, $user['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                }
                
                if ($password_valid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    if ($user['role'] == 'rw') {
                        $query_rw = "SELECT * FROM rw WHERE user_id = ?";
                        $stmt_rw = mysqli_prepare($conn, $query_rw);
                        mysqli_stmt_bind_param($stmt_rw, "i", $user['id']);
                        mysqli_stmt_execute($stmt_rw);
                        $result_rw = mysqli_stmt_get_result($stmt_rw);
                        
                        if ($data_rw = mysqli_fetch_assoc($result_rw)) {
                            $_SESSION['nama'] = $data_rw['nama_rw'];
                            $_SESSION['rw'] = $data_rw['username'];
                        } else {
                            $_SESSION['nama'] = $user['username'];
                        }
                        mysqli_stmt_close($stmt_rw);
                        
                    } elseif ($user['role'] == 'kader') {
                        $query_kader = "SELECT * FROM kader WHERE user_id = ?";
                        $stmt_kader = mysqli_prepare($conn, $query_kader);
                        mysqli_stmt_bind_param($stmt_kader, "i", $user['id']);
                        mysqli_stmt_execute($stmt_kader);
                        $result_kader = mysqli_stmt_get_result($stmt_kader);
                        
                        if ($data_kader = mysqli_fetch_assoc($result_kader)) {
                            $_SESSION['nama'] = $data_kader['nama_kader'];
                        } else {
                            $_SESSION['nama'] = $user['username'];
                        }
                        mysqli_stmt_close($stmt_kader);
                        
                    } else {
                        $_SESSION['nama'] = $user['nama'] ?? $user['nama_lengkap'] ?? $user['full_name'] ?? $user['username'];
                    }

                    switch ($user['role']) {
                        case 'admin':
                            header("Location: dashboard-admin.php");
                            break;
                        case 'kader':
                            header("Location: dashboard-kader.php");
                            break;
                        case 'rw':
                            header("Location: dashboard-rw.php");
                            break;
                    }
                    exit();
                } else {
                    $error = "Username atau password salah!";
                }
            } else {
                $error = "Username atau password salah!";
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}

// Jika tidak ada error login, maka tampilkan halaman utama
// Ambil statistik hanya saat halaman utama ditampilkan
if (empty($error) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    $check_kader = mysqli_query($conn, "SHOW TABLES LIKE 'kader'");
    if ($check_kader && mysqli_num_rows($check_kader) > 0) {
        $query_kader = "SELECT COUNT(*) as total FROM kader";
        $result = mysqli_query($conn, $query_kader);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['kader_aktif'] = intval($row['total']);
        }
    } else {
        $query_kader_users = "SELECT COUNT(*) as total FROM users WHERE role = 'kader'";
        $result = mysqli_query($conn, $query_kader_users);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['kader_aktif'] = intval($row['total']);
        }
    }

    $check_rw = mysqli_query($conn, "SHOW TABLES LIKE 'rw'");
    if ($check_rw && mysqli_num_rows($check_rw) > 0) {
        $query_rw = "SELECT COUNT(DISTINCT username) as total FROM rw";
        $result = mysqli_query($conn, $query_rw);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['jumlah_rw'] = intval($row['total']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($error) ? 'Login | Jumantik Pondok Kopi' : 'Sistem Jumantik - Kelurahan Pondok Kopi' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background: #f8f9fa;
        }

        /* ===== LOGIN PAGE STYLES ===== */
        .login-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-page h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-danger {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .login-page form {
            display: flex;
            flex-direction: column;
        }

        .login-page label {
            color: #555;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .login-page input[type="text"],
        .login-page input[type="password"] {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .login-page input[type="text"]:focus,
        .login-page input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-page button[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .login-page button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .login-page button[type="submit"]:active {
            transform: translateY(0);
        }

        .footer-info {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 12px;
        }

        /* ===== HOME PAGE STYLES ===== */
        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            overflow: hidden;
        }

        .logo-text h1 {
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }

        .logo-text h1 span {
            color: #ff69b4;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
            padding: 8px 0;
        }

        nav a:hover, nav a.active {
            color: #ff69b4;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            padding: 10px 25px !important;
            border-radius: 25px;
            transition: transform 0.3s, box-shadow 0.3s;
            display: inline-block;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white !important;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #333;
        }

        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
        }

        .hero-content {
            max-width: 900px;
            margin: 0 auto;
        }

        .hero h2 {
            font-size: 42px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .hero p {
            font-size: 18px;
            line-height: 1.8;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .hero-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .stats-section {
            background: white;
            padding: 60px 20px;
            margin-top: -40px;
            position: relative;
            z-index: 10;
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .about-section {
            padding: 80px 20px;
            background: white;
        }

        .about-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .about-content h2 {
            font-size: 36px;
            margin-bottom: 20px;
            color: #333;
        }

        .about-content h2 span {
            color: #ff69b4;
        }

        .about-content p {
            font-size: 16px;
            line-height: 1.8;
            color: #666;
            margin-bottom: 15px;
        }

        .about-image {
            position: relative;
        }

        .about-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .features-section {
            padding: 80px 20px;
            background: #f8f9fa;
        }

        .section-title {
            text-align: center;
            font-size: 36px;
            margin-bottom: 50px;
            color: #333;
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .feature-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: #333;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        footer {
            background: #2c3e50;
            color: white;
            padding: 40px 20px 20px;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto 30px;
        }

        .footer-content h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }

        .footer-content p {
            color: rgba(255,255,255,0.8);
            line-height: 1.8;
            margin-bottom: 10px;
        }

        .footer-bottom {
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: row;
                justify-content: space-between;
            }

            .mobile-menu-toggle {
                display: block;
            }

            nav ul {
                display: none;
                position: absolute;
                top: 80px;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 20px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                gap: 15px;
            }

            nav ul.active {
                display: flex;
            }

            .hero h2 {
                font-size: 32px;
            }

            .about-container {
                grid-template-columns: 1fr;
            }

            .about-image {
                order: -1;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .login-container {
                padding: 30px 20px;
            }

            .login-page h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($error)): ?>
    <!-- LOGIN PAGE -->
    <div class="login-page">
        <div class="login-container">
            <h2>🦟 Login Sistem Jumantik Pondok Kopi</h2>

            <div class="alert alert-danger">❌ <?= htmlspecialchars($error); ?></div>

            <form method="POST" action="">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autofocus placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Masukkan password">

                <button type="submit">🔐 Masuk</button>
            </form>

            <div class="footer-info">
                Sistem Pemantauan Jentik Nyamuk<br>
                Kelurahan Pondok Kopi
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- HOME PAGE -->
    <header>
        <div class="navbar">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <img src="../images/logo.png" alt="logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <div class="logo-text">
                    <h1>KELURAHAN <span>PONDOK KOPI</span></h1>
                </div>
            </a>
            <button class="mobile-menu-toggle" onclick="toggleMenu()">☰</button>
            <nav>
                <ul id="mainMenu">
                    <li><a href="index.php" class="active">Beranda</a></li>
                    <li><a href="#tentang">Tentang</a></li>
                    <li><a href="#program">Program</a></li>
                    <li><a href="javascript:void(0)" onclick="showLoginModal()" class="btn-login">🔐 Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <div class="hero-icon">🦟</div>
            <h2>Sistem Jumantik Pondok Kopi</h2>
            <p>Juru Pemantau Jentik - Bersama Cegah Demam Berdarah<br>
            Pemantauan dan Pencegahan DBD Berbasis Komunitas</p>
        </div>
    </section>

    <section class="stats-section">
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?= number_format($stats['kader_aktif']) ?></div>
                <div class="stat-label">Kader Aktif</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏘️</div>
                <div class="stat-number"><?= number_format($stats['jumlah_rw']) ?></div>
                <div class="stat-label">Wilayah RW</div>
            </div>
        </div>
    </section>

<section class="about-section" id="tentang">
        <div class="about-container">
            <div class="about-content">
                <h2>Apa itu <span>Jumantik?</span></h2>
                <p><strong>Jumantik (Juru Pemantau Jentik)</strong> adalah program kesehatan masyarakat yang bertujuan untuk memantau dan mencegah perkembangbiakan nyamuk Aedes aegypti, vektor penyebab Demam Berdarah Dengue (DBD).</p>
                <p>Kelurahan Pondok Kopi mengimplementasikan sistem digital untuk mempermudah pelaporan dan pemantauan kegiatan jumantik di seluruh wilayah RW dan RT.</p>
                <p>Program ini melibatkan kader kesehatan, petugas RW, dan masyarakat dalam upaya 3M Plus:</p>
                <p><strong>🚰 Menguras</strong> - <strong>🧹 Menutup</strong> - <strong>♻️ Mendaur Ulang</strong></p>
            </div>
            <div class="about-image">
                <img src="../images/map.png" alt="Logo Jumantik" style="width: 100%; height: 400px; object-fit: contain; border-radius: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px;">
            </div>
        </div>
    </section>

    <section class="features-section" id="program">
        <h2 class="section-title">Program & Layanan</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🔍</div>
                <h3>Pemantauan Rutin</h3>
                <p>Pemeriksaan jentik berkala di setiap rumah oleh kader jumantik terlatih untuk deteksi dini.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Sistem Digital</h3>
                <p>Pelaporan dan monitoring real-time berbasis aplikasi untuk kemudahan akses data.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Edukasi Masyarakat</h3>
                <p>Sosialisasi dan penyuluhan kesehatan tentang pencegahan DBD di lingkungan sekitar.</p>
            </div>
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 20px; padding: 40px; max-width: 420px; width: 100%; margin: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideIn 0.3s ease;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 24px; color: #333; margin: 0;">🦟 Login Jumantik</h2>
                <button onclick="closeLoginModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
            </div>

            <form method="POST" action="">
                <label for="username" style="color: #555; font-weight: 600; margin-bottom: 8px; font-size: 14px; display: block;">Username:</label>
                <input type="text" id="username" name="username" required autofocus placeholder="Masukkan username" style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; margin-bottom: 20px; width: 100%; transition: all 0.3s;">

                <label for="password" style="color: #555; font-weight: 600; margin-bottom: 8px; font-size: 14px; display: block;">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Masukkan password" style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; margin-bottom: 20px; width: 100%; transition: all 0.3s;">

                <button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; width: 100%;">🔐 Masuk</button>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <h3>🦟 Sistem Jumantik Kelurahan Pondok Kopi</h3>
            <p>Cegah DBD, Selamatkan Keluarga | Kecamatan Duren Sawit, Jakarta Timur</p>
        </div>
        <div class="footer-bottom">
            <p>&copy;2026 Kelurahan Pondok Kopi. Sistem Jumantik Digital</p>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            const menu = document.getElementById('mainMenu');
            menu.classList.toggle('active');
        }

        function showLoginModal() {
            document.getElementById('loginModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLoginModal();
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLoginModal();
            }
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    document.getElementById('mainMenu').classList.remove('active');
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>