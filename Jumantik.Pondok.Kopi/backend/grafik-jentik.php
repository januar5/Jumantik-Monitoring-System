<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Fungsi normalisasi RW (sama seperti di dashboard)
if (!function_exists('normalizeRwSql')) {
    function normalizeRwSql($column) {
        return "CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST($column AS CHAR) COLLATE utf8mb4_general_ci), 'rw', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED)";
    }
}

// Ambil data statistik per RW menggunakan agregasi yang sama dengan dashboard
$laporan_by_rw = "
    SELECT
        " . normalizeRwSql("COALESCE(k.rw, dj.rw)") . " AS rw_norm,
        COUNT(*) AS total_rumah,
        SUM(CASE WHEN dj.jentik_ditemukan = 'Ya' THEN 1 ELSE 0 END) AS total_jentik,
        SUM(CASE WHEN dj.jentik_ditemukan = 'Tidak' THEN 1 ELSE 0 END) AS total_bebas_jentik
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE COALESCE(k.rw, dj.rw) IS NOT NULL
    GROUP BY rw_norm
";

$query = "
    SELECT
        r.username AS rw_original,
        COALESCE(l.total_rumah, 0) AS total_rumah,
        COALESCE(l.total_jentik, 0) AS total_jentik,
        COALESCE(l.total_bebas_jentik, 0) AS total_bebas_jentik
    FROM rw r
    LEFT JOIN (" . $laporan_by_rw . ") l ON l.rw_norm = " . normalizeRwSql('r.username') . "
    ORDER BY " . normalizeRwSql('r.username') . " ASC
";

$result = mysqli_query($conn, $query);

// Cek error query
if (!$result) {
    die("Error query: " . mysqli_error($conn) . "<br><br>Query: " . htmlspecialchars($query));
}

$rw_labels = [];
$total_rumah = [];
$total_jentik = [];
$total_bebas = [];

while ($row = mysqli_fetch_assoc($result)) {
    $rw_labels[] = $row['rw_original'];
    $total_rumah[] = (int)$row['total_rumah'];
    $total_jentik[] = (int)$row['total_jentik'];
    $total_bebas[] = (int)$row['total_bebas_jentik'];
}

// Jika tidak ada data
if (empty($rw_labels)) {
    $rw_labels = ['Belum Ada Data'];
    $total_rumah = [0];
    $total_jentik = [0];
    $total_bebas = [0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Grafik Statistik Jentik | Jumantik Pondok Kopi</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #2d3748;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        /* === SIDEBAR === */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 25px 15px;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar h2 {
            font-size: 1.8rem;
            margin-bottom: 40px;
            text-align: center;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar ul li {
            margin-bottom: 12px;
        }
        
        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 15px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar ul li a::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #fbbf24;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar ul li a:hover::before,
        .sidebar ul li a.active::before {
            transform: scaleY(1);
        }
        
        /* === CONTENT === */
        .content {
            flex: 1;
            margin-left: 260px;
            padding: 35px;
            width: calc(100% - 260px);
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 25px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 1.9rem;
            color: #1e293b;
            font-weight: 700;
            margin: 0;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }
        
        .welcome {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .welcome h2 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 1.5rem;
        }
        
        .welcome p {
            color: rgba(255,255,255,0.9);
            margin: 0;
        }
        
        /* === STATS SUMMARY === */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 15px;
            color: white;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
        }
        
        .stat-box.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-box.red {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        
        .stat-box h3 {
            font-size: 2.5rem;
            margin: 10px 0;
            font-weight: 700;
        }
        
        .stat-box p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin: 0;
        }
        
        /* === CHART CONTAINER === */
        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }
        
        .chart-container h2 {
            color: #1e293b;
            margin: 0 0 25px 0;
            font-size: 1.5rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 400px;
            margin-top: 20px;
        }
        
        /* === TABLE DESKTOP === */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .desktop-table {
            width: 100%;
            border-collapse: collapse;
            display: table;
        }
        
        .desktop-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .desktop-table th {
            padding: 15px;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .desktop-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .desktop-table tbody tr:hover {
            background: #f8fafc;
        }
        
        /* === MOBILE CARD LAYOUT === */
        .mobile-cards {
            display: none;
        }
        
        .data-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            position: relative;
            z-index: 2;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .card-rw {
            font-size: 1.3rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .card-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .card-stats {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .stat-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-item.full-width {
            grid-column: 1 / -1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-item.jentik {
            background: #fee2e2;
        }
        
        .stat-item.jentik .stat-value {
            color: #dc2626;
        }
        
        .stat-item.bebas {
            background: #dcfce7;
        }
        
        .stat-item.bebas .stat-value {
            color: #16a34a;
        }
        
        .stat-item.persentase {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        
        .stat-item.persentase.high {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(239, 68, 68, 0.1) 100%);
        }
        
        .stat-item.persentase .stat-value {
            color: #667eea;
        }
        
        .stat-item.persentase.high .stat-value {
            color: #dc2626;
        }
        
        /* === HAMBURGER MENU === */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            pointer-events: auto;
        }
        
        .menu-toggle:active {
            transform: scale(0.95);
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        /* === RESPONSIVE DESIGN === */
        @media (max-width: 1024px) {
            .header h1 {
                font-size: 1.5rem;
            }
            
            .stats-summary {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            /* Show hamburger menu */
            .menu-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: block;
            }
            
            /* Hide sidebar by default */
            .sidebar {
                left: -280px;
                width: 280px;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            /* Full width content */
            .content {
                margin-left: 0;
                width: 100%;
                padding: 70px 15px 15px 15px;
                position: relative;
                z-index: 1;
            }
            
            /* Header adjustments */
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
            
            .logout-btn {
                width: 100%;
                text-align: center;
            }
            
            /* Welcome section */
            .welcome {
                padding: 20px;
            }
            
            .welcome h2 {
                font-size: 1.3rem;
            }
            
            .welcome p {
                font-size: 0.9rem;
            }
            
            /* Stats grid */
            .stats-summary {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-box {
                padding: 20px;
            }
            
            .stat-box h3 {
                font-size: 2rem;
            }
            
            /* Chart adjustments */
            .chart-container {
                padding: 20px;
            }
            
            .chart-container h2 {
                font-size: 1.2rem;
            }
            
            .chart-wrapper {
                height: 300px;
            }
            
            /* Hide desktop table, show mobile cards */
            .desktop-table {
                display: none;
            }
            
            .mobile-cards {
                display: block;
            }
        }
        
        @media (max-width: 480px) {
            .content {
                padding: 65px 10px 10px 10px;
            }
            
            .header h1 {
                font-size: 1.1rem;
            }
            
            .welcome {
                padding: 15px;
            }
            
            .welcome h2 {
                font-size: 1.1rem;
            }
            
            .welcome p {
                font-size: 0.85rem;
            }
            
            .stat-box {
                padding: 15px;
            }
            
            .stat-box h3 {
                font-size: 1.8rem;
            }
            
            .stat-box p {
                font-size: 0.85rem;
            }
            
            .chart-container {
                padding: 15px;
            }
            
            .chart-container h2 {
                font-size: 1.1rem;
            }
            
            .chart-wrapper {
                height: 250px;
            }
            
            .data-card {
                padding: 15px;
            }
            
            .card-rw {
                font-size: 1.1rem;
            }
            
            .stat-value {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
<!-- Hamburger Menu Button -->
<button class="menu-toggle" onclick="toggleSidebar()">☰</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <h2>🦟 Jumantik</h2>
        <ul>
            <li><a href="dashboard-admin.php">📊 Dashboard</a></li>
            <li><a href="data-kader.php">👩‍💼 Data Kader</a></li>
            <li><a href="data-rw.php">🏘️ Data RW</a></li>
            <li><a href="grafik-jentik.php" class="active">📈 Statistik</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <!-- Main content -->
    <main class="content">
        <div class="header">
            <h1>📈 Statistik Pemantauan Jentik</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <div class="welcome" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; margin-bottom: 30px;">
            <h2 style="color: white; margin: 0 0 10px 0;">Grafik & Statistik</h2>
            <p style="color: rgba(255,255,255,0.9); margin: 0;">Monitoring Jentik Nyamuk per RW - Kelurahan Pondok Kopi</p>
        </div>

        <!-- Summary Stats -->
        <div class="stats-summary">
            <div class="stat-box">
                <p>Total Rumah Dipantau</p>
                <h3><?= array_sum($total_rumah); ?></h3>
            </div>
            <div class="stat-box red">
                <p>Rumah Ada Jentik</p>
                <h3><?= array_sum($total_jentik); ?></h3>
            </div>
            <div class="stat-box green">
                <p>Rumah Bebas Jentik</p>
                <h3><?= array_sum($total_bebas); ?></h3>
            </div>
        </div>

        <!-- Chart -->
        <div class="chart-container">
            <h2>📊 Grafik Pemantauan Jentik per RW</h2>
            <div class="chart-wrapper">
                <canvas id="chartJentik"></canvas>
            </div>
        </div>

        <!-- Detail Table -->
            
            <!-- Mobile Cards -->
            <div class="mobile-cards">
                <?php 
                mysqli_data_seek($result, 0);
                $no = 1;
                while ($row = mysqli_fetch_assoc($result)):
                    $persentase = $row['total_rumah'] > 0 ? round(($row['total_jentik'] / $row['total_rumah']) * 100, 1) : 0;
                ?>
                <div class="data-card">
                    <div class="card-header">
                        <div class="card-rw"><?= htmlspecialchars($row['rw_original']); ?></div>
                        <div class="card-number">#<?= $no++; ?></div>
                    </div>
                    <div class="card-stats">
                        <div class="stat-item full-width">
                            <div class="stat-label">Total Rumah</div>
                            <div class="stat-value"><?= $row['total_rumah']; ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-item jentik">
                                <div class="stat-label">Ada Jentik</div>
                                <div class="stat-value"><?= $row['total_jentik']; ?></div>
                            </div>
                            <div class="stat-item bebas">
                                <div class="stat-label">Bebas Jentik</div>
                                <div class="stat-value"><?= $row['total_bebas_jentik']; ?></div>
                            </div>
                        </div>
                        <div class="stat-item persentase full-width <?= $persentase > 10 ? 'high' : ''; ?>">
                            <div class="stat-label">Persentase Jentik</div>
                            <div class="stat-value"><?= $persentase; ?>%</div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>
</div>

<script>
// === TOGGLE SIDEBAR ===
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    
    // Prevent body scroll when sidebar is open on mobile
    if (sidebar.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'auto';
    }
}

// Auto close sidebar when clicking links on mobile
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
        document.querySelector('.sidebar').classList.remove('active');
        document.querySelector('.sidebar-overlay').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
});

// === CHART.JS ===
const ctx = document.getElementById('chartJentik').getContext('2d');
const isMobile = window.innerWidth <= 768;

const chartJentik = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($rw_labels); ?>,
        datasets: [
            {
                label: 'Total Rumah',
                data: <?= json_encode($total_rumah); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                borderRadius: 8
            },
            {
                label: 'Ada Jentik',
                data: <?= json_encode($total_jentik); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 2,
                borderRadius: 8
            },
            {
                label: 'Bebas Jentik',
                data: <?= json_encode($total_bebas); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                borderRadius: 8
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { 
                beginAtZero: true,
                ticks: { 
                    stepSize: 1,
                    font: { size: isMobile ? 10 : 12 }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                ticks: {
                    font: { size: isMobile ? 9 : 12 },
                    maxRotation: isMobile ? 45 : 0,
                    minRotation: isMobile ? 45 : 0
                },
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: { 
                position: 'top',
                labels: { 
                    font: { size: isMobile ? 11 : 14, weight: '600' },
                    padding: isMobile ? 10 : 15,
                    usePointStyle: true,
                    boxWidth: isMobile ? 8 : 12
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: isMobile ? 8 : 12,
                titleFont: { size: isMobile ? 12 : 14, weight: 'bold' },
                bodyFont: { size: isMobile ? 11 : 13 },
                cornerRadius: 8
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Update chart on window resize
window.addEventListener('resize', () => {
    const newIsMobile = window.innerWidth <= 768;
    chartJentik.options.scales.x.ticks.font.size = newIsMobile ? 9 : 12;
    chartJentik.options.scales.y.ticks.font.size = newIsMobile ? 10 : 12;
    chartJentik.options.scales.x.ticks.maxRotation = newIsMobile ? 45 : 0;
    chartJentik.options.scales.x.ticks.minRotation = newIsMobile ? 45 : 0;
    chartJentik.options.plugins.legend.labels.font.size = newIsMobile ? 11 : 14;
    chartJentik.options.plugins.legend.labels.padding = newIsMobile ? 10 : 15;
    chartJentik.options.plugins.legend.labels.boxWidth = newIsMobile ? 8 : 12;
    chartJentik.update();
});
</script>

</body>
</html>