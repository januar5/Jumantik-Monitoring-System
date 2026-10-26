<?php
session_start();
require_once '../config.php';

// Set header JSON
header('Content-Type: application/json; charset=utf-8');

// Matikan output buffering dan error display
ob_clean();
ini_set('display_errors', 0);
error_reporting(0);

try {
    // Cek login
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Unauthorized - Silakan login'
        ]);
        exit;
    }

    $role = $_SESSION['role'];
    $user_id = $_SESSION['user_id'];
    
    // Ambil ID dari POST atau JSON body
    $id = 0;
    if (isset($_POST['id'])) {
        $id = intval($_POST['id']);
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (isset($data['id'])) {
            $id = intval($data['id']);
        }
    }
    
    if ($id <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'ID tidak valid'
        ]);
        exit;
    }
    
    // HANDLE BERDASARKAN ROLE
    if ($role === 'kader') {
        // === KADER: Hanya bisa hapus data miliknya sendiri ===
        $stmt = mysqli_prepare($conn, "SELECT id FROM kader WHERE user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $kader = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$kader) {
            echo json_encode([
                'success' => false, 
                'message' => 'Data kader tidak ditemukan'
            ]);
            exit;
        }
        
        $kader_id = $kader['id'];
        
        // Hapus data dengan validasi kader_id
        $query = "DELETE FROM data_jumantik WHERE id = ? AND kader_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            echo json_encode([
                'success' => false, 
                'message' => 'Database error: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt, "ii", $id, $kader_id);
        
    } elseif ($role === 'rw') {
        // === RW: Bisa hapus semua data dalam RW-nya ===
        $username = $_SESSION['username'];
        
        // Ambil data RW - GUNAKAN QUERY SAMA SEPERTI DI laporan-rw.php
        $query_rw = "SELECT r.*, u.username FROM rw r 
                     LEFT JOIN users u ON r.user_id = u.id 
                     WHERE u.username = ?";
        $stmt_rw = mysqli_prepare($conn, $query_rw);
        
        if ($stmt_rw === false) {
            echo json_encode([
                'success' => false, 
                'message' => 'Database error: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt_rw, "s", $username);
        mysqli_stmt_execute($stmt_rw);
        $result_rw = mysqli_stmt_get_result($stmt_rw);
        $data_rw = mysqli_fetch_assoc($result_rw);
        mysqli_stmt_close($stmt_rw);
        
        if (!$data_rw) {
            echo json_encode([
                'success' => false, 
                'message' => 'Data RW tidak ditemukan'
            ]);
            exit;
        }
        
        // Ekstrak nomor RW - SAMA SEPERTI DI laporan-rw.php
        $nomor_rw = $data_rw['nomor_rw'] ?? $data_rw['username'] ?? '';
        $nomor_rw_filter = preg_replace('/[^0-9]/', '', (string)$nomor_rw);
        $nomor_rw_filter_int = !empty($nomor_rw_filter) ? intval($nomor_rw_filter) : 0;
        
        if ($nomor_rw_filter_int <= 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Nomor RW tidak valid'
            ]);
            exit;
        }
        
        // Cek kepemilikan data sebelum hapus - GUNAKAN NORMALISASI SQL YANG SAMA
        $query_check = "
            SELECT 
                dj.*,
                k.rw as kader_rw,
                CAST(NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CAST(COALESCE(NULLIF(dj.rw, ''), k.rw) AS CHAR) COLLATE utf8mb4_general_ci), 'rw', ''), '/', ''), ' ', ''), '.', ''), '') AS UNSIGNED) as rw_normalized
            FROM data_jumantik dj
            LEFT JOIN kader k ON dj.kader_id = k.id
            WHERE dj.id = ?
        ";
        $stmt_check = mysqli_prepare($conn, $query_check);
        
        if ($stmt_check === false) {
            echo json_encode([
                'success' => false, 
                'message' => 'Database error: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt_check, "i", $id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $data_check = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);
        
        if (!$data_check) {
            echo json_encode([
                'success' => false, 
                'message' => 'Data tidak ditemukan'
            ]);
            exit;
        }
        
        // Gunakan hasil normalisasi dari query
        $data_rw_int = intval($data_check['rw_normalized'] ?? 0);
        
        // Validasi kepemilikan
        if ($data_rw_int !== $nomor_rw_filter_int) {
            echo json_encode([
                'success' => false, 
                'message' => "Anda tidak memiliki akses untuk menghapus data ini"
            ]);
            exit;
        }
        
        // Hapus foto jika ada
        if (!empty($data_check['foto_rumah'])) {
            $foto_path = '../uploads/' . $data_check['foto_rumah'];
            if (file_exists($foto_path)) {
                @unlink($foto_path);
            }
        }
        
        // Hapus data tanpa filter kader_id
        $query = "DELETE FROM data_jumantik WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            echo json_encode([
                'success' => false, 
                'message' => 'Database error: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $id);
        
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Role tidak dikenali'
        ]);
        exit;
    }
    
    // EKSEKUSI DELETE
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Data berhasil dihapus!'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Data tidak ditemukan atau sudah terhapus'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . mysqli_error($conn)
        ]);
    }
    
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit;
?>