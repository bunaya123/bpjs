<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Inisialisasi variabel
$update_success = false;
$update_error = false;
$error_message = '';

// Cek apakah ini proses update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $kode_obat = mysqli_real_escape_string($conn, $_POST['kode_obat']);
    $nama_obat = mysqli_real_escape_string($conn, $_POST['nama_obat']);
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis']);
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    $stok = intval($_POST['stok']);
    $harga = intval($_POST['harga']);
    $tanggal_expired = !empty($_POST['tanggal_expired']) ? mysqli_real_escape_string($conn, $_POST['tanggal_expired']) : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Update data
    $sql = "UPDATE obat SET 
            kode_obat = ?,
            nama_obat = ?,
            jenis = ?,
            satuan = ?,
            stok = ?,
            harga = ?,
            tanggal_expired = ?,
            status = ?,
            keterangan = ?,
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssiisssi", 
        $kode_obat, $nama_obat, $jenis, $satuan, 
        $stok, $harga, $tanggal_expired, $status, $keterangan, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $update_success = true;
        // Ambil data terbaru
        $sql_get = "SELECT * FROM obat WHERE id = ?";
        $stmt_get = mysqli_prepare($conn, $sql_get);
        mysqli_stmt_bind_param($stmt_get, "i", $id);
        mysqli_stmt_execute($stmt_get);
        $result_get = mysqli_stmt_get_result($stmt_get);
        $obat = mysqli_fetch_assoc($result_get);
        mysqli_stmt_close($stmt_get);
    } else {
        $update_error = true;
        $error_message = "Gagal memperbarui data: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    // Ambil ID dari parameter GET
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
    $obat = mysqli_fetch_assoc($result); // PERBAIKAN: fetch_assoc bukan fetch_assoc
    mysqli_stmt_close($stmt);
    
    if (!$obat) {
        die("Data obat tidak ditemukan");
    }
}

// Variabel untuk status
$is_expired = false;
$is_expiring_soon = false;
$is_stok_rendah = false;
$is_stok_habis = false;

if (!empty($obat['tanggal_expired'])) {
    $expired_date = strtotime($obat['tanggal_expired']);
    $current_date = time();
    $is_expired = ($expired_date < $current_date);
    
    if (!$is_expired) {
        $thirty_days_later = strtotime('+30 days');
        $is_expiring_soon = ($expired_date <= $thirty_days_later && $expired_date > $current_date);
    }
}

$is_stok_habis = ($obat['stok'] == 0);
$is_stok_rendah = ($obat['stok'] > 0 && $obat['stok'] < 10);

// Hitung hari tersisa jika belum expired
$days_left = 0;
if (!empty($obat['tanggal_expired']) && !$is_expired) {
    $days_left = floor(($expired_date - $current_date) / (60 * 60 * 24));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Obat - <?php echo htmlspecialchars($obat['nama_obat']); ?> - BPJS Kesehatan</title>
    
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
    
    .edit-container {
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
    
    /* EDIT CARD */
    .edit-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin: 30px auto;
        max-width: 1400px;
    }
    
    .edit-header {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
        color: white;
        padding: 30px;
        border-bottom: none;
    }
    
    .edit-header-content {
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
    
    .badge-primary {
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
    
    /* PREVIEW SECTION */
    .preview-section {
        background: #f0f7ff;
        border-radius: 12px;
        padding: 20px;
        border: 2px dashed var(--bpjs-primary);
        margin-bottom: 25px;
    }
    
    .preview-title {
        color: var(--bpjs-primary);
        font-weight: 600;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .preview-item {
        background: white;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    
    .preview-label {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 5px;
    }
    
    .preview-value {
        font-weight: 600;
        color: #495057;
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
        .edit-header-content {
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
    
    @media (max-width: 768px) {
        .bpjs-navbar {
            padding: 15px;
        }
        
        .edit-header {
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
        
        .status-badge {
            padding: 8px 15px;
            font-size: 12px;
        }
        
        .preview-grid {
            grid-template-columns: 1fr;
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
    <?php if ($update_success): ?>
    <div class="notification">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Berhasil!</strong> Data obat telah diperbarui.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php elseif ($update_error): ?>
    <div class="notification">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Gagal!</strong> Terjadi kesalahan saat memperbarui data.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    

    <!-- MAIN CONTENT -->
    <div class="edit-container">
        <form method="POST" action="edit_obat.php" id="editObatForm" onsubmit="return validateForm()">
            <input type="hidden" name="id" value="<?php echo $obat['id']; ?>">
            
            <div class="edit-card fade-in">
                <!-- HEADER -->
                <div class="edit-header">
                    <div class="edit-header-content">
                        <div class="d-flex align-items-center gap-4">
                            <div class="obat-avatar">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="obat-info">
                                <h1>Edit Obat</h1>
                                <div class="obat-subtitle">
                                    <i class="fas fa-pills me-2"></i><?php echo htmlspecialchars($obat['nama_obat']); ?>
                                    <span class="mx-3">•</span>
                                    <i class="fas fa-barcode me-2"></i><?php echo htmlspecialchars($obat['kode_obat']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="status-badges">
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
                            
                            <span class="status-badge badge-<?php echo ($obat['status'] == 'Aktif') ? 'success' : 'danger'; ?>">
                                <i class="fas fa-<?php echo ($obat['status'] == 'Aktif') ? 'check' : 'times'; ?>-circle"></i>
                                <?php echo htmlspecialchars($obat['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- FORM CONTENT -->
                <div class="form-section">
                    <!-- INFORMASI DASAR -->
                    <div class="mb-5">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informasi Dasar
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
                                               value="<?php echo htmlspecialchars($obat['kode_obat']); ?>"
                                               required
                                               pattern="[A-Za-z0-9]{3,10}"
                                               title="Kode obat harus 3-10 karakter alfanumerik">
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
                                               value="<?php echo htmlspecialchars($obat['nama_obat']); ?>"
                                               required
                                               minlength="3"
                                               maxlength="100">
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
                                            <option value="Generik" <?php echo ($obat['jenis'] == 'Generik') ? 'selected' : ''; ?>>Generik</option>
                                            <option value="Paten" <?php echo ($obat['jenis'] == 'Paten') ? 'selected' : ''; ?>>Paten</option>
                                            <option value="Herbal" <?php echo ($obat['jenis'] == 'Herbal') ? 'selected' : ''; ?>>Herbal</option>
                                            <option value="Vitamin" <?php echo ($obat['jenis'] == 'Vitamin') ? 'selected' : ''; ?>>Vitamin</option>
                                            <option value="Antibiotik" <?php echo ($obat['jenis'] == 'Antibiotik') ? 'selected' : ''; ?>>Antibiotik</option>
                                            <option value="Analgesik" <?php echo ($obat['jenis'] == 'Analgesik') ? 'selected' : ''; ?>>Analgesik</option>
                                            <option value="Antihipertensi" <?php echo ($obat['jenis'] == 'Antihipertensi') ? 'selected' : ''; ?>>Antihipertensi</option>
                                            <option value="Lainnya" <?php echo ($obat['jenis'] == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-weight"></i>Satuan
                                        </label>
                                        <select class="form-select" name="satuan" id="satuan" required>
                                            <option value="">Pilih Satuan</option>
                                            <option value="Tablet" <?php echo ($obat['satuan'] == 'Tablet') ? 'selected' : ''; ?>>Tablet</option>
                                            <option value="Kapsul" <?php echo ($obat['satuan'] == 'Kapsul') ? 'selected' : ''; ?>>Kapsul</option>
                                            <option value="Sirup" <?php echo ($obat['satuan'] == 'Sirup') ? 'selected' : ''; ?>>Sirup</option>
                                            <option value="Suntik" <?php echo ($obat['satuan'] == 'Suntik') ? 'selected' : ''; ?>>Suntik</option>
                                            <option value="Salep" <?php echo ($obat['satuan'] == 'Salep') ? 'selected' : ''; ?>>Salep</option>
                                            <option value="Drop" <?php echo ($obat['satuan'] == 'Drop') ? 'selected' : ''; ?>>Drop</option>
                                            <option value="Botol" <?php echo ($obat['satuan'] == 'Botol') ? 'selected' : ''; ?>>Botol</option>
                                            <option value="Tube" <?php echo ($obat['satuan'] == 'Tube') ? 'selected' : ''; ?>>Tube</option>
                                            <option value="Vial" <?php echo ($obat['satuan'] == 'Vial') ? 'selected' : ''; ?>>Vial</option>
                                        </select>
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
                                <div class="form-card">
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-boxes"></i>Stok Tersedia
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               name="stok" 
                                               id="stok"
                                               value="<?php echo htmlspecialchars($obat['stok']); ?>"
                                               required
                                               min="0"
                                               step="1">
                                        <div class="form-text">
                                            Jumlah stok saat ini
                                        </div>
                                        <?php if ($is_stok_habis): ?>
                                        <div class="alert alert-danger mt-2 p-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Stok sudah habis! Segera restock.
                                        </div>
                                        <?php elseif ($is_stok_rendah): ?>
                                        <div class="alert alert-warning mt-2 p-2">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            Stok rendah! Perhatikan ketersediaan.
                                        </div>
                                        <?php endif; ?>
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
                                                   value="<?php echo htmlspecialchars($obat['harga']); ?>"
                                                   required
                                                   min="0"
                                                   step="100">
                                        </div>
                                        <div class="form-text">
                                            Harga per <?php echo htmlspecialchars($obat['satuan'] ?? 'satuan'); ?>
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
                                               value="<?php echo !empty($obat['tanggal_expired']) ? htmlspecialchars($obat['tanggal_expired']) : ''; ?>"
                                               min="<?php echo date('Y-m-d'); ?>">
                                        <div class="form-text">
                                            Kosongkan jika tidak ada tanggal expired
                                        </div>
                                        <?php if (!empty($obat['tanggal_expired'])): ?>
                                        <?php 
                                        if ($is_expired) {
                                            echo '<div class="alert alert-danger mt-2 p-2">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                Obat sudah expired!
                                            </div>';
                                        } elseif ($is_expiring_soon) {
                                            echo '<div class="alert alert-warning mt-2 p-2">
                                                <i class="fas fa-clock me-2"></i>
                                                Obat akan expired dalam ' . floor(($expired_date - time()) / (60*60*24)) . ' hari
                                            </div>';
                                        }
                                        ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label required">
                                            <i class="fas fa-chart-line"></i>Status
                                        </label>
                                        <select class="form-select" name="status" id="status" required>
                                            <option value="Aktif" <?php echo ($obat['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="Non-Aktif" <?php echo ($obat['status'] == 'Non-Aktif') ? 'selected' : ''; ?>>Non-Aktif</option>
                                        </select>
                                        <div class="form-text">
                                            Status ketersediaan obat
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- KETERANGAN -->
                    <div class="mb-5">
                        <h4 class="section-title">
                            <i class="fas fa-sticky-note"></i>
                            Keterangan Tambahan
                        </h4>
                        
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
                                          placeholder="Tambahkan keterangan jika diperlukan..."><?php echo htmlspecialchars($obat['keterangan'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    Maksimal 500 karakter
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Karakter tersisa: <span id="charCount">500</span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PREVIEW -->
                    <div class="mb-4">
                        <h4 class="section-title">
                            <i class="fas fa-eye"></i>
                            Preview Perubahan
                        </h4>
                        
                        <div class="preview-section">
                            <h5 class="preview-title">
                                <i class="fas fa-info-circle"></i>
                                Data yang akan diperbarui
                            </h5>
                            <div class="preview-grid">
                                <div class="preview-item">
                                    <div class="preview-label">Kode Obat</div>
                                    <div class="preview-value" id="previewKode"><?php echo htmlspecialchars($obat['kode_obat']); ?></div>
                                </div>
                                <div class="preview-item">
                                    <div class="preview-label">Nama Obat</div>
                                    <div class="preview-value" id="previewNama"><?php echo htmlspecialchars($obat['nama_obat']); ?></div>
                                </div>
                                <div class="preview-item">
                                    <div class="preview-label">Stok</div>
                                    <div class="preview-value" id="previewStok"><?php echo number_format($obat['stok']); ?></div>
                                </div>
                                <div class="preview-item">
                                    <div class="preview-label">Harga</div>
                                    <div class="preview-value" id="previewHarga">Rp <?php echo number_format($obat['harga'], 0, ',', '.'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ACTION BUTTONS -->
                <div class="action-buttons">
                    <div class="container">
                        <div class="row justify-content-between">
                            <div class="col-auto">
                                <a href="detail_obat.php?id=<?php echo $obat['id']; ?>" class="btn btn-bpjs-secondary">
                                    <i class="fas fa-times me-2"></i> Batal
                                </a>
                            </div>
                            <div class="col-auto">
                                <div class="d-flex gap-3">
                                    <button type="reset" class="btn btn-bpjs-secondary">
                                        <i class="fas fa-redo me-2"></i> Reset
                                    </button>
                                    <button type="submit" class="btn btn-bpjs-success">
                                        <i class="fas fa-save me-2"></i> Simpan Perubahan
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
    // Real-time preview
    document.getElementById('kode_obat').addEventListener('input', function() {
        document.getElementById('previewKode').textContent = this.value;
    });
    
    document.getElementById('nama_obat').addEventListener('input', function() {
        document.getElementById('previewNama').textContent = this.value;
    });
    
    document.getElementById('stok').addEventListener('input', function() {
        document.getElementById('previewStok').textContent = this.value.toLocaleString();
    });
    
    document.getElementById('harga').addEventListener('input', function() {
        document.getElementById('previewHarga').textContent = 'Rp ' + parseInt(this.value).toLocaleString();
    });
    
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
    
    // Initialize character count
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('keterangan');
        const currentLength = textarea.value.length;
        const remaining = 500 - currentLength;
        document.getElementById('charCount').textContent = remaining;
    });
    
    // Form validation
    function validateForm() {
        let isValid = true;
        
        // Validate kode obat
        const kodeObat = document.getElementById('kode_obat');
        const kodePattern = /^[A-Za-z0-9]{3,10}$/;
        if (!kodePattern.test(kodeObat.value)) {
            kodeObat.classList.add('is-invalid');
            kodeObat.classList.remove('is-valid');
            isValid = false;
        } else {
            kodeObat.classList.remove('is-invalid');
            kodeObat.classList.add('is-valid');
        }
        
        // Validate nama obat
        const namaObat = document.getElementById('nama_obat');
        if (namaObat.value.length < 3 || namaObat.value.length > 100) {
            namaObat.classList.add('is-invalid');
            namaObat.classList.remove('is-valid');
            isValid = false;
        } else {
            namaObat.classList.remove('is-invalid');
            namaObat.classList.add('is-valid');
        }
        
        // Validate stok
        const stok = document.getElementById('stok');
        if (stok.value < 0) {
            stok.classList.add('is-invalid');
            stok.classList.remove('is-valid');
            isValid = false;
        } else {
            stok.classList.remove('is-invalid');
            stok.classList.add('is-valid');
        }
        
        // Validate harga
        const harga = document.getElementById('harga');
        if (harga.value < 0) {
            harga.classList.add('is-invalid');
            harga.classList.remove('is-valid');
            isValid = false;
        } else {
            harga.classList.remove('is-invalid');
            harga.classList.add('is-valid');
        }
        
        // Validate tanggal expired
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
                        submitForm();
                    }
                });
                return false;
            }
        }
        
        if (!isValid) {
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Harap periksa kembali data yang Anda masukkan',
                confirmButtonText: 'Mengerti'
            });
            return false;
        }
        
        return true;
    }
    
    function submitForm() {
        // Show loading
        Swal.fire({
            title: 'Menyimpan perubahan...',
            text: 'Mohon tunggu sebentar',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Submit form
        document.getElementById('editObatForm').submit();
    }
    
    // Auto-hide notification after 5 seconds
    <?php if ($update_success || $update_error): ?>
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
    
    // Auto-close success notification and redirect
    <?php if ($update_success): ?>
    setTimeout(() => {
        window.location.href = 'detail_obat.php?id=<?php echo $obat['id']; ?>';
    }, 3000);
    <?php endif; ?>
    </script>
</body>
</html>