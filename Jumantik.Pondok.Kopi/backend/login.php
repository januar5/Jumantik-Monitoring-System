<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validasi input kosong
    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        // Gunakan prepared statement untuk keamanan
        $query = "SELECT * FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                // Verifikasi password - PERBAIKAN DI SINI
                $password_valid = false;
                
                // Cek apakah password sudah di-hash atau masih plaintext
                if (password_get_info($user['password'])['algo'] !== null) {
                    // Password sudah di-hash, gunakan password_verify
                    $password_valid = password_verify($password, $user['password']);
                } else {
                    // Password masih plaintext (backward compatibility)
                    $password_valid = ($password === $user['password']);
                    
                    // Update ke hash jika login berhasil
                    if ($password_valid) {
                        $hashed = password_hash($password, PASSWORD_BCRYPT);
                        $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                        mysqli_stmt_bind_param($update_stmt, "si", $hashed, $user['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                }
                
                if ($password_valid) {
                    // Set session dasar
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Ambil data lengkap berdasarkan role
                    if ($user['role'] == 'rw') {
                        // Ambil data dari tabel rw
                        $query_rw = "SELECT * FROM rw WHERE user_id = ?";
                        $stmt_rw = mysqli_prepare($conn, $query_rw);
                        mysqli_stmt_bind_param($stmt_rw, "i", $user['id']);
                        mysqli_stmt_execute($stmt_rw);
                        $result_rw = mysqli_stmt_get_result($stmt_rw);
                        
                        if ($data_rw = mysqli_fetch_assoc($result_rw)) {
                            $_SESSION['nama'] = $data_rw['nama_rw'];
                            $_SESSION['rw'] = $data_rw['username'];
                            $_SESSION['rt'] = $data_rw['rt'];
                            $_SESSION['no_hp'] = $data_rw['no_hp'];
                        } else {
                            $_SESSION['nama'] = $user['username'];
                        }
                        mysqli_stmt_close($stmt_rw);
                        
                    } elseif ($user['role'] == 'kader') {
                        // Ambil data dari tabel kader
                        $query_kader = "SELECT * FROM kader WHERE user_id = ?";
                        $stmt_kader = mysqli_prepare($conn, $query_kader);
                        mysqli_stmt_bind_param($stmt_kader, "i", $user['id']);
                        mysqli_stmt_execute($stmt_kader);
                        $result_kader = mysqli_stmt_get_result($stmt_kader);
                        
                        if ($data_kader = mysqli_fetch_assoc($result_kader)) {
                            $_SESSION['nama'] = $data_kader['nama_kader'];
                            $_SESSION['rw'] = $data_kader['rw'];
                            $_SESSION['rt'] = $data_kader['rt'];
                            $_SESSION['no_hp'] = $data_kader['no_hp'];
                        } else {
                            $_SESSION['nama'] = $user['username'];
                        }
                        mysqli_stmt_close($stmt_kader);
                        
                    } else {
                        // Untuk admin atau role lainnya
                        if (isset($user['nama']) && !empty($user['nama'])) {
                            $_SESSION['nama'] = $user['nama'];
                        } elseif (isset($user['nama_lengkap']) && !empty($user['nama_lengkap'])) {
                            $_SESSION['nama'] = $user['nama_lengkap'];
                        } elseif (isset($user['full_name']) && !empty($user['full_name'])) {
                            $_SESSION['nama'] = $user['full_name'];
                        } else {
                            $_SESSION['nama'] = $user['username'];
                        }
                    }

                    // Redirect sesuai role
                    switch ($user['role']) {
                        case 'admin':
                            header("Location: dashboard-admin.php");
                            break;
                        case 'kader':
                            header("Location: dashboard-kader.php");
                            break;
                        case 'rw':
                            header("Location: dashboard-rw.php");
                            break;
                        default:
                            $error = "Role tidak dikenali!";
                    }
                    exit();
                } else {
                    $error = "Username atau password salah!";
                }
            } else {
                $error = "Username atau password salah!";
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Jumantik Pondok Kopi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-danger {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            color: #555;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"] {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        button[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .footer-info {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>🦟 Login Sistem Jumantik Pondok Kopi</h2>

        <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required autofocus placeholder="Masukkan username">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="Masukkan password">

            <button type="submit">🔐 Masuk</button>
        </form>

        <div class="footer-info">
            Sistem Pemantauan Jentik Nyamuk<br>
            Kelurahan Pondok Kopi
        </div>
    </div>
</body>
</html>