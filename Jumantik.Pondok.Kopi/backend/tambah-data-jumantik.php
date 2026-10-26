<?php
session_start();
require_once 'config.php';

// Pastikan hanya petugas/kader bisa tambah data
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'petugas' && $_SESSION['role'] !== 'kader')) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pemeriksaan Jumantik</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        form {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 420px;
        }

        h2 {
            text-align: center;
            color: #2a6df4;
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        button {
            width: 100%;
            background: #2a6df4;
            color: white;
            padding: 12px;
            margin-top: 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #0049c7;
        }

        .preview {
            margin-top: 10px;
            text-align: center;
        }

        img {
            width: 100%;
            max-width: 250px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<form action="proses-tambah-jumantik.php" method="POST" enctype="multipart/form-data">
    <h2>Tambah Pemeriksaan</h2>

    <label>Nama Kepala Keluarga</label>
    <input type="text" name="nama_kepala_keluarga" required>

    <label>Alamat</label>
    <input type="text" name="alamat" required>

    <label>RT</label>
    <input type="text" name="rt" required>

    <label>RW</label>
    <select name="rw" required>
        <option value="">-- Pilih RW --</option>
        <?php for($i=1; $i<=11; $i++): 
            $rw_val = str_pad($i, 3, '0', STR_PAD_LEFT);
        ?>
        <option value="<?= $rw_val; ?>">RW <?= str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
        <?php endfor; ?>
    </select>

    <label>Jentik Ditemukan</label>
    <select name="jentik_ditemukan" required>
        <option value="Ya">Ya</option>
        <option value="Tidak">Tidak</option>
    </select>

    <label>Upload Foto Rumah</label>
    <input type="file" name="foto_rumah" accept="image/*" required onchange="previewImage(event)">

    <div class="preview">
        <img id="preview" src="#" alt="Preview Foto" style="display:none;">
    </div>

    <button type="submit">Simpan Data</button>
</form>

<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const output = document.getElementById('preview');
        output.src = reader.result;
        output.style.display = 'block';
    };
    reader.readAsDataURL(event.target.files[0]);
}
</script>

</body>
</html>
