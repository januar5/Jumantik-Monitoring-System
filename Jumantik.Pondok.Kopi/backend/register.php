<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = clean_input($_POST['nama_lengkap']);
    $wilayah = clean_input($_POST['wilayah_kerja']);
    $status = clean_input($_POST['status']);
    $telepon = clean_input($_POST['nomor_telepon']);
    $username = clean_input($_POST['username']);
    $password = md5($_POST['password']);
    
    // Cek username sudah ada
    $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $error = 'Username sudah digunakan!';
    } else {
        // Insert user
        $role = ($status == 'RW') ? 'rw' : 'petugas';
        $query_user = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')";
        
        if (mysqli_query($conn, $query_user)) {
            $user_id = mysqli_insert_id($conn);
            
            // Insert petugas
            $query_petugas = "INSERT INTO petugas (user_id, nama_lengkap, wilayah_kerja, status, nomor_telepon) 
                             VALUES ('$user_id', '$nama', '$wilayah', '$status', '$telepon')";
            
            if (mysqli_query($conn, $query_petugas)) {
                $success = 'Pendaftaran berhasil! Silahkan login.';
            }
        } else {
            $error = 'Pendaftaran gagal! Silahkan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Jumantik Pondok Kopi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-brand">
                <i class="fas fa-bug"></i> Jumantik Pondok Kopi
            </div>
            <div class="auth-content">
                <h1>Halo,<br>Selamat Datang !</h1>
                <p>Untuk mengisi data Jumantik harap masuk sebagai <strong>Petugas Jumantik</strong> atau <strong>Rukun Warga (RW)</strong>.</p>
            </div>
        </div>
        
        <div class="auth-right">
            <div class="auth-box">
                <h2>Daftar</h2>
                <p class="auth-subtitle">Silahkan masukan data anda</p>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Wilayah Kerja</label>
                        <div class="input-row">
                            <select class="form-control" style="width: 30%;">
                                <option>RT</option>
                            </select>
                            <input type="text" name="wilayah_kerja" class="form-control" placeholder="01/RW 05" style="width: 65%;" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="tel" name="nomor_telepon" class="form-control" placeholder="08xxxxxxxxxx" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" required>
                            <option value="">Pilih Status</option>
                            <option value="RW">RW</option>
                            <option value="Petugas">Petugas</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Daftar
                    </button>
                </form>
                
                <p class="auth-footer">
                    Sudah punya akun? <a href="login.php">Masuk</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>