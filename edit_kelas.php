<?php
// edit_kelas.php
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

// Inisialisasi variabel
$message = '';
$message_type = '';
$kelas = null;

// Cek apakah ada ID kelas yang dikirim
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kelas.php");
    exit();
}

$id = intval($_GET['id']);

// Ambil data kelas berdasarkan ID
$sql = "SELECT * FROM kelas WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    header("Location: kelas.php");
    exit();
}

$kelas = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Proses form edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $kode_kelas = mysqli_real_escape_string($conn, $_POST['kode_kelas']);
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    // **PERBAIKAN: Hapus titik dari input iuran sebelum disimpan ke database**
    $iuran_per_bulan = str_replace('.', '', $_POST['iuran_per_bulan']);
    $iuran_per_bulan = mysqli_real_escape_string($conn, $iuran_per_bulan);
    
    $fasilitas = mysqli_real_escape_string($conn, $_POST['fasilitas']);
    
    // Validasi input iuran
    if (!is_numeric($iuran_per_bulan) || $iuran_per_bulan <= 0) {
        $message = "Iuran per bulan harus berupa angka positif!";
        $message_type = "danger";
    } else {
        // Validasi kode kelas unik (kecuali untuk kelas ini)
        $check_sql = "SELECT id FROM kelas WHERE kode_kelas = ? AND id != ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "si", $kode_kelas, $id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $message = "Kode kelas sudah digunakan oleh kelas lain!";
            $message_type = "danger";
        } else {
            // Update data kelas
            $sql = "UPDATE kelas SET 
                    kode_kelas = ?, 
                    nama_kelas = ?, 
                    deskripsi = ?, 
                    iuran_per_bulan = ?, 
                    fasilitas = ?,
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssdsi", $kode_kelas, $nama_kelas, $deskripsi, $iuran_per_bulan, $fasilitas, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Data kelas berhasil diperbarui!";
                $message_type = "success";
                
                // Update data kelas yang ditampilkan
                $kelas['kode_kelas'] = $kode_kelas;
                $kelas['nama_kelas'] = $nama_kelas;
                $kelas['deskripsi'] = $deskripsi;
                $kelas['iuran_per_bulan'] = $iuran_per_bulan;
                $kelas['fasilitas'] = $fasilitas;
            } else {
                $message = "Gagal memperbarui data kelas: " . mysqli_error($conn);
                $message_type = "danger";
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check_stmt);
    }
}

// Format tanggal
$created_at = date('d F Y', strtotime($kelas['created_at']));
$updated_at = date('d F Y', strtotime($kelas['updated_at']));
$iuran_formatted = "Rp " . number_format($kelas['iuran_per_bulan'], 0, ',', '.');

// Hitung jumlah peserta di kelas ini
$sql_peserta = "SELECT COUNT(*) as total FROM peserta WHERE kelas_id = ?";
$stmt_peserta = mysqli_prepare($conn, $sql_peserta);
mysqli_stmt_bind_param($stmt_peserta, "i", $id);
mysqli_stmt_execute($stmt_peserta);
$result_peserta = mysqli_stmt_get_result($stmt_peserta);
$data_peserta = mysqli_fetch_assoc($result_peserta);
$jumlah_peserta = $data_peserta['total'];
mysqli_stmt_close($stmt_peserta);

// Tentukan badge berdasarkan kode kelas
$badge_class = 'kelas-badge-1';
if (strpos($kelas['kode_kelas'], '2') !== false) $badge_class = 'kelas-badge-2';
if (strpos($kelas['kode_kelas'], '3') !== false) $badge_class = 'kelas-badge-3';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Kelas - BPJS Kesehatan</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --bpjs-primary: #0077C8;
            --bpjs-secondary: #00A9E0;
            --bpjs-light: #E6F4FF;
            --bpjs-dark: #0056A3;
            --bpjs-success: #28a745;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 20px;
        }
        
        .navbar-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            margin-bottom: 30px;
            padding: 15px 20px;
        }
        
        .logo-bpjs {
            color: white;
            font-weight: 700;
            font-size: 1.4rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .logo-bpjs i {
            margin-right: 10px;
            font-size: 1.6rem;
        }
        
        .card-bpjs {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
            padding: 20px;
            border-bottom: none;
        }
        
        .kelas-badge {
            font-size: 0.85rem;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
            display: inline-block;
        }
        
        .kelas-badge-1 {
            background-color: var(--bpjs-primary);
        }
        
        .kelas-badge-2 {
            background-color: var(--bpjs-secondary);
        }
        
        .kelas-badge-3 {
            background-color: var(--bpjs-dark);
        }
        
        .fasilitas-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .fasilitas-item:last-child {
            border-bottom: none;
        }
        
        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        
        .text-bpjs-primary {
            color: var(--bpjs-primary) !important;
        }
        
        .text-bpjs-secondary {
            color: var(--bpjs-secondary) !important;
        }
        
        .bg-bpjs-light {
            background-color: var(--bpjs-light) !important;
        }
        
        .btn-bpjs-primary {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-bpjs-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 119, 200, 0.3);
            color: white;
        }
        
        .btn-bpjs-secondary {
            background-color: white;
            color: var(--bpjs-primary);
            border: 2px solid var(--bpjs-primary);
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-bpjs-secondary:hover {
            background-color: var(--bpjs-primary);
            color: white;
        }
        
        .page-title {
            color: var(--bpjs-primary);
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #6c757d;
            margin-bottom: 25px;
        }
        
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--bpjs-primary);
            transition: transform 0.3s ease;
        }
        
        .info-box:hover {
            transform: translateY(-5px);
        }
        
        .footer-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 40px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .profile-img-small {
            width: 40px;
            height: 40px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }
        
        .back-link {
            color: var(--bpjs-primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            color: var(--bpjs-dark);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--bpjs-dark);
            margin-bottom: 8px;
        }
        
        .form-control, .form-control:focus {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: var(--bpjs-primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 200, 0.1);
        }
        
        .alert-bpjs {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 5px solid #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 5px solid #dc3545;
            color: #721c24;
        }
        
        .fasilitas-preview-box {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .preview-fasilitas-item {
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 4px solid var(--bpjs-success);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
        }
        
        .preview-fasilitas-item i {
            color: var(--bpjs-success);
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .input-group-text {
            font-weight: 600;
        }
        
        .invalid-feedback {
            display: none;
            font-size: 0.875em;
            color: #dc3545;
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
        }
        
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation Header -->
        <div class="navbar-bpjs d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="logo-bpjs">
                <i class="fas fa-heartbeat"></i> BPJS Kesehatan - Edit Kelas
            </a>
            <div class="user-info">
                <img src="../assets/images/profile/male/image_1.png" alt="profile" class="profile-img-small rounded-circle">
                <div>
                    <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                    <small class="opacity-75">BPJS Member</small>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-light ms-3">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>

        <!-- Back Button -->
        <a href="kelas.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Data Kelas
        </a>

        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="page-title">
                <i class="fas fa-edit text-bpjs-primary me-2"></i>
                Edit Kelas BPJS Kesehatan
            </h2>
            <p class="page-subtitle">Perbarui informasi kelas <?php echo htmlspecialchars($kelas['nama_kelas']); ?></p>
        </div>

        <!-- Notifikasi -->
        <?php if ($message): ?>
        <div class="alert-bpjs alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> fa-lg me-3"></i>
                <div><strong><?php echo $message_type == 'success' ? 'Sukses!' : 'Perhatian!'; ?></strong> <?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Detail Card -->
        <div class="card card-bpjs">
            <div class="card-header card-header-bpjs">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Form Edit Kelas
                </h5>
            </div>
            <div class="card-body p-4">
                <!-- Header Info -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h3 class="text-bpjs-primary mb-2"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h3>
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <span class="kelas-badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($kelas['kode_kelas']); ?>
                            </span>
                            <span class="text-muted">
                                <i class="far fa-calendar-alt me-1"></i>
                                Dibuat: <?php echo $created_at; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="info-box d-inline-block text-start">
                            <h6 class="mb-2">Jumlah Peserta</h6>
                            <h3 class="text-info mb-0"><?php echo $jumlah_peserta; ?></h3>
                            <small class="text-muted">Peserta terdaftar</small>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" id="editForm">
                    <div class="row">
                        <!-- Kode dan Nama Kelas -->
                        <div class="col-md-6 mb-4">
                            <label for="kode_kelas" class="form-label">Kode Kelas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="kode_kelas" name="kode_kelas" 
                                   value="<?php echo htmlspecialchars($kelas['kode_kelas']); ?>" 
                                   required maxlength="10" placeholder="Contoh: KLS1">
                            <small class="form-text text-muted">Kode unik untuk identifikasi kelas (3-10 karakter)</small>
                            <div class="invalid-feedback">Kode kelas harus diisi (3-10 karakter)</div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="nama_kelas" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" 
                                   value="<?php echo htmlspecialchars($kelas['nama_kelas']); ?>" 
                                   required maxlength="50" placeholder="Contoh: Kelas 1">
                            <small class="form-text text-muted">Nama lengkap kelas BPJS</small>
                            <div class="invalid-feedback">Nama kelas harus diisi</div>
                        </div>
                    </div>
                    
                    <!-- Iuran per Bulan - PERBAIKAN UTAMA -->
                    <div class="mb-4">
                        <label for="iuran_per_bulan" class="form-label">Iuran per Bulan (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="text" class="form-control" id="iuran_per_bulan" name="iuran_per_bulan" 
                                   value="<?php echo number_format($kelas['iuran_per_bulan'], 0, ',', '.'); ?>" 
                                   required placeholder="150.000">
                        </div>
                        <small class="form-text text-muted">Iuran bulanan yang harus dibayar peserta (contoh: 150.000)</small>
                        <div class="invalid-feedback">Iuran harus berupa angka positif</div>
                    </div>
                    
                    <!-- Deskripsi -->
                    <div class="mb-4">
                        <label for="deskripsi" class="form-label">Deskripsi Kelas</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" 
                                  rows="4" placeholder="Jelaskan manfaat, keunggulan, dan tujuan kelas ini..."><?php echo htmlspecialchars($kelas['deskripsi']); ?></textarea>
                        <small class="form-text text-muted">Berikan penjelasan yang jelas tentang kelas ini</small>
                    </div>
                    
                    <!-- Fasilitas -->
                    <div class="mb-4">
                        <label for="fasilitas" class="form-label">Fasilitas Kelas <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="fasilitas" name="fasilitas" 
                                  rows="6" required placeholder="Masukkan fasilitas, pisahkan dengan koma atau enter
Contoh: Rawat inap kelas 1, Konsultasi dokter spesialis, Obat generik
Atau:
- Rawat inap kelas 1
- Konsultasi dokter spesialis
- Obat generik"><?php echo htmlspecialchars($kelas['fasilitas']); ?></textarea>
                        <small class="form-text text-muted">Pisahkan setiap fasilitas dengan koma atau baris baru</small>
                        <div class="invalid-feedback">Fasilitas kelas harus diisi</div>
                        
                        <!-- Preview Fasilitas -->
                        <div class="fasilitas-preview-box">
                            <h6 class="text-bpjs-primary mb-3"><i class="fas fa-eye me-2"></i>Preview Fasilitas</h6>
                            <div id="fasilitasPreview">
                                <?php
                                // Pisahkan fasilitas dengan koma atau baris baru
                                $fasilitas_text = $kelas['fasilitas'];
                                $fasilitas_array = preg_split('/[,\n]/', $fasilitas_text);
                                $has_fasilitas = false;
                                
                                foreach ($fasilitas_array as $fasilitas_item):
                                    $trimmed = trim($fasilitas_item);
                                    if (!empty($trimmed) && !in_array($trimmed, ['', '-'])): 
                                        $has_fasilitas = true;
                                ?>
                                <div class="preview-fasilitas-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span><?php echo htmlspecialchars($trimmed); ?></span>
                                </div>
                                <?php 
                                    endif;
                                endforeach;
                                
                                if (!$has_fasilitas):
                                ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                                    <p class="mb-0">Belum ada fasilitas yang ditambahkan</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Sistem -->
                    <div class="mb-4">
                        <label class="form-label">Informasi Sistem</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-control-plaintext bg-light p-3 rounded">
                                    <small class="text-muted">Dibuat:</small>
                                    <br>
                                    <i class="far fa-calendar-plus text-bpjs-primary me-2"></i>
                                    <?php echo date('d M Y H:i:s', strtotime($kelas['created_at'])); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-control-plaintext bg-light p-3 rounded">
                                    <small class="text-muted">Terakhir Diperbarui:</small>
                                    <br>
                                    <i class="fas fa-sync-alt text-bpjs-primary me-2"></i>
                                    <?php echo date('d M Y H:i:s', strtotime($kelas['updated_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between pt-4 border-top">
                        <div>
                            <a href="detail_kelas.php?id=<?php echo $id; ?>" class="btn btn-bpjs-secondary me-3">
                                <i class="fas fa-eye me-2"></i> Lihat Detail
                            </a>
                            <a href="kelas.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Batal
                            </a>
                        </div>
                        <button type="submit" name="update" class="btn btn-bpjs-primary">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer-bpjs">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">BPJS Kesehatan System &copy; <?php echo date('Y'); ?></p>
                    <small>Edit Kelas - <?php echo htmlspecialchars($kelas['nama_kelas']); ?></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>Memberikan yang terbaik untuk kesehatan Anda</small>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom Script -->
    <script>
    $(document).ready(function() {
        // Format angka dengan pemisah ribuan
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Hapus format angka (hapus titik)
        function unformatNumber(num) {
            return num.toString().replace(/\./g, '');
        }
        
        // Format iuran saat halaman dimuat
        let iuranValue = $('#iuran_per_bulan').val();
        if (iuranValue) {
            let cleanValue = unformatNumber(iuranValue);
            if (cleanValue) {
                $('#iuran_per_bulan').val(formatNumber(cleanValue));
            }
        }
        
        // Format input iuran saat diketik
        $('#iuran_per_bulan').on('input', function() {
            let value = $(this).val().replace(/[^0-9]/g, '');
            if (value !== '') {
                $(this).val(formatNumber(value));
            }
        });
        
        // Preview fasilitas secara real-time
        $('#fasilitas').on('input', function() {
            const fasilitasText = $(this).val();
            // Pisahkan dengan koma atau baris baru
            const fasilitasArray = fasilitasText.split(/[,\n]/).map(item => item.trim()).filter(item => {
                return item !== '' && item !== '-';
            });
            
            let previewHtml = '';
            
            if (fasilitasArray.length > 0) {
                fasilitasArray.forEach(function(item) {
                    // Hilangkan bullet points jika ada
                    const cleanItem = item.replace(/^[-\*]\s*/, '');
                    previewHtml += `
                    <div class="preview-fasilitas-item">
                        <i class="fas fa-check-circle"></i>
                        <span>${cleanItem}</span>
                    </div>`;
                });
            } else {
                previewHtml = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <p class="mb-0">Belum ada fasilitas yang ditambahkan</p>
                </div>`;
            }
            
            $('#fasilitasPreview').html(previewHtml);
        });
        
        // Validasi form
        $('#editForm').on('submit', function(e) {
            const kode = $('#kode_kelas').val().trim();
            const nama = $('#nama_kelas').val().trim();
            const iuran = $('#iuran_per_bulan').val().replace(/\./g, '');
            const fasilitas = $('#fasilitas').val().trim();
            
            // Reset error styles
            $('.form-control').removeClass('is-invalid');
            
            let hasError = false;
            
            // Validasi kode kelas
            if (!kode) {
                $('#kode_kelas').addClass('is-invalid');
                hasError = true;
            } else if (kode.length < 3 || kode.length > 10) {
                $('#kode_kelas').addClass('is-invalid');
                hasError = true;
            }
            
            // Validasi nama kelas
            if (!nama) {
                $('#nama_kelas').addClass('is-invalid');
                hasError = true;
            }
            
            // Validasi iuran
            if (!iuran || isNaN(iuran) || parseInt(iuran) <= 0) {
                $('#iuran_per_bulan').addClass('is-invalid');
                hasError = true;
            }
            
            // Validasi fasilitas
            if (!fasilitas) {
                $('#fasilitas').addClass('is-invalid');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                alert('Harap lengkapi semua field yang wajib diisi dengan benar!');
                return false;
            }
            
            // Hapus format iuran sebelum submit
            $('#iuran_per_bulan').val(unformatNumber($('#iuran_per_bulan').val()));
            
            // Konfirmasi sebelum submit
            return confirm('Apakah Anda yakin ingin menyimpan perubahan data kelas?');
        });
        
        // Auto-hide alert setelah 5 detik
        <?php if ($message): ?>
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        <?php endif; ?>
        
        // Add animation to info boxes
        $('.info-box').hover(
            function() {
                $(this).css('transform', 'translateY(-5px)');
            },
            function() {
                $(this).css('transform', 'translateY(0)');
            }
        );
        
        // Focus on first field
        $('#kode_kelas').focus();
    });
    </script>
</body>
</html>