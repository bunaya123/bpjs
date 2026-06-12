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

// Update last activity
$_SESSION['last_activity'] = time();

// Inisialisasi variabel
$errors = [];
$success = false;
$kode_tindakan = $nama_tindakan = $deskripsi = $kategori = $tarif_bpjs = $tarif_non_bpjs = $jenis_tindakan = $unit = $waktu_estimasi = $persyaratan = $status = '';

// Kategori options
$kategori_options = [
    'Konsultasi' => 'Konsultasi',
    'Laboratorium' => 'Laboratorium',
    'Radiologi' => 'Radiologi',
    'Tindakan Minor' => 'Tindakan Minor',
    'Fisioterapi' => 'Fisioterapi',
    'Diagnostik' => 'Diagnostik',
    'Operasi' => 'Operasi',
    'Lainnya' => 'Lainnya'
];

// Jenis tindakan options
$jenis_tindakan_options = [
    'Rawat Jalan' => 'Rawat Jalan',
    'Rawat Inap' => 'Rawat Inap',
    'IGD' => 'IGD',
    'Laboratorium' => 'Laboratorium',
    'Radiologi' => 'Radiologi',
    'Fisioterapi' => 'Fisioterapi'
];

// Unit options
$unit_options = [
    'Poliklinik Umum' => 'Poliklinik Umum',
    'Poliklinik Spesialis' => 'Poliklinik Spesialis',
    'Laboratorium' => 'Laboratorium',
    'Radiologi' => 'Radiologi',
    'IGD' => 'IGD',
    'Fisioterapi' => 'Fisioterapi',
    'Kardiologi' => 'Kardiologi',
    'Mata' => 'Mata',
    'Lainnya' => 'Lainnya'
];

// Status options
$status_options = [
    'aktif' => 'Aktif',
    'tidak aktif' => 'Tidak Aktif'
];

// Proses form jika ada POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan sanitize data
    $kode_tindakan = mysqli_real_escape_string($conn, trim($_POST['kode_tindakan']));
    $nama_tindakan = mysqli_real_escape_string($conn, trim($_POST['nama_tindakan']));
    $deskripsi = mysqli_real_escape_string($conn, trim($_POST['deskripsi']));
    $kategori = mysqli_real_escape_string($conn, trim($_POST['kategori']));
    $tarif_bpjs_input = trim($_POST['tarif_bpjs']);
    $tarif_non_bpjs_input = trim($_POST['tarif_non_bpjs']);
    $jenis_tindakan = mysqli_real_escape_string($conn, trim($_POST['jenis_tindakan']));
    $unit = mysqli_real_escape_string($conn, trim($_POST['unit']));
    $waktu_estimasi = mysqli_real_escape_string($conn, trim($_POST['waktu_estimasi']));
    $persyaratan = mysqli_real_escape_string($conn, trim($_POST['persyaratan']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    
    // Simpan input asli untuk ditampilkan kembali
    $tarif_bpjs = $tarif_bpjs_input;
    $tarif_non_bpjs = $tarif_non_bpjs_input;
    
    // Hapus titik pemisah ribuan untuk validasi numerik
    $tarif_bpjs_numeric = str_replace('.', '', $tarif_bpjs_input);
    $tarif_non_bpjs_numeric = str_replace('.', '', $tarif_non_bpjs_input);
    
    // Validasi
    if (empty($kode_tindakan)) {
        $errors['kode_tindakan'] = 'Kode tindakan wajib diisi';
    } else {
        // Cek apakah kode tindakan sudah ada
        $check_sql = "SELECT id FROM tindakan WHERE kode_tindakan = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $kode_tindakan);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $errors['kode_tindakan'] = 'Kode tindakan sudah terdaftar';
        }
        mysqli_stmt_close($check_stmt);
    }
    
    if (empty($nama_tindakan)) {
        $errors['nama_tindakan'] = 'Nama tindakan wajib diisi';
    }
    
    if (empty($deskripsi)) {
        $errors['deskripsi'] = 'Deskripsi wajib diisi';
    }
    
    if (empty($kategori) || !array_key_exists($kategori, $kategori_options)) {
        $errors['kategori'] = 'Kategori tidak valid';
    }
    
    // Validasi tarif BPJS - lebih fleksibel
    if (empty($tarif_bpjs_input)) {
        $errors['tarif_bpjs'] = 'Tarif BPJS wajib diisi';
    } elseif (!preg_match('/^[0-9.,]+$/', $tarif_bpjs_input)) {
        $errors['tarif_bpjs'] = 'Tarif BPJS harus berupa angka';
    } elseif ((float)$tarif_bpjs_numeric < 0) {
        $errors['tarif_bpjs'] = 'Tarif BPJS tidak boleh negatif';
    }
    
    // Validasi tarif Non BPJS - lebih fleksibel
    if (empty($tarif_non_bpjs_input)) {
        $errors['tarif_non_bpjs'] = 'Tarif Non BPJS wajib diisi';
    } elseif (!preg_match('/^[0-9.,]+$/', $tarif_non_bpjs_input)) {
        $errors['tarif_non_bpjs'] = 'Tarif Non BPJS harus berupa angka';
    } elseif ((float)$tarif_non_bpjs_numeric < 0) {
        $errors['tarif_non_bpjs'] = 'Tarif Non BPJS tidak boleh negatif';
    }
    
    if (empty($jenis_tindakan) || !array_key_exists($jenis_tindakan, $jenis_tindakan_options)) {
        $errors['jenis_tindakan'] = 'Jenis tindakan tidak valid';
    }
    
    if (!empty($waktu_estimasi) && (!is_numeric($waktu_estimasi) || $waktu_estimasi < 0)) {
        $errors['waktu_estimasi'] = 'Waktu estimasi harus angka dan tidak boleh negatif';
    }
    
    if (empty($status) || !array_key_exists($status, $status_options)) {
        $errors['status'] = 'Status tidak valid';
    }
    
    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        // Convert numeric values - hapus semua non-digit kecuali koma desimal
        $tarif_bpjs_value = (float) preg_replace('/[^\d]/', '', $tarif_bpjs_input);
        $tarif_non_bpjs_value = (float) preg_replace('/[^\d]/', '', $tarif_non_bpjs_input);
        $waktu_estimasi = !empty($waktu_estimasi) ? (int)$waktu_estimasi : NULL;
        
        // Prepare SQL statement
        $sql = "INSERT INTO tindakan (kode_tindakan, nama_tindakan, deskripsi, kategori, tarif_bpjs, tarif_non_bpjs, jenis_tindakan, unit, waktu_estimasi, persyaratan, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssddssiss", 
            $kode_tindakan, 
            $nama_tindakan, 
            $deskripsi, 
            $kategori, 
            $tarif_bpjs_value, 
            $tarif_non_bpjs_value, 
            $jenis_tindakan, 
            $unit, 
            $waktu_estimasi, 
            $persyaratan, 
            $status
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
            $_SESSION['success'] = 'Data tindakan berhasil ditambahkan!';
            
            // Redirect ke halaman tindakan
            header("Location: tindakan.php");
            exit();
        } else {
            $errors['database'] = 'Terjadi kesalahan saat menyimpan data: ' . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Tambah Tindakan - BPJS</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            padding: 15px 20px;
        }
        .form-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        .btn-primary {
            background-color: #0066cc;
            border-color: #0066cc;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        .page-title {
            color: #0066cc;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .page-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .form-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .form-section h6 {
            color: #0066cc;
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 8px;
        }
        .tarif-box {
            background-color: #e8f5e8;
            border: 1px solid #c8e6c9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .tarif-label {
            color: #2e7d32;
            font-weight: 600;
        }
        .input-group-text {
            background-color: #f8f9fa;
            color: #495057;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-heartbeat mr-2"></i>
                <strong>BPJS KESEHATAN</strong>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                            <i class="fas fa-user-circle mr-1"></i>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user mr-2"></i> Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="page-title">
                            <i class="fas fa-procedures mr-2"></i> Tambah Tindakan Baru
                        </h2>
                        <p class="page-subtitle">Tambah data tindakan medis baru ke sistem BPJS</p>
                    </div>
                    <a href="tindakan.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Data Tindakan
                    </a>
                </div>

                <!-- Messages -->
                <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Info Box -->
                <div class="info-box">
                    <h6><i class="fas fa-info-circle mr-2"></i> Informasi Penting</h6>
                    <p class="mb-0">Pastikan data yang dimasukkan sudah benar dan sesuai. Kolom dengan tanda (*) wajib diisi. Untuk tarif, gunakan format angka (contoh: 1.500.000 untuk satu juta lima ratus ribu).</p>
                </div>

                <!-- Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Form Tambah Tindakan</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="tindakan_tambah.php" id="tindakanForm">
                            <!-- Informasi Umum -->
                            <div class="form-section">
                                <h6><i class="fas fa-info-circle mr-2"></i> Informasi Umum</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Kode Tindakan -->
                                        <div class="form-group">
                                            <label for="kode_tindakan" class="required">Kode Tindakan</label>
                                            <input type="text" class="form-control <?php echo isset($errors['kode_tindakan']) ? 'is-invalid' : ''; ?>" 
                                                   id="kode_tindakan" name="kode_tindakan" 
                                                   value="<?php echo htmlspecialchars($kode_tindakan); ?>" 
                                                   placeholder="Contoh: TDK001" maxlength="20" required>
                                            <?php if(isset($errors['kode_tindakan'])): ?>
                                                <div class="error-message"><?php echo $errors['kode_tindakan']; ?></div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">Kode unik untuk identifikasi tindakan</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Status -->
                                        <div class="form-group">
                                            <label for="status" class="required">Status</label>
                                            <select class="form-control <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                                    id="status" name="status" required>
                                                <option value="">-- Pilih Status --</option>
                                                <?php foreach($status_options as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                        <?php echo ($status == $value) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if(isset($errors['status'])): ?>
                                                <div class="error-message"><?php echo $errors['status']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- Nama Tindakan -->
                                        <div class="form-group">
                                            <label for="nama_tindakan" class="required">Nama Tindakan</label>
                                            <input type="text" class="form-control <?php echo isset($errors['nama_tindakan']) ? 'is-invalid' : ''; ?>" 
                                                   id="nama_tindakan" name="nama_tindakan" 
                                                   value="<?php echo htmlspecialchars($nama_tindakan); ?>" 
                                                   placeholder="Nama lengkap tindakan medis" required>
                                            <?php if(isset($errors['nama_tindakan'])): ?>
                                                <div class="error-message"><?php echo $errors['nama_tindakan']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- Deskripsi -->
                                        <div class="form-group">
                                            <label for="deskripsi" class="required">Deskripsi Tindakan</label>
                                            <textarea class="form-control <?php echo isset($errors['deskripsi']) ? 'is-invalid' : ''; ?>" 
                                                      id="deskripsi" name="deskripsi" rows="3" 
                                                      placeholder="Deskripsi lengkap tentang tindakan medis ini" required><?php echo htmlspecialchars($deskripsi); ?></textarea>
                                            <?php if(isset($errors['deskripsi'])): ?>
                                                <div class="error-message"><?php echo $errors['deskripsi']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kategori dan Jenis -->
                            <div class="form-section">
                                <h6><i class="fas fa-tags mr-2"></i> Kategori dan Jenis</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Kategori -->
                                        <div class="form-group">
                                            <label for="kategori" class="required">Kategori</label>
                                            <select class="form-control <?php echo isset($errors['kategori']) ? 'is-invalid' : ''; ?>" 
                                                    id="kategori" name="kategori" required>
                                                <option value="">-- Pilih Kategori --</option>
                                                <?php foreach($kategori_options as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                        <?php echo ($kategori == $value) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if(isset($errors['kategori'])): ?>
                                                <div class="error-message"><?php echo $errors['kategori']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Jenis Tindakan -->
                                        <div class="form-group">
                                            <label for="jenis_tindakan" class="required">Jenis Tindakan</label>
                                            <select class="form-control <?php echo isset($errors['jenis_tindakan']) ? 'is-invalid' : ''; ?>" 
                                                    id="jenis_tindakan" name="jenis_tindakan" required>
                                                <option value="">-- Pilih Jenis Tindakan --</option>
                                                <?php foreach($jenis_tindakan_options as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                        <?php echo ($jenis_tindakan == $value) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if(isset($errors['jenis_tindakan'])): ?>
                                                <div class="error-message"><?php echo $errors['jenis_tindakan']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Unit -->
                                        <div class="form-group">
                                            <label for="unit">Unit/Lokasi</label>
                                            <select class="form-control <?php echo isset($errors['unit']) ? 'is-invalid' : ''; ?>" 
                                                    id="unit" name="unit">
                                                <option value="">-- Pilih Unit --</option>
                                                <?php foreach($unit_options as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                        <?php echo ($unit == $value) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if(isset($errors['unit'])): ?>
                                                <div class="error-message"><?php echo $errors['unit']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Waktu Estimasi -->
                                        <div class="form-group">
                                            <label for="waktu_estimasi">Waktu Estimasi (menit)</label>
                                            <input type="number" class="form-control <?php echo isset($errors['waktu_estimasi']) ? 'is-invalid' : ''; ?>" 
                                                   id="waktu_estimasi" name="waktu_estimasi" 
                                                   value="<?php echo htmlspecialchars($waktu_estimasi); ?>" 
                                                   placeholder="Contoh: 30" min="0" step="1">
                                            <?php if(isset($errors['waktu_estimasi'])): ?>
                                                <div class="error-message"><?php echo $errors['waktu_estimasi']; ?></div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">Estimasi waktu yang dibutuhkan untuk tindakan</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tarif -->
                            <div class="form-section">
                                <h6><i class="fas fa-money-bill-wave mr-2"></i> Tarif</h6>
                                <div class="tarif-box">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <!-- Tarif BPJS -->
                                            <div class="form-group">
                                                <label for="tarif_bpjs" class="required">Tarif BPJS (Rp)</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">Rp</span>
                                                    </div>
                                                    <input type="text" class="form-control <?php echo isset($errors['tarif_bpjs']) ? 'is-invalid' : ''; ?>" 
                                                           id="tarif_bpjs" name="tarif_bpjs" 
                                                           value="<?php echo htmlspecialchars($tarif_bpjs); ?>" 
                                                           placeholder="Contoh: 1.000.000" required
                                                           onkeyup="formatCurrency(this)">
                                                </div>
                                                <?php if(isset($errors['tarif_bpjs'])): ?>
                                                    <div class="error-message"><?php echo $errors['tarif_bpjs']; ?></div>
                                                <?php endif; ?>
                                                <small class="form-text text-muted">Tarif untuk peserta BPJS. Gunakan titik sebagai pemisah ribuan</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <!-- Tarif Non BPJS -->
                                            <div class="form-group">
                                                <label for="tarif_non_bpjs" class="required">Tarif Non BPJS (Rp)</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">Rp</span>
                                                    </div>
                                                    <input type="text" class="form-control <?php echo isset($errors['tarif_non_bpjs']) ? 'is-invalid' : ''; ?>" 
                                                           id="tarif_non_bpjs" name="tarif_non_bpjs" 
                                                           value="<?php echo htmlspecialchars($tarif_non_bpjs); ?>" 
                                                           placeholder="Contoh: 1.500.000" required
                                                           onkeyup="formatCurrency(this)">
                                                </div>
                                                <?php if(isset($errors['tarif_non_bpjs'])): ?>
                                                    <div class="error-message"><?php echo $errors['tarif_non_bpjs']; ?></div>
                                                <?php endif; ?>
                                                <small class="form-text text-muted">Tarif untuk peserta non BPJS. Gunakan titik sebagai pemisah ribuan</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Persyaratan -->
                            <div class="form-section">
                                <h6><i class="fas fa-clipboard-list mr-2"></i> Persyaratan</h6>
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- Persyaratan -->
                                        <div class="form-group">
                                            <label for="persyaratan">Persyaratan</label>
                                            <textarea class="form-control <?php echo isset($errors['persyaratan']) ? 'is-invalid' : ''; ?>" 
                                                      id="persyaratan" name="persyaratan" rows="3" 
                                                      placeholder="Persyaratan yang diperlukan untuk tindakan ini"><?php echo htmlspecialchars($persyaratan); ?></textarea>
                                            <?php if(isset($errors['persyaratan'])): ?>
                                                <div class="error-message"><?php echo $errors['persyaratan']; ?></div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">Persyaratan khusus yang harus dipenuhi pasien</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Database Error -->
                            <?php if(isset($errors['database'])): ?>
                                <div class="alert alert-danger mt-3">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <?php echo $errors['database']; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Form Actions -->
                            <div class="form-group mt-4 pt-3 border-top">
                                <div class="row">
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save mr-2"></i> Simpan Data
                                        </button>
                                        <button type="reset" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-redo mr-2"></i> Reset Form
                                        </button>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <a href="tindakan.php" class="btn btn-outline-danger btn-lg">
                                            <i class="fas fa-times mr-2"></i> Batal
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Footer -->
                <footer class="mt-4 pt-3 border-top">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="text-muted">
                                &copy; <?php echo date('Y'); ?> BPJS Kesehatan System. All rights reserved.
                            </p>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <small class="text-muted">
                                Member Area | <a href="#" class="text-muted">Privacy Policy</a> | <a href="#" class="text-muted">Terms of Service</a>
                            </small>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Format kode tindakan (uppercase)
            $('#kode_tindakan').on('input', function() {
                $(this).val($(this).val().toUpperCase());
            });
        });

        // Format currency dengan pemisah ribuan
        function formatCurrency(input) {
            // Remove all non-digit characters
            let value = input.value.replace(/[^\d]/g, '');
            
            if (value) {
                // Convert to number
                let num = parseInt(value);
                
                // Format with thousand separators (Indonesian format)
                input.value = num.toLocaleString('id-ID');
            }
        }

        // Remove formatting on focus for easy editing
        function removeFormatting(input) {
            let value = input.value.replace(/[^\d]/g, '');
            input.value = value;
        }

        // Add formatting on blur
        function addFormatting(input) {
            let value = input.value.replace(/[^\d]/g, '');
            if (value) {
                let num = parseInt(value);
                input.value = num.toLocaleString('id-ID');
            }
        }

        // Initialize formatting on page load
        $(document).ready(function() {
            // Auto-format existing values
            $('#tarif_bpjs, #tarif_non_bpjs').each(function() {
                let value = $(this).val().replace(/[^\d]/g, '');
                if (value) {
                    let num = parseInt(value);
                    $(this).val(num.toLocaleString('id-ID'));
                }
            });

            // Add focus/blur events for formatting
            $('#tarif_bpjs, #tarif_non_bpjs')
                .on('focus', function() {
                    removeFormatting(this);
                })
                .on('blur', function() {
                    addFormatting(this);
                });

            // Form validation
            $('#tindakanForm').submit(function(e) {
                let isValid = true;
                
                // Reset error states
                $('.form-control').removeClass('is-invalid');
                $('.error-message').remove();
                
                // Validate required fields
                $('.required').each(function() {
                    const fieldName = $(this).attr('for');
                    const fieldElement = $('#' + fieldName);
                    const fieldValue = fieldElement.val().trim();
                    
                    if (!fieldValue) {
                        fieldElement.addClass('is-invalid');
                        fieldElement.after('<div class="error-message">Kolom ini wajib diisi</div>');
                        isValid = false;
                    }
                });
                
                // Validate tarif fields
                const tarifBpjs = $('#tarif_bpjs').val().replace(/[^\d]/g, '');
                const tarifNonBpjs = $('#tarif_non_bpjs').val().replace(/[^\d]/g, '');
                
                if (!tarifBpjs || tarifBpjs === '') {
                    $('#tarif_bpjs').addClass('is-invalid');
                    $('#tarif_bpjs').after('<div class="error-message">Tarif BPJS wajib diisi</div>');
                    isValid = false;
                } else if (parseInt(tarifBpjs) < 0) {
                    $('#tarif_bpjs').addClass('is-invalid');
                    $('#tarif_bpjs').after('<div class="error-message">Tarif BPJS tidak boleh negatif</div>');
                    isValid = false;
                }
                
                if (!tarifNonBpjs || tarifNonBpjs === '') {
                    $('#tarif_non_bpjs').addClass('is-invalid');
                    $('#tarif_non_bpjs').after('<div class="error-message">Tarif Non BPJS wajib diisi</div>');
                    isValid = false;
                } else if (parseInt(tarifNonBpjs) < 0) {
                    $('#tarif_non_bpjs').addClass('is-invalid');
                    $('#tarif_non_bpjs').after('<div class="error-message">Tarif Non BPJS tidak boleh negatif</div>');
                    isValid = false;
                }
                
                // Validate waktu estimasi if provided
                const waktuEstimasi = $('#waktu_estimasi').val();
                if (waktuEstimasi && (isNaN(waktuEstimasi) || parseInt(waktuEstimasi) < 0)) {
                    $('#waktu_estimasi').addClass('is-invalid');
                    $('#waktu_estimasi').after('<div class="error-message">Waktu estimasi harus angka dan tidak boleh negatif</div>');
                    isValid = false;
                }
                
                // Remove formatting before submission (submit plain numbers)
                if (isValid) {
                    $('#tarif_bpjs, #tarif_non_bpjs').each(function() {
                        let value = $(this).val().replace(/[^\d]/g, '');
                        $(this).val(value);
                    });
                } else {
                    e.preventDefault();
                    // Scroll to first error
                    $('.is-invalid').first().focus();
                }
            });
        });
    </script>
</body>
</html>