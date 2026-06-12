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

// Pagination
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
$filter_jenis = isset($_GET['jenis']) ? mysqli_real_escape_string($conn, $_GET['jenis']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$search_condition = 'WHERE 1=1';
if (!empty($search)) {
    $search_condition .= " AND (kode_faskes LIKE '%$search%' OR nama_faskes LIKE '%$search%' OR alamat LIKE '%$search%')";
}
if (!empty($filter_jenis) && $filter_jenis != 'all') {
    $search_condition .= " AND jenis_faskes LIKE '%$filter_jenis%'";
}
if (!empty($filter_status) && $filter_status != 'all') {
    $search_condition .= " AND status = '$filter_status'";
}

// Get total records
$sql_total = "SELECT COUNT(*) as total FROM faskes $search_condition";
$result_total = mysqli_query($conn, $sql_total);
if (!$result_total) {
    die("Query error: " . mysqli_error($conn));
}
$total_records = mysqli_fetch_assoc($result_total)['total'];
$total_pages = ceil($total_records / $limit);

// Get faskes data
$sql = "SELECT * FROM faskes $search_condition ORDER BY created_at DESC LIMIT $offset, $limit";
$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Query error: " . mysqli_error($conn));
}
$faskes_data = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Hitung statistik
$sql_stats = "SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT jenis_faskes) as jumlah_jenis,
    SUM(CASE WHEN jenis_faskes LIKE '%rumah sakit%' THEN 1 ELSE 0 END) as rumah_sakit,
    SUM(CASE WHEN jenis_faskes LIKE '%klinik%' THEN 1 ELSE 0 END) as klinik,
    SUM(CASE WHEN jenis_faskes LIKE '%puskesmas%' THEN 1 ELSE 0 END) as puskesmas,
    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
    FROM faskes";
$result_stats = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($result_stats);

// Get unique jenis faskes for filter
$sql_jenis = "SELECT DISTINCT jenis_faskes FROM faskes WHERE jenis_faskes IS NOT NULL AND jenis_faskes != '' ORDER BY jenis_faskes";
$result_jenis = mysqli_query($conn, $sql_jenis);
$jenis_options = [];
while ($row = mysqli_fetch_assoc($result_jenis)) {
    $jenis_options[] = $row['jenis_faskes'];
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manajemen Data Faskes - <?php echo htmlspecialchars($user['username']); ?></title>
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
    
    <!-- Custom BPJS CSS -->
    <style>
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
    
    /* HEADER STYLING SEPERTI TINDAKAN */
    .bpjs-main-header {
        background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        color: white;
        box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
    }
    
    .bpjs-main-header h2 {
        color: white;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .bpjs-main-header .page-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
        margin-bottom: 20px;
    }
    
    /* STATISTICS CARDS - STYLE TINDAKAN */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .bpjs-stat-card {
        background: white;
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s;
        overflow: hidden;
        position: relative;
        height: 120px;
    }
    
    .bpjs-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }
    
    .bpjs-stat-card .grid-body {
        padding: 20px;
        position: relative;
        z-index: 1;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .bpjs-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        margin-bottom: 15px;
    }
    
    .bpjs-stat-icon-total { 
        background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%); 
    }
    .bpjs-stat-icon-jenis { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
    }
    .bpjs-stat-icon-rs { 
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); 
    }
    .bpjs-stat-icon-inactive { 
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
    }
    
    .bpjs-stat-card h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
    }
    
    .bpjs-stat-card p {
        margin: 5px 0 0 0;
        color: #6c757d;
        font-size: 14px;
        font-weight: 500;
    }
    
    /* ACTION BAR - SEPERTI TINDAKAN */
    .bpjs-action-bar {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    /* TOMBOL TAMBAH UTAMA - BESAR */
    .btn-bpjs-primary {
        background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
        border: none;
        color: white;
        border-radius: 8px;
        padding: 12px 25px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
        box-shadow: 0 4px 10px rgba(0, 102, 204, 0.2);
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    
    .btn-bpjs-primary:hover {
        background: linear-gradient(135deg, #0055aa 0%, #0088ee 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 102, 204, 0.3);
        text-decoration: none;
    }
    
    /* FILTER SECTION - SEPERTI TINDAKAN */
    .bpjs-filter-section {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    .bpjs-filter-grid {
        display: grid;
        grid-template-columns: 1fr auto auto auto;
        gap: 15px;
        align-items: end;
    }
    
    .bpjs-search-box {
        position: relative;
    }
    
    .bpjs-search-box .form-control {
        padding-left: 50px;
        border-radius: 8px;
        border: 1px solid #ddd;
        height: 48px;
        font-size: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .bpjs-search-box .form-control:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    }
    
    .bpjs-search-box .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #0066cc;
        z-index: 2;
        font-size: 20px;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-group label {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .filter-select {
        border-radius: 8px;
        border: 1px solid #ddd;
        height: 48px;
        font-size: 14px;
        padding: 0 15px;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .filter-select:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        outline: none;
    }
    
    /* ACTION BUTTONS GROUP */
    .action-btn-group {
        display: flex;
        gap: 10px;
    }
    
    .btn-filter {
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        height: 48px;
    }
    
    .btn-filter:hover {
        background: #5a6268;
        color: white;
        text-decoration: none;
    }
    
    .btn-reset {
        background: #f8f9fa;
        color: #6c757d;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        height: 48px;
    }
    
    .btn-reset:hover {
        background: #e2e6ea;
        color: #495057;
        text-decoration: none;
    }
    
    /* TABLE CONTAINER */
    .bpjs-table-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .bpjs-table-header {
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        background: #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .bpjs-table-header h5 {
        margin: 0;
        color: #0066cc;
        font-weight: 600;
        font-size: 18px;
    }
    
    .record-count {
        color: #6c757d;
        font-size: 14px;
        font-weight: 500;
    }
    
    /* TABLE STYLES */
    .bpjs-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }
    
    .bpjs-table thead th {
        background: #f8fafc;
        color: #495057;
        border: none;
        padding: 15px 20px;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        vertical-align: middle;
        white-space: nowrap;
        border-bottom: 2px solid #e9ecef;
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
        background-color: rgba(0, 102, 204, 0.03);
    }
    
    /* BADGE STYLES */
    .bpjs-type-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    
    .bpjs-type-rumah-sakit { 
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
        color: #0d47a1;
        border: 1px solid #90caf9;
    }
    
    .bpjs-type-klinik { 
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); 
        color: #1b5e20;
        border: 1px solid #a5d6a7;
    }
    
    .bpjs-type-puskesmas { 
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); 
        color: #e65100;
        border: 1px solid #ffcc80;
    }
    
    .bpjs-type-default { 
        background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); 
        color: #424242;
        border: 1px solid #bdbdbd;
    }
    
    .bpjs-status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
        text-align: center;
        min-width: 70px;
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
    
    /* ACTION BUTTONS */
    .bpjs-action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
        min-width: 140px;
    }
    
    .bpjs-action-buttons .btn {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 1px solid transparent;
        transition: all 0.3s;
        flex-shrink: 0;
    }
    
    .bpjs-action-buttons .btn i {
        font-size: 16px;
        line-height: 1;
        margin: 0;
    }
    
    .btn-action-view {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        border: none;
    }
    
    .btn-action-view:hover {
        background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
        color: white;
    }
    
    .btn-action-edit {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: white;
        border: none;
    }
    
    .btn-action-edit:hover {
        background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
        color: white;
    }
    
    .btn-action-delete {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        border: none;
    }
    
    .btn-action-delete:hover {
        background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        color: white;
    }
    
    /* PAGINATION */
    .bpjs-pagination {
        padding: 20px 25px;
        background: #f8fafc;
        border-top: 1px solid #f0f0f0;
    }
    
    .bpjs-pagination .page-link {
        border-radius: 6px;
        margin: 0 3px;
        border: 1px solid #dee2e6;
        color: #0066cc;
        font-weight: 500;
        padding: 8px 12px;
    }
    
    .bpjs-pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
        border-color: #0066cc;
        color: white;
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
    
    /* FLOATING ACTION BUTTON */
    .bpjs-fab {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 4px 12px rgba(0, 102, 204, 0.5);
        z-index: 999;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .bpjs-fab:hover {
        transform: scale(1.1);
        color: white;
        box-shadow: 0 6px 16px rgba(0, 102, 204, 0.7);
    }
    
    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .bpjs-filter-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .bpjs-main-header {
            padding: 20px;
        }
        
        .bpjs-action-bar {
            flex-direction: column;
            align-items: stretch;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .bpjs-filter-section {
            padding: 15px;
        }
        
        .bpjs-table-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
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
            color: #0066cc;
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
        
        .bpjs-fab {
            bottom: 80px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 22px;
        }
    }
    
    @media (max-width: 576px) {
        .bpjs-main-header h2 {
            font-size: 1.5rem;
        }
        
        .bpjs-stat-card {
            height: 100px;
        }
        
        .bpjs-stat-card .grid-body {
            padding: 15px;
        }
        
        .bpjs-stat-icon {
            width: 40px;
            height: 40px;
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .bpjs-stat-card h3 {
            font-size: 24px;
        }
        
        .action-btn-group {
            flex-direction: column;
            width: 100%;
        }
        
        .btn-bpjs-primary, .btn-filter, .btn-reset {
            width: 100%;
            justify-content: center;
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

                    <!-- HEADER SEPERTI TINDAKAN -->
                    <div class="bpjs-main-header">
                        <h2><i class="mdi mdi-hospital-building me-2"></i>Manajemen Data Faskes</h2>
                        <p class="page-subtitle mb-0">Kelola data fasilitas kesehatan untuk kebutuhan peserta BPJS</p>
                    </div>

                    <!-- ACTION BAR DENGAN TOMBOL TAMBAH -->
                    <div class="bpjs-action-bar">
                        <div>
                            <h4 class="mb-0" style="color: #0066cc;">Data Faskes Terdaftar</h4>
                            <p class="text-muted mb-0">Total <?php echo $total_records; ?> fasilitas kesehatan</p>
                        </div>
                        <div>
                            <a href="faskes_tambah.php" class="btn-bpjs-primary">
                                <i class="mdi mdi-plus me-2"></i> Tambah Faskes
                            </a>
                        </div>
                    </div>

                    <!-- STATISTICS ROW -->
                    <div class="stats-grid">
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="bpjs-stat-icon bpjs-stat-icon-total">
                                    <i class="mdi mdi-hospital-building"></i>
                                </div>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                                <p>Total Faskes</p>
                            </div>
                        </div>
                        
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="bpjs-stat-icon bpjs-stat-icon-jenis">
                                    <i class="mdi mdi-label-multiple"></i>
                                </div>
                                <h3><?php echo number_format($stats['jumlah_jenis']); ?></h3>
                                <p>Jenis Faskes</p>
                            </div>
                        </div>
                        
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="bpjs-stat-icon bpjs-stat-icon-rs">
                                    <i class="mdi mdi-hospital"></i>
                                </div>
                                <h3><?php echo number_format($stats['rumah_sakit']); ?></h3>
                                <p>Rumah Sakit</p>
                            </div>
                        </div>
                        
                        <div class="grid bpjs-stat-card">
                            <div class="grid-body">
                                <div class="bpjs-stat-icon bpjs-stat-icon-inactive">
                                    <i class="mdi mdi-account-off"></i>
                                </div>
                                <h3><?php echo number_format($stats['nonaktif']); ?></h3>
                                <p>Faskes Non-Aktif</p>
                            </div>
                        </div>
                    </div>

                    <!-- FILTER SECTION -->
                    <div class="bpjs-filter-section">
                        <form method="GET" action="">
                            <div class="bpjs-filter-grid">
                                <div class="bpjs-search-box">
                                    <i class="mdi mdi-magnify search-icon"></i>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Cari kode faskes, nama, atau alamat..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="filterJenis"><i class="mdi mdi-filter me-1"></i> Filter Jenis</label>
                                    <select class="filter-select" id="filterJenis" name="jenis">
                                        <option value="all" <?php echo $filter_jenis == 'all' ? 'selected' : ''; ?>>Semua Jenis</option>
                                        <?php foreach($jenis_options as $jenis): ?>
                                            <option value="<?php echo htmlspecialchars($jenis); ?>" <?php echo $filter_jenis == $jenis ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($jenis); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="filterStatus"><i class="mdi mdi-check-circle me-1"></i> Filter Status</label>
                                    <select class="filter-select" id="filterStatus" name="status">
                                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                                        <option value="aktif" <?php echo $filter_status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="nonaktif" <?php echo $filter_status == 'nonaktif' ? 'selected' : ''; ?>>Non-Aktif</option>
                                    </select>
                                </div>
                                
                                <div class="action-btn-group">
                                    <button type="submit" class="btn-filter">
                                        <i class="mdi mdi-filter-apply me-2"></i> Terapkan Filter
                                    </button>
                                    <?php if(!empty($search) || !empty($filter_jenis) || !empty($filter_status)): ?>
                                    <a href="faskes.php" class="btn-reset">
                                        <i class="mdi mdi-filter-remove me-2"></i> Reset
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- DATA TABLE -->
                    <div class="bpjs-table-container">
                        <div class="bpjs-table-header">
                            <h5><i class="mdi mdi-format-list-bulleted me-2"></i> Daftar Fasilitas Kesehatan</h5>
                            <span class="record-count"><?php echo $total_records; ?> records</span>
                        </div>
                        
                        <?php if(empty($faskes_data)): ?>
                            <div class="bpjs-empty-state">
                                <div class="bpjs-empty-state-icon">
                                    <i class="mdi mdi-database-off"></i>
                                </div>
                                <h5>Tidak ada data faskes ditemukan</h5>
                                <p><?php echo !empty($search) || !empty($filter_jenis) || !empty($filter_status) ? 'Coba ubah filter pencarian Anda' : 'Tambahkan data faskes pertama Anda'; ?></p>
                                <a href="faskes_tambah.php" class="btn-bpjs-primary">
                                    <i class="mdi mdi-plus me-2"></i> Tambah Faskes Baru
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table bpjs-table">
                                    <thead>
                                        <tr>
                                            <th width="60" class="text-center">No</th>
                                            <th>Kode Faskes</th>
                                            <th>Nama Faskes</th>
                                            <th width="120">Jenis</th>
                                            <th width="150">Telepon</th>
                                            <th width="100" class="text-center">Status</th>
                                            <th width="140" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = $offset + 1; ?>
                                        <?php foreach($faskes_data as $faskes): ?>
                                        <tr>
                                            <td class="text-center" data-label="No">
                                                <span class="badge bg-light text-dark"><?php echo $no++; ?></span>
                                            </td>
                                            <td data-label="Kode Faskes">
                                                <strong class="text-primary d-block"><?php echo htmlspecialchars($faskes['kode_faskes']); ?></strong>
                                            </td>
                                            <td data-label="Nama Faskes">
                                                <div class="font-weight-semibold"><?php echo htmlspecialchars($faskes['nama_faskes']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($faskes['kota'] ?? ''); ?></small>
                                            </td>
                                            <td data-label="Jenis">
                                                <?php 
                                                $jenis = strtolower($faskes['jenis_faskes'] ?? 'default');
                                                $badge_class = '';
                                                if (strpos($jenis, 'rumah sakit') !== false) {
                                                    $badge_class = 'bpjs-type-rumah-sakit';
                                                } elseif (strpos($jenis, 'klinik') !== false) {
                                                    $badge_class = 'bpjs-type-klinik';
                                                } elseif (strpos($jenis, 'puskesmas') !== false) {
                                                    $badge_class = 'bpjs-type-puskesmas';
                                                } else {
                                                    $badge_class = 'bpjs-type-default';
                                                }
                                                ?>
                                                <span class="bpjs-type-badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($faskes['jenis_faskes'] ?? 'Lainnya'); ?>
                                                </span>
                                            </td>
                                            <td data-label="Telepon">
                                                <span class="font-weight-medium"><?php echo htmlspecialchars($faskes['no_telepon']); ?></span>
                                            </td>
                                            <td data-label="Status" class="text-center">
                                                <?php 
                                                $status = isset($faskes['status']) ? strtolower($faskes['status']) : 'aktif';
                                                $badge_class = $status == 'aktif' ? 'bpjs-status-aktif' : 'bpjs-status-nonaktif';
                                                $badge_text = $status == 'aktif' ? 'Aktif' : 'Non-Aktif';
                                                ?>
                                                <span class="bpjs-status-badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($badge_text); ?>
                                                </span>
                                            </td>
                                            <td data-label="Aksi" class="text-center">
                                                <div class="bpjs-action-buttons">
                                                    <a href="faskes_detail.php?id=<?php echo $faskes['id']; ?>" 
                                                       class="btn btn-action-view" title="Detail" data-toggle="tooltip">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                    <a href="faskes_edit.php?id=<?php echo $faskes['id']; ?>" 
                                                       class="btn btn-action-edit" title="Edit" data-toggle="tooltip">
                                                        <i class="mdi mdi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-action-delete" 
                                                            onclick="confirmDelete(<?php echo $faskes['id']; ?>, '<?php echo htmlspecialchars(addslashes($faskes['nama_faskes'])); ?>')" 
                                                            title="Hapus" data-toggle="tooltip">
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
                            <?php if($total_pages > 1): ?>
                            <div class="bpjs-pagination">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-0">
                                            Menampilkan <strong><?php echo count($faskes_data); ?></strong> dari <strong><?php echo $total_records; ?></strong> data
                                            <?php if(!empty($search) || !empty($filter_jenis) || !empty($filter_status)): ?>
                                            <br><small>Dengan filter: 
                                                <?php if(!empty($search)): ?>"<strong><?php echo htmlspecialchars($search); ?></strong>" <?php endif; ?>
                                                <?php if(!empty($filter_jenis) && $filter_jenis != 'all'): ?>/ Jenis: <?php echo htmlspecialchars($filter_jenis); ?> <?php endif; ?>
                                                <?php if(!empty($filter_status) && $filter_status != 'all'): ?>/ Status: <?php echo htmlspecialchars($filter_status); ?><?php endif; ?>
                                            </small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <nav aria-label="Page navigation" class="d-flex justify-content-end">
                                            <ul class="pagination mb-0">
                                                <?php if($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>">
                                                        <i class="mdi mdi-chevron-left"></i>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                $start = max(1, $page - 2);
                                                $end = min($total_pages, $page + 2);
                                                
                                                for($i = $start; $i <= $end; $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>"><?php echo $i; ?></a>
                                                </li>
                                                <?php endfor; ?>
                                                
                                                <?php if($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>">
                                                        <i class="mdi mdi-chevron-right"></i>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    </div>
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
                    <div class="col-sm-6 text-center text-sm-left mt-3 mt-sm-0">
                        <small class="text-muted d-block">BPJS Kesehatan System &copy; <?php echo date('Y'); ?></small>
                        <small class="text-gray mt-2">Member Area</small>
                    </div>
                    <div class="col-sm-6 text-center text-sm-right order-sm-1">
                        <ul class="text-gray list-inline mb-0">
                            <!-- Optional footer links -->
                        </ul>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- FLOATING ACTION BUTTON -->
    <a href="faskes_tambah.php" class="bpjs-fab" title="Tambah Faskes" data-toggle="tooltip">
        <i class="mdi mdi-plus"></i>
    </a>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-delete me-2 text-danger"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="mdi mdi-alert-circle-outline text-warning" style="font-size: 64px;"></i>
                    </div>
                    <h6>Apakah Anda yakin ingin menghapus data ini?</h6>
                    <p class="text-muted mb-2">Faskes: <strong id="deleteNama"></strong></p>
                    <p class="text-danger small mb-0">Data yang dihapus tidak dapat dikembalikan</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <a href="#" id="deleteLink" class="btn btn-danger">
                        <i class="mdi mdi-delete mr-2"></i> Hapus Data
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT LOADING -->
    <script src="../assets/vendors/js/core.js"></script>
    <script src="../assets/js/template.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    // Confirm delete function
    function confirmDelete(id, nama) {
        document.getElementById('deleteNama').textContent = nama;
        document.getElementById('deleteLink').href = 'faskes_hapus.php?id=' + id;
        $('#deleteModal').modal('show');
    }

    // Initialize tooltips
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
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