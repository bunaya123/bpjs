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

// Ambil data dokter - DIPERBAIKI
$query = "SELECT d.*, s.nama_spesialisasi 
          FROM dokter d 
          LEFT JOIN spesialisasi_dokter s ON d.spesialisasi_id = s.id 
          WHERE d.id = ?";
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

// Ambil data spesialisasi untuk dropdown (untuk edit nanti)
$query_spesialis = "SELECT * FROM spesialisasi_dokter ORDER BY nama_spesialisasi";
$result_spesialis = mysqli_query($conn, $query_spesialis);
$spesialis_data = [];
while ($row = mysqli_fetch_assoc($result_spesialis)) {
    $spesialis_data[] = $row;
}

// Update last activity
$_SESSION['last_activity'] = time();

// Fungsi untuk format tanggal
function formatTanggal($date, $format = 'd F Y') {
    if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Detail Dokter - BPJS KESEHATAN</title>
    
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
        
        .profile-card {
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
        
        .profile-header {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            padding: 40px;
            color: white;
            position: relative;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            color: var(--bpjs-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 20px;
            border: 5px solid rgba(255, 255, 255, 0.3);
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .profile-code {
            font-size: 1.1rem;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background-color: rgba(67, 176, 42, 0.2);
            color: #43b02a;
            border: 2px solid rgba(67, 176, 42, 0.3);
        }
        
        .status-inactive {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 2px solid rgba(220, 53, 69, 0.3);
        }
        
        .profile-body {
            padding: 40px;
        }
        
        .section-title {
            color: var(--bpjs-dark);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0, 119, 200, 0.1);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--bpjs-blue);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s;
            border: 1px solid rgba(0, 119, 200, 0.1);
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 119, 200, 0.1);
            border-color: rgba(0, 119, 200, 0.3);
        }
        
        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .info-label i {
            margin-right: 8px;
            width: 20px;
            color: var(--bpjs-blue);
        }
        
        .info-value {
            color: #212529;
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .btn-bpjs {
            background: linear-gradient(135deg, var(--bpjs-blue) 0%, var(--bpjs-dark) 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-bpjs:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 119, 200, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-outline-bpjs {
            border: 2px solid var(--bpjs-blue);
            color: var(--bpjs-blue);
            background: transparent;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-outline-bpjs:hover {
            background-color: var(--bpjs-blue);
            color: white;
            text-decoration: none;
        }
        
        .btn-danger-bpjs {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-danger-bpjs:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .empty-state {
            color: #6c757d;
            text-align: center;
            padding: 20px;
            font-style: italic;
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
            
            .profile-header {
                padding: 30px 20px;
            }
            
            .profile-body {
                padding: 30px 20px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }
            
            .profile-name {
                font-size: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-bpjs, .btn-outline-bpjs, .btn-danger-bpjs {
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
                        <i class="fas fa-user-md me-2"></i>Detail Data Dokter
                    </h3>
                    <p class="mb-0 opacity-75">BPJS Kesehatan - Informasi Lengkap Dokter</p>
                </div>
                <a href="dokter.php" class="back-btn">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Data Dokter
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-container">
        <!-- Profile Card -->
        <div class="profile-card">
            <!-- Header -->
            <div class="profile-header text-center">
                <div class="profile-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <h1 class="profile-name"><?php echo htmlspecialchars($dokter['nama_dokter']); ?></h1>
                <div class="profile-code">
                    <i class="fas fa-hashtag me-1"></i>Kode: <?php echo htmlspecialchars($dokter['kode_dokter']); ?>
                </div>
                <span class="status-badge <?php echo $dokter['status'] == 'aktif' ? 'status-active' : 'status-inactive'; ?>">
                    <i class="fas fa-<?php echo $dokter['status'] == 'aktif' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                    <?php echo $dokter['status'] == 'aktif' ? 'Aktif' : 'Tidak Aktif'; ?>
                </span>
            </div>
            
            <!-- Body -->
            <div class="profile-body">
                <!-- Informasi Umum -->
                <h4 class="section-title">
                    <i class="fas fa-info-circle"></i>Informasi Umum
                </h4>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-stethoscope"></i>Spesialisasi
                        </div>
                        <p class="info-value">
                            <?php echo $dokter['nama_spesialisasi'] ? htmlspecialchars($dokter['nama_spesialisasi']) : '<span class="empty-state">Belum ditentukan</span>'; ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-venus-mars"></i>Jenis Kelamin
                        </div>
                        <p class="info-value">
                            <?php 
                            if ($dokter['jenis_kelamin'] == 'L') {
                                echo '<i class="fas fa-male me-2 text-primary"></i> Laki-laki';
                            } elseif ($dokter['jenis_kelamin'] == 'P') {
                                echo '<i class="fas fa-female me-2 text-danger"></i> Perempuan';
                            } else {
                                echo '-';
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-birthday-cake"></i>Tempat & Tanggal Lahir
                        </div>
                        <p class="info-value">
                            <?php 
                            echo htmlspecialchars($dokter['tempat_lahir'] ?: '-');
                            if ($dokter['tempat_lahir'] && $dokter['tanggal_lahir'] != '0000-00-00') {
                                echo ', ' . formatTanggal($dokter['tanggal_lahir']);
                            } elseif ($dokter['tanggal_lahir'] != '0000-00-00') {
                                echo formatTanggal($dokter['tanggal_lahir']);
                            }
                            ?>
                        </p>
                    </div>
                </div>
                
                <!-- Kontak & Alamat -->
                <h4 class="section-title">
                    <i class="fas fa-address-book"></i>Kontak & Alamat
                </h4>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-phone"></i>No. Telepon
                        </div>
                        <p class="info-value">
                            <?php echo $dokter['no_telepon'] ? htmlspecialchars($dokter['no_telepon']) : '<span class="empty-state">-</span>'; ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-envelope"></i>Email
                        </div>
                        <p class="info-value">
                            <?php echo $dokter['email'] ? htmlspecialchars($dokter['email']) : '<span class="empty-state">-</span>'; ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-home"></i>Alamat
                        </div>
                        <p class="info-value">
                            <?php echo $dokter['alamat'] ? nl2br(htmlspecialchars($dokter['alamat'])) : '<span class="empty-state">Belum diisi</span>'; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Surat Izin Praktik -->
                <h4 class="section-title">
                    <i class="fas fa-file-contract"></i>Surat Izin Praktik (SIP)
                </h4>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-id-badge"></i>Nomor SIP
                        </div>
                        <p class="info-value">
                            <?php echo $dokter['no_sip'] ? htmlspecialchars($dokter['no_sip']) : '<span class="empty-state">-</span>'; ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-calendar-check"></i>Tanggal Berlaku SIP
                        </div>
                        <p class="info-value">
                            <?php echo formatTanggal($dokter['tgl_berlaku_sip']); ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-history"></i>Status Keanggotaan
                        </div>
                        <p class="info-value">
                            <?php 
                            $status_class = $dokter['status'] == 'aktif' ? 'text-success' : 'text-danger';
                            echo '<span class="' . $status_class . '">';
                            echo $dokter['status'] == 'aktif' ? 'Aktif melayani' : 'Tidak aktif melayani';
                            echo '</span>';
                            ?>
                        </p>
                    </div>
                </div>
                
                <!-- Informasi Sistem -->
                <h4 class="section-title">
                    <i class="fas fa-database"></i>Informasi Sistem
                </h4>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-calendar-plus"></i>Tanggal Dibuat
                        </div>
                        <p class="info-value">
                            <?php echo formatTanggal($dokter['created_at'], 'd F Y H:i'); ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-calendar-check"></i>Terakhir Diupdate
                        </div>
                        <p class="info-value">
                            <?php echo $dokter['updated_at'] ? formatTanggal($dokter['updated_at'], 'd F Y H:i') : '-'; ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">
                            <i class="fas fa-id-card"></i>ID Dokter
                        </div>
                        <p class="info-value">
                            <?php echo htmlspecialchars($dokter['id']); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="dokter.php" class="btn-outline-bpjs">
                        <i class="fas fa-arrow-left me-2"></i> Kembali
                    </a>
                    
                    <a href="edit_dokter.php?id=<?php echo $dokter['id']; ?>" class="btn-bpjs">
                        <i class="fas fa-edit me-2"></i> Edit Data
                    </a>
                    
                    <a href="javascript:void(0);" onclick="printDetail()" class="btn-outline-bpjs">
                        <i class="fas fa-print me-2"></i> Cetak Detail
                    </a>
                    
                    <a href="?delete=<?php echo $dokter['id']; ?>" 
                       class="btn-danger-bpjs"
                       onclick="return confirmDelete()">
                        <i class="fas fa-trash me-2"></i> Hapus Data
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container-fluid">
            <p class="mb-2">BPJS Kesehatan System &copy; <?php echo date('Y'); ?> v1.0</p>
            <p class="mb-0">Detail Dokter - <?php echo htmlspecialchars($dokter['nama_dokter']); ?></p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function printDetail() {
            const printContent = `
                <html>
                <head>
                    <title>Detail Dokter - <?php echo htmlspecialchars($dokter['nama_dokter']); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .logo { font-size: 24px; color: #0077c8; margin-bottom: 10px; }
                        .title { font-size: 20px; margin-bottom: 5px; }
                        .subtitle { color: #666; margin-bottom: 20px; }
                        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        .info-table th { background-color: #0077c8; color: white; padding: 10px; text-align: left; }
                        .info-table td { padding: 10px; border-bottom: 1px solid #ddd; }
                        .section { margin-bottom: 30px; }
                        .section-title { background-color: #f8f9fa; padding: 8px 15px; font-weight: bold; border-left: 4px solid #0077c8; margin-bottom: 15px; }
                        .footer { text-align: center; margin-top: 40px; color: #666; font-size: 12px; }
                        @media print { .no-print { display: none; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="logo">BPJS KESEHATAN</div>
                        <div class="title">DETAIL DATA DOKTER</div>
                        <div class="subtitle"><?php echo date('d F Y H:i'); ?></div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Identitas Dokter</div>
                        <table class="info-table">
                            <tr>
                                <th width="30%">Nama Dokter</th>
                                <td><?php echo htmlspecialchars($dokter['nama_dokter']); ?></td>
                            </tr>
                            <tr>
                                <th>Kode Dokter</th>
                                <td><?php echo htmlspecialchars($dokter['kode_dokter']); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td><?php echo $dokter['status'] == 'aktif' ? 'Aktif' : 'Tidak Aktif'; ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Data Pribadi</div>
                        <table class="info-table">
                            <tr>
                                <th width="30%">Spesialisasi</th>
                                <td><?php echo $dokter['nama_spesialisasi'] ? htmlspecialchars($dokter['nama_spesialisasi']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Jenis Kelamin</th>
                                <td><?php echo $dokter['jenis_kelamin'] == 'L' ? 'Laki-laki' : ($dokter['jenis_kelamin'] == 'P' ? 'Perempuan' : '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Tempat & Tanggal Lahir</th>
                                <td><?php echo htmlspecialchars($dokter['tempat_lahir'] ?: '-') . ($dokter['tanggal_lahir'] != '0000-00-00' ? ', ' . formatTanggal($dokter['tanggal_lahir']) : ''); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Kontak & Alamat</div>
                        <table class="info-table">
                            <tr>
                                <th width="30%">No. Telepon</th>
                                <td><?php echo htmlspecialchars($dokter['no_telepon'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($dokter['email'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Alamat</th>
                                <td><?php echo nl2br(htmlspecialchars($dokter['alamat'] ?: '-')); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Surat Izin Praktik</div>
                        <table class="info-table">
                            <tr>
                                <th width="30%">Nomor SIP</th>
                                <td><?php echo htmlspecialchars($dokter['no_sip'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal Berlaku SIP</th>
                                <td><?php echo formatTanggal($dokter['tgl_berlaku_sip']); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="footer">
                        Dicetak dari sistem BPJS Kesehatan<br>
                        <?php echo date('d F Y H:i:s'); ?>
                    </div>
                    
                    <script>
                        function formatTanggal(dateStr) {
                            if (!dateStr || dateStr === '0000-00-00') return '-';
                            const options = { year: 'numeric', month: 'long', day: 'numeric' };
                            return new Date(dateStr).toLocaleDateString('id-ID', options);
                        }
                    </script>
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
        
        function confirmDelete() {
            return confirm('Apakah Anda yakin ingin menghapus data dokter "<?php echo htmlspecialchars($dokter['nama_dokter']); ?>"?\n\nTindakan ini tidak dapat dibatalkan!');
        }
        
        // Format tanggal function untuk JavaScript
        function formatTanggal(dateStr) {
            if (!dateStr || dateStr === '0000-00-00') return '-';
            const date = new Date(dateStr);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('id-ID', options);
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