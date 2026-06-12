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

// Inisialisasi variabel
$success_msg = $error_msg = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Proses form tambah/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_kunjungan'])) {
    // Debug: Log data POST
    error_log("=== FORM SUBMIT KUNJUNGAN ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("Action: " . $action);
    
    // Sanitize input dengan validasi yang lebih baik
    $peserta_id = isset($_POST['peserta_id']) ? intval($_POST['peserta_id']) : 0;
    $faskes_id = isset($_POST['faskes_id']) ? intval($_POST['faskes_id']) : 0;
    
    // Perbaikan 1: Handle dokter_id dengan benar
    $dokter_id = (isset($_POST['dokter_id']) && $_POST['dokter_id'] !== '') ? intval($_POST['dokter_id']) : NULL;
    
    $tanggal_kunjungan = isset($_POST['tanggal_kunjungan']) ? mysqli_real_escape_string($conn, $_POST['tanggal_kunjungan']) : '';
    $jam_kunjungan = isset($_POST['jam_kunjungan']) ? mysqli_real_escape_string($conn, $_POST['jam_kunjungan']) : '';
    $jenis_pelayanan = isset($_POST['jenis_pelayanan']) ? mysqli_real_escape_string($conn, $_POST['jenis_pelayanan']) : '';
    
    // Perbaikan 2: Handle nilai kosong menjadi NULL
    $poli = (isset($_POST['poli']) && trim($_POST['poli']) !== '') ? mysqli_real_escape_string($conn, $_POST['poli']) : NULL;
    $diagnosis = (isset($_POST['diagnosis']) && trim($_POST['diagnosis']) !== '') ? mysqli_real_escape_string($conn, $_POST['diagnosis']) : NULL;
    $keluhan = (isset($_POST['keluhan']) && trim($_POST['keluhan']) !== '') ? mysqli_real_escape_string($conn, $_POST['keluhan']) : NULL;
    
    $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : '';
    $biaya_administrasi = isset($_POST['biaya_administrasi']) ? floatval($_POST['biaya_administrasi']) : 0;
    
    // Debug log
    error_log("Peserta ID: " . $peserta_id);
    error_log("Faskes ID: " . $faskes_id);
    error_log("Dokter ID: " . ($dokter_id ?? 'NULL'));
    error_log("Diagnosis: " . ($diagnosis ?? 'NULL'));
    error_log("Status: " . $status);
    
    // Validasi required fields
    $validation_errors = [];
    
    if ($peserta_id <= 0) {
        $validation_errors[] = "Peserta harus dipilih";
    }
    if ($faskes_id <= 0) {
        $validation_errors[] = "Faskes harus dipilih";
    }
    if (empty($tanggal_kunjungan)) {
        $validation_errors[] = "Tanggal kunjungan harus diisi";
    }
    if (empty($jam_kunjungan)) {
        $validation_errors[] = "Jam kunjungan harus diisi";
    }
    if (empty($jenis_pelayanan)) {
        $validation_errors[] = "Jenis pelayanan harus dipilih";
    }
    if (empty($status)) {
        $validation_errors[] = "Status kunjungan harus dipilih";
    }
    
    if (!empty($validation_errors)) {
        $error_msg = "Kesalahan: " . implode(", ", $validation_errors);
    } else {
        // Cek apakah ada ID untuk edit
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id > 0) {
            // PERBAIKAN 3: Query UPDATE dengan binding parameter yang benar
            $sql = "UPDATE kunjungan SET 
                    peserta_id = ?, 
                    faskes_id = ?, 
                    dokter_id = ?, 
                    tanggal_kunjungan = ?, 
                    jam_kunjungan = ?, 
                    jenis_pelayanan = ?,
                    poli = ?, 
                    diagnosis = ?, 
                    keluhan = ?, 
                    status = ?,
                    biaya_administrasi = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                // PERBAIKAN 4: Binding parameter dengan tipe yang tepat
                // 'i' = integer, 's' = string, 'd' = double
                // Urutan parameter: 
                // 1. peserta_id (i)
                // 2. faskes_id (i)
                // 3. dokter_id (s karena bisa NULL)
                // 4. tanggal_kunjungan (s)
                // 5. jam_kunjungan (s)
                // 6. jenis_pelayanan (s)
                // 7. poli (s)
                // 8. diagnosis (s)
                // 9. keluhan (s)
                // 10. status (s)
                // 11. biaya_administrasi (d)
                // 12. id (i)
                
                // Debug binding
                error_log("Binding parameters:");
                error_log("peserta_id: $peserta_id (i)");
                error_log("faskes_id: $faskes_id (i)");
                error_log("dokter_id: " . ($dokter_id ?? 'NULL') . " (s)");
                error_log("diagnosis: " . ($diagnosis ?? 'NULL') . " (s)");
                error_log("status: $status (s)");
                error_log("id: $id (i)");
                
                // Bind parameters - Perbaikan format string binding
                $bind_result = mysqli_stmt_bind_param($stmt, "iissssssssdi", 
                    $peserta_id,           // i
                    $faskes_id,            // i
                    $dokter_id,            // s (NULL akan dihandle sebagai string kosong)
                    $tanggal_kunjungan,    // s
                    $jam_kunjungan,        // s
                    $jenis_pelayanan,      // s
                    $poli,                 // s
                    $diagnosis,            // s
                    $keluhan,              // s
                    $status,               // s
                    $biaya_administrasi,   // d
                    $id                    // i
                );
                
                if (!$bind_result) {
                    $error_msg = "Gagal binding parameter: " . mysqli_error($conn);
                    error_log("Bind error: " . mysqli_error($conn));
                } elseif (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success'] = "Data kunjungan berhasil diperbarui!";
                    error_log("Update berhasil untuk ID: $id");
                    header("Location: kunjungan.php?action=edit&id=" . $id . "&success=1");
                    exit();
                } else {
                    $error_msg = "Gagal memperbarui data kunjungan: " . mysqli_error($conn);
                    error_log("Execute error: " . mysqli_error($conn));
                    error_log("SQL error: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_msg = "Error preparing statement: " . mysqli_error($conn);
                error_log("Prepare error: " . mysqli_error($conn));
            }
        } else {
            // Tambah kunjungan baru
            $sql = "INSERT INTO kunjungan 
                    (peserta_id, faskes_id, dokter_id, tanggal_kunjungan, 
                    jam_kunjungan, jenis_pelayanan, poli, diagnosis, keluhan, 
                    status, biaya_administrasi, user_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iissssssssdi", 
                    $peserta_id, 
                    $faskes_id, 
                    $dokter_id,
                    $tanggal_kunjungan, 
                    $jam_kunjungan, 
                    $jenis_pelayanan,
                    $poli, 
                    $diagnosis, 
                    $keluhan, 
                    $status,
                    $biaya_administrasi, 
                    $user_id
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $new_id = mysqli_insert_id($conn);
                    $_SESSION['success'] = "Kunjungan berhasil didaftarkan!";
                    header("Location: kunjungan.php?action=edit&id=" . $new_id . "&success=1");
                    exit();
                } else {
                    $error_msg = "Gagal menambahkan kunjungan: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_msg = "Error preparing statement: " . mysqli_error($conn);
            }
        }
    }
    
    // Jika ada error, simpan data POST ke session untuk prefilling
    if (!empty($error_msg)) {
        $_SESSION['form_data'] = $_POST;
    }
}

// Proses hapus
if (isset($_GET['delete']) && $id > 0) {
    $sql = "DELETE FROM kunjungan WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Data kunjungan berhasil dihapus!";
        header("Location: kunjungan.php?success=1");
        exit();
    } else {
        $error_msg = "Terjadi kesalahan saat menghapus data: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Cek jika ada pesan success dari session atau URL
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_GET['success'])) {
    $success_msg = "Operasi berhasil dilakukan!";
}

// Ambil data untuk edit
$kunjungan = null;
if (($action == 'edit' || $action == 'view') && $id > 0) {
    $sql = "SELECT k.*, 
            p.nama as peserta_nama, p.nik, p.no_kartu,
            f.nama_faskes as faskes_nama, f.alamat as faskes_alamat,
            d.nama_dokter as dokter_nama
            FROM kunjungan k
            LEFT JOIN peserta p ON k.peserta_id = p.id
            LEFT JOIN faskes f ON k.faskes_id = f.id
            LEFT JOIN dokter d ON k.dokter_id = d.id
            WHERE k.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $kunjungan = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Jika data tidak ditemukan, redirect ke list
    if (!$kunjungan && $action == 'edit') {
        header("Location: kunjungan.php");
        exit();
    }
    
    // PERBAIKAN 5: Handle nilai NULL untuk display
    if ($kunjungan) {
        $kunjungan['poli'] = $kunjungan['poli'] ?? '';
        $kunjungan['diagnosis'] = $kunjungan['diagnosis'] ?? '';
        $kunjungan['keluhan'] = $kunjungan['keluhan'] ?? '';
        $kunjungan['dokter_id'] = $kunjungan['dokter_id'] ?? '';
    }
}

// Ambil semua kunjungan untuk tabel
$sql_list = "SELECT k.*, 
             p.nama as peserta_nama, p.no_kartu,
             f.nama_faskes as faskes_nama,
             d.nama_dokter as dokter_nama
             FROM kunjungan k
             LEFT JOIN peserta p ON k.peserta_id = p.id
             LEFT JOIN faskes f ON k.faskes_id = f.id
             LEFT JOIN dokter d ON k.dokter_id = d.id
             ORDER BY k.tanggal_kunjungan DESC, k.jam_kunjungan DESC";
$result_list = mysqli_query($conn, $sql_list);

// Ambil data dropdown dengan reset pointer
$peserta_list = mysqli_query($conn, "SELECT id, nama, no_kartu, nik FROM peserta WHERE status = 'active' ORDER BY nama");
$faskes_list = mysqli_query($conn, "SELECT id, nama_faskes, alamat FROM faskes WHERE status = 'aktif' ORDER BY nama_faskes");
$dokter_list = mysqli_query($conn, "SELECT id, nama_dokter FROM dokter WHERE status = 'aktif' ORDER BY nama_dokter");

// Cek foto profil user
$profile_pic = $user['profile_pic'] ?? '';
$profile_path = 'uploads/profile_pics/' . $profile_pic;
$has_custom_profile = (!empty($profile_pic) && file_exists($profile_path));
$default_avatar = '../assets/images/faces/avatar-default.png';

// Hitung statistik
$total_kunjungan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan"))['total'] ?? 0;
$selesai_kunjungan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan WHERE status = 'selesai'"))['total'] ?? 0;
$diproses_kunjungan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan WHERE status = 'diproses'"))['total'] ?? 0;
$hari_ini_kunjungan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kunjungan WHERE tanggal_kunjungan = CURDATE()"))['total'] ?? 0;

// PERBAIKAN 6: Ambil data dari session jika ada (setelah submit error)
if (isset($_SESSION['form_data']) && $action == 'add') {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Data Kunjungan - BPJS</title>
    
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
        /* Custom styling untuk halaman kunjungan */
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
        
        .select2-custom {
            width: 100% !important;
        }
        
        .peserta-info, .peserta-detail {
            border-left: 4px solid #0066cc;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .card-header {
            border-radius: 8px 8px 0 0 !important;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-color: #e0e0e0;
        }
        
        /* Badge untuk jenis pelayanan */
        .badge-kunjungan {
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
        
        /* Badge untuk status */
        .status-badge {
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
        
        /* Statistik cards */
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card-total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card-selesai {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card-proses {
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
        }
        
        .stat-card-hariini {
            background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
        }
        
        /* Button styling */
        .btn-kunjungan {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-kunjungan:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Form styling */
        .form-control-kunjungan {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        
        .form-control-kunjungan:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        /* Table styling */
        .kunjungan-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .kunjungan-table td {
            vertical-align: middle;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .btn-lg {
                padding: 8px 16px;
                font-size: 14px;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
        }
        
        /* Hover effect for table rows */
        .kunjungan-table tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
        
        /* Action buttons */
        .action-buttons .btn {
            margin-right: 5px;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        /* Page header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        /* Foto profil yang diperbesar */
        .display-avatar .profile-img.img-lg {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        /* Custom card headers */
        .card-header-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .card-header-info {
            background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
            color: white;
        }
        
        .card-header-warning {
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            color: white;
        }
        
        .card-header-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        /* Required field indicator */
        .required::after {
            content: " *";
            color: red;
        }
        
        /* Error message styling */
        .alert-danger {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Success message styling */
        .alert-success {
            border-left: 4px solid #28a745;
            background-color: #d4edda;
            color: #155724;
        }
        
        /* Debug info */
        .debug-info {
            background-color: #f8f9fa;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
            color: #666;
        }
        
        /* Fix untuk select2 dengan nilai kosong */
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #6c757d;
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
                             onerror="this.style.display='none'; document.getElementById('avatar-default-kunjungan').style.display='block';">
                    <?php endif; ?>
                    
                    <img id="avatar-default-kunjungan" 
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
                <li class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['peserta_bpjs.php', 'faskes.php', 'dokter.php', 'obat.php', 'tindakan.php', 'kelas.php']) ? 'active' : ''; ?>">
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
                <li class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['pendaftaran.php', 'pembayaran.php', 'kunjungan.php', 'klaim.php', 'transfer_faskes.php', 'refund.php']) ? 'active' : ''; ?>">
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
                <li class="nav-item">
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
                    <!-- Notifikasi -->
                    <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle"></i> <?php echo $success_msg; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle"></i> <?php echo $error_msg; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="grid">
                                <div class="grid-body">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div>
                                            <h4 class="card-title mb-0">Data Kunjungan Peserta BPJS</h4>
                                            <p class="text-muted">Kelola data kunjungan peserta ke fasilitas kesehatan</p>
                                        </div>
                                        <a href="?action=add" class="btn btn-primary btn-kunjungan">
                                            <i class="mdi mdi-plus"></i> Tambah Kunjungan
                                        </a>
                                    </div>
                                    
                    <?php if ($action == 'add' || $action == 'edit'): ?>
                    <!-- Form Tambah/Edit Kunjungan -->
                    <div class="grid">
                        <div class="grid-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5>
                                    <i class="mdi mdi-<?php echo $action == 'edit' ? 'pencil' : 'plus'; ?> mr-2"></i>
                                    <?php echo $action == 'edit' ? 'Edit Data Kunjungan' : 'Tambah Kunjungan Baru'; ?>
                                    <?php if ($action == 'edit' && $kunjungan): ?>
                                    <small class="text-muted ml-2">ID: <?php echo $id; ?></small>
                                    <?php endif; ?>
                                </h5>
                                <a href="kunjungan.php" class="btn btn-secondary btn-sm">
                                    <i class="mdi mdi-arrow-left"></i> Kembali
                                </a>
                            </div>
                            
                            <?php if ($action == 'edit' && $kunjungan): ?>
                            <!-- Debug info untuk edit mode -->
                            <div class="debug-info mb-3">
                                <small>
                                    <i class="mdi mdi-information-outline"></i>
                                    <strong>Data saat ini:</strong> 
                                    Peserta: <?php echo htmlspecialchars($kunjungan['peserta_nama'] ?? 'Tidak ditemukan'); ?> | 
                                    Status: <?php echo htmlspecialchars($kunjungan['status'] ?? 'Tidak diketahui'); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="formKunjungan">
                                <?php if ($action == 'edit' && $kunjungan): ?>
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Peserta BPJS -->
                                        <div class="card mb-4">
                                            <div class="card-header card-header-primary">
                                                <h6 class="mb-0"><i class="mdi mdi-account-card-details mr-2"></i> Peserta BPJS</h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($action == 'edit' && $kunjungan): ?>
                                                    <!-- PERBAIKAN: Untuk edit, peserta_id harus ada di form -->
                                                    <div class="peserta-info mb-3 p-3 rounded">
                                                        <div class="row">
                                                            <div class="col-12 mb-2">
                                                                <strong>Nama:</strong> <?php echo htmlspecialchars($kunjungan['peserta_nama'] ?? '-'); ?>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <strong>NIK:</strong> <?php echo htmlspecialchars($kunjungan['nik'] ?? '-'); ?>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <strong>No. Kartu:</strong> <?php echo htmlspecialchars($kunjungan['no_kartu'] ?? '-'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="peserta_id" id="peserta_id" 
                                                           value="<?php echo $kunjungan['peserta_id'] ?? ''; ?>">
                                                <?php else: ?>
                                                    <div class="form-group">
                                                        <label for="peserta_id" class="font-weight-bold required">Pilih Peserta BPJS</label>
                                                        <select class="form-control form-control-kunjungan select2-custom" id="peserta_id" name="peserta_id" required>
                                                            <option value="">-- Pilih Peserta --</option>
                                                            <?php 
                                                            if ($peserta_list && mysqli_num_rows($peserta_list) > 0):
                                                                mysqli_data_seek($peserta_list, 0);
                                                                while ($peserta = mysqli_fetch_assoc($peserta_list)): 
                                                                    $selected = false;
                                                                    if (isset($form_data['peserta_id']) && $form_data['peserta_id'] == $peserta['id']) {
                                                                        $selected = true;
                                                                    } elseif ($action == 'edit' && $kunjungan && $kunjungan['peserta_id'] == $peserta['id']) {
                                                                        $selected = true;
                                                                    }
                                                            ?>
                                                            <option value="<?php echo $peserta['id']; ?>" 
                                                                    data-nik="<?php echo htmlspecialchars($peserta['nik'] ?? ''); ?>"
                                                                    data-nokartu="<?php echo htmlspecialchars($peserta['no_kartu'] ?? ''); ?>"
                                                                    <?php echo $selected ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($peserta['nama'] . ' - ' . $peserta['no_kartu']); ?>
                                                            </option>
                                                            <?php 
                                                                endwhile;
                                                            else: ?>
                                                            <option value="">Tidak ada peserta aktif</option>
                                                            <?php endif; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div id="peserta-detail" class="peserta-detail p-3 rounded mt-3" style="display: none;">
                                                        <h6 class="mb-3"><i class="mdi mdi-information-outline mr-2"></i>Detail Peserta</h6>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <strong>NIK:</strong> <span id="detail-nik">-</span>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <strong>No. Kartu:</strong> <span id="detail-nokartu">-</span>
                                                            </div>
                                                        </div>
                                                        <div class="mt-2">
                                                            <a href="peserta_bpjs.php?action=add" class="btn btn-sm btn-outline-primary">
                                                                <i class="mdi mdi-plus"></i> Tambah Peserta Baru
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Fasilitas Kesehatan -->
                                        <div class="form-group">
                                            <label for="faskes_id" class="font-weight-bold required">Fasilitas Kesehatan</label>
                                            <select class="form-control form-control-kunjungan select2-custom" id="faskes_id" name="faskes_id" required>
                                                <option value="">-- Pilih Faskes --</option>
                                                <?php 
                                                if ($faskes_list && mysqli_num_rows($faskes_list) > 0):
                                                    mysqli_data_seek($faskes_list, 0);
                                                    while ($faskes = mysqli_fetch_assoc($faskes_list)): 
                                                        $selected = false;
                                                        if (isset($form_data['faskes_id']) && $form_data['faskes_id'] == $faskes['id']) {
                                                            $selected = true;
                                                        } elseif ($action == 'edit' && $kunjungan && $kunjungan['faskes_id'] == $faskes['id']) {
                                                            $selected = true;
                                                        }
                                                ?>
                                                <option value="<?php echo $faskes['id']; ?>"
                                                    <?php echo $selected ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($faskes['nama_faskes']); ?>
                                                    <?php if (!empty($faskes['alamat'])): ?>
                                                        - <?php echo htmlspecialchars(substr($faskes['alamat'], 0, 30)); ?>...
                                                    <?php endif; ?>
                                                </option>
                                                <?php 
                                                    endwhile;
                                                else: ?>
                                                <option value="">Tidak ada data faskes</option>
                                                <?php endif; ?>
                                            </select>
                                            <?php if ($action == 'edit' && $kunjungan && !empty($kunjungan['faskes_alamat'])): ?>
                                                <small class="text-muted mt-1 d-block">
                                                    <i class="mdi mdi-map-marker"></i> Alamat: <?php echo htmlspecialchars($kunjungan['faskes_alamat']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Dokter -->
                                        <div class="form-group">
                                            <label for="dokter_id" class="font-weight-bold">Dokter (Opsional)</label>
                                            <select class="form-control form-control-kunjungan select2-custom" id="dokter_id" name="dokter_id">
                                                <option value="">-- Pilih Dokter --</option>
                                                <?php 
                                                if ($dokter_list && mysqli_num_rows($dokter_list) > 0):
                                                    mysqli_data_seek($dokter_list, 0);
                                                    while ($dokter = mysqli_fetch_assoc($dokter_list)): 
                                                        $selected = false;
                                                        if (isset($form_data['dokter_id']) && $form_data['dokter_id'] == $dokter['id']) {
                                                            $selected = true;
                                                        } elseif ($action == 'edit' && $kunjungan && !empty($kunjungan['dokter_id']) && $kunjungan['dokter_id'] == $dokter['id']) {
                                                            $selected = true;
                                                        }
                                                ?>
                                                <option value="<?php echo $dokter['id']; ?>"
                                                    <?php echo $selected ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dokter['nama_dokter']); ?>
                                                </option>
                                                <?php 
                                                    endwhile;
                                                else: ?>
                                                <option value="">Tidak ada data dokter</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Waktu Kunjungan -->
                                        <div class="card mb-4">
                                            <div class="card-header card-header-info">
                                                <h6 class="mb-0"><i class="mdi mdi-calendar-clock mr-2"></i> Waktu Kunjungan</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="tanggal_kunjungan" class="font-weight-bold required">Tanggal Kunjungan</label>
                                                            <?php
                                                            $tanggal_value = date('Y-m-d');
                                                            if (isset($form_data['tanggal_kunjungan'])) {
                                                                $tanggal_value = $form_data['tanggal_kunjungan'];
                                                            } elseif ($action == 'edit' && $kunjungan) {
                                                                $tanggal_value = $kunjungan['tanggal_kunjungan'];
                                                            }
                                                            ?>
                                                            <input type="date" class="form-control form-control-kunjungan" id="tanggal_kunjungan" 
                                                                   name="tanggal_kunjungan" required
                                                                   value="<?php echo $tanggal_value; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="jam_kunjungan" class="font-weight-bold required">Jam Kunjungan</label>
                                                            <?php
                                                            $jam_value = date('H:i');
                                                            if (isset($form_data['jam_kunjungan'])) {
                                                                $jam_value = $form_data['jam_kunjungan'];
                                                            } elseif ($action == 'edit' && $kunjungan) {
                                                                $jam_value = $kunjungan['jam_kunjungan'];
                                                            }
                                                            ?>
                                                            <input type="time" class="form-control form-control-kunjungan" id="jam_kunjungan" 
                                                                   name="jam_kunjungan" required
                                                                   value="<?php echo $jam_value; ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Jenis Pelayanan -->
                                        <div class="form-group">
                                            <label for="jenis_pelayanan" class="font-weight-bold required">Jenis Pelayanan</label>
                                            <?php
                                            $selected_jenis = '';
                                            if (isset($form_data['jenis_pelayanan'])) {
                                                $selected_jenis = $form_data['jenis_pelayanan'];
                                            } elseif ($action == 'edit' && $kunjungan) {
                                                $selected_jenis = $kunjungan['jenis_pelayanan'];
                                            }
                                            ?>
                                            <select class="form-control form-control-kunjungan" id="jenis_pelayanan" name="jenis_pelayanan" required>
                                                <option value="">-- Pilih Jenis Pelayanan --</option>
                                                <option value="rawat_jalan" <?php echo $selected_jenis == 'rawat_jalan' ? 'selected' : ''; ?>>Rawat Jalan</option>
                                                <option value="rawat_inap" <?php echo $selected_jenis == 'rawat_inap' ? 'selected' : ''; ?>>Rawat Inap</option>
                                                <option value="ugd" <?php echo $selected_jenis == 'ugd' ? 'selected' : ''; ?>>UGD</option>
                                                <option value="rutin" <?php echo $selected_jenis == 'rutin' ? 'selected' : ''; ?>>Kunjungan Rutin</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Poli/Bagian -->
                                        <div class="form-group">
                                            <label for="poli" class="font-weight-bold">Poli/Bagian (Opsional)</label>
                                            <?php
                                            $poli_value = '';
                                            if (isset($form_data['poli'])) {
                                                $poli_value = $form_data['poli'];
                                            } elseif ($action == 'edit' && $kunjungan) {
                                                $poli_value = $kunjungan['poli'] ?? '';
                                            }
                                            ?>
                                            <input type="text" class="form-control form-control-kunjungan" id="poli" 
                                                   name="poli" placeholder="Contoh: Umum, Gigi, Anak, Penyakit Dalam"
                                                   value="<?php echo htmlspecialchars($poli_value); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Status dan Biaya -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status" class="font-weight-bold required">Status Kunjungan</label>
                                            <?php
                                            $selected_status = '';
                                            if (isset($form_data['status'])) {
                                                $selected_status = $form_data['status'];
                                            } elseif ($action == 'edit' && $kunjungan) {
                                                $selected_status = $kunjungan['status'];
                                            }
                                            ?>
                                            <select class="form-control form-control-kunjungan" id="status" name="status" required>
                                                <option value="">-- Pilih Status --</option>
                                                <option value="terdaftar" <?php echo $selected_status == 'terdaftar' ? 'selected' : ''; ?>>Terdaftar</option>
                                                <option value="diproses" <?php echo $selected_status == 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                                <option value="selesai" <?php echo $selected_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                <option value="batal" <?php echo $selected_status == 'batal' ? 'selected' : ''; ?>>Batal</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="biaya_administrasi" class="font-weight-bold">Biaya Administrasi (Rp)</label>
                                            <?php
                                            $biaya_value = '0';
                                            if (isset($form_data['biaya_administrasi'])) {
                                                $biaya_value = $form_data['biaya_administrasi'];
                                            } elseif ($action == 'edit' && $kunjungan) {
                                                $biaya_value = $kunjungan['biaya_administrasi'] ?? '0';
                                            }
                                            ?>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">Rp</span>
                                                </div>
                                                <input type="number" class="form-control form-control-kunjungan" id="biaya_administrasi" 
                                                       name="biaya_administrasi" step="1000" min="0" placeholder="0"
                                                       value="<?php echo $biaya_value; ?>">
                                            </div>
                                            <small class="text-muted">Biaya administrasi untuk kunjungan ini</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informasi Medis -->
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header card-header-warning">
                                                <h6 class="mb-0"><i class="mdi mdi-stethoscope mr-2"></i> Informasi Medis</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="diagnosis" class="font-weight-bold">Diagnosis Awal (Opsional)</label>
                                                            <?php
                                                            $diagnosis_value = '';
                                                            if (isset($form_data['diagnosis'])) {
                                                                $diagnosis_value = $form_data['diagnosis'];
                                                            } elseif ($action == 'edit' && $kunjungan) {
                                                                $diagnosis_value = $kunjungan['diagnosis'] ?? '';
                                                            }
                                                            ?>
                                                            <input type="text" class="form-control form-control-kunjungan" id="diagnosis" 
                                                                   name="diagnosis" placeholder="Masukkan diagnosis awal"
                                                                   value="<?php echo htmlspecialchars($diagnosis_value); ?>">
                                                            <small class="text-muted">Diagnosa awal berdasarkan pemeriksaan</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="keluhan" class="font-weight-bold">Keluhan Pasien (Opsional)</label>
                                                            <?php
                                                            $keluhan_value = '';
                                                            if (isset($form_data['keluhan'])) {
                                                                $keluhan_value = $form_data['keluhan'];
                                                            } elseif ($action == 'edit' && $kunjungan) {
                                                                $keluhan_value = $kunjungan['keluhan'] ?? '';
                                                            }
                                                            ?>
                                                            <textarea class="form-control form-control-kunjungan" id="keluhan" name="keluhan" 
                                                                      rows="3" placeholder="Deskripsi keluhan pasien"><?php echo htmlspecialchars($keluhan_value); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tombol Aksi -->
                                <div class="mt-4 pt-3 border-top">
                                    <button type="submit" name="save_kunjungan" class="btn btn-success btn-lg">
                                        <i class="mdi mdi-content-save"></i> <?php echo $action == 'edit' ? 'Update Data' : 'Simpan Data Kunjungan'; ?>
                                    </button>
                                    <a href="kunjungan.php" class="btn btn-secondary btn-lg">
                                        <i class="mdi mdi-close"></i> Batal
                                    </a>
                                    <?php if ($action == 'edit'): ?>
                                    <a href="javascript:void(0)" onclick="cetakFormKunjungan(<?php echo $id; ?>)" class="btn btn-info btn-lg">
                                        <i class="mdi mdi-printer"></i> Cetak
                                    </a>
                                    
                                    <!-- Debug button -->
                                    <button type="button" class="btn btn-warning btn-lg" onclick="debugForm()">
                                        <i class="mdi mdi-bug"></i> Debug
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Statistik Kunjungan -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card stat-card-total">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Total Kunjungan</h6>
                                        <h3 class="mb-0"><?php echo $total_kunjungan; ?></h3>
                                    </div>
                                    <i class="mdi mdi-hospital-building mdi-3x"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card stat-card-selesai">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Selesai</h6>
                                        <h3 class="mb-0"><?php echo $selesai_kunjungan; ?></h3>
                                    </div>
                                    <i class="mdi mdi-check-circle mdi-3x"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card stat-card-proses">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Diproses</h6>
                                        <h3 class="mb-0"><?php echo $diproses_kunjungan; ?></h3>
                                    </div>
                                    <i class="mdi mdi-clock-outline mdi-3x"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card stat-card-hariini">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Hari Ini</h6>
                                        <h3 class="mb-0"><?php echo $hari_ini_kunjungan; ?></h3>
                                    </div>
                                    <i class="mdi mdi-calendar-today mdi-3x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabel Data Kunjungan -->
                    <div class="table-responsive">
                        <table id="dataTable" class="table table-striped table-bordered kunjungan-table">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th>Tanggal/Jam</th>
                                    <th>Peserta</th>
                                    <th>Faskes</th>
                                    <th>Jenis</th>
                                    <th>Diagnosa</th>
                                    <th>Status</th>
                                    <th width="150">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($result_list && mysqli_num_rows($result_list) > 0): 
                                $no = 1; 
                                ?>
                                <?php while ($row = mysqli_fetch_assoc($result_list)): ?>
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
                                            'rawat_jalan' => 'badge-kunjungan badge-rawat-jalan',
                                            'rawat_inap' => 'badge-kunjungan badge-rawat-inap',
                                            'ugd' => 'badge-kunjungan badge-ugd',
                                            'rutin' => 'badge-kunjungan badge-rutin'
                                        ];
                                        $jenis_text = [
                                            'rawat_jalan' => 'Rawat Jalan',
                                            'rawat_inap' => 'Rawat Inap',
                                            'ugd' => 'UGD',
                                            'rutin' => 'Rutin'
                                        ];
                                        ?>
                                        <span class="<?php echo $badge_class[$jenis] ?? 'badge badge-secondary'; ?>">
                                            <?php echo $jenis_text[$jenis] ?? $jenis; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $diagnosis = $row['diagnosis'] ?? '';
                                        echo htmlspecialchars(substr($diagnosis, 0, 20)); 
                                        if (strlen($diagnosis) > 20) echo '...';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $row['status'] ?? 'terdaftar';
                                        $status_class = [
                                            'terdaftar' => 'status-badge status-terdaftar',
                                            'diproses' => 'status-badge status-diproses',
                                            'selesai' => 'status-badge status-selesai',
                                            'batal' => 'status-badge status-batal'
                                        ];
                                        $status_text = [
                                            'terdaftar' => 'Terdaftar',
                                            'diproses' => 'Diproses',
                                            'selesai' => 'Selesai',
                                            'batal' => 'Batal'
                                        ];
                                        ?>
                                        <span class="<?php echo $status_class[$status] ?? 'badge badge-secondary'; ?>">
                                            <?php echo $status_text[$status] ?? $status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=edit&id=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-info" title="Edit">
                                                <i class="mdi mdi-pencil"></i>
                                            </a>
                                            <a href="?delete=1&id=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin menghapus data kunjungan ini?')" title="Hapus">
                                                <i class="mdi mdi-delete"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="cetakFormKunjungan(<?php echo $row['id']; ?>)" 
                                               class="btn btn-sm btn-warning" title="Cetak">
                                                <i class="mdi mdi-printer"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="py-5">
                                            <i class="mdi mdi-hospital-building mdi-3x text-muted mb-3"></i>
                                            <p class="text-muted">Belum ada data kunjungan</p>
                                            <a href="?action=add" class="btn btn-primary">
                                                <i class="mdi mdi-plus"></i> Tambah Kunjungan Pertama
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                                </div>
                            </div>
                        </div>
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
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- build:js -->
    <script src="../assets/js/template.js"></script>
    <!-- endbuild -->
    
    <script>
    $(document).ready(function() {
        // Inisialisasi DataTable
        if ($('#dataTable').length) {
            $('#dataTable').DataTable({
                "pageLength": 25,
                "order": [[0, 'desc']],
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
        
        // Inisialisasi Select2
        $('.select2-custom').select2({
            placeholder: "Pilih opsi",
            allowClear: true,
            width: '100%'
        });
        
        // Tampilkan detail peserta saat dipilih (hanya untuk mode add)
        <?php if ($action == 'add'): ?>
        $('#peserta_id').change(function() {
            var selectedOption = $(this).find('option:selected');
            var nik = selectedOption.data('nik');
            var nokartu = selectedOption.data('nokartu');
            
            if ($(this).val() && nik) {
                $('#detail-nik').text(nik || '-');
                $('#detail-nokartu').text(nokartu || '-');
                $('#peserta-detail').slideDown();
            } else {
                $('#peserta-detail').slideUp();
            }
        });
        
        // Trigger change jika sudah ada nilai
        if ($('#peserta_id').val()) {
            $('#peserta_id').trigger('change');
        }
        <?php endif; ?>
        
        // Fungsi cetak form kunjungan
        window.cetakFormKunjungan = function(id) {
            window.open('cetak_kunjungan.php?id=' + id, '_blank');
        };
        
        // Fungsi debug form
        window.debugForm = function() {
            var formData = $('#formKunjungan').serializeArray();
            console.log("=== DEBUG FORM DATA ===");
            console.log("Form Action: " + $('#formKunjungan').attr('action'));
            console.log("Form Method: " + $('#formKunjungan').attr('method'));
            console.log("Form Data:");
            formData.forEach(function(item) {
                console.log(item.name + ": " + item.value);
            });
            alert("Form data telah dicatat di console.\nBuka Developer Tools (F12) -> Console untuk melihat detail.");
        };
        
        // Validasi tanggal tidak boleh lebih dari hari ini
        $('#tanggal_kunjungan').change(function() {
            var selectedDate = new Date($(this).val());
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate > today) {
                alert('Tanggal kunjungan tidak boleh lebih dari hari ini!');
                $(this).val('<?php echo date("Y-m-d"); ?>');
            }
        });
        
        // Auto-fill poli berdasarkan jenis pelayanan
        $('#jenis_pelayanan').change(function() {
            var jenis = $(this).val();
            var poliInput = $('#poli');
            
            if (jenis === 'ugd' && !poliInput.val()) {
                poliInput.val('IGD (Instalasi Gawat Darurat)');
            } else if (jenis === 'rawat_inap' && !poliInput.val()) {
                poliInput.val('Rawat Inap Umum');
            } else if (jenis === 'rawat_jalan' && !poliInput.val()) {
                poliInput.val('Poli Umum');
            }
        });
        
        // Set tanggal default ke hari ini untuk form tambah
        if ($('#tanggal_kunjungan').val() === '' && '<?php echo $action; ?>' === 'add') {
            $('#tanggal_kunjungan').val('<?php echo date("Y-m-d"); ?>');
        }
        
        // Set jam default untuk form tambah
        if ($('#jam_kunjungan').val() === '' && '<?php echo $action; ?>' === 'add') {
            var now = new Date();
            var hours = now.getHours().toString().padStart(2, '0');
            var minutes = now.getMinutes().toString().padStart(2, '0');
            $('#jam_kunjungan').val(hours + ':' + minutes);
        }
        
        // Validasi form sebelum submit
        $('#formKunjungan').submit(function(e) {
            console.log("Form submission started...");
            
            var isValid = true;
            var errorMessages = [];
            
            // Cek required fields berdasarkan mode
            var mode = '<?php echo $action; ?>';
            
            // Cek peserta_id (cara berbeda untuk edit dan add)
            if (mode === 'add') {
                if (!$('#peserta_id').val()) {
                    errorMessages.push('Peserta harus dipilih!');
                    isValid = false;
                }
            } else {
                // Mode edit: peserta_id adalah hidden field
                var pesertaId = $('input[name="peserta_id"]').val();
                if (!pesertaId || pesertaId <= 0) {
                    errorMessages.push('Data peserta tidak valid!');
                    isValid = false;
                }
            }
            
            if (!$('#faskes_id').val()) {
                errorMessages.push('Faskes harus dipilih!');
                isValid = false;
            }
            
            if (!$('#tanggal_kunjungan').val()) {
                errorMessages.push('Tanggal kunjungan harus diisi!');
                isValid = false;
            }
            
            if (!$('#jam_kunjungan').val()) {
                errorMessages.push('Jam kunjungan harus diisi!');
                isValid = false;
            }
            
            if (!$('#jenis_pelayanan').val()) {
                errorMessages.push('Jenis pelayanan harus dipilih!');
                isValid = false;
            }
            
            if (!$('#status').val()) {
                errorMessages.push('Status kunjungan harus dipilih!');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                var errorMsg = 'Kesalahan:\n' + errorMessages.join('\n');
                console.error("Validation errors:", errorMessages);
                alert(errorMsg);
                return false;
            }
            
            console.log("Form validation passed. Submitting...");
            return true;
        });
        
        // Auto close alert setelah 5 detik
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        
        // Refresh foto profil jika baru diupdate
        if (document.referrer.includes('profile.php')) {
            const profileImages = document.querySelectorAll('.profile-img');
            profileImages.forEach(img => {
                const currentSrc = img.src;
                const newSrc = currentSrc.split('?')[0] + '?t=' + new Date().getTime();
                img.src = newSrc;
            });
        }
        
        // Debug: Tampilkan data form saat halaman load
        console.log("=== PAGE LOAD DATA ===");
        console.log("Action: <?php echo $action; ?>");
        console.log("ID: <?php echo $id; ?>");
        <?php if ($action == 'edit' && $kunjungan): ?>
        console.log("Kunjungan Data:", <?php echo json_encode($kunjungan); ?>);
        <?php endif; ?>
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