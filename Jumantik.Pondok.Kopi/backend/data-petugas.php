<?php
session_start();
require_once 'config.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

// Get all petugas
$petugas = mysqli_query($conn, "SELECT p.*, u.username FROM petugas p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Petugas - Jumantik</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="topbar">
                <h2>Data Petugas</h2>
                <div class="topbar-right">
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Tambah Petugas
                    </button>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Daftar Petugas Jumantik</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Lengkap</th>
                                    <th>Wilayah Kerja</th>
                                    <th>Status</th>
                                    <th>No. Telepon</th>
                                    <th>Username</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while($row = mysqli_fetch_assoc($petugas)): 
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo $row['nama_lengkap']; ?></td>
                                    <td><?php echo $row['wilayah_kerja']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status'] == 'RW' ? 'primary' : 'success'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['nomor_telepon']; ?></td>
                                    <td><?php echo $row['username']; ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editPetugas(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deletePetugas(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Add Petugas -->
    <div class="modal" id="petugasModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Petugas</h3>
            </div>
            <form action="backend/add-petugas.php" method="POST">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Wilayah Kerja</label>
                    <input type="text" name="wilayah_kerja" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Petugas">Petugas</option>
                        <option value="RW">RW</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nomor Telepon</label>
                    <input type="tel" name="nomor_telepon" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('petugasModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('petugasModal').classList.remove('active');
        }
        
        function editPetugas(id) {
            // Implementasi edit
            alert('Edit petugas ID: ' + id);
        }
        
        function deletePetugas(id) {
            if(confirm('Yakin ingin menghapus petugas ini?')) {
                window.location.href = 'backend/delete-petugas.php?id=' + id;
            }
        }
    </script>
</body>
</html>