<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user termasuk profile_pic
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Cek foto profil
$profile_pic = $user['profile_pic'] ?? '';
$profile_path = 'uploads/profile_pics/' . $profile_pic;
$has_custom_profile = (!empty($profile_pic) && file_exists($profile_path));
$default_avatar = '../assets/images/faces/face1.jpg';

// Update last activity
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard BPJS - <?php echo htmlspecialchars($user['username']); ?></title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.css">
    <!-- endinject -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <!-- endinject -->
    <!-- Layout style -->
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <!-- Layout style -->
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    
    <style>
    /* ===== TEMA BPJS ===== */
    :root {
        --bpjs-blue: #0066cc;
        --bpjs-light-blue: #00a8e8;
        --bpjs-green: #28a745;
        --bpjs-teal: #17a2b8;
        --bpjs-yellow: #ffc107;
        --bpjs-red: #dc3545;
        --bpjs-white: #ffffff;
        --bpjs-light: #f8f9fa;
        --bpjs-gray: #6c757d;
        --bpjs-dark: #343a40;
    }
    
    /* Reset dan layout dasar */
    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e8f4fd 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: var(--bpjs-dark);
    }
    
    .page-body {
        background: transparent;
    }
    
    .content-viewport {
        background: transparent;
        padding: 20px;
    }
    
    /* Header Dashboard */
    .dashboard-header {
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        color: white;
        box-shadow: 0 10px 30px rgba(0, 102, 204, 0.2);
        position: relative;
        overflow: hidden;
    }
    
    .dashboard-header::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        transform: translate(100px, -100px);
    }
    
    .dashboard-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 150px;
        height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        transform: translate(-50px, 50px);
    }
    
    .dashboard-header h4 {
        color: white;
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
    }
    
    .dashboard-header .text-gray {
        color: rgba(255, 255, 255, 0.9) !important;
        font-size: 1.1rem;
        position: relative;
        z-index: 1;
    }
    
    .header-icon {
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    
    .header-icon i {
        font-size: 40px;
        color: white;
    }
    
    /* Card Styling */
    .grid {
        background: var(--bpjs-white);
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        border: none;
        margin-bottom: 25px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
        overflow: hidden;
    }
    
    .grid:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 102, 204, 0.15);
    }
    
    .grid-body {
        padding: 25px;
    }
    
    /* Statistic Cards */
    .stat-card {
        border-top: 5px solid;
        position: relative;
        min-height: 200px;
        display: flex;
        flex-direction: column;
    }
    
    .stat-card:nth-child(1) {
        border-color: var(--bpjs-green);
    }
    
    .stat-card:nth-child(2) {
        border-color: var(--bpjs-blue);
    }
    
    .stat-card:nth-child(3) {
        border-color: var(--bpjs-teal);
    }
    
    .stat-card:nth-child(4) {
        border-color: var(--bpjs-yellow);
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--bpjs-dark);
        line-height: 1;
        margin-bottom: 10px;
    }
    
    .stat-label {
        font-size: 1.1rem;
        color: var(--bpjs-gray);
        margin-bottom: 20px;
        font-weight: 500;
    }
    
    .stat-icon {
        position: absolute;
        right: 25px;
        bottom: 25px;
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        color: white;
    }
    
    .stat-card:nth-child(1) .stat-icon {
        background: linear-gradient(135deg, var(--bpjs-green) 0%, #20c997 100%);
    }
    
    .stat-card:nth-child(2) .stat-icon {
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
    }
    
    .stat-card:nth-child(3) .stat-icon {
        background: linear-gradient(135deg, var(--bpjs-teal) 0%, #45c7e6 100%);
    }
    
    .stat-card:nth-child(4) .stat-icon {
        background: linear-gradient(135deg, var(--bpjs-yellow) 0%, #ffd76e 100%);
    }
    
    /* Section Titles */
    .section-title {
        color: var(--bpjs-blue);
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid rgba(0, 102, 204, 0.1);
        position: relative;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 60px;
        height: 2px;
        background: var(--bpjs-blue);
    }
    
    /* Info Cards */
    .info-item {
        display: flex;
        align-items: center;
        padding: 20px;
        background: var(--bpjs-light);
        border-radius: 12px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    
    .info-item:hover {
        background: white;
        border-left: 4px solid var(--bpjs-blue);
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .info-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
        font-size: 24px;
        flex-shrink: 0;
    }
    
    .info-content h6 {
        color: var(--bpjs-gray);
        font-size: 0.9rem;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .info-content p {
        color: var(--bpjs-dark);
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0;
    }
    
    /* Quick Actions */
    .quick-action-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        text-align: center;
        height: 100%;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .quick-action-card:hover {
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
        transform: translateY(-5px);
        border-color: var(--bpjs-blue);
        box-shadow: 0 10px 25px rgba(0, 102, 204, 0.2);
    }
    
    .quick-action-card:hover .action-icon {
        background: white;
        color: var(--bpjs-blue);
    }
    
    .quick-action-card:hover .action-title {
        color: white;
    }
    
    .quick-action-card:hover .action-desc {
        color: rgba(255, 255, 255, 0.9);
    }
    
    .action-icon {
        width: 70px;
        height: 70px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        font-size: 32px;
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%);
        color: white;
        transition: all 0.3s ease;
    }
    
    .action-title {
        color: var(--bpjs-dark);
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 8px;
        transition: color 0.3s ease;
    }
    
    .action-desc {
        color: var(--bpjs-gray);
        font-size: 0.9rem;
        transition: color 0.3s ease;
    }
    
    /* Services List */
    .service-item {
        display: flex;
        align-items: center;
        padding: 20px;
        background: var(--bpjs-light);
        border-radius: 12px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        text-decoration: none !important;
        color: var(--bpjs-dark) !important;
    }
    
    .service-item:hover {
        background: white;
        border-left: 4px solid var(--bpjs-blue);
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        color: var(--bpjs-blue) !important;
    }
    
    .service-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
        font-size: 24px;
        background: var(--bpjs-blue);
        color: white;
        flex-shrink: 0;
    }
    
    .service-content h6 {
        font-weight: 700;
        margin-bottom: 5px;
        font-size: 1rem;
    }
    
    .service-content small {
        color: var(--bpjs-gray);
        font-size: 0.85rem;
    }
    
    /* Recent Activity */
    .activity-log {
        padding: 20px;
        background: var(--bpjs-light);
        border-radius: 12px;
        margin-bottom: 15px;
        border-left: 4px solid var(--bpjs-blue);
        position: relative;
    }
    
    .activity-log:last-child {
        margin-bottom: 0;
    }
    
    .activity-log::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 50%;
        transform: translateY(-50%);
        width: 16px;
        height: 16px;
        background: var(--bpjs-blue);
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 0 0 3px var(--bpjs-light);
    }
    
    .log-name {
        color: var(--bpjs-blue);
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .log-details {
        color: var(--bpjs-dark);
        font-weight: 500;
        font-size: 1rem;
        margin-bottom: 5px;
    }
    
    .log-time {
        color: var(--bpjs-gray);
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    /* Footer */
    footer.footer {
        background: white;
        border-top: 2px solid rgba(0, 102, 204, 0.1);
        padding: 25px 0;
        margin-top: 40px;
        border-radius: 15px 15px 0 0;
    }
    
    footer .text-gray {
        color: var(--bpjs-gray) !important;
    }
    
    footer a {
        color: var(--bpjs-blue) !important;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    footer a:hover {
        color: var(--bpjs-light-blue) !important;
        text-decoration: underline;
    }
    
    /* Badge */
    .badge-success {
        background: linear-gradient(135deg, var(--bpjs-green) 0%, #20c997 100%);
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 20px;
            text-align: center;
        }
        
        .header-icon {
            position: relative;
            right: auto;
            top: auto;
            transform: none;
            margin: 20px auto 0;
        }
        
        .stat-value {
            font-size: 2rem;
        }
        
        .section-title {
            font-size: 1.3rem;
        }
        
        .content-viewport {
            padding: 15px;
        }
        
        .grid-body {
            padding: 20px;
        }
    }
    
    @media (max-width: 576px) {
        .dashboard-header h4 {
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
        }
        
        .info-item,
        .service-item,
        .activity-log {
            padding: 15px;
        }
    }
    
    /* Avatar styling (dari kode asli) */
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
    .display-avatar .profile-img.img-lg {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
    }
    </style>
    
  </head>
  <body class="header-fixed">
  
    <!-- partial -->
    <div class="page-body">
      <!-- SIDEBAR - TIDAK DIUBAH -->
      <div class="sidebar">
        <div class="user-profile">
          <div class="display-avatar animated-avatar">
            <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                <img class="profile-img img-lg rounded-circle" 
                     src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                     alt="profile image"
                     onerror="this.style.display='none'; document.getElementById('avatar-default-kelas').style.display='block';">
            <?php endif; ?>
            
            <img id="avatar-default-kelas" 
                 class="profile-img img-lg rounded-circle" 
                 src="<?php echo $has_custom_profile ? '' : $default_avatar; ?>" 
                 alt="profile image"
                 style="<?php echo $has_custom_profile ? 'display: none;' : ''; ?>">
            
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
          
          <li class="nav-category-divider">SYSTEM</li>
          <li>
            <a href="logout.php" class="text-danger">
              <span class="link-title">Logout</span>
              <i class="mdi mdi-logout link-icon"></i>
            </a>
          </li>
        </ul>
        <div class="sidebar-upgrade-banner" style="background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%); color: white;">
          <p class="text-white">BPJS Kesehatan Member</p>
          <a class="btn upgrade-btn" href="pendaftaran.php" style="background: white; color: var(--bpjs-blue);">Register Now</a>
        </div>
      </div>
      <!-- partial -->
      <div class="page-content-wrapper">
        <div class="page-content-wrapper-inner">
          <div class="content-viewport">
            <!-- Header Dashboard -->
            <div class="row">
              <div class="col-12">
                <div class="dashboard-header">
                  <h4>Dashboard BPJS Kesehatan</h4>
                  <p class="text-gray">Selamat datang, <?php echo htmlspecialchars($user['username']); ?>! Sistem BPJS Kesehatan Anda aktif dan siap digunakan.</p>
                  <div class="header-icon">
                    <i class="mdi mdi-hospital-box"></i>
                  </div>
                </div>
              </div>
            </div>
            
           
            
            <div class="row">
              <!-- Informasi Pengguna -->
              <div class="col-lg-8">
                <div class="grid">
                  <div class="grid-body">
                    <h5 class="section-title">Informasi Pengguna</h5>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="info-item">
                          <div class="info-icon" style="background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-light-blue) 100%); color: white;">
                            <i class="mdi mdi-account"></i>
                          </div>
                          <div class="info-content">
                            <h6>Username</h6>
                            <p><?php echo htmlspecialchars($user['username']); ?></p>
                          </div>
                        </div>
                        
                        <div class="info-item">
                          <div class="info-icon" style="background: linear-gradient(135deg, var(--bpjs-green) 0%, #20c997 100%); color: white;">
                            <i class="mdi mdi-email"></i>
                          </div>
                          <div class="info-content">
                            <h6>Email</h6>
                            <p><?php echo htmlspecialchars($user['email'] ?? 'Belum diatur'); ?></p>
                          </div>
                        </div>
                      </div>
                      
                      <div class="col-md-6">
                        <div class="info-item">
                          <div class="info-icon" style="background: linear-gradient(135deg, var(--bpjs-teal) 0%, #45c7e6 100%); color: white;">
                            <i class="mdi mdi-calendar"></i>
                          </div>
                          <div class="info-content">
                            <h6>Bergabung Sejak</h6>
                            <p>
                              <?php 
                              if (!empty($user['created_at'])) {
                                echo date('d M Y', strtotime($user['created_at']));
                              } else {
                                echo 'Hari Ini';
                              }
                              ?>
                            </p>
                          </div>
                        </div>
                        
                        <div class="info-item">
                          <div class="info-icon" style="background: linear-gradient(135deg, var(--bpjs-yellow) 0%, #ffd76e 100%); color: white;">
                            <i class="mdi mdi-shield-check"></i>
                          </div>
                          <div class="info-content">
                            <h6>Status Akun</h6>
                            <p><span class="badge badge-success">Aktif</span></p>
                          </div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Aksi Cepat -->
                    <h5 class="section-title mt-5">Aksi Cepat</h5>
                    <div class="row">
                      <div class="col-md-3 col-sm-6 col-6 mb-4">
                        <div class="quick-action-card">
                          <a href="profile.php" class="text-decoration-none d-block">
                            <div class="action-icon">
                              <i class="mdi mdi-account-edit"></i>
                            </div>
                            <div class="action-title">Profil</div>
                            <div class="action-desc">Perbarui informasi</div>
                          </a>
                        </div>
                      </div>
                      
                      <div class="col-md-3 col-sm-6 col-6 mb-4">
                        <div class="quick-action-card">
                          <a href="profile.php" class="text-decoration-none d-block">
                            <div class="action-icon">
                              <i class="mdi mdi-key-change"></i>
                            </div>
                            <div class="action-title">Password</div>
                            <div class="action-desc">Perbarui password</div>
                          </a>
                        </div>
                      </div>
                      
                      <div class="col-md-3 col-sm-6 col-6 mb-4">
                        <div class="quick-action-card">
                          <a href="#" class="text-decoration-none d-block" data-toggle="modal" data-target="#bpjsInfoModal">
                            <div class="action-icon">
                              <i class="mdi mdi-information-outline"></i>
                            </div>
                            <div class="action-title">Info BPJS</div>
                            <div class="action-desc">Kontak & informasi</div>
                          </a>
                        </div>
                      </div>
                      
                      <div class="col-md-3 col-sm-6 col-6 mb-4">
                        <div class="quick-action-card">
                          <a href="laporan_statistik.php" class="text-decoration-none d-block">
                            <div class="action-icon">
                              <i class="mdi mdi-chart-bar"></i>
                            </div>
                            <div class="action-title">Laporan</div>
                            <div class="action-desc">Lihat Hasil laporan</div>
                          </a>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Aktivitas Terakhir -->
                    <h5 class="section-title mt-5">Aktivitas Terakhir</h5>
                    <div class="activity-log">
                      <div class="log-name">Sistem</div>
                      <div class="log-details">Selamat datang di Sistem Kesehatan BPJS</div>
                      <div class="log-time">Baru saja</div>
                    </div>
                    
                    <div class="activity-log">
                      <div class="log-name">Akun</div>
                      <div class="log-details">Akun Anda telah terverifikasi</div>
                      <div class="log-time">Hari ini, <?php echo date('H:i'); ?></div>
                    </div>
                    
                    <div class="activity-log">
                      <div class="log-name">Login</div>
                      <div class="log-details">Login berhasil dari perangkat Anda</div>
                      <div class="log-time">Hari ini, <?php echo date('H:i', strtotime('-1 hour')); ?></div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Layanan BPJS -->
              <div class="col-lg-4">
                <div class="grid">
                  <div class="grid-body">
                    <h5 class="section-title">Layanan BPJS</h5>
                    
                    <a href="pendaftaran.php" class="service-item">
                      <div class="service-icon">
                        <i class="mdi mdi-account-plus"></i>
                      </div>
                      <div class="service-content">
                        <h6>Pendaftaran</h6>
                        <small>Daftar keanggotaan BPJS</small>
                      </div>
                    </a>
                    
                    <a href="faskes.php" class="service-item">
                      <div class="service-icon">
                        <i class="mdi mdi-hospital-building"></i>
                      </div>
                      <div class="service-content">
                        <h6>Fasilitas Kesehatan</h6>
                        <small>Pilih faskes terdekat</small>
                      </div>
                    </a>
                    
                    <a href="klaim.php" class="service-item">
                      <div class="service-icon">
                        <i class="mdi mdi-file-document"></i>
                      </div>
                      <div class="service-content">
                        <h6>Proses Klaim</h6>
                        <small>Ajukan klaim kesehatan</small>
                      </div>
                    </a>
                    
                    <a href="pembayaran.php" class="service-item">
                      <div class="service-icon">
                        <i class="mdi mdi-cash"></i>
                      </div>
                      <div class="service-content">
                        <h6>Pembayaran</h6>
                        <small>Lakukan pembayaran</small>
                      </div>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Footer -->
        <footer class="footer">
          <div class="row">
            <div class="col-sm-6 text-center text-sm-right order-sm-1">
              <ul class="text-gray">
                <li><a href="#">Syarat Penggunaan</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
              </ul>
            </div>
            <div class="col-sm-6 text-center text-sm-left mt-3 mt-sm-0">
              <small class="text-muted d-block">Sistem BPJS Kesehatan &copy; <?php echo date('Y'); ?></small>
              <small class="text-gray mt-2">Area Member</small>
            </div>
          </div>
        </footer>
      </div>
    </div>
    
    <!-- BPJS Info Modal -->
    <div class="modal fade" id="bpjsInfoModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Informasi BPJS Kesehatan</h5>
            <button type="button" class="close" data-dismiss="modal">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="info-item">
              <div class="info-icon" style="background: var(--bpjs-blue);">
                <i class="mdi mdi-phone"></i>
              </div>
              <div class="info-content">
                <h6>Call Center</h6>
                <p>165</p>
              </div>
            </div>
            
            <div class="info-item">
              <div class="info-icon" style="background: var(--bpjs-teal);">
                <i class="mdi mdi-clock"></i>
              </div>
              <div class="info-content">
                <h6>Jam Layanan</h6>
                <p>24/7</p>
              </div>
            </div>
            
            <div class="info-item">
              <div class="info-icon" style="background: var(--bpjs-green);">
                <i class="mdi mdi-web"></i>
              </div>
              <div class="info-content">
                <h6>Website</h6>
                <p>www.bpjs-kesehatan.go.id</p>
              </div>
            </div>
            
            <div class="info-item">
              <div class="info-icon" style="background: var(--bpjs-yellow);">
                <i class="mdi mdi-email"></i>
              </div>
              <div class="info-content">
                <h6>Email</h6>
                <p>callcenter@bpjs-kesehatan.go.id</p>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
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
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
      // Script untuk auto-refresh foto profil
      document.addEventListener('DOMContentLoaded', function() {
        if (document.referrer.includes('profile.php')) {
          const profileImages = document.querySelectorAll('.profile-img');
          profileImages.forEach(img => {
            const currentSrc = img.src;
            const newSrc = currentSrc.split('?')[0] + '?t=' + new Date().getTime();
            img.src = newSrc;
          });
        }
      });
      
      // Session timeout warning
      let idleTime = 0;
      const idleInterval = setInterval(() => {
        idleTime++;
        if (idleTime > 25) {
          alert("Sesi Anda akan berakhir dalam 5 menit karena tidak ada aktivitas.");
        }
        if (idleTime > 30) {
          window.location.href = "logout.php?reason=timeout";
        }
      }, 60000);
      
      // Reset idle time on user activity
      document.addEventListener('mousemove', () => idleTime = 0);
      document.addEventListener('keypress', () => idleTime = 0);
      document.addEventListener('click', () => idleTime = 0);
    </script>
  </body>
</html>