<?php
session_start();
header('Content-Type: application/json');
require_once '../config.php';

// Cek apakah user sudah login sebagai kader
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kader') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil kader_id dari user_id
$stmt = mysqli_prepare($conn, "SELECT id FROM kader WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kader = mysqli_fetch_assoc($result);

if (!$kader) {
    echo json_encode(['success' => false, 'message' => 'Kader tidak ditemukan']);
    exit;
}

$kader_id = $kader['id'];
mysqli_stmt_close($stmt);

// ===== FUNGSI NORMALISASI RT/RW =====
function normalizeRTRW($value) {
    // Ambil hanya angka
    $digits = preg_replace('/[^0-9]/', '', trim($value));
    if (empty($digits)) return '';
    $num = intval($digits);
    // Format 2 digit: 01, 02, 03, dst
    return str_pad($num, 2, '0', STR_PAD_LEFT);
}

// Proses data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        // Validasi input dengan NORMALISASI
        $rt_raw = $_POST['rt'] ?? '';
        $rw_raw = $_POST['rw'] ?? '';
        
        // NORMALISASI RT/RW
        $rt = normalizeRTRW($rt_raw);
        $rw = normalizeRTRW($rw_raw);
        
        error_log("RT RAW: $rt_raw -> NORMALIZED: $rt");
        error_log("RW RAW: $rw_raw -> NORMALIZED: $rw");
        
        // New: accept per-tatanan numeric inputs
        $tatanan_inputs = [];
        $tatanan_list = [
            'pemukiman','institusi_pendidikan','tempat_kerja','ttu','tpm','sarana_olahraga','sarana_kesehatan'
        ];
        foreach ($tatanan_list as $t) {
            $d = isset($_POST[$t . '_diperiksa']) ? intval($_POST[$t . '_diperiksa']) : 0;
            $j = isset($_POST[$t . '_jentik']) ? intval($_POST[$t . '_jentik']) : 0;
            $tatanan_inputs[$t] = ['diperiksa' => $d, 'jentik' => $j];
        }
        // Accept minggu and bulan instead of a date
        $minggu = isset($_POST['minggu']) ? intval($_POST['minggu']) : 0; // 1..6
        $bulan_n = isset($_POST['bulan']) ? intval($_POST['bulan']) : 0; // 1..12

        // compute tanggal_pemeriksaan using current year, pick starting day of the week
        $current_year = date('Y');
        $tanggal_pemeriksaan = null;
        if ($minggu >= 1 && $minggu <= 6 && $bulan_n >=1 && $bulan_n <= 12) {
            // Gunakan DateTime yang handle overflow ke bulan berikutnya
            $datetime = new DateTime("$current_year-" . str_pad($bulan_n, 2, '0', STR_PAD_LEFT) . "-01");
            $datetime->add(new DateInterval("P" . (($minggu - 1) * 7) . "D"));
            $tanggal_pemeriksaan = $datetime->format('Y-m-d');
        }
        $catatan = trim($_POST['catatan'] ?? ''); // optional

        // Log untuk debugging
        error_log("=== DATA DITERIMA (REKAP) ===");
        error_log("RT: " . $rt);
        error_log("RW: " . $rw);
        error_log("Tanggal: " . $tanggal_pemeriksaan);
        error_log("Catatan: " . $catatan);
        error_log("Tatanan inputs: " . json_encode($tatanan_inputs));

        // Validasi field wajib
        if (empty($rt)) {
            echo json_encode(['success' => false, 'message' => 'RT wajib diisi']);
            exit;
        }
        if (empty($rw)) {
            echo json_encode(['success' => false, 'message' => 'RW wajib diisi']);
            exit;
        }
        if (empty($tanggal_pemeriksaan)) {
            echo json_encode(['success' => false, 'message' => 'Minggu dan Bulan wajib dipilih dengan benar']);
            exit;
        }

        // Prepare JSON summary of tatanan inputs + catatan tambahan
        $tatanan_labels_map = [
            'pemukiman' => 'Pemukiman',
            'institusi_pendidikan' => 'Institusi Pendidikan',
            'tempat_kerja' => 'Tempat Kerja',
            'ttu' => 'TTU',
            'tpm' => 'TPM',
            'sarana_olahraga' => 'Sarana Olahraga',
            'sarana_kesehatan' => 'Sarana Kesehatan'
        ];
        $summary = [];
        foreach ($tatanan_inputs as $key => $val) {
            $name = $tatanan_labels_map[$key] ?? ucwords(str_replace(['_'], [' '], $key));
            $summary[$name] = $val;
        }
        // add catatan as key '_catatan' in the JSON
        if (!empty($catatan)) {
            $summary['_catatan'] = $catatan;
        }
        $summary_json = json_encode($summary);
        error_log("Summary JSON: " . $summary_json);

        // Insert one record as summary (catatan contains JSON counts)
        $query = "INSERT INTO data_jumantik (
            kader_id,
            rt,
            rw,
            nama_kepala_keluarga,
            tatanan_diperiksa,
            alamat,
            tanggal_pemeriksaan,
            jentik_ditemukan,
            catatan,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = mysqli_prepare($conn, $query);

        if (!$stmt) {
            error_log("Prepare failed: " . mysqli_error($conn));
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }

        // set nama_kepala_keluarga empty; tatanan_diperiksa mark as 'Rekap'
        $nama_kepala_keluarga = '';
        $tatanan_diperiksa = 'Rekap';
        $alamat = '';
        $any_jentik = false;
        foreach ($tatanan_inputs as $v) { if ($v['jentik'] > 0) { $any_jentik = true; break; } }
        $jentik_ditemukan_overall = $any_jentik ? 'Ya' : 'Tidak';

        mysqli_stmt_bind_param(
            $stmt,
            "issssssss",
            $kader_id,
            $rt,  // SUDAH DINORMALISASI
            $rw,  // SUDAH DINORMALISASI
            $nama_kepala_keluarga,
            $tatanan_diperiksa,
            $alamat,
            $tanggal_pemeriksaan,
            $jentik_ditemukan_overall,
            $summary_json
        );

        if (mysqli_stmt_execute($stmt)) {
            error_log("Rekap berhasil disimpan dengan ID: " . mysqli_insert_id($conn));
            echo json_encode([
                'success' => true,
                'message' => 'Data rekap berhasil disimpan!',
                'id' => mysqli_insert_id($conn)
            ]);
        } else {
            error_log("Execute failed: " . mysqli_stmt_error($stmt));
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . mysqli_stmt_error($stmt)]);
        }

        mysqli_stmt_close($stmt);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

mysqli_close($conn);
?>