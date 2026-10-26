<?php
session_start();
require_once 'config.php';

// Pastikan hanya kader yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kader') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data kader
$stmt = mysqli_prepare($conn, "SELECT * FROM kader WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kader = mysqli_fetch_assoc($result);

if (!$kader) {
    header("Location: login.php");
    exit;
}

$kader_id = $kader['id'];
mysqli_stmt_close($stmt);

// Ambil filter dari GET
$filter_minggu = isset($_GET['minggu']) ? intval($_GET['minggu']) : 0;
$filter_bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : 0;
$filter_tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : 0; 

// Build query dinamis
$query = "SELECT * FROM data_jumantik WHERE kader_id = ? AND tatanan_diperiksa = 'Rekap'";
$params = [$kader_id];
$types = "i";

// Filter berdasarkan minggu, bulan, dan tahun
// Jika ada filter minggu dan bulan, terapkan keduanya
if ($filter_minggu > 0 && $filter_bulan > 0) {
    // Tentukan tahun yang digunakan (jika user memilih tahun gunakan itu, kalau tidak pakai tahun sekarang)
    $year_to_use = ($filter_tahun > 0) ? $filter_tahun : intval(date('Y'));

    // Gunakan rumus yang sama seperti di dashboard-admin: FLOOR((DAY-1)/7)+1
    $query .= " AND FLOOR((DAY(tanggal_pemeriksaan)-1)/7)+1 = ? AND MONTH(tanggal_pemeriksaan) = ? AND YEAR(tanggal_pemeriksaan) = ?";
    $params[] = $filter_minggu;
    $params[] = $filter_bulan;
    $params[] = $year_to_use;
    $types .= "iii";
} elseif ($filter_bulan > 0) {
    // Hanya filter bulan
    $year_to_use = ($filter_tahun > 0) ? $filter_tahun : intval(date('Y'));
    $query .= " AND MONTH(tanggal_pemeriksaan) = ? AND YEAR(tanggal_pemeriksaan) = ?";
    $params[] = $filter_bulan;
    $params[] = $year_to_use;
    $types .= "ii";
} 



$query .= " ORDER BY tanggal_pemeriksaan DESC, created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$jumantik_data = mysqli_stmt_get_result($stmt);

// Hitung statistik
$total_data = 0;
$jentik_positif = 0;

$data_array = [];
while($row = mysqli_fetch_assoc($jumantik_data)) {
    $data_array[] = $row;
    $total_data++;
    if (($row['jentik_ditemukan'] ?? 'Tidak') == 'Ya') $jentik_positif++;
}

mysqli_stmt_close($stmt);

// Hitung persentase bebas jentik
$jentik_negatif = $total_data - $jentik_positif;
$persentase_bebas = $total_data > 0 ? round(($jentik_negatif / $total_data) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pemeriksaan | Jumantik Pondok Kopi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .filter-section h3 {
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-filter {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-filter-apply {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btn-filter-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-filter-reset {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
        }

        .btn-filter-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-mini-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-mini-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        
        .stat-mini-card h4 {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .stat-mini-card p {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-mini-card.positive p {
            color: #dc2626;
        }

        .stat-mini-card.percentage p {
            color: #2563eb;
        }

        .success-alert, .error-alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.5s ease;
        }

        .success-alert {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .error-alert {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        thead {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
        }

        thead th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #475569;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        /* Catatan Cell Styling */
        .catatan-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #64748b;
            font-style: italic;
        }

        .catatan-cell.empty {
            color: #cbd5e1;
            text-align: center;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: 1px solid #888;
            width: 90%;
            max-width: 900px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 15px 25px;
            background-color: #f8f9fa;
            border-radius: 0 0 10px 10px;
            text-align: right;
            border-top: 1px solid #dee2e6;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            line-height: 1;
            background: rgba(255, 255, 255, 0.2);
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #475569;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* RESPONSIVE MOBILE */
        @media (max-width: 768px) {
            .filter-section {
                padding: 15px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .filter-actions {
                flex-direction: column;
            }

            .btn-filter {
                width: 100%;
                justify-content: center;
            }

            .stats-mini {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-mini-card {
                padding: 15px;
            }

            .stat-mini-card p {
                font-size: 24px;
            }

            .table-container {
                border-radius: 8px;
                margin: 0 -10px;
            }

            table {
                font-size: 12px;
            }

            thead th {
                padding: 12px 8px;
                font-size: 11px;
            }

            tbody td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .badge {
                padding: 4px 8px;
                font-size: 10px;
            }

            .btn {
                padding: 6px 10px;
                font-size: 11px;
                margin: 2px 0;
            }

            .modal-content {
                width: 95%;
                margin: 5% auto;
                max-height: 85vh;
            }

            .catatan-cell {
                max-width: 100px;
            }
        }
         /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
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

        /* Desktop Menu */
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

        /* Mobile Menu Toggle */
        .navbar-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 5px;
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

        /* Mobile Menu */
        .navbar-mobile {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .navbar-mobile.active {
            max-height: 400px;
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
            gap: 12px;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 16px;
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

        /* Content with navbar spacing */
        .main-content {
            margin-top: 80px;
            padding: 20px;
        }

        /* Responsive Design */
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
                margin-top: 70px;
            }
        }

        @media (max-width: 480px) {
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

    </style>
</head>
<body>
<div class="dashboard">

        <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="navbar-brand">
                <span>🦟</span> Jumantik
            </a>

            <!-- Desktop Menu -->
            <ul class="navbar-menu">
                <li><a href="dashboard-kader.php">🏠 Beranda</a></li>
                <li><a href="data-jumantik.php">🔍 Data Jumantik</a></li>
                <li><a href="laporan-kader.php" class="active">📋 Laporan</a></li>
                <li><a href="logout.php">🚪 Keluar</a></li>
            </ul>

            <!-- Mobile Toggle -->
            <div class="navbar-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="navbar-mobile" id="mobileMenu">
        <ul>
            <li><a href="dashboard-kader.php">🏠 Beranda</a></li>
            <li><a href="data-jumantik.php">🔍 Data Jumantik</a></li>
            <li><a href="laporan-kader.php" class="active">📋 Laporan</a></li>
            <li><a href="logout.php">🚪 Keluar</a></li>
        </ul>
    </div>

    <main class="main-content">
        <div class="header">
            <h1>📋 Laporan Pemeriksaan Jentik</h1>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="success-alert">
            <span style="font-size: 20px;">✅</span>
            <span>Data berhasil diperbarui!</span>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3>🔍 Filter Laporan</h3>
            <form method="GET" action="laporan-kader.php" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Bulan</label>
                        <select name="bulan">
                            <option value="">-- Semua Bulan --</option>
                            <?php
                            $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                            for($m=1;$m<=12;$m++):
                            ?>
                                <option value="<?= $m; ?>" <?= $filter_bulan == $m ? 'selected' : ''; ?>><?= $months[$m-1]; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Tahun</label>
                        <select name="tahun">
                            <option value="">-- Semua Tahun --</option>
                            <?php
                            $now_y = intval(date('Y'));
                            for ($yy = $now_y - 2; $yy <= $now_y + 1; $yy++):
                            ?>
                                <option value="<?= $yy; ?>" <?= $filter_tahun == $yy ? 'selected' : ''; ?>><?= $yy; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Minggu</label>
                        <select name="minggu">
                            <option value="">-- Semua Minggu --</option>
                            <option value="1" <?= $filter_minggu == 1 ? 'selected' : ''; ?>>Minggu 1</option>
                            <option value="2" <?= $filter_minggu == 2 ? 'selected' : ''; ?>>Minggu 2</option>
                            <option value="3" <?= $filter_minggu == 3 ? 'selected' : ''; ?>>Minggu 3</option>
                            <option value="4" <?= $filter_minggu == 4 ? 'selected' : ''; ?>>Minggu 4</option>
                            <option value="5" <?= $filter_minggu == 5 ? 'selected' : ''; ?>>Minggu 5</option>
                            <option value="6" <?= $filter_minggu == 6 ? 'selected' : ''; ?>>Minggu 6</option>
                        </select>
                    </div>


                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter btn-filter-apply">
                        🔍 Terapkan Filter
                    </button>
                    <button type="button" class="btn-filter btn-filter-reset" onclick="resetFilter()">
                        🔄 Reset Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>RT/RW</th>
                        <th>Nama Kader</th>
                        <th>Rekap Tatanan (Diperiksa / Jentik+)</th>
                        <th>Minggu Ke-</th>
                        <th>Bulan</th>
                        <th>Catatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($data_array)):
                    $no = 1;
                    foreach($data_array as $row):
                        // Normalize RT/RW to 2-digit numeric display (e.g. 01/09)
                        $rt_raw = $row['rt'] ?? '';
                        $rw_raw = $row['rw'] ?? '';
                        $rt_num = strlen(trim($rt_raw)) ? intval(preg_replace('/[^0-9]/', '', $rt_raw)) : null;
                        $rw_num = strlen(trim($rw_raw)) ? intval(preg_replace('/[^0-9]/', '', $rw_raw)) : null;
                        $rt_disp = $rt_num !== null ? str_pad($rt_num, 2, '0', STR_PAD_LEFT) : '-';
                        $rw_disp = $rw_num !== null ? str_pad($rw_num, 2, '0', STR_PAD_LEFT) : '-';
                        $rt_rw_display = ($rt_disp !== '-' && $rw_disp !== '-') ? ('RT ' . $rt_disp . '/RW ' . $rw_disp) : (($rt_disp !== '-') ? ('RT ' . $rt_disp) : (($rw_disp !== '-') ? ('RW ' . $rw_disp) : '-'));
                        $catatan = $row['catatan'] ?? '';
                        // Extract minggu dan bulan dari tanggal_pemeriksaan
                        $minggu_ke = '-';
                        $bulan_name = '-';
                        if (!empty($row['tanggal_pemeriksaan'])) {
                            $tgl = strtotime($row['tanggal_pemeriksaan']);
                            $day = intval(date('d', $tgl));
                            $month = intval(date('m', $tgl));
                            $minggu_ke = max(1, min(6, intval(ceil($day / 7))));
                            $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                            $bulan_name = $months[$month - 1] ?? '-';
                        }
                        // parse catatan JSON if present
                        $parsed = null;
                        $catatan_text = '';
                        if (!empty($catatan)) {
                            $t = json_decode($catatan, true);
                            if (is_array($t)) {
                                $parsed = $t;
                                // extract catatan text if present
                                if (isset($parsed['_catatan'])) {
                                    $catatan_text = $parsed['_catatan'];
                                    unset($parsed['_catatan']);
                                }
                            }
                        }
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($rt_rw_display); ?></td>
                        <td><?= htmlspecialchars($kader['nama_kader'] ?? ($row['nama_kepala_keluarga'] ?? '-')); ?></td>
                        <td style="text-align:left;">
                            <?php if ($parsed): ?>
                                <?php foreach ($parsed as $tn => $vals): ?>
                                    <div style="margin-bottom:6px;"><strong><?= htmlspecialchars($tn); ?>:</strong>
                                        Diperiksa <?= intval($vals['diperiksa'] ?? 0); ?> / Jentik(+) <?= intval($vals['jentik'] ?? 0); ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="color:#94a3b8;">-</div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($minggu_ke); ?></td>
                        <td><?= htmlspecialchars($bulan_name); ?></td>
                        <td>
                            <?php if (!empty($catatan_text)): ?>
                                <div class="catatan-cell" title="<?= htmlspecialchars($catatan_text); ?>"><?= htmlspecialchars($catatan_text); ?></div>
                            <?php else: ?>
                                <div class="catatan-cell empty">-</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($row['status_konfirmasi'] ?? 'Menunggu') == 'Menunggu'): ?>
                                <button class="btn btn-primary" onclick="editData(<?= $row['id']; ?>)">✏️ Edit</button>
                                <button class="btn" onclick="deleteData(<?= $row['id']; ?>)" style="background: #dc2626; color: white;">🗑️ Hapus</button>
                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div style="color: #94a3b8; font-size: 16px;">
                                📊 Tidak ada data laporan untuk filter yang dipilih
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal Edit (tetap di halaman laporan) -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✏️ Edit Data Pemeriksaan</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form id="editForm" method="POST" action="ajax/update-jumantik.php">
            <div class="modal-body" id="editFormContent">
                <p style="text-align: center; color: #64748b;">Memuat data...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeEditModal()" style="background: #64748b; color: white;">Batal</button>
                <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

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

        // Close mobile menu on window resize to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.navbar-toggle').classList.remove('active');
                document.getElementById('mobileMenu').classList.remove('active');
            }
        });

    // Reset filter
    function resetFilter() {
        window.location.href = 'laporan-kader.php';
    }

    // Buka modal edit (edit tetap di halaman laporan)
    function editData(id) {
        const modal = document.getElementById('editModal');
        const content = document.getElementById('editFormContent');
        
        modal.style.display = 'block';
        content.innerHTML = '<p style="text-align: center; color: #64748b;">Memuat data...</p>';
        
        // Ambil data yang akan diedit
        fetch('ajax/get-jumantik.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const d = data.data;

                    // Parse catatan JSON (rekap per tatanan) + catatan teks
                    let parsed = {};
                    let catatanText = '';
                    try {
                        const obj = JSON.parse(d.catatan || '{}');
                        if (obj && typeof obj === 'object') {
                            parsed = obj;
                            if (obj._catatan) {
                                catatanText = obj._catatan;
                                delete parsed._catatan;
                            }
                        }
                    } catch (e) {
                        parsed = {};
                        catatanText = d.catatan || '';
                    }

                    // Tentukan minggu & bulan dari tanggal_pemeriksaan
                    let mingguKe = '';
                    let bulanVal = '';
                    if (d.tanggal_pemeriksaan) {
                        const tgl = new Date(d.tanggal_pemeriksaan);
                        const day = tgl.getDate();
                        const month = tgl.getMonth() + 1;
                        mingguKe = Math.max(1, Math.min(6, Math.ceil(day / 7)));
                        bulanVal = String(month).padStart(2, '0');
                    }

                    const tatananList = [
                        'Pemukiman',
                        'Institusi Pendidikan',
                        'Tempat Kerja',
                        'TTU',
                        'TPM',
                        'Sarana Olahraga',
                        'Sarana Kesehatan'
                    ];

                    // Bangun baris input angka per tatanan
                    let tatananRows = '';
                    tatananList.forEach(name => {
                        const rec = parsed[name] || {};
                        const dVal = rec.diperiksa || 0;
                        const jVal = rec.jentik || 0;
                        const key = name.toLowerCase().replace(/\s+/g, '_');
                        tatananRows += `
                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; align-items:center;">
                                <div style="padding:8px 10px; background:#f8fafc; border:1px solid #e2e8f0;">${name}</div>
                                <div><input type="number" min="0" name="${key}_diperiksa" class="form-control-table" value="${dVal}" required></div>
                                <div><input type="number" min="0" name="${key}_jentik" class="form-control-table" value="${jVal}" required></div>
                            </div>
                        `;
                    });

                    content.innerHTML = `
                        <input type="hidden" name="id" value="${d.id}">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>🏠 RT <span style="color: #dc2626;">*</span></label>
                                <select name="rt" class="form-control" required>
                                    <option value="">-- Pilih RT --</option>
                                    ${generateRTOptions(d.rt)}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>🏘️ RW <span style="color: #dc2626;">*</span></label>
                                <select name="rw" class="form-control" required>
                                    <option value="">-- Pilih RW --</option>
                                    ${generateRWOptions(d.rw)}
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:10px;">
                            <label>📊 Input Angka per Tatanan (isi 0 jika tidak ada)</label>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-top:6px;">
                                <div style="font-weight:600">Tatanan</div>
                                <div style="font-weight:600">Diperiksa (jumlah)</div>
                                <div style="font-weight:600">Jentik (+)</div>
                            </div>
                            <div id="edit_tatanan_inputs" style="margin-top:8px; display:grid; gap:8px;">
                                ${tatananRows}
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top:10px;">
                            <div class="form-group">
                                <label>Minggu Ke- <span style="color:#dc2626;">*</span></label>
                                <select name="minggu" class="form-control" required>
                                    <option value="">-- Pilih Minggu --</option>
                                    ${[1,2,3,4,5,6].map(i => `<option value="${i}" ${i === mingguKe ? 'selected' : ''}>Minggu ${i}</option>`).join('')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Bulan <span style="color:#dc2626;">*</span></label>
                                <select name="bulan" class="form-control" required>
                                    <option value="">-- Pilih Bulan --</option>
                                    ${['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
                                        .map((nm,idx) => {
                                            const val = String(idx+1).padStart(2,'0');
                                            const sel = val === bulanVal ? 'selected' : '';
                                            return `<option value="${val}" ${sel}>${nm}</option>`;
                                        }).join('')}
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:10px;">
                            <label>📝 Catatan</label>
                            <textarea name="catatan" class="form-control" rows="3" placeholder="Catatan tambahan (opsional)">${catatanText}</textarea>
                        </div>
                    `;
                } else {
                    content.innerHTML = '<p style="text-align: center; color: #dc2626;">❌ Gagal memuat data</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<p style="text-align: center; color: #dc2626;">❌ Terjadi kesalahan</p>';
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Normalisasi kode RT/RW ke dua digit (ambil hanya angka)
    function normalizeCode(value) {
        if (!value) return '';
        const digits = String(value).match(/\d+/g);
        if (!digits) return '';
        const num = parseInt(digits.join(''), 10);
        if (isNaN(num)) return '';
        return String(num).padStart(2, '0');
    }

    // Generate RT Options
    function generateRTOptions(selectedRT) {
        const normalized = normalizeCode(selectedRT);
        let options = '';
        for (let i = 1; i <= 13; i++) {
            const rtVal = String(i).padStart(2, '0');
            const rtDisplay = String(i).padStart(2, '0');
            const selected = rtVal === normalized ? 'selected' : '';
            options += `<option value="${rtVal}" ${selected}>RT ${rtDisplay}</option>`;
        }
        return options;
    }

    // Generate RW Options
    function generateRWOptions(selectedRW) {
        const normalized = normalizeCode(selectedRW);
        let options = '';
        for (let i = 1; i <= 11; i++) {
            const rwVal = String(i).padStart(2, '0');
            const rwDisplay = String(i).padStart(2, '0');
            const selected = rwVal === normalized ? 'selected' : '';
            options += `<option value="${rwVal}" ${selected}>RW ${rwDisplay}</option>`;
        }
        return options;
    }

    // Handle Edit Form Submit (kirim ke ajax/update-jumantik.php)
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('ajax/update-jumantik.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'laporan-kader.php?success=1';
            } else {
                alert('❌ Gagal memperbarui data: ' + (data.message || 'Terjadi kesalahan'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Terjadi kesalahan saat memperbarui data');
        });
    });

    // Delete Data
    function deleteData(id) {
        if (confirm('⚠️ Apakah Anda yakin ingin menghapus data ini?')) {
            const formData = new FormData();
            formData.append('id', id);
            
            fetch('ajax/delete-jumantik.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'laporan-kader.php?success=1';
                } else {
                    alert('❌ Gagal menghapus data: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Terjadi kesalahan saat menghapus data');
            });
        }
    }

    // (Tidak ada modal detail/edit lagi)
</script>

</body>
</html>