<?php
session_start();
require_once '../config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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

// Handle form submission untuk pendaftaran
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_peserta = mysqli_real_escape_string($conn, $_POST['nama_peserta']);
    $nik = mysqli_real_escape_string($conn, $_POST['nik']);
    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $kelas_bpjs = mysqli_real_escape_string($conn, $_POST['kelas_bpjs']);
    $faskes_tingkat1 = mysqli_real_escape_string($conn, $_POST['faskes_tingkat1']);
    
    // Generate nomor peserta
    $no_peserta = 'BPJS' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $sql_insert = "INSERT INTO peserta_bpjs (
        no_peserta, nama_peserta, nik, tanggal_lahir, jenis_kelamin, 
        alamat, telepon, kelas_bpjs, faskes_tingkat1, status, 
        created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())";
    
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "sssssssssi", 
        $no_peserta, $nama_peserta, $nik, $tanggal_lahir, $jenis_kelamin,
        $alamat, $telepon, $kelas_bpjs, $faskes_tingkat1, $user_id
    );
    
    if (mysqli_stmt_execute($stmt_insert)) {
        $success = "Pendaftaran peserta berhasil! No. Peserta: " . $no_peserta;
    } else {
        $error = "Gagal mendaftarkan peserta: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt_insert);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Pendaftaran Peserta BPJS - Transaksi</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap4.min.css">
    
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    
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
        .btn-primary {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0052a3 0%, #002244 100%);
        }
        .form-control:focus, .select2-container--default .select2-selection--single:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
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
        .required-label:after {
            content: " *";
            color: #dc3545;
        }
        .transaction-card {
            border-left: 4px solid #0066cc;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eee;
        }
        .info-box h6 {
            color: #0066cc;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-heartbeat mr-2"></i>
                <strong>BPJS KESEHATAN</strong>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="fas fa-home mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="transaksiDropdown" role="button" data-toggle="dropdown">
                            <i class="fas fa-exchange-alt mr-1"></i> Transaksi
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item active" href="pendaftaran.php">Pendaftaran</a>
                            <a class="dropdown-item" href="pembayaran.php">Pembayaran Iuran</a>
                            <a class="dropdown-item" href="kunjungan.php">Kunjungan</a>
                            <a class="dropdown-item" href="klaim.php">Klaim</a>
                            <a class="dropdown-item" href="transfer.php">Transfer Faskes</a>
                            <a class="dropdown-item" href="refund.php">Refund</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../profile.php">
                            <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent pl-0">
                <li class="breadcrumb-item"><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="#">Transaksi</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pendaftaran</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-user-plus mr-2"></i> Pendaftaran Peserta BPJS
                </h2>
                <p class="page-subtitle">Registrasi peserta baru BPJS Kesehatan</p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#infoModal">
                    <i class="fas fa-info-circle mr-1"></i> Panduan
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Form Pendaftaran -->
            <div class="col-lg-8">
                <div class="card transaction-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit mr-2"></i> Form Pendaftaran</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formPendaftaran">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Nama Lengkap</label>
                                    <input type="text" name="nama_peserta" class="form-control" required
                                           placeholder="Masukkan nama lengkap">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">NIK</label>
                                    <input type="text" name="nik" class="form-control" required
                                           placeholder="16 digit NIK" maxlength="16"
                                           pattern="[0-9]{16}" title="Masukkan 16 digit NIK">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Tanggal Lahir</label>
                                    <input type="date" name="tanggal_lahir" class="form-control" required
                                           max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" class="form-control select2" required>
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Nomor Telepon</label>
                                    <input type="tel" name="telepon" class="form-control" required
                                           placeholder="08xxxxxxxxxx" pattern="08[0-9]{9,11}">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Kelas BPJS</label>
                                    <select name="kelas_bpjs" class="form-control select2" required>
                                        <option value="">Pilih Kelas</option>
                                        <option value="1">Kelas 1</option>
                                        <option value="2">Kelas 2</option>
                                        <option value="3">Kelas 3</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label class="required-label">Alamat Lengkap</label>
                                    <textarea name="alamat" class="form-control" rows="3" required
                                              placeholder="Masukkan alamat lengkap"></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Faskes Tingkat 1</label>
                                    <select name="faskes_tingkat1" class="form-control select2" required>
                                        <option value="">Pilih Faskes</option>
                                        <?php
                                        // Ambil data faskes dari database
                                        $faskes_sql = "SELECT * FROM faskes WHERE jenis_faskes IN ('Puskesmas', 'Klinik') AND status = 'aktif' ORDER BY nama_faskes";
                                        $faskes_result = mysqli_query($conn, $faskes_sql);
                                        if ($faskes_result && mysqli_num_rows($faskes_result) > 0) {
                                            while ($faskes = mysqli_fetch_assoc($faskes_result)) {
                                                echo '<option value="' . htmlspecialchars($faskes['id']) . '">' 
                                                     . htmlspecialchars($faskes['nama_faskes']) . ' - ' 
                                                     . htmlspecialchars($faskes['alamat']) 
                                                     . '</option>';
                                            }
                                        } else {
                                            echo '<option value="">Tidak ada data faskes</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label>Catatan Khusus</label>
                                    <textarea name="catatan" class="form-control" rows="3"
                                              placeholder="Masukkan catatan khusus jika ada"></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="confirmData" required>
                                                <label class="form-check-label" for="confirmData">
                                                    Saya menyatakan data yang diisi adalah benar
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <button type="reset" class="btn btn-secondary mr-2">
                                            <i class="fas fa-redo mr-1"></i> Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save mr-1"></i> Simpan Pendaftaran
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Informasi Pendaftaran -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i> Informasi</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <h6><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Persyaratan</h6>
                            <ul class="mb-0 pl-3">
                                <li>NIK 16 digit</li>
                                <li>Foto KTP (jika ada)</li>
                                <li>Usia minimal 0 tahun</li>
                                <li>Alamat lengkap</li>
                            </ul>
                        </div>
                        
                        <div class="info-box">
                            <h6><i class="fas fa-money-bill-wave text-success mr-2"></i>Biaya</h6>
                            <p class="mb-0">
                                <strong>Kelas 1:</strong> Rp 150.000/bulan<br>
                                <strong>Kelas 2:</strong> Rp 100.000/bulan<br>
                                <strong>Kelas 3:</strong> Rp 50.000/bulan
                            </p>
                        </div>
                        
                        <div class="info-box">
                            <h6><i class="fas fa-clock text-info mr-2"></i>Proses</h6>
                            <p class="mb-0">
                                Kartu peserta akan aktif dalam 1x24 jam setelah pembayaran pertama
                            </p>
                        </div>
                        
                        <div class="info-box">
                            <h6><i class="fas fa-phone text-primary mr-2"></i>Bantuan</h6>
                            <p class="mb-0">
                                Hubungi Call Center: <strong>165</strong><br>
                                Email: pendaftaran@bpjs-kesehatan.go.id
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Statistik Pendaftaran -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i> Statistik</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Hitung statistik pendaftaran
                        $today = date('Y-m-d');
                        $month = date('Y-m');
                        
                        // Total pendaftaran hari ini
                        $sql_today = "SELECT COUNT(*) as total FROM peserta_bpjs WHERE DATE(created_at) = '$today'";
                        $result_today = mysqli_query($conn, $sql_today);
                        $today_count = mysqli_fetch_assoc($result_today)['total'];
                        
                        // Total pendaftaran bulan ini
                        $sql_month = "SELECT COUNT(*) as total FROM peserta_bpjs WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'";
                        $result_month = mysqli_query($conn, $sql_month);
                        $month_count = mysqli_fetch_assoc($result_month)['total'];
                        ?>
                        
                        <div class="text-center">
                            <div class="mb-3">
                                <h3 class="text-primary"><?php echo $today_count; ?></h3>
                                <small class="text-muted">Pendaftaran Hari Ini</small>
                            </div>
                            <div class="mb-3">
                                <h3 class="text-success"><?php echo $month_count; ?></h3>
                                <small class="text-muted">Pendaftaran Bulan Ini</small>
                            </div>
                            <div>
                                <h3 class="text-info">
                                    <?php
                                    $sql_total = "SELECT COUNT(*) as total FROM peserta_bpjs";
                                    $result_total = mysqli_query($conn, $sql_total);
                                    $total_count = mysqli_fetch_assoc($result_total)['total'];
                                    echo $total_count;
                                    ?>
                                </h3>
                                <small class="text-muted">Total Peserta Terdaftar</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daftar Pendaftaran Terakhir -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history mr-2"></i> Pendaftaran Terakhir</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="tablePendaftaran">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>No. Peserta</th>
                                <th>Nama</th>
                                <th>NIK</th>
                                <th>Kelas</th>
                                <th>Tanggal Daftar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_recent = "SELECT * FROM peserta_bpjs ORDER BY created_at DESC LIMIT 10";
                            $result_recent = mysqli_query($conn, $sql_recent);
                            
                            if ($result_recent && mysqli_num_rows($result_recent) > 0) {
                                $no = 1;
                                while ($peserta = mysqli_fetch_assoc($result_recent)) {
                                    $status_badge = $peserta['status'] == 'active' ? 'success' : 'secondary';
                                    echo '
                                    <tr>
                                        <td>' . $no++ . '</td>
                                        <td><strong>' . htmlspecialchars($peserta['no_peserta']) . '</strong></td>
                                        <td>' . htmlspecialchars($peserta['nama_peserta']) . '</td>
                                        <td>' . htmlspecialchars($peserta['nik']) . '</td>
                                        <td><span class="badge badge-info">Kelas ' . $peserta['kelas_bpjs'] . '</span></td>
                                        <td>' . date('d/m/Y', strtotime($peserta['created_at'])) . '</td>
                                        <td><span class="badge badge-' . $status_badge . '">' . $peserta['status'] . '</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">Belum ada data pendaftaran</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Modal -->
    <div class="modal fade" id="infoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i> Panduan Pendaftaran</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-check-circle text-success mr-2"></i>Persyaratan Dokumen</h6>
                            <ul>
                                <li>Fotokopi KTP (Asli dan Copy)</li>
                                <li>Fotokopi KK (Asli dan Copy)</li>
                                <li>Pas foto 3x4 (2 lembar)</li>
                                <li>Surat keterangan kerja (jika ada)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-clock text-info mr-2"></i>Waktu Proses</h6>
                            <ul>
                                <li>Verifikasi data: 1-2 jam</li>
                                <li>Pembuatan kartu: 1x24 jam</li>
                                <li>Aktivasi layanan: Setelah pembayaran pertama</li>
                            </ul>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Pastikan data yang diisi sesuai dengan dokumen asli. Data yang salah dapat mengakibatkan penolakan klaim.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print mr-1"></i> Cetak Panduan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%',
                placeholder: 'Pilih opsi...'
            });
            
            // Initialize DataTable
            $('#tablePendaftaran').DataTable({
                "pageLength": 5,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Berikutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
            
            // Auto format phone number
            $('input[name="telepon"]').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 0) {
                    if (value.substring(0, 2) !== '08') {
                        value = '08' + value.substring(2);
                    }
                    if (value.length > 2) {
                        value = value.substring(0, 4) + '-' + value.substring(4, 8) + '-' + value.substring(8, 12);
                    }
                }
                $(this).val(value);
            });
            
            // Auto format NIK
            $('input[name="nik"]').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 16) {
                    value = value.substring(0, 16);
                }
                $(this).val(value);
            });
            
            // Calculate age from birthdate
            $('input[name="tanggal_lahir"]').on('change', function() {
                const birthDate = new Date($(this).val());
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                if (age < 0) {
                    alert('Tanggal lahir tidak valid!');
                    $(this).val('');
                } else if (age > 120) {
                    if (!confirm('Usia lebih dari 120 tahun. Lanjutkan?')) {
                        $(this).val('');
                    }
                }
            });
            
            // Form validation
            $('#formPendaftaran').submit(function(e) {
                const nik = $('input[name="nik"]').val().replace(/\D/g, '');
                const telepon = $('input[name="telepon"]').val().replace(/\D/g, '');
                const tanggal_lahir = $('input[name="tanggal_lahir"]').val();
                
                if (nik.length !== 16) {
                    alert('NIK harus 16 digit!');
                    e.preventDefault();
                    return false;
                }
                
                if (telepon.length < 10 || telepon.length > 12) {
                    alert('Nomor telepon harus 10-12 digit!');
                    e.preventDefault();
                    return false;
                }
                
                if (!tanggal_lahir) {
                    alert('Tanggal lahir harus diisi!');
                    e.preventDefault();
                    return false;
                }
                
                if (!$('#confirmData').is(':checked')) {
                    alert('Harap konfirmasi kebenaran data!');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
        });
        
        // Session timeout
        let idleTime = 0;
        setInterval(() => {
            idleTime++;
            if (idleTime > 25) {
                alert("Sesi Anda akan berakhir dalam 5 menit karena tidak aktif.");
            }
            if (idleTime > 30) {
                window.location.href = "../logout.php?reason=timeout";
            }
        }, 60000);
        
        $(document).on('mousemove keypress click', function() {
            idleTime = 0;
        });
    </script>
</body>
</html>