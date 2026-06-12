<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Default avatar
$default_avatar = '../assets/images/faces/default-avatar.png';
$has_custom_profile = false;
$profile_pic = '';

// Ambil data user
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

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

// Fungsi untuk mengambil data obat dari database
function getObatData($conn) {
    $sql = "SELECT * FROM obat ORDER BY kode_obat ASC";
    $result = mysqli_query($conn, $sql);
    $obat = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $obat[] = $row;
        }
    }
    return $obat;
}

// Hapus obat
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql_delete = "DELETE FROM obat WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        $_SESSION['success'] = "Data obat berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus data obat!";
    }
    mysqli_stmt_close($stmt_delete);
    header("Location: obat.php");
    exit();
}

// Ambil data obat
$data_obat = getObatData($conn);

// Hitung statistik
$total_obat = count($data_obat);
$obat_aktif = 0;
$obat_expired = 0;
$obat_stok_rendah = 0;
$obat_akan_expired = 0;

$today = time();
$thirty_days_later = strtotime('+30 days');

foreach ($data_obat as $obat) {
    if ($obat['status'] == 'Aktif') {
        $obat_aktif++;
    }
    
    // Cek expired
    if (!empty($obat['tanggal_expired'])) {
        $expired_date = strtotime($obat['tanggal_expired']);
        
        if ($expired_date < $today) {
            $obat_expired++;
        } elseif ($expired_date <= $thirty_days_later) {
            $obat_akan_expired++;
        }
    }
    
    // Cek stok rendah
    if ($obat['stok'] > 0 && $obat['stok'] < 10) {
        $obat_stok_rendah++;
    }
}

// Update last activity
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Data Master Obat - BPJS Kesehatan</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.css">
    <!-- endinject -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <!-- endinject -->
    <!-- Layout style -->
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS untuk obat -->
    <style>
        /* Tema BPJS */
        :root {
            --bpjs-primary: #0066cc;
            --bpjs-primary-dark: #003366;
            --bpjs-secondary: #00a8ff;
            --bpjs-success: #28a745;
            --bpjs-warning: #ffc107;
            --bpjs-danger: #dc3545;
            --bpjs-info: #17a2b8;
            --bpjs-light: #f8f9fa;
            --bpjs-dark: #343a40;
        }
        
        /* FOTO PROFIL SIDEBAR - SAMA DENGAN FASKES */
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
        
        /* PAGE HEADER - SAMA SEPERTI FASKES */
        .bpjs-page-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(228, 240, 252, 0.1);
            border-left: 4px solid var(--bpjs-primary);
            border-bottom: 1px solid #e6f2ff;
            margin-bottom: 20px;
        }
        
        .bpjs-page-header h2 {
            color: #1a365d;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
            line-height: 1.2;
        }
        
        .bpjs-page-header h2 .text-primary {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .bpjs-page-header .page-subtitle {
            color: #5a6c7d;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        
        /* STATISTICS CARDS - SAMA SEPERTI FASKES */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .bpjs-stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 115, 230, 0.1);
            transition: all 0.3s;
            background: white;
            overflow: hidden;
            position: relative;
            height: auto;
            min-height: 120px;
        }
        
        .bpjs-stat-card .grid-body {
            padding: 25px;
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .bpjs-stat-card .d-flex {
            height: 100%;
        }
        
        .bpjs-stat-card .d-flex > div:first-child {
            flex: 1;
        }
        
        .bpjs-stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }
        
        /* Tabel Obat Styling */
        .bpjs-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .bpjs-table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            background: #f8fafc;
        }
        
        .bpjs-table-header h5 {
            margin: 0;
            color: var(--bpjs-primary);
            font-weight: 600;
        }
        
        /* TABLE STYLES */
        .bpjs-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
        }
        
        .bpjs-table thead th {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
            color: white;
            border: none;
            padding: 16px 20px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            vertical-align: middle;
            white-space: nowrap;
        }
        
        .bpjs-table tbody td {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 14px;
        }
        
        .bpjs-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .bpjs-table tbody tr:hover {
            background-color: rgba(0, 115, 230, 0.05);
        }
        
        /* BADGE STYLES untuk Obat */
        .bpjs-stok-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        
        .bpjs-stok-tinggi { 
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); 
            color: #155724;
            border: 1px solid #b1dfbb;
        }
        
        .bpjs-stok-rendah { 
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .bpjs-stok-habis { 
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); 
            color: #721c24;
            border: 1px solid #f1b0b7;
        }
        
        .bpjs-expired-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 70px;
        }
        
        .bpjs-expired-ok { 
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); 
            color: #155724;
            border: 1px solid #b1dfbb;
        }
        
        .bpjs-expired-soon { 
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .bpjs-expired { 
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); 
            color: #721c24;
            border: 1px solid #f1b0b7;
        }
        
        /* Status badge */
        .bpjs-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .bpjs-status-aktif {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #b1dfbb;
        }
        
        .bpjs-status-nonaktif {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f1b0b7;
        }
        
        /* ACTION BUTTONS - SAMA DENGAN FASKES */
        .bpjs-action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            min-width: 140px;
        }
        
        .bpjs-action-buttons .btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid transparent;
            transition: all 0.3s;
            flex-shrink: 0;
            opacity: 1;
            visibility: visible;
        }
        
        .bpjs-action-buttons .btn i {
            font-size: 16px;
            line-height: 1;
            margin: 0;
            display: block;
        }
        
        .bpjs-action-buttons .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
        }
        
        .bpjs-action-buttons .btn-info:hover {
            background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
            color: white;
        }
        
        .bpjs-action-buttons .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
            border: none;
        }
        
        .bpjs-action-buttons .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
            color: white;
        }
        
        .bpjs-action-buttons .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
        }
        
        .bpjs-action-buttons .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
            color: white;
        }
        
        /* BUTTON BPJS */
        .btn-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0, 115, 230, 0.2);
            white-space: nowrap;
        }
        
        .btn-bpjs:hover {
            background: linear-gradient(135deg, var(--bpjs-primary-dark) 0%, #0097e6 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 115, 230, 0.3);
        }
        
        /* FILTER SECTION */
        .bpjs-filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 115, 230, 0.1);
            border: 1px solid #eaeaea;
        }
        
        .bpjs-search-box {
            position: relative;
        }
        
        .bpjs-search-box .form-control {
            padding-left: 50px;
            border-radius: 10px;
            border: 1px solid #ddd;
            height: 50px;
            font-size: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .bpjs-search-box .form-control:focus {
            border-color: var(--bpjs-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 115, 230, 0.25);
        }
        
        .bpjs-search-box .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bpjs-primary);
            z-index: 2;
            font-size: 20px;
        }
        
        /* EMPTY STATE */
        .bpjs-empty-state {
            padding: 60px 20px;
            text-align: center;
            background: #f8fafc;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .bpjs-empty-state-icon {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .bpjs-empty-state h5 {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .bpjs-empty-state p {
            color: #718096;
            max-width: 400px;
            margin: 0 auto 20px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .bpjs-stat-card {
                min-height: 100px;
            }
            
            .bpjs-stat-card .grid-body {
                padding: 20px;
            }
            
            .bpjs-table-container {
                padding: 0;
            }
            
            .bpjs-table thead {
                display: none;
            }
            
            .bpjs-table tbody tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 15px;
                background: white;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            }
            
            .bpjs-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 10px;
                border-bottom: 1px solid #f0f0f0;
                text-align: right;
                white-space: normal;
            }
            
            .bpjs-table tbody td::before {
                content: attr(data-label);
                float: left;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 12px;
                color: var(--bpjs-primary);
                margin-right: 10px;
            }
            
            .bpjs-table tbody td:last-child {
                border-bottom: none;
                justify-content: center;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
                margin-top: 5px;
            }
            
            .bpjs-table tbody td:last-child::before {
                display: none;
            }
            
            .bpjs-action-buttons {
                justify-content: center;
                width: 100%;
            }
            
            .page-body {
                flex-direction: column;
            }
            
            .content-viewport {
                padding: 15px;
            }
            
            .bpjs-page-header {
                padding: 15px;
            }
            
            .bpjs-page-header h2 {
                font-size: 1.5rem;
            }
            
            .bpjs-filter-section {
                padding: 15px;
            }
            
            .bpjs-table-header {
                padding: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .bpjs-action-buttons .btn {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .display-avatar .profile-img.img-lg {
                width: 80px;
                height: 80px;
            }
            
            .avatar-edit-btn {
                width: 28px;
                height: 28px;
            }
            
            .bpjs-stat-icon {
                width: 50px;
                height: 50px;
                font-size: 22px;
            }
        }
    </style>
</head>
<body class="header-fixed">
    <div class="page-body">
        <!-- SIDEBAR - SAMA PERSIS DENGAN FASKES.PHP -->
        <div class="sidebar">
            <div class="user-profile">
                <div class="display-avatar animated-avatar">
                    <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                        <!-- Jika ada foto profil yang diupload -->
                        <img class="profile-img img-lg rounded-circle" 
                             src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                             alt="profile image"
                             onerror="this.style.display='none'; document.getElementById('avatar-default-obat').style.display='block';">
                    <?php endif; ?>
                    
                    <!-- Foto default (akan ditampilkan jika tidak ada custom photo) -->
                    <img id="avatar-default-obat" 
                         class="profile-img img-lg rounded-circle" 
                         src="<?php echo !$has_custom_profile || empty($profile_pic) ? $default_avatar : ''; ?>" 
                         alt="profile image"
                         style="<?php echo $has_custom_profile && !empty($profile_pic) ? 'display: none;' : ''; ?>">
                    
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
                            <a href="laporan_audit.php">Laporan Audit</a>
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

        <div class="page-content-wrapper">
            <div class="page-content-wrapper-inner">
                <div class="content-viewport">
                    
                    <!-- Notifikasi -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-check-circle mr-2"></i>
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-alert-circle mr-2"></i>
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Page Header - SAMA SEPERTI FASKES -->
                    <div class="bpjs-page-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2><i class="mdi mdi-pill text-primary me-2"></i>Data Obat BPJS</h2>
                                <p class="page-subtitle mb-0">Manajemen data obat BPJS Kesehatan</p>
                            </div>
                            <div>
                                <!-- UBAH INI: ganti button dengan link ke tambah_obat.php -->
                                <a href="tambah_obat.php" class="btn btn-bpjs">
                                    <i class="mdi mdi-plus me-1"></i> Tambah Obat
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Statistik Cards - SAMA SEPERTI FASKES -->
                    <div class="stats-grid">
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Total Obat</p>
                                        <h3 class="mb-0"><?php echo number_format($total_obat); ?></h3>
                                        <small class="text-muted">Semua jenis obat</small>
                                    </div>
                                    <div class="bpjs-stat-icon" style="background: linear-gradient(135deg, #0066cc 0%, #00a8ff 100%);">
                                        <i class="mdi mdi-pill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Obat Aktif</p>
                                        <h3 class="mb-0"><?php echo number_format($obat_aktif); ?></h3>
                                        <small class="text-muted">Siap digunakan</small>
                                    </div>
                                    <div class="bpjs-stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                        <i class="mdi mdi-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Akan Expired</p>
                                        <h3 class="mb-0"><?php echo number_format($obat_akan_expired); ?></h3>
                                        <small class="text-muted">≤ 30 hari lagi</small>
                                    </div>
                                    <div class="bpjs-stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                        <i class="mdi mdi-clock-alert"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Stok Rendah</p>
                                        <h3 class="mb-0"><?php echo number_format($obat_stok_rendah); ?></h3>
                                        <small class="text-muted">< 10 stok tersisa</small>
                                    </div>
                                    <div class="bpjs-stat-icon" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                                        <i class="mdi mdi-alert"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FILTER SECTION - SAMA SEPERTI FASKES -->
                    <div class="bpjs-filter-section">
                        <form method="GET" action="">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-9 col-md-8">
                                    <div class="bpjs-search-box">
                                        <i class="mdi mdi-magnify search-icon"></i>
                                        <input type="text" id="searchInput" class="form-control" 
                                               placeholder="Cari obat berdasarkan nama/kode...">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-4">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-bpjs flex-grow-1" onclick="performSearch()">
                                            <i class="mdi mdi-magnify me-2"></i> Cari
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()">
                                            <i class="mdi mdi-close"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- DATA TABLE -->
                    <div class="bpjs-table-container">
                        <div class="bpjs-table-header">
                            <h5><i class="mdi mdi-format-list-bulleted me-2"></i>Daftar Data Obat</h5>
                        </div>
                        
                        <?php if(empty($data_obat)): ?>
                            <div class="bpjs-empty-state">
                                <div class="bpjs-empty-state-icon">
                                    <i class="mdi mdi-database-off"></i>
                                </div>
                                <h5>Tidak ada data obat ditemukan</h5>
                                <p>Tambahkan data obat pertama Anda</p>
                                <a href="tambah_obat.php" class="btn btn-bpjs">
                                    <i class="mdi mdi-plus me-2"></i> Tambah Obat Baru
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="tabelObat" class="table bpjs-table">
                                    <thead>
                                        <tr>
                                            <th width="60" class="text-center">No</th>
                                            <th>Kode Obat</th>
                                            <th>Nama Obat</th>
                                            <th width="100">Jenis</th>
                                            <th width="100">Satuan</th>
                                            <th width="100" class="text-center">Stok</th>
                                            <th width="120" class="text-center">Harga (Rp)</th>
                                            <th width="120" class="text-center">Expired</th>
                                            <th width="100" class="text-center">Status</th>
                                            <th width="140" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data_obat as $index => $obat): ?>
                                            <?php
                                            $is_expired = false;
                                            $is_expiring_soon = false;
                                            $is_stok_rendah = false;
                                            $is_stok_habis = false;
                                            
                                            // Cek expired
                                            if (!empty($obat['tanggal_expired'])) {
                                                $expired_date = strtotime($obat['tanggal_expired']);
                                                $current_date = time();
                                                $is_expired = ($expired_date < $current_date);
                                                
                                                if (!$is_expired) {
                                                    $thirty_days_later = strtotime('+30 days');
                                                    $is_expiring_soon = ($expired_date <= $thirty_days_later && $expired_date > $current_date);
                                                }
                                            }
                                            
                                            // Cek stok
                                            $is_stok_habis = ($obat['stok'] == 0);
                                            $is_stok_rendah = ($obat['stok'] > 0 && $obat['stok'] < 10);
                                            
                                            // Determine stok badge class
                                            $stok_badge_class = 'bpjs-stok-tinggi';
                                            if ($is_stok_habis) {
                                                $stok_badge_class = 'bpjs-stok-habis';
                                            } elseif ($is_stok_rendah) {
                                                $stok_badge_class = 'bpjs-stok-rendah';
                                            }
                                            
                                            // Determine expired badge class
                                            $expired_badge_class = 'bpjs-expired-ok';
                                            $expired_icon = '';
                                            if ($is_expired) {
                                                $expired_badge_class = 'bpjs-expired';
                                                $expired_icon = '<i class="mdi mdi-alert ml-1"></i>';
                                            } elseif ($is_expiring_soon) {
                                                $expired_badge_class = 'bpjs-expired-soon';
                                                $expired_icon = '<i class="mdi mdi-clock-alert ml-1"></i>';
                                            }
                                            
                                            // Status badge
                                            $status_badge_class = ($obat['status'] == 'Aktif') ? 'bpjs-status-aktif' : 'bpjs-status-nonaktif';
                                            ?>
                                            <tr>
                                                <td class="text-center" data-label="No">
                                                    <span class="badge bg-light text-dark"><?php echo $index + 1; ?></span>
                                                </td>
                                                <td data-label="Kode Obat">
                                                    <strong class="text-primary d-block"><?php echo htmlspecialchars($obat['kode_obat']); ?></strong>
                                                </td>
                                                <td data-label="Nama Obat">
                                                    <div class="font-weight-semibold"><?php echo htmlspecialchars($obat['nama_obat']); ?></div>
                                                </td>
                                                <td data-label="Jenis">
                                                    <span class="text-muted"><?php echo htmlspecialchars($obat['jenis'] ?? '-'); ?></span>
                                                </td>
                                                <td data-label="Satuan">
                                                    <span class="text-muted"><?php echo htmlspecialchars($obat['satuan'] ?? '-'); ?></span>
                                                </td>
                                                <td data-label="Stok" class="text-center">
                                                    <span class="bpjs-stok-badge <?php echo $stok_badge_class; ?>">
                                                        <?php echo htmlspecialchars($obat['stok']); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Harga" class="text-center">
                                                    <strong class="text-success">
                                                        Rp <?php echo number_format($obat['harga'], 0, ',', '.'); ?>
                                                    </strong>
                                                </td>
                                                <td data-label="Expired" class="text-center">
                                                    <?php if (!empty($obat['tanggal_expired'])): ?>
                                                        <div class="d-flex align-items-center justify-content-center">
                                                            <span class="bpjs-expired-badge <?php echo $expired_badge_class; ?>">
                                                                <?php echo date('d/m/Y', strtotime($obat['tanggal_expired'])); ?>
                                                                <?php echo $expired_icon; ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Status" class="text-center">
                                                    <span class="bpjs-status-badge <?php echo $status_badge_class; ?>">
                                                        <?php echo htmlspecialchars($obat['status']); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Aksi" class="text-center">
                                                    <div class="bpjs-action-buttons">
                                                        <!-- UBAH INI: ganti button dengan link ke detail_obat.php -->
                                                        <a href="detail_obat.php?id=<?php echo $obat['id']; ?>" 
                                                           class="btn btn-sm btn-info" 
                                                           title="Detail">
                                                            <i class="mdi mdi-eye"></i>
                                                        </a>
                                                        
                                                        <!-- UBAH INI: ganti button dengan link ke edit_obat.php -->
                                                        <a href="edit_obat.php?id=<?php echo $obat['id']; ?>" 
                                                           class="btn btn-sm btn-warning" 
                                                           title="Edit">
                                                            <i class="mdi mdi-pencil"></i>
                                                        </a>
                                                        
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="confirmDelete(<?php echo $obat['id']; ?>, '<?php echo htmlspecialchars(addslashes($obat['nama_obat'])); ?>')"
                                                                title="Hapus">
                                                            <i class="mdi mdi-delete"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Info Footer -->
                            <div class="bpjs-pagination">
                                <div class="row align-items-center">
                                    <div class="col-md-12">
                                        <p class="text-muted mb-0">
                                            Menampilkan <strong><?php echo count($data_obat); ?></strong> data obat
                                            <?php if ($obat_expired > 0): ?>
                                                <span class="text-danger ml-2">
                                                    <i class="mdi mdi-alert mr-1"></i><?php echo $obat_expired; ?> expired
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($obat_stok_rendah > 0): ?>
                                                <span class="text-warning ml-2">
                                                    <i class="mdi mdi-alert mr-1"></i><?php echo $obat_stok_rendah; ?> stok rendah
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FOOTER - SAMA SEPERTI FASKES -->
        

    <!-- Scripts -->
    <script src="../assets/vendors/js/core.js"></script>
    <script src="../assets/vendors/apexcharts/apexcharts.min.js"></script>
    <script src="../assets/vendors/chartjs/Chart.min.js"></script>
    <script src="../assets/js/charts/chartjs.addon.js"></script>
    <script src="../assets/js/template.js"></script>
    
    <!-- jQuery dan DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Inisialisasi DataTables
    $(document).ready(function() {
        $('#tabelObat').DataTable({
            "language": {
                "search": "Cari:",
                "lengthMenu": "Tampilkan _MENU_ data",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                "infoEmpty": "Tidak ada data",
                "infoFiltered": "(disaring dari _MAX_ total data)",
                "zeroRecords": "Data tidak ditemukan",
                "paginate": {
                    "first": "Pertama",
                    "last": "Terakhir",
                    "next": "Berikutnya",
                    "previous": "Sebelumnya"
                }
            },
            "pageLength": 10,
            "order": [[0, 'asc']]
        });
    });
    
    // Fungsi pencarian
    function performSearch() {
        var searchValue = document.getElementById('searchInput').value;
        var table = $('#tabelObat').DataTable();
        table.search(searchValue).draw();
    }
    
    function clearSearch() {
        document.getElementById('searchInput').value = '';
        var table = $('#tabelObat').DataTable();
        table.search('').draw();
    }
    
    // Konfirmasi hapus data
    function confirmDelete(id, namaObat) {
        Swal.fire({
            title: 'Hapus Obat?',
            html: `Apakah Anda yakin ingin menghapus obat:<br><strong>${namaObat}</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect ke halaman obat.php dengan parameter delete
                window.location.href = 'obat.php?delete=' + id;
            }
        });
    }
    
    // Auto-focus search input
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    
    // Show loading when submitting forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            Swal.fire({
                title: 'Menyimpan...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
    });
    
    // Session timeout
    let idleTime = 0;
    setInterval(() => {
        idleTime++;
        if (idleTime > 25) {
            alert("Sesi Anda akan berakhir dalam 5 menit karena tidak aktif.");
        }
        if (idleTime > 30) {
            window.location.href = "logout.php?reason=timeout";
        }
    }, 60000);
    
    // Reset idle time on activity
    document.addEventListener('mousemove', () => idleTime = 0);
    document.addEventListener('keypress', () => idleTime = 0);
    document.addEventListener('click', () => idleTime = 0);
    
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
    </script>
</body>
</html>