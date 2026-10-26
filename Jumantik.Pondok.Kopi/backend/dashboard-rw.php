<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) != 'rw') {
    header("Location: login.php");
    exit;
}

// Debug mode (set ke false di production)
$debug_mode = false;

// Ambil data user dari session
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? 0;

// ============================================
// PERBAIKAN 1: Deteksi struktur tabel RW
// ============================================
// Query generik untuk melihat struktur tabel
$check_query = "DESCRIBE rw";
$check_result = mysqli_query($conn, $check_query);
$columns = [];
while ($col = mysqli_fetch_assoc($check_result)) {
    $columns[] = $col['Field'];
}

// Tentukan kolom mana yang ada
$nomor_rw_col = 'nomor_rw';  // default
if (!in_array('nomor_rw', $columns) && in_array('rw', $columns)) {
    $nomor_rw_col = 'rw';
} elseif (!in_array('nomor_rw', $columns) && !in_array('rw', $columns)) {
    $nomor_rw_col = 'username'; // fallback
}

// Debug
if ($debug_mode) {
    error_log("Kolom di tabel RW: " . implode(", ", $columns));
    error_log("Menggunakan kolom: " . $nomor_rw_col);
}

// Query yang aman - hanya ambil kolom yang ada
$query = "SELECT r.id, r.nama_rw, r.$nomor_rw_col as nomor_rw, r.user_id,
                 u.username as user_username, u.nama_lengkap 
          FROM rw r 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE u.username = ? OR r.user_id = ?
          LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
if ($stmt === false) {
    die("Error prepare: " . mysqli_error($conn) . " Query: " . $query);
}

mysqli_stmt_bind_param($stmt, "si", $username, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data_rw = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// ============================================
// PERBAIKAN 2: Ekstrak nomor RW dengan lebih baik
// ============================================
$nama_rw = $data_rw['nama_rw'] ?? $data_rw['nama_lengkap'] ?? 'RW';
$user_username = $data_rw['user_username'] ?? $username;
$rw_id = $data_rw['id'] ?? 0;

// Prioritas ekstraksi: nomor_rw → rw → username
$nomor_rw_raw = $data_rw['nomor_rw'] ?? ($data_rw['rw'] ?? $data_rw['user_username'] ?? '');

// Hapus karakter non-numerik
$nomor_rw_clean = preg_replace('/[^0-9]/', '', (string)$nomor_rw_raw);

// Konversi ke integer (remove leading zeros)
$nomor_rw_int = 0;
$nomor_rw_display = '-';

if ($nomor_rw_clean !== '') {
    $nomor_rw_int = intval($nomor_rw_clean);
    if ($nomor_rw_int > 0) {
        // Format kembali dengan padding (e.g., 01, 02, 012)
        $nomor_rw_display = str_pad((string)$nomor_rw_int, 2, '0', STR_PAD_LEFT);
    }
}

// Debug log
if ($debug_mode) {
    error_log("=== DEBUG RW DETECTION ===");
    error_log("Username: " . $username);
    error_log("RW Raw: " . print_r($data_rw, true));
    error_log("Nomor RW Clean: " . $nomor_rw_clean);
    error_log("Nomor RW Int: " . $nomor_rw_int);
    error_log("Nomor RW Display: " . $nomor_rw_display);
}

// ============================================
// PERBAIKAN 3: Query statistik lebih sederhana dan robust
// ============================================
$total_laporan = 0;
$total_rt = 0;
$rata_abj = 0;
$rumah_bebas_jentik = 0;
$rumah_ada_jentik = 0;

// Helper function untuk extract nomor RW dari string (rw, kader, dll)
function extractRWNumber($str) {
    $clean = preg_replace('/[^0-9]/', '', (string)$str);
    return $clean !== '' ? intval($clean) : 0;
}

// Jika nomor RW terdeteksi
if ($nomor_rw_int > 0) {
    
    // ===== Query 1: Total laporan (all-time) untuk RW ini =====
    // Hitung total laporan (all-time) untuk RW ini supaya sesuai dengan laporan per-RW di admin
    $query1 = "SELECT COUNT(*) as total 
               FROM data_jumantik dj 
               LEFT JOIN kader k ON dj.kader_id = k.id
               WHERE (
                   CAST(REPLACE(REPLACE(REPLACE(LOWER(dj.rw), 'rw', ''), ' ', ''), '.', '') AS UNSIGNED) = ?
                   OR CAST(REPLACE(REPLACE(REPLACE(LOWER(k.rw), 'rw', ''), ' ', ''), '.', '') AS UNSIGNED) = ?
               )";
    
    $stmt1 = mysqli_prepare($conn, $query1);
    if ($stmt1) {
        mysqli_stmt_bind_param($stmt1, "ii", $nomor_rw_int, $nomor_rw_int);
        mysqli_stmt_execute($stmt1);
        $res1 = mysqli_stmt_get_result($stmt1);
        $row1 = mysqli_fetch_assoc($res1);
        $total_laporan = intval($row1['total'] ?? 0);
        mysqli_stmt_close($stmt1);
    }
    
    // ===== Query 2: Total RT aktif =====
    $query2 = "SELECT COUNT(DISTINCT rt) as total 
               FROM kader 
               WHERE CAST(REPLACE(REPLACE(REPLACE(LOWER(rw), 'rw', ''), ' ', ''), '.', '') AS UNSIGNED) = ?";
    
    $stmt2 = mysqli_prepare($conn, $query2);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "i", $nomor_rw_int);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        $row2 = mysqli_fetch_assoc($res2);
        $total_rt = intval($row2['total'] ?? 0);
        mysqli_stmt_close($stmt2);
    }
    
    // ===== Query 3: Hitung per-rumah (unik) ABJ dan status jentik (all-time) =====
    // Kita hitung berdasarkan kombinasi nama/alamat/rt/rw agar tidak double-count saat ada beberapa pemeriksaan per rumah
    $query3 = "SELECT
                  COUNT(*) as total_houses,
                  SUM(CASE WHEN max_jentik = 0 THEN 1 ELSE 0 END) as bebas,
                  SUM(CASE WHEN max_jentik = 1 THEN 1 ELSE 0 END) as ada
               FROM (
                   SELECT
                       CONCAT(TRIM(LOWER(COALESCE(dj.nama_kepala_keluarga,''))), '||', TRIM(LOWER(COALESCE(dj.alamat,''))), '||', TRIM(COALESCE(dj.rt,'')), '||', TRIM(COALESCE(dj.rw,''))) as house_key,
                       MAX(CASE WHEN LOWER(TRIM(dj.jentik_ditemukan)) = 'ya' THEN 1 ELSE 0 END) as max_jentik
                   FROM data_jumantik dj
                   LEFT JOIN kader k ON dj.kader_id = k.id
                   WHERE (
                       CAST(REPLACE(REPLACE(REPLACE(LOWER(dj.rw), 'rw', ''), ' ', ''), '.', '') AS UNSIGNED) = ?
                       OR CAST(REPLACE(REPLACE(REPLACE(LOWER(k.rw), 'rw', ''), ' ', ''), '.', '') AS UNSIGNED) = ?
                   )
                   GROUP BY house_key
               ) as per_house";
    
    $stmt3 = mysqli_prepare($conn, $query3);
    if ($stmt3) {
        mysqli_stmt_bind_param($stmt3, "ii", $nomor_rw_int, $nomor_rw_int);
        mysqli_stmt_execute($stmt3);
        $res3 = mysqli_stmt_get_result($stmt3);
        $row3 = mysqli_fetch_assoc($res3);

        $total = intval($row3['total_houses'] ?? 0);
        $bebas = intval($row3['bebas'] ?? 0);
        $ada = intval($row3['ada'] ?? 0);

        $rumah_bebas_jentik = $bebas;
        $rumah_ada_jentik = $ada;
        $rata_abj = $total > 0 ? round(($bebas / $total) * 100, 1) : 0;

        mysqli_stmt_close($stmt3);
    }
    
    if ($debug_mode) {
        error_log("=== QUERY RESULTS (With RW Filter) ===");
        error_log("Total Laporan: " . $total_laporan);
        error_log("Total RT: " . $total_rt);
        error_log("Rumah Bebas: " . $rumah_bebas_jentik);
        error_log("Rumah Ada Jentik: " . $rumah_ada_jentik);
        error_log("Rata-rata ABJ: " . $rata_abj . "%");
    }
    
} else {
    // Fallback: jika RW tidak terdeteksi, gunakan data global
    
    if ($debug_mode) {
        error_log("WARNING: RW tidak terdeteksi, menggunakan fallback global");
    }
    
    // Global fallback: hitung total laporan (all-time)
    $q1 = mysqli_query($conn, "SELECT COUNT(*) as total FROM data_jumantik");
    $row = mysqli_fetch_assoc($q1);
    $total_laporan = intval($row['total'] ?? 0);
    
    $q2 = mysqli_query($conn, "SELECT COUNT(DISTINCT CONCAT(rt,'-',rw)) as total FROM data_jumantik");
    $row = mysqli_fetch_assoc($q2);
    $total_rt = intval($row['total'] ?? 0);
    
    // Global fallback: hitung berdasarkan rumah unik bulan ini
    $q3 = mysqli_query($conn, "SELECT COUNT(*) as total_houses,
                                      SUM(CASE WHEN max_jentik = 0 THEN 1 ELSE 0 END) as bebas,
                                      SUM(CASE WHEN max_jentik = 1 THEN 1 ELSE 0 END) as ada
                               FROM (
                                   SELECT CONCAT(TRIM(LOWER(COALESCE(nama_kepala_keluarga,''))), '||', TRIM(LOWER(COALESCE(alamat,''))), '||', TRIM(COALESCE(rt,'')), '||', TRIM(COALESCE(rw,''))) as house_key,
                                          MAX(CASE WHEN LOWER(TRIM(jentik_ditemukan)) = 'ya' THEN 1 ELSE 0 END) as max_jentik
                                   FROM data_jumantik
                                   GROUP BY house_key
                               ) as per_house");
    $row = mysqli_fetch_assoc($q3);

    $total = intval($row['total_houses'] ?? 0);
    $bebas = intval($row['bebas'] ?? 0);
    $ada = intval($row['ada'] ?? 0);

    $rumah_bebas_jentik = $bebas;
    $rumah_ada_jentik = $ada;
    $rata_abj = $total > 0 ? round(($bebas / $total) * 100, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard RW - Jumantik</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* === MOBILE MENU TOGGLE === */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1002;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: background 0.3s ease;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        
        .menu-toggle:hover {
            background: #5568d3;
        }
        
        .menu-toggle:active {
            background: #4458c2;
        }
        
        /* Fix: remove thin blue line under logo by removing sidebar shadow and matching dashboard bg */
        .sidebar {
            box-shadow: none !important;
            border-bottom: none !important;
        }
        .dashboard {
            background: #f8fafc !important;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: block;
            }
            
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 280px;
                max-width: 85%;
                position: fixed;
                left: -100%;
                z-index: 1001;
                transition: left 0.3s ease;
                height: 100vh;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .content {
                width: 100% !important;
                margin-left: 0 !important;
                padding: 15px;
                padding-top: 70px;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                height: 100vh;
                position: relative;
                z-index: 1;
            }
            
            body {
                overflow-x: hidden;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
                padding: 15px !important;
            }
            
            .header h1 {
                font-size: 1.2rem;
            }
            
            /* Stats Grid Responsive */
            .content > div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
                margin: 20px 0 !important;
            }
            
            /* Card Responsive - Stat Cards */
            .content > div[style*="grid"] > div {
                padding: 20px !important;
            }
            
            .content > div[style*="grid"] > div > div[style*="font-size: 3rem"] {
                font-size: 2rem !important;
            }
            
            /* Chart Container Responsive */
            .table-container {
                margin-top: 20px !important;
            }
            
            /* Chart Grid Layout */
            .table-container > div[style*="padding: 40px"] {
                padding: 20px !important;
            }
            
            .table-container > div > div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
            }
            
            .table-container > div > div > div[style*="max-width: 400px"] {
                max-width: 100% !important;
            }
            
            .table-container canvas {
                max-width: 100%;
                height: auto !important;
            }
            
            /* Welcome Section */
            .welcome {
                padding: 20px !important;
            }
            
            .welcome h2 {
                font-size: 1.2rem !important;
            }
            
            .welcome p {
                font-size: 0.9rem !important;
            }
            
            /* Chart title responsive */
            .table-container > div > h3 {
                font-size: 1.2rem !important;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1rem;
            }
            
            .content {
                padding: 10px;
                padding-top: 70px;
            }
            
            .content > div[style*="grid"] > div > div[style*="font-size: 3rem"] {
                font-size: 1.8rem !important;
            }
            
            .content > div[style*="grid"] > div {
                padding: 15px !important;
            }
            
            .table-container > div[style*="padding: 40px"] {
                padding: 15px !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <h2>🦟 Jumantik</h2>
            <ul>
                <li><a href="dashboard-rw.php" class="active">🏠 Beranda</a></li>
                <li><a href="laporan-rw.php">📄 Laporan RW</a></li>
                <li><a href="logout.php">🚪 Keluar</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="header">
                <h1>Dashboard RW</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>

            <div class="welcome">
                <h2>Selamat Datang, <?= htmlspecialchars($nama_rw); ?> 🙌</h2>
                <p style="font-size: 1.1rem; margin: 10px 0 0 0;">
                    <strong>Wilayah:</strong> RW <?= htmlspecialchars($nomor_rw_display); ?>
                </p>
                <p style="margin: 8px 0 0 0; color: #64748b;">
                    Anda dapat melihat laporan hasil pemeriksaan jentik di wilayah Anda.
                </p>
                <p style="margin: 5px 0 0 0; color: #94a3b8; font-size: 0.85rem;">
                    Login sebagai: <?= htmlspecialchars($user_username); ?>
                </p>
            </div>

            <!-- Statistik Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin: 30px 0;">
                
                <!-- Card 1: Total Laporan -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -20px; right: -20px; font-size: 120px; opacity: 0.1;">📋</div>
                    <div style="position: relative; z-index: 1;">
                        <h3 style="font-size: 0.9rem; font-weight: 500; opacity: 0.9; margin: 0 0 15px 0;">📋 Total Laporan</h3>
                        <div style="font-size: 3rem; font-weight: 700; margin: 10px 0;"><?= $total_laporan; ?></div>
                        <p style="font-size: 0.9rem; opacity: 0.8; margin: 0;">Total</p>
                    </div>
                </div>

                <!-- Card 2: Total RT -->
                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(240, 147, 251, 0.3); position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -20px; right: -20px; font-size: 120px; opacity: 0.1;">🏘️</div>
                    <div style="position: relative; z-index: 1;">
                        <h3 style="font-size: 0.9rem; font-weight: 500; opacity: 0.9; margin: 0 0 15px 0;">🏘️ Total RT</h3>
                        <div style="font-size: 3rem; font-weight: 700; margin: 10px 0;"><?= $total_rt; ?></div>
                        <p style="font-size: 0.9rem; opacity: 0.8; margin: 0;">RT Aktif</p>
                    </div>
                </div>

                <!-- Card 3: Rata-rata ABJ -->
                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(79, 172, 254, 0.3); position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -20px; right: -20px; font-size: 120px; opacity: 0.1;">📊</div>
                    <div style="position: relative; z-index: 1;">
                        <h3 style="font-size: 0.9rem; font-weight: 500; opacity: 0.9; margin: 0 0 15px 0;">📊 Rata-rata ABJ</h3>
                        <div style="font-size: 3rem; font-weight: 700; margin: 10px 0;"><?= $rata_abj; ?>%</div>
                        <p style="font-size: 0.9rem; opacity: 0.8; margin: 0;">Total</p>
                    </div>
                </div>

            </div>

            <!-- Statistik RW dengan Pie Chart -->
            <div class="table-container" style="margin-top: 30px;">
                <div style="padding: 25px;">
                    <h3 style="color: #1e293b; font-size: 1.4rem; margin: 0; font-weight: 600;">📊 Statistik RW <?= htmlspecialchars($nomor_rw_display); ?></h3>
                </div>
                <div style="padding: 40px; background: white;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center;">
                        <!-- Pie Chart -->
                        <div style="max-width: 400px; margin: 0 auto;">
                            <canvas id="pieChart"></canvas>
                        </div>
                        <!-- Legend & Info -->
                        <div>
                            <h4 style="color: #1e293b; font-size: 1.2rem; margin: 0 0 20px 0;">Status Pemeriksaan Jentik</h4>
                            <div style="display: flex; flex-direction: column; gap: 15px;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 24px; height: 24px; background: #10b981; border-radius: 4px;"></div>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b;">Rumah Bebas Jentik</div>
                                        <div style="color: #64748b; font-size: 0.9rem;"><?= $rumah_bebas_jentik; ?> rumah (<?= $rata_abj; ?>%)</div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 24px; height: 24px; background: #ef4444; border-radius: 4px;"></div>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b;">Rumah Ada Jentik</div>
                                        <?php $persen_ada = ($rumah_ada_jentik + $rumah_bebas_jentik) > 0 ? round(($rumah_ada_jentik / ($rumah_ada_jentik + $rumah_bebas_jentik)) * 100, 1) : 0; ?>
                                        <div style="color: #64748b; font-size: 0.9rem;"><?= $rumah_ada_jentik; ?> rumah (<?= $persen_ada; ?>%)</div>
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 30px; padding: 20px; background: #f1f5f9; border-radius: 10px;">
                                <div style="font-weight: 600; color: #1e293b; margin-bottom: 10px;">Total Data</div>
                                <div style="font-size: 2rem; font-weight: 700; color: #667eea;"><?= $total_laporan; ?></div>
                                <div style="color: #64748b; font-size: 0.9rem;">Total pemeriksaan yang dilakukan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Hamburger Menu Button -->
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        // Close sidebar when clicking overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    toggleSidebar();
                }
            });
        }

        // Close sidebar when clicking sidebar links on mobile
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Close sidebar when resizing to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.querySelector('.sidebar-overlay');
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Chart Script
        // Data untuk Pie Chart
        const data = {
            labels: ['Rumah Bebas Jentik', 'Rumah Ada Jentik'],
            datasets: [{
                data: [<?= $rumah_bebas_jentik; ?>, <?= $rumah_ada_jentik; ?>],
                backgroundColor: [
                    '#10b981', // Green untuk bebas jentik
                    '#ef4444'  // Red untuk ada jentik
                ],
                borderColor: '#ffffff',
                borderWidth: 3
            }]
        };

        // Konfigurasi Chart
        const config = {
            type: 'pie',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false // Legend sudah dibuat custom di HTML
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' rumah (' + percentage + '%)';
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'customDataLabels',
                afterDatasetsDraw: function(chart) {
                    const ctx = chart.ctx;
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach((element, index) => {
                            const data = dataset.data[index];
                            const total = dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((data / total) * 100).toFixed(1) : 0;
                            
                            const {x, y} = element.tooltipPosition();
                            
                            ctx.fillStyle = '#ffffff';
                            ctx.font = 'bold 16px Poppins, sans-serif';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
                            ctx.shadowBlur = 4;
                            
                            ctx.fillText(percentage + '%', x, y - 10);
                            
                            ctx.font = '14px Poppins, sans-serif';
                            ctx.fillText('(' + data + ')', x, y + 10);
                            
                            ctx.shadowColor = 'transparent';
                            ctx.shadowBlur = 0;
                        });
                    });
                }
            }]
        };

        // Render Chart
        const ctx = document.getElementById('pieChart').getContext('2d');
        new Chart(ctx, config);
    </script>
</body>
</html>