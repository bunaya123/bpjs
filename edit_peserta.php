<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Ambil ID dari parameter GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$peserta = null;

// Jika ada ID, ambil data peserta
if ($id > 0) {
    $sql = "SELECT * FROM peserta WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $peserta = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $nik = mysqli_real_escape_string($conn, $_POST['nik']);
    $no_kartu = mysqli_real_escape_string($conn, $_POST['no_kartu']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $tempat_lahir = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $faskes = mysqli_real_escape_string($conn, $_POST['faskes']);
    $kelas_bpjs = mysqli_real_escape_string($conn, $_POST['kelas_bpjs']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Fitur baru: Gaji dilaporkan
    $gaji_dilaporkan = isset($_POST['gaji_dilaporkan']) ? 
        str_replace('.', '', mysqli_real_escape_string($conn, $_POST['gaji_dilaporkan'])) : 
        '0';
    
    // Fitur baru: Segmen Peserta (PPU, PBPU, PBI)
    $segmen_peserta = isset($_POST['segmen_peserta']) ? 
        mysqli_real_escape_string($conn, $_POST['segmen_peserta']) : 
        'PBI'; // Default PBI
    
    // Pastikan gaji adalah angka
    if (!is_numeric($gaji_dilaporkan)) {
        $gaji_dilaporkan = 0;
    }
    
    if ($id > 0) {
        // Update data dengan segmen peserta
        $sql = "UPDATE peserta SET 
                nama = ?, nik = ?, no_kartu = ?, jenis_kelamin = ?, 
                tempat_lahir = ?, tanggal_lahir = ?, alamat = ?, 
                no_telepon = ?, email = ?, faskes = ?, kelas_bpjs = ?, 
                status = ?, gaji_dilaporkan = ?, segmen_peserta = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssssssssi", 
            $nama, $nik, $no_kartu, $jenis_kelamin,
            $tempat_lahir, $tanggal_lahir, $alamat,
            $no_telepon, $email, $faskes, $kelas_bpjs,
            $status, $gaji_dilaporkan, $segmen_peserta, $id);
    } else {
        // Insert data baru dengan segmen peserta
        $sql = "INSERT INTO peserta (nama, nik, no_kartu, jenis_kelamin, 
                tempat_lahir, tanggal_lahir, alamat, no_telepon, email, 
                faskes, kelas_bpjs, status, gaji_dilaporkan, segmen_peserta, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssssssss", 
            $nama, $nik, $no_kartu, $jenis_kelamin,
            $tempat_lahir, $tanggal_lahir, $alamat,
            $no_telepon, $email, $faskes, $kelas_bpjs,
            $status, $gaji_dilaporkan, $segmen_peserta);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $pesan = $id > 0 ? "Data berhasil diperbarui!" : "Data berhasil ditambahkan!";
        $_SESSION['success'] = $pesan;
        
        // Redirect berdasarkan action
        if (isset($_POST['action']) && $_POST['action'] == 'save_and_new') {
            header("Location: edit_peserta.php");
        } else {
            header("Location: peserta_bpjs.php");
        }
        exit();
    } else {
        $error = "Gagal menyimpan data: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
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

// Jika edit mode, ambil data tambahan untuk ditampilkan
if ($id > 0 && $peserta) {
    // Format tanggal untuk ditampilkan
    $peserta['tgl_lahir_format'] = date('d F Y', strtotime($peserta['tanggal_lahir']));
    $peserta['bergabung_format'] = date('d F Y H:i', strtotime($peserta['created_at']));
    
    // Format gaji dilaporkan
    $peserta['gaji_format'] = 'Rp ' . number_format($peserta['gaji_dilaporkan'] ?? 0, 0, ',', '.');
    $peserta['gaji_form_input'] = isset($peserta['gaji_dilaporkan']) && $peserta['gaji_dilaporkan'] > 0 ? 
        number_format($peserta['gaji_dilaporkan'], 0, ',', '.') : '0';
    
    // Status badge configuration
    $status_config = [
        'active' => ['success', 'Aktif', 'fas fa-check-circle'],
        'inactive' => ['danger', 'Non-Aktif', 'fas fa-times-circle'],
        'pending' => ['warning', 'Pending', 'fas fa-clock']
    ];
    $peserta['status_info'] = $status_config[$peserta['status']] ?? $status_config['pending'];
    
    // Segmen peserta configuration
    $segmen_config = [
        'PPU' => ['primary', 'Pekerja Penerima Upah', 'fas fa-briefcase'],
        'PBPU' => ['info', 'Pekerja Bukan Penerima Upah', 'fas fa-user-tie'],
        'PBI' => ['warning', 'Penerima Bantuan Iuran', 'fas fa-hands-helping']
    ];
    $peserta['segmen_info'] = $segmen_config[$peserta['segmen_peserta']] ?? $segmen_config['PBI'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $id > 0 ? 'Edit' : 'Tambah'; ?> Peserta - BPJS Kesehatan</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
    :root {
        --bpjs-primary: #0073e6;
        --bpjs-primary-dark: #0056b3;
        --bpjs-secondary: #00a8ff;
    }
    
    body {
        background-color: #f5f7fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .form-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        background: white;
        overflow: hidden;
    }
    
    .form-header {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
        color: white;
        padding: 20px 30px;
        border-bottom: none;
    }
    
    .form-section {
        padding: 30px;
    }
    
    .form-group-custom {
        margin-bottom: 20px;
    }
    
    .form-label-custom {
        font-weight: 500;
        margin-bottom: 8px;
        color: #495057;
        font-size: 14px;
    }
    
    .form-control-custom {
        border: 1px solid #ced4da;
        border-radius: 8px;
        padding: 10px 15px;
        font-size: 15px;
        transition: all 0.3s;
    }
    
    .form-control-custom:focus {
        border-color: var(--bpjs-primary);
        box-shadow: 0 0 0 0.2rem rgba(0, 115, 230, 0.25);
    }
    
    .required-label::after {
        content: " *";
        color: #dc3545;
    }
    
    .btn-action {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .btn-save {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
        border: none;
        color: white;
    }
    
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 115, 230, 0.3);
        color: white;
    }
    
    .btn-cancel {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
    }
    
    .btn-cancel:hover {
        background: #e9ecef;
        color: #495057;
    }
    
    /* Styles from detail_peserta.php */
    .info-section {
        padding: 25px 30px;
    }
    
    .info-group {
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid #eee;
    }
    
    .info-group:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
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
    }
    
    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 14px;
    }
    
    .gender-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
        color: white;
    }
    
    .gender-male {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }
    
    .gender-female {
        background: linear-gradient(135deg, #e83e8c 0%, #c2185b 100%);
    }
    
    .back-btn {
        background: transparent;
        border: 2px solid white;
        color: white;
        border-radius: 8px;
        padding: 8px 16px;
        transition: all 0.3s;
    }
    
    .back-btn:hover {
        background: white;
        color: var(--bpjs-primary);
    }
    
    /* Quick Actions Styling */
    .quick-actions {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-top: 20px;
    }
    
    .quick-actions-title {
        font-weight: 600;
        color: #495057;
        margin-bottom: 15px;
        font-size: 16px;
    }
    
    .btn-quick-action {
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        transition: all 0.3s;
        border: 1px solid #dee2e6;
        background: white;
        color: #495057;
        text-decoration: none;
    }
    
    .btn-quick-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        text-decoration: none;
    }
    
    .btn-payment {
        border-color: #28a745;
        color: #28a745;
    }
    
    .btn-payment:hover {
        background: #28a745;
        color: white;
    }
    
    .btn-detail {
        border-color: #17a2b8;
        color: #17a2b8;
    }
    
    .btn-detail:hover {
        background: #17a2b8;
        color: white;
    }
    
    /* Salary specific styles */
    .gaji-badge {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 5px 12px;
        border-radius: 15px;
        font-weight: 500;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
    }
    
    .salary-input-group .input-group-text {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: 1px solid #ced4da;
        border-right: none;
        color: white;
        font-weight: 500;
    }
    
    .salary-input-group .form-control-custom {
        border-left: none;
        padding-left: 10px;
    }
    
    .gaji-display {
        font-size: 18px;
        font-weight: 600;
        color: #28a745;
    }
    
    /* Segmen Peserta Styles */
    .segmen-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 14px;
    }
    
    .segmen-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        border: 2px solid transparent;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .segmen-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .segmen-card.active {
        border-color: var(--bpjs-primary);
        background: rgba(0, 115, 230, 0.05);
    }
    
    .segmen-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: white;
        margin-right: 15px;
    }
    
    .segmen-icon-ppu {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }
    
    .segmen-icon-pbpu {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    }
    
    .segmen-icon-pbi {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    }
    
    .segmen-info {
        flex: 1;
    }
    
    .segmen-title {
        font-weight: 600;
        margin-bottom: 3px;
        font-size: 16px;
    }
    
    .segmen-desc {
        color: #6c757d;
        font-size: 13px;
        margin-bottom: 0;
    }
    
    /* Radio button customization */
    .segmen-radio {
        display: none;
    }
    
    .segmen-radio:checked + .segmen-card {
        border-color: var(--bpjs-primary);
        background: rgba(0, 115, 230, 0.05);
    }
    
    .segmen-details {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-top: 10px;
        font-size: 14px;
        color: #495057;
        display: none;
    }
    
    .segmen-details.active {
        display: block;
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <div class="bg-white rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-heartbeat text-primary"></i>
            </div>
            <div>
                <span class="fw-bold">BPJS Kesehatan</span><br>
                <small class="opacity-75"><?php echo $id > 0 ? 'Edit Peserta' : 'Tambah Peserta'; ?></small>
            </div>
        </a>
        <div class="navbar-nav ms-auto">
            <a href="peserta_bpjs.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i> Kembali
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <!-- Notifikasi -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Tampilkan Informasi Peserta (Hanya untuk mode edit) -->
            <?php if ($id > 0 && $peserta): ?>
            <div class="form-card mb-4">
                <!-- Header Informasi -->
                <div class="form-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="gender-icon <?php echo $peserta['jenis_kelamin'] == 'L' ? 'gender-male' : 'gender-female'; ?> me-3">
                                <?php echo $peserta['jenis_kelamin'] == 'L' ? 'L' : 'P'; ?>
                            </div>
                            <div>
                                <h3 class="mb-1"><?php echo htmlspecialchars($peserta['nama']); ?></h3>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-id-card me-1"></i> No Kartu: <?php echo htmlspecialchars($peserta['no_kartu']); ?>
                                    <?php if (isset($peserta['gaji_dilaporkan']) && $peserta['gaji_dilaporkan'] > 0): ?>
                                    <span class="ms-3">
                                        <i class="fas fa-money-bill-wave me-1"></i> Gaji: <?php echo $peserta['gaji_format']; ?>
                                    </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <!-- Segmen Badge -->
                            <span class="badge segmen-badge bg-<?php echo $peserta['segmen_info'][0]; ?>">
                                <i class="<?php echo $peserta['segmen_info'][2]; ?> me-1"></i> <?php echo $peserta['segmen_info'][1]; ?>
                            </span>
                            
                            <!-- Status Badge -->
                            <span class="badge status-badge bg-<?php echo $peserta['status_info'][0]; ?>">
                                <i class="<?php echo $peserta['status_info'][2]; ?> me-1"></i> <?php echo $peserta['status_info'][1]; ?>
                            </span>
                            
                            <!-- Quick Actions -->
                            <div class="d-flex gap-2">
                                <a href="tambah_pembayaran.php?peserta_id=<?php echo $id; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus-circle me-1"></i> Bayar
                                </a>
                                <a href="detail_peserta.php?id=<?php echo $id; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye me-1"></i> Detail
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ringkasan Informasi -->
                <div class="info-section">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="mb-3">
                                    <div class="info-label">NIK</div>
                                    <div class="info-value"><?php echo htmlspecialchars($peserta['nik']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Tanggal Lahir</div>
                                    <div class="info-value"><?php echo $peserta['tgl_lahir_format']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Tanggal Bergabung</div>
                                    <div class="info-value"><?php echo $peserta['bergabung_format']; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="mb-3">
                                    <div class="info-label">Segmen Peserta</div>
                                    <div class="info-value">
                                        <span class="badge bg-<?php echo $peserta['segmen_info'][0]; ?>">
                                            <i class="<?php echo $peserta['segmen_info'][2]; ?> me-1"></i> <?php echo $peserta['segmen_info'][1]; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">No. Telepon</div>
                                    <div class="info-value">
                                        <i class="fas fa-phone me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($peserta['no_telepon'] ?? '-'); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Email</div>
                                    <div class="info-value">
                                        <i class="fas fa-envelope me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($peserta['email'] ?? '-'); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Gaji Dilaporkan</div>
                                    <div class="info-value gaji-display">
                                        <i class="fas fa-money-bill-wave me-2 text-success"></i>
                                        <?php echo $peserta['gaji_format']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Access Bar -->
                    
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-card">
                <!-- Header -->
                <div class="form-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-1">
                                <i class="fas <?php echo $id > 0 ? 'fa-edit' : 'fa-user-plus'; ?> me-2"></i>
                                <?php echo $id > 0 ? 'Edit Data Peserta' : 'Tambah Peserta Baru'; ?>
                            </h3>
                            <p class="mb-0 opacity-75">
                                <?php echo $id > 0 ? 'Perbarui informasi peserta BPJS Kesehatan' : 'Isi data peserta BPJS Kesehatan baru'; ?>
                            </p>
                        </div>
                        <?php if ($id > 0): ?>
                            <span class="badge bg-light text-primary px-3 py-2">
                                <i class="fas fa-user me-1"></i> ID: <?php echo $id; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Form -->
                <form method="POST" action="" class="form-section">
                    <div class="row">
                        <!-- Kolom 1 -->
                        <div class="col-md-6">
                            <!-- Informasi Pribadi -->
                            <div class="mb-4">
                                <h5 class="mb-3"><i class="fas fa-user me-2 text-primary"></i> Informasi Pribadi</h5>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">Nama Lengkap</label>
                                    <input type="text" class="form-control form-control-custom" 
                                           name="nama" 
                                           value="<?php echo htmlspecialchars($peserta['nama'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">NIK</label>
                                    <input type="text" class="form-control form-control-custom" 
                                           name="nik" 
                                           value="<?php echo htmlspecialchars($peserta['nik'] ?? ''); ?>"
                                           pattern="[0-9]{16}" 
                                           title="NIK harus 16 digit angka"
                                           required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom required-label">Jenis Kelamin</label>
                                            <select class="form-select form-control-custom" name="jenis_kelamin" required>
                                                <option value="">Pilih Jenis Kelamin</option>
                                                <option value="L" <?php echo ($peserta['jenis_kelamin'] ?? '') == 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                                <option value="P" <?php echo ($peserta['jenis_kelamin'] ?? '') == 'P' ? 'selected' : ''; ?>>Perempuan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Tempat Lahir</label>
                                            <input type="text" class="form-control form-control-custom" 
                                                   name="tempat_lahir" 
                                                   value="<?php echo htmlspecialchars($peserta['tempat_lahir'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">Tanggal Lahir</label>
                                    <input type="date" class="form-control form-control-custom" 
                                           name="tanggal_lahir" 
                                           value="<?php echo htmlspecialchars($peserta['tanggal_lahir'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom">Alamat</label>
                                    <textarea class="form-control form-control-custom" 
                                              name="alamat" 
                                              rows="3"><?php echo htmlspecialchars($peserta['alamat'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Kolom 2 -->
                        <div class="col-md-6">
                            <!-- Informasi Kontak -->
                            <div class="mb-4">
                                <h5 class="mb-3"><i class="fas fa-address-book me-2 text-primary"></i> Informasi Kontak</h5>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">No. Telepon</label>
                                    <input type="tel" class="form-control form-control-custom" 
                                           name="no_telepon" 
                                           value="<?php echo htmlspecialchars($peserta['no_telepon'] ?? ''); ?>"
                                           pattern="[0-9]{10,15}"
                                           title="Minimal 10 digit angka"
                                           required>
                                </div>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom">Email</label>
                                    <input type="email" class="form-control form-control-custom" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($peserta['email'] ?? ''); ?>">
                                </div>
                                
                                <!-- Segmen Peserta -->
                                <h5 class="mb-3 mt-4" id="form-segmen"><i class="fas fa-users me-2 text-primary"></i> Segmen Peserta</h5>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">Jenis Kepesertaan</label>
                                    
                                    <!-- PPU Card -->
                                    <input type="radio" id="segmen-ppu" name="segmen_peserta" value="PPU" 
                                           class="segmen-radio" 
                                           <?php echo ($peserta['segmen_peserta'] ?? 'PBI') == 'PPU' ? 'checked' : ''; ?>>
                                    <label for="segmen-ppu" class="segmen-card d-flex align-items-center">
                                        <div class="segmen-icon segmen-icon-ppu">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <div class="segmen-info">
                                            <div class="segmen-title">PPU - Pekerja Penerima Upah</div>
                                            <p class="segmen-desc">Karyawan dengan gaji tetap dari perusahaan</p>
                                        </div>
                                    </label>
                                    
                                    <!-- PBPU Card -->
                                    <input type="radio" id="segmen-pbpu" name="segmen_peserta" value="PBPU" 
                                           class="segmen-radio" 
                                           <?php echo ($peserta['segmen_peserta'] ?? 'PBI') == 'PBPU' ? 'checked' : ''; ?>>
                                    <label for="segmen-pbpu" class="segmen-card d-flex align-items-center">
                                        <div class="segmen-icon segmen-icon-pbpu">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <div class="segmen-info">
                                            <div class="segmen-title">PBPU - Pekerja Bukan Penerima Upah</div>
                                            <p class="segmen-desc">Wiraswasta, profesional, pekerja mandiri</p>
                                        </div>
                                    </label>
                                    
                                    <!-- PBI Card -->
                                    <input type="radio" id="segmen-pbi" name="segmen_peserta" value="PBI" 
                                           class="segmen-radio" 
                                           <?php echo ($peserta['segmen_peserta'] ?? 'PBI') == 'PBI' ? 'checked' : ''; ?>>
                                    <label for="segmen-pbi" class="segmen-card d-flex align-items-center">
                                        <div class="segmen-icon segmen-icon-pbi">
                                            <i class="fas fa-hands-helping"></i>
                                        </div>
                                        <div class="segmen-info">
                                            <div class="segmen-title">PBI - Penerima Bantuan Iuran</div>
                                            <p class="segmen-desc">Peserta dengan iuran ditanggung pemerintah</p>
                                        </div>
                                    </label>
                                    
                                    <!-- Segmen Details -->
                                    <div class="segmen-details" id="segmen-details-ppu">
                                        <strong>Karakteristik PPU:</strong><br>
                                        • Gaji tetap dari perusahaan<br>
                                        • Iuran ditanggung bersama (perusahaan & karyawan)<br>
                                        • Wajib melaporkan gaji untuk perhitungan iuran
                                    </div>
                                    
                                    <div class="segmen-details" id="segmen-details-pbpu">
                                        <strong>Karakteristik PBPU:</strong><br>
                                        • Tidak memiliki pemberi kerja tetap<br>
                                        • Iuran ditanggung sendiri<br>
                                        • Bebas memilih kelas layanan
                                    </div>
                                    
                                    <div class="segmen-details" id="segmen-details-pbi">
                                        <strong>Karakteristik PBI:</strong><br>
                                        • Iuran ditanggung pemerintah<br>
                                        • Untuk masyarakat tidak mampu<br>
                                        • Kelas layanan ditentukan pemerintah
                                    </div>
                                </div>
                                
                                <!-- Informasi Finansial -->
                                <h5 class="mb-3 mt-4" id="form-gaji"><i class="fas fa-money-bill-wave me-2 text-success"></i> Informasi Finansial</h5>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom">Gaji Dilaporkan (Rp)</label>
                                    <div class="salary-input-group">
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="text" class="form-control form-control-custom" 
                                                   name="gaji_dilaporkan" 
                                                   value="<?php echo isset($peserta['gaji_form_input']) ? $peserta['gaji_form_input'] : '0'; ?>"
                                                   placeholder="Masukkan jumlah gaji"
                                                   oninput="formatCurrency(this)">
                                        </div>
                                    </div>
                                    <small class="text-muted">Gaji yang dilaporkan untuk perhitungan iuran BPJS (wajib untuk PPU)</small>
                                </div>
                                
                                <!-- Informasi BPJS -->
                                <h5 class="mb-3 mt-4"><i class="fas fa-heartbeat me-2 text-primary"></i> Informasi BPJS</h5>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">No. Kartu BPJS</label>
                                    <input type="text" class="form-control form-control-custom" 
                                           name="no_kartu" 
                                           value="<?php echo htmlspecialchars($peserta['no_kartu'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">Faskes</label>
                                    <input type="text" class="form-control form-control-custom" 
                                           name="faskes" 
                                           value="<?php echo htmlspecialchars($peserta['faskes'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">Kelas BPJS</label>
                                    <select class="form-select form-control-custom" name="kelas_bpjs" required>
                                        <option value="">Pilih Kelas</option>
                                        <option value="Kelas 1" <?php echo ($peserta['kelas_bpjs'] ?? '') == 'Kelas 1' ? 'selected' : ''; ?>>Kelas 1</option>
                                        <option value="Kelas 2" <?php echo ($peserta['kelas_bpjs'] ?? '') == 'Kelas 2' ? 'selected' : ''; ?>>Kelas 2</option>
                                        <option value="Kelas 3" <?php echo ($peserta['kelas_bpjs'] ?? '') == 'Kelas 3' ? 'selected' : ''; ?>>Kelas 3</option>
                                    </select>
                                </div>
                                
                                <div class="form-group-custom">
                                    <label class="form-label-custom required-label">Status</label>
                                    <select class="form-select form-control-custom" name="status" required>
                                        <option value="">Pilih Status</option>
                                        <option value="active" <?php echo ($peserta['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="pending" <?php echo ($peserta['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="inactive" <?php echo ($peserta['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Non-Aktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="border-top pt-4 mt-4">
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="peserta_bpjs.php" class="btn btn-cancel btn-action">
                                    <i class="fas fa-times me-2"></i> Batal
                                </a>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($id > 0): ?>
                                    <button type="submit" name="action" value="save" class="btn btn-save btn-action">
                                        <i class="fas fa-save me-2"></i> Update Data
                                    </button>
                                    <?php if ($peserta): ?>
                                    <a href="tambah_pembayaran.php?peserta_id=<?php echo $id; ?>" class="btn btn-success btn-action">
                                        <i class="fas fa-plus-circle me-2"></i> Tambah Pembayaran
                                    </a>
                                    <a href="detail_peserta.php?id=<?php echo $id; ?>" class="btn btn-info btn-action">
                                        <i class="fas fa-eye me-2"></i> Lihat Detail
                                    </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="submit" name="action" value="save" class="btn btn-save btn-action">
                                        <i class="fas fa-save me-2"></i> Simpan Data
                                    </button>
                                    <button type="submit" name="action" value="save_and_new" class="btn btn-save btn-action" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                        <i class="fas fa-save me-2"></i> Simpan & Baru
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Informasi -->
            <div class="alert alert-info mt-4">
                <div class="d-flex">
                    <i class="fas fa-info-circle me-3 mt-1"></i>
                    <div>
                        <h6 class="alert-heading mb-2">Catatan Penting</h6>
                        <p class="mb-0">Pastikan semua data yang dimasukkan sesuai dengan dokumen asli peserta. Data yang sudah disimpan tidak dapat diubah kecuali melalui menu edit.</p>
                        
                        <p class="mb-0 mt-2"><strong>Informasi Segmen Peserta:</strong> 
                            • <span class="badge bg-primary">PPU</span>: Pekerja Penerima Upah - Karyawan dengan gaji tetap<br>
                            • <span class="badge bg-info">PBPU</span>: Pekerja Bukan Penerima Upah - Wiraswasta, profesional<br>
                            • <span class="badge bg-warning">PBI</span>: Penerima Bantuan Iuran - Iuran ditanggung pemerintah
                        </p>
                        
                        <p class="mb-0 mt-2"><strong>Fitur Gaji Dilaporkan:</strong> 
                            • Gaji dilaporkan digunakan untuk perhitungan iuran BPJS Kesehatan<br>
                            • Wajib diisi untuk peserta PPU (Pekerja Penerima Upah)<br>
                            • Pastikan gaji yang dilaporkan sesuai dengan slip gaji asli<br>
                            • Format: Input akan otomatis diformat dengan pemisah ribuan
                        </p>
                        
                        <?php if ($id > 0): ?>
                        <p class="mb-0 mt-2"><strong>Tips:</strong> 
                            • Gunakan tombol "Ubah Segmen" untuk langsung ke bagian segmen peserta<br>
                            • Gunakan tombol "Update Gaji" untuk langsung ke bagian input gaji<br>
                            • Gunakan tombol "Tambah Pembayaran" untuk mencatat pembayaran peserta<br>
                            • Setelah memperbarui data, gunakan tombol "Lihat Detail" untuk melihat informasi lengkap peserta
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Format input NIK
const nikInput = document.querySelector('input[name="nik"]');
if (nikInput) {
    nikInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '').slice(0, 16);
    });
}

// Format input telepon
const telInput = document.querySelector('input[name="no_telepon"]');
if (telInput) {
    telInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
    });
}

// Validasi tanggal lahir
const tglLahirInput = document.querySelector('input[name="tanggal_lahir"]');
if (tglLahirInput) {
    tglLahirInput.addEventListener('change', function(e) {
        const birthDate = new Date(this.value);
        const today = new Date();
        const age = today.getFullYear() - birthDate.getFullYear();
        
        if (age < 0 || age > 120) {
            alert('Tanggal lahir tidak valid. Mohon periksa kembali.');
            this.value = '';
        }
    });
}

// Format currency untuk gaji
function formatCurrency(input) {
    // Simpan posisi kursor
    const start = input.selectionStart;
    const end = input.selectionEnd;
    
    // Hapus karakter selain angka
    let value = input.value.replace(/\D/g, '');
    
    // Format dengan titik sebagai pemisah ribuan
    if (value.length > 0) {
        value = parseInt(value).toLocaleString('id-ID');
    }
    
    // Set nilai kembali
    input.value = value;
    
    // Kembalikan posisi kursor
    const newLength = value.length;
    const newStart = Math.max(start + (newLength - input.value.length), 0);
    const newEnd = Math.max(end + (newLength - input.value.length), 0);
    input.setSelectionRange(newStart, newEnd);
}

// Notifikasi sebelum batal
const cancelBtn = document.querySelector('a[href="peserta_bpjs.php"]');
if (cancelBtn) {
    cancelBtn.addEventListener('click', function(e) {
        const namaInput = document.querySelector('input[name="nama"]');
        if (namaInput && namaInput.value.trim() !== '') {
            if (!confirm('Perubahan yang belum disimpan akan hilang. Lanjutkan?')) {
                e.preventDefault();
            }
        }
    });
}

// Format gaji sebelum submit
const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function(e) {
        const gajiField = document.querySelector('input[name="gaji_dilaporkan"]');
        if (gajiField && gajiField.value) {
            // Hapus format titik sebelum submit
            gajiField.value = gajiField.value.replace(/\D/g, '');
        }
    });
}

// Segmen Peserta Logic
function setupSegmenPeserta() {
    const segmenRadios = document.querySelectorAll('.segmen-radio');
    const segmenDetails = document.querySelectorAll('.segmen-details');
    
    segmenRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Sembunyikan semua detail
            segmenDetails.forEach(detail => {
                detail.classList.remove('active');
            });
            
            // Tampilkan detail yang sesuai
            const detailId = `segmen-details-${this.value.toLowerCase()}`;
            const detailElement = document.getElementById(detailId);
            if (detailElement) {
                detailElement.classList.add('active');
            }
            
            // Untuk PPU, highlight field gaji
            const gajiInput = document.querySelector('input[name="gaji_dilaporkan"]');
            const gajiLabel = document.querySelector('label[for="' + gajiInput.id + '"]');
            
            if (this.value === 'PPU') {
                if (gajiLabel) {
                    gajiLabel.classList.add('required-label');
                    gajiInput.required = true;
                    gajiInput.style.borderColor = '#dc3545';
                }
            } else {
                if (gajiLabel) {
                    gajiLabel.classList.remove('required-label');
                    gajiInput.required = false;
                    gajiInput.style.borderColor = '';
                }
            }
        });
        
        // Trigger change untuk yang sudah terpilih
        if (radio.checked) {
            radio.dispatchEvent(new Event('change'));
        }
    });
}

// Auto-format saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    // Format NIK jika sudah ada data
    if (nikInput && nikInput.value) {
        nikInput.value = nikInput.value.replace(/\D/g, '').slice(0, 16);
    }
    
    // Format telepon jika sudah ada data
    if (telInput && telInput.value) {
        telInput.value = telInput.value.replace(/\D/g, '');
    }
    
    // Format gaji jika sudah ada data
    const gajiInput = document.querySelector('input[name="gaji_dilaporkan"]');
    if (gajiInput && gajiInput.value && gajiInput.value !== '0') {
        let gajiValue = gajiInput.value.replace(/\D/g, '');
        if (gajiValue.length > 0) {
            gajiInput.value = parseInt(gajiValue).toLocaleString('id-ID');
        }
    }
    
    // Setup segmen peserta
    setupSegmenPeserta();
    
    // Scroll ke bagian tertentu jika ada hash
    const hash = window.location.hash;
    if (hash) {
        setTimeout(() => {
            const element = document.querySelector(hash);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
            }
        }, 100);
    }
});
</script>
</body>
</html>