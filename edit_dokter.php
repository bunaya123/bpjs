<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek ID dokter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dokter.php");
    exit();
}

$dokter_id = (int)$_GET['id'];

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

// PROSES UPDATE DOKTER
$error_message = '';
$success_message = '';
$warning_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_dokter'])) {
    $nama_dokter = isset($_POST['edit_nama_dokter']) ? sanitize_input($_POST['edit_nama_dokter']) : '';
    $jenis_kelamin = isset($_POST['edit_jenis_kelamin']) ? sanitize_input($_POST['edit_jenis_kelamin']) : '';
    $tempat_lahir = isset($_POST['edit_tempat_lahir']) ? sanitize_input($_POST['edit_tempat_lahir']) : '';
    $tanggal_lahir = isset($_POST['edit_tanggal_lahir']) ? $_POST['edit_tanggal_lahir'] : '';
    $alamat = isset($_POST['edit_alamat']) ? sanitize_input($_POST['edit_alamat']) : '';
    $no_telepon = isset($_POST['edit_no_telepon']) ? sanitize_input($_POST['edit_no_telepon']) : '';
    $email = isset($_POST['edit_email']) ? sanitize_input($_POST['edit_email']) : '';
    $spesialisasi_id = isset($_POST['edit_spesialisasi_id']) ? (int)$_POST['edit_spesialisasi_id'] : 0;
    $no_sip = isset($_POST['edit_no_sip']) ? sanitize_input($_POST['edit_no_sip']) : '';
    $tgl_berlaku_sip = isset($_POST['edit_tgl_berlaku_sip']) ? $_POST['edit_tgl_berlaku_sip'] : '';
    $status = isset($_POST['edit_status']) ? sanitize_input($_POST['edit_status']) : 'aktif';
    
    // Validasi data wajib
    if (empty($nama_dokter) || empty($jenis_kelamin) || empty($status)) {
        $error_message = "❌ Error: Nama dokter, jenis kelamin, dan status harus diisi!";
    } else {
        // Update data dengan prepared statement
        $query = "UPDATE dokter SET 
                  nama_dokter = ?, 
                  jenis_kelamin = ?, 
                  tempat_lahir = ?, 
                  tanggal_lahir = ?, 
                  alamat = ?, 
                  no_telepon = ?, 
                  email = ?, 
                  spesialisasi_id = ?, 
                  no_sip = ?, 
                  tgl_berlaku_sip = ?, 
                  status = ?, 
                  updated_at = NOW() 
                  WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt === false) {
            $error_message = "❌ Error preparing statement: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "sssssssisssi", 
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
                $status,
                $dokter_id
            );
            
            if (mysqli_stmt_execute($stmt)) {
                $affected_rows = mysqli_stmt_affected_rows($stmt);
                
                if ($affected_rows > 0) {
                    $success_message = "✅ Data dokter berhasil diperbarui!";
                    // Redirect ke detail setelah update berhasil
                    $_SESSION['message'] = $success_message;
                    $_SESSION['message_type'] = "success";
                    header("Location: detail_dokter.php?id=" . $dokter_id);
                    exit();
                } else {
                    $warning_message = "⚠️ Tidak ada perubahan data atau data tidak ditemukan!";
                }
                
            } else {
                $error_message = "❌ Error: " . mysqli_stmt_error($stmt);
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

// Ambil data dokter untuk ditampilkan di form - DIPERBAIKI
$query = "SELECT * FROM dokter WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $dokter_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dokter = mysqli_fetch_assoc($result); // DIPERBAIKI: menggunakan mysqli_fetch_assoc($result), bukan $stmt

if (!$dokter) {
    $_SESSION['message'] = "❌ Error: Data dokter tidak ditemukan!";
    $_SESSION['message_type'] = "danger";
    header("Location: dokter.php");
    exit();
}

mysqli_stmt_close($stmt);

// Ambil data spesialisasi untuk dropdown
$query_spesialis = "SELECT * FROM spesialisasi_dokter ORDER BY nama_spesialisasi";
$result_spesialis = mysqli_query($conn, $query_spesialis);
$spesialis_data = [];
while ($row = mysqli_fetch_assoc($result_spesialis)) {
    $spesialis_data[] = $row;
}

// Update last activity
$_SESSION['last_activity'] = time();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Dokter - BPJS KESEHATAN</title>
    
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
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            padding: 25px 40px;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 55, 135, 0.15);
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
        
        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .edit-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
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
        
        .edit-header {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            padding: 30px 40px;
            color: white;
            position: relative;
        }
        
        .edit-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .doctor-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            color: var(--bpjs-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-right: 20px;
        }
        
        .edit-title h2 {
            font-weight: 700;
            margin-bottom: 5px;
            color: white;
        }
        
        .edit-subtitle {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .current-status {
            display: inline-flex;
            align-items: center;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .edit-body {
            padding: 40px;
        }
        
        .section-title {
            color: var(--bpjs-dark);
            font-weight: 600;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0, 119, 200, 0.1);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--bpjs-blue);
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
        
        .form-control-bpjs:disabled {
            background-color: rgba(0, 0, 0, 0.05);
            color: #6c757d;
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
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border-left: 4px solid #ffc107;
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
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
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
        
        .form-section {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        footer {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 40px;
        }
        
        @media (max-width: 768px) {
            .header-bpjs {
                padding: 20px;
            }
            
            .edit-header {
                padding: 25px 20px;
            }
            
            .edit-header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .doctor-avatar {
                margin-bottom: 15px;
            }
            
            .edit-body {
                padding: 25px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-bpjs, .btn-outline-bpjs {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-bpjs">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">
                        <i class="fas fa-edit me-2"></i>Edit Data Dokter
                    </h3>
                    <p class="mb-0 opacity-75">BPJS Kesehatan - Perbarui Data Dokter</p>
                </div>
                <a href="detail_dokter.php?id=<?php echo $dokter_id; ?>" class="back-btn">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Detail
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-container">
        <!-- Edit Card -->
        <div class="edit-card">
            <!-- Header -->
            <div class="edit-header">
                <div class="edit-header-content">
                    <div class="d-flex align-items-center">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="edit-title">
                            <h2><?php echo htmlspecialchars($dokter['nama_dokter']); ?></h2>
                            <p class="edit-subtitle mb-2">
                                <i class="fas fa-hashtag me-1"></i>Kode: <?php echo htmlspecialchars($dokter['kode_dokter']); ?>
                            </p>
                            <span class="current-status">
                                <i class="fas fa-<?php echo $dokter['status'] == 'aktif' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                Status saat ini: <?php echo $dokter['status'] == 'aktif' ? 'Aktif' : 'Tidak Aktif'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Body -->
            <div class="edit-body">
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
                
                <?php if (isset($warning_message)): ?>
                    <div class="alert alert-warning alert-bpjs">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                            <div>
                                <h6 class="alert-heading mb-1">Warning!</h6>
                                <span class="mb-0"><?php echo $warning_message; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Information Box -->
                <div class="info-box">
                    <div class="d-flex">
                        <i class="fas fa-info-circle me-3 mt-1 text-bpjs-blue"></i>
                        <div>
                            <h6 class="mb-2">Informasi Edit Data</h6>
                            <p class="mb-0">Field dengan tanda bintang (<span class="text-danger">*</span>) wajib diisi. Pastikan data yang diperbarui valid dan sesuai dengan dokumen resmi. Perubahan akan segera diterapkan ke sistem.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Form -->
                <form method="POST" action="" id="formEditDokter" onsubmit="return validateForm()">
                    <input type="hidden" name="dokter_id" value="<?php echo $dokter_id; ?>">
                    
                    <!-- Identitas Dokter -->
                    <h5 class="section-title">
                        <i class="fas fa-id-card me-2"></i>Identitas Dokter
                    </h5>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Kode Dokter</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-hashtag"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-bpjs" 
                                           value="<?php echo htmlspecialchars($dokter['kode_dokter']); ?>" readonly>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="fas fa-lock me-1"></i>Kode dokter tidak dapat diubah
                                </small>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="edit_nama_dokter" class="form-label required">Nama Lengkap Dokter</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-user-md"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-bpjs" id="edit_nama_dokter" name="edit_nama_dokter" required 
                                           value="<?php echo htmlspecialchars($dokter['nama_dokter']); ?>"
                                           placeholder="Masukkan nama lengkap dokter">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="edit_jenis_kelamin" class="form-label required">Jenis Kelamin</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-venus-mars"></i>
                                    </span>
                                    <select class="form-select form-control-bpjs" id="edit_jenis_kelamin" name="edit_jenis_kelamin" required>
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="L" <?php echo $dokter['jenis_kelamin'] == 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                        <option value="P" <?php echo $dokter['jenis_kelamin'] == 'P' ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="edit_spesialisasi_id" class="form-label">Spesialisasi</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-stethoscope"></i>
                                    </span>
                                    <select class="form-select form-control-bpjs" id="edit_spesialisasi_id" name="edit_spesialisasi_id">
                                        <option value="">Pilih Spesialisasi</option>
                                        <?php foreach ($spesialis_data as $spesialis): ?>
                                            <option value="<?php echo $spesialis['id']; ?>" 
                                                <?php echo $dokter['spesialisasi_id'] == $spesialis['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($spesialis['nama_spesialisasi']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Pribadi -->
                    <h5 class="section-title">
                        <i class="fas fa-user me-2"></i>Data Pribadi
                    </h5>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="edit_tempat_lahir" class="form-label">Tempat Lahir</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-bpjs" id="edit_tempat_lahir" name="edit_tempat_lahir"
                                           value="<?php echo htmlspecialchars($dokter['tempat_lahir']); ?>"
                                           placeholder="Kota tempat lahir">
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="edit_tanggal_lahir" class="form-label">Tanggal Lahir</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-calendar-alt"></i>
                                    </span>
                                    <input type="date" class="form-control form-control-bpjs" id="edit_tanggal_lahir" name="edit_tanggal_lahir"
                                           value="<?php echo htmlspecialchars($dokter['tanggal_lahir']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_alamat" class="form-label">Alamat Lengkap</label>
                            <div class="input-group input-group-bpjs">
                                <span class="input-group-text input-group-text-bpjs">
                                    <i class="fas fa-home"></i>
                                </span>
                                <textarea class="form-control form-control-bpjs" id="edit_alamat" name="edit_alamat" rows="3" 
                                          placeholder="Alamat lengkap dokter"><?php echo htmlspecialchars($dokter['alamat']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kontak & SIP -->
                    <h5 class="section-title">
                        <i class="fas fa-address-card me-2"></i>Kontak & Surat Izin Praktik
                    </h5>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="edit_no_telepon" class="form-label">No. Telepon</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-bpjs" id="edit_no_telepon" name="edit_no_telepon"
                                           value="<?php echo htmlspecialchars($dokter['no_telepon']); ?>"
                                           placeholder="Nomor telepon aktif">
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="edit_email" class="form-label">Email</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control form-control-bpjs" id="edit_email" name="edit_email"
                                           value="<?php echo htmlspecialchars($dokter['email']); ?>"
                                           placeholder="Email aktif dokter">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="edit_no_sip" class="form-label">No. Surat Izin Praktik (SIP)</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-id-badge"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-bpjs" id="edit_no_sip" name="edit_no_sip"
                                           value="<?php echo htmlspecialchars($dokter['no_sip']); ?>"
                                           placeholder="Nomor SIP dokter">
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="edit_tgl_berlaku_sip" class="form-label">Tanggal Berlaku SIP</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-calendar-check"></i>
                                    </span>
                                    <input type="date" class="form-control form-control-bpjs" id="edit_tgl_berlaku_sip" name="edit_tgl_berlaku_sip"
                                           value="<?php echo htmlspecialchars($dokter['tgl_berlaku_sip']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <h5 class="section-title">
                        <i class="fas fa-chart-line me-2"></i>Status Dokter
                    </h5>
                    <div class="form-section">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="edit_status" class="form-label required">Status Dokter</label>
                                <div class="input-group input-group-bpjs">
                                    <span class="input-group-text input-group-text-bpjs">
                                        <i class="fas fa-toggle-on"></i>
                                    </span>
                                    <select class="form-select form-control-bpjs" id="edit_status" name="edit_status" required>
                                        <option value="aktif" <?php echo $dokter['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="tidak aktif" <?php echo $dokter['status'] == 'tidak aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                                    </select>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="fas fa-info-circle me-1"></i>Dokter aktif dapat melayani pasien BPJS
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="detail_dokter.php?id=<?php echo $dokter_id; ?>" class="btn-outline-bpjs">
                            <i class="fas fa-times me-2"></i> Batal
                        </a>
                        
                        <button type="reset" class="btn-outline-bpjs" onclick="resetForm()">
                            <i class="fas fa-redo me-2"></i> Reset Form
                        </button>
                        
                        <button type="submit" name="update_dokter" class="btn-bpjs">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container-fluid">
            <p class="mb-2">BPJS Kesehatan System &copy; <?php echo date('Y'); ?> v1.0</p>
            <p class="mb-0">Edit Dokter - <?php echo htmlspecialchars($dokter['nama_dokter']); ?></p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Auto-hide alert setelah 5 detik
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
        
        function validateForm() {
            const namaDokter = $('#edit_nama_dokter').val().trim();
            if (namaDokter === '') {
                alert('Nama dokter harus diisi!');
                $('#edit_nama_dokter').focus();
                return false;
            }
            
            const jenisKelamin = $('#edit_jenis_kelamin').val();
            if (!jenisKelamin) {
                alert('Jenis kelamin harus dipilih!');
                $('#edit_jenis_kelamin').focus();
                return false;
            }
            
            const status = $('#edit_status').val();
            if (!status) {
                alert('Status harus dipilih!');
                $('#edit_status').focus();
                return false;
            }
            
            // Validasi email format
            const email = $('#edit_email').val();
            if (email && !isValidEmail(email)) {
                alert('Format email tidak valid!');
                $('#edit_email').focus();
                return false;
            }
            
            // Validasi no telepon
            const telepon = $('#edit_no_telepon').val();
            if (telepon && !isValidPhone(telepon)) {
                alert('Format nomor telepon tidak valid!');
                $('#edit_no_telepon').focus();
                return false;
            }
            
            // Show loading state
            $('button[name="update_dokter"]').html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...');
            $('button[name="update_dokter"]').prop('disabled', true);
            
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
            // Reset hanya ke nilai awal dari database
            // Hapus form validation state
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();
            
            // Reset tombol jika disabled
            $('button[name="update_dokter"]').html('<i class="fas fa-save me-2"></i> Simpan Perubahan');
            $('button[name="update_dokter"]').prop('disabled', false);
        }
    </script>
</body>
</html>
<?php
// Tutup koneksi
if (isset($conn) && is_object($conn)) {
    mysqli_close($conn);
}
?>