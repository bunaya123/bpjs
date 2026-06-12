<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil ID obat dari parameter GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ID obat tidak valid");
}

// Ambil data obat
$sql = "SELECT * FROM obat WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$obat = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$obat) {
    die("Data obat tidak ditemukan");
}

// Ambil data user untuk sidebar
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// Cek foto profil
$has_custom_profile = false;
$profile_pic = '';
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

// Variabel untuk status
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

// Format tanggal
function formatDateIndonesian($date) {
    if (empty($date)) return '-';
    
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    return "$day $month $year $time";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Detail Obat - <?php echo htmlspecialchars($obat['nama_obat']); ?> - BPJS Kesehatan</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    :root {
        --bpjs-primary: #0066cc;
        --bpjs-primary-dark: #003366;
        --bpjs-secondary: #00a8ff;
        --bpjs-success: #28a745;
        --bpjs-warning: #ffc107;
        --bpjs-danger: #dc3545;
        --bpjs-info: #17a2b8;
    }
    
    body {
        background-color: #f5f7fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        overflow-x: hidden;
    }
    
    .detail-container {
        min-height: 100vh;
        background: #f5f7fa;
    }
    
    
    
    .back-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        border-radius: 8px;
        padding: 8px 20px;
        transition: all 0.3s;
    }
    
    .back-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        transform: translateY(-2px);
    }
    
    /* DETAIL CARD */
    .detail-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin: 30px auto;
        max-width: 1400px;
    }
    
    .detail-header {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
        color: white;
        padding: 30px;
        border-bottom: none;
    }
    
    .detail-header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .obat-avatar {
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: var(--bpjs-primary);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    
    .obat-info h1 {
        font-weight: 700;
        margin: 0;
        font-size: 2rem;
    }
    
    .obat-subtitle {
        opacity: 0.9;
        margin-top: 5px;
        font-size: 1rem;
    }
    
    .status-badges {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .status-badge {
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }
    
    .badge-success {
        background: linear-gradient(135deg, var(--bpjs-success) 0%, #20c997 100%);
    }
    
    .badge-warning {
        background: linear-gradient(135deg, var(--bpjs-warning) 0%, #fd7e14 100%);
    }
    
    .badge-danger {
        background: linear-gradient(135deg, var(--bpjs-danger) 0%, #c82333 100%);
    }
    
    .badge-info {
        background: linear-gradient(135deg, var(--bpjs-info) 0%, #138496 100%);
    }
    
    .badge-primary {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
    }
    
    /* CONTENT SECTIONS */
    .content-section {
        padding: 30px;
    }
    
    .section-title {
        color: var(--bpjs-primary);
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        font-size: 1.2rem;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .info-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 25px;
        border-left: 5px solid var(--bpjs-primary);
        transition: transform 0.3s;
    }
    
    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }
    
    .info-card h6 {
        color: var(--bpjs-primary);
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 1rem;
    }
    
    .info-row {
        margin-bottom: 15px;
    }
    
    .info-row:last-child {
        margin-bottom: 0;
    }
    
    .info-label {
        color: #6c757d;
        font-weight: 500;
        margin-bottom: 5px;
        font-size: 14px;
    }
    
    .info-value {
        font-size: 16px;
        font-weight: 500;
        color: #333;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-value-lg {
        font-size: 20px;
        font-weight: 600;
    }
    
    /* STOK DISPLAY */
    .stok-display {
        font-size: 32px;
        font-weight: 700;
        padding: 20px;
        border-radius: 15px;
        text-align: center;
        margin: 15px 0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .stok-tinggi { 
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border: 3px solid #b1dfbb;
    }
    
    .stok-rendah { 
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
        border: 3px solid #ffeaa7;
    }
    
    stok-habis { 
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border: 3px solid #f1b0b7;
    }
    
    /* HARGA DISPLAY */
    .harga-display {
        font-size: 28px;
        font-weight: 700;
        color: var(--bpjs-success);
        padding: 15px;
        background: linear-gradient(135deg, #f8fff9 0%, #e8f5e9 100%);
        border-radius: 12px;
        text-align: center;
        border: 2px solid #d4edda;
    }
    
    /* KETERANGAN SECTION */
    .keterangan-box {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 25px;
        border: 1px solid #e9ecef;
    }
    
    .keterangan-box p {
        margin: 0;
        line-height: 1.6;
        color: #495057;
    }
    
    /* TIMELINE */
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--bpjs-primary);
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 25px;
    }
    
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    
    .timeline-icon {
        position: absolute;
        left: -28px;
        top: 0;
        width: 24px;
        height: 24px;
        background: var(--bpjs-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        box-shadow: 0 0 0 3px white, 0 0 0 6px rgba(0, 115, 230, 0.2);
    }
    
    /* ACTION BUTTONS */
    .action-buttons {
        background: white;
        padding: 25px 30px;
        border-top: 1px solid #e9ecef;
        position: sticky;
        bottom: 0;
        z-index: 1020;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .btn-bpjs {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
        border: none;
        color: white;
        border-radius: 10px;
        padding: 12px 28px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(0, 115, 230, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-bpjs:hover {
        background: linear-gradient(135deg, var(--bpjs-primary-dark) 0%, var(--bpjs-primary) 100%);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 115, 230, 0.3);
    }
    
    .btn-bpjs-secondary {
        background: #f8f9fa;
        border: 2px solid var(--bpjs-primary);
        color: var(--bpjs-primary);
        border-radius: 10px;
        padding: 12px 28px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-bpjs-secondary:hover {
        background: var(--bpjs-primary);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 115, 230, 0.2);
    }
    
    .btn-bpjs-success {
        background: linear-gradient(135deg, var(--bpjs-success) 0%, #20c997 100%);
        border: none;
        color: white;
        border-radius: 10px;
        padding: 12px 28px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-bpjs-success:hover {
        background: linear-gradient(135deg, #20c997 0%, var(--bpjs-success) 100%);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
    }
    
    /* RESPONSIVE */
    @media (max-width: 992px) {
        .detail-header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .obat-info h1 {
            font-size: 1.8rem;
        }
        
        .status-badges {
            justify-content: center;
        }
    }
    
   
    
    @media (max-width: 576px) {
        .obat-avatar {
            width: 60px;
            height: 60px;
            font-size: 24px;
        }
        
        .status-badge {
            padding: 8px 15px;
            font-size: 12px;
        }
        
        .info-card {
            padding: 20px;
        }
    }
    
    /* ANIMATIONS */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease-out;
    }
    
    .sticky-header {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1000;
        padding: 15px 0;
        border-bottom: 1px solid #e9ecef;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    </style>
</head>
<body>
   

    <!-- MAIN CONTENT -->
    <div class="detail-container">
        <div class="detail-card fade-in">
            <!-- HEADER -->
            <div class="detail-header">
                <div class="detail-header-content">
                    <div class="d-flex align-items-center gap-4">
                        <div class="obat-avatar">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="obat-info">
                            <h1><?php echo htmlspecialchars($obat['nama_obat']); ?></h1>
                            <div class="obat-subtitle">
                                <i class="fas fa-barcode me-2"></i>Kode: <?php echo htmlspecialchars($obat['kode_obat']); ?>
                                <?php if (!empty($obat['jenis'])): ?>
                                    <span class="mx-3">•</span>
                                    <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($obat['jenis']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="status-badges">
                        <!-- Status Stok -->
                        <?php if ($is_stok_habis): ?>
                            <span class="status-badge badge-danger">
                                <i class="fas fa-exclamation-triangle"></i> Stok Habis
                            </span>
                        <?php elseif ($is_stok_rendah): ?>
                            <span class="status-badge badge-warning">
                                <i class="fas fa-exclamation-circle"></i> Stok Rendah
                            </span>
                        <?php else: ?>
                            <span class="status-badge badge-success">
                                <i class="fas fa-check-circle"></i> Stok Tersedia
                            </span>
                        <?php endif; ?>
                        
                        <!-- Status Expired -->
                        <?php if ($is_expired): ?>
                            <span class="status-badge badge-danger">
                                <i class="fas fa-calendar-times"></i> Expired
                            </span>
                        <?php elseif ($is_expiring_soon): ?>
                            <span class="status-badge badge-warning">
                                <i class="fas fa-clock"></i> Akan Expired
                            </span>
                        <?php elseif (!empty($obat['tanggal_expired'])): ?>
                            <span class="status-badge badge-success">
                                <i class="fas fa-calendar-check"></i> Masih Berlaku
                            </span>
                        <?php endif; ?>
                        
                        <!-- Status Obat -->
                        <span class="status-badge badge-<?php echo ($obat['status'] == 'Aktif') ? 'success' : 'danger'; ?>">
                            <i class="fas fa-<?php echo ($obat['status'] == 'Aktif') ? 'check' : 'times'; ?>-circle"></i>
                            <?php echo htmlspecialchars($obat['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- CONTENT -->
            <div class="content-section">
                <!-- INFORMASI DASAR -->
                <div class="mb-5">
                    <h4 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Informasi Dasar
                    </h4>
                    
                    <div class="info-grid">
                        <!-- Kode dan Nama -->
                        <div class="info-card">
                            <h6><i class="fas fa-id-card me-2"></i> Identitas Obat</h6>
                            <div class="info-row">
                                <div class="info-label">Kode Obat</div>
                                <div class="info-value">
                                    <i class="fas fa-barcode text-primary"></i>
                                    <span class="info-value-lg"><?php echo htmlspecialchars($obat['kode_obat']); ?></span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Nama Obat</div>
                                <div class="info-value">
                                    <i class="fas fa-pills text-primary"></i>
                                    <span><?php echo htmlspecialchars($obat['nama_obat']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Jenis dan Satuan -->
                        <div class="info-card">
                            <h6><i class="fas fa-tags me-2"></i> Klasifikasi</h6>
                            <div class="info-row">
                                <div class="info-label">Jenis Obat</div>
                                <div class="info-value">
                                    <i class="fas fa-tag text-info"></i>
                                    <span><?php echo !empty($obat['jenis']) ? htmlspecialchars($obat['jenis']) : 'Tidak ditentukan'; ?></span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Satuan</div>
                                <div class="info-value">
                                    <i class="fas fa-weight text-info"></i>
                                    <span><?php echo !empty($obat['satuan']) ? htmlspecialchars($obat['satuan']) : 'Tidak ditentukan'; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="info-card">
                            <h6><i class="fas fa-chart-line me-2"></i> Status</h6>
                            <div class="info-row">
                                <div class="info-label">Status Obat</div>
                                <div class="info-value">
                                    <i class="fas fa-<?php echo ($obat['status'] == 'Aktif') ? 'check' : 'times'; ?>-circle text-<?php echo ($obat['status'] == 'Aktif') ? 'success' : 'danger'; ?>"></i>
                                    <span class="fw-bold text-<?php echo ($obat['status'] == 'Aktif') ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($obat['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Terakhir Diupdate</div>
                                <div class="info-value">
                                    <i class="fas fa-calendar-alt text-secondary"></i>
                                    <span><?php echo formatDateIndonesian($obat['updated_at'] ?? $obat['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- STOK DAN HARGA -->
                <div class="mb-5">
                    <h4 class="section-title">
                        <i class="fas fa-warehouse"></i>
                        Stok & Harga
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="info-card h-100">
                                <h6><i class="fas fa-boxes me-2"></i> Stok Tersedia</h6>
                                <div class="stok-display <?php 
                                    echo $is_stok_habis ? 'stok-habis' : 
                                        ($is_stok_rendah ? 'stok-rendah' : 'stok-tinggi'); 
                                ?>">
                                    <?php echo number_format($obat['stok']); ?> <?php echo htmlspecialchars($obat['satuan'] ?? ''); ?>
                                </div>
                                <div class="mt-3 text-center">
                                    <?php if ($is_stok_habis): ?>
                                        <span class="text-danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Stok sudah habis, segera restock!
                                        </span>
                                    <?php elseif ($is_stok_rendah): ?>
                                        <span class="text-warning">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            Stok rendah, perhatikan!
                                        </span>
                                    <?php else: ?>
                                        <span class="text-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Stok mencukupi
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="info-card h-100">
                                <h6><i class="fas fa-money-bill-wave me-2"></i> Informasi Harga</h6>
                                <div class="harga-display mb-3">
                                    Rp <?php echo number_format($obat['harga'], 0, ',', '.'); ?>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Harga per <?php echo htmlspecialchars($obat['satuan'] ?? 'satuan'); ?></div>
                                    <div class="info-value">
                                        <i class="fas fa-dollar-sign text-success"></i>
                                        <span>Rp <?php echo number_format($obat['harga'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Total Nilai Stok</div>
                                    <div class="info-value">
                                        <i class="fas fa-calculator text-info"></i>
                                        <span class="fw-bold text-info">
                                            Rp <?php echo number_format($obat['stok'] * $obat['harga'], 0, ',', '.'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- INFORMASI EXPIRED -->
                <?php if (!empty($obat['tanggal_expired'])): ?>
                <div class="mb-5">
                    <h4 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Informasi Expired
                    </h4>
                    
                    <div class="info-card">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row mb-4">
                                    <div class="info-label">Tanggal Expired</div>
                                    <div class="info-value">
                                        <i class="fas fa-calendar <?php 
                                            echo $is_expired ? 'text-danger' : 
                                                ($is_expiring_soon ? 'text-warning' : 'text-success'); 
                                        ?>"></i>
                                        <span class="fw-bold"><?php echo date('d F Y', strtotime($obat['tanggal_expired'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!$is_expired): ?>
                                <div class="info-row">
                                    <div class="info-label">Sisa Hari</div>
                                    <div class="info-value">
                                        <i class="fas fa-clock <?php echo $is_expiring_soon ? 'text-warning' : 'text-success'; ?>"></i>
                                        <?php 
                                        $expired_date = strtotime($obat['tanggal_expired']);
                                        $current_date = time();
                                        $days_left = floor(($expired_date - $current_date) / (60 * 60 * 24));
                                        ?>
                                        <span class="fw-bold <?php echo $days_left <= 30 ? 'text-warning' : 'text-success'; ?>">
                                            <?php echo number_format($days_left); ?> hari lagi
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="alert alert-<?php 
                                    echo $is_expired ? 'danger' : 
                                        ($is_expiring_soon ? 'warning' : 'success'); 
                                ?> h-100 d-flex align-items-center">
                                    <div>
                                        <h5 class="alert-heading mb-3">
                                            <i class="fas fa-<?php 
                                                echo $is_expired ? 'exclamation-triangle' : 
                                                    ($is_expiring_soon ? 'clock' : 'check-circle'); 
                                            ?> me-2"></i>
                                            <?php 
                                            if ($is_expired) {
                                                echo 'OBAT SUDAH EXPIRED!';
                                            } elseif ($is_expiring_soon) {
                                                echo 'OBAT AKAN EXPIRED!';
                                            } else {
                                                echo 'OBAT MASIH BERLAKU';
                                            }
                                            ?>
                                        </h5>
                                        <p class="mb-2">
                                            <?php 
                                            if ($is_expired) {
                                                echo 'Obat ini sudah melewati tanggal expired. Tidak disarankan untuk digunakan.';
                                            } elseif ($is_expiring_soon) {
                                                echo "Obat akan expired dalam $days_left hari. Segera gunakan atau kelola stok.";
                                            } else {
                                                echo 'Obat masih dalam masa berlaku. Aman untuk digunakan.';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- KETERANGAN -->
                <?php if (!empty($obat['keterangan'])): ?>
                <div class="mb-5">
                    <h4 class="section-title">
                        <i class="fas fa-sticky-note"></i>
                        Keterangan Tambahan
                    </h4>
                    
                    <div class="keterangan-box">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-quote-left text-muted me-3 mt-1" style="font-size: 20px;"></i>
                            <div>
                                <?php echo nl2br(htmlspecialchars($obat['keterangan'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- RIWAYAT -->
                <div>
                    <h4 class="section-title">
                        <i class="fas fa-history"></i>
                        Riwayat Obat
                    </h4>
                    
                    <div class="info-card">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Dibuat</h6>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-calendar-plus me-2"></i>
                                        <?php echo formatDateIndonesian($obat['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if (!empty($obat['updated_at']) && $obat['updated_at'] != $obat['created_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Terakhir Diupdate</h6>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-calendar-edit me-2"></i>
                                        <?php echo formatDateIndonesian($obat['updated_at']); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($obat['tanggal_expired'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Tanggal Expired</h6>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        <?php echo date('d F Y', strtotime($obat['tanggal_expired'])); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ACTION BUTTONS -->
            <div class="action-buttons">
                <div class="container">
                    <div class="row justify-content-between">
                        <div class="col-auto">
                            <a href="obat.php" class="btn btn-bpjs-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Kembali
                            </a>
                        </div>
                        <div class="col-auto">
                            <div class="d-flex gap-3">
                                <a href="edit_obat.php?id=<?php echo $obat['id']; ?>" class="btn btn-bpjs">
                                    <i class="fas fa-edit me-2"></i> Edit Obat
                                </a>
                                <a href="obat.php" class="btn btn-bpjs-success">
                                    <i class="fas fa-list me-2"></i> Lihat Semua Obat
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Animasi saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        // Animasi untuk cards
        const cards = document.querySelectorAll('.info-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
        
        // Konfirmasi jika obat expired
        <?php if ($is_expired): ?>
        Swal.fire({
            icon: 'error',
            title: 'Perhatian!',
            html: 'Obat <strong><?php echo htmlspecialchars($obat['nama_obat']); ?></strong> sudah melewati tanggal expired.<br><br>Obat tidak disarankan untuk digunakan.',
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#dc3545'
        });
        <?php elseif ($is_expiring_soon): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Peringatan!',
            html: 'Obat <strong><?php echo htmlspecialchars($obat['nama_obat']); ?></strong> akan expired dalam <strong><?php echo $days_left; ?> hari</strong>.<br><br>Segera gunakan atau kelola stok.',
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#ffc107'
        });
        <?php endif; ?>
        
        <?php if ($is_stok_habis): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Stok Habis!',
            html: 'Stok obat <strong><?php echo htmlspecialchars($obat['nama_obat']); ?></strong> sudah habis.<br><br>Segera lakukan restock.',
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#dc3545'
        });
        <?php endif; ?>
    });
    
    // Print fungsi
    function printDetail() {
        window.print();
    }
    
    // Share fungsi
    function shareDetail() {
        if (navigator.share) {
            navigator.share({
                title: 'Detail Obat: <?php echo htmlspecialchars($obat['nama_obat']); ?>',
                text: 'Lihat detail obat <?php echo htmlspecialchars($obat['nama_obat']); ?> di BPJS Kesehatan',
                url: window.location.href
            });
        } else {
            // Fallback untuk browser yang tidak support Web Share API
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Link disalin!',
                    text: 'Link detail obat telah disalin ke clipboard',
                    timer: 2000
                });
            });
        }
    }
    </script>
</body>
</html>