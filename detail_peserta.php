<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Ambil ID dari parameter GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ID peserta tidak valid");
}

// Cek apakah tabel pembayaran_bpjs ada
$check_table = "SHOW TABLES LIKE 'pembayaran_bpjs'";
$table_result = mysqli_query($conn, $check_table);
$table_exists = mysqli_num_rows($table_result) > 0;

// Ambil data peserta dengan atau tanpa data pembayaran
if ($table_exists) {
    $sql = "SELECT p.*, 
                   DATE_FORMAT(p.tanggal_lahir, '%d %M %Y') as tgl_lahir_format,
                   DATE_FORMAT(p.created_at, '%d %M %Y %H:%i') as bergabung_format,
                   DATE_FORMAT(p.updated_at, '%d %M %Y %H:%i') as terakhir_diupdate,
                   pb.tanggal_pembayaran,
                   pb.jumlah_dibayarkan,
                   pb.metode_pembayaran,
                   pb.denda,
                   pb.status_pembayaran,
                   DATE_FORMAT(pb.tanggal_pembayaran, '%d %M %Y') as tgl_pembayaran_format,
                   DATE_FORMAT(pb.created_at, '%d %M %Y %H:%i') as waktu_pembayaran_format
            FROM peserta p 
            LEFT JOIN (
                SELECT * FROM pembayaran_bpjs 
                WHERE peserta_id = ? 
                ORDER BY tanggal_pembayaran DESC 
                LIMIT 1
            ) pb ON p.id = pb.peserta_id 
            WHERE p.id = ?";
} else {
    // Jika tabel pembayaran_bpjs belum ada
    $sql = "SELECT p.*, 
                   DATE_FORMAT(p.tanggal_lahir, '%d %M %Y') as tgl_lahir_format,
                   DATE_FORMAT(p.created_at, '%d %M %Y %H:%i') as bergabung_format,
                   DATE_FORMAT(p.updated_at, '%d %M %Y %H:%i') as terakhir_diupdate,
                   NULL as tanggal_pembayaran,
                   NULL as jumlah_dibayarkan,
                   NULL as metode_pembayaran,
                   NULL as denda,
                   NULL as status_pembayaran,
                   NULL as tgl_pembayaran_format,
                   NULL as waktu_pembayaran_format
            FROM peserta p 
            WHERE p.id = ?";
}

$stmt = mysqli_prepare($conn, $sql);

if ($table_exists) {
    mysqli_stmt_bind_param($stmt, "ii", $id, $id);
} else {
    mysqli_stmt_bind_param($stmt, "i", $id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$peserta = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$peserta) {
    die("Data peserta tidak ditemukan");
}

// Ambil riwayat pembayaran jika tabel ada
$riwayat_pembayaran = [];
if ($table_exists) {
    $sql_riwayat = "SELECT 
                    DATE_FORMAT(tanggal_pembayaran, '%d %M %Y') as tgl_pembayaran_format,
                    jumlah_dibayarkan,
                    metode_pembayaran,
                    denda,
                    status_pembayaran,
                    DATE_FORMAT(created_at, '%d %M %Y %H:%i') as waktu_bayar_format
                    FROM pembayaran_bpjs 
                    WHERE peserta_id = ? 
                    ORDER BY tanggal_pembayaran DESC 
                    LIMIT 5";
    $stmt_riwayat = mysqli_prepare($conn, $sql_riwayat);
    mysqli_stmt_bind_param($stmt_riwayat, "i", $id);
    mysqli_stmt_execute($stmt_riwayat);
    $result_riwayat = mysqli_stmt_get_result($stmt_riwayat);
    while ($row = mysqli_fetch_assoc($result_riwayat)) {
        $riwayat_pembayaran[] = $row;
    }
    mysqli_stmt_close($stmt_riwayat);
}

// Ambil data user
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// Format mata uang
function formatRupiah($angka) {
    if ($angka === null) return '-';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Hitung iuran berdasarkan gaji (5% dari gaji)
$gaji = $peserta['gaji_dilaporkan'] ?? 0;
$iuran_bpjs = $gaji * 0.05;

// Konfigurasi segmen peserta
$segmen_config = [
    'PPU' => ['primary', 'Pekerja Penerima Upah', 'fas fa-briefcase'],
    'PBPU' => ['info', 'Pekerja Bukan Penerima Upah', 'fas fa-user-tie'],
    'PBI' => ['warning', 'Penerima Bantuan Iuran', 'fas fa-hands-helping']
];
$segmen_info = $segmen_config[$peserta['segmen_peserta'] ?? 'PBI'] ?? $segmen_config['PBI'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Detail Peserta - <?php echo htmlspecialchars($peserta['nama']); ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
    :root {
        --bpjs-primary: #0073e6;
        --bpjs-primary-dark: #0056b3;
        --bpjs-secondary: #00a8ff;
    }
    
    body {
        background-color: #f5f7fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .detail-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        background: white;
        overflow: hidden;
    }
    
    .detail-header {
        background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);
        color: white;
        padding: 20px 30px;
        border-bottom: none;
    }
    
    .info-section {
        padding: 25px 30px;
    }
    
    .info-group {
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid #eee;
    }
    
    .info-group:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .info-label {
        color: #6c757d;
        font-weight: 500;
        margin-bottom: 5px;
        font-size: 14px;
    }
    
    .info-value {
        font-size: 16px;
        font-weight: 500;
        color: #333;
    }
    
    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 14px;
    }
    
    .gender-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
        color: white;
    }
    
    .gender-male {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }
    
    .gender-female {
        background: linear-gradient(135deg, #e83e8c 0%, #c2185b 100%);
    }
    
    .back-btn {
        background: transparent;
        border: 2px solid white;
        color: white;
        border-radius: 8px;
        padding: 8px 16px;
        transition: all 0.3s;
    }
    
    .back-btn:hover {
        background: white;
        color: var(--bpjs-primary);
    }
    
    .payment-success {
        color: #28a745;
        background-color: rgba(40, 167, 69, 0.1);
        padding: 3px 10px;
        border-radius: 5px;
        font-weight: 500;
    }
    
    .payment-pending {
        color: #ffc107;
        background-color: rgba(255, 193, 7, 0.1);
        padding: 3px 10px;
        border-radius: 5px;
        font-weight: 500;
    }
    
    .payment-failed {
        color: #dc3545;
        background-color: rgba(220, 53, 69, 0.1);
        padding: 3px 10px;
        border-radius: 5px;
        font-weight: 500;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0, 115, 230, 0.05);
    }
    
    .total-payment {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 15px;
        border-radius: 10px;
        font-weight: bold;
    }
    
    /* Gaji Styles */
    .gaji-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        padding: 20px;
        margin-top: 10px;
    }
    
    .gaji-value {
        font-size: 24px;
        font-weight: 700;
        color: #28a745;
        margin: 10px 0;
    }
    
    .iuran-value {
        font-size: 18px;
        font-weight: 600;
        color: #007bff;
    }
    
    .update-gaji-btn {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .update-gaji-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        color: white;
    }
    
    .update-segmen-btn {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .update-segmen-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        color: white;
    }
    
    .gaji-timestamp {
        font-size: 12px;
        color: #6c757d;
        font-style: italic;
    }
    
    .gaji-info-icon {
        color: #17a2b8;
        margin-right: 8px;
    }
    
    /* Segmen Styles */
    .segmen-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 14px;
    }
    
    .segmen-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-top: 10px;
        border-left: 5px solid;
    }
    
    .segmen-card-ppu {
        border-left-color: #007bff;
        background: linear-gradient(135deg, rgba(0, 123, 255, 0.05) 0%, rgba(0, 86, 179, 0.05) 100%);
    }
    
    .segmen-card-pbpu {
        border-left-color: #17a2b8;
        background: linear-gradient(135deg, rgba(23, 162, 184, 0.05) 0%, rgba(19, 132, 150, 0.05) 100%);
    }
    
    .segmen-card-pbi {
        border-left-color: #ffc107;
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(224, 168, 0, 0.05) 100%);
    }
    
    .segmen-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        margin-right: 15px;
    }
    
    .segmen-icon-ppu {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }
    
    .segmen-icon-pbpu {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    }
    
    .segmen-icon-pbi {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    }
    
    .segmen-info h5 {
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }
    
    .segmen-info p {
        color: #6c757d;
        margin-bottom: 5px;
        font-size: 14px;
    }
    
    .segmen-details {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-top: 10px;
        border: 1px solid #e9ecef;
    }
    
    .segmen-details h6 {
        font-weight: 600;
        color: #495057;
        margin-bottom: 10px;
    }
    
    .segmen-details ul {
        margin-bottom: 0;
        padding-left: 20px;
    }
    
    .segmen-details li {
        margin-bottom: 5px;
        font-size: 14px;
        color: #6c757d;
    }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-primary-dark) 100%);">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <div class="bg-white rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-heartbeat text-primary"></i>
            </div>
            <div>
                <span class="fw-bold">BPJS Kesehatan</span><br>
                <small class="opacity-75">Detail Peserta</small>
            </div>
        </a>
        <div class="navbar-nav ms-auto">
            <a href="peserta_bpjs.php" class="btn back-btn">
                <i class="fas fa-arrow-left me-2"></i> Kembali
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="detail-card">
                <!-- Header -->
                <div class="detail-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="gender-icon <?php echo $peserta['jenis_kelamin'] == 'L' ? 'gender-male' : 'gender-female'; ?> me-3">
                                <?php echo $peserta['jenis_kelamin'] == 'L' ? 'L' : 'P'; ?>
                            </div>
                            <div>
                                <h3 class="mb-1"><?php echo htmlspecialchars($peserta['nama']); ?></h3>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-id-card me-1"></i> No Kartu: <?php echo htmlspecialchars($peserta['no_kartu']); ?>
                                    <?php if ($gaji > 0): ?>
                                    <span class="ms-3">
                                        <i class="fas fa-money-bill-wave me-1"></i> Gaji: <?php echo formatRupiah($gaji); ?>
                                    </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <!-- Segmen Badge -->
                            <span class="badge segmen-badge bg-<?php echo $segmen_info[0]; ?> me-2">
                                <i class="<?php echo $segmen_info[2]; ?> me-1"></i> <?php echo $segmen_info[1]; ?>
                            </span>
                            
                            <!-- Status Badge -->
                            <?php 
                            $status_config = [
                                'active' => ['success', 'Aktif', 'fas fa-check-circle'],
                                'inactive' => ['danger', 'Non-Aktif', 'fas fa-times-circle'],
                                'pending' => ['warning', 'Pending', 'fas fa-clock']
                            ];
                            $status = $status_config[$peserta['status']] ?? $status_config['pending'];
                            ?>
                            <span class="badge status-badge bg-<?php echo $status[0]; ?>">
                                <i class="<?php echo $status[2]; ?> me-1"></i> <?php echo $status[1]; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="info-section">
                    <div class="row">
                        <!-- Kolom 1 -->
                        <div class="col-md-6">
                            <!-- Informasi Pribadi -->
                            <div class="info-group">
                                <h5 class="mb-3"><i class="fas fa-user me-2 text-primary"></i> Informasi Pribadi</h5>
                                <div class="mb-3">
                                    <div class="info-label">NIK</div>
                                    <div class="info-value"><?php echo htmlspecialchars($peserta['nik']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Jenis Kelamin</div>
                                    <div class="info-value">
                                        <?php echo $peserta['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Tempat, Tanggal Lahir</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($peserta['tempat_lahir'] ?? '-'); ?>, 
                                        <?php echo $peserta['tgl_lahir_format']; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Alamat</div>
                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($peserta['alamat'] ?? '-')); ?></div>
                                </div>
                            </div>
                            
                            <!-- Informasi Kontak -->
                            <div class="info-group">
                                <h5 class="mb-3"><i class="fas fa-address-book me-2 text-primary"></i> Informasi Kontak</h5>
                                <div class="mb-3">
                                    <div class="info-label">No. Telepon</div>
                                    <div class="info-value">
                                        <i class="fas fa-phone me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($peserta['no_telepon'] ?? '-'); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Email</div>
                                    <div class="info-value">
                                        <i class="fas fa-envelope me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($peserta['email'] ?? '-'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Kolom 2 -->
                        <div class="col-md-6">
                            <!-- Informasi BPJS -->
                            <div class="info-group">
                                <h5 class="mb-3"><i class="fas fa-heartbeat me-2 text-primary"></i> Informasi BPJS</h5>
                                <div class="mb-3">
                                    <div class="info-label">No. Kartu BPJS</div>
                                    <div class="info-value">
                                        <i class="fas fa-id-card me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($peserta['no_kartu']); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Faskes</div>
                                    <div class="info-value">
                                        <i class="fas fa-hospital me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($peserta['faskes'] ?? '-'); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Kelas BPJS</div>
                                    <div class="info-value">
                                        <i class="fas fa-star me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($peserta['kelas_bpjs'] ?? '-'); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Tanggal Bergabung</div>
                                    <div class="info-value">
                                        <i class="fas fa-calendar-alt me-2 text-muted"></i>
                                        <?php echo $peserta['bergabung_format']; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informasi Pembayaran Terbaru -->
                            <div class="info-group">
                                <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-primary"></i> Pembayaran Terakhir</h5>
                                <?php if ($peserta['tanggal_pembayaran']): ?>
                                <div class="mb-3">
                                    <div class="info-label">Tanggal Pembayaran</div>
                                    <div class="info-value">
                                        <i class="fas fa-calendar-check me-2 text-muted"></i>
                                        <?php echo $peserta['tgl_pembayaran_format']; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Jumlah Dibayarkan</div>
                                    <div class="info-value fw-bold" style="color: #28a745;">
                                        <i class="fas fa-money-bill-wave me-2"></i>
                                        <?php echo formatRupiah($peserta['jumlah_dibayarkan']); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Metode Pembayaran</div>
                                    <div class="info-value">
                                        <i class="fas fa-wallet me-2 text-muted"></i>
                                        <?php 
                                        $metode = [
                                            'transfer' => 'Transfer Bank',
                                            'tunai' => 'Tunai',
                                            'debit' => 'Kartu Debit',
                                            'kredit' => 'Kartu Kredit',
                                            'ewallet' => 'E-Wallet'
                                        ];
                                        echo $metode[$peserta['metode_pembayaran']] ?? $peserta['metode_pembayaran'] ?? '-';
                                        ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">Denda</div>
                                    <div class="info-value" style="color: #dc3545;">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php echo $peserta['denda'] > 0 ? formatRupiah($peserta['denda']) : 'Tidak ada denda'; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Status Pembayaran</div>
                                    <div class="info-value">
                                        <?php
                                        $status_pembayaran = $peserta['status_pembayaran'] ?? 'pending';
                                        $status_class = '';
                                        if ($status_pembayaran == 'success') {
                                            $status_class = 'payment-success';
                                            $status_text = 'Lunas';
                                            $icon = 'fas fa-check-circle';
                                        } elseif ($status_pembayaran == 'pending') {
                                            $status_class = 'payment-pending';
                                            $status_text = 'Menunggu';
                                            $icon = 'fas fa-clock';
                                        } else {
                                            $status_class = 'payment-failed';
                                            $status_text = 'Gagal';
                                            $icon = 'fas fa-times-circle';
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>">
                                            <i class="<?php echo $icon; ?> me-1"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                    <p class="text-muted mb-0">Belum ada riwayat pembayaran</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- INFORMASI SEGMEN PESERTA - BAGIAN BARU -->
                    <div class="info-group">
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mb-3"><i class="fas fa-users me-2 text-primary"></i> Informasi Segmen Peserta</h5>
                                
                                <!-- Segmen Card -->
                                <div class="segmen-card segmen-card-<?php echo strtolower($peserta['segmen_peserta'] ?? 'pbi'); ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="segmen-icon segmen-icon-<?php echo strtolower($peserta['segmen_peserta'] ?? 'pbi'); ?>">
                                            <i class="<?php echo $segmen_info[2]; ?>"></i>
                                        </div>
                                        <div class="segmen-info flex-grow-1">
                                            <h5 class="mb-1"><?php echo $segmen_info[1]; ?> (<?php echo $peserta['segmen_peserta'] ?? 'PBI'; ?>)</h5>
                                            <p class="mb-2">
                                                <?php
                                                $deskripsi = [
                                                    'PPU' => 'Peserta dengan status sebagai karyawan/pekerja yang menerima gaji tetap dari perusahaan atau instansi',
                                                    'PBPU' => 'Peserta dengan status pekerja mandiri, wiraswasta, atau profesional tanpa pemberi kerja tetap',
                                                    'PBI' => 'Peserta yang iurannya ditanggung oleh pemerintah karena termasuk dalam kategori masyarakat tidak mampu'
                                                ];
                                                echo $deskripsi[$peserta['segmen_peserta'] ?? 'PBI'] ?? 'Informasi segmen peserta';
                                                ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-<?php echo $segmen_info[0]; ?>">
                                                    <i class="<?php echo $segmen_info[2]; ?> me-1"></i> 
                                                    <?php echo $peserta['segmen_peserta'] ?? 'PBI'; ?>
                                                </span>
                                                <a href="edit_peserta.php?id=<?php echo $peserta['id']; ?>#form-segmen" class="btn btn-sm update-segmen-btn">
                                                    <i class="fas fa-edit me-1"></i> Ubah Segmen
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Detail Segmen -->
                                    <div class="segmen-details mt-3">
                                        <h6>Karakteristik Segmen <?php echo $peserta['segmen_peserta'] ?? 'PBI'; ?>:</h6>
                                        <ul>
                                            <?php
                                            $karakteristik = [
                                                'PPU' => [
                                                    'Gaji tetap dari perusahaan/pemberi kerja',
                                                    'Iuran dibayar bersama (perusahaan dan pekerja)',
                                                    'Wajib melaporkan gaji untuk perhitungan iuran',
                                                    'Potongan iuran langsung dari gaji',
                                                    'Memiliki hubungan kerja formal'
                                                ],
                                                'PBPU' => [
                                                    'Tidak memiliki pemberi kerja tetap',
                                                    'Iuran dibayar mandiri',
                                                    'Bebas memilih kelas layanan',
                                                    'Pembayaran iuran secara berkala',
                                                    'Termasuk wiraswasta, profesional, pekerja lepas'
                                                ],
                                                'PBI' => [
                                                    'Iuran ditanggung pemerintah',
                                                    'Untuk masyarakat tidak mampu',
                                                    'Kelas layanan ditentukan pemerintah',
                                                    'Pendaftaran melalui data terpadu kesejahteraan sosial',
                                                    'Tidak ada pembayaran iuran dari peserta'
                                                ]
                                            ];
                                            $karakteristik_segmen = $karakteristik[$peserta['segmen_peserta'] ?? 'PBI'] ?? $karakteristik['PBI'];
                                            foreach ($karakteristik_segmen as $item):
                                            ?>
                                            <li><?php echo $item; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        
                                        <?php if (($peserta['segmen_peserta'] ?? '') === 'PPU' && $gaji > 0): ?>
                                        <div class="alert alert-success mt-2 mb-0 p-2">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Data gaji telah dilaporkan:</strong> <?php echo formatRupiah($gaji); ?>
                                            <br>
                                            <small>Iuran bulanan: <?php echo formatRupiah($iuran_bpjs); ?> (5% dari gaji)</small>
                                        </div>
                                        <?php elseif (($peserta['segmen_peserta'] ?? '') === 'PPU'): ?>
                                        <div class="alert alert-warning mt-2 mb-0 p-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Perhatian:</strong> Peserta PPU wajib melaporkan gaji untuk perhitungan iuran.
                                            <a href="edit_peserta.php?id=<?php echo $peserta['id']; ?>#form-gaji" class="alert-link">Laporkan gaji sekarang</a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- INFORMASI GAJI DAN IURAN -->
                    <div class="info-group">
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mb-3"><i class="fas fa-money-bill-wave me-2 text-success"></i> Laporan Gaji & Iuran</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="gaji-card">
                                            <div class="info-label">
                                                <i class="fas fa-money-bill-alt gaji-info-icon"></i> Gaji Dilaporkan
                                            </div>
                                            <div class="gaji-value">
                                                <?php echo formatRupiah($gaji); ?>
                                            </div>
                                            <div class="gaji-timestamp">
                                                <?php if ($peserta['terakhir_diupdate']): ?>
                                                    <i class="fas fa-clock me-1"></i>
                                                    Terakhir diperbarui: <?php echo $peserta['terakhir_diupdate']; ?>
                                                <?php else: ?>
                                                    <i class="fas fa-clock me-1"></i>
                                                    Data dibuat: <?php echo $peserta['bergabung_format']; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="gaji-card">
                                            <div class="info-label">
                                                <i class="fas fa-percentage gaji-info-icon"></i> Iuran BPJS (5%)
                                            </div>
                                            <div class="iuran-value">
                                                <?php echo formatRupiah($iuran_bpjs); ?>
                                            </div>
                                            <small class="text-muted">Perhitungan: 5% dari gaji dilaporkan</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="gaji-card">
                                            <div class="info-label">
                                                <i class="fas fa-chart-bar gaji-info-icon"></i> Status Iuran
                                            </div>
                                            <div class="mt-3">
                                                <?php if ($gaji > 0): ?>
                                                    <span class="badge bg-success p-2">
                                                        <i class="fas fa-check-circle me-1"></i> 
                                                        Gaji Terlapor
                                                    </span>
                                                    <p class="mt-2 mb-2">
                                                        <small>
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Iuran bulanan: <?php echo formatRupiah($iuran_bpjs); ?>
                                                        </small>
                                                    </p>
                                                <?php else: ?>
                                                    <span class="badge bg-warning p-2">
                                                        <i class="fas fa-exclamation-triangle me-1"></i> 
                                                        Belum Ada Data Gaji
                                                    </span>
                                                    <p class="mt-2 mb-2">
                                                        <small>
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Silakan tambahkan data gaji untuk menghitung iuran
                                                        </small>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-info-circle fa-2x me-3"></i>
                                                <div>
                                                    <h6 class="alert-heading mb-2">Informasi Perhitungan Iuran</h6>
                                                    <p class="mb-0">
                                                        • Iuran BPJS Kesehatan dihitung sebesar 5% dari gaji yang dilaporkan<br>
                                                        • Gaji yang dilaporkan harus sesuai dengan slip gaji resmi<br>
                                                        • Perhitungan iuran akan digunakan untuk menentukan besaran pembayaran bulanan<br>
                                                        • Untuk peserta PPU, pelaporan gaji wajib dilakukan setiap bulan
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Riwayat Pembayaran -->
                    <div class="info-group">
                        <h5 class="mb-3"><i class="fas fa-history me-2 text-primary"></i> Riwayat Pembayaran</h5>
                        <?php if (!empty($riwayat_pembayaran)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jumlah</th>
                                        <th>Denda</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                        <th>Waktu Bayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($riwayat_pembayaran as $pembayaran): ?>
                                    <tr>
                                        <td><?php echo $pembayaran['tgl_pembayaran_format']; ?></td>
                                        <td class="fw-bold" style="color: #28a745;">
                                            <?php echo formatRupiah($pembayaran['jumlah_dibayarkan']); ?>
                                        </td>
                                        <td>
                                            <?php if ($pembayaran['denda'] > 0): ?>
                                            <span class="badge bg-danger">
                                                <?php echo formatRupiah($pembayaran['denda']); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-success">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $metode = [
                                                'transfer' => 'Transfer',
                                                'tunai' => 'Tunai',
                                                'debit' => 'Debit',
                                                'kredit' => 'Kredit',
                                                'ewallet' => 'E-Wallet'
                                            ];
                                            echo $metode[$pembayaran['metode_pembayaran']] ?? $pembayaran['metode_pembayaran'];
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            if ($pembayaran['status_pembayaran'] == 'success') {
                                                $status_class = 'payment-success';
                                                $status_text = 'Lunas';
                                            } elseif ($pembayaran['status_pembayaran'] == 'pending') {
                                                $status_class = 'payment-pending';
                                                $status_text = 'Pending';
                                            } else {
                                                $status_class = 'payment-failed';
                                                $status_text = 'Gagal';
                                            }
                                            ?>
                                            <span class="<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $pembayaran['waktu_bayar_format']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada riwayat pembayaran</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="info-group">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-3"><i class="fas fa-cogs me-2 text-primary"></i> Tindakan</h5>
                                <div class="d-flex gap-2">
                                    <a href="edit_peserta.php?id=<?php echo $peserta['id']; ?>" class="btn btn-primary flex-fill">
                                        <i class="fas fa-edit me-2"></i> Edit Data Peserta
                                    </a>
                                    <a href="edit_peserta.php?id=<?php echo $peserta['id']; ?>#form-segmen" class="btn btn-info flex-fill update-segmen-btn">
                                        <i class="fas fa-users me-2"></i> Ubah Segmen
                                    </a>
                                    <a href="edit_peserta.php?id=<?php echo $peserta['id']; ?>#form-gaji" class="btn btn-success flex-fill update-gaji-btn">
                                        <i class="fas fa-money-bill-wave me-2"></i> Update Gaji
                                    </a>
                                    <a href="tambah_pembayaran.php?peserta_id=<?php echo $peserta['id']; ?>" class="btn btn-warning flex-fill" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white;">
                                        <i class="fas fa-plus-circle me-2"></i> Tambah Pembayaran
                                    </a>
                                    <button class="btn btn-outline-danger flex-fill" onclick="confirmDelete()">
                                        <i class="fas fa-trash me-2"></i> Hapus Peserta
                                    </button>
                                </div>
                            </div>
                            <?php if (!empty($riwayat_pembayaran)): ?>
                            <div class="col-md-4">
                                <div class="total-payment mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small>Total Pembayaran</small>
                                            <h4 class="mb-0">
                                                <?php
                                                $total = array_sum(array_column($riwayat_pembayaran, 'jumlah_dibayarkan'));
                                                echo formatRupiah($total);
                                                ?>
                                            </h4>
                                        </div>
                                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer Notes -->
            <div class="mt-3 text-center text-muted">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    Data ini adalah informasi resmi peserta BPJS Kesehatan. 
                    <?php if ($gaji > 0): ?>
                        <span class="ms-2">
                            <i class="fas fa-money-bill-wave me-1"></i>
                            Iuran bulanan berdasarkan gaji: <?php echo formatRupiah($iuran_bpjs); ?>
                        </span>
                    <?php endif; ?>
                    <span class="ms-2">
                        <i class="fas fa-users me-1"></i>
                        Segmen: <span class="badge bg-<?php echo $segmen_info[0]; ?>"><?php echo $peserta['segmen_peserta'] ?? 'PBI'; ?></span>
                    </span>
                </small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete() {
    if (confirm('Apakah Anda yakin ingin menghapus data peserta ini?')) {
        window.location.href = 'delete_peserta.php?id=<?php echo $peserta['id']; ?>';
    }
}

function goBack() {
    window.history.back();
}

// Scroll ke bagian tertentu jika ada hash
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        setTimeout(() => {
            if (hash === '#form-segmen') {
                const segmenSection = document.querySelector('.info-group:has(h5 i.fa-users)');
                if (segmenSection) {
                    segmenSection.scrollIntoView({ behavior: 'smooth' });
                }
            } else if (hash === '#form-gaji') {
                const gajiSection = document.querySelector('.info-group:has(h5 i.fa-money-bill-wave)');
                if (gajiSection) {
                    gajiSection.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }, 100);
    }
});
</script>

</body>
</html>