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

// === FUNGSI FORMAT TANGGAL INDONESIA ===
if (!function_exists('formatDateIndonesia')) {
    function formatDateIndonesia($date) {
        if ($date instanceof DateTime) {
            $dt = $date;
        } else {
            $dt = new DateTime($date);
        }
        
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        $day = $days[$dt->format('w')];
        $date_num = $dt->format('d');
        $month = $months[$dt->format('n') - 1];
        $year = $dt->format('Y');
        
        return "$day, $date_num $month $year";
    }
}

// Ambil parameter dari URL
$periode = $_GET['periode'] ?? '';
$minggu = $_GET['minggu'] ?? '';
$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? '';
$selected_rw = $_GET['rw'] ?? '';

// Tentukan periode filter dan label
$periode_label = '';
$periode_filter_date = null;
$filter_bulan_n = isset($_GET['bulan_n']) ? intval($_GET['bulan_n']) : 0;
$filter_minggu = isset($_GET['minggu']) ? intval($_GET['minggu']) : 0;

// Jika mode minggu tapi bulan_n tidak ada, gunakan bulan saat ini
if ($periode == 'minggu' && $filter_minggu > 0 && $filter_bulan_n == 0) {
    $filter_bulan_n = intval(date('n'));
}

$minggu_display = '-';
$tanggal_display = formatDateIndonesia(new DateTime());

if ($periode == 'minggu' && $filter_minggu > 0 && $filter_bulan_n > 0) {
    // Mode mingguan - tampilkan minggu
    $year_to_use = !empty($tahun) ? intval($tahun) : intval(date('Y'));
    $query_rekapitulasi .= " AND (FLOOR((DAY(dj.tanggal_pemeriksaan)-1)/7)+1) = " . intval($filter_minggu);
    $query_rekapitulasi .= " AND MONTH(dj.tanggal_pemeriksaan) = " . intval($filter_bulan_n);
    $query_rekapitulasi .= " AND YEAR(dj.tanggal_pemeriksaan) = " . $year_to_use;
    
    $minggu_display = $filter_minggu;
    
    // Set tanggal_display untuk range minggu
    try {
        $dto = new DateTime();
        $dto->setISODate($year_to_use, $filter_minggu);
        $tanggal_display = formatDateIndonesia($dto);
        
        $dto_display = new DateTime();
        $dto_display->setISODate($year_to_use, $filter_minggu);
        $periode_label = $dto_display->format('d M Y') . ' — ';
        $dto_display->modify('+6 days');
        $periode_label .= $dto_display->format('d M Y');
    } catch (Exception $e) {
        $periode_label = 'Minggu ' . $filter_minggu;
    }
} elseif ($periode == 'bulan' && !empty($bulan)) {
    // Mode bulanan
    $query_rekapitulasi .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '" . mysqli_real_escape_string($conn, $bulan) . "'";
    $periode_label = date('F Y', strtotime($bulan . '-01'));
    $minggu_display = '-';
} else {
    // Semua periode
    $periode_label = 'Keseluruhan';
    $minggu_display = '-';
}

// Build base query - Ambil baris Rekap dengan JSON catatan
$query_rekapitulasi = "
    SELECT 
        COALESCE(k.rw, dj.rw) as rw,
        dj.catatan
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE dj.tatanan_diperiksa = 'Rekap'
";

// Add RW filter if selected
if (!empty($selected_rw)) {
    $selected_rw_int = intval(normalizeNumber($selected_rw));
    $query_rekapitulasi .= " AND " . normalizeRwSql("COALESCE(NULLIF(dj.rw, ''), k.rw)") . " = $selected_rw_int";
}

// Add periode filter - sudah ditambahkan di periode determination section di atas

$query_rekapitulasi .= " ORDER BY COALESCE(k.rw, dj.rw) ASC";

$result_rekapitulasi = mysqli_query($conn, $query_rekapitulasi);

if (!$result_rekapitulasi) {
    die("Database Error: " . mysqli_error($conn));
}

// Kelompokkan data per RW dengan parse JSON dari catatan
$data_per_rw = [];
$tatanan_labels = [
    'Pemukiman',
    'Institusi Pendidikan',
    'Tempat Kerja',
    'TTU',
    'TPM',
    'Sarana Olahraga',
    'Sarana Kesehatan'
];

while ($row = mysqli_fetch_assoc($result_rekapitulasi)) {
    $rw = $row['rw'] ?? '-';
    $catatan_json = $row['catatan'] ?? '';
    
    if (!isset($data_per_rw[$rw])) {
        $data_per_rw[$rw] = [
            'Pemukiman' => ['diperiksa' => 0, 'jentik' => 0],
            'Institusi Pendidikan' => ['diperiksa' => 0, 'jentik' => 0],
            'Tempat Kerja' => ['diperiksa' => 0, 'jentik' => 0],
            'TTU' => ['diperiksa' => 0, 'jentik' => 0],
            'TPM' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Olahraga' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Kesehatan' => ['diperiksa' => 0, 'jentik' => 0],
        ];
    }
    
    // Decode JSON catatan dan extract tatanan data
    $decoded = json_decode($catatan_json, true);
    if (is_array($decoded)) {
        foreach ($tatanan_labels as $label) {
            if (!isset($decoded[$label]) || !is_array($decoded[$label])) continue;
            $diperiksa = (int)($decoded[$label]['diperiksa'] ?? 0);
            $jentik = (int)($decoded[$label]['jentik'] ?? 0);

            $data_per_rw[$rw][$label]['diperiksa'] += $diperiksa;
            $data_per_rw[$rw][$label]['jentik'] += $jentik;
        }
    }
}

// Hitung grand total
$grand_total_diperiksa = 0;
$grand_total_jentik = 0;
$tatanan_list = ['Pemukiman', 'Institusi Pendidikan', 'Tempat Kerja', 'TTU', 'TPM', 'Sarana Olahraga', 'Sarana Kesehatan'];

foreach ($data_per_rw as $rw => $data) {
    foreach ($tatanan_list as $tatanan) {
        $grand_total_diperiksa += $data[$tatanan]['diperiksa'];
        $grand_total_jentik += $data[$tatanan]['jentik'];
    }
}

$grand_total_bebas = $grand_total_diperiksa - $grand_total_jentik;
$grand_abj = $grand_total_diperiksa > 0 ? round(($grand_total_bebas / $grand_total_diperiksa) * 100, 2) : 0;

// Set header untuk download Excel
$rw_label = !empty($selected_rw) ? 'RW_' . $selected_rw : 'Semua_RW';
$periode_label_file = str_replace(' ', '_', str_replace('—', '', $periode_label));
$filename = "Rekapitulasi_" . $rw_label . "_" . $periode_label_file . "_" . date('Y-m-d_His') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        * { margin: 0; padding: 0; } 
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        table { 
            border-collapse: collapse; 
            width: 100%;
            margin-bottom: 20px;
        }
        /* Default: no borders for general/layout tables (title/info sections) */
        td, th { 
            border: none;
            padding: 4px; 
            text-align: center;
            vertical-align: middle;
            font-size: 9pt;
        }
        /* Borders applied only to the main data table */
        .data-table, .data-table th, .data-table td {
            border: 1px solid #000000;
        }
        .data-table th, .data-table td {
            padding: 4px; 
            text-align: center;
            vertical-align: middle;
            font-size: 9pt;
        }
        .data-table th { 
            background-color: #FFFFFF; 
            color: #000000; 
            font-weight: bold; 
        }
        .header-section {
            border: none;
            text-align: left;
            padding: 4px;
            font-weight: normal;
        }
        .title { 
            font-size: 12pt; 
            font-weight: bold; 
            text-align: center; 
        }
        .subtitle {
            font-size: 11pt;
            text-align: center;
        }
        .total-row { 
            background-color: #D9E1F2; 
            font-weight: bold; 
        }
        .info-text {
            font-size: 8pt;
            margin-top: 10px;
            padding: 5px;
        }
        .signature-section {
            margin-top: 30px;
            font-size: 9pt;
        }
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="20" class="title">Rekapitulasi Kegiatan Pemantauan Jentik di 7 (Tujuh) Tatanan</td>
    </tr>
    <tr>
        <td colspan="20" class="subtitle">Tingkat RW Kelurahan Pondok Kopi Kecamatan Duren Sawit</td>
    </tr>
</table>

<table style="margin-bottom: 10px;">
    <tr>
        <td style="width: 50%; padding: 4px; font-size: 9pt; text-align: left;">Kelurahan : Pondok Kopi</td>
        <td style="width: 50%; padding: 4px;"></td>
    </tr>
    <tr>
        <td style="width: 50%; padding: 4px; font-size: 9pt; text-align: left;">Kecamatan : Duren Sawit</td>
        <td style="width: 50%; padding: 4px;"></td>
    </tr>
    <tr>
        <td style="width: 50%; padding: 4px; font-size: 9pt; text-align: left;">Hari/Tanggal : <?= htmlspecialchars($tanggal_display); ?></td>
        <td style="width: 50%; padding: 4px; text-align: right; font-size: 9pt;">Minggu Ke : <?= htmlspecialchars($minggu_display); ?></td>
    </tr>
</table>

<table class="data-table">
    <tr>
        <th colspan="20" style="background-color: #FFFFFF; border: none; color: #000000; padding: 4px;">Tatanan Diperiksa</th>
    </tr>
    <thead>
        <tr>
            <th rowspan="2" style="width: 40px;">No</th>
            <th rowspan="2" style="width: 60px;">RW</th>
            <th colspan="2">Pemukiman</th>
            <th colspan="2">Institusi Pendidikan</th>
            <th colspan="2">Tempat Kerja</th>
            <th colspan="2">TTU</th>
            <th colspan="2">TPM</th>
            <th colspan="2">Sarana Olahraga</th>
            <th colspan="2">Sarana Kesehatan</th>
            <th colspan="3">Jumlah</th>
            <th rowspan="2" style="width: 60px;">ABJ</th>
        </tr>
        <tr>
            <th style="width: 50px;">Di periksa</th>
            <th style="width: 50px;">Jentik (+)</th>
            <th style="width: 50px;">Di periksa</th>
            <th style="width: 50px;">Jentik (+)</th>
            <th style="width: 50px;">Di periksa</th>
            <th style="width: 50px;">Jentik (+)</th>
            <th style="width: 50px;">Di periksa</th>
            <th style="width: 50px;">Jentik (+)</th>
            <th style="width: 50px;">Di periksa</th>
            <th style="width: 50px;">Jentik (+)</th>
            <th style="width: 50px;">Di periksa</th>
            <th style="width: 50px;">Jentik (+)</th>
            <th style="width: 50px;">Di periksa</th>
            <th style="width: 50px;">Jentik (+)</th>
            <th style="width: 60px;">Jumlah Total Pemeriks</th>
            <th style="width: 60px;">Jumlah Total Jentik (+)</th>
            <th style="width: 60px;">Jumlah Total Jentik (-)</th>
        </tr>
    </thead>
    <tbody>
<?php
$no = 1;
foreach ($data_per_rw as $rw => $data):
    $rw_total_diperiksa = 0;
    $rw_total_jentik = 0;
    
    // Calculate RW totals
    foreach ($tatanan_list as $tatanan) {
        $rw_total_diperiksa += $data[$tatanan]['diperiksa'];
        $rw_total_jentik += $data[$tatanan]['jentik'];
    }
    $rw_total_bebas = $rw_total_diperiksa - $rw_total_jentik;
    $rw_abj = $rw_total_diperiksa > 0 ? round(($rw_total_bebas / $rw_total_diperiksa) * 100, 2) : 0;
?>
        <tr>
            <td><?= $no; ?></td>
            <td><?= htmlspecialchars($rw); ?></td>
            <td><?= $data['Pemukiman']['diperiksa']; ?></td>
            <td><?= $data['Pemukiman']['jentik']; ?></td>
            <td><?= $data['Institusi Pendidikan']['diperiksa']; ?></td>
            <td><?= $data['Institusi Pendidikan']['jentik']; ?></td>
            <td><?= $data['Tempat Kerja']['diperiksa']; ?></td>
            <td><?= $data['Tempat Kerja']['jentik']; ?></td>
            <td><?= $data['TTU']['diperiksa']; ?></td>
            <td><?= $data['TTU']['jentik']; ?></td>
            <td><?= $data['TPM']['diperiksa']; ?></td>
            <td><?= $data['TPM']['jentik']; ?></td>
            <td><?= $data['Sarana Olahraga']['diperiksa']; ?></td>
            <td><?= $data['Sarana Olahraga']['jentik']; ?></td>
            <td><?= $data['Sarana Kesehatan']['diperiksa']; ?></td>
            <td><?= $data['Sarana Kesehatan']['jentik']; ?></td>
            <td><?= $rw_total_diperiksa; ?></td>
            <td><?= $rw_total_jentik; ?></td>
            <td><?= $rw_total_bebas; ?></td>
            <td><?= $rw_abj; ?>%</td>
        </tr>
<?php 
    $no++;
endforeach; 
?>
        <tr class="total-row">
            <td colspan="2">Jumlah</td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Pemukiman'), 'diperiksa')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Pemukiman'), 'jentik')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Institusi Pendidikan'), 'diperiksa')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Institusi Pendidikan'), 'jentik')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Tempat Kerja'), 'diperiksa')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Tempat Kerja'), 'jentik')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'TTU'), 'diperiksa')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'TTU'), 'jentik')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'TPM'), 'diperiksa')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'TPM'), 'jentik')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Sarana Olahraga'), 'diperiksa')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Sarana Olahraga'), 'jentik')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Sarana Kesehatan'), 'diperiksa')); ?></td>
            <td><?= array_sum(array_column(array_column($data_per_rw, 'Sarana Kesehatan'), 'jentik')); ?></td>
            <td><?= $grand_total_diperiksa; ?></td>
            <td><?= $grand_total_jentik; ?></td>
            <td><?= $grand_total_bebas; ?></td>
            <td><?= $grand_abj; ?>%</td>
        </tr>
    </tbody>
</table>

<div style="margin-top: 40px; font-size: 9pt;">
    <table style="border: none;">
        <tr style="border: none;">
            <td style="border: none; width: 50%; text-align: center;">
                Jakarta, <?= date('d F Y'); ?><br>
                <br><br><br>
                Mengetahui,<br>
                Lurah Kelurahan Pondok Kopi<br>
                <br><br><br>
                _______________________
            </td>
            <td style="border: none; width: 50%; text-align: center;">
                <br><br><br>
                Korwil<br>
                Kepala Pemantau Jumantik<br>
                <br><br><br>
                _______________________
            </td>
        </tr>
    </table>
</div>

</body>
</html>
<?php
mysqli_close($conn);
?>
