<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil ID dari parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID tindakan tidak valid!";
    header("Location: tindakan.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Ambil data user
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// Ambil data tindakan untuk di-edit
$sql = "SELECT * FROM tindakan WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Data tindakan tidak ditemukan!";
    header("Location: tindakan.php");
    exit();
}

$tindakan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Update last activity
$_SESSION['last_activity'] = time();

// Get kategori untuk dropdown
$kategori_options = [];
$kategori_query = "SELECT DISTINCT kategori FROM tindakan WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori";
$kategori_result = mysqli_query($conn, $kategori_query);
while ($row = mysqli_fetch_assoc($kategori_result)) {
    $kategori_options[] = $row['kategori'];
}

// Cek apakah ada data form yang disimpan di session (jika ada error validasi)
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    
    // Pastikan ID sesuai
    if ($form_data['id'] == $id) {
        // Gunakan data dari session
        $tindakan['kode_tindakan'] = $form_data['kode_tindakan'];
        $tindakan['nama_tindakan'] = $form_data['nama_tindakan'];
        $tindakan['kategori'] = $form_data['kategori'];
        $tindakan['deskripsi'] = $form_data['deskripsi'];
        $tindakan['tarif_bpjs'] = $form_data['tarif_bpjs'];
        $tindakan['tarif_non_bpjs'] = $form_data['tarif_non_bpjs'];
        $tindakan['status'] = $form_data['status'];
        $tindakan['catatan'] = $form_data['catatan'] ?? '';
        $tindakan['jenis_tindakan'] = $form_data['jenis_tindakan'] ?? '';
        $tindakan['unit'] = $form_data['unit'] ?? '';
        $tindakan['waktu_estimasi'] = $form_data['waktu_estimasi'] ?? '';
        $tindakan['persyaratan'] = $form_data['persyaratan'] ?? '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Tindakan - BPJS Kesehatan</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: #333;
            padding-top: 20px;
            padding-bottom: 20px;
        }
        
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        
        .header-section h2 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .header-section p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .btn-back {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }
        
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 20px 25px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .card-body-custom {
            padding: 30px;
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            color: #555;
            font-weight: 500;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary-custom {
            background-color: #6c757d;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-secondary-custom:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
        }
        
        .rupiah-input {
            text-align: right;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .rupiah-input::placeholder {
            color: #95a5a6;
            font-weight: normal;
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .alert-danger {
            background-color: #fee;
            border-left: 5px solid #e74c3c;
            color: #c0392b;
        }
        
        .alert-success {
            background-color: #e8f6f3;
            border-left: 5px solid #27ae60;
            color: #27ae60;
        }
        
        .alert-info {
            background-color: #e8f4fc;
            border-left: 5px solid #3498db;
            color: #2980b9;
        }
        
        .info-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
        }
        
        .info-box h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .info-box ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .info-box li {
            margin-bottom: 8px;
            color: #555;
        }
        
        .info-box li:last-child {
            margin-bottom: 0;
        }
        
        .info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }
        
        .action-btn {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .action-btn i {
            margin-right: 8px;
        }
        
        .action-btn-info {
            background-color: #e8f4fc;
            color: #2980b9;
            border: 1px solid #bde0fe;
        }
        
        .action-btn-info:hover {
            background-color: #d4edff;
            color: #2980b9;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .action-btn-danger {
            background-color: #fee;
            color: #e74c3c;
            border: 1px solid #fadbd8;
        }
        
        .action-btn-danger:hover {
            background-color: #fadbd8;
            color: #c0392b;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 768px) {
            .header-section {
                padding: 20px;
            }
            
            .card-body-custom {
                padding: 20px;
            }
            
            .btn-primary-custom, .btn-secondary-custom {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .btn-back {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <!-- Header Section -->
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-edit"></i> Edit Data Tindakan</h2>
                    <p>Perbarui informasi tindakan medis BPJS Kesehatan</p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="tindakan_detail.php?id=<?php echo $tindakan['id']; ?>" class="btn btn-back">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Detail
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-custom">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-3"></i>
                    <div><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-custom">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-3"></i>
                    <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Form Edit -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <i class="fas fa-edit mr-2"></i> Form Edit Tindakan
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="simpan_tindakan.php" id="editForm">
                            <!-- Hidden input untuk ID -->
                            <input type="hidden" name="id" value="<?php echo $tindakan['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kode_tindakan" class="form-label">Kode Tindakan <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="kode_tindakan" 
                                               name="kode_tindakan" 
                                               value="<?php echo htmlspecialchars($tindakan['kode_tindakan']); ?>"
                                               required>
                                        <small class="form-text text-muted">Kode unik untuk tindakan</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kategori" class="form-label">Kategori</label>
                                        <input type="text" class="form-control" id="kategori" 
                                               name="kategori" 
                                               value="<?php echo htmlspecialchars($tindakan['kategori'] ?? ''); ?>"
                                               list="kategori-list">
                                        <datalist id="kategori-list">
                                            <?php foreach($kategori_options as $kategori_opt): ?>
                                                <option value="<?php echo htmlspecialchars($kategori_opt); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                        <small class="form-text text-muted">Kategori tindakan medis</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="nama_tindakan" class="form-label">Nama Tindakan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_tindakan" 
                                       name="nama_tindakan" 
                                       value="<?php echo htmlspecialchars($tindakan['nama_tindakan']); ?>"
                                       required>
                                <small class="form-text text-muted">Nama lengkap tindakan medis</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="deskripsi" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="deskripsi" name="deskripsi" 
                                          rows="3"><?php echo htmlspecialchars($tindakan['deskripsi'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Deskripsi lengkap tentang tindakan medis</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tarif_bpjs" class="form-label">Tarif BPJS <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Rp</span>
                                            </div>
                                            <input type="text" class="form-control rupiah-input" id="tarif_bpjs" 
                                                   name="tarif_bpjs" 
                                                   value="<?php echo isset($tindakan['tarif_bpjs']) && $tindakan['tarif_bpjs'] > 0 ? number_format($tindakan['tarif_bpjs'], 0, ',', '.') : ''; ?>"
                                                   placeholder="Contoh: 100.000"
                                                   required>
                                        </div>
                                        <small class="form-text text-muted">Tarif untuk peserta BPJS (contoh: 10.000 - 500.000.000)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tarif_non_bpjs" class="form-label">Tarif Non-BPJS <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Rp</span>
                                            </div>
                                            <input type="text" class="form-control rupiah-input" id="tarif_non_bpjs" 
                                                   name="tarif_non_bpjs" 
                                                   value="<?php echo isset($tindakan['tarif_non_bpjs']) && $tindakan['tarif_non_bpjs'] > 0 ? number_format($tindakan['tarif_non_bpjs'], 0, ',', '.') : ''; ?>"
                                                   placeholder="Contoh: 150.000"
                                                   required>
                                        </div>
                                        <small class="form-text text-muted">Tarif untuk non-peserta BPJS (contoh: 15.000 - 750.000.000)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="jenis_tindakan" class="form-label">Jenis Tindakan</label>
                                        <select class="form-select" id="jenis_tindakan" name="jenis_tindakan">
    <option value="">Pilih Jenis</option>
    <option value="Rawat Jalan" <?php echo (isset($tindakan['jenis_tindakan']) && $tindakan['jenis_tindakan'] == 'Rawat Jalan') ? 'selected' : ''; ?>>Rawat Jalan</option>
    <option value="Rawat Inap" <?php echo (isset($tindakan['jenis_tindakan']) && $tindakan['jenis_tindakan'] == 'Rawat Inap') ? 'selected' : ''; ?>>Rawat Inap</option>
    <option value="IGD" <?php echo (isset($tindakan['jenis_tindakan']) && $tindakan['jenis_tindakan'] == 'IGD') ? 'selected' : ''; ?>>IGD</option>
    <option value="Laboratorium" <?php echo (isset($tindakan['jenis_tindakan']) && $tindakan['jenis_tindakan'] == 'Laboratorium') ? 'selected' : ''; ?>>Laboratorium</option>
    <option value="Radiologi" <?php echo (isset($tindakan['jenis_tindakan']) && $tindakan['jenis_tindakan'] == 'Radiologi') ? 'selected' : ''; ?>>Radiologi</option>
    <option value="Fisioterapi" <?php echo (isset($tindakan['jenis_tindakan']) && $tindakan['jenis_tindakan'] == 'Fisioterapi') ? 'selected' : ''; ?>>Fisioterapi</option>
</select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="status" name="status" required>
    <option value="aktif" <?php echo $tindakan['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
    <option value="tidak aktif" <?php echo $tindakan['status'] == 'tidak aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
</select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="unit" class="form-label">Unit</label>
                                        <input type="text" class="form-control" id="unit" 
                                               name="unit" 
                                               value="<?php echo htmlspecialchars($tindakan['unit'] ?? ''); ?>">
                                        <small class="form-text text-muted">Unit pelaksana tindakan</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="waktu_estimasi" class="form-label">Waktu Estimasi (menit)</label>
                                        <input type="number" class="form-control" id="waktu_estimasi" 
                                               name="waktu_estimasi" 
                                               value="<?php echo htmlspecialchars($tindakan['waktu_estimasi'] ?? ''); ?>"
                                               min="0" step="1">
                                        <small class="form-text text-muted">Estimasi waktu pelaksanaan dalam menit</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="persyaratan" class="form-label">Persyaratan</label>
                                <textarea class="form-control" id="persyaratan" name="persyaratan" 
                                          rows="2"><?php echo htmlspecialchars($tindakan['persyaratan'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Persyaratan yang diperlukan untuk tindakan ini</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="catatan" class="form-label">Catatan Tambahan</label>
                                <textarea class="form-control" id="catatan" name="catatan" 
                                          rows="2"><?php echo htmlspecialchars($tindakan['catatan'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Catatan khusus tentang tindakan ini</small>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                                <a href="tindakan_detail.php?id=<?php echo $tindakan['id']; ?>" 
                                   class="btn btn-secondary-custom">
                                    <i class="fas fa-times mr-2"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="fas fa-save mr-2"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Informasi & Aksi -->
            <div class="col-lg-4">
                <div class="info-box">
                    <h6><i class="fas fa-info-circle mr-2"></i> Panduan Pengisian</h6>
                    <ul>
                        <li>Field bertanda <span class="text-danger">*</span> wajib diisi</li>
                        <li>Kode tindakan harus unik</li>
                        <li>Tarif dalam satuan Rupiah (Rp)</li>
                        <li>Gunakan titik (.) sebagai pemisah ribuan</li>
                        <li>Tarif minimal Rp 10.000</li>
                        <li>Tarif maksimal Rp 1.000.000.000</li>
                        <li>Pastikan data yang diinput valid</li>
                    </ul>
                </div>
                
                <div class="card-custom">
                    <div class="card-header-custom">
                        <i class="fas fa-history mr-2"></i> Informasi Data
                    </div>
                    <div class="card-body-custom">
                        <div class="info-item">
                            <div class="info-label">Tanggal Dibuat</div>
                            <div class="info-value">
                                <i class="far fa-calendar-plus mr-2"></i>
                                <?php echo date('d/m/Y H:i', strtotime($tindakan['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Terakhir Diupdate</div>
                            <div class="info-value">
                                <i class="far fa-calendar-check mr-2"></i>
                                <?php 
                                if (!empty($tindakan['updated_at'])) {
                                    echo date('d/m/Y H:i', strtotime($tindakan['updated_at']));
                                } else {
                                    echo '<span class="text-muted">Belum pernah diupdate</span>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Status Saat Ini</div>
                            <div class="info-value">
                                <?php if($tindakan['status'] == 'aktif'): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-times-circle mr-1"></i> Nonaktif
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-custom">
                    <div class="card-header-custom">
                        <i class="fas fa-bolt mr-2"></i> Aksi Cepat
                    </div>
                    <div class="card-body-custom">
                        <div class="action-buttons">
                            <a href="tindakan_detail.php?id=<?php echo $tindakan['id']; ?>" 
                               class="action-btn action-btn-info">
                                <i class="fas fa-eye mr-2"></i> Lihat Detail
                            </a>
                            <button type="button" 
                                    class="action-btn action-btn-danger" 
                                    onclick="confirmDelete(<?php echo $tindakan['id']; ?>, '<?php echo htmlspecialchars(addslashes($tindakan['nama_tindakan'])); ?>')">
                                <i class="fas fa-trash mr-2"></i> Hapus Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>BPJS Kesehatan System &copy; <?php echo date('Y'); ?> - Edit Data Tindakan</p>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header card-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <h6 id="deleteMessage">Apakah Anda yakin ingin menghapus data ini?</h6>
                    <p class="text-muted mb-0">Data yang dihapus tidak dapat dikembalikan</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <a href="#" id="deleteLink" class="btn btn-danger">
                        <i class="fas fa-trash mr-1"></i> Ya, Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fungsi format Rupiah yang lebih baik (10.000 - 1.000.000.000)
        function formatRupiah(angka, prefix = '') {
            if (!angka) return '';
            
            // Hapus semua karakter non-digit
            let number_string = angka.toString().replace(/[^,\d]/g, '').toString();
            
            // Pastikan angka minimal 4 digit (10.000)
            if (number_string.length < 4) {
                number_string = '0000'.substring(0, 4 - number_string.length) + number_string;
            }
            
            // Batasi maksimal 10 digit (1.000.000.000)
            if (number_string.length > 10) {
                number_string = number_string.substring(0, 10);
            }
            
            let split = number_string.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/g);
            
            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            
            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix === '' ? rupiah : rupiah ? prefix + rupiah : '';
        }
        
        function unformatRupiah(angka) {
            if (!angka) return 0;
            // Hapus semua karakter non-digit
            return parseInt(angka.toString().replace(/[^0-9]/g, '') || 0);
        }
        
        // Validasi range tarif
        function validateTarifRange(value, min = 10000, max = 1000000000) {
            const numericValue = unformatRupiah(value);
            return numericValue >= min && numericValue <= max;
        }
        
        $(document).ready(function() {
            // Format harga saat halaman dimuat
            $('.rupiah-input').each(function() {
                let value = $(this).val();
                if (value) {
                    let unformatted = unformatRupiah(value);
                    let formatted = formatRupiah(unformatted);
                    $(this).val(formatted);
                }
            });
            
            // Auto format harga saat input
            $('.rupiah-input').on('keyup', function() {
                let value = $(this).val();
                let unformatted = unformatRupiah(value);
                let formatted = formatRupiah(unformatted);
                $(this).val(formatted);
            });
            
            // Validasi range saat blur
            $('.rupiah-input').on('blur', function() {
                let value = $(this).val();
                let unformatted = unformatRupiah(value);
                
                // Validasi minimal 10.000
                if (unformatted < 10000) {
                    $(this).addClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                    $(this).after('<div class="invalid-feedback">Tarif minimal Rp 10.000</div>');
                }
                // Validasi maksimal 1.000.000.000
                else if (unformatted > 1000000000) {
                    $(this).addClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                    $(this).after('<div class="invalid-feedback">Tarif maksimal Rp 1.000.000.000</div>');
                }
                else {
                    $(this).removeClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                }
            });
            
            // Format ulang saat fokus
            $('.rupiah-input').on('focus', function() {
                let value = $(this).val();
                let unformatted = unformatRupiah(value);
                $(this).val(unformatted || '');
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
            });
            
            // Auto-hide alerts setelah 5 detik
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Validasi form sebelum submit
            $('#editForm').on('submit', function(e) {
                let isValid = true;
                let errors = [];
                
                // Validasi kode tindakan
                if ($('#kode_tindakan').val().trim() === '') {
                    errors.push('Kode tindakan wajib diisi');
                    $('#kode_tindakan').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#kode_tindakan').removeClass('is-invalid');
                }
                
                // Validasi nama tindakan
                if ($('#nama_tindakan').val().trim() === '') {
                    errors.push('Nama tindakan wajib diisi');
                    $('#nama_tindakan').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#nama_tindakan').removeClass('is-invalid');
                }
                
                // Validasi tarif BPJS
                let tarif_bpjs = unformatRupiah($('#tarif_bpjs').val());
                if (tarif_bpjs < 10000) {
                    errors.push('Tarif BPJS minimal Rp 10.000');
                    $('#tarif_bpjs').addClass('is-invalid');
                    isValid = false;
                } else if (tarif_bpjs > 1000000000) {
                    errors.push('Tarif BPJS maksimal Rp 1.000.000.000');
                    $('#tarif_bpjs').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#tarif_bpjs').removeClass('is-invalid');
                }
                
                // Validasi tarif Non BPJS
                let tarif_non_bpjs = unformatRupiah($('#tarif_non_bpjs').val());
                if (tarif_non_bpjs < 10000) {
                    errors.push('Tarif Non BPJS minimal Rp 10.000');
                    $('#tarif_non_bpjs').addClass('is-invalid');
                    isValid = false;
                } else if (tarif_non_bpjs > 1000000000) {
                    errors.push('Tarif Non BPJS maksimal Rp 1.000.000.000');
                    $('#tarif_non_bpjs').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#tarif_non_bpjs').removeClass('is-invalid');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    let errorHtml = '<div class="alert alert-danger alert-custom"><ul class="mb-0">';
                    errors.forEach(error => {
                        errorHtml += '<li>' + error + '</li>';
                    });
                    errorHtml += '</ul></div>';
                    
                    // Tampilkan error di atas form
                    $('.alert-custom').remove();
                    $(errorHtml).insertAfter('.header-section');
                    
                    // Auto-hide error alert
                    setTimeout(function() {
                        $('.alert-danger').fadeOut('slow');
                    }, 8000);
                }
            });
        });
        
        // Confirm delete function
        function confirmDelete(id, nama) {
            document.getElementById('deleteLink').href = 'tindakan_hapus.php?id=' + id;
            if (nama) {
                document.getElementById('deleteMessage').innerHTML = 
                    'Apakah Anda yakin ingin menghapus tindakan <strong>"' + nama + '"</strong>?';
            }
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html>