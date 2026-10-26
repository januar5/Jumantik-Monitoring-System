<?php
session_start();
require_once 'config.php';

// Pastikan hanya admin yang boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$error_msg = '';
$success_msg = '';

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: data-kader.php");
    exit;
}

$id = intval($_GET['id']);

// Ambil data kader dengan prepared statement
$stmt = mysqli_prepare($conn, "SELECT k.*, u.username FROM kader k LEFT JOIN users u ON k.user_id = u.id WHERE k.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$data) {
    header("Location: data-kader.php");
    exit;
}

// Proses Update
if (isset($_POST['update'])) {
    $nama = trim($_POST['nama_lengkap']);
    $rt = trim($_POST['rt']);
    $rw = trim($_POST['rw']);
    $no_hp = trim($_POST['no_hp']);
    $username = trim($_POST['username']);

    // Validasi input
    if (empty($nama) || empty($rt) || empty($rw) || empty($no_hp) || empty($username)) {
        $error_msg = "Semua field wajib diisi!";
    } elseif (!preg_match('/^[0-9]{10,13}$/', $no_hp)) {
        $error_msg = "Format No HP tidak valid (10-13 digit)!";
    } elseif (strlen($username) < 4) {
        $error_msg = "Username minimal 4 karakter!";
    } else {
        // Cek apakah username sudah digunakan user lain
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
        mysqli_stmt_bind_param($check, "si", $username, $data['user_id']);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        
        if (mysqli_stmt_num_rows($check) > 0) {
            $error_msg = "Username sudah digunakan oleh kader lain!";
        } else {
            mysqli_autocommit($conn, FALSE);
            
            try {
                // Update tabel kader
                $stmt_kader = mysqli_prepare($conn, "UPDATE kader SET nama_kader=?, rt=?, rw=?, no_hp=? WHERE id=?");
                mysqli_stmt_bind_param($stmt_kader, "ssssi", $nama, $rt, $rw, $no_hp, $id);
                
                if (!mysqli_stmt_execute($stmt_kader)) {
                    throw new Exception("Gagal update data kader: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_kader);
                
                // Update username di tabel users
                $stmt_user = mysqli_prepare($conn, "UPDATE users SET username=? WHERE id=?");
                mysqli_stmt_bind_param($stmt_user, "si", $username, $data['user_id']);
                
                if (!mysqli_stmt_execute($stmt_user)) {
                    throw new Exception("Gagal update username: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_user);
                
                // Update password jika diisi
                if (!empty($_POST['password'])) {
                    $password = $_POST['password'];
                    if (strlen($password) < 4) {
                        throw new Exception("Password minimal 4 karakter!");
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt_pass = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt_pass, "si", $hashed_password, $data['user_id']);
                    
                    if (!mysqli_stmt_execute($stmt_pass)) {
                        throw new Exception("Gagal update password: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt_pass);
                }
                
                if (!mysqli_commit($conn)) {
                    throw new Exception("Gagal commit transaksi: " . mysqli_error($conn));
                }
                
                mysqli_autocommit($conn, TRUE);
                header("Location: data-kader.php?success=edit");
                exit;
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                mysqli_autocommit($conn, TRUE);
                $error_msg = $e->getMessage();
            }
        }
        mysqli_stmt_close($check);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kader Jumantik</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @media screen and (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr !important;
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
                <li><a href="dashboard-admin.php">🏠 Dashboard</a></li>
                <li><a href="data-kader.php" class="active">👩‍💼 Data Kader</a></li>
                <li><a href="data-rw.php">🏘️ Data RW</a></li>
                <li><a href="grafik-jentik.php">📊 Statistik</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <!-- Header -->
            <div class="header">
                <h1>✏️ Edit Data Kader</h1>
                <a href="data-kader.php" class="logout-btn">← Kembali</a>
            </div>

            <!-- Alert Messages -->
            <?php if ($error_msg): ?>
            <div class="welcome" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-left-color: #dc3545;">
                <p style="color: #721c24; margin: 0;">❌ <?= htmlspecialchars($error_msg); ?></p>
            </div>
            <?php endif; ?>

            <!-- Form Edit Kader -->
            <form method="POST" action="" onsubmit="return validateForm()">
                <h3 style="margin-bottom: 20px; color: #1e293b; font-size: 1.3rem;">📝 Edit Data Kader</h3>
                
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <input type="text" name="username" id="username" value="<?= htmlspecialchars($data['username']); ?>" placeholder="Username" required minlength="4" maxlength="50">
                    
                    <input type="password" name="password" id="password" placeholder="Password Baru (kosongkan jika tidak diubah)" minlength="4" maxlength="100">
                    
                    <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($data['nama_kader']); ?>" placeholder="Nama Lengkap" required maxlength="100">
                    
                    <input type="text" name="rt" value="<?= htmlspecialchars($data['rt']); ?>" placeholder="RT (contoh: RT 01)" required maxlength="10">
                    
                    <input type="text" name="rw" value="<?= htmlspecialchars($data['rw']); ?>" placeholder="RW (contoh: RW 01)" required maxlength="10">
                    
                    <input type="tel" name="no_hp" id="no_hp" value="<?= htmlspecialchars($data['no_hp']); ?>" placeholder="No HP (08xxx)" required pattern="[0-9]{10,13}" title="Nomor HP harus 10-13 digit angka">
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" name="update" class="btn btn-primary" style="flex: 1;">💾 Simpan Perubahan</button>
                    <a href="data-kader.php" class="btn btn-secondary" style="flex: 1; text-align: center; text-decoration: none; padding: 12px;">❌ Batal</a>
                </div>
            </form>
        </main>
    </div>

    <!-- Hamburger Menu & Overlay -->
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }

        function validateForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const noHp = document.getElementById('no_hp').value.trim();
            
            if (username.length < 4) {
                alert('⚠️ Username minimal 4 karakter!');
                return false;
            }
            
            if (password && password.length < 4) {
                alert('⚠️ Password minimal 4 karakter!');
                return false;
            }
            
            if (!/^[0-9]{10,13}$/.test(noHp)) {
                alert('⚠️ Nomor HP harus 10-13 digit angka!\nContoh: 08123456789');
                return false;
            }
            
            return confirm('💾 Simpan perubahan data kader?');
        }
    </script>
</body>
</html>