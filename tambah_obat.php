<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek apakah ini proses simpan
$save_success = false;
$save_error = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_obat = mysqli_real_escape_string($conn, $_POST['kode_obat']);
    $nama_obat = mysqli_real_escape_string($conn, $_POST['nama_obat']);
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis']);
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    $stok = intval($_POST['stok']);
    $harga = intval($_POST['harga']);
    $tanggal_expired = !empty($_POST['tanggal_expired']) ? mysqli_real_escape_string($conn, $_POST['tanggal_expired']) : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Cek apakah kode obat sudah ada
    $check_sql = "SELECT id FROM obat WHERE kode_obat = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $kode_obat);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $save_error = true;
        $error_message = "Kode obat '$kode_obat' sudah digunakan.";
        mysqli_stmt_close($check_stmt);
    } else {
        mysqli_stmt_close($check_stmt);
        
        // Insert data baru
        $sql = "INSERT INTO obat (kode_obat, nama_obat, jenis, satuan, stok, harga, tanggal_expired, status, keterangan, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssiisss", 
            $kode_obat, $nama_obat, $jenis, $satuan, 
            $stok, $harga, $tanggal_expired, $status, $keterangan);
        
        if (mysqli_stmt_execute($stmt)) {
            $save_success = true;
            $last_id = mysqli_insert_id($conn);
            
            // Redirect ke detail setelah berhasil
            header("Location: detail_obat.php?id=" . $last_id);
            exit();
        } else {
            $save_error = true;
            $error_message = "Gagal menyimpan data obat: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Ambil data user untuk sidebar (jika diperlukan)
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Tambah Obat Baru - BPJS Kesehatan</title>
    
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
    
    .create-container {
        min-height: 100vh;
        background: #f5f7fa;
    }
    
    /* NAVBAR */
    .bpjs-navbar {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
        padding: 15px 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        position: sticky;
        top: 0;
        z-index: 1030;
    }
    
    .navbar-brand {
        color: white !important;
        font-weight: 600;
        font-size: 1.2rem;
    }
    
    .navbar-brand i {
        margin-right: 10px;
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
    
    /* CREATE CARD */
    .create-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin: 30px auto;
        max-width: 1400px;
    }
    
    .create-header {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
        color: white;
        padding: 30px;
        border-bottom: none;
    }
    
    .create-header-content {
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
    
    .status-badge {
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
    }
    
    /* FORM STYLES */
    .form-section {
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
    
    .form-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 25px;
        border-left: 5px solid var(--bpjs-primary);
        margin-bottom: 25px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-label i {
        color: var(--bpjs-primary);
    }
    
    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #ced4da;
        padding: 12px 15px;
        font-size: 16px;
        transition: all 0.3s;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--bpjs-primary);
        box-shadow: 0 0 0 0.25rem rgba(0, 115, 230, 0.25);
    }
    
    .form-text {
        color: #6c757d;
        font-size: 14px;
        margin-top: 5px;
    }
    
    .required::after {
        content: " *";
        color: var(--bpjs-danger);
    }
    
    /* WIZARD STEPS */
    .wizard-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        position: relative;
    }
    
    .wizard-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e9ecef;
        z-index: 1;
    }
    
    .wizard-step {
        position: relative;
        z-index: 2;
        text-align: center;
        flex: 1;
    }
    
    .step-number {
        width: 40px;
        height: 40px;
        background: #e9ecef;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: 600;
        color: #6c757d;
        border: 3px solid white;
        transition: all 0.3s;
    }
    
    .step-title {
        font-size: 14px;
        color: #6c757d;
        font-weight: 500;
    }
    
    .wizard-step.active .step-number {
        background: var(--bpjs-primary);
        color: white;
        box-shadow: 0 0 0 3px white, 0 0 0 6px rgba(0, 115, 230, 0.2);
    }
    
    .wizard-step.active .step-title {
        color: var(--bpjs-primary);
        font-weight: 600;
    }
    
    .wizard-step.completed .step-number {
        background: var(--bpjs-success);
        color: white;
    }
    
    .wizard-step.completed .step-title {
        color: var(--bpjs-success);
    }
    
    /* NOTIFICATION */
    .notification {
        position: fixed;
        top: 100px;
        right: 30px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        animation: slideIn 0.5s ease-out;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
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
    
    /* VALIDATION STATES */
    .is-valid {
        border-color: var(--bpjs-success) !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    
    .is-invalid {
        border-color: var(--bpjs-danger) !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    
    .valid-feedback {
        color: var(--bpjs-success);
        font-size: 14px;
        margin-top: 5px;
    }
    
    .invalid-feedback {
        color: var(--bpjs-danger);
        font-size: 14px;
        margin-top: 5px;
    }
    
    /* RESPONSIVE */
    @media (max-width: 992px) {
        .create-header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .obat-info h1 {
            font-size: 1.8rem;
        }
        
        .wizard-steps {
            flex-wrap: wrap;
        }
        
        .wizard-step {
            flex: 0 0 50%;
            margin-bottom: 20px;
        }
    }
    
    @media (max-width: 768px) {
        .bpjs-navbar {
            padding: 15px;
        }
        
        .create-header {
            padding: 20px;
        }
        
        .form-section {
            padding: 20px;
        }
        
        .form-card {
            padding: 20px;
        }
        
        .obat-info h1 {
            font-size: 1.5rem;
        }
        
        .wizard-step {
            flex: 0 0 100%;
        }
        
        .btn-bpjs, .btn-bpjs-secondary, .btn-bpjs-success {
            padding: 10px 20px;
            font-size: 14px;
            width: 100%;
            margin-bottom: 10px;
        }
    }
    
    @media (max-width: 576px) {
        .obat-avatar {
            width: 60px;
            height: 60px;
            font-size: 24px;
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }
        
        .step-title {
            font-size: 12px;
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
    </style>
</head>
<body>
    <!-- NOTIFICATION -->
    <?php if ($save_error): ?>
    <div class="notification">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Gagal!</strong> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bpjs-navbar">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="obat.php">
                <i class="fas fa-heartbeat fa-lg"></i>
                <div class="ms-3">
                    <span class="fw-bold">BPJS Kesehatan</span><br>
                    <small class="opacity-75">Tambah Obat Baru</small>
                </div>
            </a>
            <div class="d-flex align-items-center">
                <a href="obat.php" class="btn back-btn">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="create-container">
        <form method="POST" action="tambah_obat.php" id="tambahObatForm" onsubmit="return validateForm()">
            <div class="create-card fade-in">
                <!-- HEADER -->
                <div class="create-header">
                    <div class="create-header-content">
                        <div class="d-flex align-items-center gap-4">
                            <div class="obat-avatar">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="obat-info">
                                <h1>Tambah Obat Baru</h1>
                                <div class="obat-subtitle">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Lengkapi informasi obat baru di bawah ini
                                </div>
                            </div>
                        </div>
                        
                        <div class="status-badge">
                            <i class="fas fa-clock"></i> Form Baru
                        </div>
                    </div>
                </div>
                
                <!-- WIZARD STEPS -->
                <div class="form-section">
                    <div class="wizard-steps">
                        <div class="wizard-step active" id="step1">
                            <div class="step-number">1</div>
                            <div class="step-title">Informasi Dasar</div>
                        </div>
                        <div class="wizard-step" id="step2">
                            <div class="step-number">2</div>
                            <div class="step-title">Stok & Harga</div>
                        </div>
                        <div class="wizard-step" id="step3">
                            <div class="step-number">3</div>
                            <div class="step-title">Status & Keterangan</div>
                        </div>
                        <div class="wizard-step" id="step4">
                            <div class="step-number">4</div>
                            <div class="step-title">Preview</div>
                        </div>
                    </div>
                </div>
                
                <!-- FORM CONTENT -->
                <div class="form-section">
                    <!-- STEP 1: INFORMASI DASAR -->
                    <div id="step1-content" class="wizard-content active">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informasi Dasar Obat
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-barcode"></i>Kode Obat
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="kode_obat" 
                                               id="kode_obat"
                                               required
                                               pattern="[A-Za-z0-9]{3,10}"
                                               title="Kode obat harus 3-10 karakter alfanumerik"
                                               placeholder="Contoh: OB001">
                                        <div class="form-text">
                                            Format: OBxxx (contoh: OB001, OB123)
                                        </div>
                                        <div class="invalid-feedback" id="kodeObatError">
                                            Kode obat harus 3-10 karakter alfanumerik
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-pills"></i>Nama Obat
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="nama_obat" 
                                               id="nama_obat"
                                               required
                                               minlength="3"
                                               maxlength="100"
                                               placeholder="Contoh: Paracetamol 500mg">
                                        <div class="form-text">
                                            Masukkan nama lengkap obat
                                        </div>
                                        <div class="invalid-feedback" id="namaObatError">
                                            Nama obat harus 3-100 karakter
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-tag"></i>Jenis Obat
                                        </label>
                                        <select class="form-select" name="jenis" id="jenis">
                                            <option value="">Pilih Jenis</option>
                                            <option value="Generik">Generik</option>
                                            <option value="Paten">Paten</option>
                                            <option value="Herbal">Herbal</option>
                                            <option value="Vitamin">Vitamin</option>
                                            <option value="Antibiotik">Antibiotik</option>
                                            <option value="Analgesik">Analgesik</option>
                                            <option value="Antihipertensi">Antihipertensi</option>
                                            <option value="Lainnya">Lainnya</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-weight"></i>Satuan
                                        </label>
                                        <select class="form-select" name="satuan" id="satuan" required>
                                            <option value="">Pilih Satuan</option>
                                            <option value="Tablet">Tablet</option>
                                            <option value="Kapsul">Kapsul</option>
                                            <option value="Sirup">Sirup</option>
                                            <option value="Suntik">Suntik</option>
                                            <option value="Salep">Salep</option>
                                            <option value="Drop">Drop</option>
                                            <option value="Botol">Botol</option>
                                            <option value="Tube">Tube</option>
                                            <option value="Vial">Vial</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- STEP 2: STOK & HARGA -->
                    <div id="step2-content" class="wizard-content" style="display: none;">
                        <h4 class="section-title">
                            <i class="fas fa-warehouse"></i>
                            Stok & Harga
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-boxes"></i>Stok Awal
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               name="stok" 
                                               id="stok"
                                               required
                                               min="0"
                                               step="1"
                                               value="0">
                                        <div class="form-text">
                                            Jumlah stok awal obat
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-dollar-sign"></i>Harga (Rp)
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   name="harga" 
                                                   id="harga"
                                                   required
                                                   min="0"
                                                   step="100"
                                                   placeholder="5000">
                                        </div>
                                        <div class="form-text">
                                            Harga per satuan dalam Rupiah
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-calendar-alt"></i>Tanggal Expired
                                        </label>
                                        <input type="date" 
                                               class="form-control" 
                                               name="tanggal_expired" 
                                               id="tanggal_expired"
                                               min="<?php echo date('Y-m-d'); ?>">
                                        <div class="form-text">
                                            Kosongkan jika tidak ada tanggal expired
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Informasi:</strong> Pastikan tanggal expired diisi dengan benar untuk obat yang memiliki masa berlaku.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- STEP 3: STATUS & KETERANGAN -->
                    <div id="step3-content" class="wizard-content" style="display: none;">
                        <h4 class="section-title">
                            <i class="fas fa-chart-line"></i>
                            Status & Keterangan
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-check-circle"></i>Status
                                        </label>
                                        <select class="form-select" name="status" id="status" required>
                                            <option value="Aktif" selected>Aktif</option>
                                            <option value="Non-Aktif">Non-Aktif</option>
                                        </select>
                                        <div class="form-text">
                                            Status ketersediaan obat
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-success mt-3">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        <strong>Tips:</strong> Pilih "Aktif" untuk obat yang siap digunakan, atau "Non-Aktif" untuk obat yang tidak tersedia.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-edit"></i>Keterangan
                                        </label>
                                        <textarea class="form-control" 
                                                  name="keterangan" 
                                                  id="keterangan"
                                                  rows="5"
                                                  maxlength="500"
                                                  placeholder="Tambahkan keterangan jika diperlukan..."></textarea>
                                        <div class="form-text">
                                            Maksimal 500 karakter
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">Karakter tersisa: <span id="charCount">500</span></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- STEP 4: PREVIEW -->
                    <div id="step4-content" class="wizard-content" style="display: none;">
                        <h4 class="section-title">
                            <i class="fas fa-eye"></i>
                            Preview Data Obat
                        </h4>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Data siap disimpan!</strong> Periksa kembali data obat yang akan ditambahkan.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <h5><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Dasar</h5>
                                    <div class="preview-item mb-3">
                                        <div class="preview-label">Kode Obat</div>
                                        <div class="preview-value" id="previewKode">-</div>
                                    </div>
                                    <div class="preview-item mb-3">
                                        <div class="preview-label">Nama Obat</div>
                                        <div class="preview-value" id="previewNama">-</div>
                                    </div>
                                    <div class="preview-item mb-3">
                                        <div class="preview-label">Jenis</div>
                                        <div class="preview-value" id="previewJenis">-</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">Satuan</div>
                                        <div class="preview-value" id="previewSatuan">-</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="form-card">
                                    <h5><i class="fas fa-chart-bar me-2 text-success"></i>Stok & Harga</h5>
                                    <div class="preview-item mb-3">
                                        <div class="preview-label">Stok</div>
                                        <div class="preview-value" id="previewStok">0</div>
                                    </div>
                                    <div class="preview-item mb-3">
                                        <div class="preview-label">Harga</div>
                                        <div class="preview-value" id="previewHarga">Rp 0</div>
                                    </div>
                                    <div class="preview-item mb-3">
                                        <div class="preview-label">Tanggal Expired</div>
                                        <div class="preview-value" id="previewExpired">-</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">Status</div>
                                        <div class="preview-value" id="previewStatus">Aktif</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-card">
                            <h5><i class="fas fa-sticky-note me-2 text-info"></i>Keterangan</h5>
                            <div id="previewKeterangan" class="text-muted">Tidak ada keterangan</div>
                        </div>
                    </div>
                </div>
                
                <!-- ACTION BUTTONS -->
                <div class="action-buttons">
                    <div class="container">
                        <div class="row justify-content-between">
                            <div class="col-auto">
                                <button type="button" class="btn btn-bpjs-secondary" id="prevBtn" style="display: none;">
                                    <i class="fas fa-arrow-left me-2"></i> Sebelumnya
                                </button>
                            </div>
                            <div class="col-auto">
                                <div class="d-flex gap-3">
                                    <button type="button" class="btn btn-bpjs-secondary" id="nextBtn">
                                        <i class="fas fa-arrow-right me-2"></i> Berikutnya
                                    </button>
                                    <button type="submit" class="btn btn-bpjs-success" id="submitBtn" style="display: none;">
                                        <i class="fas fa-save me-2"></i> Simpan Obat
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // Wizard navigation
    let currentStep = 1;
    const totalSteps = 4;
    
    // Update preview in real-time
    document.getElementById('kode_obat').addEventListener('input', updatePreview);
    document.getElementById('nama_obat').addEventListener('input', updatePreview);
    document.getElementById('jenis').addEventListener('change', updatePreview);
    document.getElementById('satuan').addEventListener('change', updatePreview);
    document.getElementById('stok').addEventListener('input', updatePreview);
    document.getElementById('harga').addEventListener('input', updatePreview);
    document.getElementById('tanggal_expired').addEventListener('change', updatePreview);
    document.getElementById('status').addEventListener('change', updatePreview);
    document.getElementById('keterangan').addEventListener('input', updatePreview);
    
    // Character count for keterangan
    document.getElementById('keterangan').addEventListener('input', function() {
        const maxLength = 500;
        const currentLength = this.value.length;
        const remaining = maxLength - currentLength;
        document.getElementById('charCount').textContent = remaining;
        
        if (remaining < 0) {
            this.value = this.value.substring(0, maxLength);
            document.getElementById('charCount').textContent = 0;
        }
    });
    
    function updatePreview() {
        // Update preview values
        document.getElementById('previewKode').textContent = document.getElementById('kode_obat').value || '-';
        document.getElementById('previewNama').textContent = document.getElementById('nama_obat').value || '-';
        document.getElementById('previewJenis').textContent = document.getElementById('jenis').value || '-';
        document.getElementById('previewSatuan').textContent = document.getElementById('satuan').value || '-';
        
        const stok = parseInt(document.getElementById('stok').value) || 0;
        document.getElementById('previewStok').textContent = stok.toLocaleString();
        
        const harga = parseInt(document.getElementById('harga').value) || 0;
        document.getElementById('previewHarga').textContent = 'Rp ' + harga.toLocaleString();
        
        const expired = document.getElementById('tanggal_expired').value;
        document.getElementById('previewExpired').textContent = expired ? new Date(expired).toLocaleDateString('id-ID') : '-';
        
        document.getElementById('previewStatus').textContent = document.getElementById('status').value || 'Aktif';
        
        const keterangan = document.getElementById('keterangan').value;
        document.getElementById('previewKeterangan').textContent = keterangan || 'Tidak ada keterangan';
        document.getElementById('previewKeterangan').className = keterangan ? '' : 'text-muted';
    }
    
    function showStep(step) {
        // Hide all steps
        document.querySelectorAll('.wizard-content').forEach(content => {
            content.style.display = 'none';
        });
        
        // Remove active class from all steps
        document.querySelectorAll('.wizard-step').forEach(stepEl => {
            stepEl.classList.remove('active', 'completed');
        });
        
        // Show current step
        document.getElementById(`step${step}-content`).style.display = 'block';
        
        // Mark previous steps as completed
        for (let i = 1; i < step; i++) {
            document.getElementById(`step${i}`).classList.add('completed');
        }
        
        // Mark current step as active
        document.getElementById(`step${step}`).classList.add('active');
        
        // Update button visibility
        document.getElementById('prevBtn').style.display = step > 1 ? 'inline-flex' : 'none';
        document.getElementById('nextBtn').style.display = step < totalSteps ? 'inline-flex' : 'none';
        document.getElementById('submitBtn').style.display = step === totalSteps ? 'inline-flex' : 'none';
        
        // Update current step
        currentStep = step;
    }
    
    // Navigation buttons
    document.getElementById('nextBtn').addEventListener('click', function() {
        if (validateCurrentStep()) {
            showStep(currentStep + 1);
        }
    });
    
    document.getElementById('prevBtn').addEventListener('click', function() {
        showStep(currentStep - 1);
    });
    
    // Step validation
    function validateCurrentStep() {
        let isValid = true;
        
        if (currentStep === 1) {
            // Validate step 1
            const kodeObat = document.getElementById('kode_obat');
            const kodePattern = /^[A-Za-z0-9]{3,10}$/;
            if (!kodePattern.test(kodeObat.value)) {
                kodeObat.classList.add('is-invalid');
                isValid = false;
            } else {
                kodeObat.classList.remove('is-invalid');
            }
            
            const namaObat = document.getElementById('nama_obat');
            if (namaObat.value.length < 3 || namaObat.value.length > 100) {
                namaObat.classList.add('is-invalid');
                isValid = false;
            } else {
                namaObat.classList.remove('is-invalid');
            }
            
            const satuan = document.getElementById('satuan');
            if (!satuan.value) {
                satuan.classList.add('is-invalid');
                isValid = false;
            } else {
                satuan.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Gagal',
                    text: 'Harap lengkapi informasi dasar obat dengan benar',
                    confirmButtonText: 'Mengerti'
                });
            }
        } else if (currentStep === 2) {
            // Validate step 2
            const stok = document.getElementById('stok');
            if (stok.value < 0) {
                stok.classList.add('is-invalid');
                isValid = false;
            } else {
                stok.classList.remove('is-invalid');
            }
            
            const harga = document.getElementById('harga');
            if (harga.value < 0) {
                harga.classList.add('is-invalid');
                isValid = false;
            } else {
                harga.classList.remove('is-invalid');
            }
            
            const tanggalExpired = document.getElementById('tanggal_expired');
            if (tanggalExpired.value) {
                const selectedDate = new Date(tanggalExpired.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tanggal Expired Telah Lewat',
                        text: 'Tanggal expired sudah lewat dari hari ini. Apakah Anda yakin ingin melanjutkan?',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Lanjutkan',
                        cancelButtonText: 'Perbaiki'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            showStep(currentStep + 1);
                        }
                    });
                    return false;
                }
            }
            
            if (!isValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Gagal',
                    text: 'Harap periksa data stok dan harga',
                    confirmButtonText: 'Mengerti'
                });
            }
        }
        
        return isValid;
    }
    
    // Form validation
    function validateForm() {
        // Validate all steps
        for (let i = 1; i <= totalSteps; i++) {
            showStep(i);
            if (!validateCurrentStep()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Gagal',
                    text: 'Harap lengkapi semua data dengan benar',
                    confirmButtonText: 'Mengerti'
                });
                return false;
            }
        }
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Simpan Obat Baru?',
            html: `Apakah Anda yakin ingin menyimpan data obat baru?<br><br>
                  <strong>Kode:</strong> ${document.getElementById('kode_obat').value}<br>
                  <strong>Nama:</strong> ${document.getElementById('nama_obat').value}<br>
                  <strong>Stok:</strong> ${parseInt(document.getElementById('stok').value).toLocaleString()}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#28a745'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading and submit form
                Swal.fire({
                    title: 'Menyimpan data...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        document.getElementById('tambahObatForm').submit();
                    }
                });
            }
        });
        
        return false;
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        updatePreview();
        showStep(1);
        
        // Auto-hide error notification after 5 seconds
        <?php if ($save_error): ?>
        setTimeout(() => {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.transition = 'opacity 0.5s';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 500);
            }
        }, 5000);
        <?php endif; ?>
    });
    </script>
</body>
</html>