<?php
session_start();
require_once 'config.php';

// Cek autentikasi RW
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'rw') {
    header("Location: login.php");
    exit;
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
mysqli_stmt_close($stmt);

$nama_rw = $data_rw['nama_rw'] ?? 'RW';
$nomor_rw = $data_rw['nomor_rw'] ?? $data_rw['username'] ?? '-';
$rw_id = $data_rw['id'] ?? 0;

// Extract nomor RW sebagai angka
$nomor_rw_filter = preg_replace('/[^0-9]/', '', (string)$nomor_rw);
$nomor_rw_filter_int = !empty($nomor_rw_filter) ? intval($nomor_rw_filter) : null;

// Ambil parameter dari URL
$periode = $_GET['periode'] ?? 'mingguan';
$minggu = isset($_GET['minggu']) ? $_GET['minggu'] : '';
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';

// backward compatibility: if month+year provided separately
if (isset($_GET['month']) && isset($_GET['year'])) {
    $y = intval($_GET['year']);
    $m = intval($_GET['month']);
    if ($y > 0 && $m >= 1 && $m <= 12) {
        $bulan = sprintf('%04d-%02d', $y, $m);
    }
}

// if minggu_choice provided in format YYYY-MM|N
if (isset($_GET['minggu_choice']) && strpos($_GET['minggu_choice'], '|') !== false) {
    list($g_bulan, $g_minggu) = explode('|', $_GET['minggu_choice'], 2);
    $bulan = $g_bulan;
    $minggu = intval($g_minggu);
}

// Tentukan periode filter
$periode_label = '';
$periode_filter_date = null;

if ($periode == 'mingguan' && !empty($minggu) && !empty($bulan)) {
    // Minggu berdasarkan minggu-ke dalam bulan (1..6)
    $parts = explode('-', $bulan);
    if (count($parts) == 2) {
        $year = intval($parts[0]);
        $month = intval($parts[1]);
        $minggu_n = intval($minggu);
        if ($minggu_n < 1) $minggu_n = 1; if ($minggu_n > 6) $minggu_n = 6;

        $start_day = ($minggu_n - 1) * 7 + 1;
        $last_day = intval(date('t', strtotime(sprintf('%04d-%02d-01', $year, $month))));
        $end_day = min($minggu_n * 7, $last_day);

        $start_date = sprintf('%04d-%02d-%02d', $year, $month, $start_day);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, $end_day);
        $periode_filter_date = $start_date . ',' . $end_date;
        $periode_label = date('d M Y', strtotime($start_date)) . ' — ' . date('d M Y', strtotime($end_date));
    }
} else {
    $bulan_filter = $bulan ?? date('Y-m');
    $periode_label = date('F Y', strtotime($bulan_filter . '-01'));
}

// Query untuk rekapitulasi - ambil baris dengan tatanan_diperiksa = 'Rekap' (berisi JSON agregasi)
$query_tatanan = "
    SELECT 
        dj.rt,
        dj.catatan,
        k.nama_kader
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE CAST(REGEXP_SUBSTR(dj.rw, '[0-9]+') AS UNSIGNED) = ?
      AND dj.tatanan_diperiksa = 'Rekap'
";

if ($periode_filter_date) {
    list($start_date, $end_date) = explode(',', $periode_filter_date);
    $query_tatanan .= " AND dj.tanggal_pemeriksaan BETWEEN ? AND ?";
    $stmt_tatanan = mysqli_prepare($conn, $query_tatanan . " ORDER BY dj.rt ASC");
    mysqli_stmt_bind_param($stmt_tatanan, "iss", $nomor_rw_filter_int, $start_date, $end_date);
} else {
    $bulan_filter = $bulan ?? date('Y-m');
    $query_tatanan .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?";
    $stmt_tatanan = mysqli_prepare($conn, $query_tatanan . " ORDER BY dj.rt ASC");
    mysqli_stmt_bind_param($stmt_tatanan, "is", $nomor_rw_filter_int, $bulan_filter);
}

mysqli_stmt_execute($stmt_tatanan);
$result_tatanan = mysqli_stmt_get_result($stmt_tatanan);

// Kelompokkan data per RT berdasarkan JSON catatan
$data_per_rt = [];
$tatanan_labels = [
    'Pemukiman',
    'Institusi Pendidikan',
    'Tempat Kerja',
    'TTU',
    'TPM',
    'Sarana Olahraga',
    'Sarana Kesehatan'
];

while ($row = mysqli_fetch_assoc($result_tatanan)) {
    $rt = $row['rt'] ?? '-';
    $nama_kader_row = $row['nama_kader'] ?? null;
    $catatan_json = $row['catatan'] ?? '';

    if (!isset($data_per_rt[$rt])) {
        $data_per_rt[$rt] = [
            'nama_kader' => $nama_kader_row,
            'Pemukiman' => ['diperiksa' => 0, 'jentik' => 0],
            'Institusi Pendidikan' => ['diperiksa' => 0, 'jentik' => 0],
            'Tempat Kerja' => ['diperiksa' => 0, 'jentik' => 0],
            'TTU' => ['diperiksa' => 0, 'jentik' => 0],
            'TPM' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Olahraga' => ['diperiksa' => 0, 'jentik' => 0],
            'Sarana Kesehatan' => ['diperiksa' => 0, 'jentik' => 0],
        ];
    } elseif (empty($data_per_rt[$rt]['nama_kader']) && $nama_kader_row) {
        $data_per_rt[$rt]['nama_kader'] = $nama_kader_row;
    }

    // Decode JSON catatan: kunci tatanan berisi {diperiksa, jentik}
    $decoded = json_decode($catatan_json, true);
    if (is_array($decoded)) {
        foreach ($tatanan_labels as $label) {
            if (!isset($decoded[$label]) || !is_array($decoded[$label])) continue;
            $diperiksa = (int)($decoded[$label]['diperiksa'] ?? 0);
            $jentik = (int)($decoded[$label]['jentik'] ?? 0);

            $data_per_rt[$rt][$label]['diperiksa'] += $diperiksa;
            $data_per_rt[$rt][$label]['jentik'] += $jentik;
        }
    }
}

mysqli_stmt_close($stmt_tatanan);

// Hitung grand total
$grand_total_diperiksa = 0;
$grand_total_jentik = 0;

foreach ($data_per_rt as $rt => $data) {
    foreach (['Pemukiman', 'Institusi Pendidikan', 'Tempat Kerja', 'TTU', 'TPM', 'Sarana Olahraga', 'Sarana Kesehatan'] as $tatanan) {
        $grand_total_diperiksa += $data[$tatanan]['diperiksa'];
        $grand_total_jentik += $data[$tatanan]['jentik'];
    }
}

$grand_total_bebas = $grand_total_diperiksa - $grand_total_jentik;
$grand_abj = $grand_total_diperiksa > 0 ? round(($grand_total_bebas / $grand_total_diperiksa) * 100, 2) : 0;

// Set header untuk download Excel
$filename = "Rekapitulasi_RW_" . $nomor_rw_filter . "_" . date('Y-m-d_His') . ".xls";

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
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        table { 
            border-collapse: collapse; 
            width: 100%;
            margin-bottom: 20px;
        }
        td, th { 
            border: 1px solid #000000;
            padding: 8px; 
            text-align: center;
            vertical-align: middle;
        }
        th { 
            background-color: #3B82F6; 
            color: white; 
            font-weight: bold; 
        }
        .header-title { 
            font-size: 14pt; 
            font-weight: bold; 
            text-align: center; 
            padding: 12px; 
        }
        .header-subtitle { 
            font-size: 11pt; 
            text-align: center; 
            padding: 8px; 
        }
        .total-row { 
            background-color: #D9E1F2; 
            font-weight: bold; 
        }
        .rt-col { text-align: center; font-weight: bold; }
        .nama-col { text-align: left; }
        .info-text {
            font-size: 9pt;
            margin-top: 15px;
            padding: 10px;
        }
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="20" class="header-title">📊 REKAPITULASI KEGIATAN PEMANTAUAN JENTIK DI 7 (Tujuh) TATANAN</td>
    </tr>
    <tr>
        <td colspan="20" class="header-subtitle">Tingkat RW <?= htmlspecialchars($nomor_rw_filter); ?></td>
    </tr>
</table>

<table>
    <tr>
        <td style="width:200px;">Kelurahan</td>
        <td style="width:10px;">:</td>
        <td style="width:400px;">Pondok Kopi</td>
        <td style="width:120px;">Hari/Tanggal</td>
        <td style="width:10px;">:</td>
        <td><?= date('d M Y'); ?></td>
    </tr>
    <tr>
        <td>Kecamatan</td>
        <td>:</td>
        <td>Duren Sawit</td>
        <td>Minggu Ke</td>
        <td>:</td>
        <td><?php if (!empty($minggu) && !empty($bulan)) { echo intval($minggu); } else { echo '-'; } ?></td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th rowspan="2" style="width: 50px;">RT</th>
            <th rowspan="2" style="width: 150px;">Nama Jumantik</th>
            <th colspan="2">Pemukiman</th>
            <th colspan="2">Institusi Pendidikan</th>
            <th colspan="2">Tempat Kerja</th>
            <th colspan="2">TTU</th>
            <th colspan="2">TPM</th>
            <th colspan="2">Sarana Olahraga</th>
            <th colspan="2">Sarana Kesehatan</th>
            <th colspan="3">Jumlah</th>
            <th rowspan="2" style="width: 80px;">ABJ (%)</th>
        </tr>
        <tr>
            <th style="width: 60px;">Diperiksa</th>
            <th style="width: 60px;">Jentik (+)</th>
            <th style="width: 60px;">Diperiksa</th>
            <th style="width: 60px;">Jentik (+)</th>
            <th style="width: 60px;">Diperiksa</th>
            <th style="width: 60px;">Jentik (+)</th>
            <th style="width: 60px;">Diperiksa</th>
            <th style="width: 60px;">Jentik (+)</th>
            <th style="width: 60px;">Diperiksa</th>
            <th style="width: 60px;">Jentik (+)</th>
            <th style="width: 60px;">Diperiksa</th>
            <th style="width: 60px;">Jentik (+)</th>
            <th style="width: 60px;">Diperiksa</th>
            <th style="width: 60px;">Jentik (+)</th>
            <th style="width: 70px;">Total Diperiksa</th>
            <th style="width: 70px;">Total Jentik</th>
            <th style="width: 70px;">Total Bebas</th>
        </tr>
    </thead>
    <tbody>
<?php
$tatanan_list = ['Pemukiman', 'Institusi Pendidikan', 'Tempat Kerja', 'TTU', 'TPM', 'Sarana Olahraga', 'Sarana Kesehatan'];

foreach ($data_per_rt as $rt => $data):
    $total_diperiksa = 0;
    $total_jentik = 0;
    
    foreach ($tatanan_list as $tatanan) {
        $total_diperiksa += $data[$tatanan]['diperiksa'];
        $total_jentik += $data[$tatanan]['jentik'];
    }
    
    $total_bebas = $total_diperiksa - $total_jentik;
    $abj = $total_diperiksa > 0 ? round(($total_bebas / $total_diperiksa) * 100, 2) : 0;
?>
        <tr>
            <td class="rt-col"><?= htmlspecialchars($rt); ?></td>
            <td class="nama-col"><?= htmlspecialchars($data['nama_kader'] ?? '-'); ?></td>
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
            <td style="font-weight: bold;"><?= $total_diperiksa; ?></td>
            <td style="font-weight: bold;"><?= $total_jentik; ?></td>
            <td style="font-weight: bold;"><?= $total_bebas; ?></td>
            <td style="font-weight: bold;"><?= $abj; ?></td>
        </tr>
<?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2" style="text-align: center;">TOTAL</td>
<?php 
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
            <td><?= $grand_abj; ?></td>
        </tr>
    </tbody>
</table>

<div class="info-text">
    <strong>Keterangan:</strong><br>
    - Diperiksa: Jumlah rumah/tempat yang diperiksa<br>
    - Jentik (+): Jumlah rumah/tempat yang ditemukan jentik<br>
    - ABJ: Angka Bebas Jentik (persentase rumah bebas jentik)<br>
    - Laporan diunduh pada: <?= date('d M Y H:i:s'); ?><br>
    - Format: MS Excel (.xls)
</div>

</body>
</html>
<?php
mysqli_close($conn);
?>
