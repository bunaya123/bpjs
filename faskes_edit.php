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

// Ambil ID faskes dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID faskes tidak valid!";
    header("Location: faskes.php");
    exit();
}

$id = intval($_GET['id']);

// Ambil data faskes untuk di-edit
$sql = "SELECT * FROM faskes WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$faskes = mysqli_fetch_assoc($result);

if (!$faskes) {
    $_SESSION['error'] = "Data faskes tidak ditemukan!";
    header("Location: faskes.php");
    exit();
}

// Proses update data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi dan sanitasi input
    $kode_faskes = mysqli_real_escape_string($conn, $_POST['kode_faskes']);
    $nama_faskes = mysqli_real_escape_string($conn, $_POST['nama_faskes']);
    $jenis_faskes = mysqli_real_escape_string($conn, $_POST['jenis_faskes']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $kota = mysqli_real_escape_string($conn, $_POST['kota']);
    $provinsi = mysqli_real_escape_string($conn, $_POST['provinsi']);
    $kode_pos = mysqli_real_escape_string($conn, $_POST['kode_pos']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $website = mysqli_real_escape_string($conn, $_POST['website']);
    $direktur = mysqli_real_escape_string($conn, $_POST['direktur']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Cek apakah kode faskes sudah digunakan oleh faskes lain
    $check_sql = "SELECT * FROM faskes WHERE kode_faskes = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "si", $kode_faskes, $id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $_SESSION['error'] = "Kode faskes sudah digunakan oleh faskes lain!";
    } else {
        // Update data ke database
        $update_sql = "UPDATE faskes SET 
            kode_faskes = ?,
            nama_faskes = ?,
            jenis_faskes = ?,
            alamat = ?,
            kota = ?,
            provinsi = ?,
            kode_pos = ?,
            no_telepon = ?,
            email = ?,
            website = ?,
            direktur = ?,
            status = ?,
            updated_at = NOW()
            WHERE id = ?";
        
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssssssssssssi", 
            $kode_faskes, $nama_faskes, $jenis_faskes, $alamat, $kota, $provinsi,
            $kode_pos, $no_telepon, $email, $website, $direktur, $status, $id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $_SESSION['success'] = "Data faskes berhasil diperbarui!";
            header("Location: faskes_detail.php?id=" . $id);
            exit();
        } else {
            $_SESSION['error'] = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Faskes - BPJS</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f2ff 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .bpjs-header {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .bpjs-card {
            background: white;
            border-radius: 15px;
            border: none;
            box-shadow: 0 8px 30px rgba(0, 102, 204, 0.1);
            margin-bottom: 25px;
        }
        .bpjs-card-header {
            background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
            border: none;
        }
        .form-label {
            color: #0066cc;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        .btn-bpjs {
            background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-bpjs:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 102, 204, 0.3);
            color: white;
        }
        .btn-cancel {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #dee2e6;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-cancel:hover {
            background: #e9ecef;
            color: #495057;
        }
        .required-star {
            color: #dc3545;
        }
        .info-box {
            background: #f0f8ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #0066cc;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid #e6f2ff;
            margin-bottom: 20px;
            position: relative;
        }
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100px;
            height: 2px;
            background: #0066cc;
        }
        .help-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="bpjs-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-1">
                        <i class="fas fa-hospital mr-2"></i>
                        <strong>BPJS KESEHATAN</strong>
                    </h1>
                    <p class="mb-0">Sistem Informasi Fasilitas Kesehatan</p>
                </div>
                <div class="col-md-4 text-right">
                    <div class="dropdown">
                        
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="dashboard.php">
                                <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                            </a>
                            <a class="dropdown-item" href="faskes.php">
                                <i class="fas fa-hospital mr-2"></i> Data Faskes
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    

    <!-- Main Content -->
    <div class="container my-4">
        <!-- Messages -->
        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="bpjs-card">
            <div class="bpjs-card-header">
                <h4 class="mb-0">
                    <i class="fas fa-edit mr-2"></i> Edit Data Faskes
                </h4>
                <p class="mb-0">Perbarui informasi <?php echo htmlspecialchars($faskes['nama_faskes']); ?></p>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <i class="fas fa-info-circle text-primary mr-2"></i>
                    Isi formulir di bawah ini untuk memperbarui data faskes. Kolom dengan tanda (<span class="required-star">*</span>) wajib diisi.
                </div>

                <form method="POST" action="">
                    <div class="row">
                        <!-- Informasi Utama -->
                        <div class="col-md-6">
                            <h5 class="section-title">
                                <i class="fas fa-info-circle mr-2"></i> Informasi Utama
                            </h5>
                            
                            <div class="form-group">
                                <label class="form-label">Kode Faskes <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="kode_faskes" 
                                       value="<?php echo htmlspecialchars($faskes['kode_faskes']); ?>" 
                                       required pattern="[A-Z0-9]+" title="Hanya huruf kapital dan angka">
                                <div class="help-text">Contoh: FSK001, RS012, PKM045</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nama Faskes <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="nama_faskes" 
                                       value="<?php echo htmlspecialchars($faskes['nama_faskes']); ?>" required>
                                <div class="help-text">Nama lengkap fasilitas kesehatan</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Jenis Faskes <span class="required-star">*</span></label>
                                        <select class="form-select form-control" name="jenis_faskes" required>
                                            <option value="">Pilih Jenis</option>
                                            <option value="Rumah Sakit" <?php echo $faskes['jenis_faskes'] == 'Rumah Sakit' ? 'selected' : ''; ?>>Rumah Sakit</option>
                                            <option value="Puskesmas" <?php echo $faskes['jenis_faskes'] == 'Puskesmas' ? 'selected' : ''; ?>>Puskesmas</option>
                                            <option value="Klinik" <?php echo $faskes['jenis_faskes'] == 'Klinik' ? 'selected' : ''; ?>>Klinik</option>
                                            <option value="Laboratorium" <?php echo $faskes['jenis_faskes'] == 'Laboratorium' ? 'selected' : ''; ?>>Laboratorium</option>
                                            <option value="Apotek" <?php echo $faskes['jenis_faskes'] == 'Apotek' ? 'selected' : ''; ?>>Apotek</option>
                                            <option value="Rumah Bersalin" <?php echo $faskes['jenis_faskes'] == 'Rumah Bersalin' ? 'selected' : ''; ?>>Rumah Bersalin</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Status <span class="required-star">*</span></label>
                                        <select class="form-select form-control" name="status" required>
                                            <option value="aktif" <?php echo $faskes['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="tidak aktif" <?php echo $faskes['status'] == 'tidak aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nama Direktur</label>
                                <input type="text" class="form-control" name="direktur" 
                                       value="<?php echo htmlspecialchars($faskes['direktur'] ?? ''); ?>">
                                <div class="help-text">Nama direktur/pimpinan faskes</div>
                            </div>
                        </div>

                        <!-- Informasi Lokasi -->
                        <div class="col-md-6">
                            <h5 class="section-title">
                                <i class="fas fa-map-marker-alt mr-2"></i> Informasi Lokasi
                            </h5>
                            
                            <div class="form-group">
                                <label class="form-label">Alamat Lengkap <span class="required-star">*</span></label>
                                <textarea class="form-control" name="alamat" rows="3" required><?php echo htmlspecialchars($faskes['alamat']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Kota <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" name="kota" 
                                               value="<?php echo htmlspecialchars($faskes['kota']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Provinsi <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" name="provinsi" 
                                               value="<?php echo htmlspecialchars($faskes['provinsi']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Kode Pos</label>
                                <input type="text" class="form-control" name="kode_pos" 
                                       value="<?php echo htmlspecialchars($faskes['kode_pos'] ?? ''); ?>"
                                       pattern="[0-9]{5}" title="5 digit angka">
                                <div class="help-text">Format: 5 digit angka</div>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Kontak -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="section-title">
                                <i class="fas fa-address-book mr-2"></i> Informasi Kontak
                            </h5>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Telepon <span class="required-star">*</span></label>
                                <input type="tel" class="form-control" name="no_telepon" 
                                       value="<?php echo htmlspecialchars($faskes['no_telepon']); ?>" required>
                                <div class="help-text">Contoh: 021-1234567</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($faskes['email'] ?? ''); ?>">
                                <div class="help-text">Contoh: info@rscontoh.com</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="website" 
                                       value="<?php echo htmlspecialchars($faskes['website'] ?? ''); ?>">
                                <div class="help-text">Contoh: https://www.rscontoh.com</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-5">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-bpjs mr-3">
                                <i class="fas fa-save mr-2"></i> Simpan Perubahan
                            </button>
                            <a href="faskes_detail.php?id=<?php echo $faskes['id']; ?>" class="btn btn-cancel">
                                <i class="fas fa-times mr-2"></i> Batal
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informasi Sistem -->
        <div class="row">
            <div class="col-12">
                <div class="bpjs-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="text-muted">
                                    <i class="fas fa-calendar-plus fa-2x mb-2 text-primary"></i>
                                    <p>Dibuat: <?php echo date('d M Y H:i', strtotime($faskes['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="text-muted">
                                    <i class="fas fa-calendar-check fa-2x mb-2 text-primary"></i>
                                    <p>Terakhir Diperbarui: <?php echo date('d M Y H:i', strtotime($faskes['updated_at'])); ?></p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="text-muted">
                                    <i class="fas fa-user-md fa-2x mb-2 text-primary"></i>
                                    <p>ID Faskes: <?php echo $faskes['id']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4" style="background: linear-gradient(135deg, #003366 0%, #0066cc 100%); color: white;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-heartbeat mr-2"></i> BPJS Kesehatan</h5>
                    <p>Sistem Informasi Fasilitas Kesehatan Terpadu</p>
                    <small>&copy; <?php echo date('Y'); ?> BPJS Kesehatan. All rights reserved.</small>
                </div>
                <div class="col-md-6 text-md-right">
                    <h6>Call Center</h6>
                    <p><i class="fas fa-phone-alt mr-2"></i> 165 (24 Jam)</p>
                    <p><i class="fas fa-envelope mr-2"></i> hubungi@bpjs-kesehatan.go.id</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.form-select').select2({
                theme: 'bootstrap4',
                width: '100%'
            });

            // Auto-hide alerts
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Form validation
            $('form').on('submit', function(e) {
                let isValid = true;
                
                // Check required fields
                $(this).find('[required]').each(function() {
                    if (!$(this).val().trim()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Harap lengkapi semua bidang yang wajib diisi!');
                }
            });
        });
    </script>
</body>
</html>