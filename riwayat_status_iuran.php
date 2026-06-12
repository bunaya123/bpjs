<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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

// Parameter filter
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filter tambahan
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_peserta = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : 0;
$filter_start = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$filter_metode = isset($_GET['metode']) ? $_GET['metode'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Query dinamis untuk riwayat pembayaran
$where_conditions = [];
$params = [];
$types = '';

if ($filter_status != 'all') {
    $where_conditions[] = "pi.status_baru = ?";
    $params[] = $filter_status;
    $types .= 's';
}

// Metode pembayaran mungkin tidak ada di tabel riwayat_status_iuran
// Jadi kita skip filter metode untuk sementara

if ($filter_peserta > 0) {
    // Asumsi ada tabel iuran yang berelasi dengan peserta
    $where_conditions[] = "i.peserta_id = ?";
    $params[] = $filter_peserta;
    $types .= 'i';
}

if (!empty($filter_start)) {
    // Tanggal mungkin tidak ada di riwayat_status_iuran, perlu tabel iuran
    // $where_conditions[] = "DATE(i.tanggal_bayar) >= ?";
    // $params[] = $filter_start;
    // $types .= 's';
}

if (!empty($filter_end)) {
    // $where_conditions[] = "DATE(i.tanggal_bayar) <= ?";
    // $params[] = $filter_end;
    // $types .= 's';
}

if (!empty($search)) {
    // $where_conditions[] = "(p.nama LIKE ? OR p.nik LIKE ? OR p.no_kartu LIKE ? OR i.no_transaksi LIKE ?)";
    // $search_term = "%$search%";
    // $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    // $types .= str_repeat('s', 4);
}

// Tambahkan filter user_id untuk keamanan
// $where_conditions[] = "i.user_id = ?";
// $params[] = $user_id;
// $types .= 'i';

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// SOLUSI: Gunakan tabel yang ada - riwayat_status_iuran
// Hitung total data
$count_sql = "SELECT COUNT(*) as total 
              FROM riwayat_status_iuran pi
              $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$row_count = mysqli_fetch_assoc($count_result);
$total_data = $row_count['total'];
$total_pages = ceil($total_data / $limit);
mysqli_stmt_close($count_stmt);

// Ambil data riwayat pembayaran dengan filter
$params_limit = array_merge($params, [$limit, $offset]);
$types_limit = $types . 'ii';

$sql_pembayaran = "SELECT 
    rsi.*,
    u.username as diubah_oleh,
    DATE_FORMAT(rsi.created_at, '%d/%m/%Y %H:%i') as tanggal_format
    FROM riwayat_status_iuran rsi
    LEFT JOIN users u ON rsi.perubahan_oleh = u.id
    $where_sql
    ORDER BY rsi.created_at DESC
    LIMIT ? OFFSET ?";

$stmt_pembayaran = mysqli_prepare($conn, $sql_pembayaran);
if (!empty($params_limit)) {
    mysqli_stmt_bind_param($stmt_pembayaran, $types_limit, ...$params_limit);
}
mysqli_stmt_execute($stmt_pembayaran);
$result_pembayaran = mysqli_stmt_get_result($stmt_pembayaran);
$riwayat = mysqli_fetch_all($result_pembayaran, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_pembayaran);

// Statistik pembayaran
$stats_sql = "SELECT 
    status_baru as status,
    COUNT(*) as jumlah
    FROM riwayat_status_iuran
    GROUP BY status_baru";
$stmt_stats = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_execute($stmt_stats);
$stats_result = mysqli_stmt_get_result($stmt_stats);
$stats = [];
while ($row = mysqli_fetch_assoc($stats_result)) {
    $stats[$row['status']] = $row;
}
mysqli_stmt_close($stmt_stats);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Riwayat Status Iuran - <?php echo htmlspecialchars($user['username']); ?></title>
    
    <!-- TEMPLATE CSS -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    
    <!-- CUSTOM BPJS CSS -->
    <link rel="stylesheet" href="../assets/css/bpjs-custom.css">
    
    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* Custom Styles for Riwayat Status Iuran */
    
    .bpjs-page-header {
        background: white;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    .bpjs-page-header h2 {
        color: #0073e6;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .bpjs-page-header .page-subtitle {
        color: #6c757d;
        margin-bottom: 0;
    }
    
    .bpjs-stat-card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.3s;
        height: 100%;
        margin-bottom: 20px;
        background: white;
    }
    
    .bpjs-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }
    
    .bpjs-stat-card .grid-body {
        padding: 20px;
    }
    
    .bpjs-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    
    .bpjs-stat-icon-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .bpjs-stat-icon-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
    .bpjs-stat-icon-pending { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
    .bpjs-stat-icon-failed { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
    
    .bpjs-table-container {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        margin-bottom: 20px;
    }
    
    .bpjs-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }
    
    .bpjs-table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 15px;
        font-weight: 500;
        font-size: 13px;
        text-transform: uppercase;
        vertical-align: middle;
    }
    
    .bpjs-table tbody td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }
    
    .bpjs-table tbody tr:hover {
        background-color: rgba(102, 126, 234, 0.03);
    }
    
    .bpjs-status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
    }
    
    .bpjs-status-success { 
        background: #d4edda; 
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .bpjs-status-pending { 
        background: #fff3cd; 
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .bpjs-status-failed { 
        background: #f8d7da; 
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .bpjs-status-expired { 
        background: #e2e3e5; 
        color: #383d41;
        border: 1px solid #d6d8db;
    }
    
    .btn-bpjs {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .btn-bpjs:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(102, 126, 234, 0.2);
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
                         onerror="this.style.display='none'; document.getElementById('avatar-default-riwayat').style.display='block';">
                <?php endif; ?>
                
                <img id="avatar-default-riwayat" 
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
            <li class="active">
                <a href="#transaksi" data-toggle="collapse" aria-expanded="true">
                    <span class="link-title">Transaksi</span>
                    <i class="mdi mdi-cash-multiple link-icon"></i>
                </a>
                <ul class="collapse navigation-submenu show" id="transaksi">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'pendaftaran.php' ? 'active' : ''; ?>">
                        <a href="pendaftaran.php">Pendaftaran</a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'pembayaran.php' ? 'active' : ''; ?>">
                        <a href="pembayaran.php">Pembayaran Iuran</a>
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
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'refund.php' ? 'active' : ''; ?>">
                        <a href="refund.php">Refund</a>
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
                        <a href="laporan/peserta.php">Laporan Peserta</a>
                    </li>
                    <li>
                        <a href="laporan/kunjungan.php">Laporan Kunjungan</a>
                    </li>
                    <li>
                        <a href="laporan/klaim.php">Laporan Klaim</a>
                    </li>
                    <li>
                        <a href="laporan/keuangan.php">Laporan Keuangan</a>
                    </li>
                    <li>
                        <a href="laporan/audit.php">Laporan Audit</a>
                    </li>
                    <li>
                        <a href="laporan/statistik.php">Statistik</a>
                    </li>
                </ul>
            </li>
            
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'riwayat.php' ? 'active' : ''; ?>">
                <a href="riwayat.php">
                    <span class="link-title">History</span>
                    <i class="mdi mdi-history link-icon"></i>
                </a>
            </li>
            <li>
                <a href="bantuan.php">
                    <span class="link-title">Help & Support</span>
                    <i class="mdi mdi-help-circle link-icon"></i>
                </a>
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
                <!-- CONTENT AREA -->
                <div class="page-content-wrapper">
                    <div class="page-content-wrapper-inner">
                        <div class="content-viewport">
                            
                            <!-- PAGE HEADER -->
                            <div class="bpjs-page-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2><i class="mdi mdi-history text-primary me-2"></i>Riwayat Status Iuran</h2>
                                        <p class="page-subtitle mb-0">Lihat riwayat perubahan status iuran BPJS</p>
                                    </div>
                                    <div>
                                        <a href="pembayaran.php" class="btn btn-bpjs mr-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Kembali
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- STATISTICS ROW -->
                            <div class="row">
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                    <div class="grid bpjs-stat-card">
                                        <div class="grid-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <p class="text-muted mb-1">Total Perubahan</p>
                                                    <h3 class="mb-0"><?php echo number_format($total_data); ?></h3>
                                                    <small class="text-muted">Semua riwayat perubahan</small>
                                                </div>
                                                <div class="bpjs-stat-icon bpjs-stat-icon-total">
                                                    <i class="mdi mdi-history"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php 
                                $status_icons = [
                                    'success' => ['icon' => 'check-circle', 'class' => 'bpjs-stat-icon-success', 'label' => 'Berhasil'],
                                    'pending' => ['icon' => 'clock', 'class' => 'bpjs-stat-icon-pending', 'label' => 'Menunggu'],
                                    'failed' => ['icon' => 'close-circle', 'class' => 'bpjs-stat-icon-failed', 'label' => 'Gagal'],
                                    'expired' => ['icon' => 'alert-circle', 'class' => 'bpjs-stat-icon-expired', 'label' => 'Kadaluarsa']
                                ];
                                
                                foreach ($status_icons as $status_key => $status_info): 
                                    $jumlah = $stats[$status_key]['jumlah'] ?? 0;
                                ?>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                    <div class="grid bpjs-stat-card">
                                        <div class="grid-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <p class="text-muted mb-1"><?php echo $status_info['label']; ?></p>
                                                    <h3 class="mb-0"><?php echo number_format($jumlah); ?></h3>
                                                    <small class="text-muted">
                                                        <?php echo $total_data > 0 ? round(($jumlah / $total_data) * 100, 1) : 0; ?>% dari total
                                                    </small>
                                                </div>
                                                <div class="bpjs-stat-icon <?php echo $status_info['class']; ?>">
                                                    <i class="mdi mdi-<?php echo $status_info['icon']; ?>"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- DATA TABLE -->
                            <div class="bpjs-table-container">
                                <?php if (empty($riwayat)): ?>
                                    <div class="text-center py-5">
                                        <i class="mdi mdi-history-off mdi-5x text-muted"></i>
                                        <h5 class="mt-3">Belum ada riwayat perubahan status</h5>
                                        <p class="text-muted">Perubahan status iuran akan tercatat di sini</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table bpjs-table">
                                            <thead>
                                                <tr>
                                                    <th width="50">No</th>
                                                    <th>ID Iuran</th>
                                                    <th>Status Lama</th>
                                                    <th>Status Baru</th>
                                                    <th>Alasan Perubahan</th>
                                                    <th>Diubah Oleh</th>
                                                    <th>Waktu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $no = ($page - 1) * $limit + 1; ?>
                                                <?php foreach ($riwayat as $r): ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $no++; ?></td>
                                                    <td>
                                                        <strong>#<?php echo $r['iuran_id']; ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $status_class = 'bpjs-status-badge ';
                                                        switch($r['status_lama']) {
                                                            case 'success':
                                                                $status_class .= 'bpjs-status-success';
                                                                break;
                                                            case 'pending':
                                                                $status_class .= 'bpjs-status-pending';
                                                                break;
                                                            case 'failed':
                                                                $status_class .= 'bpjs-status-failed';
                                                                break;
                                                            case 'expired':
                                                                $status_class .= 'bpjs-status-expired';
                                                                break;
                                                            default:
                                                                $status_class .= 'bpjs-status-pending';
                                                        }
                                                        ?>
                                                        <span class="<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($r['status_lama']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $status_class = 'bpjs-status-badge ';
                                                        switch($r['status_baru']) {
                                                            case 'success':
                                                                $status_class .= 'bpjs-status-success';
                                                                break;
                                                            case 'pending':
                                                                $status_class .= 'bpjs-status-pending';
                                                                break;
                                                            case 'failed':
                                                                $status_class .= 'bpjs-status-failed';
                                                                break;
                                                            case 'expired':
                                                                $status_class .= 'bpjs-status-expired';
                                                                break;
                                                            default:
                                                                $status_class .= 'bpjs-status-pending';
                                                        }
                                                        ?>
                                                        <span class="<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($r['status_baru']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo nl2br(htmlspecialchars($r['alasan_perubahan'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $r['diubah_oleh'] ?? 'System'; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $r['tanggal_format'] ?? '-'; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- PAGINATION -->
                                    <?php if ($total_pages > 1): ?>
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <p class="text-muted mb-0">
                                                Menampilkan <strong><?php echo count($riwayat); ?></strong> dari <strong><?php echo number_format($total_data); ?></strong> perubahan status
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <nav aria-label="Page navigation" class="d-flex justify-content-end">
                                                <ul class="pagination mb-0">
                                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                                            <i class="mdi mdi-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                    <?php 
                                                    $start_page = max(1, $page - 2);
                                                    $end_page = min($total_pages, $page + 2);
                                                    
                                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                                    ?>
                                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $i; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                                            <i class="mdi mdi-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- FOOTER -->
                    <footer class="footer">
                        <div class="row">
                            <div class="col-sm-6 text-center text-sm-right order-sm-1">
                                <ul class="text-gray">
                                    <li><a href="#">Terms of use</a></li>
                                    <li><a href="#">Privacy Policy</a></li>
                                </ul>
                            </div>
                            <div class="col-sm-6 text-center text-sm-left mt-3 mt-sm-0">
                                <small class="text-muted d-block">BPJS Kesehatan System &copy; <?php echo date('Y'); ?></small>
                                <small class="text-gray mt-2">Member Area</small>
                            </div>
                        </div>
                    </footer>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="../assets/vendors/js/core.js"></script>
<script src="../assets/js/template.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Set active menu
    $('.navigation-menu li').removeClass('active');
    $('.navigation-menu li a[href="riwayat_status.php"]').parent().addClass('active');
    $('.navigation-menu li a[href="#transaksi"]').parent().addClass('active');
    $('#transaksi').addClass('show');
});
</script>

</body>
</html>