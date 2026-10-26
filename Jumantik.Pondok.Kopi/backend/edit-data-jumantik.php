<?php
session_start();
require_once 'config.php';
include 'partials/header.php';
include 'partials/sidebar.php';

// Ambil data awal
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$q  = mysqli_query($conn, "SELECT * FROM data_jumantik WHERE id = $id");
$data = mysqli_fetch_assoc($q);
$original_catatan = $data['catatan'] ?? '';

// Ekstrak teks catatan yang bisa diedit (kalau JSON, ambil _catatan)
$editable_catatan = '';
if (!empty($original_catatan)) {
    $decoded = json_decode($original_catatan, true);
    if (is_array($decoded) && isset($decoded['_catatan'])) {
        $editable_catatan = $decoded['_catatan'];
    } else {
        $editable_catatan = $original_catatan;
    }
}

// Normalisasi RT/RW ke 2 digit (ambil angka saja)
function normalize_code($val) {
    if (!$val) return '';
    if (preg_match_all('/\d+/', (string)$val, $m)) {
        $num = (int)implode('', $m[0]);
        return str_pad($num, 2, '0', STR_PAD_LEFT);
    }
    return '';
}

$rt_selected = normalize_code($data['rt'] ?? '');
$rw_selected = normalize_code($data['rw'] ?? '');

// Submit update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama     = $_POST['nama_kepala_keluarga'];
    $alamat   = $_POST['alamat'];
    $tatanan  = $_POST['tatanan_diperiksa'];
    $jentik   = $_POST['jentik_ditemukan'];
    $tanggal  = $_POST['tanggal_pemeriksaan'];
    $rt       = $_POST['rt'];
    $rw       = $_POST['rw'];
    $catatan_text  = $_POST['catatan'];

    // Rekonstruksi catatan: kalau sebelumnya JSON rekap, update hanya _catatan
    $final_catatan = $catatan_text;
    if (!empty($original_catatan)) {
        $decoded = json_decode($original_catatan, true);
        if (is_array($decoded)) {
            $decoded['_catatan'] = $catatan_text;
            $final_catatan = json_encode($decoded);
        }
    }

    mysqli_query($conn, "UPDATE data_jumantik SET 
        nama_kepala_keluarga='$nama',
        alamat='$alamat',
        tatanan_diperiksa='$tatanan',
        jentik_ditemukan='$jentik',
        tanggal_pemeriksaan='$tanggal',
        rt='$rt',
        rw='$rw',
        catatan='$final_catatan'
        WHERE id=$id
    ");
    header("Location: laporan-kader.php?success=1");
    exit;
}
?>

<div class="main-content">
    <h2>✏️ Edit Data Pemeriksaan</h2>
    <form method="POST">
        <div class="form-group">
            <label>📅 Tanggal Pemeriksaan <span style="color: #dc3545;">*</span></label>
            <input
                type="date"
                name="tanggal_pemeriksaan"
                class="form-control"
                value="<?= htmlspecialchars(isset($data['tanggal_pemeriksaan']) ? date('Y-m-d', strtotime($data['tanggal_pemeriksaan'])) : ''); ?>"
                required
            >
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label>🏠 RT</label>
                <div style="padding:10px;background:#f1f5f9;border-radius:6px;"><?= htmlspecialchars($data['rt'] ?? '-'); ?></div>
                <input type="hidden" name="rt" value="<?= htmlspecialchars($data['rt'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>🏘️ RW</label>
                <div style="padding:10px;background:#f1f5f9;border-radius:6px;"><?= htmlspecialchars($data['rw'] ?? '-'); ?></div>
                <input type="hidden" name="rw" value="<?= htmlspecialchars($data['rw'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>👤 Nama Kepala Keluarga <span style="color: #dc3545;">*</span></label>
            <input
                type="text"
                name="nama_kepala_keluarga"
                class="form-control"
                value="<?= htmlspecialchars($data['nama_kepala_keluarga'] ?? ''); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>🏢 Tatanan Diperiksa <span style="color: #dc3545;">*</span></label>
            <?php $tatanan_val = $data['tatanan_diperiksa'] ?? ''; ?>
            <select name="tatanan_diperiksa" class="form-control" required>
                <option value="">-- Pilih Tatanan --</option>
                <option value="Pemukiman" <?= $tatanan_val === 'Pemukiman' ? 'selected' : ''; ?>>Pemukiman</option>
                <option value="Institusi Pendidikan" <?= $tatanan_val === 'Institusi Pendidikan' ? 'selected' : ''; ?>>Institusi Pendidikan</option>
                <option value="Tempat Kerja" <?= $tatanan_val === 'Tempat Kerja' ? 'selected' : ''; ?>>Tempat Kerja</option>
                <option value="TTU" <?= $tatanan_val === 'TTU' ? 'selected' : ''; ?>>TTU (Tempat-Tempat Umum)</option>
                <option value="TPM" <?= $tatanan_val === 'TPM' ? 'selected' : ''; ?>>TPM (Tempat Pengelolaan Makanan)</option>
                <option value="Sarana Olahraga" <?= $tatanan_val === 'Sarana Olahraga' ? 'selected' : ''; ?>>Sarana Olahraga</option>
                <option value="Sarana Kesehatan" <?= $tatanan_val === 'Sarana Kesehatan' ? 'selected' : ''; ?>>Sarana Kesehatan</option>
            </select>
        </div>

        <div class="form-group">
            <label>📍 Alamat <span style="color: #dc3545;">*</span></label>
            <textarea
                name="alamat"
                class="form-control"
                rows="3"
                required
            ><?= htmlspecialchars($data['alamat'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>🦟 Status Jentik <span style="color: #dc3545;">*</span></label>
            <?php $jentik_val = $data['jentik_ditemukan'] ?? ''; ?>
            <select name="jentik_ditemukan" class="form-control" required>
                <option value="Tidak" <?= $jentik_val === 'Tidak' ? 'selected' : ''; ?>>Negatif (-)</option>
                <option value="Ya" <?= $jentik_val === 'Ya' ? 'selected' : ''; ?>>Positif (+)</option>
            </select>
        </div>

        <div class="form-group">
            <label>📝 Catatan</label>
            <textarea
                name="catatan"
                class="form-control"
                rows="3"
                placeholder="Tambahkan catatan jika diperlukan..."
            ><?= htmlspecialchars($editable_catatan); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
        <a href="laporan-kader.php" class="btn btn-secondary">Kembali</a>
    </form>
</div>
</body>
</html>
