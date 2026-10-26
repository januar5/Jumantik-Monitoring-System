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

// Tangani pesan success dari redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'delete') {
        $success_msg = "Kader berhasil dihapus!";
    } elseif ($_GET['success'] == 'add') {
        $success_msg = "Kader berhasil ditambahkan!";
    }
    elseif ($_GET['success'] == 'edit') {
    $success_msg = "Data kader berhasil diperbarui!";
    }
}

// Tambah Data
if (isset($_POST['tambah'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nama = trim($_POST['nama_lengkap']);
    $rt = trim($_POST['rt']);
    $rw = trim($_POST['rw']);
    $no_hp = trim($_POST['no_hp']);

    // Validasi input
    if (empty($username) || empty($password) || empty($nama)) {
        $error_msg = "Username, password, dan nama lengkap wajib diisi!";
    } elseif (strlen($password) < 4) {
        $error_msg = "Password minimal 4 karakter!";
    } elseif (!preg_match('/^[0-9]{10,13}$/', $no_hp)) {
        $error_msg = "Format No HP tidak valid (10-13 digit)!";
    } else {
        // Cek username sudah ada atau belum
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($check, "s", $username);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        
        if (mysqli_stmt_num_rows($check) > 0) {
            $error_msg = "Username sudah digunakan! Silakan gunakan username lain.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            mysqli_autocommit($conn, FALSE);
            
            try {
                // Insert user
                $stmt1 = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, 'kader')");
                mysqli_stmt_bind_param($stmt1, "ss", $username, $hashed_password);
                
                if (!mysqli_stmt_execute($stmt1)) {
                    throw new Exception("Gagal menambahkan user: " . mysqli_error($conn));
                }
                
                $user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt1);
                
                // Insert ke tabel kader
                $stmt2 = mysqli_prepare($conn, "INSERT INTO kader (user_id, nama_kader, rt, rw, no_hp) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt2, "issss", $user_id, $nama, $rt, $rw, $no_hp);
                
                if (!mysqli_stmt_execute($stmt2)) {
                    throw new Exception("Gagal menambahkan data kader: " . mysqli_error($conn));
                }
                
                mysqli_stmt_close($stmt2);
                
                if (!mysqli_commit($conn)) {
                    throw new Exception("Gagal commit transaksi: " . mysqli_error($conn));
                }
                
                mysqli_autocommit($conn, TRUE);
                header("Location: data-kader.php?success=add");
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

// Hapus Data
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    mysqli_autocommit($conn, FALSE);
    
    try {
        $stmt = mysqli_prepare($conn, "SELECT user_id, nama_kader FROM kader WHERE id=?");
        if (!$stmt) {
            throw new Exception("Error prepare statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error execute statement: " . mysqli_error($conn));
        }
        
        $result_hapus = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result_hapus);
        mysqli_stmt_close($stmt);
        
        if (!$data) {
            throw new Exception("Data kader tidak ditemukan!");
        }
        
        $user_id = $data['user_id'];
        
        $stmt_kader = mysqli_prepare($conn, "DELETE FROM kader WHERE id=?");
        if (!$stmt_kader) {
            throw new Exception("Error prepare delete kader: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt_kader, "i", $id);
        
        if (!mysqli_stmt_execute($stmt_kader)) {
            throw new Exception("Gagal menghapus data kader: " . mysqli_error($conn));
        }
        
        $affected_kader = mysqli_stmt_affected_rows($stmt_kader);
        mysqli_stmt_close($stmt_kader);
        
        if ($affected_kader == 0) {
            throw new Exception("Data kader tidak ditemukan atau sudah dihapus!");
        }
        
        $stmt_user = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        if (!$stmt_user) {
            throw new Exception("Error prepare delete user: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        
        if (!mysqli_stmt_execute($stmt_user)) {
            throw new Exception("Gagal menghapus user: " . mysqli_error($conn));
        }
        
        mysqli_stmt_close($stmt_user);
        
        if (!mysqli_commit($conn)) {
            throw new Exception("Gagal commit transaksi: " . mysqli_error($conn));
        }
        
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        mysqli_autocommit($conn, TRUE);
        
        header("Location: data-kader.php?success=delete");
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        mysqli_autocommit($conn, TRUE);
        $error_msg = "ERROR: " . $e->getMessage();
    }
}

// Ambil data kader dikelompokkan per RW
$query_grouped = "SELECT k.*, u.username 
                  FROM kader k 
                  LEFT JOIN users u ON k.user_id = u.id 
                  WHERE u.role = 'kader' 
                  ORDER BY k.rw ASC, k.rt ASC, k.nama_kader ASC";
$result = mysqli_query($conn, $query_grouped);

// Kelompokkan data per RW
$data_per_rw = [];
if ($result && mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $rw = $row['rw'];
        if (!isset($data_per_rw[$rw])) {
            $data_per_rw[$rw] = [];
        }
        $data_per_rw[$rw][] = $row;
    }
}

// Urutkan RW
ksort($data_per_rw);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Data Kader Jumantik</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
        
        @media screen and (max-width: 768px) {
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
            
            /* Pastikan konten tidak ter-trigger oleh overlay */
            .rw-section,
            .rw-header,
            .filter-section,
            .table-container {
                position: relative;
                z-index: 1;
            }
            
            .form-grid {
                grid-template-columns: 1fr !important;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: row;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .action-buttons a {
                flex: 1;
                min-width: auto;
                text-align: center;
                padding: 8px 12px !important;
                font-size: 0.85rem !important;
                white-space: nowrap;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .header h1 {
                font-size: 1.2rem;
            }
            
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table-container table {
                font-size: 0.85rem;
                min-width: 700px;
            }
            
            .table-container th,
            .table-container td {
                padding: 8px 6px;
                white-space: nowrap;
            }
            
            .filter-section {
                padding: 15px;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .filter-btn {
                width: 100%;
            }
            
            .rw-header {
                padding: 12px 15px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1rem;
            }
            
            .table-container table {
                font-size: 0.75rem;
            }
        }
        
        /* Style untuk grouping RW */
        .rw-section {
            margin-bottom: 30px;
        }
        
        .rw-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
            touch-action: manipulation;
            -webkit-tap-highlight-color: rgba(255,255,255,0.1);
            user-select: none;
        }
        
        .rw-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .rw-header:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }
        
        .rw-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .table-container.rw-table {
            margin-top: 0;
            border-radius: 0 0 10px 10px;
        }
        
        .collapse-icon {
            transition: transform 0.3s ease;
        }
        
        .collapsed .collapse-icon {
            transform: rotate(-90deg);
        }
        
        .rw-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .rw-content:not(.collapsed) {
            max-height: 2000px;
        }
        
        .rw-content.collapsed {
            max-height: 0;
        }
        
        /* Filter RW */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: #667eea;
            color: white;
        }
        
        /* === ACTION BUTTONS === */
        .action-buttons {
            display: flex;
            flex-direction: row;
            gap: 6px;
            flex-wrap: nowrap;
            justify-content: center;
            align-items: center;
        }
        
        .action-buttons .btn {
            padding: 6px 10px !important;
            font-size: 1rem !important;
            white-space: nowrap;
            min-width: 36px;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        
        .action-buttons .btn-warning {
            padding: 6px 10px !important;
        }
        
        .action-buttons .btn-danger {
            padding: 6px 10px !important;
        }
        
        /* Mobile: button sedikit lebih besar untuk touch */
        @media (max-width: 768px) {
            .action-buttons {
                gap: 8px;
            }
            
            .action-buttons .btn {
                padding: 8px !important;
                font-size: 1.1rem !important;
                min-width: 40px;
                width: 40px;
                height: 40px;
            }
        }
        
        /* Desktop: button compact */
        @media (min-width: 769px) {
            .action-buttons {
                gap: 5px;
            }
            
            .action-buttons .btn {
                padding: 5px !important;
                font-size: 0.95rem !important;
                min-width: 32px;
                width: 32px;
                height: 32px;
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
                <li><a href="logout.php">🚪 Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <!-- Header -->
            <div class="header">
                <h1>👩‍💼 Data Kader Jumantik</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>

            <!-- Alert Messages -->
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

            <!-- Form Tambah Kader -->
            <form method="POST" action="" onsubmit="return validateForm()">
                <h3 style="margin-bottom: 20px; color: #1e293b; font-size: 1.3rem;">➕ Tambah Kader Baru</h3>
                
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <input type="text" name="username" id="username" placeholder="Username (harus unik)" required minlength="4" maxlength="50">
                    <input type="password" name="password" id="password" placeholder="Password (min 4 karakter)" required minlength="4" maxlength="100">
                    <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" required maxlength="100">
                    <input type="text" name="rt" placeholder="RT (contoh: RT 01)" required maxlength="10">
                    <input type="text" name="rw" placeholder="RW (contoh: RW 01)" required maxlength="10">
                    <input type="tel" name="no_hp" id="no_hp" placeholder="No HP (08xxx)" required pattern="[0-9]{10,13}" title="Nomor HP harus 10-13 digit angka">
                </div>
                
                <button type="submit" name="tambah" class="btn btn-primary" style="margin-top: 15px; width: 100%;">Tambah Kader</button>
            </form>

            <!-- Filter Section -->
            <div class="filter-section">
                <h3 style="margin: 0 0 10px 0; color: #1e293b;">🔍 Filter RW</h3>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterRW('all')">Semua RW</button>
                    <button class="filter-btn" onclick="toggleAllRW()">Expand/Collapse All</button>
                    <?php foreach($data_per_rw as $rw => $kaders): ?>
                    <button class="filter-btn" onclick="filterRW('<?= htmlspecialchars($rw); ?>')"><?= htmlspecialchars($rw); ?> (<?= count($kaders); ?>)</button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Data Kader Per RW -->
            <?php if (!empty($data_per_rw)): ?>
                <?php foreach($data_per_rw as $rw => $kaders): ?>
                <div class="rw-section collapsed" data-rw="<?= htmlspecialchars($rw); ?>">
                    <div class="rw-header" onclick="toggleRW(this, event)">
                        <div>
                            <span class="collapse-icon">▼</span>
                            🏘️ <?= htmlspecialchars($rw); ?>
                        </div>
                        <span class="rw-count"><?= count($kaders); ?> Kader</span>
                    </div>
                    <div class="rw-content collapsed">
                        <div class="table-container rw-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>NO</th>
                                        <th>USERNAME</th>
                                        <th>NAMA LENGKAP</th>
                                        <th>RT</th>
                                        <th>NO HP</th>
                                        <th>AKSI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach($kaders as $row): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['username']); ?></td>
                                        <td><?= htmlspecialchars($row['nama_kader']); ?></td>
                                        <td><?= htmlspecialchars($row['rt']); ?></td>
                                        <td><?= htmlspecialchars($row['no_hp']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit-kader.php?id=<?= $row['id']; ?>" class="btn btn-warning" title="Edit Kader">✏️</a>
                                                <a href="?hapus=<?= $row['id']; ?>" 
                                                   onclick="return confirm('⚠️ PERHATIAN!\n\nYakin ingin menghapus kader:\n<?= htmlspecialchars($row['nama_kader']); ?>?\n\nData yang dihapus TIDAK DAPAT dikembalikan!')" 
                                                   class="btn btn-danger" title="Hapus Kader">🗑️</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="table-container">
                    <div style="text-align: center; padding: 40px; color: #94a3b8; font-style: italic;">
                        📭 Belum ada data kader. Silakan tambahkan kader baru di atas.
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Hamburger Menu & Overlay -->
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

        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Close sidebar when clicking overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    toggleSidebar();
                }
            });
        }
        
        // Pastikan semua section RW tertutup saat page load
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.rw-section');
            sections.forEach(section => {
                const content = section.querySelector('.rw-content');
                if (content && !content.classList.contains('collapsed')) {
                    content.classList.add('collapsed');
                }
                if (!section.classList.contains('collapsed')) {
                    section.classList.add('collapsed');
                }
            });
        });

        function validateForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const noHp = document.getElementById('no_hp').value.trim();
            
            if (username.length < 4) {
                alert('⚠️ Username minimal 4 karakter!');
                return false;
            }
            
            if (password.length < 4) {
                alert('⚠️ Password minimal 4 karakter!');
                return false;
            }
            
            if (!/^[0-9]{10,13}$/.test(noHp)) {
                alert('⚠️ Nomor HP harus 10-13 digit angka!\nContoh: 08123456789');
                return false;
            }
            
            return true;
        }

        // Toggle RW Section
        function toggleRW(header, event) {
            // Prevent event bubbling untuk mobile jika event ada
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            const content = header.nextElementSibling;
            const section = header.parentElement;
            if (content && section) {
                content.classList.toggle('collapsed');
                section.classList.toggle('collapsed');
            }
            
            return false;
        }
        
        // Handle touch events untuk mobile
        document.addEventListener('DOMContentLoaded', function() {
            const rwHeaders = document.querySelectorAll('.rw-header');
            rwHeaders.forEach(header => {
                // Prevent double-tap zoom dan handle touch
                let lastTap = 0;
                header.addEventListener('touchend', function(e) {
                    e.stopPropagation();
                    const currentTime = new Date().getTime();
                    const tapLength = currentTime - lastTap;
                    if (tapLength < 300 && tapLength > 0) {
                        e.preventDefault();
                        toggleRW(this, e);
                    } else {
                        lastTap = currentTime;
                        setTimeout(() => {
                            if (new Date().getTime() - lastTap >= 300) {
                                toggleRW(this, e);
                            }
                        }, 300);
                    }
                }, { passive: false });
                
                // Ensure click works on mobile dan desktop
                header.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleRW(this, e);
                });
            });
        });

        // Filter RW
        function filterRW(rw) {
            const sections = document.querySelectorAll('.rw-section');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.includes(rw) || (rw === 'all' && btn.textContent === 'Semua RW')) {
                    btn.classList.add('active');
                }
            });
            
            // Show/hide sections
            sections.forEach(section => {
                if (rw === 'all') {
                    section.style.display = 'block';
                    section.querySelector('.rw-content').classList.remove('collapsed');
                    section.classList.remove('collapsed');
                } else {
                    if (section.dataset.rw === rw) {
                        section.style.display = 'block';
                        section.querySelector('.rw-content').classList.remove('collapsed');
                        section.classList.remove('collapsed');
                    } else {
                        section.style.display = 'none';
                    }
                }
            });
        }

        // Toggle All RW
        function toggleAllRW() {
            const sections = document.querySelectorAll('.rw-section');
            const allCollapsed = Array.from(sections).every(s => s.classList.contains('collapsed'));
            
            sections.forEach(section => {
                const content = section.querySelector('.rw-content');
                if (allCollapsed) {
                    content.classList.remove('collapsed');
                    section.classList.remove('collapsed');
                } else {
                    content.classList.add('collapsed');
                    section.classList.add('collapsed');
                }
            });
        }

        setTimeout(function() {
            const alerts = document.querySelectorAll('.welcome');
            alerts.forEach(function(alert) {
                if (alert.textContent.includes('✅') || alert.textContent.includes('❌')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }
            });
        }, 5000);

        if (window.location.search.includes('success=')) {
            setTimeout(function() {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, document.title, url.pathname);
            }, 100);
        }
    </script>
</body>
</html>