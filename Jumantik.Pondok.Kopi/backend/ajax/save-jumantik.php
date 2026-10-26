<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kader') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? 'create';
$response = ['success' => false, 'message' => ''];

try {
    // Ambil kader_id yang BENAR dari tabel kader
    $user_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, rw FROM kader WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $kader = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$kader) {
        echo json_encode(['success' => false, 'message' => 'Data kader tidak ditemukan']);
        exit();
    }
    
    $kader_id = $kader['id'];
    
    // Ambil data dari form - DISESUAIKAN dengan data-jumantik.php
    $rt = mysqli_real_escape_string($conn, $_POST['rt'] ?? '');
    $rw = mysqli_real_escape_string($conn, $_POST['rw'] ?? '');
    $nama_kepala_keluarga = mysqli_real_escape_string($conn, $_POST['nama_kepala_keluarga'] ?? '');
    $tatanan_diperiksa = mysqli_real_escape_string($conn, $_POST['tatanan_diperiksa'] ?? '');
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat'] ?? '');
    $tanggal_pemeriksaan = mysqli_real_escape_string($conn, $_POST['tanggal_pemeriksaan'] ?? '');
    $status_pemeriksaan = mysqli_real_escape_string($conn, $_POST['status_pemeriksaan'] ?? '');
    $jentik_ditemukan = mysqli_real_escape_string($conn, $_POST['jentik_ditemukan'] ?? '');
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    
    // Validasi data wajib
    if (empty($rt) || empty($rw) || empty($nama_kepala_keluarga) || empty($tatanan_diperiksa) || 
        empty($alamat) || empty($tanggal_pemeriksaan) || empty($status_pemeriksaan) || empty($jentik_ditemukan)) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi!']);
        exit();
    }
    
    if ($action == 'create') {
        // INSERT data baru
        $query = "INSERT INTO data_jumantik 
                  (kader_id, rt, rw, nama_kepala_keluarga, tatanan_diperiksa, alamat, 
                   tanggal_pemeriksaan, status_pemeriksaan, jentik_ditemukan, keterangan, 
                   status_konfirmasi, created_at) 
                  VALUES 
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu', NOW())";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isssssssss", 
            $kader_id, $rt, $rw, $nama_kepala_keluarga, $tatanan_diperiksa, $alamat, 
            $tanggal_pemeriksaan, $status_pemeriksaan, $jentik_ditemukan, $keterangan);
        
        if (mysqli_stmt_execute($stmt)) {
            $response['success'] = true;
            $response['message'] = 'Data berhasil disimpan!';
        } else {
            $response['message'] = 'Error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        
    } elseif ($action == 'update') {
        // UPDATE data existing
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit();
        }
        
        $query = "UPDATE data_jumantik SET 
                  rt=?, rw=?, nama_kepala_keluarga=?, tatanan_diperiksa=?, alamat=?, 
                  tanggal_pemeriksaan=?, status_pemeriksaan=?, jentik_ditemukan=?, 
                  keterangan=?, updated_at=NOW() 
                  WHERE id=? AND kader_id=?";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssssssii", 
            $rt, $rw, $nama_kepala_keluarga, $tatanan_diperiksa, $alamat,
            $tanggal_pemeriksaan, $status_pemeriksaan, $jentik_ditemukan,
            $keterangan, $id, $kader_id);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $response['success'] = true;
                $response['message'] = 'Data berhasil diperbarui!';
            } else {
                $response['success'] = false;
                $response['message'] = 'Tidak ada perubahan atau data tidak ditemukan';
            }
        } else {
            $response['message'] = 'Error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        
    } elseif ($action == 'delete') {
        // DELETE data
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit();
        }
        
        $query = "DELETE FROM data_jumantik WHERE id=? AND kader_id=?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $id, $kader_id);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $response['success'] = true;
                $response['message'] = 'Data berhasil dihapus!';
            } else {
                $response['success'] = false;
                $response['message'] = 'Data tidak ditemukan atau Anda tidak memiliki akses';
            }
        } else {
            $response['message'] = 'Error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        
    } else {
        $response['message'] = 'Action tidak valid';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Exception: ' . $e->getMessage();
}

echo json_encode($response);
?>