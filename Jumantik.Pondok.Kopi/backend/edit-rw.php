<?php
session_start();
require_once 'config.php';

// Pastikan hanya admin yang boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Validasi ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: data-rw.php");
    exit;
}

$id = intval($_GET['id']);

// Ambil data RW dengan JOIN ke users
$query = "SELECT r.*, u.username AS user_username, u.id AS user_id 
          FROM rw r 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE r.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rw = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Jika data tidak ditemukan
if (!$rw) {
    $_SESSION['error'] = "Data RW tidak ditemukan!";
    header("Location: data-rw.php");
    exit;
}

// Proses UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username_login = trim($_POST['username_login']);
    $rw_number = trim($_POST['rw']);
    $rt_number = trim($_POST['rt']);
    $no_hp = trim($_POST['no_hp']);
    $password = $_POST['password'];

    // Validasi
    if (empty($nama_lengkap) || empty($username_login) || empty($rw_number) || empty($rt_number) || empty($no_hp)) {
        $error_msg = "Semua field wajib diisi!";
    } elseif (!preg_match('/^[0-9]{10,13}$/', $no_hp)) {
        $error_msg = "Format No HP tidak valid (10–13 digit)!";
    } else {
        // Cek username login tidak dipakai user lain
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
        mysqli_stmt_bind_param($check, "si", $username_login, $rw['user_id']);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error_msg = "Username sudah digunakan oleh user lain!";
        } else {
            mysqli_begin_transaction($conn);
            try {
                // Update tabel users
                if (!empty($password)) {
                    // Jika password diubah
                    if (strlen($password) < 4) {
                        throw new Exception("Password minimal 4 karakter!");
                    }
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt1 = mysqli_prepare($conn, "UPDATE users SET username = ?, password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt1, "ssi", $username_login, $hashed_password, $rw['user_id']);
                } else {
                    // Jika password tidak diubah
                    $stmt1 = mysqli_prepare($conn, "UPDATE users SET username = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt1, "si", $username_login, $rw['user_id']);
                }

                if (!mysqli_stmt_execute($stmt1)) {
                    throw new Exception("Gagal update users: " . mysqli_stmt_error($stmt1));
                }
                mysqli_stmt_close($stmt1);

                // Update tabel rw (termasuk password jika diubah)
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt2 = mysqli_prepare($conn, 
                        "UPDATE rw SET nama_rw = ?, username = ?, password = ?, rt = ?, no_hp = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt2, "sssssi", $nama_lengkap, $rw_number, $hashed_password, $rt_number, $no_hp, $id);
                } else {
                    $stmt2 = mysqli_prepare($conn, 
                        "UPDATE rw SET nama_rw = ?, username = ?, rt = ?, no_hp = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt2, "ssssi", $nama_lengkap, $rw_number, $rt_number, $no_hp, $id);
                }

                if (!mysqli_stmt_execute($stmt2)) {
                    throw new Exception("Gagal update data RW: " . mysqli_stmt_error($stmt2));
                }
                mysqli_stmt_close($stmt2);

                mysqli_commit($conn);
                $_SESSION['success'] = "Data RW berhasil diupdate!";
                header("Location: data-rw.php");
                exit;

            } catch (Exception $e) {
                mysqli_rollback($conn);
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
    <title>Edit Data RW - Jumantik</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <h2>🦟 Jumantik</h2>
            <ul>
                <li><a href="dashboard-admin.php">📊 Dashboard</a></li>
                <li><a href="data-kader.php">👩‍💼 Data Kader</a></li>
                <li><a href="data-rw.php" class="active">🏘️ Data RW</a></li>
                <li><a href="grafik-jentik.php">📈 Statistik</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="header">
                <h1>✏️ Edit Data RW</h1>
                <a href="data-rw.php" class="logout-btn">← Kembali</a>
            </div>

            <?php if ($success_msg): ?>
            <div class="welcome" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left-color: #28a745;">
                <p style="color: #155724; margin: 0;">✅ <?= htmlspecialchars($success_msg); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
            <div class="welcome" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-left-color: #dc3545;">
                <p style="color: #721c24; margin: 0;">❌ <?= htmlspecialchars($error_msg); ?></p>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <div style="padding: 20px 25px; border-bottom: 2px solid #e2e8f0;">
                    <h3 style="color: #1e293b; font-size: 1.3rem; margin: 0;">📝 Form Edit Data RW</h3>
                </div>
                
                <form method="POST" action="" onsubmit="return validateForm()" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">
                                Username Login <span style="color: #dc2626;">*</span>
                            </label>
                            <input type="text" name="username_login" id="username_login" 
                                   value="<?= htmlspecialchars($rw['user_username']); ?>" 
                                   placeholder="Username untuk login" 
                                   required minlength="4"
                                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                            <small style="color: #64748b; font-size: 12px;">Username untuk login ke sistem</small>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">
                                Password Baru
                            </label>
                            <input type="password" name="password" id="password" 
                                   placeholder="Kosongkan jika tidak diubah" 
                                   minlength="4"
                                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                            <small style="color: #64748b; font-size: 12px;">Minimal 4 karakter, kosongkan jika tidak ingin mengubah</small>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">
                                Nama Lengkap <span style="color: #dc2626;">*</span>
                            </label>
                            <input type="text" name="nama_lengkap" 
                                   value="<?= htmlspecialchars($rw['nama_rw']); ?>" 
                                   placeholder="Nama lengkap RW" 
                                   required
                                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">
                                RW <span style="color: #dc2626;">*</span>
                            </label>
                            <input type="text" name="rw" 
                                   value="<?= htmlspecialchars($rw['username']); ?>" 
                                   placeholder="Contoh: RW 01" 
                                   required
                                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">
                                RT <span style="color: #dc2626;">*</span>
                            </label>
                            <input type="text" name="rt" 
                                   value="<?= htmlspecialchars($rw['rt'] ?? ''); ?>" 
                                   placeholder="Contoh: RT 05" 
                                   required
                                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">
                                No HP <span style="color: #dc2626;">*</span>
                            </label>
                            <input type="text" name="no_hp" id="no_hp" 
                                   value="<?= htmlspecialchars($rw['no_hp']); ?>" 
                                   placeholder="08xxxxxxxxxx" 
                                   required pattern="[0-9]{10,13}"
                                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                    </div>

                    <div style="margin-top: 25px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 15px;">
                            💾 Simpan Perubahan
                        </button>
                        <a href="data-rw.php" class="btn btn-secondary" style="padding: 12px 30px; font-size: 15px; text-decoration: none; display: inline-block;">
                            ❌ Batal
                        </a>
                    </div>
                </form>
            </div>

            <div class="table-container" style="margin-top: 20px;">
                <div style="padding: 20px 25px; background: #f8fafc;">
                    <h4 style="color: #1e293b; margin: 0 0 10px 0;">ℹ️ Informasi</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #64748b; line-height: 1.8;">
                        <li><strong>Username Login:</strong> Digunakan untuk masuk ke sistem</li>
                        <li><strong>Nama Lengkap:</strong> Nama lengkap petugas RW</li>
                        <li><strong>RW & RT:</strong> Wilayah yang dikelola</li>
                        <li><strong>Password:</strong> Kosongkan jika tidak ingin mengubah password</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script>
        function validateForm() {
            const password = document.getElementById('password').value;
            const noHp = document.getElementById('no_hp').value.trim();
            const username = document.getElementById('username_login').value.trim();

            if (username.length < 4) {
                alert('Username minimal 4 karakter!');
                return false;
            }

            if (password.length > 0 && password.length < 4) {
                alert('Password minimal 4 karakter!');
                return false;
            }

            if (!/^[0-9]{10,13}$/.test(noHp)) {
                alert('Nomor HP harus 10-13 digit angka!');
                return false;
            }

            return true;
        }

        // Auto-focus ke field pertama
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username_login').focus();
        });
    </script>
</body>
</html>