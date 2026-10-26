<?php
session_start();
require_once 'config.php';

// Cek autentikasi admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
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

if (!function_exists('normalizeRtSql')) {
    function normalizeRtSql($column) {
        return "CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST($column AS CHAR) COLLATE utf8mb4_general_ci), 'rt', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED)";
    }
}

// === INISIALISASI VARIABEL FILTER (PINDAHKAN KE SINI!) ===
$selected_rw = $_GET['rw'] ?? '';
$selected_rt = $_GET['rt'] ?? '';
$filter_periode = $_GET['periode'] ?? '';
$filter_minggu = isset($_GET['minggu']) ? intval($_GET['minggu']) : 0;
$filter_bulan = $_GET['bulan'] ?? '';
$filter_bulan_n = isset($_GET['bulan_n']) ? intval($_GET['bulan_n']) : 0;
$filter_tahun = $_GET['tahun'] ?? '';

$selected_rw_int = $selected_rw !== '' ? intval(normalizeNumber($selected_rw)) : null;
$selected_rt_int = $selected_rt !== '' ? intval(normalizeNumber($selected_rt)) : null;

// === STATISTIK UTAMA ===
$stats_queries = [
    'total_rw' => "SELECT COUNT(DISTINCT id) AS total FROM rw",
    'total_kader' => "SELECT COUNT(*) AS total FROM kader",
    'total_laporan' => "SELECT COUNT(*) AS total FROM data_jumantik",
    'total_jentik' => "SELECT COUNT(*) AS total FROM data_jumantik WHERE jentik_ditemukan = 'Ya'",
    'total_tidak_jentik' => "SELECT COUNT(*) AS total FROM data_jumantik WHERE jentik_ditemukan = 'Tidak'"
];

$stats = [];
foreach ($stats_queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    $stats[$key] = mysqli_fetch_assoc($result)['total'];
}

$rw_expr_r = normalizeRwSql('r.username');

// === REKAPITULASI PER TATANAN (PER RW) ===
// Ambil baris dengan tatanan_diperiksa = 'Rekap' yang berisi JSON agregasi
$query_tatanan_per_rw = "
    SELECT 
        COALESCE(k.rw, dj.rw) as rw,
        dj.catatan,
        k.nama_kader
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE dj.tatanan_diperiksa = 'Rekap'
";

// Filter berdasarkan RW jika ada
if ($selected_rw_int !== null) {
    $query_tatanan_per_rw .= " AND " . normalizeRwSql("COALESCE(NULLIF(dj.rw, ''), k.rw)") . " = $selected_rw_int";
}

// Filter berdasarkan periode
if ($filter_periode == 'minggu' && $filter_minggu > 0 && $filter_bulan_n > 0) {
    $year_to_use = !empty($filter_tahun) ? intval($filter_tahun) : intval(date('Y'));
    $query_tatanan_per_rw .= " AND CEIL(DAY(dj.tanggal_pemeriksaan)/7) = " . intval($filter_minggu);
    $query_tatanan_per_rw .= " AND MONTH(dj.tanggal_pemeriksaan) = " . intval($filter_bulan_n);
    $query_tatanan_per_rw .= " AND YEAR(dj.tanggal_pemeriksaan) = " . $year_to_use;
} elseif ($filter_periode == 'bulan' && !empty($filter_bulan)) {
    $query_tatanan_per_rw .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filter_bulan) . "'";
}

$query_tatanan_per_rw .= " ORDER BY COALESCE(k.rw, dj.rw) ASC";

$result_tatanan_per_rw = mysqli_query($conn, $query_tatanan_per_rw);

// Error handling untuk query
if (!$result_tatanan_per_rw) {
    die("Database Error pada query tatanan per RW: " . mysqli_error($conn));
}

// Kelompokkan data per RW berdasarkan JSON catatan
$data_tatanan_rw = [];
$tatanan_labels = [
    'Pemukiman',
    'Institusi Pendidikan',
    'Tempat Kerja',
    'TTU',
    'TPM',
    'Sarana Olahraga',
    'Sarana Kesehatan'
];

while ($row = mysqli_fetch_assoc($result_tatanan_per_rw)) {
    $rw = $row['rw'] ?? '-';
    $catatan_json = $row['catatan'] ?? '';
    
    if (!isset($data_tatanan_rw[$rw])) {
        $data_tatanan_rw[$rw] = [
            'Pemukiman' => ['diperiksa' => 0, 'jentik' => 0],
            'Institusi Pendidikan' => ['diperiksa' => 0, 'jentik' => 0],
            'Tempat Kerja' => ['diperiksa' => 0, 'jentik' => 0],
            'TTU' => ['diperiksa' => 0, 'jentik' => 0],
            'TPM' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Olahraga' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Kesehatan' => ['diperiksa' => 0, 'jentik' => 0],
        ];
    }
    
    // Decode JSON catatan: kunci tatanan berisi {diperiksa, jentik}
    $decoded = json_decode($catatan_json, true);
    if (is_array($decoded)) {
        foreach ($tatanan_labels as $label) {
            if (!isset($decoded[$label]) || !is_array($decoded[$label])) continue;
            $diperiksa = (int)($decoded[$label]['diperiksa'] ?? 0);
            $jentik = (int)($decoded[$label]['jentik'] ?? 0);

            $data_tatanan_rw[$rw][$label]['diperiksa'] += $diperiksa;
            $data_tatanan_rw[$rw][$label]['jentik'] += $jentik;
        }
    }
}

// === LAPORAN PER RW ===
$kader_summary_query = "
    SELECT 
        " . normalizeRwSql('k.rw') . " AS rw_norm,
        COUNT(*) AS jumlah_kader,
        COUNT(DISTINCT k.rt) AS jumlah_rt
    FROM kader k
    GROUP BY rw_norm
";

$laporan_summary_query = "
    SELECT 
        " . normalizeRwSql("COALESCE(k.rw, dj.rw)") . " AS rw_norm,
        COUNT(*) AS total_laporan,
        SUM(CASE WHEN dj.jentik_ditemukan = 'Ya' THEN 1 ELSE 0 END) AS laporan_jentik
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    GROUP BY rw_norm
";

$query_per_rw = "
    SELECT 
        r.id,
        r.username AS nomor_rw,
        r.nama_rw,
        r.no_hp,
        COALESCE(k_data.jumlah_rt, 0) AS jumlah_rt,
        COALESCE(k_data.jumlah_kader, 0) AS jumlah_kader,
        COALESCE(l_data.total_laporan, 0) AS total_laporan,
        COALESCE(l_data.laporan_jentik, 0) AS laporan_jentik
    FROM rw r
    LEFT JOIN ($kader_summary_query) k_data ON k_data.rw_norm = {$rw_expr_r}
    LEFT JOIN ($laporan_summary_query) l_data ON l_data.rw_norm = {$rw_expr_r}
    ORDER BY {$rw_expr_r} ASC
";
$result_per_rw = mysqli_query($conn, $query_per_rw);

// === QUERY LAPORAN DETAIL ===
$query_laporan = "
    SELECT 
        dj.id,
        dj.tanggal_pemeriksaan,
        dj.rt,
        dj.rw,
        dj.nama_kepala_keluarga,
        dj.alamat,
        dj.tatanan_diperiksa,
        dj.jentik_ditemukan,
        dj.status_pemeriksaan,
        dj.catatan,
        dj.foto_rumah,
        k.nama_kader AS nama_kader,
        k.rw AS kader_rw
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE dj.tatanan_diperiksa = 'Rekap'
";

// Filter RW
if ($selected_rw_int !== null) {
    $query_laporan .= " AND " . normalizeRwSql("COALESCE(NULLIF(dj.rw, ''), k.rw)") . " = $selected_rw_int";
}

// Filter RT
if (!empty($selected_rt)) {
    $selected_rt_escaped = mysqli_real_escape_string($conn, $selected_rt);
    $selected_rt_trimmed = trim($selected_rt);
    $selected_rt_normalized = normalizeNumber($selected_rt);
    
    $query_laporan .= " AND (";
    $query_laporan .= "LOWER(TRIM(COALESCE(dj.rt, ''))) = LOWER(TRIM('$selected_rt_escaped'))";
    
    if ($selected_rt_normalized !== '') {
        $selected_rt_int = intval($selected_rt_normalized);
        $query_laporan .= " OR " . normalizeRtSql('COALESCE(dj.rt, \'\')') . " = $selected_rt_int";
    }
    
    $query_laporan .= " OR LOWER(COALESCE(dj.rt, '')) LIKE LOWER('%" . mysqli_real_escape_string($conn, $selected_rt_trimmed) . "%')";
    $query_laporan .= ")";
}

// Filter Periode
if ($filter_periode == 'minggu' && $filter_minggu > 0 && $filter_bulan_n == 0) {
    $filter_bulan_n = intval(date('n'));
}

                if ($filter_periode == 'minggu' && $filter_minggu > 0 && $filter_minggu <= 6 && $filter_bulan_n > 0) {
    $year_to_use = !empty($filter_tahun) ? intval($filter_tahun) : intval(date('Y'));
    $query_laporan .= " AND CEIL(DAY(dj.tanggal_pemeriksaan)/7) = " . intval($filter_minggu);
    $query_laporan .= " AND MONTH(dj.tanggal_pemeriksaan) = " . intval($filter_bulan_n);
    $query_laporan .= " AND YEAR(dj.tanggal_pemeriksaan) = " . $year_to_use;
} elseif ($filter_periode == 'bulan' && !empty($filter_bulan)) {
    $query_laporan .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filter_bulan) . "'";
}

$query_laporan .= " ORDER BY dj.tanggal_pemeriksaan DESC, dj.id DESC";

// Ambil daftar RW untuk dropdown filter
$rw_list = mysqli_query($conn, "SELECT DISTINCT username FROM rw ORDER BY username ASC");

// Eksekusi query laporan
$q_laporan = mysqli_query($conn, $query_laporan);

// Error handling
if (!$q_laporan) {
    die("Database Error: " . mysqli_error($conn));
}

// Hitung statistik berdasarkan filter
$stats_filtered = [
    'total' => 0,
    'jentik_positif' => 0,
    'jentik_negatif' => 0
];

$data_laporan = [];
while ($row = mysqli_fetch_assoc($q_laporan)) {
    $data_laporan[] = $row;
    $stats_filtered['total']++;
    if (($row['jentik_ditemukan'] ?? 'Tidak') === 'Ya') {
        $stats_filtered['jentik_positif']++;
    } else {
        $stats_filtered['jentik_negatif']++;
    }
}

$stats_filtered['persentase_bebas'] = $stats_filtered['total'] > 0 
    ? round(($stats_filtered['jentik_negatif'] / $stats_filtered['total']) * 100, 1) 
    : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Admin | Jumantik Pondok Kopi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* === STATS GRID === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            margin: 10px 0;
            font-weight: 700;
        }
        
        .stat-card p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        .stat-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.teal { background: linear-gradient(135deg, #13547a 0%, #80d0c7 100%); }
        
        /* === SECTION TITLE === */
        .section-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
            font-weight: 600;
        }
        
        /* === STATS MINI === */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-mini-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-5px);
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
        
        .stat-mini-card.positive p { color: #dc2626; }
        .stat-mini-card.percentage p { color: #2563eb; }
        
        /* === RW TABLE === */
        .rw-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        /* RW Table header styling - same as desktop */
        .rw-table th {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 16px 14px;
            font-size: 13px;
        }
        
        /* RW Table wrapper */
        .rw-table > div {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-container > div {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Table container header styling - same as desktop */
        .table-container th {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 16px 14px;
            font-size: 13px;
        }
        
        /* === BUTTONS === */
        .expand-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .expand-btn:hover {
            background: #5568d3;
        }
        
        .change-password-btn {
            background: #f59e0b;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
            display: inline-block;
        }
        
        .change-password-btn:hover {
            background: #d97706;
        }
        
        .export-btn {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .export-btn:hover {
            background: #059669;
        }
        
        /* === DETAIL ROW === */
        .detail-row {
            display: none;
            background: #f8fafc;
        }
        
        .detail-row.show {
            display: table-row;
        }
        
        .detail-content {
            padding: 20px;
            background: #f1f5f9;
        }
        
        .detail-content h4 {
            margin-bottom: 15px;
            color: #1e293b;
            font-size: 1.1rem;
        }
        
        .detail-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .detail-table thead {
            background: #e2e8f0;
        }
        
        .detail-table th,
        .detail-table td {
            padding: 12px;
            text-align: left;
        }
        
        /* === FILTER SECTION === */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #475569;
            font-size: 0.9rem;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-btn {
            padding: 10px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .filter-btn:hover {
            background: #5568d3;
        }
        
        .reset-btn {
            padding: 10px 25px;
            background: #94a3b8;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .reset-btn:hover {
            background: #64748b;
        }
        
        /* === STATUS BADGES === */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #d97706;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #2563eb;
        }
        
        /* === IMAGE PREVIEW === */
        .image-preview {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #e2e8f0;
            transition: transform 0.3s ease;
        }
        
        .image-preview:hover {
            transform: scale(1.1);
        }
        
        /* === FILTER ACTIONS === */
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
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
                width: 100%;
                margin-left: 0;
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
            }
            
            .header h1 {
                font-size: 1.2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-card h3 {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
            
            .filter-section {
                padding: 15px;
            }
            
            .filter-form {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-group {
                width: 100%;
                min-width: 100%;
            }
            
            .filter-actions {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }
            
            .filter-btn,
            .reset-btn,
            .export-btn {
                width: 100%;
                justify-content: center;
                padding: 12px;
            }
            
            .stats-mini {
                grid-template-columns: 1fr;
            }
            
            /* Table responsive improvements */
            .table-container {
                position: relative;
                z-index: 1;
                margin: 0 -15px;
                padding: 0;
                overflow: hidden;
            }
            
            .table-container > div:last-child,
            .rw-table > div:last-child {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding: 0 15px;
            }
            
            .table-container table,
            .rw-table table {
                font-size: 0.85rem;
                min-width: 800px;
                width: 100%;
            }
            
            .table-container th,
            .table-container td,
            .rw-table th,
            .rw-table td {
                padding: 10px 8px;
                white-space: nowrap;
                font-size: 0.85rem;
            }
            
            .table-container th,
            .rw-table th {
                font-size: 0.8rem;
                padding: 12px 8px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
                color: white !important;
            }
            
            .rw-table {
                position: relative;
                z-index: 1;
                margin: 0 -15px;
                padding: 0;
                overflow: hidden;
            }
            
            .detail-content {
                padding: 15px;
            }
            
            .detail-table {
                font-size: 0.85rem;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .image-preview {
                width: 50px;
                height: 50px;
            }
            
            .badge {
                font-size: 0.75rem;
                padding: 3px 8px;
            }
            
            .expand-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .change-password-btn,
            .logout-btn {
                width: 100%;
                text-align: center;
                padding: 10px;
            }
            
            .header > div {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .header > div a {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .stat-card h3 {
                font-size: 1.5rem;
            }
            
            .stat-card p {
                font-size: 0.85rem;
            }
            
            .stat-mini-card p {
                font-size: 24px;
            }
            
            .table-container {
                margin: 0 -10px;
                padding: 0 10px;
            }
            
            .table-container table,
            .rw-table table {
                font-size: 0.75rem;
                min-width: 700px;
            }
            
            .table-container th,
            .table-container td,
            .rw-table th,
            .rw-table td {
                padding: 8px 6px;
                font-size: 0.75rem;
            }
            
            .table-container th,
            .rw-table th {
                font-size: 0.7rem;
                padding: 10px 6px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
                color: white !important;
            }
            
            .content {
                padding: 10px;
                padding-top: 70px;
            }
            
            .rw-table {
                margin: 0 -10px;
                padding: 0 10px;
            }
            
            /* Form improvements */
            input, select {
                font-size: 16px !important; /* Prevent zoom on iOS */
            }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <h2>🦟 Jumantik</h2>
        <ul>
            <li><a href="dashboard-admin.php" class="active">📊 Dashboard</a></li>
            <li><a href="data-kader.php">👩‍💼 Data Kader</a></li>
            <li><a href="data-rw.php">🏘️ Data RW</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <!-- Main content -->
    <main class="content">
        <div class="header">
            <h1>📊 Dashboard Admin - Jumantik Pondok Kopi</h1>
            <div style="display: flex; gap: 10px;">
                <a href="ganti-password.php" class="change-password-btn">🔑 Ganti Password</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="welcome" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; margin-bottom: 30px;">
            <h2 style="color: white; margin: 0 0 10px 0;">Selamat Datang, <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>! 👋</h2>
            <p style="color: rgba(255,255,255,0.9); margin: 0;">Sistem Pemantauan Jentik Nyamuk - Kelurahan Pondok Kopi</p>
        </div>

        <!-- === REKAPITULASI PER TATANAN (PER RW) === -->
         
<?php if (!empty($data_tatanan_rw)): ?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <h3 class="section-title">📊 Rekapitulasi Per Tatanan - Semua RW</h3>
    <a href="export-rekapitulasi-admin.php?<?= http_build_query($_GET); ?>" class="export-btn" style="background:#10b981;">
        📊 Export Rekapitulasi
    </a>
</div>

<div class="table-container">
    <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; position: relative;">
        <table>
            <thead>
                <tr>
                    <th rowspan="2">RW</th>
                    <th colspan="2">Pemukiman</th>
                    <th colspan="2">Institusi Pendidikan</th>
                    <th colspan="2">Tempat Kerja</th>
                    <th colspan="2">TTU</th>
                    <th colspan="2">TPM</th>
                    <th colspan="2">Sarana Olahraga</th>
                    <th colspan="2">Sarana Kesehatan</th>
                    <th colspan="3">Jumlah</th>
                    <th rowspan="2">ABJ (%)</th>
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
                $grand_total_diperiksa = 0;
                $grand_total_jentik = 0;
                $tatanan_list = ['Pemukiman', 'Institusi Pendidikan', 'Tempat Kerja', 'TTU', 'TPM', 'Sarana Olahraga', 'Sarana Kesehatan'];
                
                // Array untuk menyimpan total per tatanan
                $tatanan_totals = [];
                foreach ($tatanan_list as $tatanan) {
                    $tatanan_totals[$tatanan] = ['diperiksa' => 0, 'jentik' => 0];
                }
                
                foreach ($data_tatanan_rw as $rw => $data):
                    $rw_total_diperiksa = 0;
                    $rw_total_jentik = 0;
                    
                    foreach ($tatanan_list as $tatanan) {
                        $rw_total_diperiksa += $data[$tatanan]['diperiksa'];
                        $rw_total_jentik += $data[$tatanan]['jentik'];
                        
                        // Tambahkan ke grand total per tatanan
                        $tatanan_totals[$tatanan]['diperiksa'] += $data[$tatanan]['diperiksa'];
                        $tatanan_totals[$tatanan]['jentik'] += $data[$tatanan]['jentik'];
                    }
                    
                    $rw_total_bebas = $rw_total_diperiksa - $rw_total_jentik;
                    $rw_abj = $rw_total_diperiksa > 0 ? round(($rw_total_bebas / $rw_total_diperiksa) * 100, 2) : 0;
                    
                    $grand_total_diperiksa += $rw_total_diperiksa;
                    $grand_total_jentik += $rw_total_jentik;
                ?>
                <tr>
                    <td><strong>RW <?= htmlspecialchars($rw); ?></strong></td>
                    <?php foreach ($tatanan_list as $tatanan): ?>
                    <td><?= $data[$tatanan]['diperiksa']; ?></td>
                    <td><?= $data[$tatanan]['jentik']; ?></td>
                    <?php endforeach; ?>
                    <td><strong><?= $rw_total_diperiksa; ?></strong></td>
                    <td><strong><?= $rw_total_jentik; ?></strong></td>
                    <td><strong><?= $rw_total_bebas; ?></strong></td>
                    <td><strong><?= $rw_abj; ?>%</strong></td>
                </tr>
                <?php endforeach; 
                
                $grand_total_bebas = $grand_total_diperiksa - $grand_total_jentik;
                $grand_abj = $grand_total_diperiksa > 0 ? round(($grand_total_bebas / $grand_total_diperiksa) * 100, 2) : 0;
                ?>
                <tr style="background: #f1f5f9; font-weight: bold;">
                    <td>TOTAL SEMUA RW</td>
                    <?php foreach ($tatanan_list as $tatanan): ?>
                    <td><?= $tatanan_totals[$tatanan]['diperiksa']; ?></td>
                    <td><?= $tatanan_totals[$tatanan]['jentik']; ?></td>
                    <?php endforeach; ?>
                    <td><?= $grand_total_diperiksa; ?></td>
                    <td><?= $grand_total_jentik; ?></td>
                    <td><?= $grand_total_bebas; ?></td>
                    <td><?= $grand_abj; ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

        <!-- === FILTER & TABEL LAPORAN DETAIL === -->
        <div id="laporan-detail-section">
            <h3 class="section-title">📋 Laporan Pemeriksaan Detail</h3>
            
            <div class="filter-section">
                <form method="GET" class="filter-form" id="filterForm" action="#laporan-detail-section">
                <div class="filter-group">
                    <label>📅 Periode:</label>
                    <select name="periode" id="periode" onchange="togglePeriodeFields()">
                        <option value="">-- Semua Periode --</option>
                        <option value="minggu" <?= $filter_periode == 'minggu' ? 'selected' : ''; ?>>Per Minggu</option>
                        <option value="bulan" <?= $filter_periode == 'bulan' ? 'selected' : ''; ?>>Per Bulan</option>
                    </select>
                </div>
                
                <div class="filter-group" id="field_minggu" style="display: none;">
                    <label>📆 Minggu & Bulan:</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="minggu" style="flex: 1;">
                            <option value="">-- Pilih Minggu --</option>
                            <?php for($i=1; $i<=6; $i++): ?>
                            <option value="<?= $i; ?>" <?= $filter_minggu == $i ? 'selected' : ''; ?>>Minggu <?= $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="bulan_n" style="flex: 1;">
                            <option value="">-- Pilih Bulan --</option>
                            <?php
                            $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                            for($m=1;$m<=12;$m++):
                                $val = str_pad($m,2,'0',STR_PAD_LEFT);
                            ?>
                                <option value="<?= $m; ?>" <?= $filter_bulan_n == $m ? 'selected' : ''; ?>><?= $months[$m-1]; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-group" id="field_bulan" style="display: none;">
                    <label>📆 Bulan:</label>
                    <input type="month" name="bulan" value="<?= htmlspecialchars($filter_bulan); ?>">
                </div>
                
                <div class="filter-group">
                    <label>🏘️ Filter RW:</label>
                    <select name="rw" id="filter_rw">
                        <option value="">-- Semua RW --</option>
                        <?php 
                        mysqli_data_seek($rw_list, 0);
                        while ($rw_opt = mysqli_fetch_assoc($rw_list)): 
                        ?>
                        <option value="<?= $rw_opt['username']; ?>" <?= $selected_rw == $rw_opt['username'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($rw_opt['username']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Filter RT dihapus sesuai permintaan -->
                
                <div class="filter-actions">
                    <button type="submit" class="filter-btn">🔍 Terapkan Filter</button>
                    <a href="dashboard-admin.php" class="reset-btn">🔄 Reset</a>
                </div>
            </form>
        </div>
    <div class="table-container">
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; position: relative;">
        <table id="dataTable">
            <thead>
                <tr>
                    <th>NO</th>
                    <th>MINGGU</th>
                    <th>KADER</th>
                    <th>RW</th>
                    <th>RT</th>
                    <th>TATANAN</th>
                    <th>CATATAN</th>
                    <th>GAMBAR</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (!empty($data_laporan)):
                    $no_lap = 1; 
                    foreach($data_laporan as $row): 
                        $rw_display = !empty($row['rw']) ? $row['rw'] : $row['kader_rw'];
                ?>
                <tr>
                    <td><?= $no_lap++; ?></td>
                    <td><?= $row['tanggal_pemeriksaan'] ? ('Minggu ' . max(1, min(6, intval(ceil(intval(date('d', strtotime($row['tanggal_pemeriksaan']))) / 7))))) : '-'; ?></td>
                    <td><?= htmlspecialchars($row['nama_kader'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($rw_display ?? '-'); ?></td>
                    <td><?= htmlspecialchars($row['rt'] ?? '-'); ?></td>
                    <?php
                        // Parse catatan JSON jika ada (struktur rekap tatanan)
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
                    ?>
                    <td style="text-align:left;">
                        <?php if ($parsed): ?>
                            <?php foreach ($parsed as $tn => $vals): ?>
                                <div style="margin-bottom:4px;">
                                    <strong><?= htmlspecialchars($tn); ?>:</strong>
                                    Diperiksa <?= intval($vals['diperiksa'] ?? 0); ?> / Jentik(+) <?= intval($vals['jentik'] ?? 0); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?= htmlspecialchars($catatan_text ?: ($row['catatan'] ?? '-')); ?>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($catatan_text ?: '-'); ?></td>
                    <td>
                        <?php if (!empty($row['foto_rumah'])): ?>
                            <a href="../backend/uploads/<?= htmlspecialchars($row['foto_rumah']); ?>" target="_blank">
                                <img src="../backend/uploads/<?= htmlspecialchars($row['foto_rumah']); ?>" 
                                     alt="Foto Rumah" 
                                     class="image-preview"
                                     title="Klik untuk memperbesar">
                            </a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.85rem;">Tidak ada foto</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    endforeach;
                else:
                ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                        📊 Tidak ada data laporan<?= !empty($selected_rw) || !empty($selected_rt) || !empty($filter_periode) ? ' sesuai filter' : ''; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
        </div> <!-- End laporan-detail-section -->
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
        });        function toggleDetail(rw) {
            const detailRow = document.getElementById('detail-' + rw);
            detailRow.classList.toggle('show');
        }        function togglePeriodeFields() {
            const periode = document.getElementById('periode').value;
            
            // Hide all period fields
            document.getElementById('field_minggu').style.display = 'none';
            document.getElementById('field_bulan').style.display = 'none';
            
            // Show selected period field
            if (periode === 'minggu') {
                // Field minggu now includes both minggu and bulan dropdowns
                document.getElementById('field_minggu').style.display = 'block';
                
                // Auto-set month to current month if not already selected
                const bulanSelect = document.querySelector('select[name="bulan_n"]');
                if (bulanSelect && !bulanSelect.value) {
                    const today = new Date();
                    const currentMonth = today.getMonth() + 1; // getMonth() returns 0-11
                    bulanSelect.value = currentMonth;
                }
            } else if (periode === 'bulan') {
                document.getElementById('field_bulan').style.display = 'block';
            }
        }

        // Initialize period fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            togglePeriodeFields();
            
            // Load RT list if RW is already selected
            const rwSelect = document.getElementById('filter_rw');
            if (rwSelect && rwSelect.value) {
                loadRTList();
            }
            
            // Scroll ke section laporan detail setelah submit filter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.toString()) {
                // Delay sedikit untuk memastikan DOM sudah render
                setTimeout(function() {
                    const section = document.getElementById('laporan-detail-section');
                    if (section) {
                        // Scroll ke section dengan offset untuk header
                        const headerOffset = 100;
                        const elementPosition = section.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                }, 300);
            }
            
            // Handle form submit untuk memastikan scroll ke section
            const filterForm = document.getElementById('filterForm');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    // Set hash di URL untuk jump ke section setelah reload
                    if (!window.location.hash || window.location.hash !== '#laporan-detail-section') {
                        // Simpan hash di sessionStorage untuk digunakan setelah reload
                        sessionStorage.setItem('scrollToSection', 'laporan-detail-section');
                    }
                });
            }
            
            // Check sessionStorage untuk scroll setelah reload
            if (sessionStorage.getItem('scrollToSection') === 'laporan-detail-section') {
                sessionStorage.removeItem('scrollToSection');
                setTimeout(function() {
                    const section = document.getElementById('laporan-detail-section');
                    if (section) {
                        const headerOffset = 100;
                        const elementPosition = section.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                }, 500);
            }
            
            // Ensure tables are scrollable on mobile
            const tableContainers = document.querySelectorAll('.table-container, .rw-table');
            tableContainers.forEach(container => {
                if (window.innerWidth <= 768) {
                    container.style.position = 'relative';
                }
            });
        });

        // RT filter removed; no dynamic RT loading required
</script>
</body>
</html>