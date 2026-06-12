<?php
// kelas.php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Cek apakah user memiliki foto profil custom
$has_custom_profile = false;
$profile_pic = '';
$default_avatar = '../assets/images/profile/male/image_1.png';

$sql_profile = "SELECT profile_pic FROM users WHERE id = ?";
$stmt_profile = mysqli_prepare($conn, $sql_profile);
mysqli_stmt_bind_param($stmt_profile, "i", $user_id);
mysqli_stmt_execute($stmt_profile);
$result_profile = mysqli_stmt_get_result($stmt_profile);
if ($row_profile = mysqli_fetch_assoc($result_profile)) {
    if (!empty($row_profile['profile_pic']) && file_exists('uploads/profile_pics/' . $row_profile['profile_pic'])) {
        $has_custom_profile = true;
        $profile_pic = $row_profile['profile_pic'];
    }
}
mysqli_stmt_close($stmt_profile);

// Update last activity
$_SESSION['last_activity'] = time();

// Cek dan buat tabel kelas jika belum ada
$check_table = "SHOW TABLES LIKE 'kelas'";
$table_result = mysqli_query($conn, $check_table);

if (mysqli_num_rows($table_result) == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS kelas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        kode_kelas VARCHAR(10) UNIQUE NOT NULL,
        nama_kelas VARCHAR(50) NOT NULL,
        deskripsi TEXT,
        iuran_per_bulan DECIMAL(10,2) NOT NULL,
        fasilitas TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
    
    mysqli_query($conn, $create_table);
    
    $insert_data = "INSERT INTO kelas (kode_kelas, nama_kelas, deskripsi, iuran_per_bulan, fasilitas) VALUES
    ('KLS1', 'Kelas 1', 'Kelas dengan fasilitas terbaik untuk perawatan kesehatan', 150000.00, 'Rawat inap kamar VIP, Konsultasi dokter spesialis tanpa batas, Obat-obatan komprehensif, Pemeriksaan laboratorium lengkap, Fisioterapi, Akomodasi untuk 1 pendamping'),
    ('KLS2', 'Kelas 2', 'Kelas menengah dengan fasilitas standar yang baik', 100000.00, 'Rawat inap kamar kelas 2, Konsultasi dokter spesialis terbatas, Obat-obatan standar, Pemeriksaan laboratorium dasar, Rawat jalan terbatas'),
    ('KLS3', 'Kelas 3', 'Kelas dasar dengan fasilitas minimal untuk perlindungan kesehatan', 50000.00, 'Rawat inap kamar kelas 3, Konsultasi dokter umum, Obat generik, Pemeriksaan dasar, Rawat jalan terbatas')
    ON DUPLICATE KEY UPDATE 
    nama_kelas = VALUES(nama_kelas),
    deskripsi = VALUES(deskripsi),
    iuran_per_bulan = VALUES(iuran_per_bulan),
    fasilitas = VALUES(fasilitas)";
    
    mysqli_query($conn, $insert_data);
}

// Cek apakah kolom kelas_id sudah ada di tabel peserta
$check_column = "SHOW COLUMNS FROM peserta LIKE 'kelas_id'";
$column_result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_result) == 0) {
    $alter_sql = "ALTER TABLE peserta 
                 ADD COLUMN kelas_id INT DEFAULT NULL,
                 ADD CONSTRAINT fk_peserta_kelas 
                 FOREIGN KEY (kelas_id) 
                 REFERENCES kelas(id) 
                 ON DELETE SET NULL 
                 ON UPDATE CASCADE";
    
    mysqli_query($conn, $alter_sql);
    
    $update_peserta = "UPDATE peserta p 
                      JOIN kelas k ON 
                      CASE 
                          WHEN p.kelas_bpjs LIKE '%Kelas 1%' THEN k.kode_kelas = 'KLS1'
                          WHEN p.kelas_bpjs LIKE '%Kelas 2%' THEN k.kode_kelas = 'KLS2'
                          WHEN p.kelas_bpjs LIKE '%Kelas 3%' THEN k.kode_kelas = 'KLS3'
                      END
                      SET p.kelas_id = k.id";
    
    mysqli_query($conn, $update_peserta);
}

// Fungsi CRUD untuk Data Kelas
$message = '';
$message_type = '';

// Tambah Data Kelas
if (isset($_POST['tambah'])) {
    $kode_kelas = mysqli_real_escape_string($conn, $_POST['kode_kelas']);
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $iuran_per_bulan = mysqli_real_escape_string($conn, $_POST['iuran_per_bulan']);
    $fasilitas = mysqli_real_escape_string($conn, $_POST['fasilitas']);
    
    $check_sql = "SELECT id FROM kelas WHERE kode_kelas = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $kode_kelas);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $message = "Kode kelas sudah digunakan!";
        $message_type = "danger";
    } else {
        $sql = "INSERT INTO kelas (kode_kelas, nama_kelas, deskripsi, iuran_per_bulan, fasilitas) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssds", $kode_kelas, $nama_kelas, $deskripsi, $iuran_per_bulan, $fasilitas);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Data kelas berhasil ditambahkan!";
            $message_type = "success";
        } else {
            $message = "Gagal menambahkan data kelas: " . mysqli_error($conn);
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_stmt_close($check_stmt);
}

// Edit Data Kelas
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $kode_kelas = mysqli_real_escape_string($conn, $_POST['kode_kelas']);
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $iuran_per_bulan = mysqli_real_escape_string($conn, $_POST['iuran_per_bulan']);
    $fasilitas = mysqli_real_escape_string($conn, $_POST['fasilitas']);
    
    $check_sql = "SELECT id FROM kelas WHERE kode_kelas = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "si", $kode_kelas, $id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $message = "Kode kelas sudah digunakan oleh kelas lain!";
        $message_type = "danger";
    } else {
        $sql = "UPDATE kelas SET kode_kelas = ?, nama_kelas = ?, deskripsi = ?, 
                iuran_per_bulan = ?, fasilitas = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssdsi", $kode_kelas, $nama_kelas, $deskripsi, $iuran_per_bulan, $fasilitas, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Data kelas berhasil diupdate!";
            $message_type = "success";
        } else {
            $message = "Gagal mengupdate data kelas: " . mysqli_error($conn);
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_stmt_close($check_stmt);
}

// Hapus Data Kelas
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    
    $check_sql = "SELECT COUNT(*) as total FROM peserta WHERE kelas_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if ($check_data['total'] > 0) {
        $message = "Tidak dapat menghapus kelas yang masih memiliki peserta!";
        $message_type = "danger";
    } else {
        $sql = "DELETE FROM kelas WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Data kelas berhasil dihapus!";
            $message_type = "success";
        } else {
            $message = "Gagal menghapus data kelas: " . mysqli_error($conn);
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    }
}

// Ambil semua data kelas
$sql = "SELECT * FROM kelas ORDER BY kode_kelas ASC";
$result_kelas = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Data Kelas - BPJS Kesehatan</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.css">
    <!-- endinject -->
    <!-- vendor css for this page -->
    <!-- End vendor css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <!-- endinject -->
    <!-- Layout style -->
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <!-- Layout style -->
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS -->
    <style>
        /* Tema BPJS - Warna Biru BPJS */
        :root {
            --bpjs-primary: #0077C8;
            --bpjs-secondary: #00A9E0;
            --bpjs-success: #4CAF50;
            --bpjs-warning: #FF9800;
            --bpjs-danger: #F44336;
            --bpjs-info: #2196F3;
            --bpjs-light: #f8f9fa;
            --bpjs-dark: #343a40;
        }
        
        /* FOTO PROFIL SIDEBAR - SAMA DENGAN TINDAKAN.PHP */
        .display-avatar {
            position: relative;
        }
        .avatar-edit-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 28px;
            height: 28px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            background-color: #0066cc;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .display-avatar:hover .avatar-edit-btn {
            opacity: 1;
        }
        /* Foto profil yang diperbesar */
        .display-avatar .profile-img.img-lg {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            border: none;
        }
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #0066b3 0%, #0098cc 100%);
            box-shadow: 0 4px 12px rgba(0, 119, 200, 0.3);
        }
        .kelas-badge {
            font-size: 0.85rem;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
        }
        .kelas-badge-1 {
            background-color: var(--bpjs-primary);
        }
        .kelas-badge-2 {
            background-color: var(--bpjs-secondary);
        }
        .kelas-badge-3 {
            background-color: #0056a3;
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .bpjs-bg-primary {
            background-color: var(--bpjs-primary) !important;
            color: white !important;
        }
        .bpjs-bg-secondary {
            background-color: var(--bpjs-secondary) !important;
            color: white !important;
        }
        .text-bpjs-primary {
            color: var(--bpjs-primary) !important;
        }
        .text-bpjs-secondary {
            color: var(--bpjs-secondary) !important;
        }
        .btn-bpjs-outline {
            border: 2px solid var(--bpjs-primary);
            color: var(--bpjs-primary);
            background: transparent;
        }
        .btn-bpjs-outline:hover {
            background: var(--bpjs-primary);
            color: white;
        }
        .table thead th {
            background-color: var(--bpjs-primary);
            color: white;
            border: none;
        }
        .table tbody tr:hover {
            background-color: rgba(0, 119, 200, 0.05);
        }
        .modal-header {
            border-bottom: 3px solid var(--bpjs-primary);
        }
        .btn-group-sm .btn {
            border-radius: 4px;
            margin: 0 2px;
        }
        .fasilitas-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .fasilitas-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .fasilitas-item:last-child {
            border-bottom: none;
        }
        .detail-card {
            border: 2px solid var(--bpjs-primary);
            border-radius: 10px;
            overflow: hidden;
        }
        .detail-header {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
            padding: 15px;
            margin: -1px -1px 0 -1px;
        }
        .logo-bpjs {
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            display: inline-flex;
            align-items: center;
        }
        .logo-bpjs i {
            margin-right: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .display-avatar .profile-img.img-lg {
                width: 80px;
                height: 80px;
            }
            
            .avatar-edit-btn {
                width: 28px;
                height: 28px;
            }
        }
    </style>
  </head>
  <body class="header-fixed">
  
    <!-- partial -->
    <div class="page-body">
      <!-- SIDEBAR - SAMA PERSIS DENGAN TINDAKAN.PHP -->
      <div class="sidebar">
        <div class="user-profile">
          <div class="display-avatar animated-avatar">
            <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                <!-- Jika ada foto profil yang diupload -->
                <img class="profile-img img-lg rounded-circle" 
                     src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                     alt="profile image"
                     onerror="this.style.display='none'; document.getElementById('avatar-default-kelas').style.display='block';">
            <?php endif; ?>
            
            <!-- Foto default (akan ditampilkan jika tidak ada custom photo) -->
            <img id="avatar-default-kelas" 
                 class="profile-img img-lg rounded-circle" 
                 src="<?php echo $has_custom_profile ? '' : $default_avatar; ?>" 
                 alt="profile image"
                 style="<?php echo $has_custom_profile ? 'display: none;' : ''; ?>">
            
            <!-- Tombol Edit Foto -->
            <a href="profile.php" 
               class="btn btn-primary btn-xs rounded-circle avatar-edit-btn" 
               title="Edit Profile Picture">
                <i class="mdi mdi-camera" style="font-size: 14px; color: white;"></i>
            </a>
          </div>
          <div class="info-wrapper">
            <p class="user-name"><?php echo htmlspecialchars($user['username']); ?></p>
            <h6 class="display-income">BPJS Member</h6>
          </div>
        </div>
        <ul class="navigation-menu">
          <li>
            <a href="dashboard.php">
              <span class="link-title">Dashboard</span>
              <i class="mdi mdi-gauge link-icon"></i>
            </a>
          </li>
          
          <!-- MENU DATA MASTER -->
          <li class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['peserta_bpjs.php', 'faskes.php', 'dokter.php', 'obat.php', 'tindakan.php', 'kelas.php']) ? 'active' : ''; ?>">
            <a href="#data-master" data-toggle="collapse" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['peserta_bpjs.php', 'faskes.php', 'dokter.php', 'obat.php', 'tindakan.php', 'kelas.php']) ? 'true' : 'false'; ?>">
              <span class="link-title">Data Master</span>
              <i class="mdi mdi-database link-icon"></i>
            </a>
            <ul class="collapse navigation-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['peserta_bpjs.php', 'faskes.php', 'dokter.php', 'obat.php', 'tindakan.php', 'kelas.php']) ? 'show' : ''; ?>" id="data-master">
              <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'peserta_bpjs.php' ? 'active' : ''; ?>">
                <a href="peserta_bpjs.php">Data Peserta</a>
              </li>
              <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'faskes.php' ? 'active' : ''; ?>">
                <a href="faskes.php">Data Faskes</a>
              </li>
              <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dokter.php' ? 'active' : ''; ?>">
                <a href="dokter.php">Data Dokter</a>
              </li>
              <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'obat.php' ? 'active' : ''; ?>">
                <a href="obat.php">Data Obat</a>
              </li>
              <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'tindakan.php' ? 'active' : ''; ?>">
                <a href="tindakan.php">Data Tindakan</a>
              </li>
              <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kelas.php' ? 'active' : ''; ?>">
                <a href="kelas.php">Data Kelas</a>
              </li>
            </ul>
          </li>
          
        <!-- MENU TRANSAKSI -->
        <li class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['pendaftaran.php', 'pembayaran.php', 'kunjungan.php', 'klaim.php', 'transfer_faskes.php', 'refund.php']) ? 'active' : ''; ?>">
            <a href="#transaksi" data-toggle="collapse" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['pendaftaran.php', 'pembayaran.php', 'kunjungan.php', 'klaim.php', 'transfer_faskes.php', 'refund.php']) ? 'true' : 'false'; ?>">
                <span class="link-title">Transaksi</span>
                <i class="mdi mdi-cash-multiple link-icon"></i>
            </a>
            <ul class="collapse navigation-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['pendaftaran.php', 'pembayaran.php', 'kunjungan.php', 'klaim.php', 'transfer_faskes.php', 'refund.php']) ? 'show' : ''; ?>" id="transaksi">
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'pendaftaran.php' ? 'active' : ''; ?>">
                    <a href="pendaftaran.php">Pendaftaran</a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'pembayaran.php' ? 'active' : ''; ?>">
                    <a href="pembayaran.php">Pembayaran</a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kunjungan.php' ? 'active' : ''; ?>">
                    <a href="kunjungan.php">Kunjungan</a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'klaim.php' ? 'active' : ''; ?>">
                    <a href="klaim.php">Klaim</a>
                </li>
            </ul>
        </li>
          
          <!-- MENU LAPORAN YANG DITAMBAHKAN -->
          <li>
            <a href="#laporan" data-toggle="collapse" aria-expanded="false">
              <span class="link-title">Laporan</span>
              <i class="mdi mdi-chart-bar link-icon"></i>
            </a>
            <ul class="collapse navigation-submenu" id="laporan">
              <li>
                <a href="laporan_peserta.php">Laporan Peserta</a>
              </li>
              <li>
                <a href="laporan_kunjungan.php">Laporan Kunjungan</a>
              </li>
              <li>
                <a href="laporan_klaim.php">Laporan Klaim</a>
              </li>
              <li>
                <a href="laporan_keuangan.php">Laporan Keuangan</a>
              </li>
              <li>
                <a href="laporan_statistik.php">Laporan Statistik</a>
              </li>
            </ul>
          </li>
          
          <!-- MENU ACCOUNT SETTINGS - SAMA SEPERTI TINDAKAN.PHP -->
          <li class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'ubah_password.php']) ? 'active' : ''; ?>">
            <a href="#account-settings" data-toggle="collapse" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'ubah_password.php']) ? 'true' : 'false'; ?>">
                <span class="link-title">Account Settings</span>
                <i class="mdi mdi-account-cog link-icon"></i>
            </a>
            <ul class="collapse navigation-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'ubah_password.php']) ? 'show' : ''; ?>" id="account-settings">
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <a href="profile.php">
                        <i class="mdi mdi-account-edit mr-2"></i> Profile & Photo
                    </a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ubah_password.php' ? 'active' : ''; ?>">
                    <a href="ubah_password.php">
                        <i class="mdi mdi-key-change mr-2"></i> Change Password
                    </a>
                </li>
            </ul>
          </li>
          
          <li>
            
            </a>
          </li>
          <li>
         
          </li>
          <li class="nav-category-divider">SYSTEM</li>
          <li>
            <a href="logout.php" class="text-danger">
              <span class="link-title">Logout</span>
              <i class="mdi mdi-logout link-icon"></i>
            </a>
          </li>
        </ul>
        <div class="sidebar-upgrade-banner" style="background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%); color: white;">
          <p class="text-white">BPJS Kesehatan Member</p>
          <a class="btn upgrade-btn" href="pendaftaran.php" style="background: white; color: var(--bpjs-primary);">Register Now</a>
        </div>
      </div>
      <!-- partial -->
      <div class="page-content-wrapper">
        <div class="page-content-wrapper-inner">
          <div class="content-viewport">
            
            <!-- Notifikasi -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="mdi mdi-<?php echo $message_type == 'success' ? 'check-circle' : 'alert-circle'; ?> mdi-1x me-3"></i>
                    <div><?php echo $message; ?></div>
                </div>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="row">
              <div class="col-12 py-3">
                <div class="d-flex align-items-center">
                  <div class="mr-3">
                    <div class="icon-wrapper rounded-circle bg-primary text-white p-3">
                      <i class="mdi mdi-layers mdi-24px"></i>
                    </div>
                  </div>
                  <div>
                    <h4 class="mb-1">Manajemen Data Kelas BPJS Kesehatan</h4>
                    <p class="text-muted mb-0">Kelola informasi kelas BPJS Kesehatan (Kelas 1, 2, 3)</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
              <div class="col-xl-3 col-md-6 col-sm-6 col-6 equel-grid">
                <div class="grid stat-card">
                  <div class="grid-body text-gray">
                    <div class="d-flex justify-content-between">
                      <p>
                        <?php 
                        $sql_total = "SELECT COUNT(*) as total FROM kelas";
                        $result_total = mysqli_query($conn, $sql_total);
                        $total = mysqli_fetch_assoc($result_total)['total'];
                        echo $total;
                        ?>
                      </p>
                      <p class="text-primary">Total</p>
                    </div>
                    <p class="text-black">Jumlah Kelas</p>
                    <div class="wrapper w-50 mt-4">
                      <div class="text-center">
                        <i class="mdi mdi-layers mdi-3x text-primary"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-md-6 col-sm-6 col-6 equel-grid">
                <div class="grid stat-card">
                  <div class="grid-body text-gray">
                    <div class="d-flex justify-content-between">
                      <p>
                        <?php 
                        $sql_peserta = "SELECT COUNT(*) as total FROM peserta";
                        $result_peserta = mysqli_query($conn, $sql_peserta);
                        $total_peserta = mysqli_fetch_assoc($result_peserta)['total'];
                        echo $total_peserta;
                        ?>
                      </p>
                      <p class="text-success">Total</p>
                    </div>
                    <p class="text-black">Jumlah Peserta</p>
                    <div class="wrapper w-50 mt-4">
                      <div class="text-center">
                        <i class="mdi mdi-account-multiple mdi-3x text-success"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-md-6 col-sm-6 col-6 equel-grid">
                <div class="grid stat-card">
                  <div class="grid-body text-gray">
                    <div class="d-flex justify-content-between">
                      <p>
                        <?php 
                        $sql_avg = "SELECT AVG(iuran_per_bulan) as rata FROM kelas";
                        $result_avg = mysqli_query($conn, $sql_avg);
                        $avg = mysqli_fetch_assoc($result_avg)['rata'];
                        if ($avg) {
                            echo "Rp " . number_format($avg, 0, ',', '.');
                        } else {
                            echo "-";
                        }
                        ?>
                      </p>
                      <p class="text-warning">Rata-rata</p>
                    </div>
                    <p class="text-black">Rata-rata Iuran</p>
                    <div class="wrapper w-50 mt-4">
                      <div class="text-center">
                        <i class="mdi mdi-cash mdi-3x text-warning"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-md-6 col-sm-6 col-6 equel-grid">
                <div class="grid stat-card">
                  <div class="grid-body text-gray">
                    <div class="d-flex justify-content-between">
                      <p>
                        <?php 
                        $sql_populer = "SELECT k.nama_kelas, COUNT(p.id) as jumlah 
                                       FROM kelas k 
                                       LEFT JOIN peserta p ON k.id = p.kelas_id 
                                       GROUP BY k.id 
                                       ORDER BY jumlah DESC 
                                       LIMIT 1";
                        $result_populer = mysqli_query($conn, $sql_populer);
                        if (mysqli_num_rows($result_populer) > 0) {
                            $populer = mysqli_fetch_assoc($result_populer);
                            echo $populer['nama_kelas'];
                        } else {
                            echo "-";
                        }
                        ?>
                      </p>
                      <p class="text-danger">Terpopuler</p>
                    </div>
                    <p class="text-black">Kelas Terpopuler</p>
                    <div class="wrapper w-50 mt-4">
                      <div class="text-center">
                        <i class="mdi mdi-chart-line mdi-3x text-danger"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Main Content Card -->
            <div class="row">
              <div class="col-12 equel-grid">
                <div class="grid">
                  <div class="grid-body">
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                      <div class="d-flex">
                        <button type="button" class="btn btn-primary-custom me-2" data-toggle="modal" data-target="#tambahModal">
                          <i class="mdi mdi-plus mr-1"></i> Tambah Kelas
                        </button>
                        <div class="dropdown">
                         
                          </ul>
                        </div>
                      </div>
                      <div style="width: 300px;">
                        <div class="input-group">
                          <input type="text" class="form-control" id="searchInput" 
                                 placeholder="Cari kode atau nama kelas...">
                          <div class="input-group-append">
                            <button class="btn btn-primary-custom" type="button" id="searchButton">
                              <i class="mdi mdi-magnify"></i>
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Data Table -->
                    <div class="table-responsive">
                      <table id="kelasTable" class="table table-hover table-striped">
                        <thead class="table-light">
                          <tr>
                            <th width="5%">No</th>
                            <th width="10%">Kode</th>
                            <th width="20%">Nama Kelas</th>
                            <th width="15%">Iuran/Bulan</th>
                            <th width="25%">Deskripsi</th>
                            <th width="10%">Peserta</th>
                            <th width="15%">Aksi</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php 
                          $no = 1;
                          if ($result_kelas && mysqli_num_rows($result_kelas) > 0):
                              while ($row = mysqli_fetch_assoc($result_kelas)):
                                  // Hitung jumlah peserta
                                  $sql_peserta = "SELECT COUNT(*) as total FROM peserta WHERE kelas_id = ?";
                                  $stmt = mysqli_prepare($conn, $sql_peserta);
                                  mysqli_stmt_bind_param($stmt, "i", $row['id']);
                                  mysqli_stmt_execute($stmt);
                                  $result_count = mysqli_stmt_get_result($stmt);
                                  $count_data = mysqli_fetch_assoc($result_count);
                                  $jumlah_peserta = $count_data['total'];
                                  mysqli_stmt_close($stmt);
                                  
                                  // Format iuran
                                  $iuran_formatted = "Rp " . number_format($row['iuran_per_bulan'], 0, ',', '.');
                                  
                                  // Potong deskripsi jika terlalu panjang
                                  $deskripsi_pendek = strlen($row['deskripsi']) > 60 ? 
                                      substr($row['deskripsi'], 0, 60) . "..." : $row['deskripsi'];
                                  
                                  // Tentukan badge berdasarkan kode kelas
                                  $badge_class = 'kelas-badge-1';
                                  if (strpos($row['kode_kelas'], '2') !== false) $badge_class = 'kelas-badge-2';
                                  if (strpos($row['kode_kelas'], '3') !== false) $badge_class = 'kelas-badge-3';
                                  
                                  // Format tanggal
                          ?>
                          <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td>
                              <span class="kelas-badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($row['kode_kelas']); ?>
                              </span>
                            </td>
                            <td>
                              <strong><?php echo htmlspecialchars($row['nama_kelas']); ?></strong>
                              <br>
                              <small class="text-muted">
                                
                                </a>
                              </small>
                            </td>
                            <td>
                              <span class="fw-bold text-primary"><?php echo $iuran_formatted; ?></span>
                              <br>
                              <small class="text-muted">per bulan</small>
                            </td>
                            <td>
                              <div class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($row['deskripsi']); ?>">
                                <?php echo htmlspecialchars($deskripsi_pendek); ?>
                              </div>
                            </td>
                            <td>
                              <span class="badge rounded-pill <?php echo $jumlah_peserta > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                <i class="mdi mdi-account me-1"></i><?php echo $jumlah_peserta; ?>
                              </span>
                            </td>
                            <td>
                              <div class="btn-group btn-group-sm">
                                <!-- Tombol Detail -->
                                <td>
    <div class="btn-group btn-group-sm">
        <!-- Tombol Detail -->
        <a href="detail_kelas.php?id=<?php echo $row['id']; ?>" 
           class="btn btn-info" title="Detail">
            <i class="mdi mdi-eye"></i>
        </a>
        
        <!-- Tombol Edit -->
        <a href="edit_kelas.php?id=<?php echo $row['id']; ?>" 
           class="btn btn-warning" title="Edit">
            <i class="mdi mdi-pencil"></i>
        </a>
        
        <!-- Tombol Hapus -->
        <a href="javascript:void(0);" 
           class="btn btn-danger btn-hapus" 
           title="Hapus"
           data-id="<?php echo $row['id']; ?>"
           data-nama="<?php echo htmlspecialchars($row['nama_kelas']); ?>">
            <i class="mdi mdi-delete"></i>
        </a>
    </div>
</td>
                          </tr>
                          <?php endwhile; ?>
                          <?php else: ?>
                          <tr>
                            <td colspan="7" class="text-center py-5">
                              <div class="text-muted">
                                <i class="mdi mdi-database-off mdi-3x mb-3"></i><br>
                                <h5>Belum ada data kelas</h5>
                                <p>Silakan tambah data kelas baru dengan menekan tombol "Tambah Kelas"</p>
                              </div>
                            </td>
                          </tr>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- partial -->
      </div>
      <!-- page content ends -->
    </div>
    <!--page body ends -->

    <!-- Tambah Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header card-header-custom">
            <h5 class="modal-title"><i class="mdi mdi-plus-circle me-2"></i>Tambah Kelas Baru</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form method="POST" action="">
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="kode_kelas" class="form-label">Kode Kelas <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="kode_kelas" name="kode_kelas" 
                         placeholder="Contoh: KLS1, KLS2, KLS3" required>
                  <div class="form-text">Kode unik (3-5 karakter)</div>
                </div>
                <div class="col-md-6">
                  <label for="nama_kelas" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" 
                         placeholder="Contoh: Kelas 1, Kelas 2, Kelas 3" required>
                </div>
              </div>
              
              <div class="mt-3">
                <label for="iuran_per_bulan" class="form-label">Iuran per Bulan (Rp) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">Rp</span>
                  <input type="number" class="form-control" id="iuran_per_bulan" name="iuran_per_bulan" 
                         placeholder="150000" required min="0" step="1000">
                </div>
                <div class="form-text">Iuran bulanan peserta</div>
              </div>
              
              <div class="mt-3">
                <label for="deskripsi" class="form-label">Deskripsi</label>
                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" 
                          placeholder="Deskripsi singkat tentang kelas ini..."></textarea>
              </div>
              
              <div class="mt-3">
                <label for="fasilitas" class="form-label">Fasilitas</label>
                <textarea class="form-control" id="fasilitas" name="fasilitas" rows="4" 
                          placeholder="Masukkan fasilitas, pisahkan dengan koma
Contoh: Rawat inap kelas 1, Konsultasi dokter spesialis, Obat generik"></textarea>
                <div class="form-text">Contoh: Rawat inap kelas 1, Konsultasi dokter spesialis, Obat generik</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
              <button type="submit" name="tambah" class="btn btn-primary-custom">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header card-header-custom">
            <h5 class="modal-title" id="editModalTitle">
              <i class="mdi mdi-pencil me-2"></i>Edit Kelas
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form method="POST" action="" id="editForm">
            <input type="hidden" name="id" id="editId">
            
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="editKodeKelas" class="form-label">
                    Kode Kelas <span class="text-danger">*</span>
                  </label>
                  <input type="text" class="form-control" id="editKodeKelas" name="kode_kelas" 
                         required maxlength="10">
                  <div class="form-text">Kode unik (contoh: KLS1, KLS2)</div>
                </div>
                
                <div class="col-md-6">
                  <label for="editNamaKelas" class="form-label">
                    Nama Kelas <span class="text-danger">*</span>
                  </label>
                  <input type="text" class="form-control" id="editNamaKelas" name="nama_kelas" 
                         required maxlength="50">
                </div>
              </div>
              
              <div class="mt-3">
                <label for="editIuran" class="form-label">
                  Iuran per Bulan (Rp) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text">Rp</span>
                  <input type="number" class="form-control" id="editIuran" name="iuran_per_bulan" 
                         required min="0" step="1000">
                </div>
              </div>
              
              <div class="mt-3">
                <label for="editDeskripsi" class="form-label">Deskripsi</label>
                <textarea class="form-control" id="editDeskripsi" name="deskripsi" rows="3"></textarea>
              </div>
              
              <div class="mt-3">
                <label for="editFasilitas" class="form-label">Fasilitas</label>
                <textarea class="form-control" id="editFasilitas" name="fasilitas" rows="4" 
                          placeholder="Pisahkan setiap fasilitas dengan koma"></textarea>
                <div class="form-text">Contoh: Rawat inap kelas 1, Konsultasi dokter, Obat generik</div>
              </div>
            </div>
            
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">
                <i class="mdi mdi-close me-1"></i> Batal
              </button>
              <button type="submit" name="edit" class="btn btn-primary-custom">
                <i class="mdi mdi-content-save me-1"></i> Simpan Perubahan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content detail-card">
          <div class="modal-header detail-header">
            <h5 class="modal-title">
              <i class="mdi mdi-information-outline me-2"></i>
              Detail Kelas BPJS Kesehatan
            </h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-12 mb-4">
                <div class="card border-0 shadow-sm">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                      <div>
                        <h4 class="card-title text-bpjs-primary mb-1" id="detailNamaKelas">Nama Kelas</h4>
                        <div class="d-flex align-items-center">
                          <span class="kelas-badge kelas-badge-1 mr-3" id="detailKodeKelas">KLS1</span>
                          <span class="text-muted">
                            <i class="mdi mdi-calendar mr-1"></i>
                            Dibuat: <span id="detailDibuat">-</span>
                          </span>
                        </div>
                      </div>
                      <div class="text-right">
                        <h5 class="text-success mb-0" id="detailIuran">Rp 0</h5>
                        <small class="text-muted">Iuran per bulan</small>
                      </div>
                    </div>
                    
                    <div class="mb-4">
                      <h6 class="text-bpjs-secondary mb-2"><i class="mdi mdi-text-short mr-2"></i>Deskripsi</h6>
                      <p class="card-text" id="detailDeskripsi">Deskripsi kelas</p>
                    </div>
                    
                    <div class="row">
                      <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                          <div class="icon-wrapper rounded-circle bg-info text-white p-2 mr-3">
                            <i class="mdi mdi-account-group mdi-18px"></i>
                          </div>
                          <div>
                            <h6 class="mb-0">Jumlah Peserta</h6>
                            <h4 class="text-info mb-0" id="detailPeserta">0</h4>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                          <div class="icon-wrapper rounded-circle bg-warning text-white p-2 mr-3">
                            <i class="mdi mdi-update mdi-18px"></i>
                          </div>
                          <div>
                            <h6 class="mb-0">Terakhir Diperbarui</h6>
                            <p class="text-muted mb-0" id="detailDiupdate">-</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                  <div class="card-header bg-light">
                    <h5 class="mb-0">
                      <i class="mdi mdi-checkbox-multiple-marked-circle mr-2 text-bpjs-primary"></i>
                      Fasilitas Kelas
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="fasilitas-list" id="detailFasilitasList">
                      <!-- Fasilitas akan dimasukkan melalui JavaScript -->
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="mdi mdi-close mr-1"></i> Tutup
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Fasilitas Modal -->
    <div class="modal fade" id="fasilitasModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header card-header-custom">
            <h5 class="modal-title"><i class="mdi mdi-checkbox-multiple-marked me-2"></i>Fasilitas Kelas</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="fasilitasContent">
              <!-- Fasilitas akan ditampilkan di sini -->
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- SCRIPT LOADING -->
    <!-- plugins:js -->
    <script src="../assets/vendors/js/core.js"></script>
    <!-- endinject -->
    <!-- Vendor Js For This Page Ends-->
    <script src="../assets/vendors/apexcharts/apexcharts.min.js"></script>
    <script src="../assets/vendors/chartjs/Chart.min.js"></script>
    <script src="../assets/js/charts/chartjs.addon.js"></script>
    <!-- Vendor Js For This Page Ends-->
    <!-- build:js -->
    <script src="../assets/js/template.js"></script>
    <!-- endbuild -->
    
    <!-- jQuery dan DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
    // Konfirmasi hapus dengan modal
$(document).on('click', '.btn-hapus', function() {
    const id = $(this).data('id');
    const nama = $(this).data('nama');
    
    // Tampilkan konfirmasi
    if (confirm(`Apakah Anda yakin ingin menghapus kelas ${nama}?`)) {
        window.location.href = 'kelas.php?hapus=' + id;
    }
});
        
        // Tombol detail
        $('.btn-detail').click(function() {
            const kode = $(this).data('kode');
            const nama = $(this).data('nama');
            const iuran = $(this).data('iuran');
            const deskripsi = $(this).data('deskripsi');
            const fasilitas = $(this).data('fasilitas');
            const dibuat = $(this).data('dibuat');
            const diupdate = $(this).data('diupdate');
            const jumlahPeserta = $(this).data('jumlah-peserta');
            
            // Isi data ke modal detail
            $('#detailNamaKelas').text(nama);
            $('#detailKodeKelas').text(kode);
            $('#detailDeskripsi').text(deskripsi || 'Tidak ada deskripsi');
            $('#detailIuran').text('Rp ' + formatNumber(iuran));
            $('#detailDibuat').text(dibuat);
            $('#detailDiupdate').text(diupdate);
            $('#detailPeserta').text(jumlahPeserta);
            
            // Tampilkan fasilitas
            showDetailFasilitas(fasilitas);
            
            // Tampilkan modal detail
            $('#detailModal').modal('show');
        });
        
        // Tombol edit
        $('.btn-edit').click(function() {
            const id = $(this).data('id');
            const kode = $(this).data('kode');
            const nama = $(this).data('nama');
            const iuran = $(this).data('iuran');
            const deskripsi = $(this).data('deskripsi');
            const fasilitas = $(this).data('fasilitas');
            
            // Isi form edit
            $('#editId').val(id);
            $('#editKodeKelas').val(kode);
            $('#editNamaKelas').val(nama);
            $('#editIuran').val(iuran);
            $('#editDeskripsi').val(deskripsi);
            $('#editFasilitas').val(fasilitas);
            
            // Update judul modal
            $('#editModalTitle').html(`<i class="mdi mdi-pencil me-2"></i>Edit Kelas: ${nama}`);
            
            // Tampilkan modal
            $('#editModal').modal('show');
        });
        
        // Tombol lihat fasilitas (di tabel)
        $('.btn-view-fasilitas').click(function(e) {
            e.preventDefault();
            const fasilitas = $(this).data('fasilitas');
            showFasilitas(fasilitas);
        });
        
        // Fungsi tampilkan fasilitas di modal kecil
        function showFasilitas(fasilitas) {
            const fasilitasArray = fasilitas.split(',').map(item => item.trim());
            let html = '';
            
            if (fasilitasArray.length === 1 && fasilitasArray[0] === '') {
                html = `
                    <div class="text-center py-4">
                        <i class="mdi mdi-information-outline mdi-3x text-muted mb-3"></i>
                        <p class="text-muted">Tidak ada fasilitas yang tercatat</p>
                    </div>`;
            } else {
                html = '<div class="list-group">';
                fasilitasArray.forEach((item, index) => {
                    if (item) {
                        html += `
                        <div class="list-group-item border-0 py-3 fasilitas-item">
                            <div class="d-flex align-items-start">
                                <i class="mdi mdi-check-circle text-success mt-1 me-3"></i>
                                <div>
                                    <p class="mb-0">${item}</p>
                                </div>
                            </div>
                        </div>`;
                    }
                });
                html += '</div>';
            }
            
            document.getElementById('fasilitasContent').innerHTML = html;
            $('#fasilitasModal').modal('show');
        }
        
        // Fungsi tampilkan fasilitas di modal detail
        function showDetailFasilitas(fasilitas) {
            const fasilitasArray = fasilitas.split(',').map(item => item.trim());
            let html = '';
            
            if (fasilitasArray.length === 1 && fasilitasArray[0] === '') {
                html = `
                    <div class="text-center py-4">
                        <i class="mdi mdi-information-outline mdi-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Tidak ada fasilitas yang tercatat untuk kelas ini</p>
                    </div>`;
            } else {
                html = '<ul class="list-unstyled mb-0">';
                fasilitasArray.forEach((item, index) => {
                    if (item) {
                        html += `
                        <li class="mb-3 fasilitas-item">
                            <div class="d-flex align-items-start">
                                <div class="icon-wrapper rounded-circle bg-success text-white p-1 me-3">
                                    <i class="mdi mdi-check mdi-18px"></i>
                                </div>
                                <div>
                                    <p class="mb-0">${item}</p>
                                </div>
                            </div>
                        </li>`;
                    }
                });
                html += '</ul>';
            }
            
            document.getElementById('detailFasilitasList').innerHTML = html;
        }
        
        // Format angka dengan pemisah ribuan
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Format input iuran
        $('#iuran_per_bulan, #editIuran').on('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
        
        // Reset form edit saat modal ditutup
        $('#editModal').on('hidden.bs.modal', function() {
            $('#editForm')[0].reset();
            $('#editId').val('');
            $('#editModalTitle').html('<i class="mdi mdi-pencil me-2"></i>Edit Kelas');
        });
        
        // Validasi form edit
        $('#editForm').on('submit', function(e) {
            const kode = $('#editKodeKelas').val().trim();
            const nama = $('#editNamaKelas').val().trim();
            const iuran = $('#editIuran').val();
            
            // Validasi dasar
            if (!kode) {
                e.preventDefault();
                alert('Kode kelas harus diisi');
                $('#editKodeKelas').focus();
                return false;
            }
            
            if (!nama) {
                e.preventDefault();
                alert('Nama kelas harus diisi');
                $('#editNamaKelas').focus();
                return false;
            }
            
            if (!iuran || iuran <= 0) {
                e.preventDefault();
                alert('Iuran harus lebih dari 0');
                $('#editIuran').focus();
                return false;
            }
            
            return true;
        });
        
        // Validasi form tambah
        $('form[action=""]').on('submit', function(e) {
            const isEditForm = $(this).attr('id') === 'editForm';
            if (!isEditForm) {
                const kode = $('#kode_kelas').val().trim();
                const nama = $('#nama_kelas').val().trim();
                const iuran = $('#iuran_per_bulan').val();
                
                if (!kode) {
                    e.preventDefault();
                    alert('Kode kelas harus diisi');
                    $('#kode_kelas').focus();
                    return false;
                }
                
                if (!nama) {
                    e.preventDefault();
                    alert('Nama kelas harus diisi');
                    $('#nama_kelas').focus();
                    return false;
                }
                
                if (!iuran || iuran <= 0) {
                    e.preventDefault();
                    alert('Iuran harus lebih dari 0');
                    $('#iuran_per_bulan').focus();
                    return false;
                }
            }
        });
        
        // Konfirmasi hapus
        $('.btn-delete').click(function(e) {
            const nama = $(this).data('nama');
            if (!confirm(`Apakah Anda yakin ingin menghapus kelas ${nama}?`)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Session timeout notification - SAMA SEPERTI TINDAKAN.PHP
        let idleTime = 0;
        const idleInterval = setInterval(() => {
            idleTime++;
            if (idleTime === 25) {
                console.log("Sesi Anda akan berakhir dalam 5 menit karena tidak aktif.");
            }
            if (idleTime > 30) {
                window.location.href = "logout.php?reason=timeout";
            }
        }, 60000);
        
        // Reset idle time on activity
        $(document).on('mousemove keypress click', function() {
            idleTime = 0;
        });
        
        // Auto refresh foto profil - SAMA SEPERTI TINDAKAN.PHP
        function refreshProfilePhoto() {
            const profileImages = document.querySelectorAll('.profile-img');
            profileImages.forEach(img => {
                if (img.src && img.src.includes('uploads/profile_pics/')) {
                    const baseSrc = img.src.split('?')[0];
                    img.src = baseSrc + '?refresh=' + new Date().getTime();
                }
            });
        }
        
        // Refresh saat halaman dimuat
        window.addEventListener('load', function() {
            setTimeout(refreshProfilePhoto, 100);
        });
        
        // Refresh saat kembali dari profile.php
        if (document.referrer.includes('profile.php')) {
            setTimeout(refreshProfilePhoto, 300);
            setTimeout(function() {
                location.reload();
            }, 500);
        }
    </script>
  </body>
</html>