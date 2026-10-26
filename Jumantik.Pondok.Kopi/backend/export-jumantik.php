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
$nomor_rw = $data_rw['username'] ?? '-';
$rw_id = $data_rw['id'] ?? 0;

// Extract nomor RW - ambil angka saja
$nomor_rw_angka = preg_replace('/[^0-9]/', '', (string)$nomor_rw);
if (empty($nomor_rw_angka) && isset($data_rw['nomor_rw'])) {
    $nomor_rw_angka = preg_replace('/[^0-9]/', '', (string)$data_rw['nomor_rw']);
}
if (empty($nomor_rw_angka) && isset($data_rw['rw'])) {
    $nomor_rw_angka = preg_replace('/[^0-9]/', '', (string)$data_rw['rw']);
}

// Ambil parameter dari URL
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$bulan = $_GET['bulan'] ?? '';
$status = $_GET['status'] ?? '';
$periode = $_GET['periode'] ?? '';
$minggu = $_GET['minggu'] ?? '';
$export_all = isset($_GET['export_all']) && $_GET['export_all'] == '1';

// Debug mode - tambahkan ?debug=1 di URL untuk melihat query
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

// Detect available columns
$cols = [];
$col_res = mysqli_query($conn, "SHOW COLUMNS FROM data_jumantik");
if ($col_res) {
    while ($c = mysqli_fetch_assoc($col_res)) {
        $cols[] = $c['Field'];
    }
}

// Build petugas join
$petugas_select = "";
$petugas_join = "";
if (in_array('petugas_id', $cols)) {
    $petugas_select = ",\n    p.nama_lengkap AS petugas_nama,\n    u.username AS petugas_username";
    $petugas_join = "\nLEFT JOIN petugas p ON p.id = dj.petugas_id\nLEFT JOIN users u ON u.id = p.user_id";
} elseif (in_array('petugas', $cols)) {
    $petugas_select = ",\n    p.nama_lengkap AS petugas_nama,\n    u.username AS petugas_username";
    $petugas_join = "\nLEFT JOIN petugas p ON p.user_id = dj.petugas\nLEFT JOIN users u ON u.id = dj.petugas";
} elseif (in_array('petugas_user_id', $cols)) {
    $petugas_select = ",\n    p.nama_lengkap AS petugas_nama,\n    u.username AS petugas_username";
    $petugas_join = "\nLEFT JOIN petugas p ON p.user_id = dj.petugas_user_id\nLEFT JOIN users u ON u.id = dj.petugas_user_id";
}

// Build query
$query = "
    SELECT 
        dj.*, 
        k.nama_kader AS kader_nama,
        k.rw AS kader_rw,
        CONCAT(dj.rt, '/', COALESCE(NULLIF(dj.rw, ''), k.rw, '-')) AS rt_rw_display,
        COALESCE(k.nama_kader, dj.nama_kepala_keluarga) AS nama_kader_display
        {$petugas_select}
    FROM data_jumantik dj
    LEFT JOIN kader k ON dj.kader_id = k.id
    {$petugas_join}
    WHERE 1 = 1";

$params = [];
$types = "";

// Filter RW
if (!$export_all && !empty($nomor_rw_angka)) {
    $query .= " AND (
        dj.rw LIKE CONCAT('%', ?, '%') OR
        k.rw LIKE CONCAT('%', ?, '%')
    )";
    $params[] = $nomor_rw_angka;
    $params[] = $nomor_rw_angka;
    $types .= "ss";
}

// Filter bulan atau minggu
if ($periode == 'mingguan' && !empty($minggu)) {
    // Jika format minggu adalah "2026-W03", extract minggu-nya
    if (strpos($minggu, 'W') !== false) {
        // Format: 2026-W03 - ambil week number
        preg_match('/W(\d+)/', $minggu, $matches);
        $week_number = $matches[1] ?? null;
        
        if ($week_number) {
            // Hitung tanggal dari week number
            $year = date('Y', strtotime($minggu));
            $tanggal_awal = date('Y-m-d', strtotime($year . 'W' . str_pad($week_number, 2, '0', STR_PAD_LEFT)));
            $tanggal_akhir = date('Y-m-d', strtotime($tanggal_awal . ' +6 days'));
        }
    } elseif (!empty($bulan)) {
        // Format lama: minggu=1, minggu=2, dll dengan bulan terpisah
        $tahun_bulan = explode('-', $bulan);
        $tahun = $tahun_bulan[0];
        $bulan_num = $tahun_bulan[1];
        
        switch($minggu) {
            case '1':
                $tanggal_awal = "$tahun-$bulan_num-01";
                $tanggal_akhir = "$tahun-$bulan_num-07";
                break;
            case '2':
                $tanggal_awal = "$tahun-$bulan_num-08";
                $tanggal_akhir = "$tahun-$bulan_num-14";
                break;
            case '3':
                $tanggal_awal = "$tahun-$bulan_num-15";
                $tanggal_akhir = "$tahun-$bulan_num-21";
                break;
            case '4':
                $tanggal_awal = "$tahun-$bulan_num-22";
                $tanggal_akhir = "$tahun-$bulan_num-28";
                break;
            case '5':
                $tanggal_awal = "$tahun-$bulan_num-29";
                $tanggal_akhir = date("Y-m-t", strtotime("$tahun-$bulan_num-01"));
                break;
            default:
                $tanggal_awal = "$tahun-$bulan_num-01";
                $tanggal_akhir = date("Y-m-t", strtotime("$tahun-$bulan_num-01"));
        }
    }
    
    if (isset($tanggal_awal) && isset($tanggal_akhir)) {
        $query .= " AND dj.tanggal_pemeriksaan BETWEEN ? AND ?";
        $params[] = $tanggal_awal;
        $params[] = $tanggal_akhir;
        $types .= "ss";
    }
    
} elseif ($periode == 'bulanan' && !empty($bulan)) {
    $query .= " AND DATE_FORMAT(dj.tanggal_pemeriksaan, '%Y-%m') = ?";
    $params[] = $bulan;
    $types .= "s";
}

// Filter pencarian
if (!empty($cari)) {
    $cari_param = '%' . $cari . '%';
    $query .= " AND (
        dj.nama_kepala_keluarga LIKE ? OR
        k.nama_kader LIKE ? OR
        dj.rt LIKE ? OR
        dj.alamat LIKE ?
    )";
    $params[] = $cari_param;
    $params[] = $cari_param;
    $params[] = $cari_param;
    $params[] = $cari_param;
    $types .= "ssss";
}

// Filter status jentik
if (!empty($status)) {
    if ($status == 'Ada') {
        $query .= " AND dj.jentik_ditemukan IN ('Ya', 'ya', 'ada', 'Ada', '1')";
    } else {
        $query .= " AND (dj.jentik_ditemukan NOT IN ('Ya', 'ya', 'ada', 'Ada', '1') OR dj.jentik_ditemukan IS NULL)";
    }
}

$query .= " ORDER BY dj.tanggal_pemeriksaan DESC";

// Execute query
$stmt = mysqli_prepare($conn, $query);
if ($stmt === false) {
    die("Error preparing statement: " . mysqli_error($conn));
}

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

// Debug mode
if ($debug) {
    echo "<h3>Debug Information</h3>";
    echo "<p><strong>Query:</strong><br><pre>" . htmlspecialchars($query) . "</pre></p>";
    echo "<p><strong>Parameters:</strong><br><pre>" . print_r($params, true) . "</pre></p>";
    echo "<p><strong>Types:</strong> " . $types . "</p>";
    echo "<p><strong>Periode:</strong> " . $periode . "</p>";
    echo "<p><strong>Bulan:</strong> " . $bulan . "</p>";
    echo "<p><strong>Minggu:</strong> " . $minggu . "</p>";
    echo "<p><strong>RW:</strong> " . $nomor_rw_angka . "</p>";
    echo "<hr>";
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Debug: tampilkan jumlah rows
if ($debug) {
    echo "<p><strong>Total Rows Found:</strong> " . mysqli_num_rows($result) . "</p>";
    echo "<hr>";
}

// Set header untuk download Excel
$filename_suffix = $export_all ? "Semua_RW" : "RW_" . $nomor_rw_angka;

if ($periode == 'mingguan' && !empty($bulan) && !empty($minggu)) {
    $bulan_suffix = "_" . $bulan . "_Minggu_" . $minggu;
} elseif ($periode == 'bulanan' && !empty($bulan)) {
    $bulan_suffix = "_" . $bulan . "_Bulanan";
} else {
    $bulan_suffix = !empty($bulan) ? "_" . $bulan : "";
}

$filename = "Laporan_Jumantik_{$filename_suffix}{$bulan_suffix}_" . date('Y-m-d_His') . ".xls";

// Jika debug mode, jangan set header download
if (!$debug) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Output HTML Table untuk Excel
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #4472C4; color: white; font-weight: bold; padding: 8px; border: 1px solid #000; text-align: center; }
        td { padding: 5px; border: 1px solid #000; }
        .header-title { font-size: 16px; font-weight: bold; text-align: center; padding: 10px; }
        .header-info { font-size: 12px; text-align: center; padding: 5px; }
    </style>
</head>
<body>
    <div class="header-title">LAPORAN DATA JUMANTIK RW <?php echo htmlspecialchars($nomor_rw_angka); ?></div>
    
    <?php if ($periode == 'mingguan' && !empty($bulan) && !empty($minggu)): ?>
        <?php $bulan_display = date('F Y', strtotime($bulan . '-01')); ?>
        <div class="header-info">Periode: Minggu ke-<?php echo $minggu; ?> (<?php echo $bulan_display; ?>)</div>
    <?php elseif ($periode == 'bulanan' && !empty($bulan)): ?>
        <?php $bulan_display = date('F Y', strtotime($bulan . '-01')); ?>
        <div class="header-info">Periode: Bulanan (<?php echo $bulan_display; ?>)</div>
    <?php endif; ?>
    
    <br>
    
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>RT/RW</th>
                <th>Nama KK</th>
                <th>Alamat</th>
                <th>Jentik</th>
                <th>Tanggal</th>
                <th>Petugas</th>
                <th>Gambar</th>
                <th>Catatan</th>
                <th>Konfirmasi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_rows = mysqli_num_rows($result);
            if ($total_rows > 0) {
                $no = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                    // Normalize jentik value
                    $raw_j = trim(strtolower($row['jentik_ditemukan'] ?? ''));
                    $is_jentik = in_array($raw_j, ['ya', 'ada', 'yes', '1']);
                    $jentik_text = $is_jentik ? 'Ada Jentik' : 'Bebas Jentik';

                    // RT/RW display
                    $rw_display = $row['rw'] ?? $row['kader_rw'] ?? '-';
                    $rt_rw = ($row['rt'] ?? '-') . ' / ' . ($rw_display ?: '-');
                    
                    // Tanggal
                    $tanggal = isset($row['tanggal_pemeriksaan']) ? date('d M Y', strtotime($row['tanggal_pemeriksaan'])) : '-';

                    // Gambar
                    $gambar = isset($row['foto_rumah']) && !empty($row['foto_rumah']) ? basename($row['foto_rumah']) : 'Tidak ada foto';

                    // Petugas
                    $petugas_label = '-';
                    if (!empty(trim($row['petugas_nama'] ?? ''))) {
                        $petugas_label = $row['petugas_nama'];
                    } elseif (!empty(trim($row['petugas_username'] ?? ''))) {
                        $petugas_label = $row['petugas_username'];
                    }

                    echo '<tr>';
                    echo '<td style="text-align: center;">' . $no++ . '</td>';
                    echo '<td style="text-align: center;">' . htmlspecialchars($rt_rw) . '</td>';
                    echo '<td>' . htmlspecialchars($row['nama_kepala_keluarga'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($row['alamat'] ?? '-') . '</td>';
                    echo '<td style="text-align: center;">' . htmlspecialchars($jentik_text) . '</td>';
                    echo '<td style="text-align: center;">' . htmlspecialchars($tanggal) . '</td>';
                    echo '<td>' . htmlspecialchars($petugas_label) . '</td>';
                    echo '<td>' . htmlspecialchars($gambar) . '</td>';
                    echo '<td>' . htmlspecialchars($row['catatan'] ?? '-') . '</td>';
                    echo '<td style="text-align: center;">' . htmlspecialchars($row['status_konfirmasi'] ?? 'Menunggu') . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="10" style="text-align: center;">Tidak ada data</td></tr>';
            }
            ?>
        </tbody>
    </table>
</body>
</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
exit;
?>