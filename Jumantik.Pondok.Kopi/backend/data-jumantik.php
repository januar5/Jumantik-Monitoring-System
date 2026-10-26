<?php
session_start();
require_once 'config.php';

// Pastikan hanya kader yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kader') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data kader
$stmt = mysqli_prepare($conn, "SELECT * FROM kader WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kader = mysqli_fetch_assoc($result);

// Validasi jika kader tidak ditemukan
if (!$kader) {
    header("Location: login.php");
    exit;
}

$kader_id = $kader['id'];
$rt_kader = $kader['rt'] ?? '';
$rw_kader = $kader['rw'] ?? '';
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Jumantik | Sistem Jumantik Pondok Kopi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }

        .form-table-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 20px auto;
        }

        .form-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .form-subtitle {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }

        /* Table Form Style */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 2px solid #333;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #333;
            padding: 12px 15px;
            text-align: left;
        }

        .data-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            width: 200px;
        }

        .data-table td {
            background-color: white;
        }

        /* RT/RW Display - Read Only Style */
        .rt-rw-display {
            padding: 12px 15px;
            background-color: #f8f9fa;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            color: #333;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }

        .rt-rw-label {
            display: inline-block;
            padding: 8px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            margin-right: 10px;
        }

        /* Button Styles */
        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-select-small {
            padding: 8px 16px;
            border: 2px solid #333;
            background-color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            min-width: 50px;
            text-align: center;
        }

        .btn-select-small:hover {
            background-color: #e8e8e8;
        }

        .btn-select-small.active {
            background-color: #333;
            color: white;
        }

        /* Tatanan Buttons */
        .tatanan-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .tatanan-btn {
            padding: 10px 12px;
            border: 2px solid #333;
            background-color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            text-align: left;
        }

        .tatanan-btn:hover {
            background-color: #e8e8e8;
        }

        .tatanan-btn.active {
            background-color: #333;
            color: white;
        }

        /* Status Buttons */
        .status-btn-group {
            display: flex;
            gap: 15px;
        }

        .status-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #333;
            background-color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .status-btn i {
            font-size: 20px;
        }

        .status-btn:hover {
            background-color: #e8e8e8;
        }

        .status-btn.active.positive {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }

        .status-btn.active.negative {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        /* Form Controls */
        .form-control-table {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-control-table:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control-table {
            resize: vertical;
            min-height: 80px;
        }

        /* Submit Button */
        .submit-container {
            text-align: center;
            margin-top: 30px;
        }

        .btn-submit {
            padding: 12px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .required-mark {
            color: #dc3545;
            font-weight: bold;
        }

        /* Success Alert */
        .success-alert {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease;
            font-weight: 500;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-table-container {
                padding: 15px;
            }

            .data-table th {
                width: 150px;
                font-size: 13px;
            }

            .btn-select-small {
                padding: 6px 12px;
                font-size: 12px;
                min-width: 45px;
            }

            .tatanan-buttons {
                grid-template-columns: 1fr;
            }

            .status-btn-group {
                flex-direction: column;
            }
        }

        /* MOBILE RESPONSIVE FIX */
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }

            .sidebar {
                position: fixed;
                left: -260px;
                top: 0;
                width: 260px;
                height: 100%;
                z-index: 999;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .topbar h2 {
                font-size: 16px;
                text-align: center;
            }

            .form-table-container {
                padding: 15px;
                margin: 10px;
            }

            .data-table,
            .data-table tbody,
            .data-table tr,
            .data-table th,
            .data-table td {
                display: block;
                width: 100%;
            }

            .data-table tr {
                margin-bottom: 15px;
                border-bottom: 2px solid #ddd;
                padding-bottom: 10px;
            }

            .data-table th {
                background: none;
                border: none;
                padding: 5px 0;
                font-weight: bold;
                color: #333;
            }

            .data-table td {
                border: none;
                padding: 5px 0;
            }

            .btn-group {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 6px;
            }

            .btn-select-small {
                padding: 10px 0;
                font-size: 13px;
            }

            .tatanan-buttons {
                grid-template-columns: 1fr;
            }

            .tatanan-btn {
                font-size: 14px;
                padding: 12px;
            }

            .status-btn-group {
                flex-direction: column;
                gap: 10px;
            }

            .status-btn {
                font-size: 14px;
                padding: 14px;
            }

            .form-control-table {
                font-size: 14px;
                padding: 12px;
            }

            .btn-submit {
                width: 100%;
                padding: 14px;
                font-size: 16px;
            }

            .success-alert {
                font-size: 14px;
                padding: 12px;
            }

            .rt-rw-display {
                font-size: 16px;
                padding: 10px 12px;
            }
        }

        /* NAVBAR STYLES */
        .navbar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 700;
            text-decoration: none;
            color: white;
        }

        .navbar-brand span {
            color: #fbbf24;
        }

        .navbar-menu {
            display: flex;
            gap: 5px;
            list-style: none;
        }

        .navbar-menu li a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 15px;
        }

        .navbar-menu li a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .navbar-menu li a.active {
            background: rgba(251, 191, 36, 0.2);
            border: 2px solid #fbbf24;
        }

        .navbar-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 5px;
        }

        .navbar-toggle span {
            width: 28px;
            height: 3px;
            background: white;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .navbar-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .navbar-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .navbar-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        .navbar-mobile {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .navbar-mobile.active {
            max-height: 400px;
        }

        .navbar-mobile ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .navbar-mobile ul li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.05);
        }

        .navbar-mobile ul li a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .navbar-mobile ul li a.active {
            background: rgba(251, 191, 36, 0.2);
            border-left: 4px solid #fbbf24;
        }

        .dashboard {
            margin-top: 70px;
        }

        @media (max-width: 768px) {
            .navbar-menu {
                display: none;
            }

            .navbar-toggle {
                display: flex;
            }

            .navbar-mobile {
                display: block;
            }

            .navbar-brand {
                font-size: 20px;
            }

            .dashboard {
                margin-top: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Navbar -->
        <nav class="navbar">
            <div class="navbar-container">
                <a href="#" class="navbar-brand">
                    <span>🦟</span> Jumantik
                </a>

                <!-- Desktop Menu -->
                <ul class="navbar-menu">
                    <li><a href="dashboard-kader.php">🏠 Beranda</a></li>
                    <li><a href="data-jumantik.php" class="active">🔍 Data Jumantik</a></li>
                    <li><a href="laporan-kader.php">📋 Laporan</a></li>
                    <li><a href="logout.php">🚪 Keluar</a></li>
                </ul>

                <!-- Mobile Toggle -->
                <div class="navbar-toggle" onclick="toggleMobileMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </nav>
    
        <!-- Mobile Menu -->
        <div class="navbar-mobile" id="mobileMenu">
            <ul>
                <li><a href="dashboard-kader.php">🏠 Beranda</a></li>
                <li><a href="data-jumantik.php" class="active">🔍 Data Jumantik</a></li>
                <li><a href="laporan-kader.php">📋 Laporan</a></li>
                <li><a href="logout.php">🚪 Keluar</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="topbar">
                <h2>Form Rekapitulasi Kegiatan Pemantauan Jentik</h2>
                <div class="topbar-right"></div>
            </div>

            <?php if (isset($_GET['success'])): ?>
            <div class="success-alert">
                <span style="font-size: 20px;">✅</span>
                <span>Data berhasil disimpan!</span>
            </div>
            <?php endif; ?>

            <form id="jumantikForm" onsubmit="saveData(event)">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="rt" id="rt_value" value="<?= htmlspecialchars($rt_kader); ?>">
                <input type="hidden" name="rw" id="rw_value" value="<?= htmlspecialchars($rw_kader); ?>">
                

                <table class="data-table">
                    <tr>
                        <th>RT</th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="rt-rw-label">RT Anda:</span>
                                <span class="rt-rw-display"><?= htmlspecialchars($rt_kader ?: '-'); ?></span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th>RW</th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="rt-rw-label">RW Anda:</span>
                                <span class="rt-rw-display"><?= htmlspecialchars($rw_kader ?: '-'); ?></span>
                            </div>
                        </td>
                    </tr>


                    <tr>
                        <th colspan="2">Input Angka per Tatanan (isi dengan angka nol jika tidak ada)</th>
                    </tr>

                    <tr>
                        <td colspan="2">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
                                <div style="font-weight:600">Tatanan</div>
                                <div style="font-weight:600">Diperiksa (jumlah)</div>
                                <div style="font-weight:600">Jentik (+)</div>
                            </div>

                            <div id="tatanan_inputs" style="margin-top:8px; display:grid; gap:8px;">
                                <?php
                                $tatanan_list = [
                                    'Pemukiman',
                                    'Institusi Pendidikan',
                                    'Tempat Kerja',
                                    'TTU',
                                    'TPM',
                                    'Sarana Olahraga',
                                    'Sarana Kesehatan'
                                ];
                                foreach ($tatanan_list as $t):
                                    $key = strtolower(str_replace(' ', '_', $t));
                                ?>
                                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; align-items:center;">
                                    <div style="padding:8px 10px; background:#f8fafc; border:1px solid #e2e8f0;"><?= htmlspecialchars($t); ?></div>
                                    <div><input type="number" min="0" name="<?= $key; ?>_diperiksa" class="form-control-table" value="0" required></div>
                                    <div><input type="number" min="0" name="<?= $key; ?>_jentik" class="form-control-table" value="0" required></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th>Minggu Ke- <span class="required-mark">*</span></th>
                        <td>
                            <select name="minggu" id="minggu_select" class="form-control-table" required>
                                <option value="">-- Pilih Minggu --</option>
                                <?php for ($i=1;$i<=6;$i++): ?>
                                    <option value="<?= $i; ?>">Minggu <?= $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Bulan <span class="required-mark">*</span></th>
                        <td>
                            <select name="bulan" id="bulan_select" class="form-control-table" required>
                                <option value="">-- Pilih Bulan --</option>
                                <?php
                                $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                                for ($m=1;$m<=12;$m++):
                                    $val = str_pad($m,2,'0',STR_PAD_LEFT);
                                ?>
                                <option value="<?= $val; ?>"><?= $months[$m-1]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Catatan</th>
                        <td>
                            <textarea name="catatan" class="form-control-table" placeholder="Catatan tambahan (opsional)"></textarea>
                        </td>
                    </tr>
                </table>

                <div class="submit-container">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
    // Mobile Menu Toggle
    function toggleMobileMenu() {
        const toggle = document.querySelector('.navbar-toggle');
        const mobileMenu = document.getElementById('mobileMenu');
        
        toggle.classList.toggle('active');
        mobileMenu.classList.toggle('active');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const navbar = document.querySelector('.navbar');
        const mobileMenu = document.getElementById('mobileMenu');
        const toggle = document.querySelector('.navbar-toggle');
        
        if (!navbar.contains(event.target) && mobileMenu.classList.contains('active')) {
            toggle.classList.remove('active');
            mobileMenu.classList.remove('active');
        }
    });

    // No tatanan button and jentik button logic anymore — inputs per tatanan are numeric

    function saveData(event) {
        event.preventDefault();
        
        console.log('=== FORM SUBMISSION START ===');
        
        const rtValue = document.getElementById('rt_value').value;
        const rwValue = document.getElementById('rw_value').value;
        console.log('RT:', rtValue);
        console.log('RW:', rwValue);

        // Validasi dasar (RT/RW)
        if (!rtValue) {
            alert('❌ Data RT tidak ditemukan. Silakan hubungi admin.');
            return false;
        }
        if (!rwValue) {
            alert('❌ Data RW tidak ditemukan. Silakan hubungi admin.');
            return false;
        }

        // Validasi angka per tatanan: pastikan setidaknya satu nilai diperiksa > 0
        const inputs = document.querySelectorAll('#tatanan_inputs input[name$="_diperiksa"]');
        let totalDiperiksa = 0;
        inputs.forEach(i => totalDiperiksa += parseInt(i.value || '0', 10));
        if (totalDiperiksa === 0) {
            if (!confirm('Semua nilai diperiksa bernilai 0. Apakah Anda yakin ingin menyimpan?')) return false;
        }

        const formData = new FormData(document.getElementById('jumantikForm'));
        
        console.log('=== DATA FORMDATA ===');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Good: formData contains numeric inputs per tatanan

        fetch('ajax/process-jumantik.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response dari server:', data);
            if (data.success) {
                alert('✅ ' + data.message);
                window.location.href = 'laporan-kader.php?success=1';
            } else {
                alert('❌ Gagal menyimpan: ' + data.message);
                console.error('Server error:', data);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('❌ Terjadi kesalahan: ' + error.message);
        });
        
        return false;
    }

    // Set default minggu dan bulan ke minggu sekarang dan bulan sekarang
    document.addEventListener('DOMContentLoaded', function() {
        const now = new Date();
        const month = now.getMonth() + 1;
        const day = now.getDate();
        // hitung minggu ke-(ceil(day/7))
        let week = Math.ceil(day / 7);
        if (week < 1) week = 1;
        if (week > 6) week = 6;

        const bulanSelect = document.getElementById('bulan_select');
        const mingguSelect = document.getElementById('minggu_select');
        if (bulanSelect) bulanSelect.value = String(month).padStart(2, '0');
        if (mingguSelect) mingguSelect.value = String(week);
    });
</script>
</body>
</html>