<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'rw') {
    header("Location: login.php");
    exit;
}

// === FUNGSI NORMALISASI ===
if (!function_exists('normalizeNumber')) {
    function normalizeNumber($value) {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^0-9]/', '', $value);
        return ltrim($value, '0');
    }
}

if (!function_exists('normalizeRwSql')) {
    function normalizeRwSql($column) {
        return "CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST($column AS CHAR) COLLATE utf8mb4_general_ci), 'rw', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED)";
    }
}

$username = $_SESSION['username'];

// Ambil data RW
$query = "SELECT r.*, u.username FROM rw r 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE u.username = ?";
$stmt = mysqli_prepare($conn, $query);

if ($stmt === false) {
    die("Error prepare: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data_rw = mysqli_fetch_assoc($result);

// Jika data RW tidak ditemukan, redirect ke login
if (!$data_rw) {
    header("Location: login.php");
    exit;
}

$nama_rw = $data_rw['nama_rw'] ?? 'RW';
$nomor_rw = $data_rw['nomor_rw'] ?? $data_rw['username'] ?? '-';
$rw_id = $data_rw['id'] ?? 0;

// Extract nomor RW sebagai angka - REQUIRED untuk filter
$nomor_rw_filter = preg_replace('/[^0-9]/', '', (string)$nomor_rw);
$nomor_rw_filter_int = !empty($nomor_rw_filter) ? intval($nomor_rw_filter) : 0;

mysqli_stmt_close($stmt);

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan RW - Jumantik</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    /* ===== LAYOUT & CONTENT ===== */
    .content { padding: 20px; }
    .dashboard { background: #f8fafc !important; }
    .sidebar { box-shadow: none !important; border-bottom: none !important; }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
                    <?php
                    // compute selected month/year from $current_month (format YYYY-MM)
                    $selected_year = isset($current_month) ? intval(substr($current_month,0,4)) : intval(date('Y'));
                    $selected_month = isset($current_month) ? intval(substr($current_month,5,2)) : intval(date('m'));
                    // prepare year range (current year -2 .. current year +1)
                    $yr_now = intval(date('Y'));
                    $years = [];
                    for ($yy = $yr_now - 2; $yy <= $yr_now + 1; $yy++) $years[] = $yy;
                    ?>
                    <label style="margin:0 4px 0 0; font-weight:600; color:#1e293b;">Bulan</label>
                    <select name="month" onchange="this.form.submit()" style="padding:8px;border-radius:8px;border:1px solid #cbd5e1;min-width:120px;">
                        <?php for ($mo = 1; $mo <= 12; $mo++):
                            $m_label = date('F', mktime(0,0,0,$mo,1));
                            $sel_mo = ($selected_month == $mo) ? 'selected' : '';
                        ?>
                            <option value="<?= $mo; ?>" <?= $sel_mo; ?>><?= $m_label; ?></option>
                        <?php endfor; ?>
                    </select>

                    <label style="margin:0 4px 0 6px; font-weight:600; color:#1e293b;">Tahun</label>
                    <select name="year" onchange="this.form.submit()" style="padding:8px;border-radius:8px;border:1px solid #cbd5e1;min-width:120px;">
                        <?php foreach ($years as $yy): $sel_yy = ($selected_year == $yy) ? 'selected' : ''; ?>
                            <option value="<?= $yy; ?>" <?= $sel_yy; ?>><?= $yy; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label style="margin:0 4px 0 6px; font-weight:600; color:#1e293b;">Minggu</label>
                    <select name="minggu" onchange="this.form.submit()" style="padding:8px;border-radius:8px;border:1px solid #cbd5e1;min-width:120px;">
                        <?php for ($wk_nav = 1; $wk_nav <= 6; $wk_nav++):
                            $sel_w = (isset($current_minggu) && $current_minggu == $wk_nav) ? 'selected' : '';
                        ?>
                            <option value="<?= $wk_nav; ?>" <?= $sel_w; ?>>Minggu <?= $wk_nav; ?></option>
                        <?php endfor; ?>
                    </select>
        border: 1px solid #cbd5e1; 
        border-radius: 8px; 
        flex: 1; 
        min-width: 180px;
    }
    .filter-box button {
        background: #2563eb; 
        color: white; 
        border: none; 
        padding: 10px 20px;
        border-radius: 8px; 
        cursor: pointer; 
        font-weight: 500;
    }
    .filter-box button:hover { background: #1e40af; }
    
    /* ===== BUTTONS ===== */
    .reset-btn {
        padding: 10px 20px; 
        background: #64748b; 
        color: white; 
        text-decoration: none;
        border-radius: 8px; 
        display: inline-block;
    }
    .reset-btn:hover { background: #475569; }
    
    .btn-upload {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .btn-upload:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }
    
    /* ===== PERIODE NAVIGATION ===== */
    .periode-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        padding: 12px;
        background: #fff;
        border-radius: 12px;
        box-shadow: none;
        border-bottom: none;
    }
    .periode-nav .nav-left { 
        display: flex; 
        gap: 8px; 
        align-items: center; 
    }
    .periode-nav .nav-center { 
        display: flex; 
        gap: 10px; 
        align-items: center; 
        justify-content: center; 
        flex: 1; 
    }
    .periode-nav .nav-btn {
        background: #64748b;
        color: #fff;
        padding: 8px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .periode-nav .date-label {
        font-weight: 700;
        color: #1e293b;
        white-space: nowrap;
        text-align: center;
    }
    
    .tab-btn {
        padding: 8px 14px;
        border: 2px solid #cbd5e1;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
        text-decoration: none;
        color: #1e293b;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .tab-btn.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }
    .tab-btn:hover { border-color: #2563eb; }
    
    /* ===== TABLE STYLES ===== */
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 10px; 
    }
    table th, 
    table td { 
        vertical-align: middle; 
        text-align: center; 
        padding: 12px; 
        border-bottom: 1px solid #e2e8f0; 
    }
    table th { 
        background: #3b82f6; 
        color: white; 
    }
    table th:nth-child(3), 
    table th:nth-child(4),
    table td:nth-child(3), 
    table td:nth-child(4) {
        text-align: left;
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 10px 0;
    }
    
    .table-container table {
        min-width: 900px;
    }
    
    /* ===== BADGES ===== */
    .badge-success, 
    .badge-warning, 
    .badge-danger {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
        line-height: 1;
    }
    .badge-success { background: #22c55e; color: white; }
    .badge-warning { background: #facc15; color: #1e293b; }
    .badge-danger { background: #ef4444; color: white; }
    
    /* ===== JENTIK STATUS ===== */
    .jentik-full { display: inline; }
    .jentik-short { display: none; }
    
    /* ===== MODAL STYLES ===== */
    .modal { 
        display: none; 
        position: fixed; 
        z-index: 9999; 
        inset: 0; 
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(3px);
    }
    .modal-content { 
        max-width: 900px; 
        margin: 2% auto; 
        background: #fff; 
        border-radius: 10px; 
        overflow: hidden;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-header { 
        padding: 16px 20px; 
        background: #1e293b; 
        color: #fff; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
    }
    .modal-header h3 { margin: 0; font-size: 16px; }
    .modal-body { 
        padding: 20px; 
        background: #f8fafc; 
    }
    .modal-footer {
        padding: 15px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        text-align: right;
    }
    .close { 
        cursor: pointer; 
        font-size: 22px;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        transition: all 0.3s;
    }
    .close:hover { 
        transform: rotate(90deg);
        background: rgba(255, 255, 255, 0.3);
    }
    
    /* ===== UPLOAD FORM ===== */
    .upload-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .form-group label {
        font-weight: 600;
        color: #475569;
        font-size: 14px;
    }
    .form-group input[type="file"] {
        padding: 10px;
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        background: white;
    }
    .preview-image {
        max-width: 100%;
        max-height: 200px;
        border-radius: 8px;
        margin-top: 10px;
        display: none;
    }
    
    /* ===== MOBILE NAVIGATION ===== */
    .mobile-nav {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        box-shadow: none;
        z-index: 1000;
        padding: 8px 0 max(8px, env(safe-area-inset-bottom));
    }
    .mobile-nav-content {
        display: flex;
        justify-content: space-around;
        align-items: center;
        max-width: 600px;
        margin: 0 auto;
    }
    .mobile-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 8px 16px;
        text-decoration: none;
        color: #64748b;
        transition: all 0.3s;
        border-radius: 12px;
        flex: 1;
        max-width: 120px;
    }
    .mobile-nav-item:hover,
    .mobile-nav-item:active { background: #f1f5f9; }
    .mobile-nav-item.active {
        color: #3b82f6;
        font-weight: 600;
    }
    .mobile-nav-item.active .nav-icon { transform: scale(1.1); }
    .nav-icon {
        font-size: 24px;
        transition: transform 0.3s;
    }
    .nav-label {
        font-size: 11px;
        font-weight: 500;
        text-align: center;
        line-height: 1.2;
    }
    
    /* ===== HAMBURGER MENU ===== */
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1001;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 12px;
        width: 48px;
        height: 48px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        transition: all 0.3s;
    }
    .mobile-menu-btn:active { transform: scale(0.95); }
    
    .hamburger {
        display: flex;
        flex-direction: column;
        gap: 4px;
        align-items: center;
        justify-content: center;
    }
    .hamburger span {
        display: block;
        width: 20px;
        height: 2px;
        background: white;
        border-radius: 2px;
        transition: all 0.3s;
    }
    .hamburger.active span:nth-child(1) { transform: translateY(6px) rotate(45deg); }
    .hamburger.active span:nth-child(2) { opacity: 0; }
    .hamburger.active span:nth-child(3) { transform: translateY(-6px) rotate(-45deg); }
    
    /* ===== SIDEBAR OVERLAY ===== */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .sidebar-overlay.active { opacity: 1; }
    
    /* ===== MEDIA QUERIES ===== */
    @media(max-width: 768px){
        .filter-box { flex-direction: column; }
        .filter-box input, 
        .filter-box select { width: 100%; min-width: auto; }
        
        table { font-size: 0.8rem; }
        th, td { padding: 6px; }
        .table-container { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
            margin: 5px 0;
        }
        .table-container table {
            min-width: 1000px;
        }
        
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            bottom: 0;
            width: 280px;
            z-index: 1000;
            transition: left 0.3s;
        }
        .sidebar.active { left: 0; }
        
        .mobile-menu-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .mobile-nav { display: block; }
        
        .content {
            margin-left: 0;
            padding-bottom: 80px;
            padding-top: 80px;
        }
        .header { position: relative; padding-right: 60px; flex-wrap: wrap; }
        .header h1 { flex-basis: 100%; margin-bottom: 10px; }
        .header > div { width: 100%; }
    }
    
    @media(max-width: 600px){
        .table-container { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
            margin: 5px 0;
        }
        .table-container table {
            min-width: 1200px;
            font-size: 0.75rem;
        }
        
        table thead th:nth-child(4),
        table thead th:nth-child(10),
        table thead th:nth-child(11) { display: none; }
        table tbody td:nth-child(4),
        table tbody td:nth-child(10),
        table tbody td:nth-child(11) { display: none; }
        
        table th, table td { padding: 6px; font-size: 11px; }
        td img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; }
        
        .modal-content { width: calc(100% - 24px); margin: 4% auto; max-width: 95%; }
        .filter-box input, .filter-box select { min-width: auto; width: 100%; }
        .tab-btn { width: 100%; text-align: left; }
        
        .periode-nav { flex-direction: column; align-items: stretch; gap: 10px; }
        .periode-nav .nav-center { justify-content: space-between; }
        .periode-nav .date-label { white-space: normal; text-align: center; }
        
        .jentik-full { display: inline; }
        .jentik-short { display: none; }
        
        .header { flex-direction: column; }
        .header h1 { margin-bottom: 10px; }
    }
    
    @media(max-width: 480px){
        .table-container {
            margin: 5px 0;
        }
        .table-container table {
            min-width: 1200px;
            font-size: 0.7rem;
        }
        
        table th, table td { padding: 4px; }
        
        .jentik-full { display: none; }
        .jentik-short { display: inline; }
        .badge-success, .badge-warning, .badge-danger { 
            font-size: 10px; 
            padding: 4px 6px; 
        }
        
        .mobile-nav-item { padding: 6px 8px; }
        .nav-icon { font-size: 20px; }
        .nav-label { font-size: 10px; }
        
        .mobile-menu-btn {
            width: 44px;
            height: 44px;
            top: 16px;
            right: 16px;
        }
        
        .header h1 { font-size: 18px; }
        
        /* Make images responsive in table */
        td img { 
            width: 40px !important; 
            height: 40px !important; 
            object-fit: cover; 
            border-radius: 6px; 
        }
        
        /* Ensure table cells with images are centered */
        td:nth-child(9) { text-align: center; }
        
        /* Improve button sizes for mobile */
        .btn-upload {
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 4px;
        }
    }
</style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <h2>🦟 Jumantik</h2>
        <ul>
            <li><a href="dashboard-rw.php">🏠 Beranda</a></li>
            <li><a href="laporan-rw.php" class="active">📄 Laporan RW</a></li>
            <li><a href="logout.php">🚪 Keluar</a></li>
        </ul>
    </aside>

    <main class="content">
        <div class="header">
            <h1>📄 Laporan RW <?= htmlspecialchars($nomor_rw); ?></h1>
            <div style="display: flex; gap: 10px;">
                <a href="export-rekapitulasi.php?<?= http_build_query($_GET); ?>" class="btn" style="background:#0284c7;color:#fff;border-radius:8px;padding:10px 16px;text-decoration:none;">
                    📊 Export Rekapitulasi
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div style="padding:12px 20px;background:#22c55e;color:white;border-radius:8px;margin-bottom:20px;">
            ✅ <?= htmlspecialchars($_GET['success']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div style="padding:12px 20px;background:#ef4444;color:white;border-radius:8px;margin-bottom:20px;">
            ❌ <?= htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>

        <?php
        // Hitung minggu dan bulan untuk button navigasi
        // Mingguan: minggu ke-1..6 dalam sebuah bulan (berdasarkan tombol di data kader)
        // Jika ada pilihan dropdown 'minggu_choice' (format: YYYY-MM|N), parse ke GET agar kompatibel
        if (isset($_GET['minggu_choice']) && strpos($_GET['minggu_choice'], '|') !== false) {
            list($g_bulan, $g_minggu) = explode('|', $_GET['minggu_choice'], 2);
            $_GET['bulan'] = $g_bulan;
            $_GET['minggu'] = intval($g_minggu);
        }

        // Jika form navbar mengirimkan `month` dan `year` terpisah, gabungkan jadi `bulan` (YYYY-MM)
        if (isset($_GET['month']) && isset($_GET['year'])) {
            $y = intval($_GET['year']);
            $m = intval($_GET['month']);
            if ($y > 0 && $m >= 1 && $m <= 12) {
                $_GET['bulan'] = sprintf('%04d-%02d', $y, $m);
            }
        }

        $current_periode = $_GET['periode'] ?? 'mingguan';

        if ($current_periode == 'mingguan') {
            $current_month = $_GET['bulan'] ?? date('Y-m');
            $current_minggu = isset($_GET['minggu']) ? intval($_GET['minggu']) : 1;

            // Clamp minggu antara 1..6
            if ($current_minggu < 1) $current_minggu = 1;
            if ($current_minggu > 6) $current_minggu = 6;

            $periode_label = 'Minggu ' . $current_minggu . ', ' . date('F Y', strtotime($current_month . '-01'));

            // Prev / Next minggu (bisa bergeser bulan jika perlu)
            $prev_minggu = $current_minggu - 1;
            $prev_bulan = $current_month;
            if ($prev_minggu < 1) {
                $prev_minggu = 6;
                $prev_bulan = date('Y-m', strtotime($current_month . '-01 -1 month'));
            }

            $next_minggu = $current_minggu + 1;
            $next_bulan = $current_month;
            if ($next_minggu > 6) {
                $next_minggu = 1;
                $next_bulan = date('Y-m', strtotime($current_month . '-01 +1 month'));
            }
            $prev_week = http_build_query(['periode' => 'mingguan', 'bulan' => $prev_bulan, 'minggu' => $prev_minggu]);
            $next_week = http_build_query(['periode' => 'mingguan', 'bulan' => $next_bulan, 'minggu' => $next_minggu]);
        } else {
            $current_month = $_GET['bulan'] ?? date('Y-m');
            list($year, $month) = explode('-', $current_month);

            // Bulan sebelumnya
            $prev_month = date('Y-m', strtotime($current_month . '-01 -1 month'));
            $prev_month_label = date('F Y', strtotime($prev_month . '-01'));

            // Bulan saat ini
            $curr_month_label = date('F Y', strtotime($current_month . '-01'));
            $periode_label = $curr_month_label;

            // Bulan selanjutnya
            $next_month = date('Y-m', strtotime($current_month . '-01 +1 month'));
            $next_month_label = date('F Y', strtotime($next_month . '-01'));
        }
// Simpan parameter filter lainnya
$filter_params = [];
if (!empty($_GET['cari'])) $filter_params['cari'] = $_GET['cari'];
if (!empty($_GET['status'])) $filter_params['status'] = $_GET['status'];
$filter_query = http_build_query($filter_params);
$filter_query = !empty($filter_query) ? '&' . $filter_query : '';
        ?>

<!-- Periode Navigation (Navbar) -->
<div class="periode-nav">
    <div class="nav-left">
        <a href="?periode=mingguan<?= $filter_query; ?>" class="tab-btn <?= ($current_periode == 'mingguan') ? 'active' : ''; ?>">📅 Per Minggu</a>
        <a href="?periode=bulanan<?= $filter_query; ?>" class="tab-btn <?= ($current_periode == 'bulanan') ? 'active' : ''; ?>">📆 Per Bulan</a>
    </div>
    <div class="nav-center">
        <?php if ($current_periode == 'mingguan'): ?>
            <div style="display:flex;align-items:center;gap:10px;width:100%;justify-content:center;">
                <form method="GET" style="display:inline-flex;align-items:center;gap:8px;">
                    <input type="hidden" name="periode" value="mingguan">
                    <?php foreach($filter_params as $fk => $fv): ?>
                        <input type="hidden" name="<?= htmlspecialchars($fk); ?>" value="<?= htmlspecialchars($fv); ?>">
                    <?php endforeach; ?>
                    <?php
                    // build month options (2 months back .. +1 month)
                    $base_month_nav = $current_month ?? date('Y-m');
                    $start_month_nav = date('Y-m', strtotime($base_month_nav . '-01 -2 month'));
                    $months_nav = [];
                    for ($i = 0; $i < 4; $i++) { $months_nav[] = date('Y-m', strtotime($start_month_nav . ' +' . $i . ' month')); }
                    ?>
                    <label style="margin:0 4px 0 0; font-weight:600; color:#1e293b;">Bulan</label>
                    <select name="bulan" onchange="this.form.submit()" style="padding:8px;border-radius:8px;border:1px solid #cbd5e1;min-width:160px;">
                        <?php foreach ($months_nav as $mth_nav):
                            $label_month_nav = date('F Y', strtotime($mth_nav . '-01'));
                            $sel_m = (isset($current_month) && $current_month == $mth_nav) ? 'selected' : '';
                        ?>
                            <option value="<?= $mth_nav; ?>" <?= $sel_m; ?>><?= $label_month_nav; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label style="margin:0 4px 0 6px; font-weight:600; color:#1e293b;">Minggu</label>
                    <select name="minggu" onchange="this.form.submit()" style="padding:8px;border-radius:8px;border:1px solid #cbd5e1;min-width:120px;">
                        <?php for ($wk_nav = 1; $wk_nav <= 6; $wk_nav++):
                            $sel_w = (isset($current_minggu) && $current_minggu == $wk_nav) ? 'selected' : '';
                        ?>
                            <option value="<?= $wk_nav; ?>" <?= $sel_w; ?>>Minggu <?= $wk_nav; ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
        <?php else: ?>
            <a href="?periode=bulanan&bulan=<?= $prev_month; ?><?= $filter_query; ?>" class="nav-btn">←</a>
            <div class="date-label">📆 <?= htmlspecialchars($periode_label); ?></div>
            <a href="?periode=bulanan&bulan=<?= $next_month; ?><?= $filter_query; ?>" class="nav-btn">→</a>
        <?php endif; ?>
    </div>
</div>

<?php
// ===== GENERATE REKAP TATANAN BERDASARKAN DATA INPUT KADER (JSON) =====
// Perhitungan minggu/bulan disamakan dengan logika di laporan-kader/process-jumantik
if ($current_periode == 'mingguan' && isset($_GET['minggu'])) {
    // Mingguan berdasarkan minggu ke-1..6 dalam bulan
    $minggu = intval($_GET['minggu']);
    $bulan_filter = $_GET['bulan'] ?? date('Y-m');
    // keep nilai untuk penggunaan query selanjutnya
} else {
    $bulan_filter = $_GET['bulan'] ?? date('Y-m');
}

// Ambil baris rekap (tatanan_diperiksa = 'Rekap') untuk RW ini dan periode terkait
$query_tatanan = "
    SELECT 
        dj.id,
        dj.rt,
        dj.rw,
        dj.catatan,
        dj.tanggal_pemeriksaan,
        k.nama_kader,
        k.rw as kader_rw
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE dj.tatanan_diperiksa = 'Rekap'
    AND CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST(COALESCE(NULLIF(dj.rw, ''), k.rw) AS CHAR) COLLATE utf8mb4_general_ci), 'rw', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED) = ?
";

// Tambahkan filter periode
if ($current_periode == 'mingguan' && isset($_GET['minggu'])) {
    $minggu = intval($_GET['minggu']);
    if ($minggu < 1) $minggu = 1; if ($minggu > 6) $minggu = 6;
    $query_tatanan .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ? AND CEIL(DAY(dj.tanggal_pemeriksaan)/7) = ?";
    $stmt_tatanan = mysqli_prepare($conn, $query_tatanan . " ORDER BY dj.rt ASC");
    if ($stmt_tatanan === false) {
        error_log("Query error: " . mysqli_error($conn));
        echo "Error: " . mysqli_error($conn);
        exit;
    }
    mysqli_stmt_bind_param($stmt_tatanan, "isi", $nomor_rw_filter_int, $bulan_filter, $minggu);
} else {
    $query_tatanan .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?";
    $stmt_tatanan = mysqli_prepare($conn, $query_tatanan . " ORDER BY dj.rt ASC");
    if ($stmt_tatanan === false) {
        error_log("Query error: " . mysqli_error($conn));
        echo "Error: " . mysqli_error($conn);
        exit;
    }
    mysqli_stmt_bind_param($stmt_tatanan, "is", $nomor_rw_filter_int, $bulan_filter);
}

mysqli_stmt_execute($stmt_tatanan);
if (!mysqli_stmt_execute($stmt_tatanan)) {
    error_log("Execute error: " . mysqli_error($conn));
    echo "Execute error: " . mysqli_error($conn);
    exit;
}
$result_tatanan = mysqli_stmt_get_result($stmt_tatanan);

// Kelompokkan data per RT berdasarkan JSON catatan (struktur sama seperti input di laporan-kader/data-jumantik)
$data_per_rt = [];
$tatanan_labels = [
    'Pemukiman',
    'Institusi Pendidikan',
    'Tempat Kerja',
    'Ttu',
    'Tpm',
    'Sarana Olahraga',
    'Sarana Kesehatan'
];

while ($row = mysqli_fetch_assoc($result_tatanan)) {
    $rt = $row['rt'] ?? '-';
    $nama_kader_row = $row['nama_kader'] ?? null;
    $catatan_json = $row['catatan'] ?? '';
    
    error_log("DEBUG PARSING: RT=$rt, catatan_json=" . substr($catatan_json, 0, 100));

    if (!isset($data_per_rt[$rt])) {
        $data_per_rt[$rt] = [
            'nama_kader' => $nama_kader_row,
            'Pemukiman' => ['diperiksa' => 0, 'jentik' => 0],
            'Institusi Pendidikan' => ['diperiksa' => 0, 'jentik' => 0],
            'Tempat Kerja' => ['diperiksa' => 0, 'jentik' => 0],
            'Ttu' => ['diperiksa' => 0, 'jentik' => 0],
            'Tpm' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Olahraga' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Kesehatan' => ['diperiksa' => 0, 'jentik' => 0],
        ];
    } elseif (empty($data_per_rt[$rt]['nama_kader']) && $nama_kader_row) {
        // isi nama_kader kalau sebelumnya kosong
        $data_per_rt[$rt]['nama_kader'] = $nama_kader_row;
    }

    // Decode JSON catatan: kunci tatanan berisi {diperiksa, jentik}, _catatan diabaikan
    $decoded = json_decode($catatan_json, true);
    if (is_array($decoded)) {
        error_log("DEBUG JSON KEYS: " . implode(", ", array_keys($decoded)));
        // Mapping untuk handle variasi nama tatanan yang mungkin tersimpan
        $tatanan_aliases = [
            'pemukiman' => 'Pemukiman',
            'ttu' => 'TTU',
            'tpm' => 'TPM',
            'ttuolahraga' => 'Sarana Olahraga',
            'ttukesehatanbaru' => 'Sarana Kesehatan',
            'ttu olahraga' => 'Sarana Olahraga',
            'sarana kesehatan' => 'Sarana Kesehatan'
        ];
        
        foreach ($tatanan_labels as $label) {
            // Cari di decoded array dengan berbagai variasi nama
            $found = false;
            $diperiksa = 0;
            $jentik = 0;
            
            // Coba exact match dulu
            if (isset($decoded[$label]) && is_array($decoded[$label])) {
                $diperiksa = (int)($decoded[$label]['diperiksa'] ?? 0);
                $jentik = (int)($decoded[$label]['jentik'] ?? 0);
                $found = true;
            } else {
                // Coba case-insensitive + normalize spaces
                $label_normalized = strtolower(trim($label));
                foreach ($decoded as $key => $val) {
                    if (!is_array($val) || !isset($val['diperiksa'])) continue;
                    $key_normalized = strtolower(trim($key));
                    if ($key_normalized === $label_normalized || 
                        str_replace(' ', '', $key_normalized) === str_replace(' ', '', $label_normalized)) {
                        $diperiksa = (int)($val['diperiksa'] ?? 0);
                        $jentik = (int)($val['jentik'] ?? 0);
                        $found = true;
                        break;
                    }
                }
            }
            
            if ($found) {
                $data_per_rt[$rt][$label]['diperiksa'] += $diperiksa;
                $data_per_rt[$rt][$label]['jentik'] += $jentik;
            }
        }
    }
}

$grand_total_diperiksa = 0;
$grand_total_jentik = 0;
?>

<form method="GET" class="filter-box">
    <input type="hidden" name="periode" value="<?= htmlspecialchars($current_periode); ?>">
    <?php if ($current_periode == 'mingguan'): ?>
        <!-- Periode dikontrol lewat navbar; kirimkan nilai saat ini sebagai hidden -->
        <input type="hidden" name="bulan" value="<?= htmlspecialchars($current_month ?? date('Y-m')); ?>">
        <input type="hidden" name="minggu" value="<?= htmlspecialchars($current_minggu ?? 1); ?>">
    <?php else: ?>
        <input type="hidden" name="bulan" value="<?= htmlspecialchars($current_month ?? date('Y-m')); ?>">
    <?php endif; ?>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th rowspan="2">RT</th>
                    <th rowspan="2">Nama Jumantik</th>
                    <th colspan="2">Pemukiman</th>
                    <th colspan="2">Institusi Pendidikan</th>
                    <th colspan="2">Tempat Kerja</th>
                    <th colspan="2">TTU</th>
                    <th colspan="2">TPM</th>
                    <th colspan="2">Sarana Olahraga</th>
                    <th colspan="2">Sarana Kesehatan</th>
                    <th colspan="3">Jumlah</th>
                    <th rowspan="2">ABJ</th>
                </tr>
                <tr>
                    <th>Diperiksa</th><th>Jentik (+)</th>
                    <th>Diperiksa</th><th>Jentik (+)</th>
                    <th>Diperiksa</th><th>Jentik (+)</th>
                    <th>Diperiksa</th><th>Jentik (+)</th>
                    <th>Diperiksa</th><th>Jentik (+)</th>
                    <th>Diperiksa</th><th>Jentik (+)</th>
                    <th>Diperiksa</th><th>Jentik (+)</th>
                    <th>Total Diperiksa</th>
                    <th>Total Jentik</th>
                    <th>Total Bebas</th>
                </tr>
            </thead>
            <tbody>
<?php
foreach ($data_per_rt as $rt => $data):
    $total_diperiksa = 0;
    $total_jentik = 0;
    
    foreach (['Pemukiman', 'Institusi Pendidikan', 'Tempat Kerja', 'Ttu', 'Tpm', 'Sarana Olahraga', 'Sarana Kesehatan'] as $tatanan) {
        $total_diperiksa += $data[$tatanan]['diperiksa'];
        $total_jentik += $data[$tatanan]['jentik'];
    }
    
    $total_bebas = $total_diperiksa - $total_jentik;
    $abj = $total_diperiksa > 0 ? round(($total_bebas / $total_diperiksa) * 100, 2) : 0;
    
    $grand_total_diperiksa += $total_diperiksa;
    $grand_total_jentik += $total_jentik;
?>
                    <tr>
                    <td><?= htmlspecialchars($rt); ?></td>
                    <td><?= htmlspecialchars($data['nama_kader'] ?? '-'); ?></td>
                    <td><?= $data['Pemukiman']['diperiksa']; ?></td>
                    <td><?= $data['Pemukiman']['jentik']; ?></td>
                    <td><?= $data['Institusi Pendidikan']['diperiksa']; ?></td>
                    <td><?= $data['Institusi Pendidikan']['jentik']; ?></td>
                    <td><?= $data['Tempat Kerja']['diperiksa']; ?></td>
                    <td><?= $data['Tempat Kerja']['jentik']; ?></td>
                    <td><?= $data['Ttu']['diperiksa']; ?></td>
                    <td><?= $data['Ttu']['jentik']; ?></td>
                    <td><?= $data['Tpm']['diperiksa']; ?></td>
                    <td><?= $data['Tpm']['jentik']; ?></td>
                    <td><?= $data['Sarana Olahraga']['diperiksa']; ?></td>
                    <td><?= $data['Sarana Olahraga']['jentik']; ?></td>
                    <td><?= $data['Sarana Kesehatan']['diperiksa']; ?></td>
                    <td><?= $data['Sarana Kesehatan']['jentik']; ?></td>
                    <td><strong><?= $total_diperiksa; ?></strong></td>
                    <td><strong><?= $total_jentik; ?></strong></td>
                    <td><strong><?= $total_bebas; ?></strong></td>
                    <td><strong><?= $abj; ?>%</strong></td>
                </tr>
<?php endforeach; 

$grand_total_bebas = $grand_total_diperiksa - $grand_total_jentik;
$grand_abj = $grand_total_diperiksa > 0 ? round(($grand_total_bebas / $grand_total_diperiksa) * 100, 2) : 0;
?>
                <tr style="background: #f1f5f9; font-weight: bold;">
                    <td colspan="2">TOTAL</td>
                    <?php 
                    $tatanan_list = ['Pemukiman', 'Institusi Pendidikan', 'Tempat Kerja', 'Ttu', 'Tpm', 'Sarana Olahraga', 'Sarana Kesehatan'];
                    foreach ($tatanan_list as $tatanan):
                        $sum_diperiksa = array_sum(array_column(array_column($data_per_rt, $tatanan), 'diperiksa'));
                        $sum_jentik = array_sum(array_column(array_column($data_per_rt, $tatanan), 'jentik'));
                    ?>
                    <td><?= $sum_diperiksa; ?></td>
                    <td><?= $sum_jentik; ?></td>
                    <?php endforeach; ?>
                    <td><?= $grand_total_diperiksa; ?></td>
                    <td><?= $grand_total_jentik; ?></td>
                    <td><?= $grand_total_bebas; ?></td>
                    <td><?= $grand_abj; ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>
    
<?php mysqli_stmt_close($stmt_tatanan); ?>

    <button type="submit">🔍 Cari</button>
    <a href="laporan-rw.php" class="reset-btn">↻ Reset</a>
</form>

        <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>RT/RW</th>
                    <th>Nama Kader</th>
                    <th>Minggu Ke-</th>
                    <th>Bulan</th>
                    <th>Rekap Tatanan (Diperiksa / Jentik+)</th>
                    <th>Gambar</th>
                    <th>Catatan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php

// Persiapan parameter filter
$params = [];
$types = "";
$query_conditions = [];

// Filter berdasarkan RW - HARUS ADA!
if ($nomor_rw_filter_int !== null) {
    $rw_patterns = [
        "CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = ?",
        "CAST(REGEXP_SUBSTR(k.rw, '[0-9]+') AS UNSIGNED) = ?"
    ];
    $query_conditions[] = "(" . implode(" OR ", $rw_patterns) . ")";
    $params[] = $nomor_rw_filter_int;
    $params[] = $nomor_rw_filter_int;
    $types .= "ii";
}

// Filter Periode (Mingguan atau Bulanan)
$periode = $_GET['periode'] ?? 'mingguan';

if ($periode == 'bulanan' && !empty($_GET['bulan'])) {
    $query_conditions[] = "DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?";
    $params[] = $_GET['bulan'];
    $types .= "s";
} elseif ($periode == 'mingguan' && isset($_GET['minggu'])) {
    $minggu = intval($_GET['minggu']);
    if ($minggu < 1) $minggu = 1; if ($minggu > 6) $minggu = 6;
    $bulan_for_week = $_GET['bulan'] ?? date('Y-m');
    $query_conditions[] = "DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?";
    $params[] = $bulan_for_week;
    $types .= "s";
    $query_conditions[] = "(FLOOR((DAY(dj.tanggal_pemeriksaan)-1)/7)+1) = ?";
    $params[] = $minggu;
    $types .= "i";
}

// Filter pencarian
if (!empty($_GET['cari'])) {
    $search_term = '%' . $_GET['cari'] . '%';
    $query_conditions[] = "(dj.nama_kepala_keluarga LIKE ? OR k.nama_kader LIKE ? OR dj.rt LIKE ? OR dj.alamat LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

// Filter status jentik
if (!empty($_GET['status'])) {
    $status_label = $_GET['status'];
    $query_conditions[] = "(CASE WHEN dj.jentik_ditemukan IN ('Ya','Ada') THEN 'Ada' ELSE 'Tidak Ada' END) = ?";
    $params[] = $status_label;
    $types .= "s";
}

// Filter tatanan
if (!empty($_GET['tatanan'])) {
    $tatanan_filter = $_GET['tatanan'];
    $query_conditions[] = "dj.tatanan_diperiksa = ?";
    $params[] = $tatanan_filter;
    $types .= "s";
} else {
    // Jika tidak memilih tatanan khusus, tampilkan baris 'Rekap'
    // agar tabel detail sesuai dengan rekap di atas
    $query_conditions[] = "dj.tatanan_diperiksa = 'Rekap'";
}

// Build WHERE clause
$where_clause = !empty($query_conditions) ? "WHERE " . implode(" AND ", $query_conditions) : "";

$query = "
        SELECT 
        dj.id,
        dj.rt,
        dj.rw,
        k.nama_kader,
        dj.catatan,
        dj.foto_rumah,
        dj.tanggal_pemeriksaan
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    LEFT JOIN users uk ON k.user_id = uk.id
    {$where_clause}
    ORDER BY dj.tanggal_pemeriksaan DESC, dj.id DESC
";

$stmt = mysqli_prepare($conn, $query);

if ($stmt === false) {
    die("Error preparing statement: " . mysqli_error($conn));
}

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Debug logging
error_log("=== LAPORAN RW DETAIL QUERY ===");
error_log("Total rows for detail report: " . mysqli_num_rows($result));
error_log("Types: " . $types);
error_log("Params count: " . count($params));
error_log("Params: " . json_encode($params));
error_log("Query: " . $query);

if (mysqli_num_rows($result) > 0):
    $no = 1;
    while ($row = mysqli_fetch_assoc($result)):
        // Parse catatan JSON seperti di laporan-kader
        $catatan_raw = $row['catatan'] ?? '';
        $parsed = null;
        $catatan_text = '';
        if (!empty($catatan_raw)) {
            $tmp = json_decode($catatan_raw, true);
            if (is_array($tmp)) {
                $parsed = $tmp;
                if (isset($parsed['_catatan'])) {
                    $catatan_text = $parsed['_catatan'];
                    unset($parsed['_catatan']);
                }
            } else {
                $catatan_text = $catatan_raw;
            }
        }

        // Hitung minggu ke- dan nama bulan dari tanggal_pemeriksaan
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
?>
    <tr>
        <td><?= $no++; ?></td>
        <td><?php
            $rt = trim($row['rt'] ?? '-');
            $rw = trim($row['rw'] ?? '-');
            if ($rt !== '-' && $rw !== '-') {
                echo htmlspecialchars('RT ' . $rt . '/RW ' . $rw);
            } elseif ($rt !== '-') {
                echo htmlspecialchars('RT ' . $rt);
            } elseif ($rw !== '-') {
                echo htmlspecialchars('RW ' . $rw);
            } else {
                echo '-';
            }
        ?></td>
        <td><?= htmlspecialchars($row['nama_kader'] ?? '-'); ?></td>
        <td><?= htmlspecialchars($minggu_ke); ?></td>
        <td><?= htmlspecialchars($bulan_name); ?></td>
        <td style="text-align:left;">
            <?php if ($parsed): ?>
                <?php foreach ($parsed as $tn => $vals): ?>
                    <div style="margin-bottom:4px;">
                        <strong><?= htmlspecialchars($tn); ?>:</strong>
                        Diperiksa <?= intval($vals['diperiksa'] ?? 0); ?> / Jentik(+) <?= intval($vals['jentik'] ?? 0); ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <span style="color:#94a3b8;">-</span>
            <?php endif; ?>
        </td>
        <td style="text-align:center;">
            <?php if (!empty($row['foto_rumah'])): 
                $image_path = $base_url . 'backend/uploads/' . htmlspecialchars($row['foto_rumah']);
                $local_path = 'uploads/' . htmlspecialchars($row['foto_rumah']);
                if (file_exists($local_path)):
            ?>
                <div style="display:flex;flex-direction:column;gap:6px;align-items:center;">
                    <img src="<?= $image_path; ?>" alt="Foto" style="width:56px;height:56px;border-radius:8px;object-fit:cover;border:2px solid #e2e8f0;cursor:pointer;" onclick="viewImage('<?= $image_path; ?>')">
                    <button class="btn-upload" onclick="openUploadModal(<?= (int)$row['id']; ?>)">🔄 Ganti</button>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:6px;align-items:center;">
                    <span style="color:#f59e0b;font-size:12px;">❌ Hilang</span>
                    <button class="btn-upload" onclick="openUploadModal(<?= (int)$row['id']; ?>)">📤 Upload</button>
                </div>
            <?php endif; else: ?>
                <button class="btn-upload" onclick="openUploadModal(<?= (int)$row['id']; ?>)">📤 Upload</button>
            <?php endif; ?>
        </td>
        <td>
            <?php if (!empty($catatan_text)): ?>
                <?= htmlspecialchars($catatan_text); ?>
            <?php else: ?>
                <span style="color:#cbd5e1;">-</span>
            <?php endif; ?>
        </td>
        <td>
            <button class="btn-upload" style="background:#dc2626;" onclick="deleteData(<?= (int)$row['id']; ?>)">🗑️ Hapus</button>
        </td>
    </tr>
<?php 
    endwhile;
else:
?>
    <tr>
        <td colspan="8" style="color:#94a3b8; padding:40px; font-style: italic;">
            📭 Belum ada laporan untuk filter ini
        </td>
    </tr>
<?php 
endif;

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
            </tbody>
        </table>
        </div>
        
        <!-- Modal Preview Gambar -->
        <div id="imageModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>📷 Preview Gambar</h3>
                    <span class="close" onclick="closeImageModal()">&times;</span>
                </div>
                <div class="modal-body" style="text-align:center;">
                    <img id="modalImage" src="" alt="Gambar" style="max-width:100%; max-height:75vh; border-radius:8px;">
                </div>
            </div>
        </div>

        <!-- Modal Upload Gambar -->
        <div id="uploadModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>📤 Upload Gambar Rumah</h3>
                    <span class="close" onclick="closeUploadModal()">&times;</span>
                </div>
                <form id="uploadForm" class="upload-form" style="padding:20px;" enctype="multipart/form-data">
                    <input type="hidden" id="upload_id" name="id">
                    
                    <div class="form-group">
                        <label>Pilih Gambar *</label>
                        <input type="file" name="foto_rumah" id="foto_rumah" accept="image/*" required onchange="previewUploadImage(event)">
                        <small style="color:#64748b;">Format: JPG, PNG, GIF. Maksimal 5MB</small>
                    </div>

                    <img id="previewImage" class="preview-image" alt="Preview">

                    <div class="modal-footer" style="padding:0;background:transparent;border:none;">
                        <button type="button" class="btn" onclick="closeUploadModal()" style="background:#64748b;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;margin-right:8px;">Batal</button>
                        <button type="submit" class="btn" style="background:#3b82f6;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;">💾 Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Mobile Menu Button (Hamburger) -->
<button class="mobile-menu-btn" onclick="toggleSidebar()">
    <div class="hamburger" id="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </div>
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav">
    <div class="mobile-nav-content">
        <a href="dashboard-rw.php" class="mobile-nav-item">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Beranda</span>
        </a>
        <a href="laporan-rw.php" class="mobile-nav-item active">
            <span class="nav-icon">📄</span>
            <span class="nav-label">Laporan</span>
        </a>
        <a href="logout.php" class="mobile-nav-item">
            <span class="nav-icon">🚪</span>
            <span class="nav-label">Keluar</span>
        </a>
    </div>
</nav>

<script>
// ===== MODAL IMAGE FUNCTIONS =====
function viewImage(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// ===== MODAL UPLOAD FUNCTIONS =====
function openUploadModal(id) {
    document.getElementById('upload_id').value = id;
    document.getElementById('uploadModal').style.display = 'block';
    document.getElementById('foto_rumah').value = '';
    document.getElementById('previewImage').style.display = 'none';
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function previewUploadImage(event) {
    const preview = document.getElementById('previewImage');
    const file = event.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}

// ===== FORM SUBMISSIONS =====
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Uploading...';
    
    fetch('ajax/upload-gambar.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✅ Gambar berhasil diupload!');
            location.reload();
        } else {
            alert('❌ Gagal: ' + (d.message || 'Kesalahan tidak diketahui'));
            submitBtn.disabled = false;
            submitBtn.textContent = '💾 Upload';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('⚠️ Terjadi kesalahan pada koneksi');
        submitBtn.disabled = false;
        submitBtn.textContent = '💾 Upload';
    });
});

// ===== HAPUS DATA (RW) =====
function deleteData(id) {
    if (!confirm('⚠️ Apakah Anda yakin ingin menghapus data ini?')) return;

    const formData = new FormData();
    formData.append('id', id);

    // Show loading state
    const deleteBtn = event.target;
    const originalText = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '⏳ Menghapus...';

    fetch('ajax/delete-jumantik.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check content type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server tidak mengembalikan JSON. Response: ' + text.substring(0, 100));
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('❌ ' + (data.message || 'Gagal menghapus data'));
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error detail:', error);
        alert('❌ Terjadi kesalahan: ' + error.message);
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
    });
}

// ===== MODAL CLOSE ON BACKGROUND CLICK =====
window.addEventListener('click', function(e) {
    const im = document.getElementById('imageModal');
    const um = document.getElementById('uploadModal');
    if (e.target === im) closeImageModal();
    if (e.target === um) closeUploadModal();
});

// ===== MOBILE SIDEBAR FUNCTIONS =====
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const hamburger = document.getElementById('hamburger');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    hamburger.classList.toggle('active');
    
    if (sidebar.classList.contains('active')) {
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    } else {
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 300);
        document.body.style.overflow = 'auto';
    }
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const hamburger = document.getElementById('hamburger');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    hamburger.classList.remove('active');
    
    setTimeout(() => {
        overlay.style.display = 'none';
    }, 300);
    document.body.style.overflow = 'auto';
}

// Close sidebar when clicking menu items
document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('click', closeSidebar);
});

// Handle window resize
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    }, 250);
});
</script>
</body>
</html>