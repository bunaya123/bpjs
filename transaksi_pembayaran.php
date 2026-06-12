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

// Handle form submission untuk pembayaran
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_peserta = mysqli_real_escape_string($conn, $_POST['no_peserta']);
    $jumlah_bayar = mysqli_real_escape_string($conn, $_POST['jumlah_bayar']);
    $periode = mysqli_real_escape_string($conn, $_POST['periode']);
    $metode_bayar = mysqli_real_escape_string($conn, $_POST['metode_bayar']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    
    // Generate nomor transaksi
    $no_transaksi = 'TRX' . date('YmdHis') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $sql_insert = "INSERT INTO pembayaran_iuran (
        no_transaksi, no_peserta, jumlah_bayar, periode, metode_bayar, 
        keterangan, status, created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW())";
    
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "ssdsssi", 
        $no_transaksi, $no_peserta, $jumlah_bayar, $periode, $metode_bayar,
        $keterangan, $user_id
    );
    
    if (mysqli_stmt_execute($stmt_insert)) {
        $success = "Pembayaran berhasil! No. Transaksi: " . $no_transaksi;
    } else {
        $error = "Gagal melakukan pembayaran: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt_insert);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Pembayaran Iuran BPJS - Transaksi</title>
    
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
        .payment-card {
            border-left: 4px solid #28a745;
        }
        .amount-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            border: 2px dashed #dee2e6;
        }
        .amount-box h3 {
            color: #28a745;
            font-weight: bold;
        }
        .method-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        .method-bank { color: #0066cc; }
        .method-cash { color: #28a745; }
        .method-transfer { color: #17a2b8; }
        .method-qris { color: #ffc107; }
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
                            <a class="dropdown-item" href="pendaftaran.php">Pendaftaran</a>
                            <a class="dropdown-item active" href="pembayaran.php">Pembayaran Iuran</a>
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
                <li class="breadcrumb-item active" aria-current="page">Pembayaran Iuran</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-money-bill-wave mr-2"></i> Pembayaran Iuran BPJS
                </h2>
                <p class="page-subtitle">Bayar iuran peserta BPJS Kesehatan</p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-success" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Cetak Bukti
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
            <!-- Form Pembayaran -->
            <div class="col-lg-8">
                <div class="card payment-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-credit-card mr-2"></i> Form Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formPembayaran">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">No. Peserta</label>
                                    <select name="no_peserta" class="form-control select2" required
                                            id="selectPeserta" onchange="loadPesertaData()">
                                        <option value="">Pilih Peserta</option>
                                        <?php
                                        // Ambil data peserta
                                        $peserta_sql = "SELECT * FROM peserta_bpjs WHERE status = 'active' ORDER BY nama_peserta";
                                        $peserta_result = mysqli_query($conn, $peserta_sql);
                                        if ($peserta_result && mysqli_num_rows($peserta_result) > 0) {
                                            while ($peserta = mysqli_fetch_assoc($peserta_result)) {
                                                echo '<option value="' . htmlspecialchars($peserta['no_peserta']) . '" 
                                                      data-nama="' . htmlspecialchars($peserta['nama_peserta']) . '"
                                                      data-kelas="' . $peserta['kelas_bpjs'] . '">' 
                                                      . htmlspecialchars($peserta['no_peserta']) . ' - ' 
                                                      . htmlspecialchars($peserta['nama_peserta']) 
                                                      . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Periode</label>
                                    <select name="periode" class="form-control select2" required>
                                        <option value="">Pilih Periode</option>
                                        <?php
                                        // Generate pilihan periode 12 bulan ke depan
                                        $current_month = date('Y-m');
                                        for ($i = 0; $i < 12; $i++) {
                                            $month = date('Y-m', strtotime("+$i months"));
                                            $month_name = date('F Y', strtotime($month));
                                            echo '<option value="' . $month . '">' . $month_name . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Jumlah Bayar (Rp)</label>
                                    <input type="number" name="jumlah_bayar" class="form-control" required
                                           id="jumlahBayar" min="0" step="1000"
                                           placeholder="Masukkan jumlah bayar">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="required-label">Metode Pembayaran</label>
                                    <select name="metode_bayar" class="form-control select2" required>
                                        <option value="">Pilih Metode</option>
                                        <option value="transfer">
                                            <i class="fas fa-university method-icon method-bank"></i> Transfer Bank
                                        </option>
                                        <option value="tunai">
                                            <i class="fas fa-money-bill-wave method-icon method-cash"></i> Tunai
                                        </option>
                                        <option value="e-wallet">
                                            <i class="fas fa-wallet method-icon method-transfer"></i> E-Wallet
                                        </option>
                                        <option value="qris">
                                            <i class="fas fa-qrcode method-icon method-qris"></i> QRIS
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label>Keterangan</label>
                                    <textarea name="keterangan" class="form-control" rows="2"
                                              placeholder="Masukkan keterangan pembayaran (opsional)"></textarea>
                                </div>
                                
                                <!-- Informasi Peserta -->
                                <div class="col-12 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6><i class="fas fa-user-circle mr-2 text-primary"></i>Informasi Peserta</h6>
                                            <div class="row mt-2">
                                                <div class="col-md-4">
                                                    <small class="text-muted">Nama Peserta:</small>
                                                    <p id="infoNama" class="mb-1">-</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">Kelas:</small>
                                                    <p id="infoKelas" class="mb-1">-</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">Tarif Standar:</small>
                                                    <p id="infoTarif" class="mb-1">-</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Total Pembayaran -->
                            <div class="amount-box">
                                <small class="text-muted">TOTAL PEMBAYARAN</small>
                                <h3 id="totalAmount">Rp 0</h3>
                                <small class="text-muted" id="terbilang">Nol Rupiah</small>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="confirmPayment" required>
                                                <label class="form-check-label" for="confirmPayment">
                                                    Saya telah menerima pembayaran
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <button type="reset" class="btn btn-secondary mr-2">
                                            <i class="fas fa-redo mr-1"></i> Reset
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check-circle mr-1"></i> Konfirmasi Pembayaran
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Informasi Pembayaran -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i> Informasi Tarif</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Tarif/Bulan</th>
                                        <th>Fasilitas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge badge-primary">Kelas 1</span></td>
                                        <td>Rp 150.000</td>
                                        <td>Ruang VIP</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-info">Kelas 2</span></td>
                                        <td>Rp 100.000</td>
                                        <td>Ruang Kelas 1</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-secondary">Kelas 3</span></td>
                                        <td>Rp 50.000</td>
                                        <td>Ruang Kelas 2</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-lightbulb mr-2"></i>
                            <strong>Tips:</strong> Bayar iuran tepat waktu untuk menghindari denda.
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Perhatian:</strong> Tunggakan lebih dari 3 bulan akan mengakibatkan penangguhan layanan.
                        </div>
                    </div>
                </div>
                
                <!-- Metode Pembayaran -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-credit-card mr-2"></i> Metode Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-university method-icon method-bank"></i>
                                    <div>
                                        <h6 class="mb-1">Transfer Bank</h6>
                                        <small class="text-muted">BNI: 1234567890 a.n BPJS</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-money-bill-wave method-icon method-cash"></i>
                                    <div>
                                        <h6 class="mb-1">Tunai</h6>
                                        <small class="text-muted">Bayar di kantor BPJS</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-wallet method-icon method-transfer"></i>
                                    <div>
                                        <h6 class="mb-1">E-Wallet</h6>
                                        <small class="text-muted">OVO, DANA, GoPay</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-qrcode method-icon method-qris"></i>
                                    <div>
                                        <h6 class="mb-1">QRIS</h6>
                                        <small class="text-muted">Scan QR Code</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Riwayat Pembayaran -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history mr-2"></i> Riwayat Pembayaran Terakhir</h5>
                <a href="#" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="tablePembayaran">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>No. Transaksi</th>
                                <th>No. Peserta</th>
                                <th>Jumlah</th>
                                <th>Periode</th>
                                <th>Metode</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_history = "SELECT * FROM pembayaran_iuran ORDER BY created_at DESC LIMIT 10";
                            $result_history = mysqli_query($conn, $sql_history);
                            
                            if ($result_history && mysqli_num_rows($result_history) > 0) {
                                $no = 1;
                                while ($pembayaran = mysqli_fetch_assoc($result_history)) {
                                    $status_badge = $pembayaran['status'] == 'completed' ? 'success' : 
                                                  ($pembayaran['status'] == 'pending' ? 'warning' : 'danger');
                                    $metode_icon = $pembayaran['metode_bayar'] == 'transfer' ? 'university' :
                                                  ($pembayaran['metode_bayar'] == 'tunai' ? 'money-bill-wave' :
                                                  ($pembayaran['metode_bayar'] == 'e-wallet' ? 'wallet' : 'qrcode'));
                                    
                                    echo '
                                    <tr>
                                        <td>' . $no++ . '</td>
                                        <td><strong>' . htmlspecialchars($pembayaran['no_transaksi']) . '</strong></td>
                                        <td>' . htmlspecialchars($pembayaran['no_peserta']) . '</td>
                                        <td class="text-success">Rp ' . number_format($pembayaran['jumlah_bayar'], 0, ',', '.') . '</td>
                                        <td>' . date('M Y', strtotime($pembayaran['periode'])) . '</td>
                                        <td><i class="fas fa-' . $metode_icon . ' mr-1"></i>' . $pembayaran['metode_bayar'] . '</td>
                                        <td>' . date('d/m/Y H:i', strtotime($pembayaran['created_at'])) . '</td>
                                        <td><span class="badge badge-' . $status_badge . '">' . $pembayaran['status'] . '</span></td>
                                    </tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">Belum ada data pembayaran</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
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
        // Tarif berdasarkan kelas
        const tarifKelas = {
            '1': 150000,
            '2': 100000,
            '3': 50000
        };
        
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%',
                placeholder: 'Pilih opsi...'
            });
            
            // Initialize DataTable
            $('#tablePembayaran').DataTable({
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
                },
                "order": [[0, "desc"]]
            });
            
            // Format jumlah bayar dengan separator ribuan
            $('#jumlahBayar').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value) {
                    $(this).val(parseInt(value).toLocaleString('id-ID'));
                    updateTotalAmount(parseInt(value));
                } else {
                    updateTotalAmount(0);
                }
            });
            
            // Update total amount display
            function updateTotalAmount(amount) {
                $('#totalAmount').text('Rp ' + amount.toLocaleString('id-ID'));
                $('#terbilang').text(terbilang(amount) + ' Rupiah');
            }
            
            // Fungsi terbilang
            function terbilang(angka) {
                const bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
                let terbilang = '';
                
                if (angka < 12) {
                    terbilang = bilangan[angka];
                } else if (angka < 20) {
                    terbilang = terbilang(angka - 10) + ' Belas';
                } else if (angka < 100) {
                    terbilang = terbilang(Math.floor(angka / 10)) + ' Puluh ' + terbilang(angka % 10);
                } else if (angka < 200) {
                    terbilang = 'Seratus ' + terbilang(angka - 100);
                } else if (angka < 1000) {
                    terbilang = terbilang(Math.floor(angka / 100)) + ' Ratus ' + terbilang(angka % 100);
                } else if (angka < 2000) {
                    terbilang = 'Seribu ' + terbilang(angka - 1000);
                } else if (angka < 1000000) {
                    terbilang = terbilang(Math.floor(angka / 1000)) + ' Ribu ' + terbilang(angka % 1000);
                } else if (angka < 1000000000) {
                    terbilang = terbilang(Math.floor(angka / 1000000)) + ' Juta ' + terbilang(angka % 1000000);
                }
                
                return terbilang.trim();
            }
            
            // Form validation
            $('#formPembayaran').submit(function(e) {
                const jumlahBayar = $('#jumlahBayar').val().replace(/\./g, '');
                const noPeserta = $('#selectPeserta').val();
                const periode = $('select[name="periode"]').val();
                const metodeBayar = $('select[name="metode_bayar"]').val();
                
                if (!noPeserta) {
                    alert('Harap pilih peserta!');
                    e.preventDefault();
                    return false;
                }
                
                if (!periode) {
                    alert('Harap pilih periode!');
                    e.preventDefault();
                    return false;
                }
                
                if (!metodeBayar) {
                    alert('Harap pilih metode pembayaran!');
                    e.preventDefault();
                    return false;
                }
                
                if (!jumlahBayar || parseInt(jumlahBayar) < 1000) {
                    alert('Jumlah pembayaran minimal Rp 1.000!');
                    e.preventDefault();
                    return false;
                }
                
                if (!$('#confirmPayment').is(':checked')) {
                    alert('Harap konfirmasi pembayaran!');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
        });
        
        // Load data peserta saat dipilih
        function loadPesertaData() {
            const select = document.getElementById('selectPeserta');
            const selectedOption = select.options[select.selectedIndex];
            const nama = selectedOption.getAttribute('data-nama');
            const kelas = selectedOption.getAttribute('data-kelas');
            
            if (nama && kelas) {
                document.getElementById('infoNama').textContent = nama;
                document.getElementById('infoKelas').textContent = 'Kelas ' + kelas;
                document.getElementById('infoTarif').textContent = 'Rp ' + tarifKelas[kelas].toLocaleString('id-ID');
                
                // Auto set jumlah bayar berdasarkan tarif
                document.getElementById('jumlahBayar').value = tarifKelas[kelas].toLocaleString('id-ID');
                updateTotalAmount(tarifKelas[kelas]);
            } else {
                document.getElementById('infoNama').textContent = '-';
                document.getElementById('infoKelas').textContent = '-';
                document.getElementById('infoTarif').textContent = '-';
                document.getElementById('jumlahBayar').value = '';
                updateTotalAmount(0);
            }
        }
        
        // Update total amount display
        function updateTotalAmount(amount) {
            document.getElementById('totalAmount').textContent = 'Rp ' + amount.toLocaleString('id-ID');
            document.getElementById('terbilang').textContent = terbilang(amount) + ' Rupiah';
        }
        
        // Fungsi terbilang
        function terbilang(angka) {
            const bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
            let kalimat = '';
            
            if (angka < 12) {
                kalimat = bilangan[angka];
            } else if (angka < 20) {
                kalimat = terbilang(angka - 10) + ' Belas';
            } else if (angka < 100) {
                kalimat = terbilang(Math.floor(angka / 10)) + ' Puluh ' + terbilang(angka % 10);
            } else if (angka < 200) {
                kalimat = 'Seratus ' + terbilang(angka - 100);
            } else if (angka < 1000) {
                kalimat = terbilang(Math.floor(angka / 100)) + ' Ratus ' + terbilang(angka % 100);
            } else if (angka < 2000) {
                kalimat = 'Seribu ' + terbilang(angka - 1000);
            } else if (angka < 1000000) {
                kalimat = terbilang(Math.floor(angka / 1000)) + ' Ribu ' + terbilang(angka % 1000);
            }
            
            return kalimat.trim().replace(/  /g, ' ');
        }
        
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