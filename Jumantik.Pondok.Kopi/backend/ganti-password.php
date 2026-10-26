<?php
session_start();
require_once 'config.php';

// Cek autentikasi admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Proses ganti password
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';
    
    // Validasi input
    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $error = 'Semua field harus diisi!';
    } elseif ($password_baru !== $konfirmasi_password) {
        $error = 'Password baru dan konfirmasi password tidak cocok!';
    } elseif (strlen($password_baru) < 6) {
        $error = 'Password baru minimal 6 karakter!';
    } else {
        // Ambil data admin dari database
        $username = $_SESSION['username'];
        $query = "SELECT password FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        // Cek error prepare statement
        if ($stmt === false) {
            $error = 'Terjadi kesalahan sistem: ' . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($result);
            
            // Cek apakah user ditemukan
            if (!$admin) {
                $error = 'User tidak ditemukan!';
            } else {
                // Verifikasi password lama
                if (password_verify($password_lama, $admin['password'])) {
                    // Hash password baru
                    $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                    
                    // Update password di database
                    $update_query = "UPDATE users SET password = ? WHERE username = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    
                    // Cek error prepare statement
                    if ($update_stmt === false) {
                        $error = 'Terjadi kesalahan sistem: ' . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($update_stmt, 'ss', $password_hash, $username);
                        
                        if (mysqli_stmt_execute($update_stmt)) {
                            $success = 'Password berhasil diubah! Anda akan diarahkan ke dashboard...';
                            // Redirect setelah 2 detik
                            header("refresh:2;url=dashboard-admin.php");
                        } else {
                            $error = 'Gagal mengubah password. Silakan coba lagi.';
                        }
                        mysqli_stmt_close($update_stmt);
                    }
                } else {
                    $error = 'Password lama tidak sesuai!';
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password Admin | Jumantik Pondok Kopi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #1e293b;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 1.2rem;
            user-select: none;
        }
        
        .toggle-password:hover {
            color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .password-requirements {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .password-requirements h4 {
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .password-requirements ul {
            list-style: none;
            padding: 0;
        }
        
        .password-requirements li {
            color: #64748b;
            font-size: 0.85rem;
            padding: 5px 0;
            padding-left: 20px;
            position: relative;
        }
        
        .password-requirements li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #667eea;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🔐</div>
            <h1>Ganti Password</h1>
            <p>Admin - <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin'); ?></p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="changePasswordForm">
            <div class="form-group">
                <label for="password_lama">Password Lama</label>
                <div class="password-wrapper">
                    <input type="password" id="password_lama" name="password_lama" required>
                    <span class="toggle-password" onclick="togglePassword('password_lama')">👁️</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password_baru">Password Baru</label>
                <div class="password-wrapper">
                    <input type="password" id="password_baru" name="password_baru" required minlength="6">
                    <span class="toggle-password" onclick="togglePassword('password_baru')">👁️</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                <div class="password-wrapper">
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" required minlength="6">
                    <span class="toggle-password" onclick="togglePassword('konfirmasi_password')">👁️</span>
                </div>
            </div>
            
            <div class="password-requirements">
                <h4>📋 Persyaratan Password:</h4>
                <ul>
                    <li>Minimal 6 karakter</li>
                    <li>Gunakan kombinasi huruf dan angka</li>
                    <li>Hindari password yang mudah ditebak</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-primary" id="submitBtn">💾 Simpan Password Baru</button>
            <a href="dashboard-admin.php" class="btn btn-secondary" style="display: block; text-align: center; text-decoration: none;">
                ← Kembali ke Dashboard
            </a>
        </form>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.textContent = '🙈';
            } else {
                field.type = 'password';
                icon.textContent = '👁️';
            }
        }
        
        // Validasi real-time
        const passwordBaru = document.getElementById('password_baru');
        const konfirmasiPassword = document.getElementById('konfirmasi_password');
        const submitBtn = document.getElementById('submitBtn');
        
        function validatePasswords() {
            if (konfirmasiPassword.value !== '' && passwordBaru.value !== '') {
                if (konfirmasiPassword.value !== passwordBaru.value) {
                    konfirmasiPassword.style.borderColor = '#dc2626';
                    submitBtn.disabled = true;
                } else {
                    konfirmasiPassword.style.borderColor = '#16a34a';
                    submitBtn.disabled = false;
                }
            } else {
                konfirmasiPassword.style.borderColor = '#e2e8f0';
                submitBtn.disabled = false;
            }
        }
        
        passwordBaru.addEventListener('input', validatePasswords);
        konfirmasiPassword.addEventListener('input', validatePasswords);
        
        // Validasi panjang password
        passwordBaru.addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                this.style.borderColor = '#dc2626';
            } else if (this.value.length >= 6) {
                this.style.borderColor = '#16a34a';
            } else {
                this.style.borderColor = '#e2e8f0';
            }
        });
        
        // Prevent form submission jika password tidak match
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            if (passwordBaru.value !== konfirmasiPassword.value) {
                e.preventDefault();
                alert('Password baru dan konfirmasi password tidak cocok!');
            }
        });
    </script>
</body>
</html>