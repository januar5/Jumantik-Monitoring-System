<?php
session_start();
require_once 'config.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$role = strtolower($_SESSION['role'] ?? 'kader');
$current_page = basename($_SERVER['PHP_SELF']);
$sidebar_menu = [];

if ($role === 'admin') {
    $sidebar_menu = [
        ['href' => 'dashboard.php', 'label' => '🏠 Dashboard'],
        ['href' => 'data-petugas.php', 'label' => '👥 Data Petugas'],
        ['href' => 'data-rw.php', 'label' => '🏘️ Data RW'],
        ['href' => 'data-jumantik.php', 'label' => '📋 Data Jumantik'],
        ['href' => 'jadwal.php', 'label' => '📅 Jadwal'],
        ['href' => 'laporan-kader.php', 'label' => '📑 Laporan'],
    ];
} elseif ($role === 'rw') {
    $sidebar_menu = [
        ['href' => 'dashboard-rw.php', 'label' => '🏠 Dashboard'],
        ['href' => 'laporan-rw.php', 'label' => '📄 Laporan RW'],
        ['href' => 'laporan-kader.php', 'label' => '📑 Laporan'],
    ];
} else {
    $sidebar_menu = [
        ['href' => 'dashboard-kader.php', 'label' => '🏠 Beranda'],
        ['href' => 'data-jumantik.php', 'label' => '📋 Data Jumantik'],
        ['href' => 'jadwal.php', 'label' => '📅 Jadwal'],
        ['href' => 'laporan-kader.php', 'label' => '📑 Laporan'],
    ];
}

$sidebar_menu[] = ['href' => 'logout.php', 'label' => '🚪 Keluar'];

function table_exists(mysqli $conn, string $table_name): bool {
    $table_name = mysqli_real_escape_string($conn, $table_name);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$table_name}'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result) {
        mysqli_free_result($result);
    }
    return $exists;
}

$jadwal_table = null;
if (table_exists($conn, 'jadwal')) {
    $jadwal_table = 'jadwal';
} elseif (table_exists($conn, 'jadwal_pemeriksaan')) {
    $jadwal_table = 'jadwal_pemeriksaan';
}

$missing_table_message = '';
if ($jadwal_table === null) {
    $missing_table_message = 'Tabel jadwal tidak ditemukan. Hubungi administrator untuk membuat tabel "jadwal" atau "jadwal_pemeriksaan".';
    $error_message = isset($error_message) && $error_message
        ? $error_message . ' ' . $missing_table_message
        : $missing_table_message;
}

$success_message = $_SESSION['flash_success'] ?? '';
$error_message = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Ambil daftar kolom tabel jadwal agar kita bisa menyesuaikan field dinamis
$jadwal_columns = [];
if ($jadwal_table) {
    $res_cols = mysqli_query($conn, "SHOW COLUMNS FROM {$jadwal_table}");
    if ($res_cols) {
        while ($c = mysqli_fetch_assoc($res_cols)) {
            $jadwal_columns[] = $c['Field'];
        }
        mysqli_free_result($res_cols);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($jadwal_table === null) {
        $_SESSION['flash_error'] = $missing_table_message;
        header('Location: jadwal.php');
        exit();
    }

    $action = $_POST['action'] ?? 'create';
    $tanggal = $_POST['tanggal'] ?? '';
    $wilayah_input = trim($_POST['wilayah'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Validasi dasar
    if (empty($tanggal) || empty($keterangan)) {
        $_SESSION['flash_error'] = 'Harap lengkapi field Tanggal dan Keterangan.';
        header('Location: jadwal.php');
        exit();
    }

    // Siapkan mapping field berdasarkan kolom yang ada di tabel
    $fields = [];
    $values = [];
    $types = '';

    // tanggal (jika ada)
    if (in_array('tanggal', $jadwal_columns)) {
        $fields[] = 'tanggal';
        $values[] = $tanggal;
        $types .= 's';
    }

    // wilayah: jika ada kolom 'wilayah' gunakan, jika tidak dan ada kolom 'rt' & 'rw' coba pisah
    if (in_array('wilayah', $jadwal_columns)) {
        $fields[] = 'wilayah';
        $values[] = $wilayah_input;
        $types .= 's';
    } else {
        // jika format input seperti "RT 01 / RW 05" coba ekstrak
        if (in_array('rt', $jadwal_columns) && in_array('rw', $jadwal_columns)) {
            $rt_val = '';
            $rw_val = '';
            if (strpos($wilayah_input, '/') !== false) {
                list($a, $b) = array_map('trim', explode('/', $wilayah_input, 2));
                $rt_val = preg_replace('/[^0-9a-zA-Z ]/', '', $a);
                $rw_val = preg_replace('/[^0-9a-zA-Z ]/', '', $b);
            } else {
                // jika tidak ada slash, masukkan seluruh nilai ke kolom 'wilayah' alternatif: gunakan rt jika kosong
                $rt_val = $wilayah_input;
            }
            $fields[] = 'rt'; $values[] = $rt_val; $types .= 's';
            $fields[] = 'rw'; $values[] = $rw_val; $types .= 's';
        }
    }

    // keterangan
    if (in_array('keterangan', $jadwal_columns)) {
        $fields[] = 'keterangan';
        $values[] = $keterangan;
        $types .= 's';
    }

    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID jadwal tidak valid.';
            header('Location: jadwal.php');
            exit();
        }

        if (empty($fields)) {
            $_SESSION['flash_error'] = 'Tidak ada kolom yang dapat diperbarui pada tabel jadwal.';
            header('Location: jadwal.php');
            exit();
        }

        $set_parts = [];
        foreach ($fields as $f) $set_parts[] = "{$f} = ?";
        $sql = "UPDATE {$jadwal_table} SET " . implode(', ', $set_parts) . " WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $_SESSION['flash_error'] = 'Gagal menyiapkan statement update: ' . mysqli_error($conn);
            header('Location: jadwal.php');
            exit();
        }

        // bind params
        $types_with_id = $types . 'i';
        $bind_params = array_merge($values, [$id]);
        mysqli_stmt_bind_param($stmt, $types_with_id, ...$bind_params);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash_' . ($result ? 'success' : 'error')] = $result ? 'Jadwal berhasil diperbarui.' : 'Gagal memperbarui jadwal.';
        header('Location: jadwal.php');
        exit();
    }

    // INSERT
    if (empty($fields)) {
        $_SESSION['flash_error'] = 'Tidak ada kolom yang dapat disimpan pada tabel jadwal.';
        header('Location: jadwal.php');
        exit();
    }

    $cols_sql = implode(', ', $fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO {$jadwal_table} ({$cols_sql}) VALUES ({$placeholders})";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $_SESSION['flash_error'] = 'Gagal menyiapkan statement insert: ' . mysqli_error($conn);
        header('Location: jadwal.php');
        exit();
    }

    mysqli_stmt_bind_param($stmt, $types, ...$values);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['flash_' . ($result ? 'success' : 'error')] = $result ? 'Jadwal baru berhasil ditambahkan.' : 'Gagal menambahkan jadwal baru.';
    header('Location: jadwal.php');
    exit();
}

$jadwal_result = $jadwal_table
    ? mysqli_query($conn, "SELECT * FROM {$jadwal_table} ORDER BY tanggal DESC, id DESC")
    : false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jadwal Pemeriksaan | Jumantik Pondok Kopi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background:#f4f6fb; color:#1e293b; }
        .dashboard { display:flex; min-height:100vh; }
        .main-content { flex:1; padding:30px; }
        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .btn { border:none; border-radius:10px; padding:10px 16px; font-weight:600; cursor:pointer; transition:0.2s; font-family:'Poppins',sans-serif; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-primary:hover { background:#1d4ed8; }
        .btn-danger { background:#ef4444; color:#fff; }
        .btn-danger:hover { background:#dc2626; }
        .card { background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(15,23,42,0.08); margin-bottom:20px; }
        .card-header { padding:20px 24px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
        .card-body { padding:20px 24px; }
        table { width:100%; border-collapse:collapse; }
        thead { background:#0f172a; color:#fff; }
        th, td { padding:12px 14px; text-align:left; border-bottom:1px solid #f1f5f9; }
        tbody tr:hover { background:#f8fafc; }
        .badge { padding:6px 10px; border-radius:999px; font-size:0.75rem; background:#e0e7ff; color:#312e81; }
        .alert { padding:14px 18px; border-radius:12px; margin-bottom:20px; font-weight:500; }
        .alert-success { background:#dcfce7; color:#166534; }
        .alert-error { background:#fee2e2; color:#b91c1c; }
        .modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; backdrop-filter:blur(4px); }
        .modal-content { background:#fff; border-radius:16px; width:95%; max-width:520px; margin:60px auto; overflow:hidden; }
        .modal-header { padding:18px 22px; background:#2563eb; color:#fff; display:flex; justify-content:space-between; align-items:center; }
        .modal-body { padding:20px 22px; }
        .modal-footer { padding:16px 22px; border-top:1px solid #f1f5f9; text-align:right; background:#f8fafc; }
        .form-group { margin-bottom:16px; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input, textarea { width:100%; padding:10px 12px; border-radius:10px; border:1px solid #e2e8f0; font-family:'Poppins',sans-serif; }
        textarea { min-height:90px; resize:vertical; }
        .close { cursor:pointer; font-size:1.4rem; }
        .table-responsive { overflow-x:auto; }
        @media(max-width:1024px){ .dashboard{flex-direction:column;} .sidebar{width:100%; border-radius:0 0 20px 20px;} }
    </style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <h2>🦟 Jumantik</h2>
        <ul>
            <?php foreach ($sidebar_menu as $item): 
                $is_active = $current_page === basename($item['href']);
            ?>
            <li>
                <a href="<?= $item['href']; ?>" class="<?= $is_active ? 'active' : ''; ?>">
                    <?= $item['label']; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <div>
                <h1 style="margin:0;font-size:1.7rem;">Jadwal Pemeriksaan Lapangan</h1>
                <p style="color:#94a3b8;margin-top:6px;">Atur rencana kunjungan petugas ke setiap wilayah.</p>
            </div>
            <button class="btn btn-primary" onclick="openJadwalModal()">+ Buat Jadwal</button>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <section class="card">
            <div class="card-header">
                <h3>Daftar Jadwal Pemeriksaan</h3>
            </div>
            <div class="card-body table-responsive">
                <?php if ($jadwal_result && mysqli_num_rows($jadwal_result) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Wilayah</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = mysqli_fetch_assoc($jadwal_result)): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= date('d M Y', strtotime($row['tanggal'] ?? date('Y-m-d'))); ?></td>
                                    <?php
                                        // Tampilkan wilayah: gunakan kolom 'wilayah' bila ada, jika tidak gabungkan 'rt' / 'rw' bila tersedia
                                        $display_wilayah = '-';
                                        if (isset($row['wilayah']) && $row['wilayah'] !== null && $row['wilayah'] !== '') {
                                            $display_wilayah = $row['wilayah'];
                                        } else {
                                            $parts = [];
                                            if (isset($row['rt']) && $row['rt'] !== null && $row['rt'] !== '') $parts[] = trim($row['rt']);
                                            if (isset($row['rw']) && $row['rw'] !== null && $row['rw'] !== '') $parts[] = trim($row['rw']);
                                            if (!empty($parts)) $display_wilayah = implode(' / ', $parts);
                                        }
                                    ?>
                                    <td><span class="badge"><?= htmlspecialchars($display_wilayah); ?></span></td>
                                    <td><?= nl2br(htmlspecialchars($row['keterangan'] ?? '-')); ?></td>
                                    <td>
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                            <button class="btn btn-primary"
                                                data-id="<?= (int)$row['id']; ?>"
                                                data-tanggal="<?= htmlspecialchars($row['tanggal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php
                                                    // Kirimkan nilai wilayah ke modal: prefer 'wilayah' kolom, jika tidak ada gabungkan rt/rw
                                                    $data_wilayah = '';
                                                    if (isset($row['wilayah']) && $row['wilayah'] !== null) {
                                                        $data_wilayah = $row['wilayah'];
                                                    } else {
                                                        $parts2 = [];
                                                        if (isset($row['rt']) && $row['rt'] !== null) $parts2[] = $row['rt'];
                                                        if (isset($row['rw']) && $row['rw'] !== null) $parts2[] = $row['rw'];
                                                        if (!empty($parts2)) $data_wilayah = implode(' / ', $parts2);
                                                    }
                                                ?>
                                                data-wilayah="<?= htmlspecialchars($data_wilayah, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-keterangan="<?= htmlspecialchars($row['keterangan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="openJadwalModal(this)">Edit</button>
                                            <a class="btn btn-danger" href="hapus-jadwal.php?id=<?= (int)$row['id']; ?>" onclick="return confirm('Hapus jadwal ini?');">Hapus</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center;color:#94a3b8;padding:30px;">Belum ada jadwal yang tercatat.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<div id="jadwalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="jadwalModalTitle">Tambah Jadwal</h3>
            <span class="close" onclick="closeJadwalModal()">&times;</span>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="id" id="jadwal_id">
            <input type="hidden" name="action" id="jadwal_action" value="create">
            <div class="form-group">
                <label>Tanggal Pemeriksaan</label>
                <input type="date" name="tanggal" id="jadwal_tanggal" required>
            </div>
            <div class="form-group">
                <label>Wilayah (RT/RW)</label>
                <input type="text" name="wilayah" id="jadwal_wilayah" placeholder="Contoh: RT 01 / RW 05" required>
            </div>
            <div class="form-group">
                <label>Rencana Kegiatan</label>
                <textarea name="keterangan" id="jadwal_keterangan" placeholder="Tuliskan detail kegiatan" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#e2e8f0;color:#1f2937;" onclick="closeJadwalModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('jadwalModal');
const body = document.body;

function decodeHtmlEntities(str = '') {
    if (!str) return '';
    const txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
}

function openJadwalModal(button = null) {
    if (button) {
        document.getElementById('jadwalModalTitle').textContent = 'Edit Jadwal';
        document.getElementById('jadwal_action').value = 'update';
        document.getElementById('jadwal_id').value = button.dataset.id;
        document.getElementById('jadwal_tanggal').value = button.dataset.tanggal || '';
        document.getElementById('jadwal_wilayah').value = decodeHtmlEntities(button.dataset.wilayah);
        document.getElementById('jadwal_keterangan').value = decodeHtmlEntities(button.dataset.keterangan);
    } else {
        document.getElementById('jadwalModalTitle').textContent = 'Tambah Jadwal';
        document.getElementById('jadwal_action').value = 'create';
        document.getElementById('jadwal_id').value = '';
        document.querySelector('#jadwalModal form').reset();
    }
    modal.style.display = 'block';
    body.style.overflow = 'hidden';
}

function closeJadwalModal() {
    modal.style.display = 'none';
    body.style.overflow = 'auto';
}

window.addEventListener('click', function(e) {
    if (e.target === modal) closeJadwalModal();
});

document.addEventListener('DOMContentLoaded', function() {
    const tanggalInput = document.getElementById('jadwal_tanggal');
    if (tanggalInput) {
        tanggalInput.setAttribute('min', new Date().toISOString().split('T')[0]);
    }
});
</script>
</body>
</html>