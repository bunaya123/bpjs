
<?php
    session_start();
    require_once 'config.php';

    // Cek login
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

    // Tanggal default
    $default_date = date('Y-m-d');
    $default_year = date('Y');

    // Variabel mode (new, edit, view)
    $mode = 'new'; // default: pendaftaran baru
    $pendaftaran_id = null;
    $pendaftaran_data = null;

    // Cek apakah ada parameter id (untuk edit atau view)
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $pendaftaran_id = intval($_GET['id']);
        
        // Ambil data pendaftaran dari database
        $sql_pendaftaran = "SELECT * FROM pendaftaran WHERE id = ?";
        $stmt_pendaftaran = mysqli_prepare($conn, $sql_pendaftaran);
        mysqli_stmt_bind_param($stmt_pendaftaran, "i", $pendaftaran_id);
        mysqli_stmt_execute($stmt_pendaftaran);
        $result_pendaftaran = mysqli_stmt_get_result($stmt_pendaftaran);
        
        if (mysqli_num_rows($result_pendaftaran) > 0) {
            $pendaftaran_data = mysqli_fetch_assoc($result_pendaftaran);
            
            // Tentukan mode berdasarkan parameter
            if (isset($_GET['mode'])) {
                $mode = $_GET['mode']; // 'edit' atau 'view'
            } else {
                $mode = 'view'; // default ke view jika ada id
            }
        } else {
            // Data tidak ditemukan, kembali ke mode new
            $mode = 'new';
        }
        mysqli_stmt_close($stmt_pendaftaran);
    }

    // Cek aksi simpan dari POST
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'save') {
            // Proses simpan data pendaftaran
            require_once 'proses_simpan_pendaftaran.php'; // File terpisah untuk handle save
            exit();
        } elseif ($action == 'update') {
            // Proses update data pendaftaran
            require_once 'proses_update_pendaftaran.php'; // File terpisah untuk handle update
            exit();
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        
        <!-- Judul dinamis berdasarkan mode -->
        <title>
            <?php 
            if ($mode == 'edit'): 
                echo 'Edit Pendaftaran - ' . htmlspecialchars($user['username']);
            elseif ($mode == 'view'):
                echo 'Detail Pendaftaran - ' . htmlspecialchars($user['username']);
            else:
                echo 'Pendaftaran BPJS - ' . htmlspecialchars($user['username']);
            endif;
            ?>
        </title>
        
        <!-- CSS DARI DASHBOARD -->
        <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
        <link rel="stylesheet" href="../assets/css/shared/style.css">
        <link rel="stylesheet" href="../assets/css/demo_1/style.css">
        <link rel="shortcut icon" href="../assets/images/favicon.ico">
        
        <!-- DataTables CSS -->
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
        
        <!-- STYLE KHUSUS PENDAFTARAN -->
        <style>
        /* TEMA BPJS - SAMA SEPERTI KELAS.PHP */
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
        
        /* FOTO PROFIL SIDEBAR - SAMA SEPERTI KELAS.PHP */
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
        
        /* HEADER BPJS */
        .t-header {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
        }
        
        /* PAGE HEADER */
        .page-header-bpjs {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--bpjs-primary);
        }
        
        .page-header-bpjs h1 {
            color: var(--bpjs-primary);
            font-weight: 600;
            margin: 0;
            font-size: 24px;
        }
        
        .page-header-bpjs p {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        /* CARD HEADER CUSTOM */
        .card-header-custom {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
        }
        
        /* BUTTON STYLES */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            border: none;
        }
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #0066b3 0%, #0098cc 100%);
            box-shadow: 0 4px 12px rgba(0, 119, 200, 0.3);
        }
        
        /* BADGE STATUS */
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
        }
        
        .status-draft { background: #ffc107; color: #000; }
        .status-pending { background: var(--bpjs-info); color: white; }
        .status-approved { background: var(--bpjs-success); color: white; }
        .status-rejected { background: var(--bpjs-danger); color: white; }
        .status-active { background: var(--bpjs-primary); color: white; }
        .status-inactive { background: #6c757d; color: white; }
        
        /* KELAS BADGE */
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
        
        /* STAT CARD */
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
        
        /* CUSTOM COLORS */
        .text-bpjs-primary {
            color: var(--bpjs-primary) !important;
        }
        .text-bpjs-secondary {
            color: var(--bpjs-secondary) !important;
        }
        
        /* CARD KHUSUS PENDAFTARAN */
        .card-registration {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 115, 230, 0.1);
            border-top: 4px solid var(--bpjs-primary);
            margin-bottom: 25px;
            background: white;
        }
        
        .card-registration .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 115, 230, 0.1);
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
        }
        
        .card-registration .card-header h4 {
            color: var(--bpjs-primary);
            font-weight: 600;
            margin: 0;
            font-size: 18px;
        }
        
        .card-registration .card-header h4 i {
            margin-right: 10px;
        }
        
        /* DETAIL CARD */
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
        
        /* STEPS INDICATOR */
        .steps-container {
            margin-bottom: 30px;
            padding: 15px 0;
        }
        
        .steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            width: 80%;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .step.active .step-circle {
            background: var(--bpjs-primary);
            color: white;
            transform: scale(1.1);
        }
        
        .step-text {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-align: center;
        }
        
        .step.active .step-text {
            color: var(--bpjs-primary);
            font-weight: 600;
        }
        
        /* PLAN CARD */
        .plan-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            height: 100%;
            background: white;
        }
        
        .plan-card:hover {
            border-color: var(--bpjs-primary);
            box-shadow: 0 5px 15px rgba(0, 115, 230, 0.1);
        }
        
        .plan-card.selected {
            border-color: var(--bpjs-primary);
            background: rgba(0, 119, 200, 0.1);
        }
        
        .plan-title {
            font-weight: 600;
            color: var(--bpjs-primary);
            margin-bottom: 10px;
            text-align: center;
        }
        
        .plan-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--bpjs-primary);
            text-align: center;
            margin: 10px 0;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 15px 0 0 0;
        }
        
        .plan-features li {
            padding: 4px 0;
            font-size: 13px;
        }
        
        .plan-features li i {
            color: #28a745;
            margin-right: 5px;
        }
        
        /* SUMMARY BOX */
        .summary-box {
            background: rgba(0, 119, 200, 0.1);
            border-radius: 8px;
            padding: 20px;
            border: 1px solid rgba(0, 119, 200, 0.2);
        }
        
        .summary-title {
            color: var(--bpjs-primary);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 115, 230, 0.1);
            font-size: 14px;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 16px;
            color: var(--bpjs-primary);
            padding-top: 12px;
        }
        
        /* BUTTON STYLES */
        .btn-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            border: none;
            color: white;
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-bpjs:hover {
            background: linear-gradient(135deg, #0066b3 0%, #0098cc 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 119, 200, 0.3);
        }
        
        .btn-bpjs-outline {
            border: 2px solid var(--bpjs-primary);
            color: var(--bpjs-primary);
            background: transparent;
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-bpjs-outline:hover {
            background: var(--bpjs-primary);
            color: white;
        }
        
        .btn-bpjs-success {
            background: var(--bpjs-success);
            border: none;
            color: white;
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-bpjs-success:hover {
            background: #218838;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
        }
        
        .btn-bpjs-warning {
            background: var(--bpjs-warning);
            border: none;
            color: #000;
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-bpjs-warning:hover {
            background: #e0a800;
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
        }
        
        /* FORM STYLES */
        .form-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 5px;
            font-size: 14px;
            display: block;
        }
        
        .required-field::after {
            content: " *";
            color: var(--bpjs-danger);
        }
        
        .form-control:focus {
            border-color: var(--bpjs-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 200, 0.25);
        }
        
        /* READONLY STYLE */
        .form-control[readonly], 
        .form-control[disabled] {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            cursor: not-allowed;
        }
        
        /* VIEW MODE STYLE */
        .view-mode .form-control {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            cursor: not-allowed;
        }
        
        .view-mode .plan-card {
            cursor: default;
        }
        
        .view-mode .plan-card:hover {
            border-color: #ddd;
            box-shadow: none;
        }
        
        /* ALERT */
        .alert-bpjs {
            background: rgba(0, 119, 200, 0.1);
            border: 1px solid rgba(0, 119, 200, 0.2);
            color: var(--bpjs-primary);
            border-radius: 6px;
            padding: 15px;
        }
        
        .alert-bpjs h6 {
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .alert-bpjs ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .alert-bpjs ul li {
            margin-bottom: 5px;
        }
        
        /* INFO BOX */
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid var(--bpjs-primary);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 4px 4px 0;
        }
        
        .info-box h6 {
            color: var(--bpjs-primary);
            margin-bottom: 10px;
        }
        
        /* FASILITAS LIST */
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
        
        /* LOGO BPJS */
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
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .step-text {
                font-size: 11px;
            }
            
            .plan-card {
                margin-bottom: 15px;
            }
            
            .page-header-bpjs h1 {
                font-size: 20px;
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
        
        <div class="sidebar-upgrade-banner" style="background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%); color: white;">
            <p class="text-white">BPJS Kesehatan Member</p>
            <a class="btn upgrade-btn" href="pendaftaran.php" style="background: white; color: var(--bpjs-primary);">Register Now</a>
        </div>
    </div>

    <div class="page-content-wrapper">
        <div class="page-content-wrapper-inner">
            <div class="content-viewport" style="padding: 20px;">
                
                <!-- PAGE HEADER -->
                <div class="page-header-bpjs">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <!-- Judul dinamis berdasarkan mode -->
                            <h1>
                                <i class="mdi 
                                    <?php 
                                    if ($mode == 'edit'): 
                                        echo 'mdi-pencil text-warning';
                                    elseif ($mode == 'view'):
                                        echo 'mdi-eye text-info';
                                    else:
                                        echo 'mdi-account-plus text-primary';
                                    endif;
                                    ?> 
                                    mr-2">
                                </i>
                                <?php 
                                if ($mode == 'edit'): 
                                    echo 'Edit Pendaftaran Peserta';
                                elseif ($mode == 'view'):
                                    echo 'Detail Pendaftaran Peserta';
                                else:
                                    echo 'Pendaftaran Peserta BPJS';
                                endif;
                                ?>
                            </h1>
                            
                            <p class="text-muted mb-0">
                                <?php 
                                if ($mode == 'edit'): 
                                    echo 'Formulir edit data pendaftaran peserta BPJS';
                                elseif ($mode == 'view'):
                                    echo 'Detail data pendaftaran peserta BPJS';
                                else:
                                    echo 'Formulir pendaftaran peserta BPJS Kesehatan baru';
                                endif;
                                
                                // Tampilkan nomor pendaftaran jika ada
                                if ($pendaftaran_data && isset($pendaftaran_data['no_pendaftaran'])) {
                                    echo ' | No. Pendaftaran: <strong>' . htmlspecialchars($pendaftaran_data['no_pendaftaran']) . '</strong>';
                                }
                                ?>
                            </p>
                            
                            <!-- Tampilkan status jika ada -->
                            <?php if ($pendaftaran_data && isset($pendaftaran_data['status'])): ?>
                            <div class="mt-2">
                                <span class="status-badge status-<?php echo strtolower($pendaftaran_data['status']); ?>">
                                    <?php echo htmlspecialchars($pendaftaran_data['status']); ?>
                                </span>
                                <?php if ($pendaftaran_data['status'] == 'approved' && isset($pendaftaran_data['no_bpjs'])): ?>
                                    <span class="ml-2 badge badge-success">No. BPJS: <?php echo htmlspecialchars($pendaftaran_data['no_bpjs']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- TOMBOL AKSI BERDASARKAN MODE -->
                        <div>
                            <?php if ($mode == 'view' && $pendaftaran_data): ?>
                                <button type="button" class="btn btn-bpjs-outline mr-2" onclick="window.print()">
                                    <i class="mdi mdi-printer mr-1"></i> Cetak
                                </button>
                                <a href="pendaftaran.php?mode=edit&id=<?php echo $pendaftaran_id; ?>" class="btn btn-bpjs-warning mr-2">
                                    <i class="mdi mdi-pencil mr-1"></i> Edit
                                </a>
                                <a href="pendaftaran.php" class="btn btn-primary-custom">
                                    <i class="mdi mdi-plus mr-1"></i> Baru
                                </a>
                            <?php elseif ($mode == 'edit' && $pendaftaran_data): ?>
                                <button type="button" class="btn btn-bpjs-outline mr-2" onclick="window.location.href='pendaftaran.php?mode=view&id=<?php echo $pendaftaran_id; ?>'">
                                    <i class="mdi mdi-eye mr-1"></i> Lihat
                                </button>
                                <button type="button" class="btn btn-bpjs-warning" onclick="resetForm()">
                                    <i class="mdi mdi-refresh mr-1"></i> Reset
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-bpjs-outline mr-2" onclick="window.print()">
                                    <i class="mdi mdi-printer mr-1"></i> Cetak
                                </button>
                                <button type="button" class="btn btn-bpjs-outline" onclick="resetForm()">
                                    <i class="mdi mdi-refresh mr-1"></i> Reset Form
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- STATISTICS CARDS (SAMA SEPERTI KELAS.PHP) -->
                <?php if ($mode == 'view' || $mode == 'edit'): ?>
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 col-sm-6 col-6 equel-grid">
                        <div class="grid stat-card">
                            <div class="grid-body text-gray">
                                <div class="d-flex justify-content-between">
                                    <p>
                                        <?php 
                                        // Hitung total pendaftaran
                                        $sql_total = "SELECT COUNT(*) as total FROM pendaftaran";
                                        $result_total = mysqli_query($conn, $sql_total);
                                        $total_pendaftaran = mysqli_fetch_assoc($result_total)['total'];
                                        echo $total_pendaftaran;
                                        ?>
                                    </p>
                                    <p class="text-primary">Total</p>
                                </div>
                                <p class="text-black">Total Pendaftaran</p>
                                <div class="wrapper w-50 mt-4">
                                    <div class="text-center">
                                        <i class="mdi mdi-account-plus mdi-3x text-primary"></i>
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
                                        $sql_pending = "SELECT COUNT(*) as total FROM pendaftaran WHERE status = 'pending'";
                                        $result_pending = mysqli_query($conn, $sql_pending);
                                        $total_pending = mysqli_fetch_assoc($result_pending)['total'];
                                        echo $total_pending;
                                        ?>
                                    </p>
                                    <p class="text-warning">Pending</p>
                                </div>
                                <p class="text-black">Menunggu Verifikasi</p>
                                <div class="wrapper w-50 mt-4">
                                    <div class="text-center">
                                        <i class="mdi mdi-clock mdi-3x text-warning"></i>
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
                                        $sql_approved = "SELECT COUNT(*) as total FROM pendaftaran WHERE status = 'approved'";
                                        $result_approved = mysqli_query($conn, $sql_approved);
                                        $total_approved = mysqli_fetch_assoc($result_approved)['total'];
                                        echo $total_approved;
                                        ?>
                                    </p>
                                    <p class="text-success">Disetujui</p>
                                </div>
                                <p class="text-black">Pendaftaran Disetujui</p>
                                <div class="wrapper w-50 mt-4">
                                    <div class="text-center">
                                        <i class="mdi mdi-check-circle mdi-3x text-success"></i>
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
                                        $sql_rejected = "SELECT COUNT(*) as total FROM pendaftaran WHERE status = 'rejected'";
                                        $result_rejected = mysqli_query($conn, $sql_rejected);
                                        $total_rejected = mysqli_fetch_assoc($result_rejected)['total'];
                                        echo $total_rejected;
                                        ?>
                                    </p>
                                    <p class="text-danger">Ditolak</p>
                                </div>
                                <p class="text-black">Pendaftaran Ditolak</p>
                                <div class="wrapper w-50 mt-4">
                                    <div class="text-center">
                                        <i class="mdi mdi-close-circle mdi-3x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- INFO BOX UNTUK EDIT/VIEW MODE -->
                <?php if ($mode == 'edit' || $mode == 'view'): ?>
                <div class="info-box mb-4">
                    <h6><i class="mdi mdi-information-outline mr-2"></i> Informasi</h6>
                    <p class="mb-0">
                        <?php if ($mode == 'edit'): ?>
                            Anda sedang mengedit data pendaftaran. Pastikan perubahan yang dilakukan sudah benar sebelum menyimpan.
                        <?php else: ?>
                            Mode tampilan data. Untuk mengubah data, klik tombol "Edit" di atas.
                        <?php endif; ?>
                        
                        <?php if ($pendaftaran_data && isset($pendaftaran_data['updated_at'])): ?>
                            <br><small class="text-muted">Terakhir diupdate: <?php echo date('d/m/Y H:i', strtotime($pendaftaran_data['updated_at'])); ?></small>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- STEPS INDICATOR -->
                <?php if ($mode != 'view'): ?>
                <div class="card card-registration mb-4">
                    <div class="card-body">
                        <div class="steps-container">
                            <div class="steps">
                                <div class="step active" id="step1">
                                    <div class="step-circle">1</div>
                                    <div class="step-text">Data Diri</div>
                                </div>
                                <div class="step" id="step2">
                                    <div class="step-circle">2</div>
                                    <div class="step-text">Kelas & Faskes</div>
                                </div>
                                <div class="step" id="step3">
                                    <div class="step-circle">3</div>
                                    <div class="step-text">Pembayaran</div>
                                </div>
                                <div class="step" id="step4">
                                    <div class="step-circle">4</div>
                                    <div class="step-text">Konfirmasi</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FORM PENDAFTARAN -->
                <form id="pendaftaranForm" method="POST" action="<?php echo $mode == 'edit' ? 'pendaftaran.php' : 'pendaftaran.php'; ?>">
                    <!-- Input hidden untuk mode dan id -->
                    <input type="hidden" name="action" value="<?php echo $mode == 'edit' ? 'update' : 'save'; ?>">
                    <input type="hidden" name="pendaftaran_id" value="<?php echo $pendaftaran_id ?: ''; ?>">
                    
                    <!-- STEP 1: DATA DIRI -->
                    <div class="card card-registration mb-4" id="step1Content">
                        <div class="card-header">
                            <h4><i class="mdi mdi-account-card-details text-primary mr-2"></i> Data Diri Peserta</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">Nama Lengkap</label>
                                    <input type="text" class="form-control" name="nama" 
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['nama']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required-field">NIK</label>
                                    <input type="text" class="form-control" name="nik" maxlength="16"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['nik']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Tempat Lahir</label>
                                    <input type="text" class="form-control" name="tempat_lahir"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['tempat_lahir']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Tanggal Lahir</label>
                                    <input type="date" class="form-control" name="tanggal_lahir" max="<?php echo $default_date; ?>"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['tanggal_lahir']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Jenis Kelamin</label>
                                    <select class="form-control" name="jenis_kelamin" <?php echo $mode == 'view' ? 'disabled' : 'required'; ?>>
                                        <option value="">Pilih</option>
                                        <option value="L" <?php echo ($pendaftaran_data && $pendaftaran_data['jenis_kelamin'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                        <option value="P" <?php echo ($pendaftaran_data && $pendaftaran_data['jenis_kelamin'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Alamat</label>
                                    <textarea class="form-control" name="alamat" rows="2" <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                        <?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['alamat']) : ''; ?>
                                    </textarea>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Provinsi</label>
                                    <input type="text" class="form-control" name="provinsi"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['provinsi']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Kota/Kabupaten</label>
                                    <input type="text" class="form-control" name="kota"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['kota']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">No. Telepon</label>
                                    <input type="tel" class="form-control" name="no_telepon"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['no_telepon']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" name="email"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['email']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : 'required'; ?>>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Pekerjaan</label>
                                    <input type="text" class="form-control" name="pekerjaan"
                                        value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['pekerjaan']) : ''; ?>"
                                        <?php echo $mode == 'view' ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                            
                            <?php if ($mode != 'view'): ?>
                            <div class="text-right">
                                <button type="button" class="btn btn-bpjs" onclick="nextStep(2)">
                                    Selanjutnya <i class="mdi mdi-arrow-right ml-1"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 2: KELAS & FASKES -->
                    <div class="card card-registration mb-4" id="step2Content" style="<?php echo $mode == 'new' ? 'display: none;' : ''; ?>">
                        <div class="card-header">
                            <h4><i class="mdi mdi-hospital-building text-primary mr-2"></i> Pilih Kelas & Faskes</h4>
                        </div>
                        <div class="card-body">
                            <!-- PILIH KELAS -->
                            <div class="mb-4">
                                <h5 class="form-label required-field mb-3">Pilih Kelas BPJS</h5>
                                <div class="row">
                                    <?php
                                    // Data kelas
                                    $kelas_options = [
                                        1 => ['name' => 'Kelas 3', 'price' => 40000],
                                        2 => ['name' => 'Kelas 2', 'price' => 100000],
                                        3 => ['name' => 'Kelas 1', 'price' => 150000]
                                    ];
                                    
                                    $selected_kelas_id = $pendaftaran_data ? $pendaftaran_data['kelas_id'] : 1;
                                    $selected_kelas_price = $pendaftaran_data ? $pendaftaran_data['iuran_bulanan'] : 35000;
                                    ?>
                                    
                                    <?php foreach ($kelas_options as $id => $kelas): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="plan-card <?php echo ($selected_kelas_id == $id) ? 'selected' : ''; ?>"
                                            <?php if ($mode != 'view'): ?>onclick="selectPlan(<?php echo $id; ?>, '<?php echo $kelas['name']; ?>', <?php echo $kelas['price']; ?>)"<?php endif; ?>>
                                            <h5 class="plan-title"><?php echo $kelas['name']; ?></h5>
                                            <div class="plan-price">Rp <?php echo number_format($kelas['price'], 0, ',', '.'); ?></div>
                                            <small class="text-muted d-block text-center mb-3">per bulan</small>
                                            <ul class="plan-features">
                                                <?php if ($id == 1): ?>
                                                <li><i class="mdi mdi-check"></i> Kamar 3-4 pasien</li>
                                                <li><i class="mdi mdi-check"></i> Dokter umum</li>
                                                <li><i class="mdi mdi-check"></i> Obat generik</li>
                                                <?php elseif ($id == 2): ?>
                                                <li><i class="mdi mdi-check"></i> Kamar 2-3 pasien</li>
                                                <li><i class="mdi mdi-check"></i> Dokter spesialis</li>
                                                <li><i class="mdi mdi-check"></i> Obat paten</li>
                                                <?php else: ?>
                                                <li><i class="mdi mdi-check"></i> Kamar VIP</li>
                                                <li><i class="mdi mdi-check"></i> Dokter konsultan</li>
                                                <li><i class="mdi mdi-check"></i> Obat terbaik</li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="kelas_id" id="kelas_id" value="<?php echo $selected_kelas_id; ?>">
                                <input type="hidden" name="nama_kelas" id="nama_kelas" value="<?php echo $kelas_options[$selected_kelas_id]['name']; ?>">
                            </div>
                            
                            <!-- PILIH FASKES -->
                            <div>
                                <h5 class="form-label required-field mb-3">Pilih Faskes Tingkat 1</h5>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Fasilitas Kesehatan</label>
                                        <select class="form-control" name="faskes_id" <?php echo $mode == 'view' ? 'disabled' : 'required'; ?>>
                                            <option value="">Pilih Faskes</option>
                                            <?php
                                            // Data faskes contoh
                                            $faskes_options = [
                                                1 => 'RS Umum Daerah',
                                                2 => 'Puskesmas Central',
                                                3 => 'Klinik BPJS',
                                                4 => 'RS Mitra Sehat',
                                                5 => 'Puskesmas Pembantu'
                                            ];
                                            
                                            foreach ($faskes_options as $id => $name):
                                            ?>
                                            <option value="<?php echo $id; ?>" 
                                                <?php echo ($pendaftaran_data && $pendaftaran_data['faskes_id'] == $id) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Masa Berlaku</label>
                                        <select class="form-control" name="tahun_berlaku" <?php echo $mode == 'view' ? 'disabled' : ''; ?>>
                                            <?php 
                                            $selected_year = $pendaftaran_data ? $pendaftaran_data['tahun_berlaku'] : date('Y');
                                            for($i = 0; $i <= 5; $i++): 
                                                $year = date('Y') + $i;
                                            ?>
                                                <option value="<?php echo $year; ?>" 
                                                    <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                                    <?php echo $year; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($mode != 'view'): ?>
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-bpjs-outline" onclick="prevStep(1)">
                                    <i class="mdi mdi-arrow-left mr-1"></i> Sebelumnya
                                </button>
                                <button type="button" class="btn btn-bpjs" onclick="nextStep(3)">
                                    Selanjutnya <i class="mdi mdi-arrow-right ml-1"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 3: PEMBAYARAN -->
                    <div class="card card-registration mb-4" id="step3Content" style="display: none;">
                        <div class="card-header">
                            <h4><i class="mdi mdi-credit-card-outline text-primary mr-2"></i> Informasi Pembayaran</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- METODE PEMBAYARAN -->
                                    <div class="mb-3">
                                        <label class="form-label required-field">Metode Pembayaran</label>
                                        <select class="form-control" name="metode_pembayaran" 
                                            <?php echo $mode == 'view' ? 'disabled' : 'required'; ?> 
                                            onchange="showPaymentFields(this.value)">
                                            <option value="">Pilih Metode</option>
                                            <?php
                                            $payment_methods = [
                                                'transfer' => 'Transfer Bank',
                                                'kredit' => 'Kartu Kredit',
                                                'debit' => 'Kartu Debit',
                                                'tunai' => 'Tunai'
                                            ];
                                            
                                            $selected_method = $pendaftaran_data ? $pendaftaran_data['metode_pembayaran'] : '';
                                            
                                            foreach ($payment_methods as $value => $label):
                                            ?>
                                            <option value="<?php echo $value; ?>" 
                                                <?php echo ($selected_method == $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- FIELDS BANK -->
                                    <div id="bankFields" style="display: <?php echo ($selected_method == 'transfer') ? 'block' : 'none'; ?>;">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Bank</label>
                                            <input type="text" class="form-control" name="nama_bank" 
                                                value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['nama_bank']) : ''; ?>"
                                                <?php echo $mode == 'view' ? 'readonly' : ''; ?>
                                                placeholder="Contoh: BCA">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">No. Rekening</label>
                                            <input type="text" class="form-control" name="no_rekening"
                                                value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['no_rekening']) : ''; ?>"
                                                <?php echo $mode == 'view' ? 'readonly' : ''; ?>
                                                placeholder="No. Rekening">
                                        </div>
                                    </div>
                                    
                                    <!-- FIELDS KARTU -->
                                    <div id="cardFields" style="display: <?php echo ($selected_method == 'kredit' || $selected_method == 'debit') ? 'block' : 'none'; ?>;">
                                        <div class="mb-3">
                                            <label class="form-label">No. Kartu</label>
                                            <input type="text" class="form-control" name="no_kartu_kredit"
                                                value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['no_kartu_kredit']) : ''; ?>"
                                                <?php echo $mode == 'view' ? 'readonly' : ''; ?>
                                                placeholder="xxxx-xxxx-xxxx-xxxx">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nama di Kartu</label>
                                            <input type="text" class="form-control" name="nama_kartu"
                                                value="<?php echo $pendaftaran_data ? htmlspecialchars($pendaftaran_data['nama_kartu']) : ''; ?>"
                                                <?php echo $mode == 'view' ? 'readonly' : ''; ?>
                                                placeholder="Nama di kartu">
                                        </div>
                                    </div>
                                    
                                    <!-- UPLOAD BUKTI -->
                                    <?php if ($mode != 'view'): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Upload Bukti Pembayaran</label>
                                        <input type="file" class="form-control" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.pdf">
                                        <small class="text-muted">Format: JPG, PNG, PDF (Max 2MB)</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- TAMPILKAN BUKTI JIKA ADA -->
                                    <?php if ($pendaftaran_data && $pendaftaran_data['bukti_pembayaran']): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Bukti Pembayaran:</label>
                                        <div>
                                            <a href="../uploads/<?php echo htmlspecialchars($pendaftaran_data['bukti_pembayaran']); ?>" 
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="mdi mdi-file-document"></i> Lihat Bukti
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- RINGKASAN BIAYA -->
                                <div class="col-md-6">
                                    <div class="summary-box">
                                        <h5 class="summary-title">Ringkasan Biaya</h5>
                                        <div class="summary-item">
                                            <span>Iuran Bulanan:</span>
                                            <span id="iuranText">Rp <?php echo number_format($selected_kelas_price, 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Biaya Admin:</span>
                                            <span>Rp 5.000</span>
                                        </div>
                                        <div class="summary-item">
                                            <span>PPN (10%):</span>
                                            <span id="ppnText">Rp <?php echo number_format($selected_kelas_price * 0.1, 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Total Pembayaran:</span>
                                            <span id="totalText">Rp <?php echo number_format($selected_kelas_price + 5000 + ($selected_kelas_price * 0.1), 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- STATUS PEMBAYARAN -->
                                    <div class="mt-4">
                                        <label class="form-label">Status Pembayaran</label>
                                        <select class="form-control" name="status_pembayaran" <?php echo $mode == 'view' ? 'disabled' : ''; ?>>
                                            <?php
                                            $payment_statuses = [
                                                'pending' => 'Pending',
                                                'paid' => 'Paid',
                                                'verified' => 'Verified',
                                                'failed' => 'Failed'
                                            ];
                                            
                                            $selected_status = $pendaftaran_data ? $pendaftaran_data['status_pembayaran'] : 'pending';
                                            
                                            foreach ($payment_statuses as $value => $label):
                                            ?>
                                            <option value="<?php echo $value; ?>" 
                                                <?php echo ($selected_status == $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($mode != 'view'): ?>
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-bpjs-outline" onclick="prevStep(2)">
                                    <i class="mdi mdi-arrow-left mr-1"></i> Sebelumnya
                                </button>
                                <button type="button" class="btn btn-bpjs" onclick="nextStep(4)">
                                    Selanjutnya <i class="mdi mdi-arrow-right ml-1"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 4: KONFIRMASI -->
                    <div class="card card-registration mb-4" id="step4Content" style="display: none;">
                        <div class="card-header">
                            <h4><i class="mdi mdi-check-circle text-primary mr-2"></i> Konfirmasi Pendaftaran</h4>
                        </div>
                        <div class="card-body">
                            <!-- INFORMASI PENTING -->
                            <div class="alert-bpjs mb-4">
                                <h6><i class="mdi mdi-information-outline mr-2"></i> Informasi Penting</h6>
                                <ul>
                                    <li>Pastikan data yang diisi sudah benar</li>
                                    <li>Bukti pembayaran akan diverifikasi dalam 1x24 jam</li>
                                    <li>Kartu peserta akan dikirimkan setelah verifikasi berhasil</li>
                                </ul>
                            </div>
                            
                            <!-- CHECKBOX SETUJU -->
                            <?php if ($mode != 'view'): ?>
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    Saya menyetujui syarat dan ketentuan BPJS Kesehatan
                                </label>
                            </div>
                            
                            <!-- TOMBOL -->
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-bpjs-outline" onclick="prevStep(3)">
                                    <i class="mdi mdi-arrow-left mr-1"></i> Sebelumnya
                                </button>
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="mdi mdi-check-circle mr-1"></i> 
                                    <?php echo $mode == 'edit' ? 'Update Data' : 'Daftarkan Peserta'; ?>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    

<!-- SCRIPTS DARI DASHBOARD -->
<script src="../assets/vendors/js/core.js"></script>
<script src="../assets/js/template.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
let currentStep = 1;
let selectedPlanPrice = <?php echo $selected_kelas_price; ?>;

// Set step awal berdasarkan mode
<?php if ($mode == 'view' || $mode == 'edit'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Tampilkan semua step untuk mode view/edit
        document.getElementById('step1Content').style.display = 'block';
        document.getElementById('step2Content').style.display = 'block';
        document.getElementById('step3Content').style.display = 'block';
        document.getElementById('step4Content').style.display = 'block';
        
        // Update steps indicator untuk edit mode
        <?php if ($mode == 'edit'): ?>
            document.getElementById('step1').classList.add('active');
            document.getElementById('step2').classList.add('active');
            document.getElementById('step3').classList.add('active');
            document.getElementById('step4').classList.add('active');
        <?php endif; ?>
    });
<?php endif; ?>

// FUNGSI UNTUK BERPINDAH STEP
function nextStep(step) {
    // Validasi step saat ini
    if (!validateStep(currentStep)) {
        return;
    }
    
    // Sembunyikan step saat ini
    document.getElementById(`step${currentStep}Content`).style.display = 'none';
    document.getElementById(`step${currentStep}`).classList.remove('active');
    
    // Tampilkan step berikutnya
    document.getElementById(`step${step}Content`).style.display = 'block';
    document.getElementById(`step${step}`).classList.add('active');
    
    // Update current step
    currentStep = step;
    
    // Scroll ke atas
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevStep(step) {
    // Sembunyikan step saat ini
    document.getElementById(`step${currentStep}Content`).style.display = 'none';
    document.getElementById(`step${currentStep}`).classList.remove('active');
    
    // Tampilkan step sebelumnya
    document.getElementById(`step${step}Content`).style.display = 'block';
    document.getElementById(`step${step}`).classList.add('active');
    
    // Update current step
    currentStep = step;
    
    // Scroll ke atas
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// FUNGSI VALIDASI
function validateStep(step) {
    let isValid = true;
    
    if (step === 1) {
        // Validasi data diri
        const fields = ['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'provinsi', 'kota', 'no_telepon', 'email'];
        
        fields.forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
                
                // Validasi khusus
                if (fieldName === 'nik' && field.value.length !== 16) {
                    alert('NIK harus 16 digit!');
                    field.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (fieldName === 'email' && !isValidEmail(field.value)) {
                    alert('Format email tidak valid!');
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            }
        });
        
        if (!isValid) {
            alert('Harap lengkapi semua field yang wajib diisi!');
        }
    }
    
    if (step === 2) {
        // Validasi kelas dan faskes
        const faskesField = document.querySelector('[name="faskes_id"]');
        if (!faskesField.value) {
            faskesField.classList.add('is-invalid');
            alert('Harap pilih Fasilitas Kesehatan!');
            isValid = false;
        } else {
            faskesField.classList.remove('is-invalid');
        }
    }
    
    if (step === 3) {
        // Validasi pembayaran
        const paymentMethod = document.querySelector('[name="metode_pembayaran"]');
        if (!paymentMethod.value) {
            paymentMethod.classList.add('is-invalid');
            alert('Harap pilih metode pembayaran!');
            isValid = false;
        } else {
            paymentMethod.classList.remove('is-invalid');
        }
    }
    
    return isValid;
}

// VALIDASI EMAIL
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// FUNGSI PILIH KELAS
function selectPlan(planId, planName, price) {
    // Hapus class selected dari semua card
    document.querySelectorAll('.plan-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Tambah class selected ke card yang diklik
    event.currentTarget.classList.add('selected');
    
    // Update nilai
    document.getElementById('kelas_id').value = planId;
    document.getElementById('nama_kelas').value = planName;
    selectedPlanPrice = price;
    
    // Update ringkasan biaya
    updateSummary();
}

// UPDATE RINGKASAN BIAYA
function updateSummary() {
    const adminFee = 5000;
    const ppn = selectedPlanPrice * 0.1;
    const total = selectedPlanPrice + adminFee + ppn;
    
    document.getElementById('iuranText').textContent = 'Rp ' + selectedPlanPrice.toLocaleString('id-ID');
    document.getElementById('ppnText').textContent = 'Rp ' + Math.round(ppn).toLocaleString('id-ID');
    document.getElementById('totalText').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

// TAMPILKAN FIELD PEMBAYARAN
function showPaymentFields(method) {
    document.getElementById('bankFields').style.display = 'none';
    document.getElementById('cardFields').style.display = 'none';
    
    if (method === 'transfer') {
        document.getElementById('bankFields').style.display = 'block';
    } else if (method === 'kredit' || method === 'debit') {
        document.getElementById('cardFields').style.display = 'block';
    }
}

// RESET FORM
function resetForm() {
    <?php if ($mode == 'edit'): ?>
        if (confirm('Apakah Anda yakin ingin mereset perubahan?')) {
            location.reload();
        }
    <?php else: ?>
        if (confirm('Apakah Anda yakin ingin mereset seluruh form?')) {
            document.getElementById('pendaftaranForm').reset();
            
            // Reset pilihan kelas
            document.querySelectorAll('.plan-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector('.plan-card').classList.add('selected');
            document.getElementById('kelas_id').value = 1;
            document.getElementById('nama_kelas').value = 'Kelas 3';
            selectedPlanPrice = 35000;
            updateSummary();
            
            // Reset steps
            document.querySelectorAll('[id$="Content"]').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('.step').forEach(el => {
                el.classList.remove('active');
            });
            
            document.getElementById('step1Content').style.display = 'block';
            document.getElementById('step1').classList.add('active');
            currentStep = 1;
            
            // Sembunyikan field pembayaran
            document.getElementById('bankFields').style.display = 'none';
            document.getElementById('cardFields').style.display = 'none';
            
            // Hapus class validasi
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
        }
    <?php endif; ?>
}

// INISIALISASI
<?php if ($mode != 'view'): ?>
updateSummary();
<?php endif; ?>

// HANDLE FORM SUBMIT
document.getElementById('pendaftaranForm').addEventListener('submit', function(e) {
    <?php if ($mode != 'view'): ?>
    e.preventDefault();
    
    // Validasi semua step
    for (let i = 1; i <= 4; i++) {
        if (!validateStep(i)) {
            // Tampilkan step yang error
            document.querySelectorAll('[id$="Content"]').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('.step').forEach(el => {
                el.classList.remove('active');
            });
            
            document.getElementById(`step${i}Content`).style.display = 'block';
            document.getElementById(`step${i}`).classList.add('active');
            currentStep = i;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
    }
    
    // Validasi checkbox setuju
    if (!document.getElementById('agreeTerms').checked) {
        alert('Anda harus menyetujui syarat dan ketentuan!');
        document.getElementById('step4Content').scrollIntoView();
        return;
    }
    
    // Konfirmasi
    const action = '<?php echo $mode == 'edit' ? "update" : "save"; ?>';
    const message = action === 'update' 
        ? 'Apakah Anda yakin ingin mengupdate data pendaftaran ini?'
        : 'Apakah Anda yakin data yang diisi sudah benar dan ingin mendaftarkan peserta?';
    
    if (confirm(message)) {
        // Jika ada file upload, gunakan FormData
        const fileInput = document.querySelector('input[name="bukti_pembayaran"]');
        if (fileInput && fileInput.files.length > 0) {
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Data berhasil disimpan!');
                window.location.href = 'pendaftaran.php?mode=view&id=' + (data.id || '');
            })
            .catch(error => {
                alert('Terjadi kesalahan: ' + error);
            });
        } else {
            // Submit form biasa
            this.submit();
        }
    }
    <?php endif; ?>
});

// Auto show payment fields on load
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethod = document.querySelector('[name="metode_pembayaran"]');
    if (paymentMethod) {
        showPaymentFields(paymentMethod.value);
    }
});

// SESSION TIMEOUT NOTIFICATION - SAMA SEPERTI KELAS.PHP
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

// Auto refresh foto profil - SAMA SEPERTI KELAS.PHP
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