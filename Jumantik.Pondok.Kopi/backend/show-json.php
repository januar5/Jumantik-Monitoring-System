<?php
require_once 'config.php';

// Get first rekap record
$result = mysqli_query($conn, "SELECT id, rt, catatan FROM data_jumantik WHERE tatanan_diperiksa = 'Rekap' LIMIT 1");
$row = mysqli_fetch_assoc($result);

echo "=== SAMPLE REKAP CATATAN ===\n\n";
echo "ID: " . $row['id'] . "\n";
echo "RT: " . $row['rt'] . "\n";
echo "\nRAW CATATAN:\n";
echo $row['catatan'] . "\n\n";
echo "DECODED (Pretty Print):\n";
echo json_encode(json_decode($row['catatan'], true), JSON_PRETTY_PRINT);
?>
