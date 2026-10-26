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

// Tambah Data RW
if (isset($_POST['tambah'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nama = trim($_POST['nama_lengkap']);
    $rw = trim($_POST['rw']);
    $rt = trim($_POST['rt']);
    $no_hp = trim($_POST['no_hp']);

    // Validasi
    if (empty($username) || empty($password) || empty($nama) || empty($rw) || empty($rt)) {
        $error_msg = "Semua field wajib diisi!";
    } elseif (strlen($password) < 4) {
        $error_msg = "Password minimal 4 karakter!";
    } elseif (!preg_match('/^[0-9]{10,13}$/', $no_hp)) {
        $error_msg = "Format No HP tidak valid (10–13 digit)!";
    } else {
        // Cek username
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($check, "s", $username);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error_msg = "Username sudah digunakan!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            mysqli_begin_transaction($conn);
            try {
                // Insert ke tabel users
                $stmt1 = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, 'rw')");
                if ($stmt1 === false) {
                    throw new Exception("Error prepare users: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt1, "ss", $username, $hashed_password);
                if (!mysqli_stmt_execute($stmt1)) {
                    throw new Exception("Gagal menambahkan user: " . mysqli_stmt_error($stmt1));
                }

                $user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt1);

                // Insert ke tabel rw dengan kolom RT
                // Sesuaikan dengan struktur tabel: user_id, nama_rw, username, password, rt, no_hp
                $stmt2 = mysqli_prepare($conn, "INSERT INTO rw (user_id, nama_rw, username, password, rt, no_hp) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt2 === false) {
                    throw new Exception("Error prepare rw: " . mysqli_error($conn));
                }
                
                // nama di nama_rw, RW di username, RT di kolom rt
                mysqli_stmt_bind_param($stmt2, "isssss", $user_id, $nama, $rw, $hashed_password, $rt, $no_hp);
                if (!mysqli_stmt_execute($stmt2)) {
                    throw new Exception("Gagal menambahkan data RW: " . mysqli_stmt_error($stmt2));
                }

                mysqli_stmt_close($stmt2);
                mysqli_commit($conn);
                $success_msg = "Data RW berhasil ditambahkan!";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_msg = $e->getMessage();
            }
        }
        mysqli_stmt_close($check);
    }
}

// Hapus Data RW
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_begin_transaction($conn);

    try {
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM rw WHERE id=?");
        if ($stmt === false) {
            throw new Exception("Error prepare: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$data) throw new Exception("Data RW tidak ditemukan!");

        $stmt1 = mysqli_prepare($conn, "DELETE FROM rw WHERE id=?");
        if ($stmt1 === false) {
            throw new Exception("Error prepare delete rw: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt1, "i", $id);
        if (!mysqli_stmt_execute($stmt1)) {
            throw new Exception("Gagal menghapus data RW: " . mysqli_stmt_error($stmt1));
        }
        mysqli_stmt_close($stmt1);

        $stmt2 = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        if ($stmt2 === false) {
            throw new Exception("Error prepare delete users: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt2, "i", $data['user_id']);
        if (!mysqli_stmt_execute($stmt2)) {
            throw new Exception("Gagal menghapus user: " . mysqli_stmt_error($stmt2));
        }
        mysqli_stmt_close($stmt2);

        mysqli_commit($conn);
        $success_msg = "Data RW berhasil dihapus!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}

// Ambil data RW
$query = "
    SELECT r.*, u.username AS user_username
    FROM rw r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE u.role = 'rw' 
    ORDER BY r.username ASC, r.nama_rw ASC
";
$result = mysqli_query($conn, $query);
if (!$result) {
    $error_msg = "Gagal mengambil data RW: " . mysqli_error($conn);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Data RW - Jumantik</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* === TABLE CONTAINER === */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-container th,
        .table-container td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table-container th {
            background: #f8fafc;
            font-weight: 600;
            color: #1e293b;
        }
        
        /* === MOBILE MENU TOGGLE === */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1002;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: background 0.3s ease;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        
        .menu-toggle:hover {
            background: #5568d3;
        }
        
        .menu-toggle:active {
            background: #4458c2;
        }
        
        /* Compact action buttons for table rows */
        .table-container td .btn,
        .table-container td a.btn {
            padding: 6px 10px;
            font-size: 13px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            min-width: 56px;
        }

        /* Explicit small button helper */
        .btn-sm {
            padding: 6px 10px !important;
            font-size: 13px !important;
            border-radius: 8px !important;
        }

        /* Desktop: action cell shows buttons side-by-side */
        .table-container td.actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        @media (max-width: 768px) {
            /* Mobile: only action cell stacks buttons vertically */
            .table-container td.actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
                padding-top: 6px;
            }

            .table-container td.actions .btn,
            .table-container td.actions a.btn {
                display: block !important;
                width: 100% !important;
                min-width: 0 !important;
                padding: 10px;
                font-size: 0.95rem;
                box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            }
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: block;
            }
            
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 280px;
                max-width: 85%;
                position: fixed;
                left: -100%;
                z-index: 1001;
                transition: left 0.3s ease;
                height: 100vh;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .content {
                width: 100%;
                margin-left: 0;
                padding: 15px;
                padding-top: 70px;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                height: 100vh;
                position: relative;
                z-index: 1;
            }
            
            body {
                overflow-x: hidden;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .header h1 {
                font-size: 1.2rem;
            }
            
            /* Table responsive improvements */
            .table-container {
                position: relative;
                z-index: 1;
                margin: 0 -15px;
                padding: 0;
            }
            
            .table-container > div:last-child {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding: 0 15px;
            }
            
            .table-container table {
                font-size: 0.85rem;
                min-width: 800px;
                width: 100%;
                margin: 0;
            }
            
            .table-container th,
            .table-container td {
                padding: 10px 8px;
                white-space: nowrap;
                font-size: 0.85rem;
            }
            
            .table-container th {
                font-size: 0.8rem;
                padding: 12px 8px;
                background: #f8fafc;
                position: sticky;
                left: 0;
            }
            
            
            /* Button improvements */
            .btn {
                width: 100%;
                margin: 5px 0;
                padding: 10px;
                font-size: 0.9rem;
            }
            
            form > div[style*="grid"] {
                grid-template-columns: 1fr !important;
            }
            
            /* Form improvements */
            input, select {
                font-size: 16px !important; /* Prevent zoom on iOS */
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1rem;
            }
            
            .table-container {
                margin: 0 -10px;
                padding: 0 10px;
            }
            
            .table-container table {
                font-size: 0.75rem;
                min-width: 700px;
            }
            
            .table-container th,
            .table-container td {
                padding: 8px 6px;
                font-size: 0.75rem;
            }
            
            .table-container th {
                font-size: 0.7rem;
                padding: 10px 6px;
            }
            
            .content {
                padding: 10px;
                padding-top: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <h2>🦟 Jumantik</h2>
            <ul>
                <li><a href="dashboard-admin.php">🏠 Dashboard</a></li>
                <li><a href="data-kader.php">👩‍💼 Data Kader</a></li>
                <li><a href="data-rw.php" class="active">🏘️ Data RW</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="header">
                <h1>🏘️ Data RW</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
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

            <div class="table-container" style="margin-bottom: 30px;">
                <div style="padding: 20px 25px; border-bottom: 2px solid #e2e8f0;">
                    <h3 style="color: #1e293b; font-size: 1.3rem; margin: 0;">➕ Tambah Data RW</h3>
                </div>
                <form method="POST" action="" onsubmit="return validateForm()" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">Username <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="username" id="username" placeholder="Masukkan username" required minlength="4" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">Password <span style="color: #dc2626;">*</span></label>
                            <input type="password" name="password" id="password" placeholder="Min 4 karakter" required minlength="4" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">Nama Lengkap <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="nama_lengkap" placeholder="Nama lengkap RW" required style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">RW <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="rw" placeholder="Contoh: RW 01" required style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">RT <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="rt" placeholder="Contoh: RT 05" required style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 500;">No HP <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="no_hp" id="no_hp" placeholder="08xxxxxxxxxx" required pattern="[0-9]{10,13}" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="tambah" class="btn btn-primary" style="margin-top: 20px; padding: 12px 30px; font-size: 15px;">
                        ➕ Tambah Data RW
                    </button>
                </form>
            </div>

            <div class="table-container">
                <div style="padding: 20px 25px; border-bottom: 2px solid #e2e8f0;">
                    <h3 style="color: #1e293b; font-size: 1.3rem; margin: 0;">📋 Daftar RW</h3>
                </div>
                <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; position: relative;">
                <table style="min-width: 800px; width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>USERNAME</th>
                            <th>NAMA LENGKAP</th>
                            <th>RW</th>
                            <th>RT</th>
                            <th>NO HP</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($row['user_username']); ?></td>
                                <td><?= htmlspecialchars($row['nama_rw']); ?></td>
                                <td><?= htmlspecialchars($row['username']); ?></td>
                                <td><?= htmlspecialchars($row['rt'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($row['no_hp']); ?></td>
                                <td class="actions">
                                    <a href="edit-rw.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                                    <a href="?hapus=<?= $row['id']; ?>" 
                                       onclick="return confirm('Yakin ingin menghapus RW <?= htmlspecialchars($row['nama_rw']); ?>?\nData tidak dapat dikembalikan!')" 
                                       class="btn btn-danger btn-sm">🗑️ Hapus</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">Belum ada data RW</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function validateForm() {
            const password = document.getElementById('password').value;
            const noHp = document.getElementById('no_hp').value.trim();

            if (password.length < 4) {
                alert('Password minimal 4 karakter!');
                return false;
            }
            if (!/^[0-9]{10,13}$/.test(noHp)) {
                alert('Nomor HP harus 10-13 digit angka!');
                return false;
            }
            return true;
        }
    </script>
    
    <!-- Hamburger Menu Button -->
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        // Close sidebar when clicking overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    toggleSidebar();
                }
            });
        }

        // Close sidebar when clicking sidebar links on mobile
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Ensure table is scrollable on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const tableContainers = document.querySelectorAll('.table-container');
            tableContainers.forEach(container => {
                // Add scroll indicator
                if (window.innerWidth <= 768) {
                    container.style.position = 'relative';
                }
            });
        });
    </script>
</body>
</html>