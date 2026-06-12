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
$limit = 15; // Jumlah data per halaman
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filter tambahan
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'all';
$filter_faskes = isset($_GET['faskes']) ? $_GET['faskes'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Query dinamis
$where_conditions = [];
$params = [];
$types = '';

if ($filter_status != 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_kelas != 'all') {
    $where_conditions[] = "kelas_bpjs = ?";
    $params[] = $filter_kelas;
    $types .= 's';
}

if ($filter_faskes != 'all') {
    $where_conditions[] = "faskes = ?";
    $params[] = $filter_faskes;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(no_kartu LIKE ? OR nik LIKE ? OR nama LIKE ? OR no_telepon LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= str_repeat('s', 5);
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Hitung total data
$count_sql = "SELECT COUNT(*) as total FROM peserta $where_sql";
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

// Ambil data peserta dengan filter
$params_limit = array_merge($params, [$limit, $offset]);
$types_limit = $types . 'ii';

$sql_peserta = "SELECT * FROM peserta $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt_peserta = mysqli_prepare($conn, $sql_peserta);
if (!empty($params_limit)) {
    mysqli_stmt_bind_param($stmt_peserta, $types_limit, ...$params_limit);
}
mysqli_stmt_execute($stmt_peserta);
$result_peserta = mysqli_stmt_get_result($stmt_peserta);
$peserta = mysqli_fetch_all($result_peserta, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_peserta);

// Ambil data unik untuk filter dropdown
$sql_kelas = "SELECT DISTINCT kelas_bpjs FROM peserta WHERE kelas_bpjs IS NOT NULL ORDER BY kelas_bpjs";
$sql_faskes = "SELECT DISTINCT faskes FROM peserta WHERE faskes IS NOT NULL ORDER BY faskes";
$result_kelas = mysqli_query($conn, $sql_kelas);
$result_faskes = mysqli_query($conn, $sql_faskes);
$kelas_options = mysqli_fetch_all($result_kelas, MYSQLI_ASSOC);
$faskes_options = mysqli_fetch_all($result_faskes, MYSQLI_ASSOC);

// Statistik
$stats_sql = "SELECT 
    status,
    COUNT(*) as jumlah,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM peserta), 2) as persentase
    FROM peserta 
    GROUP BY status";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = [];
while ($row = mysqli_fetch_assoc($stats_result)) {
    $stats[$row['status']] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Data Peserta BPJS - <?php echo htmlspecialchars($user['username']); ?></title>
    
    <!-- TEMPLATE CSS -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    
    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* BPJS THEME - CLEAN AND PROFESSIONAL */
    :root {
        --bpjs-primary: #0066cc;
        --bpjs-primary-dark: #004d99;
        --bpjs-primary-light: #e6f2ff;
        --bpjs-secondary: #0099ff;
        --bpjs-accent: #00a8ff;
        --bpjs-success: #28a745;
        --bpjs-warning: #ffc107;
        --bpjs-danger: #dc3545;
        --bpjs-light: #f8f9fa;
        --bpjs-dark: #343a40;
        --bpjs-gray: #6c757d;
    }
    
    /* GLOBAL STYLES */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f7fa;
    }
    
    .page-content-wrapper-inner {
        padding: 20px;
    }
    
    /* HEADER SECTION */
    .bpjs-header-section {
        background: white;
        border-radius: 12px;
        padding: 25px 30px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 102, 204, 0.08);
        border-left: 4px solid var(--bpjs-primary);
    }
    
    .bpjs-page-title {
        color: var(--bpjs-primary-dark);
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 1.8rem;
    }
    
    .bpjs-page-subtitle {
        color: var(--bpjs-gray);
        font-size: 0.95rem;
        margin-bottom: 0;
        opacity: 0.85;
    }
    
    /* STATS CARDS */
    .bpjs-stats-container {
        margin-bottom: 25px;
    }
    
    .bpjs-stat-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        height: 100%;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        border-top: 4px solid transparent;
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    
    .bpjs-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .bpjs-stat-card-total { border-color: var(--bpjs-primary); }
    .bpjs-stat-card-active { border-color: var(--bpjs-success); }
    .bpjs-stat-card-pending { border-color: var(--bpjs-warning); }
    .bpjs-stat-card-inactive { border-color: var(--bpjs-danger); }
    
    .bpjs-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        margin-bottom: 15px;
    }
    
    .bpjs-stat-icon-total { background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%); }
    .bpjs-stat-icon-active { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
    .bpjs-stat-icon-pending { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
    .bpjs-stat-icon-inactive { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
    
    .bpjs-stat-number {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--bpjs-dark);
        margin-bottom: 5px;
    }
    
    .bpjs-stat-label {
        color: var(--bpjs-gray);
        font-size: 0.85rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .bpjs-stat-percentage {
        font-size: 0.75rem;
        color: var(--bpjs-gray);
        margin-top: 5px;
    }
    
    /* QUICK ACTIONS */
    .bpjs-quick-actions {
        margin-bottom: 25px;
    }
    
    .bpjs-quick-action-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        height: 100%;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 20px;
    }
    
    .bpjs-quick-action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 102, 204, 0.1);
        border-color: var(--bpjs-primary-light);
    }
    
    .bpjs-quick-action-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 28px;
        color: white;
    }
    
    .bpjs-quick-action-icon-register { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
    .bpjs-quick-action-icon-payment { background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%); }
    .bpjs-quick-action-icon-history { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .bpjs-quick-action-icon-add { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
    
    .bpjs-quick-action-title {
        color: var(--bpjs-dark);
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 5px;
    }
    
    .bpjs-quick-action-desc {
        color: var(--bpjs-gray);
        font-size: 0.85rem;
        margin-bottom: 0;
    }
    
    /* FILTER SECTION */
    .bpjs-filter-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }
    
    .bpjs-search-box {
        position: relative;
    }
    
    .bpjs-search-box .form-control {
        padding-left: 45px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        height: 45px;
        font-size: 0.9rem;
    }
    
    .bpjs-search-box .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--bpjs-primary);
        z-index: 2;
    }
    
    .bpjs-quick-filter {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }
    
    .bpjs-quick-filter .btn {
        border-radius: 20px;
        padding: 6px 15px;
        margin-right: 8px;
        margin-bottom: 8px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    /* TABLE SECTION */
    .bpjs-table-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }
    
    .bpjs-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .bpjs-table-title {
        color: var(--bpjs-primary-dark);
        font-weight: 600;
        font-size: 1.1rem;
        margin: 0;
    }
    
    .bpjs-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }
    
    .bpjs-table thead th {
        background: var(--bpjs-primary);
        color: white;
        border: none;
        padding: 12px 15px;
        font-weight: 500;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        vertical-align: middle;
        border-bottom: none;
    }
    
    .bpjs-table thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    .bpjs-table theach th:last-child {
        border-top-right-radius: 8px;
    }
    
    .bpjs-table tbody td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
        font-size: 0.9rem;
    }
    
    .bpjs-table tbody tr:hover {
        background-color: var(--bpjs-primary-light);
    }
    
    /* TABLE CELL STYLES */
    .bpjs-peserta-info {
        display: flex;
        align-items: center;
    }
    
    .bpjs-gender-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        color: white;
        margin-right: 10px;
    }
    
    .bpjs-gender-male { background: var(--bpjs-primary); }
    .bpjs-gender-female { background: #e83e8c; }
    
    .bpjs-peserta-name {
        font-weight: 500;
        color: var(--bpjs-dark);
    }
    
    .bpjs-peserta-dob {
        font-size: 0.8rem;
        color: var(--bpjs-gray);
    }
    
    /* BADGES */
    .bpjs-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        min-width: 70px;
    }
    
    .bpjs-badge-kelas-1 {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .bpjs-badge-kelas-2 {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .bpjs-badge-kelas-3 {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .bpjs-badge-faskes {
        background: #e3f2fd;
        color: #1976d2;
        border: 1px solid #bbdefb;
    }
    
    .bpjs-badge-status {
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .bpjs-badge-active { background: #d4edda; color: #155724; }
    .bpjs-badge-pending { background: #fff3cd; color: #856404; }
    .bpjs-badge-inactive { background: #f8d7da; color: #721c24; }
    
    /* ACTION BUTTONS */
    .bpjs-action-buttons {
        display: flex;
        justify-content: center;
        gap: 5px;
    }
    
    .bpjs-action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: none;
        color: white;
        transition: all 0.2s ease;
    }
    
    .bpjs-action-btn:hover {
        transform: scale(1.05);
        opacity: 0.9;
    }
    
    .bpjs-action-btn-detail { background: #17a2b8; }
    .bpjs-action-btn-edit { background: #ffc107; }
    .bpjs-action-btn-delete { background: #dc3545; }
    
  
    
    /* EMPTY STATE */
    .bpjs-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--bpjs-gray);
    }
    
    .bpjs-empty-state-icon {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    
    .bpjs-empty-state-title {
        color: var(--bpjs-dark);
        margin-bottom: 10px;
        font-weight: 500;
    }
    
    .bpjs-empty-state-desc {
        margin-bottom: 25px;
        opacity: 0.8;
    }
    
    /* BPJS BUTTON */
    .btn-bpjs {
        background: var(--bpjs-primary);
        border: none;
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .btn-bpjs:hover {
        background: var(--bpjs-primary-dark);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 102, 204, 0.2);
    }
    
    /* FORM CONTROLS */
    .form-control-bpjs {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 10px 15px;
        font-size: 0.9rem;
    }
    
    .form-control-bpjs:focus {
        border-color: var(--bpjs-primary);
        box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    }
    
    /* SELECT DROPDOWNS */
    select.form-control {
        border-radius: 6px;
        border: 1px solid #dee2e6;
        padding: 10px 15px;
        font-size: 0.9rem;
        height: 45px;
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
    
    /* RESPONSIVE */
    @media (max-width: 768px) {
        .bpjs-header-section {
            padding: 20px;
        }
        
        .bpjs-page-title {
            font-size: 1.5rem;
        }
        
        .bpjs-table-section {
            padding: 15px;
        }
        
        .bpjs-table {
            display: block;
            overflow-x: auto;
        }
        
        .bpjs-table thead {
            display: none;
        }
        
        .bpjs-table tbody tr {
            display: block;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
        }
        
        .bpjs-table tbody td {
            display: block;
            text-align: right;
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .bpjs-table tbody td::before {
            content: attr(data-label);
            float: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            color: var(--bpjs-gray);
        }
        
        .bpjs-pagination-container {
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }
        
        .bpjs-quick-filter .btn {
            margin-bottom: 5px;
        }
    }
    </style>
</head>
  
 <body class="header-fixed">

<div class="page-body">
    <!-- SIDEBAR - SAMA SEPERTI KELAS.PHP -->
    <div class="sidebar">
        <div class="user-profile">
            <div class="display-avatar animated-avatar">
                <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                    <!-- Jika ada foto profil yang diupload -->
                    <img class="profile-img img-lg rounded-circle" 
                         src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                         alt="profile image"
                         onerror="this.style.display='none'; document.getElementById('avatar-default-pendaftaran').style.display='block';">
                <?php endif; ?>
                
                <!-- Foto default (akan ditampilkan jika tidak ada custom photo) -->
                <img id="avatar-default-pendaftaran" 
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
                    <li class="<?php echo basename($_Server['PHP_SELF']) == 'ubah_password.php' ? 'active' : ''; ?>">
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
                
                <!-- PAGE HEADER -->
                <div class="bpjs-header-section">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="bpjs-page-title">
                                <i class="mdi mdi-account-multiple text-primary me-2"></i>Data Peserta BPJS
                            </h1>
                            <p class="bpjs-page-subtitle">
                                Manajemen data peserta BPJS Kesehatan
                            </p>
                        </div>
                    </div>
                </div>

                <!-- QUICK ACTIONS -->
                <div class="row bpjs-quick-actions">
                    <div class="col-md-6 col-sm-6">
                        <a href="pendaftaran.php" class="bpjs-quick-action-card">
                            <div class="bpjs-quick-action-icon bpjs-quick-action-icon-register">
                                <i class="mdi mdi-account-plus"></i>
                            </div>
                            <h5 class="bpjs-quick-action-title">Pendaftaran Baru</h5>
                            <p class="bpjs-quick-action-desc">Daftarkan peserta baru BPJS</p>
                        </a>
                    </div>
                    <div class="col-md-6 col-sm-6">
                        <a href="pembayaran.php" class="bpjs-quick-action-card">
                            <div class="bpjs-quick-action-icon bpjs-quick-action-icon-payment">
                                <i class="mdi mdi-cash-usd"></i>
                            </div>
                            <h5 class="bpjs-quick-action-title">Pembayaran Iuran</h5>
                            <p class="bpjs-quick-action-desc">Bayar iuran peserta BPJS</p>
                        </a>
                    </div>
                  
                    </div>
                  

                <!-- STATISTICS -->
                <div class="row bpjs-stats-container">
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                        <div class="bpjs-stat-card bpjs-stat-card-total">
                            <div class="bpjs-stat-icon bpjs-stat-icon-total">
                                <i class="mdi mdi-account-multiple"></i>
                            </div>
                            <div class="bpjs-stat-number"><?php echo number_format($total_data); ?></div>
                            <div class="bpjs-stat-label">Total Peserta</div>
                            <div class="bpjs-stat-percentage">100% dari total data</div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                        <div class="bpjs-stat-card bpjs-stat-card-active">
                            <div class="bpjs-stat-icon bpjs-stat-icon-active">
                                <i class="mdi mdi-check-circle"></i>
                            </div>
                            <div class="bpjs-stat-number"><?php echo number_format($stats['active']['jumlah'] ?? 0); ?></div>
                            <div class="bpjs-stat-label">Peserta Aktif</div>
                            <div class="bpjs-stat-percentage"><?php echo $stats['active']['persentase'] ?? 0; ?>% dari total</div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                        <div class="bpjs-stat-card bpjs-stat-card-pending">
                            <div class="bpjs-stat-icon bpjs-stat-icon-pending">
                                <i class="mdi mdi-clock"></i>
                            </div>
                            <div class="bpjs-stat-number"><?php echo number_format($stats['pending']['jumlah'] ?? 0); ?></div>
                            <div class="bpjs-stat-label">Peserta Pending</div>
                            <div class="bpjs-stat-percentage"><?php echo $stats['pending']['persentase'] ?? 0; ?>% dari total</div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                        <div class="bpjs-stat-card bpjs-stat-card-inactive">
                            <div class="bpjs-stat-icon bpjs-stat-icon-inactive">
                                <i class="mdi mdi-close-circle"></i>
                            </div>
                            <div class="bpjs-stat-number"><?php echo number_format($stats['inactive']['jumlah'] ?? 0); ?></div>
                            <div class="bpjs-stat-label">Peserta Non-Aktif</div>
                            <div class="bpjs-stat-percentage"><?php echo $stats['inactive']['persentase'] ?? 0; ?>% dari total</div>
                        </div>
                    </div>
                </div>

                <!-- FILTER SECTION -->
                <div class="bpjs-filter-section">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="bpjs-search-box">
                                    <i class="mdi mdi-magnify search-icon"></i>
                                    <input type="text" class="form-control form-control-bpjs" name="search" 
                                           placeholder="Cari nama, NIK, atau no kartu..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-lg-2">
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Non-Aktif</option>
                                </select>
                            </div>
                            <div class="col-lg-3">
                                <select class="form-control" name="kelas" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter_kelas == 'all' ? 'selected' : ''; ?>>Semua Kelas</option>
                                    <?php foreach ($kelas_options as $kelas): ?>
                                        <option value="<?php echo htmlspecialchars($kelas['kelas_bpjs']); ?>" 
                                            <?php echo $filter_kelas == $kelas['kelas_bpjs'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kelas['kelas_bpjs']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <button type="submit" class="btn btn-bpjs w-100">
                                    <i class="mdi mdi-filter me-1"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- QUICK FILTER -->
                    <div class="bpjs-quick-filter mt-3">
                        <span class="me-2 text-muted">Filter Cepat:</span>
                        <a href="?status=all" class="btn btn-sm <?php echo $filter_status == 'all' ? 'active btn-primary' : 'btn-outline-secondary'; ?>">
                            Semua (<?php echo $total_data; ?>)
                        </a>
                        <a href="?status=active" class="btn btn-sm <?php echo $filter_status == 'active' ? 'active btn-success' : 'btn-outline-success'; ?>">
                            Aktif (<?php echo $stats['active']['jumlah'] ?? 0; ?>)
                        </a>
                        <a href="?status=pending" class="btn btn-sm <?php echo $filter_status == 'pending' ? 'active btn-warning' : 'btn-outline-warning'; ?>">
                            Pending (<?php echo $stats['pending']['jumlah'] ?? 0; ?>)
                        </a>
                        <a href="?status=inactive" class="btn btn-sm <?php echo $filter_status == 'inactive' ? 'active btn-danger' : 'btn-outline-danger'; ?>">
                            Non-Aktif (<?php echo $stats['inactive']['jumlah'] ?? 0; ?>)
                        </a>
                    </div>
                </div>

                <!-- DATA TABLE -->
                <div class="bpjs-table-section">
                    <?php if (empty($peserta)): ?>
                        <div class="bpjs-empty-state">
                            <i class="mdi mdi-database-off bpjs-empty-state-icon"></i>
                            <h4 class="bpjs-empty-state-title">Tidak ada data ditemukan</h4>
                            <p class="bpjs-empty-state-desc">Coba ubah filter atau tambah data peserta baru</p>
                            <div class="mt-4">
                                <a href="riwayat_pembayaran.php" class="btn btn-bpjs mr-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="mdi mdi-history me-1"></i> Riwayat Pembayaran
                                </a>
                                <a href="tambah_peserta.php" class="btn btn-bpjs mr-2">
                                    <i class="mdi mdi-plus me-1"></i> Tambah Peserta
                                </a>
                                <a href="pendaftaran.php" class="btn btn-bpjs" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                    <i class="mdi mdi-account-plus me-1"></i> Pendaftaran Baru
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bpjs-table-header">
                            <h4 class="bpjs-table-title">
                                Daftar Peserta BPJS
                            </h4>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table bpjs-table">
                                <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Nama</th>
                                        <th>NIK</th>
                                        <th>No Kartu</th>
                                        <th>Kelas</th>
                                        <th>Faskes</th>
                                        <th>Status</th>
                                        <th width="120" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = ($page - 1) * $limit + 1; ?>
                                    <?php foreach ($peserta as $p): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td>
                                            <div class="bpjs-peserta-info">
                                                <div class="bpjs-gender-icon <?php echo $p['jenis_kelamin'] == 'L' ? 'bpjs-gender-male' : 'bpjs-gender-female'; ?>">
                                                    <?php echo $p['jenis_kelamin'] == 'L' ? 'L' : 'P'; ?>
                                                </div>
                                                <div>
                                                    <div class="bpjs-peserta-name"><?php echo htmlspecialchars($p['nama']); ?></div>
                                                    <small class="bpjs-peserta-dob">
                                                        <?php echo date('d/m/Y', strtotime($p['tanggal_lahir'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($p['nik']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($p['no_kartu']); ?></strong></td>
                                        <td>
                                            <span class="bpjs-badge bpjs-badge-kelas-<?php 
                                                echo isset($p['kelas_bpjs']) ? str_replace(' ', '-', strtolower($p['kelas_bpjs'])) : 'default'; 
                                            ?>">
                                                <?php echo htmlspecialchars($p['kelas_bpjs'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bpjs-badge bpjs-badge-faskes">
                                                <?php echo htmlspecialchars($p['faskes'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $p['status'] ?? 'pending';
                                            $badge_class = 'bpjs-badge-status bpjs-badge-' . $status;
                                            $badge_text = $status == 'active' ? 'Aktif' : 
                                                         ($status == 'inactive' ? 'Non-Aktif' : 'Pending');
                                            ?>
                                            <span class="<?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($badge_text); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="bpjs-action-buttons">
                                                <a href="detail_peserta.php?id=<?php echo $p['id']; ?>" 
                                                   class="bpjs-action-btn bpjs-action-btn-detail" title="Detail">
                                                    <i class="mdi mdi-eye"></i>
                                                </a>
                                                <a href="edit_peserta.php?id=<?php echo $p['id']; ?>" 
                                                   class="bpjs-action-btn bpjs-action-btn-edit" title="Edit">
                                                    <i class="mdi mdi-pencil"></i>
                                                </a>
                                                
                                                <button type="button" class="bpjs-action-btn bpjs-action-btn-delete btn-delete" 
                                                        data-id="<?php echo $p['id']; ?>"
                                                        data-nama="<?php echo htmlspecialchars($p['nama']); ?>"
                                                        data-toggle="modal" 
                                                        data-target="#deleteModal"
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
                        
                        <!-- PAGINATION -->
                        <?php if ($total_pages > 1): ?>
                        <div class="bpjs-pagination-container">
                            <div class="bpjs-pagination-info">
                                Menampilkan <strong><?php echo count($peserta); ?></strong> dari <strong><?php echo number_format($total_data); ?></strong> data
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination bpjs-pagination">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="mdi mdi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="mdi mdi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
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
                    <small class="text-gray mt-2">Data Peserta BPJS</small>
                </div>
            </div>
        </footer>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--bpjs-primary); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-delete me-2"></i>Konfirmasi Hapus
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center py-3">
                    <i class="mdi mdi-alert-circle-outline mdi-4x text-warning mb-3"></i>
                    <h5>Apakah Anda yakin?</h5>
                    <p>Data peserta <strong id="deleteNama"></strong> akan dihapus secara permanen.</p>
                    <p class="text-danger"><small>Aksi ini tidak dapat dibatalkan!</small></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <a id="deleteConfirm" href="#" class="btn btn-danger">
                    <i class="mdi mdi-delete me-1"></i> Hapus
                </a>
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
    // Delete confirmation
    $('.btn-delete').click(function() {
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        $('#deleteNama').text(nama);
        $('#deleteConfirm').attr('href', 'delete_peserta.php?id=' + id);
    });
    
    // Set active menu
    $('.navigation-menu li').removeClass('active');
    $('.navigation-menu li a[href="peserta_bpjs.php"]').parent().addClass('active');
    $('.navigation-menu li a[href="#data-master"]').parent().addClass('active');
    $('#data-master').addClass('show');
    
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
    
    // Mobile sidebar toggle
    $('.t-header-mobile-toggler').click(function() {
        $('.sidebar').toggleClass('show');
    });
});
</script>

</body>
</html>