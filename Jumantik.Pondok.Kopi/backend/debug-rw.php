<?php
require_once 'config.php';

echo "<h2>DEBUG: Format RW di Database</h2>";

// Cek format RW di tabel data_jumantik dengan tatanan_diperiksa = 'Rekap'
echo "<h3>Format RW di data_jumantik (Rekap):</h3>";
$result = mysqli_query($conn, "SELECT DISTINCT dj.rw, k.rw as kader_rw FROM data_jumantik dj LEFT JOIN kader k ON dj.kader_id = k.id WHERE dj.tatanan_diperiksa = 'Rekap' LIMIT 10");
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>dj.rw</th><th>k.rw (kader_rw)</th></tr>";
    while($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td>" . htmlspecialchars($row['rw']) . "</td><td>" . htmlspecialchars($row['kader_rw']) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Tidak ada data rekap!";
}

echo "<br><br>";

// Cek format di tabel rw (login RW)
echo "<h3>Format nomor_rw di tabel rw:</h3>";
$result = mysqli_query($conn, "SELECT id, nomor_rw, nama_rw FROM rw LIMIT 10");
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>nomor_rw</th><th>nama_rw</th></tr>";
    while($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . htmlspecialchars($row['nomor_rw']) . "</td><td>" . htmlspecialchars($row['nama_rw']) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Tidak ada RW!";
}

echo "<br><br>";

// Cek format di tabel kader
echo "<h3>Format rw di tabel kader:</h3>";
$result = mysqli_query($conn, "SELECT id, nama_kader, rw FROM kader LIMIT 10");
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>nama_kader</th><th>rw</th></tr>";
    while($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . htmlspecialchars($row['nama_kader']) . "</td><td>" . htmlspecialchars($row['rw']) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Tidak ada kader!";
}

?>
