<?php
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

// Pagination - FIXED
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;
if ($offset < 0) {
    $offset = 0;
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE kode_tindakan LIKE '%$search%' OR nama_tindakan LIKE '%$search%' OR kategori LIKE '%$search%'";
}

// Get total records
$sql_total = "SELECT COUNT(*) as total FROM tindakan $search_condition";
$result_total = mysqli_query($conn, $sql_total);
$total_records = mysqli_fetch_assoc($result_total)['total'];
$total_pages = ceil($total_records / $limit);

// Get tindakan data - FIXED LIMIT syntax
$sql = "SELECT * FROM tindakan $search_condition ORDER BY created_at DESC LIMIT $offset, $limit";
$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Query error: " . mysqli_error($conn));
}
$tindakan_data = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get kategori untuk filter
$kategori_options = [];
$kategori_query = "SELECT DISTINCT kategori FROM tindakan WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori";
$kategori_result = mysqli_query($conn, $kategori_query);
while ($row = mysqli_fetch_assoc($kategori_result)) {
    $kategori_options[] = $row['kategori'];
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Data Tindakan - BPJS Kesehatan</title>
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
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom BPJS Theme CSS -->
    <style>
        :root {
            --bpjs-blue: #0066cc;
            --bpjs-light-blue: #0088ff;
            --bpjs-green: #00a859;
            --bpjs-light-green: #00cc6f;
            --bpjs-dark: #1a365d;
            --bpjs-warning: #ff9900;
            --bpjs-danger: #e53e3e;
        }
        
        /* FOTO PROFIL SIDEBAR - SAMA DENGAN OBAT.PHP */
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
        
        /* Header styling - SAMA SEPERTI OBAT.PHP */
        .bpjs-page-header {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.15);
        }
        
        /* Stat cards with BPJS theme */
        .stat-card-bpjs {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid var(--bpjs-blue);
            height: 100%;
        }
        
        .stat-card-bpjs:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }
        
        .stat-card-bpjs.total { border-left-color: var(--bpjs-blue); }
        .stat-card-bpjs.active { border-left-color: var(--bpjs-green); }
        .stat-card-bpjs.category { border-left-color: var(--bpjs-warning); }
        .stat-card-bpjs.inactive { border-left-color: var(--bpjs-danger); }
        
        .stat-icon-bpjs {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stat-icon-bpjs.total { background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%); }
        .stat-icon-bpjs.active { background: linear-gradient(135deg, var(--bpjs-green) 0%, var(--bpjs-light-green) 100%); }
        .stat-icon-bpjs.category { background: linear-gradient(135deg, var(--bpjs-warning) 0%, #ffcc00 100%); }
        .stat-icon-bpjs.inactive { background: linear-gradient(135deg, var(--bpjs-danger) 0%, #fc8181 100%); }
        
        /* Main card styling */
        .main-card-bpjs {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            background: white;
        }
        
        .card-header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
        }
        
        /* Buttons with BPJS theme */
        .btn-bpjs-primary {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 102, 204, 0.2);
        }
        
        .btn-bpjs-primary:hover {
            background: linear-gradient(135deg, #0052a3 0%, #0077d9 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
        }
        
        /* Table styling */
        .table-bpjs {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .table-bpjs thead {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
            color: white;
        }
        
        .table-bpjs th {
            border: none;
            padding: 15px 12px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .table-bpjs td {
            padding: 12px;
            vertical-align: middle;
            border-color: #f0f4f8;
        }
        
        .table-bpjs tbody tr {
            transition: all 0.2s ease;
        }
        
        .table-bpjs tbody tr:hover {
            background-color: #f8fbff;
        }
        
        /* Badges */
        .badge-bpjs {
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .badge-bpjs-aktif {
            background: linear-gradient(135deg, var(--bpjs-green) 0%, var(--bpjs-light-green) 100%);
            color: white;
        }
        
        .badge-bpjs-nonaktif {
            background: linear-gradient(135deg, var(--bpjs-danger) 0%, #fc8181 100%);
            color: white;
        }
        
        .badge-kategori-bpjs {
            background: linear-gradient(135deg, #e6f2ff 0%, #cce6ff 100%);
            color: var(--bpjs-blue);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid #b3d9ff;
        }
        
        /* Price tags */
        .price-tag-bpjs {
            background: linear-gradient(135deg, #e8f5e8 0%, #d1f0d1 100%);
            color: #1a7d32;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
            border: 1px solid #c8e6c9;
        }
        
        .price-tag-non-bpjs {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #e65100;
            border: 1px solid #ffcc80;
        }
        
        /* Action buttons */
        .action-buttons-bpjs .btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            transition: all 0.3s ease;
        }
        
        .action-buttons-bpjs .btn:hover {
            transform: translateY(-2px);
        }
        
        /* Search box - SAMA SEPERTI OBAT.PHP */
        .search-box-bpjs {
            position: relative;
            max-width: 350px;
        }
        
        .search-box-bpjs input {
            border-radius: 20px;
            padding-left: 40px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .search-box-bpjs input:focus {
            border-color: var(--bpjs-blue);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }
        
        .search-box-bpjs i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            z-index: 10;
        }
        
        /* Pagination */
        .pagination-bpjs .page-link {
            color: var(--bpjs-blue);
            border: 1px solid #dee2e6;
            margin: 0 3px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .pagination-bpjs .page-item.active .page-link {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
            border-color: var(--bpjs-blue);
            color: white;
        }
        
        .pagination-bpjs .page-link:hover {
            background-color: #e6f2ff;
            border-color: var(--bpjs-blue);
        }
        
        /* Alerts */
        .alert-bpjs {
            border-radius: 8px;
            border: none;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .alert-bpjs-success {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-left: 4px solid var(--bpjs-green);
            color: #1b5e20;
        }
        
        .alert-bpjs-danger {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-left: 4px solid var(--bpjs-danger);
            color: #c62828;
        }
        
        /* Modal */
        .modal-header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        /* Info section */
        .info-section-bpjs {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        /* Quick stats */
        .quick-stats-bpjs {
            background: linear-gradient(135deg, #fff8e1 0%, #fff3cd 100%);
            border-left: 4px solid var(--bpjs-warning);
            border-radius: 8px;
            padding: 1rem;
        }
        
        /* Empty state */
        .empty-state-bpjs {
            padding: 3rem 1rem;
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
    <div class="page-body">
        <!-- SIDEBAR - SAMA PERSIS DENGAN OBAT.PHP -->
        <div class="sidebar">
            <div class="user-profile">
                <div class="display-avatar animated-avatar">
                    <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                        <!-- Jika ada foto profil yang diupload -->
                        <img class="profile-img img-lg rounded-circle" 
                             src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                             alt="profile image"
                             onerror="this.style.display='none'; document.getElementById('avatar-default-tindakan').style.display='block';">
                    <?php endif; ?>
                    
                    <!-- Foto default (akan ditampilkan jika tidak ada custom photo) -->
                    <img id="avatar-default-tindakan" 
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
                
                <!-- Dashboard Menu -->
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
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
                
                <!-- MENU LAPORAN -->
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
                
                <!-- MENU ACCOUNT SETTINGS -->
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
            
            <div class="sidebar-upgrade-banner">
                <p class="text-gray">BPJS Kesehatan Member</p>
                <a class="btn upgrade-btn" href="pendaftaran.php">Register Now</a>
            </div>
        </div>

      <!-- Main Content Area - Dengan tema BPJS -->
      <div class="page-content-wrapper">
        <div class="page-content-wrapper-inner">
          <div class="content-viewport">
            
            <!-- BPJS Page Header -->
            <div class="bpjs-page-header">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <h4 class="mb-2 fw-bold">
                    <i class="fas fa-procedures me-2"></i> Manajemen Data Tindakan Medis
                  </h4>
                  <p class="mb-0 opacity-75">
                    <i class="fas fa-shield-alt me-1"></i> BPJS Kesehatan - Sistem Pengelolaan Tindakan Medis Terintegrasi
                  </p>
                </div>
                <div class="col-md-4 text-md-end">
                  <small class="d-block mb-1">
                    <i class="fas fa-user-md me-1"></i> <?php echo htmlspecialchars($user['username']); ?>
                  </small>
                  <small class="d-block opacity-75">
                    <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d F Y'); ?>
                  </small>
                </div>
              </div>
            </div>

            <!-- Notifikasi -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-bpjs alert-bpjs-success alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1 fw-bold">Berhasil!</h6>
                            <p class="mb-0"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-bpjs alert-bpjs-danger alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1 fw-bold">Perhatian!</h6>
                            <p class="mb-0"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
              <div class="col-xl-3 col-md-6">
                <div class="stat-card-bpjs total">
                  <div class="d-flex align-items-center">
                    <div class="stat-icon-bpjs total me-3">
                      <i class="fas fa-procedures"></i>
                    </div>
                    <div>
                      <h3 class="fw-bold mb-0"><?php echo $total_records; ?></h3>
                      <p class="text-muted mb-0">Total Tindakan</p>
                    </div>
                  </div>
                  <div class="mt-3">
                    <small class="text-primary">
                      <i class="fas fa-database me-1"></i> Semua data tindakan
                    </small>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-md-6">
                <div class="stat-card-bpjs active">
                  <div class="d-flex align-items-center">
                    <div class="stat-icon-bpjs active me-3">
                      <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                      <h3 class="fw-bold mb-0">
                        <?php 
                        $sql_aktif = "SELECT COUNT(*) as total FROM tindakan WHERE status = 'aktif'";
                        $result_aktif = mysqli_query($conn, $sql_aktif);
                        echo mysqli_fetch_assoc($result_aktif)['total'];
                        ?>
                      </h3>
                      <p class="text-muted mb-0">Tindakan Aktif</p>
                    </div>
                  </div>
                  <div class="mt-3">
                    <small class="text-success">
                      <i class="fas fa-heartbeat me-1"></i> Siap digunakan
                    </small>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-md-6">
                <div class="stat-card-bpjs category">
                  <div class="d-flex align-items-center">
                    <div class="stat-icon-bpjs category me-3">
                      <i class="fas fa-tags"></i>
                    </div>
                    <div>
                      <h3 class="fw-bold mb-0"><?php echo count($kategori_options); ?></h3>
                      <p class="text-muted mb-0">Kategori Tindakan</p>
                    </div>
                  </div>
                  <div class="mt-3">
                    <small class="text-warning">
                      <i class="fas fa-folder-open me-1"></i> Terkelompokkan
                    </small>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-md-6">
                <div class="stat-card-bpjs inactive">
                  <div class="d-flex align-items-center">
                    <div class="stat-icon-bpjs inactive me-3">
                      <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                      <h3 class="fw-bold mb-0">
                        <?php 
                        $sql_nonaktif = "SELECT COUNT(*) as total FROM tindakan WHERE status = 'nonaktif' OR status = 'tidak aktif'";
                        $result_nonaktif = mysqli_query($conn, $sql_nonaktif);
                        echo mysqli_fetch_assoc($result_nonaktif)['total'];
                        ?>
                      </h3>
                      <p class="text-muted mb-0">Tindakan Nonaktif</p>
                    </div>
                  </div>
                  <div class="mt-3">
                    <small class="text-danger">
                      <i class="fas fa-exclamation-triangle me-1"></i> Perlu perhatian
                    </small>
                  </div>
                </div>
              </div>
            </div>

            <!-- Main Content Card -->
            <div class="main-card-bpjs mb-4">
              <div class="card-header-bpjs">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h5 class="mb-0 fw-bold">
                      <i class="fas fa-list-ul me-2"></i> Daftar Tindakan Medis
                    </h5>
                    <p class="mb-0 opacity-75">Kelola data tindakan medis untuk kebutuhan kesehatan peserta BPJS</p>
                  </div>
                  <div>
                    <span class="badge bg-light text-dark p-2">
                      <i class="fas fa-database me-1"></i> <?php echo $total_records; ?> records
                    </span>
                  </div>
                </div>
              </div>
              
              <div class="card-body">
                <!-- Action Buttons & Search -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                  <div class="d-flex flex-wrap gap-2">
                    <a href="tindakan_tambah.php" class="btn btn-bpjs-primary">
                      <i class="fas fa-plus-circle me-2"></i> Tambah Tindakan
                    </a>
                    <div class="dropdown">
                      
                      </button>
                      <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-file-excel me-2"></i> Excel</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-file-pdf me-2"></i> PDF</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-file-csv me-2"></i> CSV</a></li>
                      </ul>
                    </div>
                    <?php if(!empty($kategori_options)): ?>
                    <div class="dropdown">
                      <button class="btn btn-outline-success dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-filter me-2"></i> Filter Kategori
                      </button>
                      <ul class="dropdown-menu">
                        <?php foreach($kategori_options as $kategori): ?>
                        <li><a class="dropdown-item" href="?search=<?php echo urlencode($kategori); ?>"><?php echo htmlspecialchars($kategori); ?></a></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                    <?php endif; ?>
                  </div>
                  
                  <div class="search-box-bpjs">
                    <i class="fas fa-search"></i>
                    <form action="" method="GET" class="d-inline">
                      <input type="text" class="form-control" name="search" 
                             placeholder="Cari kode, nama, atau kategori..." 
                             value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                  </div>
                </div>

                <!-- Data Table -->
                <div class="table-responsive">
                  <table class="table table-bpjs table-hover">
                    <thead>
                      <tr>
                        <th width="60" class="text-center">No</th>
                        <th>Kode Tindakan</th>
                        <th>Nama Tindakan</th>
                        <th>Kategori</th>
                        <th>Tarif</th>
                        <th width="120" class="text-center">Status</th>
                        <th width="120" class="text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if(empty($tindakan_data)): ?>
                      <tr>
                        <td colspan="7" class="text-center py-5">
                          <div class="empty-state-bpjs">
                            <i class="fas fa-hospital-user fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted mb-3">Belum ada data tindakan</h5>
                            <p class="text-muted mb-4">Mulai dengan menambahkan data tindakan baru untuk peserta BPJS</p>
                            <a href="tindakan_tambah.php" class="btn btn-bpjs-primary">
                              <i class="fas fa-plus-circle me-2"></i> Tambah Tindakan Pertama
                            </a>
                          </div>
                        </td>
                      </tr>
                      <?php else: ?>
                      <?php $no = $offset + 1; ?>
                      <?php foreach($tindakan_data as $tindakan): ?>
                      <tr>
                        <td class="text-center fw-bold align-middle">
                          <span class="badge bg-light text-dark"><?php echo $no++; ?></span>
                        </td>
                        <td class="align-middle">
                          <div class="fw-bold text-primary">
                            <i class="fas fa-hashtag me-1"></i>
                            <?php echo htmlspecialchars($tindakan['kode_tindakan'] ?? 'N/A'); ?>
                          </div>
                          <small class="text-muted">ID: <?php echo $tindakan['id']; ?></small>
                        </td>
                        <td class="align-middle">
                          <div class="fw-semibold"><?php echo htmlspecialchars($tindakan['nama_tindakan']); ?></div>
                          <?php if(!empty($tindakan['deskripsi'])): ?>
                          <small class="text-muted d-block mt-1">
                            <i class="fas fa-info-circle me-1"></i>
                            <?php 
                            $deskripsi = htmlspecialchars($tindakan['deskripsi']);
                            echo strlen($deskripsi) > 80 ? substr($deskripsi, 0, 80) . '...' : $deskripsi;
                            ?>
                          </small>
                          <?php endif; ?>
                        </td>
                        <td class="align-middle">
                          <?php if(!empty($tindakan['kategori'])): ?>
                          <span class="badge-kategori-bpjs">
                            <i class="fas fa-tag me-1"></i>
                            <?php echo htmlspecialchars($tindakan['kategori']); ?>
                          </span>
                          <?php else: ?>
                          <span class="text-muted"><i class="fas fa-times me-1"></i> Tidak dikategorikan</span>
                          <?php endif; ?>
                        </td>
                        <td class="align-middle">
                          <div class="mb-2">
                            <small class="text-muted d-block mb-1">
                              <i class="fas fa-shield-alt me-1"></i> BPJS Kesehatan:
                            </small>
                            <span class="price-tag-bpjs">
                              <i class="fas fa-money-bill-wave me-1"></i>
                              Rp <?php echo number_format($tindakan['tarif_bpjs'] ?? 0, 0, ',', '.'); ?>
                            </span>
                          </div>
                          <div>
                            <small class="text-muted d-block mb-1">
                              <i class="fas fa-user me-1"></i> Non-BPJS:
                            </small>
                            <span class="price-tag-bpjs price-tag-non-bpjs">
                              <i class="fas fa-money-bill-alt me-1"></i>
                              Rp <?php echo number_format($tindakan['tarif_non_bpjs'] ?? 0, 0, ',', '.'); ?>
                            </span>
                          </div>
                        </td>
                        <td class="align-middle text-center">
                          <?php 
                          $status = $tindakan['status'] ?? 'aktif';
                          if($status == 'aktif'): ?>
                          <span class="badge-bpjs badge-bpjs-aktif">
                            <i class="fas fa-check-circle me-1"></i> Aktif
                          </span>
                          <?php else: ?>
                          <span class="badge-bpjs badge-bpjs-nonaktif">
                            <i class="fas fa-times-circle me-1"></i> Nonaktif
                          </span>
                          <?php endif; ?>
                        </td>
                        <td class="align-middle text-center action-buttons-bpjs">
                          <div class="d-flex justify-content-center">
                            <a href="tindakan_detail.php?id=<?php echo $tindakan['id']; ?>" 
                               class="btn btn-info btn-sm" 
                               title="Detail Tindakan">
                              <i class="fas fa-eye"></i>
                            </a>
                            <a href="tindakan_edit.php?id=<?php echo $tindakan['id']; ?>" 
                               class="btn btn-warning btn-sm" 
                               title="Edit Data">
                              <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" 
                                    class="btn btn-danger btn-sm" 
                                    onclick="confirmDelete(<?php echo $tindakan['id']; ?>, '<?php echo htmlspecialchars(addslashes($tindakan['nama_tindakan'])); ?>')" 
                                    title="Hapus Tindakan">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                  <ul class="pagination pagination-bpjs justify-content-center">
                    <?php if($page > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                      </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                      <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                      </a>
                    </li>
                    <?php endif; ?>
                  </ul>
                </nav>
                <?php endif; ?>

                <!-- Summary -->
                <div class="text-center text-muted mt-3">
                  <small>
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <?php echo count($tindakan_data); ?> dari <?php echo $total_records; ?> data tindakan
                    <?php if(!empty($search)): ?>
                    untuk pencarian "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    <?php endif; ?>
                  </small>
                </div>
              </div>
            </div>

            <!-- Additional Information -->
            <div class="row mb-4">
              <div class="col-md-4">
                <div class="info-section-bpjs">
                  <h6 class="fw-bold mb-3">
                    <i class="fas fa-chart-pie me-2 text-primary"></i> Distribusi Kategori
                  </h6>
                  <?php 
                  $sql_stats = "SELECT kategori, COUNT(*) as count FROM tindakan WHERE kategori IS NOT NULL AND kategori != '' GROUP BY kategori ORDER BY count DESC LIMIT 5";
                  $result_stats = mysqli_query($conn, $sql_stats);
                  $categories = mysqli_fetch_all($result_stats, MYSQLI_ASSOC);
                  ?>
                  <?php foreach($categories as $cat): ?>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-truncate" style="max-width: 70%;">
                      <i class="fas fa-circle me-2" style="font-size: 8px; color: #0066cc;"></i>
                      <?php echo htmlspecialchars($cat['kategori']); ?>
                    </span>
                    <span class="badge bg-light text-dark"><?php echo $cat['count']; ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-md-8">
                <div class="quick-stats-bpjs">
                  <div class="d-flex">
                    <i class="fas fa-lightbulb fa-2x text-warning me-3 mt-1"></i>
                    <div>
                      <h6 class="fw-bold mb-2">Tips Pengelolaan Tindakan Medis</h6>
                      <ul class="mb-0 ps-3" style="font-size: 0.9rem;">
                        <li class="mb-1">Pastikan kode tindakan unik untuk setiap prosedur medis</li>
                        <li class="mb-1">Perbarui tarif sesuai dengan ketentuan BPJS Kesehatan terbaru</li>
                        <li class="mb-1">Verifikasi status tindakan sebelum digunakan untuk klaim</li>
                        <li>Gunakan kategori untuk memudahkan pencarian dan pelaporan</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header modal-header-bpjs">
            <h5 class="modal-title fw-bold">
              <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus
            </h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body text-center py-4">
            <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
            <h6 id="deleteMessage">Apakah Anda yakin ingin menghapus data ini?</h6>
            <p class="text-muted mb-0">Data yang dihapus tidak dapat dikembalikan</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <a href="#" id="deleteLink" class="btn btn-danger">
              <i class="fas fa-trash-alt me-1"></i> Ya, Hapus
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/vendors/js/core.js"></script>
    <script src="../assets/vendors/apexcharts/apexcharts.min.js"></script>
    <script src="../assets/vendors/chartjs/Chart.min.js"></script>
    <script src="../assets/js/charts/chartjs.addon.js"></script>
    <script src="../assets/js/template.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
      // Confirm delete function
      function confirmDelete(id, nama) {
        document.getElementById('deleteLink').href = 'tindakan_hapus.php?id=' + id;
        if (nama) {
          document.getElementById('deleteMessage').innerHTML = 
            'Apakah Anda yakin ingin menghapus tindakan <strong>"' + nama + '"</strong>?';
        }
        $('#deleteModal').modal('show');
      }

      // Auto-hide alerts after 5 seconds
      $(document).ready(function() {
        setTimeout(function() {
          $('.alert').alert('close');
        }, 5000);
        
        // Auto focus search input
        if(window.location.search.includes('search=')) {
          $('input[name="search"]').focus().select();
        }
        
        // Add enter key submit for search
        $('input[name="search"]').keypress(function(e) {
          if(e.which == 13) {
            $(this).closest('form').submit();
          }
        });
      });

      // Session timeout notification - SAMA SEPERTI OBAT.PHP
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
      
      // Auto refresh foto profil - SAMA SEPERTI OBAT.PHP
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