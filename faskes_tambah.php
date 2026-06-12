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
$kode_faskes = $nama_faskes = $jenis_faskes = $alamat = $kota = $provinsi = $kode_pos = $no_telepon = $email = $website = $direktur = $status = '';

// Jenis faskes options
$jenis_options = [
    'Rumah Sakit' => 'Rumah Sakit',
    'Puskesmas' => 'Puskesmas',
    'Klinik' => 'Klinik',
    'Apotek' => 'Apotek',
    'Laboratorium' => 'Laboratorium',
    'Dokter Praktek' => 'Dokter Praktek',
    'Bidan Praktek' => 'Bidan Praktek'
];

// Provinsi options (contoh)
$provinsi_options = [
    'DKI Jakarta' => 'DKI Jakarta',
    'Jawa Barat' => 'Jawa Barat',
    'Jawa Tengah' => 'Jawa Tengah',
    'Jawa Timur' => 'Jawa Timur',
    'Banten' => 'Banten',
    'Bali' => 'Bali',
    'Sumatera Utara' => 'Sumatera Utara',
    'Sumatera Barat' => 'Sumatera Barat',
    'Riau' => 'Riau',
    'Kalimantan Timur' => 'Kalimantan Timur',
    'Sulawesi Selatan' => 'Sulawesi Selatan',
    'Papua' => 'Papua'
];

// Kota options berdasarkan provinsi (contoh)
$kota_options = [
    'DKI Jakarta' => ['Jakarta Pusat', 'Jakarta Utara', 'Jakarta Timur', 'Jakarta Selatan', 'Jakarta Barat'],
    'Jawa Barat' => ['Bandung', 'Bogor', 'Depok', 'Bekasi', 'Cimahi', 'Tasikmalaya', 'Cirebon'],
    'Jawa Tengah' => ['Semarang', 'Surakarta', 'Salatiga', 'Pekalongan', 'Tegal'],
    'Jawa Timur' => ['Surabaya', 'Malang', 'Sidoarjo', 'Mojokerto', 'Jember'],
    'Banten' => ['Tangerang', 'Serang', 'Cilegon', 'Tangerang Selatan'],
    'Bali' => ['Denpasar', 'Badung', 'Gianyar', 'Klungkung'],
    'Sumatera Utara' => ['Medan', 'Binjai', 'Pematang Siantar', 'Tebing Tinggi'],
    'Sumatera Barat' => ['Padang', 'Bukittinggi', 'Payakumbuh', 'Solok'],
    'Riau' => ['Pekanbaru', 'Dumai', 'Bengkalis', 'Rengat'],
    'Kalimantan Timur' => ['Samarinda', 'Balikpapan', 'Bontang', 'Tenggarong'],
    'Sulawesi Selatan' => ['Makassar', 'Parepare', 'Palopo', 'Bulukumba'],
    'Papua' => ['Jayapura', 'Merauke', 'Biak', 'Timika']
];

// Status options
$status_options = [
    'aktif' => 'Aktif',
    'tidak aktif' => 'Tidak Aktif'
];

// Proses form jika ada POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan sanitize data
    $kode_faskes = mysqli_real_escape_string($conn, trim($_POST['kode_faskes']));
    $nama_faskes = mysqli_real_escape_string($conn, trim($_POST['nama_faskes']));
    $jenis_faskes = mysqli_real_escape_string($conn, trim($_POST['jenis_faskes']));
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat']));
    $kota = mysqli_real_escape_string($conn, trim($_POST['kota']));
    $provinsi = mysqli_real_escape_string($conn, trim($_POST['provinsi']));
    $kode_pos = mysqli_real_escape_string($conn, trim($_POST['kode_pos']));
    $no_telepon = mysqli_real_escape_string($conn, trim($_POST['no_telepon']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $website = mysqli_real_escape_string($conn, trim($_POST['website']));
    $direktur = mysqli_real_escape_string($conn, trim($_POST['direktur']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    
    // Validasi
    if (empty($kode_faskes)) {
        $errors['kode_faskes'] = 'Kode faskes wajib diisi';
    } else {
        // Cek apakah kode faskes sudah ada
        $check_sql = "SELECT id FROM faskes WHERE kode_faskes = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $kode_faskes);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $errors['kode_faskes'] = 'Kode faskes sudah terdaftar';
        }
        mysqli_stmt_close($check_stmt);
    }
    
    if (empty($nama_faskes)) {
        $errors['nama_faskes'] = 'Nama faskes wajib diisi';
    }
    
    if (empty($jenis_faskes) || !array_key_exists($jenis_faskes, $jenis_options)) {
        $errors['jenis_faskes'] = 'Jenis faskes tidak valid';
    }
    
    if (empty($alamat)) {
        $errors['alamat'] = 'Alamat wajib diisi';
    }
    
    if (empty($kota)) {
        $errors['kota'] = 'Kota wajib diisi';
    }
    
    if (empty($provinsi) || !array_key_exists($provinsi, $provinsi_options)) {
        $errors['provinsi'] = 'Provinsi tidak valid';
    }
    
    if (!empty($kode_pos) && !preg_match('/^[0-9]{5}$/', $kode_pos)) {
        $errors['kode_pos'] = 'Kode pos harus 5 digit angka';
    }
    
    if (empty($no_telepon)) {
        $errors['no_telepon'] = 'Nomor telepon wajib diisi';
    } elseif (!preg_match('/^[0-9+\-\s()]{10,15}$/', $no_telepon)) {
        $errors['no_telepon'] = 'Format nomor telepon tidak valid';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid';
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors['website'] = 'Format website tidak valid';
    }
    
    if (empty($status) || !array_key_exists($status, $status_options)) {
        $errors['status'] = 'Status tidak valid';
    }
    
    // Validasi kota sesuai provinsi
    if (!empty($provinsi) && !empty($kota) && isset($kota_options[$provinsi])) {
        if (!in_array($kota, $kota_options[$provinsi])) {
            $errors['kota'] = 'Kota tidak sesuai dengan provinsi yang dipilih';
        }
    }
    
    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        // Prepare SQL statement sesuai struktur tabel
        $sql = "INSERT INTO faskes (kode_faskes, nama_faskes, jenis_faskes, alamat, kota, provinsi, kode_pos, no_telepon, email, website, direktur, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssssss", 
            $kode_faskes, 
            $nama_faskes, 
            $jenis_faskes, 
            $alamat, 
            $kota, 
            $provinsi, 
            $kode_pos, 
            $no_telepon, 
            $email, 
            $website, 
            $direktur, 
            $status
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
            $_SESSION['success'] = 'Data faskes berhasil ditambahkan!';
            
            // Redirect ke halaman faskes
            header("Location: faskes.php");
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
    <title>Tambah Faskes - BPJS</title>
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
            <div class="col-lg-11">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="page-title">
                            <i class="fas fa-hospital mr-2"></i> Tambah Faskes Baru
                        </h2>
                        <p class="page-subtitle">Tambah data fasilitas kesehatan baru ke sistem BPJS</p>
                    </div>
                    <a href="faskes.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Data Faskes
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
                    <p class="mb-0">Pastikan data yang dimasukkan sudah benar dan sesuai. Kolom dengan tanda (*) wajib diisi.</p>
                </div>

                <!-- Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Form Tambah Faskes</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="faskesForm">
                            <!-- Informasi Umum -->
                            <div class="form-section">
                                <h6><i class="fas fa-info-circle mr-2"></i> Informasi Umum</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <!-- Kode Faskes -->
                                        <div class="form-group">
                                            <label for="kode_faskes" class="required">Kode Faskes</label>
                                            <input type="text" class="form-control <?php echo isset($errors['kode_faskes']) ? 'is-invalid' : ''; ?>" 
                                                   id="kode_faskes" name="kode_faskes" 
                                                   value="<?php echo htmlspecialchars($kode_faskes); ?>" 
                                                   placeholder="Contoh: FSK001" maxlength="20" required>
                                            <?php if(isset($errors['kode_faskes'])): ?>
                                                <div class="error-message"><?php echo $errors['kode_faskes']; ?></div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">Kode unik untuk identifikasi faskes</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <!-- Nama Faskes -->
                                        <div class="form-group">
                                            <label for="nama_faskes" class="required">Nama Faskes</label>
                                            <input type="text" class="form-control <?php echo isset($errors['nama_faskes']) ? 'is-invalid' : ''; ?>" 
                                                   id="nama_faskes" name="nama_faskes" 
                                                   value="<?php echo htmlspecialchars($nama_faskes); ?>" 
                                                   placeholder="Nama lengkap faskes" required>
                                            <?php if(isset($errors['nama_faskes'])): ?>
                                                <div class="error-message"><?php echo $errors['nama_faskes']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Jenis Faskes -->
                                        <div class="form-group">
                                            <label for="jenis_faskes" class="required">Jenis Faskes</label>
                                            <select class="form-control <?php echo isset($errors['jenis_faskes']) ? 'is-invalid' : ''; ?>" 
                                                    id="jenis_faskes" name="jenis_faskes" required>
                                                <option value="">-- Pilih Jenis Faskes --</option>
                                                <?php foreach($jenis_options as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                        <?php echo ($jenis_faskes == $value) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if(isset($errors['jenis_faskes'])): ?>
                                                <div class="error-message"><?php echo $errors['jenis_faskes']; ?></div>
                                            <?php endif; ?>
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
                            </div>

                            <!-- Alamat -->
                            <div class="form-section">
                                <h6><i class="fas fa-map-marker-alt mr-2"></i> Alamat</h6>
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- Alamat -->
                                        <div class="form-group">
                                            <label for="alamat" class="required">Alamat Lengkap</label>
                                            <textarea class="form-control <?php echo isset($errors['alamat']) ? 'is-invalid' : ''; ?>" 
                                                      id="alamat" name="alamat" rows="3" 
                                                      placeholder="Jalan, Nomor, RT/RW, Kelurahan" required><?php echo htmlspecialchars($alamat); ?></textarea>
                                            <?php if(isset($errors['alamat'])): ?>
                                                <div class="error-message"><?php echo $errors['alamat']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Provinsi -->
                                        <div class="form-group">
                                            <label for="provinsi" class="required">Provinsi</label>
                                            <select class="form-control <?php echo isset($errors['provinsi']) ? 'is-invalid' : ''; ?>" 
                                                    id="provinsi" name="provinsi" required>
                                                <option value="">-- Pilih Provinsi --</option>
                                                <?php foreach($provinsi_options as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                        <?php echo ($provinsi == $value) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if(isset($errors['provinsi'])): ?>
                                                <div class="error-message"><?php echo $errors['provinsi']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Kota -->
                                        <div class="form-group">
                                            <label for="kota" class="required">Kota/Kabupaten</label>
                                            <select class="form-control <?php echo isset($errors['kota']) ? 'is-invalid' : ''; ?>" 
                                                    id="kota" name="kota" required>
                                                <option value="">-- Pilih Kota --</option>
                                                <?php 
                                                if (!empty($provinsi) && isset($kota_options[$provinsi])) {
                                                    foreach($kota_options[$provinsi] as $kota_item): ?>
                                                        <option value="<?php echo $kota_item; ?>" 
                                                            <?php echo ($kota == $kota_item) ? 'selected' : ''; ?>>
                                                            <?php echo $kota_item; ?>
                                                        </option>
                                                    <?php endforeach;
                                                }
                                                ?>
                                            </select>
                                            <?php if(isset($errors['kota'])): ?>
                                                <div class="error-message"><?php echo $errors['kota']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Kode Pos -->
                                        <div class="form-group">
                                            <label for="kode_pos">Kode Pos</label>
                                            <input type="text" class="form-control <?php echo isset($errors['kode_pos']) ? 'is-invalid' : ''; ?>" 
                                                   id="kode_pos" name="kode_pos" 
                                                   value="<?php echo htmlspecialchars($kode_pos); ?>" 
                                                   placeholder="Contoh: 12345" maxlength="10">
                                            <?php if(isset($errors['kode_pos'])): ?>
                                                <div class="error-message"><?php echo $errors['kode_pos']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kontak -->
                            <div class="form-section">
                                <h6><i class="fas fa-phone-alt mr-2"></i> Kontak</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- No Telepon -->
                                        <div class="form-group">
                                            <label for="no_telepon" class="required">Nomor Telepon</label>
                                            <input type="text" class="form-control <?php echo isset($errors['no_telepon']) ? 'is-invalid' : ''; ?>" 
                                                   id="no_telepon" name="no_telepon" 
                                                   value="<?php echo htmlspecialchars($no_telepon); ?>" 
                                                   placeholder="Contoh: 021-1234567" required>
                                            <?php if(isset($errors['no_telepon'])): ?>
                                                <div class="error-message"><?php echo $errors['no_telepon']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Email -->
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                                   id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($email); ?>" 
                                                   placeholder="email@contoh.com">
                                            <?php if(isset($errors['email'])): ?>
                                                <div class="error-message"><?php echo $errors['email']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Website -->
                                        <div class="form-group">
                                            <label for="website">Website</label>
                                            <input type="text" class="form-control <?php echo isset($errors['website']) ? 'is-invalid' : ''; ?>" 
                                                   id="website" name="website" 
                                                   value="<?php echo htmlspecialchars($website); ?>" 
                                                   placeholder="https://www.contoh.com">
                                            <?php if(isset($errors['website'])): ?>
                                                <div class="error-message"><?php echo $errors['website']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Direktur -->
                                        <div class="form-group">
                                            <label for="direktur">Nama Direktur/Pimpinan</label>
                                            <input type="text" class="form-control <?php echo isset($errors['direktur']) ? 'is-invalid' : ''; ?>" 
                                                   id="direktur" name="direktur" 
                                                   value="<?php echo htmlspecialchars($direktur); ?>" 
                                                   placeholder="Nama lengkap direktur">
                                            <?php if(isset($errors['direktur'])): ?>
                                                <div class="error-message"><?php echo $errors['direktur']; ?></div>
                                            <?php endif; ?>
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
                                        <a href="faskes.php" class="btn btn-outline-danger btn-lg">
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
        });

        // Form validation
        $(document).ready(function() {
            // Update kota dropdown berdasarkan provinsi
            $('#provinsi').change(function() {
                const provinsi = $(this).val();
                const kotaSelect = $('#kota');
                
                // Reset kota options
                kotaSelect.html('<option value="">-- Pilih Kota --</option>');
                
                // Define kota options
                const kotaOptions = {
                    'DKI Jakarta': ['Jakarta Pusat', 'Jakarta Utara', 'Jakarta Timur', 'Jakarta Selatan', 'Jakarta Barat'],
                    'Jawa Barat': ['Bandung', 'Bogor', 'Depok', 'Bekasi', 'Cimahi', 'Tasikmalaya', 'Cirebon'],
                    'Jawa Tengah': ['Semarang', 'Surakarta', 'Salatiga', 'Pekalongan', 'Tegal'],
                    'Jawa Timur': ['Surabaya', 'Malang', 'Sidoarjo', 'Mojokerto', 'Jember'],
                    'Banten': ['Tangerang', 'Serang', 'Cilegon', 'Tangerang Selatan'],
                    'Bali': ['Denpasar', 'Badung', 'Gianyar', 'Klungkung'],
                    'Sumatera Utara': ['Medan', 'Binjai', 'Pematang Siantar', 'Tebing Tinggi'],
                    'Sumatera Barat': ['Padang', 'Bukittinggi', 'Payakumbuh', 'Solok'],
                    'Riau': ['Pekanbaru', 'Dumai', 'Bengkalis', 'Rengat'],
                    'Kalimantan Timur': ['Samarinda', 'Balikpapan', 'Bontang', 'Tenggarong'],
                    'Sulawesi Selatan': ['Makassar', 'Parepare', 'Palopo', 'Bulukumba'],
                    'Papua': ['Jayapura', 'Merauke', 'Biak', 'Timika']
                };
                
                // Populate kota options
                if (provinsi && kotaOptions[provinsi]) {
                    kotaOptions[provinsi].forEach(function(kota) {
                        kotaSelect.append('<option value="' + kota + '">' + kota + '</option>');
                    });
                }
            });
            
            $('#faskesForm').submit(function(e) {
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
                
                // Validate email if provided
                const email = $('#email').val().trim();
                if (email && !isValidEmail(email)) {
                    $('#email').addClass('is-invalid');
                    $('#email').after('<div class="error-message">Format email tidak valid</div>');
                    isValid = false;
                }
                
                // Validate website if provided
                const website = $('#website').val().trim();
                if (website && !isValidUrl(website)) {
                    $('#website').addClass('is-invalid');
                    $('#website').after('<div class="error-message">Format website tidak valid</div>');
                    isValid = false;
                }
                
                // Validate kode pos if provided
                const kodePos = $('#kode_pos').val().trim();
                if (kodePos && !/^[0-9]{5}$/.test(kodePos)) {
                    $('#kode_pos').addClass('is-invalid');
                    $('#kode_pos').after('<div class="error-message">Kode pos harus 5 digit angka</div>');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    $('.is-invalid').first().focus();
                }
            });
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            function isValidUrl(url) {
                try {
                    new URL(url);
                    return true;
                } catch (_) {
                    return false;
                }
            }
            
            // Format phone number
            $('#no_telepon').on('input', function() {
                let value = $(this).val().replace(/[^0-9+\-\s()]/g, '');
                $(this).val(value);
            });
            
            // Format kode faskes (uppercase)
            $('#kode_faskes').on('input', function() {
                $(this).val($(this).val().toUpperCase());
            });
            
            // Format kode pos (numbers only)
            $('#kode_pos').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9]/g, ''));
            });
        });
    </script>
</body>
</html>