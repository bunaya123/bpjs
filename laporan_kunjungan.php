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
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// Update last activity
$_SESSION['last_activity'] = time();

// Inisialisasi variabel filter
$filter_tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
$filter_tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$filter_faskes = isset($_GET['faskes_id']) ? intval($_GET['faskes_id']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_jenis = isset($_GET['jenis_pelayanan']) ? $_GET['jenis_pelayanan'] : '';

// Build query dengan filter
$where_conditions = [];
$params = [];
$param_types = "";

// Filter tanggal
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_akhir)) {
    $where_conditions[] = "k.tanggal_kunjungan BETWEEN ? AND ?";
    $params[] = $filter_tanggal_mulai;
    $params[] = $filter_tanggal_akhir;
    $param_types .= "ss";
}

// Filter faskes
if ($filter_faskes > 0) {
    $where_conditions[] = "k.faskes_id = ?";
    $params[] = $filter_faskes;
    $param_types .= "i";
}

// Filter status
if (!empty($filter_status) && in_array($filter_status, ['terdaftar', 'diproses', 'selesai', 'batal'])) {
    $where_conditions[] = "k.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

// Filter jenis pelayanan
if (!empty($filter_jenis) && in_array($filter_jenis, ['rawat_jalan', 'rawat_inap', 'ugd', 'rutin'])) {
    $where_conditions[] = "k.jenis_pelayanan = ?";
    $params[] = $filter_jenis;
    $param_types .= "s";
}

// Build WHERE clause
$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Query untuk data kunjungan
$sql = "SELECT k.*, 
        p.nama as peserta_nama, p.nik, p.no_kartu,
        f.nama_faskes as faskes_nama,
        d.nama_dokter as dokter_nama
        FROM kunjungan k
        LEFT JOIN peserta p ON k.peserta_id = p.id
        LEFT JOIN faskes f ON k.faskes_id = f.id
        LEFT JOIN dokter d ON k.dokter_id = d.id
        $where_clause
        ORDER BY k.tanggal_kunjungan DESC, k.jam_kunjungan DESC";

// Eksekusi query dengan parameter jika ada
$result_list = null;
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result_list = mysqli_stmt_get_result($stmt);
} else {
    $result_list = mysqli_query($conn, $sql);
}

// Query statistik berdasarkan filter
$sql_statistik = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) as diproses,
    SUM(CASE WHEN status = 'terdaftar' THEN 1 ELSE 0 END) as terdaftar,
    SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as batal,
    SUM(CASE WHEN jenis_pelayanan = 'rawat_jalan' THEN 1 ELSE 0 END) as rawat_jalan,
    SUM(CASE WHEN jenis_pelayanan = 'rawat_inap' THEN 1 ELSE 0 END) as rawat_inap,
    SUM(CASE WHEN jenis_pelayanan = 'ugd' THEN 1 ELSE 0 END) as ugd,
    SUM(CASE WHEN jenis_pelayanan = 'rutin' THEN 1 ELSE 0 END) as rutin,
    SUM(biaya_administrasi) as total_biaya
    FROM kunjungan k $where_clause";

if (!empty($params)) {
    $stmt_stat = mysqli_prepare($conn, $sql_statistik);
    mysqli_stmt_bind_param($stmt_stat, $param_types, ...$params);
    mysqli_stmt_execute($stmt_stat);
    $result_stat = mysqli_stmt_get_result($stmt_stat);
    $statistik = mysqli_fetch_assoc($result_stat);
    mysqli_stmt_close($stmt_stat);
} else {
    $result_stat = mysqli_query($conn, $sql_statistik);
    $statistik = mysqli_fetch_assoc($result_stat);
}

// Ambil data dropdown faskes
$faskes_list = mysqli_query($conn, "SELECT id, nama_faskes FROM faskes WHERE status = 'aktif' ORDER BY nama_faskes");

// Cek foto profil user
$profile_pic = $user['profile_pic'] ?? '';
$profile_path = 'uploads/profile_pics/' . $profile_pic;
$has_custom_profile = (!empty($profile_pic) && file_exists($profile_path));
$default_avatar = '../assets/images/faces/avatar-default.png';

// Fungsi untuk mendapatkan label jenis pelayanan
function getJenisLabel($jenis) {
    $labels = [
        'rawat_jalan' => 'Rawat Jalan',
        'rawat_inap' => 'Rawat Inap',
        'ugd' => 'UGD',
        'rutin' => 'Rutin'
    ];
    return $labels[$jenis] ?? $jenis;
}

// Fungsi untuk mendapatkan label status
function getStatusLabel($status) {
    $labels = [
        'terdaftar' => 'Terdaftar',
        'diproses' => 'Diproses',
        'selesai' => 'Selesai',
        'batal' => 'Batal'
    ];
    return $labels[$status] ?? $status;
}

// Format Rupiah
function formatRupiah($angka) {
    if ($angka === null || $angka == 0) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Laporan Kunjungan - BPJS</title>
    
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/iconfonts/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    
    <!-- vendor css for this page -->
    <link rel="stylesheet" href="../assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    
    <!-- Layout styles -->
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    
    <style>
        /* Custom styling untuk halaman laporan */
        .page-header-bpjs {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.2);
        }
        
        .page-header-bpjs h1 {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .page-header-bpjs .subtitle {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .stat-card-bpjs {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stat-card-bpjs:hover {
            transform: translateY(-5px);
        }
        
        .stat-card-primary {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
        }
        
        .stat-card-success {
            background: linear-gradient(135deg, #00a65a 0%, #006837 100%);
        }
        
        .stat-card-warning {
            background: linear-gradient(135deg, #f39c12 0%, #c87f0a 100%);
        }
        
        .stat-card-info {
            background: linear-gradient(135deg, #00c0ef 0%, #0088a3 100%);
        }
        
        .stat-card-danger {
            background: linear-gradient(135deg, #dd4b39 0%, #a93226 100%);
        }
        
        .stat-card-purple {
            background: linear-gradient(135deg, #605ca8 0%, #3c3b6e 100%);
        }
        
        .stat-card-teal {
            background: linear-gradient(135deg, #39cccc 0%, #229954 100%);
        }
        
        .stat-card-orange {
            background: linear-gradient(135deg, #ff851b 0%, #cc6c00 100%);
        }
        
        .stat-icon {
            font-size: 40px;
            opacity: 0.8;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .card-header-bpjs {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            color: white;
            border-radius: 8px 8px 0 0 !important;
            padding: 15px 20px;
        }
        
        .filter-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .btn-bpjs {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 20px;
            transition: all 0.3s ease;
        }
        
        .btn-bpjs:hover {
            background: linear-gradient(135deg, #0055aa 0%, #002244 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 102, 204, 0.3);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #00a65a 0%, #006837 100%);
            color: white;
            border: none;
        }
        
        .btn-print:hover {
            background: linear-gradient(135deg, #008848 0%, #004d27 100%);
            color: white;
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #f39c12 0%, #c87f0a 100%);
            color: white;
            border: none;
        }
        
        .btn-reset:hover {
            background: linear-gradient(135deg, #e08e0b 0%, #a66909 100%);
            color: white;
        }
        
        .badge-bpjs {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-rawat-jalan {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .badge-rawat-inap {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge-ugd {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge-rutin {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-badge-bpjs {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        
        .status-terdaftar {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .status-diproses {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-selesai {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-batal {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .laporan-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .laporan-table td {
            vertical-align: middle;
        }
        
        .laporan-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .table-actions {
            min-width: 120px;
        }
        
        .export-buttons .btn {
            margin-right: 5px;
        }
        
        .form-control-bpjs {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 8px 12px;
            transition: all 0.3s;
        }
        
        .form-control-bpjs:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        /* FOTO PROFIL SIDEBAR */
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
            background-color: var(--bpjs-blue);
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
        
        /* PRINT STYLES - SAMA SEPERTI LAPORAN PESERTA */
        .print-header {
            display: none;
        }
        
        @media print {
            .sidebar, .t-header, .no-print, .filter-section, .export-btn-group, .btn-action-group, 
            .filter-card, .export-buttons, .btn, .dataTables_length,
            .dataTables_filter, .dataTables_info, .dataTables_paginate,
            .page-header-bpjs, .stat-card-bpjs {
                display: none !important;
            }
            
            .print-header {
                display: block !important;
            }
            
            .page-content-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #000;
                background: white;
                padding: 15px !important;
            }
            
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            
            .laporan-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 20px !important;
                font-size: 10px !important;
            }
            
            .laporan-table th {
                background-color: #0066cc !important;
                color: white !important;
                border: 1px solid #0056b3 !important;
                padding: 6px 8px !important;
                text-align: center !important;
                font-weight: bold !important;
            }
            
            .laporan-table td {
                border: 1px solid #ddd !important;
                padding: 5px 8px !important;
                vertical-align: top !important;
                text-align: center !important;
            }
            
            .badge-bpjs, .status-badge-bpjs {
                border: 1px solid #000 !important;
                font-size: 9px !important;
                padding: 2px 6px !important;
            }
            
            @page {
                margin: 0.5cm !important;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-header-bpjs {
                padding: 15px;
            }
            
            .page-header-bpjs h1 {
                font-size: 24px;
            }
            
            .stat-value {
                font-size: 22px;
            }
            
            .stat-icon {
                font-size: 30px;
            }
            
            .export-buttons .btn {
                margin-bottom: 10px;
                width: 100%;
            }
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
                        <img class="profile-img img-lg rounded-circle" 
                             src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                             alt="profile image"
                             onerror="this.style.display='none'; document.getElementById('avatar-default-obat').style.display='block';">
                    <?php endif; ?>
                    
                    <img id="avatar-default-obat" 
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
                <li class="active">
                    <a href="#laporan" data-toggle="collapse" aria-expanded="true">
                        <span class="link-title">Laporan</span>
                        <i class="mdi mdi-chart-bar link-icon"></i>
                    </a>
                    <ul class="collapse navigation-submenu show" id="laporan">
                        <li>
                            <a href="laporan_peserta.php">Laporan Peserta</a>
                        </li>
                        <li class="active">
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
                <li class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'ubah_password.php']) ? 'active' : ''; ?>">
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
                    <!-- Header Halaman -->
                    <div class="page-header-bpjs">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="mdi mdi-chart-bar mr-2"></i> Laporan Kunjungan</h1>
                                <p class="subtitle">Analisis dan monitoring data kunjungan peserta BPJS</p>
                            </div>
                            <div class="export-buttons">
                                <!-- Tombol Cetak Langsung -->
                                <button class="btn btn-print btn-bpjs" onclick="printLaporan()">
                                    <i class="mdi mdi-printer mr-1"></i> Cetak
                                </button>
                                <button onclick="exportToExcel()" class="btn btn-success btn-bpjs">
                                    <i class="mdi mdi-file-excel mr-1"></i> Excel
                                </button>
                                <button onclick="exportToPDF()" class="btn btn-danger btn-bpjs">
                                    <i class="mdi mdi-file-pdf mr-1"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Form -->
                    <div class="card filter-card">
                        <div class="card-header card-header-bpjs">
                            <h6 class="mb-0"><i class="mdi mdi-filter mr-2"></i> Filter Laporan</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="" class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Tanggal Mulai</label>
                                        <input type="date" class="form-control form-control-bpjs" 
                                               name="tanggal_mulai" 
                                               value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Tanggal Akhir</label>
                                        <input type="date" class="form-control form-control-bpjs" 
                                               name="tanggal_akhir" 
                                               value="<?php echo htmlspecialchars($filter_tanggal_akhir); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Faskes</label>
                                        <select class="form-control form-control-bpjs" name="faskes_id">
                                            <option value="">Semua Faskes</option>
                                            <?php while ($faskes = mysqli_fetch_assoc($faskes_list)): ?>
                                                <option value="<?php echo $faskes['id']; ?>"
                                                    <?php echo $filter_faskes == $faskes['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($faskes['nama_faskes']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                            <?php mysqli_data_seek($faskes_list, 0); ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select class="form-control form-control-bpjs" name="status">
                                            <option value="">Semua Status</option>
                                            <option value="terdaftar" <?php echo $filter_status == 'terdaftar' ? 'selected' : ''; ?>>Terdaftar</option>
                                            <option value="diproses" <?php echo $filter_status == 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                            <option value="selesai" <?php echo $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                            <option value="batal" <?php echo $filter_status == 'batal' ? 'selected' : ''; ?>>Batal</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Jenis Pelayanan</label>
                                        <select class="form-control form-control-bpjs" name="jenis_pelayanan">
                                            <option value="">Semua Jenis</option>
                                            <option value="rawat_jalan" <?php echo $filter_jenis == 'rawat_jalan' ? 'selected' : ''; ?>>Rawat Jalan</option>
                                            <option value="rawat_inap" <?php echo $filter_jenis == 'rawat_inap' ? 'selected' : ''; ?>>Rawat Inap</option>
                                            <option value="ugd" <?php echo $filter_jenis == 'ugd' ? 'selected' : ''; ?>>UGD</option>
                                            <option value="rutin" <?php echo $filter_jenis == 'rutin' ? 'selected' : ''; ?>>Rutin</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-bpjs mr-2">
                                            <i class="mdi mdi-magnify mr-1"></i> Terapkan Filter
                                        </button>
                                        <a href="laporan_kunjungan.php" class="btn btn-reset">
                                            <i class="mdi mdi-refresh mr-1"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- Info Filter Aktif -->
                            <div class="mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <i class="mdi mdi-information-outline mr-1"></i>
                                    <strong>Filter Aktif:</strong>
                                    <?php 
                                    $filter_info = [];
                                    if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_akhir)) {
                                        $filter_info[] = "Periode: " . date('d/m/Y', strtotime($filter_tanggal_mulai)) . " - " . date('d/m/Y', strtotime($filter_tanggal_akhir));
                                    }
                                    if ($filter_faskes > 0) {
                                        $filter_info[] = "Faskes: ID $filter_faskes";
                                    }
                                    if (!empty($filter_status)) {
                                        $filter_info[] = "Status: " . getStatusLabel($filter_status);
                                    }
                                    if (!empty($filter_jenis)) {
                                        $filter_info[] = "Jenis: " . getJenisLabel($filter_jenis);
                                    }
                                    
                                    if (empty($filter_info)) {
                                        echo "Tidak ada filter yang diterapkan.";
                                    } else {
                                        echo implode(" | ", $filter_info);
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistik -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-primary">
                                <div class="stat-icon">
                                    <i class="mdi mdi-hospital-building"></i>
                                </div>
                                <div class="stat-value"><?php echo $statistik['total'] ?? 0; ?></div>
                                <div class="stat-label">Total Kunjungan</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-success">
                                <div class="stat-icon">
                                    <i class="mdi mdi-check-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $statistik['selesai'] ?? 0; ?></div>
                                <div class="stat-label">Selesai</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-warning">
                                <div class="stat-icon">
                                    <i class="mdi mdi-clock-outline"></i>
                                </div>
                                <div class="stat-value"><?php echo $statistik['diproses'] ?? 0; ?></div>
                                <div class="stat-label">Diproses</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-danger">
                                <div class="stat-icon">
                                    <i class="mdi mdi-close-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $statistik['batal'] ?? 0; ?></div>
                                <div class="stat-label">Dibatalkan</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-info">
                                <div class="stat-icon">
                                    <i class="mdi mdi-walk"></i>
                                </div>
                                <div class="stat-value"><?php echo $statistik['rawat_jalan'] ?? 0; ?></div>
                                <div class="stat-label">Rawat Jalan</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-purple">
                                <div class="stat-icon">
                                    <i class="mdi mdi-bed"></i>
                                </div>
                                <div class="stat-value"><?php echo $statistik['rawat_inap'] ?? 0; ?></div>
                                <div class="stat-label">Rawat Inap</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-teal">
                                <div class="stat-icon">
                                    <i class="mdi mdi-ambulance"></i>
                                </div>
                                <div class="stat-value"><?php echo $statistik['ugd'] ?? 0; ?></div>
                                <div class="stat-label">UGD</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-bpjs stat-card-orange">
                                <div class="stat-icon">
                                    <i class="mdi mdi-cash"></i>
                                </div>
                                <div class="stat-value">Rp <?php echo number_format($statistik['total_biaya'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Total Biaya</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabel Data Kunjungan -->
                    <div class="card">
                        <div class="card-header card-header-bpjs d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="mdi mdi-table mr-2"></i> Data Kunjungan</h6>
                            <small class="text-light">Total: <?php echo mysqli_num_rows($result_list); ?> data</small>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" id="table-laporan">
                                <table id="dataTable" class="table table-striped table-bordered laporan-table">
                                    <thead>
                                        <tr>
                                            <th width="50">No</th>
                                            <th>Tanggal/Jam</th>
                                            <th>Peserta</th>
                                            <th>Faskes</th>
                                            <th>Jenis</th>
                                            <th>Dokter</th>
                                            <th>Diagnosa</th>
                                            <th>Biaya</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if ($result_list && mysqli_num_rows($result_list) > 0): 
                                        $no = 1; 
                                        $total_biaya = 0;
                                        ?>
                                        <?php while ($row = mysqli_fetch_assoc($result_list)): 
                                            $total_biaya += $row['biaya_administrasi'];
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++; ?></td>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($row['tanggal_kunjungan'])); ?></strong><br>
                                                <small class="text-muted"><?php echo $row['jam_kunjungan']; ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['peserta_nama'] ?? '-'); ?></strong><br>
                                                <small class="text-muted">Kartu: <?php echo htmlspecialchars($row['no_kartu'] ?? '-'); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['faskes_nama'] ?? '-'); ?></td>
                                            <td>
                                                <?php 
                                                $jenis = $row['jenis_pelayanan'] ?? '';
                                                $badge_class = [
                                                    'rawat_jalan' => 'badge-bpjs badge-rawat-jalan',
                                                    'rawat_inap' => 'badge-bpjs badge-rawat-inap',
                                                    'ugd' => 'badge-bpjs badge-ugd',
                                                    'rutin' => 'badge-bpjs badge-rutin'
                                                ];
                                                ?>
                                                <span class="<?php echo $badge_class[$jenis] ?? 'badge badge-secondary'; ?>">
                                                    <?php echo getJenisLabel($jenis); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['dokter_nama'] ?? '-'); ?></td>
                                            <td>
                                                <?php 
                                                $diagnosis = $row['diagnosis'] ?? '';
                                                echo htmlspecialchars(substr($diagnosis, 0, 25)); 
                                                if (strlen($diagnosis) > 25) echo '...';
                                                ?>
                                            </td>
                                            <td class="text-right">
                                                Rp <?php echo number_format($row['biaya_administrasi'], 0, ',', '.'); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status = $row['status'] ?? 'terdaftar';
                                                $status_class = [
                                                    'terdaftar' => 'status-badge-bpjs status-terdaftar',
                                                    'diproses' => 'status-badge-bpjs status-diproses',
                                                    'selesai' => 'status-badge-bpjs status-selesai',
                                                    'batal' => 'status-badge-bpjs status-batal'
                                                ];
                                                ?>
                                                <span class="<?php echo $status_class[$status] ?? 'badge badge-secondary'; ?>">
                                                    <?php echo getStatusLabel($status); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <div class="py-5">
                                                    <i class="mdi mdi-chart-bar mdi-3x text-muted mb-3"></i>
                                                    <p class="text-muted">Tidak ada data kunjungan untuk periode yang dipilih</p>
                                                    <a href="kunjungan.php" class="btn btn-bpjs">
                                                        <i class="mdi mdi-plus"></i> Tambah Data Kunjungan
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <?php if ($result_list && mysqli_num_rows($result_list) > 0): ?>
                                    <tfoot>
                                        <tr class="bg-light">
                                            <td colspan="7" class="text-right"><strong>Total Biaya:</strong></td>
                                            <td class="text-right"><strong>Rp <?php echo number_format($total_biaya, 0, ',', '.'); ?></strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- HEADER UNTUK PRINT (DISEMBUNYIKAN) - SAMA SEPERTI LAPORAN PESERTA -->
    <div class="print-header" id="print-header" style="display: none;">
        <div style="text-align: center; margin-bottom: 30px;">
            <!-- Logo dan Header BPJS -->
            <table style="width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="width: 20%; text-align: left; vertical-align: top;">
                        <!-- Logo BPJS -->
                        <div style="text-align: center;">
                            <div style="background: #0066cc; color: white; padding: 10px; border-radius: 5px; display: inline-block;">
                                <strong style="font-size: 16px;">BPJS</strong><br>
                                <span style="font-size: 12px;">KESEHATAN</span>
                            </div>
                        </div>
                    </td>
                    <td style="width: 60%; text-align: center; vertical-align: top;">
                        <h2 style="margin: 0; color: #0066cc; font-weight: bold;">BADAN PENYELENGGARA JAMINAN SOSIAL</h2>
                        <h3 style="margin: 5px 0 10px 0; color: #333;">LAPORAN DATA KUNJUNGAN BPJS KESEHATAN</h3>
                        <p style="margin: 0; font-size: 12px;">
                            <strong>Alamat Kantor:</strong> Jl. Letjen Sutoyo No. 79, Cililitan, Jakarta Timur 13640<br>
                            <strong>Telp:</strong> (021) 1500-400 | <strong>Email:</strong> contact@bpjs-kesehatan.go.id<br>
                            <strong>Website:</strong> www.bpjs-kesehatan.go.id
                        </p>
                    </td>
                    <td style="width: 20%; text-align: right; vertical-align: top;">
                        <!-- Logo Republik -->
                        <div style="text-align: center;">
                            <div style="background: #ff0000; color: white; padding: 10px; border-radius: 5px; display: inline-block;">
                                <strong style="font-size: 16px;">REP</strong><br>
                                <span style="font-size: 12px;">INDONESIA</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <hr style="border: 2px solid #0066cc; margin: 10px 0;">
            
            <!-- Informasi Laporan -->
            <div style="text-align: left; font-size: 12px; margin-bottom: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <strong>INFORMASI LAPORAN</strong><br>
                            <strong>Periode:</strong> 
                            <?php 
                            if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_akhir)) {
                                echo date('d F Y', strtotime($filter_tanggal_mulai)) . ' s/d ' . date('d F Y', strtotime($filter_tanggal_akhir));
                            } else {
                                echo 'Semua Periode';
                            }
                            ?><br>
                            <strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i:s'); ?><br>
                            <strong>Jumlah Data:</strong> <?php echo $statistik['total'] ?? 0; ?> Kunjungan<br>
                            <strong>Status Filter:</strong> 
                            <?php 
                            $filter_active = [];
                            if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_akhir)) {
                                $filter_active[] = "Periode: " . date('d/m/Y', strtotime($filter_tanggal_mulai)) . " - " . date('d/m/Y', strtotime($filter_tanggal_akhir));
                            }
                            if ($filter_faskes > 0) {
                                $filter_active[] = "Faskes: ID $filter_faskes";
                            }
                            if (!empty($filter_status)) {
                                $filter_active[] = "Status: " . getStatusLabel($filter_status);
                            }
                            if (!empty($filter_jenis)) {
                                $filter_active[] = "Jenis: " . getJenisLabel($filter_jenis);
                            }
                            
                            echo empty($filter_active) ? 'Semua Data' : implode(', ', $filter_active);
                            ?>
                        </td>
                        <td style="width: 50%; vertical-align: top;">
                            <strong>STATISTIK KUNJUNGAN</strong><br>
                            <strong>Total Kunjungan:</strong> <?php echo $statistik['total'] ?? 0; ?><br>
                            <strong>Selesai:</strong> <?php echo $statistik['selesai'] ?? 0; ?> | 
                            <strong>Diproses:</strong> <?php echo $statistik['diproses'] ?? 0; ?><br>
                            <strong>Dibatalkan:</strong> <?php echo $statistik['batal'] ?? 0; ?> | 
                            <strong>Total Biaya:</strong> <?php echo formatRupiah($statistik['total_biaya'] ?? 0); ?><br>
                            <strong>Peserta Aktif:</strong> <?php echo $statistik['total'] ?? 0; ?> data ditemukan
                        </td>
                    </tr>
                </table>
            </div>
            
            <hr style="border: 1px solid #ddd; margin: 10px 0;">
        </div>
    </div>

    <!-- plugins:js -->
    <script src="../assets/vendors/js/core.js"></script>
    <script src="../assets/vendors/jquery/jquery.min.js"></script>
    <script src="../assets/vendors/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- endinject -->
    
    <!-- Vendor Js For This Page -->
    <script src="../assets/vendors/datatables.net/jquery.dataTables.js"></script>
    <script src="../assets/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
    <!-- End vendor js for this page -->
    
    <!-- build:js -->
    <script src="../assets/js/template.js"></script>
    <!-- endbuild -->
    
    <!-- Script untuk Print -->
    <script>
    // Fungsi print laporan - SAMA SEPERTI LAPORAN PESERTA
    function printLaporan() {
        const printContents = document.getElementById('table-laporan').innerHTML;
        const originalContents = document.body.innerHTML;
        const printHeader = document.getElementById('print-header').innerHTML;
        
        // Clone table untuk print
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = printContents;
        const table = tempDiv.getElementsByTagName('table')[0];
        
        // Dapatkan nama user untuk tanda tangan (ambil dari user yang login)
        const userName = "<?php echo $user['full_name'] ? htmlspecialchars($user['full_name']) : htmlspecialchars($user['username']); ?>";
        const userPosition = "<?php echo $user['username'] == 'admin' ? 'Administrator' : 'Petugas BPJS'; ?>";
        
        // Tambahkan tanda tangan dengan nama user
        const signatureSection = `
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 70%;">
                            <p style="font-size: 11px; color: #666;">
                                <strong>Keterangan:</strong><br>
                                1. Laporan ini dicetak secara otomatis dari Sistem Informasi BPJS Kesehatan<br>
                                2. Data diambil dari database yang diperbarui hingga <?php echo date('d F Y H:i:s'); ?><br>
                                3. Laporan ini sah dan dapat dipertanggungjawabkan<br>
                                4. Untuk informasi lebih lanjut hubungi call center 1500-400
                            </p>
                        </td>
                        <td style="width: 30%; text-align: center;">
                            <div style="margin-top: 60px;">
                                <p>Jakarta, <?php echo date('d F Y'); ?></p>
                                <p>Petugas yang bertanggung jawab,</p>
                                <br><br><br><br>
                                <p><strong><u>${userName}</u></strong></p>
                                <p>${userPosition}</p>
                                <p>ID: <?php echo htmlspecialchars($user['id']); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="print-footer" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 11px; text-align: center; color: #666;">
                <p>Laporan ini dicetak secara otomatis dari Sistem Informasi BPJS Kesehatan</p>
                <p>&copy; <?php echo date('Y'); ?> BPJS Kesehatan. Hak Cipta Dilindungi Undang-Undang.</p>
                <p>Dicetak oleh: <?php echo htmlspecialchars($user['username']); ?> | User ID: <?php echo $user_id; ?></p>
            </div>
        `;
        
        document.body.innerHTML = 
            '<html><head><title>Laporan Kunjungan BPJS</title>' +
            '<style>' +
            'body {padding: 20px; font-family: "Arial", "Helvetica", sans-serif;}' +
            'table {width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px;}' +
            'th {background-color: #0066cc; color: white; padding: 8px; text-align: center; border: 1px solid #0056b3; font-weight: bold;}' +
            'td {padding: 6px; border: 1px solid #ddd; vertical-align: middle; text-align: center;}' +
            '.badge {padding: 2px 6px; border-radius: 8px; font-size: 9px; display: inline-block;}' +
            '.badge-rawat-jalan {background-color: #d1ecf1; color: #0c5460;}' +
            '.badge-rawat-inap {background-color: #d4edda; color: #155724;}' +
            '.badge-ugd {background-color: #f8d7da; color: #721c24;}' +
            '.badge-rutin {background-color: #fff3cd; color: #856404;}' +
            '.status-badge {padding: 2px 6px; border-radius: 8px; font-size: 9px; font-weight: bold;}' +
            '.status-terdaftar {background-color: #cce5ff; color: #004085;}' +
            '.status-diproses {background-color: #fff3cd; color: #856404;}' +
            '.status-selesai {background-color: #d4edda; color: #155724;}' +
            '.status-batal {background-color: #f8d7da; color: #721c24;}' +
            '.print-header h2, .print-header h3 {font-family: "Arial", sans-serif;}' +
            '.print-header p {margin: 3px 0;}' +
            '@media print {' +
            '  @page { margin: 0.5cm; }' +
            '  body { padding: 10px; }' +
            '}' +
            '</style>' +
            '</head><body>' +
            printHeader +
            table.outerHTML +
            signatureSection +
            '</body></html>';
        
        window.print();
        setTimeout(function() {
            document.body.innerHTML = originalContents;
            window.location.reload();
        }, 100);
    }
    
    $(document).ready(function() {
        // Inisialisasi DataTable
        if ($('#dataTable').length) {
            $('#dataTable').DataTable({
                "pageLength": 25,
                "order": [[1, 'desc']],
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Data tidak ditemukan",
                    "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                    "infoEmpty": "Tidak ada data",
                    "infoFiltered": "(difilter dari _MAX_ total data)",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
        }
        
        // Fungsi export ke Excel
        window.exportToExcel = function() {
            var table = document.getElementById("dataTable");
            var html = table.outerHTML;
            
            // Buat blob dan download
            var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "Laporan_Kunjungan_<?php echo date('Y-m-d'); ?>.xls";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        };
        
        // Fungsi export ke PDF
        window.exportToPDF = function() {
            alert("Fitur export PDF akan segera tersedia.");
        };
        
        // Auto close alert setelah 5 detik
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        
        // Set tanggal default jika kosong
        if (!$('input[name="tanggal_mulai"]').val()) {
            $('input[name="tanggal_mulai"]').val('<?php echo date("Y-m-01"); ?>');
        }
        if (!$('input[name="tanggal_akhir"]').val()) {
            $('input[name="tanggal_akhir"]').val('<?php echo date("Y-m-d"); ?>');
        }
        
        // Validasi tanggal tidak boleh tanggal mulai > tanggal akhir
        $('form').submit(function(e) {
            var tanggalMulai = new Date($('input[name="tanggal_mulai"]').val());
            var tanggalAkhir = new Date($('input[name="tanggal_akhir"]').val());
            
            if (tanggalMulai > tanggalAkhir) {
                alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir!');
                e.preventDefault();
                return false;
            }
            return true;
        });
        
        // Refresh halaman jika ada perubahan filter
        $('select').change(function() {
            if ($(this).val() !== '') {
                // Trigger submit form
                $('form').submit();
            }
        });
    });
    </script>
</body>
</html>
<?php 
// Tutup koneksi database
if (isset($conn)) {
    mysqli_close($conn);
}
?>