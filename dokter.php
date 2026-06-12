<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function untuk sanitize input
function sanitize_input($data) {
    if (!is_string($data)) {
        return $data;
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
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

if (!$user) {
    die("Error: User data not found.");
}

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

// PROSES HAPUS DOKTER (tetap di file utama)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Cek apakah dokter memiliki data terkait (misalnya di tabel kunjungan)
    $check_query = "SELECT COUNT(*) as total FROM kunjungan WHERE dokter_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if ($check_data['total'] > 0) {
        $_SESSION['message'] = "❌ Tidak dapat menghapus dokter ini karena memiliki data kunjungan terkait!";
        $_SESSION['message_type'] = "danger";
        header("Location: dokter.php");
        exit();
    }
    
    $query = "DELETE FROM dokter WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt === false) {
        $_SESSION['message'] = "❌ Error preparing statement: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
        header("Location: dokter.php");
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "✅ Data dokter berhasil dihapus!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "❌ Error: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    
    mysqli_stmt_close($stmt);
    header("Location: dokter.php");
    exit();
}

// Query untuk data dokter
$query_dokter = "SELECT d.*, s.nama_spesialisasi 
                 FROM dokter d 
                 LEFT JOIN spesialisasi_dokter s ON d.spesialisasi_id = s.id 
                 ORDER BY d.nama_dokter ASC";
$result_dokter = mysqli_query($conn, $query_dokter);

if (!$result_dokter) {
    die("Error mengambil data dokter: " . mysqli_error($conn));
}

// Hitung total dokter
$total_dokter = mysqli_num_rows($result_dokter);

// Hitung dokter aktif
$query_aktif = "SELECT COUNT(*) as total FROM dokter WHERE status = 'aktif'";
$result_aktif = mysqli_query($conn, $query_aktif);
if ($result_aktif) {
    $aktif_data = mysqli_fetch_assoc($result_aktif);
    $dokter_aktif = $aktif_data['total'] ?? 0;
} else {
    $dokter_aktif = 0;
}

// Ambil data spesialisasi untuk dropdown
$query_spesialis = "SELECT * FROM spesialisasi_dokter ORDER BY nama_spesialisasi";
$result_spesialis = mysqli_query($conn, $query_spesialis);
$spesialis_data = [];
while ($row = mysqli_fetch_assoc($result_spesialis)) {
    $spesialis_data[] = $row;
}

// Update last activity
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Data Dokter - BPJS KESEHATAN</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Load CSS dari dashboard.php -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.css">
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    
    <style>
        :root {
            --bpjs-primary-dark: #003366;
            --bpjs-primary: #0066cc;
            --bpjs-blue: #0077c8;
            --bpjs-green: #43b02a;
            --bpjs-dark: #003087;
            --bpjs-light: #cfd3fdff;
        }
        
        .bg-bpjs-blue {
            background-color: var(--bpjs-blue) !important;
        }
        
        .bg-bpjs-light {
            background-color: var(--bpjs-light) !important;
        }
        
        .text-bpjs-blue {
            color: var(--bpjs-blue) !important;
        }
        
        .text-bpjs-green {
            color: var(--bpjs-green) !important;
        }
        
        .btn-bpjs {
            background-color: var(--bpjs-blue);
            border-color: var(--bpjs-blue);
            color: white;
            font-weight: 600;
            padding: 10px 20px;
        }
        
        /* OVERRIDE TEMPLATE STYLES */
        
        /* FOTO PROFIL SIDEBAR - SAMA DENGAN DASHBOARD */
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
        
        /* REST OF YOUR EXISTING STYLES */
        .btn-bpjs:hover {
            background-color: var(--bpjs-dark);
            border-color: var(--bpjs-dark);
            color: white;
            transform: translateY(-2px);
            transition: all 0.3s;
        }
        
        .btn-bpjs-outline {
            border: 2px solid var(--bpjs-blue);
            color: var(--bpjs-blue);
            background: transparent;
            font-weight: 600;
        }
        
        .btn-bpjs-outline:hover {
            background-color: var(--bpjs-blue);
            color: white;
        }
        
        .status-aktif {
            color: #43b02a;
            font-weight: bold;
            background-color: rgba(67, 176, 42, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .status-nonaktif {
            color: #dc3545;
            font-weight: bold;
            background-color: rgba(220, 53, 69, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .card-header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            color: white;
            padding: 20px;
            border-bottom: none;
        }
        
        .card-bpjs {
            border: 1px solid rgba(0, 119, 200, 0.1);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 119, 200, 0.08);
            transition: all 0.3s;
        }
        
        .card-bpjs:hover {
            box-shadow: 0 6px 16px rgba(0, 119, 200, 0.12);
        }
        
        .table th {
            background-color: #0f75dbff;
            border-top: none;
            font-weight: 600;
            color: var(--bpjs-dark);
            border-bottom: 2px solid var(--bpjs-blue);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 119, 200, 0.05);
            transform: scale(1.002);
            transition: all 0.2s;
        }
        
        .avatar-doctor {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 20px;
            margin-right: 12px;
        }
        
        .navbar-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            box-shadow: 0 4px 12px rgba(0, 55, 135, 0.15);
        }
        
        .sidebar-card {
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            border: 1px solid rgba(0, 119, 200, 0.1);
        }
        
        .modal-header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            color: white;
            padding: 20px;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
        }
        
        .badge-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            color: white;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .badge-specialist {
            background-color: rgba(67, 176, 42, 0.1);
            color: var(--bpjs-green);
            border: 1px solid rgba(67, 176, 42, 0.3);
            font-weight: 500;
        }
        
        .action-buttons .btn {
            padding: 0.35rem 0.65rem;
            font-size: 0.9rem;
            border-radius: 8px;
            margin: 0 3px;
        }
        
        /* Loading spinner */
        .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }
        
        /* Custom CSS untuk perbaikan layout */
        body {
            background-color: #187ce0ff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            padding: 25px;
            margin-top: 20px;
        }
        
        .content-area {
            padding: 25px;
            background-color: #513ff5ff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .page-title {
            font-weight: 700;
            color: var(--bpjs-dark);
            margin-bottom: 5px;
            font-size: 1.8rem;
        }
        
        .page-subtitle {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 25px;
        }
        
        .stat-card {
            padding: 20px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Untuk tombol aksi di table */
        .table-actions {
            white-space: nowrap;
        }
        
        /* Hover effect untuk rows */
        .doctor-row {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .doctor-row:hover {
            border-left: 4px solid var(--bpjs-blue);
        }
        
        /* Search box */
        .search-box {
            border: 2px solid rgba(0, 119, 200, 0.2);
            border-radius: 10px;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        
        .search-box:focus {
            border-color: var(--bpjs-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 200, 0.1);
        }
        
        /* Untuk responsive */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .action-buttons .btn {
                margin-bottom: 5px;
            }
            
            .display-avatar .profile-img.img-lg {
                width: 80px;
                height: 80px;
            }
            
            .avatar-edit-btn {
                width: 28px;
                height: 28px;
            }
        }
        
        /* Animasi untuk tombol */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .btn-animated:hover {
            animation: pulse 0.5s ease-in-out;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--bpjs-blue);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--bpjs-dark);
        }
        
        /* Quick Action Buttons */
        .quick-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            margin: 0 3px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            text-decoration: none;
        }
        
        .btn-detail {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border: 1px solid rgba(13, 110, 253, 0.2);
        }
        
        .btn-detail:hover {
            background-color: #0d6efd;
            color: white;
        }
        
        .btn-edit {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.2);
        }
        
        .btn-edit:hover {
            background-color: #198754;
            color: white;
        }
        
        .btn-delete {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .btn-delete:hover {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body class="header-fixed">

<div class="page-body">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="user-profile">
            <div class="display-avatar animated-avatar">
                <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                    <!-- Jika ada foto profil yang diupload -->
                    <img class="profile-img img-lg rounded-circle" 
                         src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                         alt="profile image"
                         onerror="this.style.display='none'; document.getElementById('avatar-default-faskes').style.display='block';">
                <?php endif; ?>
                
                <!-- Foto default (akan ditampilkan jika tidak ada custom photo) -->
                <img id="avatar-default-faskes" 
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
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'transfer_faskes.php' ? 'active' : ''; ?>">
                        <a href="transfer_faskes.php">Transfer Faskes</a>
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
        
        <div class="sidebar-upgrade-banner">
            <p class="text-gray">BPJS Kesehatan Member</p>
            <a class="btn upgrade-btn" href="pendaftaran.php">Register Now</a>
        </div>
    </div>

    <div class="page-content-wrapper">
        <div class="page-content-wrapper-inner">
            <div class="content-viewport">
                <!-- Content Area Dokter -->
                <div class="page-content-wrapper">
                    <div class="page-content-wrapper-inner">
                        <div class="content-viewport">
                            <!-- Header dengan Tema BPJS -->
                            <div class="d-flex justify-content-between align-items-center mb-7">
                                <div>
                                    <h1 class="page-title">
                                        <i class="fas fa-user-md me-6 text-bpjs-blue"></i>Data Dokter BPJS Kesehatan
                                    </h1>
                                </div>
                                <div>
                                    <!-- Link ke halaman tambah dokter terpisah -->
                                    <a href="tambah_dokter.php" class="btn btn-bpjs btn-animated shadow-sm">
                                        <i class="fas fa-plus me-2"></i> Tambah Dokter Baru
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Statistik Cards -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card card-bpjs stat-card bg-white">
                                        <div class="card-body text-center">
                                            <div class="stat-icon bg-bpjs-light text-bpjs-blue mx-auto">
                                                <i class="fas fa-user-md"></i>
                                            </div>
                                            <div class="stat-number text-bpjs-blue"><?php echo $total_dokter; ?></div>
                                            <div class="stat-label">Total Dokter</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card card-bpjs stat-card bg-white">
                                        <div class="card-body text-center">
                                            <div class="stat-icon" style="background-color: rgba(67, 176, 42, 0.1); color: var(--bpjs-green);">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="stat-number text-success"><?php echo $dokter_aktif; ?></div>
                                            <div class="stat-label">Dokter Aktif</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card card-bpjs stat-card bg-white">
                                        <div class="card-body text-center">
                                            <div class="stat-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                                <i class="fas fa-times-circle"></i>
                                            </div>
                                            <div class="stat-number text-danger"><?php echo $total_dokter - $dokter_aktif; ?></div>
                                            <div class="stat-label">Dokter Non-Aktif</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card card-bpjs stat-card bg-white">
                                        <div class="card-body text-center">
                                            <div class="stat-icon" style="background-color: rgba(108, 117, 125, 0.1); color: #6c757d;">
                                                <i class="fas fa-stethoscope"></i>
                                            </div>
                                            <div class="stat-number" style="color: #6c757d;"><?php echo count($spesialis_data); ?></div>
                                            <div class="stat-label">Spesialisasi</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Alert Messages -->
                            <?php if (isset($_SESSION['message'])): ?>
                                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show shadow-sm border-0" role="alert">
                                    <div class="d-flex align-items-center">
                                        <?php if ($_SESSION['message_type'] == 'success'): ?>
                                            <i class="fas fa-check-circle me-3" style="font-size: 1.5rem;"></i>
                                        <?php elseif ($_SESSION['message_type'] == 'danger'): ?>
                                            <i class="fas fa-exclamation-circle me-3" style="font-size: 1.5rem;"></i>
                                        <?php elseif ($_SESSION['message_type'] == 'warning'): ?>
                                            <i class="fas fa-exclamation-triangle me-3" style="font-size: 1.5rem;"></i>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle me-3" style="font-size: 1.5rem;"></i>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="alert-heading mb-1">
                                                <?php echo $_SESSION['message_type'] == 'success' ? 'Success!' : 
                                                       ($_SESSION['message_type'] == 'danger' ? 'Error!' : 
                                                       ($_SESSION['message_type'] == 'warning' ? 'Warning!' : 'Info!')); ?>
                                            </h6>
                                            <span class="mb-0"><?php echo $_SESSION['message']; ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php 
                                unset($_SESSION['message']);
                                unset($_SESSION['message_type']);
                                ?>
                            <?php endif; ?>
                            
                            <!-- Search and Filter -->
                            <div class="card card-bpjs mb-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <div class="input-group">
                                                <span class="input-group-text bg-bpjs-light border-0">
                                                    <i class="fas fa-search text-bpjs-blue"></i>
                                                </span>
                                                <input type="text" class="form-control search-box border-0 bg-bpjs-light" id="searchInput" placeholder="Cari dokter berdasarkan nama, spesialisasi, atau kode...">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-md-end">
                                                <div class="me-2">
                                                    <select class="form-select search-box" id="filterStatus">
                                                        <option value="">Semua Status</option>
                                                        <option value="aktif">Aktif</option>
                                                        <option value="tidak aktif">Tidak Aktif</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <select class="form-select search-box" id="filterSpesialis">
                                                        <option value="">Semua Spesialisasi</option>
                                                        <?php foreach ($spesialis_data as $spesialis): ?>
                                                            <option value="<?php echo $spesialis['id']; ?>">
                                                                <?php echo htmlspecialchars($spesialis['nama_spesialisasi']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data Table -->
                            <div class="card card-bpjs">
                                <div class="card-header-bpjs d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-table me-2 fs-5"></i>
                                        <h5 class="mb-0">Daftar Dokter BPJS Kesehatan</h5>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <button class="btn btn-sm btn-light me-2" onclick="printTable()">
                                            <i class="fas fa-print me-1"></i> Print
                                        </button>
                                        <button class="btn btn-sm btn-light" onclick="exportToExcel()">
                                            <i class="fas fa-file-excel me-1"></i> Export
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($total_dokter > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" id="doctorTable">
                                            <thead>
                                                <tr>
                                                    <th width="50" class="text-center">No</th>
                                                    <th width="100">Kode</th>
                                                    <th>Nama Dokter</th>
                                                    <th>Spesialisasi</th>
                                                    <th>Jenis Kelamin</th>
                                                    <th>Kontak</th>
                                                    <th>Status</th>
                                                    <th width="150" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $no = 1;
                                                mysqli_data_seek($result_dokter, 0);
                                                while ($row = mysqli_fetch_assoc($result_dokter)):
                                                    // Get spesialisasi name
                                                    $spesialis_name = $row['nama_spesialisasi'] ?? '-';
                                                ?>
                                                <tr class="doctor-row" data-id="<?php echo $row['id']; ?>" 
                                                    data-name="<?php echo strtolower($row['nama_dokter']); ?>"
                                                    data-code="<?php echo strtolower($row['kode_dokter']); ?>"
                                                    data-specialist="<?php echo strtolower($spesialis_name); ?>"
                                                    data-status="<?php echo $row['status']; ?>"
                                                    data-specialist-id="<?php echo $row['spesialisasi_id']; ?>">
                                                    <td class="text-center"><?php echo $no++; ?></td>
                                                    <td>
                                                        <span class="badge-bpjs"><?php echo htmlspecialchars($row['kode_dokter']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-doctor">
                                                                <i class="fas fa-user-md"></i>
                                                            </div>
                                                            <div>
                                                                <strong class="d-block"><?php echo htmlspecialchars($row['nama_dokter']); ?></strong>
                                                                <small class="text-muted"><?php echo htmlspecialchars($row['email'] ?? '-'); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($spesialis_name != '-'): ?>
                                                            <span class="badge badge-specialist"><?php echo htmlspecialchars($spesialis_name); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['jenis_kelamin'] == 'L'): ?>
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-male me-1"></i> Laki-laki
                                                            </span>
                                                        <?php elseif ($row['jenis_kelamin'] == 'P'): ?>
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="fas fa-female me-1"></i> Perempuan
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <small class="d-block">
                                                                <i class="fas fa-phone me-1 text-muted"></i>
                                                                <?php echo htmlspecialchars($row['no_telepon'] ?? '-'); ?>
                                                            </small>
                                                            <?php if ($row['email']): ?>
                                                            <small class="d-block">
                                                                <i class="fas fa-envelope me-1 text-muted"></i>
                                                                <?php echo htmlspecialchars($row['email']); ?>
                                                            </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['status'] == 'aktif'): ?>
                                                            <span class="status-aktif">
                                                                <i class="fas fa-check-circle me-1"></i>Aktif
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-nonaktif">
                                                                <i class="fas fa-times-circle me-1"></i>Tidak Aktif
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center table-actions">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <!-- Tombol Detail (mengarah ke detail_dokter.php) -->
                                                            <a href="detail_dokter.php?id=<?php echo $row['id']; ?>" 
                                                               class="btn btn-outline-info btn-animated quick-action-btn btn-detail"
                                                               title="Detail">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            
                                                            <!-- Tombol Edit (mengarah ke edit_dokter.php) -->
                                                            <a href="edit_dokter.php?id=<?php echo $row['id']; ?>" 
                                                               class="btn btn-outline-primary btn-animated quick-action-btn btn-edit"
                                                               title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            
                                                            <!-- Tombol Hapus (tetap di file utama dengan modal konfirmasi) -->
                                                            <a href="?delete=<?php echo $row['id']; ?>" 
                                                               class="btn btn-outline-danger btn-animated quick-action-btn btn-delete"
                                                               title="Hapus"
                                                               onclick="return confirmDelete('<?php echo addslashes($row['nama_dokter']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <div class="text-muted mb-4">
                                            <i class="fas fa-user-md fa-4x mb-3" style="color: #e9ecef;"></i>
                                            <h4>Belum ada data dokter</h4>
                                            <p>Mulai dengan menambahkan data dokter pertama Anda</p>
                                        </div>
                                        <a href="tambah_dokter.php" class="btn btn-bpjs btn-lg shadow-sm">
                                            <i class="fas fa-plus me-2"></i> Tambah Dokter Pertama
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">Menampilkan <?php echo $total_dokter; ?> data dokter</small>
                                        </div>
                                        <div>
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination pagination-sm mb-0">
                                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                                </ul>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <footer class="footer mt-4">
                        <div class="row">
                            <div class="col-sm-6 text-center text-sm-right order-sm-1">
                                <ul class="text-gray list-inline mb-0">
                                </ul>
                            </div>
                            <div class="col-sm-6 text-center text-sm-left mt-3 mt-sm-0">
                                <small class="text-muted d-block">BPJS Kesehatan System &copy; <?php echo date('Y'); ?> v1.0</small>
                                <small class="text-gray mt-2">Member Area - Data Management</small>
                            </div>
                        </div>
                    </footer>
                </div>
                <!-- page content ends -->
            </div>
        </div>
        <!--page body ends -->
        
        <!-- JavaScript -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Load JS dari template dashboard -->
        <script src="../assets/vendors/js/core.js"></script>
        <script src="../assets/js/template.js"></script>
        
        <script>
            $(document).ready(function() {
                // Auto-hide alert setelah 5 detik
                setTimeout(function() {
                    $('.alert').alert('close');
                }, 5000);
                
                // Search functionality
                $('#searchInput').on('keyup', function() {
                    const searchText = $(this).val().toLowerCase();
                    const filterStatus = $('#filterStatus').val();
                    const filterSpesialis = $('#filterSpesialis').val();
                    
                    $('.doctor-row').each(function() {
                        const name = $(this).data('name');
                        const code = $(this).data('code');
                        const specialist = $(this).data('specialist');
                        const status = $(this).data('status');
                        const specialistId = $(this).data('specialist-id').toString();
                        
                        const matchesSearch = searchText === '' || 
                                             name.includes(searchText) || 
                                             code.includes(searchText) || 
                                             specialist.includes(searchText);
                        
                        const matchesStatus = filterStatus === '' || status === filterStatus;
                        const matchesSpecialist = filterSpesialis === '' || specialistId === filterSpesialis;
                        
                        if (matchesSearch && matchesStatus && matchesSpecialist) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });
                
                // Filter functionality
                $('#filterStatus, #filterSpesialis').on('change', function() {
                    $('#searchInput').trigger('keyup');
                });
                
                // Export to Excel function
                window.exportToExcel = function() {
                    let table = document.getElementById('doctorTable');
                    let html = table.outerHTML;
                    let blob = new Blob([html], { type: 'application/vnd.ms-excel' });
                    let url = URL.createObjectURL(blob);
                    let a = document.createElement('a');
                    a.href = url;
                    a.download = 'Data_Dokter_BPJS_' + new Date().toISOString().split('T')[0] + '.xls';
                    a.click();
                    URL.revokeObjectURL(url);
                };
                
                // Print table
                window.printTable = function() {
                    let printWindow = window.open('', '', 'height=600,width=800');
                    printWindow.document.write('<html><head><title>Data Dokter BPJS Kesehatan</title>');
                    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">');
                    printWindow.document.write('<style>@media print { body { font-size: 12pt; } }</style>');
                    printWindow.document.write('</head><body>');
                    printWindow.document.write('<h2 class="text-center mb-4">Data Dokter BPJS Kesehatan</h2>');
                    printWindow.document.write('<div class="table-responsive">');
                    printWindow.document.write(document.getElementById('doctorTable').outerHTML);
                    printWindow.document.write('</div>');
                    printWindow.document.write('<div class="text-center mt-4">');
                    printWindow.document.write('<small>Dicetak pada: ' + new Date().toLocaleString() + '</small>');
                    printWindow.document.write('</div>');
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                };
                
                // Auto refresh foto profil
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
            });
            
            // Fungsi konfirmasi hapus dengan sweet alert style
            function confirmDelete(namaDokter) {
                return confirm(`Apakah Anda yakin ingin menghapus data dokter "${namaDokter}"?\n\nTindakan ini tidak dapat dibatalkan!`);
            }
            
            // Quick detail modal (optional - jika ingin tetap ada modal cepat)
            function quickDetail(dokterId) {
                // Redirect ke halaman detail terpisah
                window.location.href = `detail_dokter.php?id=${dokterId}`;
            }
            
            // Quick edit modal (optional - jika ingin tetap ada modal cepat)
            function quickEdit(dokterId) {
                // Redirect ke halaman edit terpisah
                window.location.href = `edit_dokter.php?id=${dokterId}`;
            }
        </script>
    </div>
</div>
</body>
</html>
<?php
// Tutup koneksi
if (isset($conn) && is_object($conn)) {
    mysqli_close($conn);
}
?>