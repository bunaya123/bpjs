<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function untuk sanitize input
function sanitize_input($data) {
    if (!is_string($data)) {
        return $data;
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
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

if (!$user) {
    die("Error: User data not found.");
}

// PROSES TAMBAH DOKTER
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_dokter'])) {
    $kode_dokter = isset($_POST['kode_dokter']) ? sanitize_input($_POST['kode_dokter']) : '';
    $nama_dokter = isset($_POST['nama_dokter']) ? sanitize_input($_POST['nama_dokter']) : '';
    $jenis_kelamin = isset($_POST['jenis_kelamin']) ? sanitize_input($_POST['jenis_kelamin']) : '';
    $tempat_lahir = isset($_POST['tempat_lahir']) ? sanitize_input($_POST['tempat_lahir']) : '';
    $tanggal_lahir = isset($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : '';
    $alamat = isset($_POST['alamat']) ? sanitize_input($_POST['alamat']) : '';
    $no_telepon = isset($_POST['no_telepon']) ? sanitize_input($_POST['no_telepon']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $spesialisasi_id = isset($_POST['spesialisasi_id']) ? (int)$_POST['spesialisasi_id'] : 0;
    $no_sip = isset($_POST['no_sip']) ? sanitize_input($_POST['no_sip']) : '';
    $tgl_berlaku_sip = isset($_POST['tgl_berlaku_sip']) ? $_POST['tgl_berlaku_sip'] : '';
    $status = isset($_POST['status']) ? sanitize_input($_POST['status']) : 'aktif';
    
    // Validasi data wajib
    if (empty($nama_dokter) || empty($jenis_kelamin) || empty($status)) {
        $error_message = "❌ Error: Nama dokter, jenis kelamin, dan status harus diisi!";
    } else {
        // Generate kode dokter otomatis jika kosong
        if (empty($kode_dokter)) {
            $result_kode = mysqli_query($conn, "SELECT MAX(kode_dokter) as max_kode FROM dokter");
            $data_kode = mysqli_fetch_assoc($result_kode);
            $max_kode = $data_kode['max_kode'] ?? 'DR000';
            $number = (int)str_replace('DR', '', $max_kode) + 1;
            $kode_dokter = 'DR' . str_pad($number, 3, '0', STR_PAD_LEFT);
        }
        
        // Insert data
        $query = "INSERT INTO dokter (kode_dokter, nama_dokter, jenis_kelamin, tempat_lahir, 
                  tanggal_lahir, alamat, no_telepon, email, spesialisasi_id, no_sip, 
                  tgl_berlaku_sip, status, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt === false) {
            $error_message = "❌ Error preparing statement: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "ssssssssisss", 
                $kode_dokter, 
                $nama_dokter, 
                $jenis_kelamin, 
                $tempat_lahir,
                $tanggal_lahir, 
                $alamat, 
                $no_telepon, 
                $email, 
                $spesialisasi_id,
                $no_sip, 
                $tgl_berlaku_sip, 
                $status
            );
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "✅ Data dokter berhasil ditambahkan!";
                // Reset form jika sukses
                if (empty($error_message)) {
                    $_POST = array();
                }
            } else {
                $error_message = "❌ Error: " . mysqli_error($conn);
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

// Ambil data spesialisasi untuk dropdown
$query_spesialis = "SELECT * FROM spesialisasi_dokter ORDER BY nama_spesialisasi";
$result_spesialis = mysqli_query($conn, $query_spesialis);
$spesialis_data = [];
while ($row = mysqli_fetch_assoc($result_spesialis)) {
    $spesialis_data[] = $row;
}

// Update last activity
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Tambah Dokter - BPJS KESEHATAN</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --bpjs-primary-dark: #003366;
            --bpjs-primary: #0066cc;
            --bpjs-blue: #0077c8;
            --bpjs-green: #43b02a;
            --bpjs-dark: #003087;
            --bpjs-light: #cfd3fdff;
        }
        
        body {
            background: linear-gradient(135deg, #0077c8 0%, #003087 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .container-full {
            max-width: 100%;
            padding: 0;
            margin: 0;
        }
        
        .header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            padding: 25px 40px;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 55, 135, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .back-btn {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .back-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            transform: translateX(-5px);
        }
        
        .form-container {
            background-color: white;
            border-radius: 20px;
            padding: 40px;
            margin: 40px auto;
            max-width: 1200px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-title {
            color: var(--bpjs-dark);
            font-weight: 700;
            margin-bottom: 5px;
            border-left: 5px solid var(--bpjs-blue);
            padding-left: 15px;
        }
        
        .form-subtitle {
            color: #6c757d;
            margin-bottom: 30px;
            padding-left: 20px;
        }
        
        .section-title {
            color: var(--bpjs-blue);
            font-weight: 600;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0, 119, 200, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .input-group-bpjs {
            border: 2px solid rgba(0, 119, 200, 0.1);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .input-group-bpjs:focus-within {
            border-color: var(--bpjs-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 200, 0.1);
        }
        
        .input-group-text-bpjs {
            background-color: rgba(0, 119, 200, 0.05);
            border: none;
            color: var(--bpjs-blue);
            min-width: 45px;
            justify-content: center;
        }
        
        .form-control-bpjs {
            border: none;
            background-color: transparent;
            padding: 12px 15px;
        }
        
        .form-control-bpjs:focus {
            box-shadow: none;
            background-color: transparent;
        }
        
        .btn-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-bpjs:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 119, 200, 0.3);
            color: white;
        }
        
        .btn-bpjs:active {
            transform: translateY(0);
        }
        
        .btn-outline-bpjs {
            border: 2px solid var(--bpjs-blue);
            color: var(--bpjs-blue);
            background: transparent;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-outline-bpjs:hover {
            background-color: var(--bpjs-blue);
            color: white;
        }
        
        .alert-bpjs {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background-color: rgba(67, 176, 42, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }
        
        .info-box {
            background-color: rgba(0, 119, 200, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--bpjs-blue);
        }
        
        .info-box h6 {
            color: var(--bpjs-blue);
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        .preview-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            border: 2px dashed rgba(0, 119, 200, 0.3);
            text-align: center;
        }
        
        .preview-card .avatar-doctor {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
        }
        
        .preview-card h5 {
            color: var(--bpjs-dark);
            margin-bottom: 5px;
        }
        
        .preview-card p {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        footer {
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 25px;
                margin: 20px;
            }
            
            .header-bpjs {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-bpjs">
        <div class="container-full">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">
                        <i class="fas fa-user-plus me-2"></i>Tambah Dokter Baru
                    </h3>
                    <p class="mb-0 opacity-75">BPJS Kesehatan - Data Master Dokter</p>
                </div>
                <a href="dokter.php" class="back-btn">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Data Dokter
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Form -->
    <div class="container-full">
        <div class="form-container">
            <!-- Alert Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-bpjs">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-3 fs-4"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Success!</h6>
                            <span class="mb-0"><?php echo $success_message; ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-bpjs">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-3 fs-4"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Error!</h6>
                            <span class="mb-0"><?php echo $error_message; ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Information Box -->
            <div class="info-box">
                <div class="d-flex">
                    <i class="fas fa-info-circle me-3 mt-1 text-bpjs-blue"></i>
                    <div>
                        <h6 class="mb-2">Informasi Penting</h6>
                        <p class="mb-0">Field dengan tanda bintang (<span class="text-danger">*</span>) wajib diisi. Pastikan data yang dimasukkan valid dan sesuai dengan dokumen resmi.</p>
                    </div>
                </div>
            </div>
            
            <!-- Form -->
            <form method="POST" action="" id="formTambahDokter" onsubmit="return validateForm()">
                <h3 class="form-title">Form Tambah Data Dokter</h3>
                <p class="form-subtitle">Lengkapi semua data di bawah ini untuk menambahkan dokter baru</p>
                
                <!-- Identitas Dokter -->
                <h5 class="section-title">
                    <i class="fas fa-id-card me-2"></i>Identitas Dokter
                </h5>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="kode_dokter" class="form-label">Kode Dokter</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-hashtag"></i>
                            </span>
                            <input type="text" class="form-control form-control-bpjs" id="kode_dokter" name="kode_dokter" 
                                   placeholder="Otomatis terisi" readonly>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>Kode akan di-generate otomatis
                        </small>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="nama_dokter" class="form-label required">Nama Lengkap Dokter</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-user-md"></i>
                            </span>
                            <input type="text" class="form-control form-control-bpjs" id="nama_dokter" name="nama_dokter" required 
                                   placeholder="Masukkan nama lengkap dokter"
                                   value="<?php echo isset($_POST['nama_dokter']) ? htmlspecialchars($_POST['nama_dokter']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="jenis_kelamin" class="form-label required">Jenis Kelamin</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-venus-mars"></i>
                            </span>
                            <select class="form-select form-control-bpjs" id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="L" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="spesialisasi_id" class="form-label">Spesialisasi</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-stethoscope"></i>
                            </span>
                            <select class="form-select form-control-bpjs" id="spesialisasi_id" name="spesialisasi_id">
                                <option value="">Pilih Spesialisasi</option>
                                <?php foreach ($spesialis_data as $spesialis): ?>
                                    <option value="<?php echo $spesialis['id']; ?>" 
                                        <?php echo (isset($_POST['spesialisasi_id']) && $_POST['spesialisasi_id'] == $spesialis['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($spesialis['nama_spesialisasi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Data Pribadi -->
                <h5 class="section-title">
                    <i class="fas fa-user me-2"></i>Data Pribadi
                </h5>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-map-marker-alt"></i>
                            </span>
                            <input type="text" class="form-control form-control-bpjs" id="tempat_lahir" name="tempat_lahir"
                                   placeholder="Kota tempat lahir"
                                   value="<?php echo isset($_POST['tempat_lahir']) ? htmlspecialchars($_POST['tempat_lahir']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <input type="date" class="form-control form-control-bpjs" id="tanggal_lahir" name="tanggal_lahir"
                                   value="<?php echo isset($_POST['tanggal_lahir']) ? htmlspecialchars($_POST['tanggal_lahir']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="alamat" class="form-label">Alamat Lengkap</label>
                    <div class="input-group input-group-bpjs">
                        <span class="input-group-text input-group-text-bpjs">
                            <i class="fas fa-home"></i>
                        </span>
                        <textarea class="form-control form-control-bpjs" id="alamat" name="alamat" rows="3" 
                                  placeholder="Alamat lengkap dokter"><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                    </div>
                </div>
                
                <!-- Kontak & SIP -->
                <h5 class="section-title">
                    <i class="fas fa-address-card me-2"></i>Kontak & Surat Izin Praktik
                </h5>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="no_telepon" class="form-label">No. Telepon</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input type="text" class="form-control form-control-bpjs" id="no_telepon" name="no_telepon"
                                   placeholder="Nomor telepon aktif"
                                   value="<?php echo isset($_POST['no_telepon']) ? htmlspecialchars($_POST['no_telepon']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control form-control-bpjs" id="email" name="email"
                                   placeholder="Email aktif dokter"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="no_sip" class="form-label">No. Surat Izin Praktik (SIP)</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-id-badge"></i>
                            </span>
                            <input type="text" class="form-control form-control-bpjs" id="no_sip" name="no_sip"
                                   placeholder="Nomor SIP dokter"
                                   value="<?php echo isset($_POST['no_sip']) ? htmlspecialchars($_POST['no_sip']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="tgl_berlaku_sip" class="form-label">Tanggal Berlaku SIP</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-calendar-check"></i>
                            </span>
                            <input type="date" class="form-control form-control-bpjs" id="tgl_berlaku_sip" name="tgl_berlaku_sip"
                                   value="<?php echo isset($_POST['tgl_berlaku_sip']) ? htmlspecialchars($_POST['tgl_berlaku_sip']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Status -->
                <h5 class="section-title">
                    <i class="fas fa-chart-line me-2"></i>Status & Konfirmasi
                </h5>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="status" class="form-label required">Status Dokter</label>
                        <div class="input-group input-group-bpjs">
                            <span class="input-group-text input-group-text-bpjs">
                                <i class="fas fa-toggle-on"></i>
                            </span>
                            <select class="form-select form-control-bpjs" id="status" name="status" required>
                                <option value="aktif" <?php echo (isset($_POST['status']) && $_POST['status'] == 'aktif') ? 'selected' : 'selected'; ?>>Aktif</option>
                                <option value="tidak aktif" <?php echo (isset($_POST['status']) && $_POST['status'] == 'tidak aktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="preview-card">
                            <div class="avatar-doctor">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h5 id="previewNama">Nama Dokter</h5>
                            <p id="previewSpesialis">Spesialisasi</p>
                            <span class="badge rounded-pill bg-success" id="previewStatus">Status</span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between border-top pt-4">
                            <button type="reset" class="btn btn-outline-bpjs" onclick="resetForm()">
                                <i class="fas fa-redo me-2"></i> Reset Form
                            </button>
                            <div>
                                <a href="dokter.php" class="btn btn-outline-secondary me-3">
                                    <i class="fas fa-times me-2"></i> Batal
                                </a>
                                <button type="submit" name="tambah_dokter" class="btn btn-bpjs">
                                    <i class="fas fa-save me-2"></i> Simpan Data Dokter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container-full">
            <p class="mb-2">BPJS Kesehatan System &copy; <?php echo date('Y'); ?> v1.0</p>
            <p class="mb-0">Member Area - Tambah Data Dokter</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Generate kode dokter saat halaman dimuat
            generateKodeDokter();
            
            // Preview real-time
            $('#nama_dokter').on('input', updatePreview);
            $('#spesialisasi_id').on('change', updatePreview);
            $('#status').on('change', updatePreview);
            
            // Set tanggal default untuk SIP (1 tahun dari sekarang)
            const today = new Date();
            const nextYear = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
            $('#tgl_berlaku_sip').val(nextYear.toISOString().split('T')[0]);
            
            // Auto-hide alert setelah 5 detik
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
        
        function generateKodeDokter() {
            // Simulasi generate kode (bisa diganti dengan AJAX ke server)
            const timestamp = new Date().getTime();
            const randomNum = Math.floor(Math.random() * 1000);
            const kode = 'DR' + String(randomNum).padStart(3, '0');
            $('#kode_dokter').val(kode);
        }
        
        function updatePreview() {
            const nama = $('#nama_dokter').val() || 'Nama Dokter';
            const spesialisId = $('#spesialisasi_id').val();
            const status = $('#status').val();
            
            // Update nama
            $('#previewNama').text(nama);
            
            // Update spesialisasi
            let spesialisText = 'Spesialisasi';
            if (spesialisId) {
                const selectedOption = $('#spesialisasi_id option:selected').text();
                if (selectedOption !== 'Pilih Spesialisasi') {
                    spesialisText = selectedOption;
                }
            }
            $('#previewSpesialis').text(spesialisText);
            
            // Update status badge
            const statusBadge = $('#previewStatus');
            statusBadge.text(status === 'aktif' ? 'Aktif' : 'Tidak Aktif');
            statusBadge.removeClass('bg-success bg-danger');
            statusBadge.addClass(status === 'aktif' ? 'bg-success' : 'bg-danger');
        }
        
        function validateForm() {
            const namaDokter = $('#nama_dokter').val().trim();
            if (namaDokter === '') {
                alert('Nama dokter harus diisi!');
                $('#nama_dokter').focus();
                return false;
            }
            
            const jenisKelamin = $('#jenis_kelamin').val();
            if (!jenisKelamin) {
                alert('Jenis kelamin harus dipilih!');
                $('#jenis_kelamin').focus();
                return false;
            }
            
            const status = $('#status').val();
            if (!status) {
                alert('Status harus dipilih!');
                $('#status').focus();
                return false;
            }
            
            // Validasi email format
            const email = $('#email').val();
            if (email && !isValidEmail(email)) {
                alert('Format email tidak valid!');
                $('#email').focus();
                return false;
            }
            
            // Validasi no telepon
            const telepon = $('#no_telepon').val();
            if (telepon && !isValidPhone(telepon)) {
                alert('Format nomor telepon tidak valid!');
                $('#no_telepon').focus();
                return false;
            }
            
            // Show loading state
            $('button[name="tambah_dokter"]').html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...');
            $('button[name="tambah_dokter"]').prop('disabled', true);
            
            return true;
        }
        
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        function isValidPhone(phone) {
            const re = /^[0-9+\-\s()]+$/;
            return re.test(phone);
        }
        
        function resetForm() {
            generateKodeDokter();
            updatePreview();
            
            // Set tanggal SIP default
            const today = new Date();
            const nextYear = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
            $('#tgl_berlaku_sip').val(nextYear.toISOString().split('T')[0]);
            
            // Reset tombol jika disabled
            $('button[name="tambah_dokter"]').html('<i class="fas fa-save me-2"></i> Simpan Data Dokter');
            $('button[name="tambah_dokter"]').prop('disabled', false);
        }
        
        // Auto update preview saat halaman dimuat
        $(window).on('load', updatePreview);
    </script>
</body>
</html>
<?php
// Tutup koneksi
if (isset($conn) && is_object($conn)) {
    mysqli_close($conn);
}
?>