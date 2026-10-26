<?php
session_start();
require_once 'config.php';

// Cek autentikasi admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// === FUNGSI NORMALISASI ===
function normalizeNumber($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^0-9]/', '', $value);
    return ltrim($value, '0');
}

function normalizeRwSql($column) {
    return "CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST($column AS CHAR) COLLATE utf8mb4_general_ci), 'rw', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED)";
}

function normalizeRtSql($column) {
    return "CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST($column AS CHAR) COLLATE utf8mb4_general_ci), 'rt', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED)";
}

// === FILTER LAPORAN ===
$selected_rw = $_GET['rw'] ?? '';
$selected_rt = $_GET['rt'] ?? '';
$filter_periode = $_GET['periode'] ?? '';
$filter_minggu = isset($_GET['minggu']) ? intval($_GET['minggu']) : 0;
$filter_bulan = $_GET['bulan'] ?? '';
$filter_bulan_n = isset($_GET['bulan_n']) ? intval($_GET['bulan_n']) : 0;
$filter_tahun = $_GET['tahun'] ?? '';

$selected_rw_int = $selected_rw !== '' ? intval(normalizeNumber($selected_rw)) : null;
$selected_rt_int = $selected_rt !== '' ? intval(normalizeNumber($selected_rt)) : null;

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
        dj.catatan,
        k.nama_kader AS nama_kader,
        k.rw AS kader_rw
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    WHERE 1 = 1
";

// Filter RW
if ($selected_rw_int !== null) {
    $query_laporan .= " AND " . normalizeRwSql("COALESCE(NULLIF(dj.rw, ''), k.rw)") . " = $selected_rw_int";
}

// Filter RT
if ($selected_rt_int !== null) {
    $query_laporan .= " AND " . normalizeRtSql('dj.rt') . " = $selected_rt_int";
}

// Filter Periode
// If weekly selected but no month provided, default to current month
if ($filter_periode == 'minggu' && $filter_minggu > 0 && $filter_bulan_n == 0) {
    $filter_bulan_n = intval(date('n'));
}
if ($filter_periode == 'minggu' && $filter_minggu > 0 && $filter_minggu <= 4 && $filter_bulan_n > 0) {
    $year_to_use = !empty($filter_tahun) ? intval($filter_tahun) : intval(date('Y'));
    $query_laporan .= " AND CEIL(DAY(dj.tanggal_pemeriksaan)/7) = " . intval($filter_minggu);
    $query_laporan .= " AND MONTH(dj.tanggal_pemeriksaan) = " . intval($filter_bulan_n);
    $query_laporan .= " AND YEAR(dj.tanggal_pemeriksaan) = " . $year_to_use;
} elseif ($filter_periode == 'bulan' && !empty($filter_bulan)) {
    $query_laporan .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filter_bulan) . "'";
} elseif ($filter_periode == 'tahun' && !empty($filter_tahun)) {
    $query_laporan .= " AND YEAR(dj.tanggal_pemeriksaan) = " . intval($filter_tahun);
}

$query_laporan .= " ORDER BY dj.tanggal_pemeriksaan DESC, dj.id DESC";

$result = mysqli_query($conn, $query_laporan);

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}

// === GENERATE FILENAME ===
$filename = "Laporan_Jumantik";

if (!empty($filter_periode)) {
    if ($filter_periode == 'minggu' && !empty($filter_minggu)) {
        $year_to_use = !empty($filter_tahun) ? $filter_tahun : date('Y');
        $month_name = $filter_bulan_n ? date('F', mktime(0,0,0,$filter_bulan_n,1)) : '';
        $filename .= "_Minggu{$filter_minggu}" . (!empty($month_name) ? "_{$month_name}" : '') . "_Tahun{$year_to_use}";
    } elseif ($filter_periode == 'bulan' && !empty($filter_bulan)) {
        $filename .= "_" . date('F_Y', strtotime($filter_bulan . '-01'));
    } elseif ($filter_periode == 'tahun' && !empty($filter_tahun)) {
        $filename .= "_Tahun{$filter_tahun}";
    }
}

if (!empty($selected_rw)) {
    $filename .= "_{$selected_rw}";
}

if (!empty($selected_rt)) {
    $filename .= "_{$selected_rt}";
}

$filename .= "_" . date('d-m-Y') . ".xls";

// === HITUNG STATISTIK ===
$total_data = 0;
$jentik_positif = 0;
$jentik_negatif = 0;

$data_array = [];
while($row = mysqli_fetch_assoc($result)) {
    $data_array[] = $row;
    $total_data++;
    if (($row['jentik_ditemukan'] ?? 'Tidak') == 'Ya') {
        $jentik_positif++;
    } else {
        $jentik_negatif++;
    }
}

$persentase_bebas = $total_data > 0 ? round(($jentik_negatif / $total_data) * 100, 1) : 0;

// === BUILD FILTER INFO ===
$filter_info = [];
if (!empty($filter_periode)) {
    if ($filter_periode == 'minggu' && !empty($filter_minggu)) {
        $filter_info[] = "Periode: Minggu $filter_minggu Tahun $filter_tahun";
    } elseif ($filter_periode == 'bulan' && !empty($filter_bulan)) {
        $filter_info[] = "Periode: " . date('F Y', strtotime($filter_bulan . '-01'));
    } elseif ($filter_periode == 'tahun' && !empty($filter_tahun)) {
        $filter_info[] = "Periode: Tahun $filter_tahun";
    }
}
if (!empty($selected_rw)) {
    $filter_info[] = "RW: $selected_rw";
}
if (!empty($selected_rt)) {
    $filter_info[] = "RT: $selected_rt";
}

// Output Excel dengan format HTML sederhana yang kompatibel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 10px 0;
            border: 2px solid #667eea;
        }
        .header-title {
            background-color: #667eea;
            color: white;
            font-weight: bold;
            font-size: 16pt;
            text-align: center;
            padding: 15px;
            border: 2px solid #5568d3;
        }
        .info-row {
            background-color: #f1f5f9;
            padding: 8px;
            font-size: 10pt;
            border: 1px solid #cbd5e1;
        }
        .stats-row {
            background-color: #e0e7ff;
            border: 2px solid #667eea;
        }
        .stats-row td {
            padding: 10px;
            font-weight: bold;
            border: 1px solid #667eea;
            text-align: left;
        }
        th {
            background-color: #667eea;
            color: white;
            font-weight: bold;
            padding: 12px 8px;
            text-align: center;
            border: 2px solid #5568d3;
            font-size: 10pt;
            white-space: nowrap;
        }
        td {
            padding: 8px;
            border: 1px solid #94a3b8;
            text-align: left;
            font-size: 10pt;
            vertical-align: top;
        }
        tbody tr {
            border-bottom: 1px solid #cbd5e1;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center {
            text-align: center;
        }
        .jentik-ya {
            background-color: #fee2e2;
            color: #dc2626;
            font-weight: bold;
            text-align: center;
        }
        .jentik-tidak {
            background-color: #dcfce7;
            color: #16a34a;
            font-weight: bold;
            text-align: center;
        }
        .footer {
            background-color: #f1f5f9;
            text-align: center;
            padding: 10px;
            font-size: 9pt;
            color: #64748b;
        }
    </style>
</head>
<body>

<!-- HEADER TITLE -->
<table>
    <tr>
        <td colspan="10" class="header-title">LAPORAN PEMERIKSAAN JENTIK NYAMUK<br>KELURAHAN PONDOK KOPI</td>
    </tr>
</table>

<!-- INFO & FILTER -->
<table>
    <tr>
        <td class="info-row"><strong>Tanggal Export:</strong> <?= date('d F Y, H:i:s'); ?></td>
    </tr>
    <?php if (!empty($filter_info)): ?>
    <tr>
        <td class="info-row"><strong>Filter:</strong> <?= htmlspecialchars(implode(' | ', $filter_info)); ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td class="info-row"><strong>Admin:</strong> <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?></td>
    </tr>
</table>

<!-- STATISTIK -->
<table class="stats-row">
    <tr>
        <td style="width: 20%;">Total Data:</td>
        <td style="width: 20%; font-size: 12pt; color: #1e293b;"><?= $total_data; ?></td>
        <td style="width: 20%;">Jentik Positif:</td>
        <td style="width: 20%; font-size: 12pt; color: #dc2626;"><?= $jentik_positif; ?></td>
        <td style="width: 20%;"></td>
    </tr>
    <tr>
        <td>Jentik Negatif:</td>
        <td style="font-size: 12pt; color: #16a34a;"><?= $jentik_negatif; ?></td>
        <td>% Bebas Jentik:</td>
        <td style="font-size: 12pt; color: #2563eb;"><?= $persentase_bebas; ?>%</td>
        <td></td>
    </tr>
</table>

<!-- DATA TABLE -->
<table>
    <thead>
        <tr>
            <th style="width: 40px;">NO</th>
            <th style="width: 100px;">TANGGAL</th>
            <th style="width: 150px;">NAMA KADER</th>
            <th style="width: 60px;">RW</th>
            <th style="width: 60px;">RT</th>
            <th style="width: 200px;">NAMA KEPALA KELUARGA</th>
            <th style="width: 150px;">TATANAN</th>
            <th style="width: 250px;">ALAMAT</th>
            <th style="width: 100px;">STATUS JENTIK</th>
            <th style="width: 250px;">CATATAN</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if (!empty($data_array)):
            $no = 1;
            foreach($data_array as $row):
                $rw_display = !empty($row['rw']) ? $row['rw'] : $row['kader_rw'];
                $is_jentik = ($row['jentik_ditemukan'] ?? 'Tidak') === 'Ya';
                $jentik_class = $is_jentik ? 'jentik-ya' : 'jentik-tidak';
                $jentik_text = $is_jentik ? 'ADA JENTIK (+)' : 'TIDAK ADA (-)';
                $tanggal = $row['tanggal_pemeriksaan'] ? date('d/m/Y', strtotime($row['tanggal_pemeriksaan'])) : '-';
        ?>
        <tr>
            <td class="text-center"><?= $no++; ?></td>
            <td><?= htmlspecialchars($tanggal); ?></td>
            <td><?= htmlspecialchars($row['nama_kader'] ?? 'N/A'); ?></td>
            <td class="text-center"><?= htmlspecialchars($rw_display ?? '-'); ?></td>
            <td class="text-center"><?= htmlspecialchars($row['rt'] ?? '-'); ?></td>
            <td><?= htmlspecialchars($row['nama_kepala_keluarga'] ?? '-'); ?></td>
            <td><?= htmlspecialchars($row['tatanan_diperiksa'] ?? '-'); ?></td>
            <td><?= htmlspecialchars($row['alamat'] ?? '-'); ?></td>
            <td class="<?= $jentik_class; ?>"><?= $jentik_text; ?></td>
            <td><?= htmlspecialchars($row['catatan'] ?? '-'); ?></td>
        </tr>
        <?php 
            endforeach;
        else:
        ?>
        <tr>
            <td colspan="10" class="text-center" style="padding: 30px; color: #94a3b8; font-style: italic;">
                Tidak ada data laporan sesuai filter yang dipilih
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- FOOTER -->
<table>
    <tr>
        <td colspan="10" class="footer">
            <strong>Sistem Pemantauan Jentik Nyamuk - Kelurahan Pondok Kopi</strong><br>
            Diekspor pada <?= date('d F Y, H:i:s'); ?> WIB
        </td>
    </tr>
</table>

</body>
</html>