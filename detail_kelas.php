<?php
// detail_kelas.php
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

// Ambil ID kelas dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kelas.php");
    exit();
}

$kelas_id = intval($_GET['id']);

// Ambil data kelas
$sql = "SELECT * FROM kelas WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $kelas_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: kelas.php?error=Kelas+tidak+ditemukan");
    exit();
}

$kelas = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// **PERBAIKAN: Format tanggal setelah data kelas diambil**
$created_at = date('d F Y', strtotime($kelas['created_at']));
$updated_at = date('d F Y', strtotime($kelas['updated_at']));

// **PERBAIKAN: Format iuran dengan pengecekan**
$iuran_formatted = "Rp " . number_format($kelas['iuran_per_bulan'], 0, ',', '.');

// **PERBAIKAN: Ambil jumlah peserta**
$sql_peserta = "SELECT COUNT(*) as total FROM peserta WHERE kelas_id = ? AND status = 'aktif'";
$stmt = mysqli_prepare($conn, $sql_peserta);
mysqli_stmt_bind_param($stmt, "i", $kelas_id);
mysqli_stmt_execute($stmt);
$result_count = mysqli_stmt_get_result($stmt);
$count_data = mysqli_fetch_assoc($result_count);
$jumlah_peserta = $count_data['total'];
mysqli_stmt_close($stmt);

// **PERBAIKAN: Tentukan badge berdasarkan kode kelas**
$badge_class = 'kelas-badge-1';
$kode_kelas = strtolower($kelas['kode_kelas']);
if (strpos($kode_kelas, '2') !== false) $badge_class = 'kelas-badge-2';
if (strpos($kode_kelas, '3') !== false) $badge_class = 'kelas-badge-3';

// **PERBAIKAN: Olah fasilitas untuk menangani pemisah koma dan baris baru**
$fasilitas_text = trim($kelas['fasilitas']);
if (!empty($fasilitas_text)) {
    // Pisahkan dengan koma, titik koma, atau baris baru
    $fasilitas_array = preg_split('/[,\n;]+/', $fasilitas_text);
    $fasilitas_array = array_map('trim', $fasilitas_array);
    $fasilitas_array = array_filter($fasilitas_array); // Hapus elemen kosong
} else {
    $fasilitas_array = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Detail Kelas - BPJS Kesehatan</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --bpjs-primary: #0077C8;
            --bpjs-secondary: #00A9E0;
            --bpjs-light: #E6F4FF;
            --bpjs-dark: #0056A3;
            --bpjs-success: #28a745;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 20px;
        }
        
        .navbar-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            margin-bottom: 30px;
            padding: 15px 20px;
        }
        
        .logo-bpjs {
            color: white;
            font-weight: 700;
            font-size: 1.4rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .logo-bpjs i {
            margin-right: 10px;
            font-size: 1.6rem;
        }
        
        .card-bpjs {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
            padding: 20px;
            border-bottom: none;
        }
        
        .kelas-badge {
            font-size: 0.85rem;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
            display: inline-block;
        }
        
        .kelas-badge-1 {
            background-color: var(--bpjs-primary);
        }
        
        .kelas-badge-2 {
            background-color: var(--bpjs-secondary);
        }
        
        .kelas-badge-3 {
            background-color: var(--bpjs-dark);
        }
        
        .fasilitas-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .fasilitas-item:last-child {
            border-bottom: none;
        }
        
        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        
        .text-bpjs-primary {
            color: var(--bpjs-primary) !important;
        }
        
        .text-bpjs-secondary {
            color: var(--bpjs-secondary) !important;
        }
        
        .bg-bpjs-light {
            background-color: var(--bpjs-light) !important;
        }
        
        .btn-bpjs-primary {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-bpjs-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 119, 200, 0.3);
            color: white;
        }
        
        .btn-bpjs-secondary {
            background-color: var(--bpjs-secondary);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .page-title {
            color: var(--bpjs-primary);
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #6c757d;
            margin-bottom: 25px;
        }
        
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--bpjs-primary);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .info-box:hover {
            transform: translateY(-5px);
        }
        
        .iuran-box {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-left: 4px solid var(--bpjs-success);
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        
        .footer-bpjs {
            background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 40px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .profile-img-small {
            width: 40px;
            height: 40px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }
        
        .back-link {
            color: var(--bpjs-primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            color: var(--bpjs-dark);
        }
        
        .deskripsi-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--bpjs-secondary);
            line-height: 1.6;
        }
        
        .fasilitas-icon {
            color: var(--bpjs-success);
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation Header -->
        <div class="navbar-bpjs d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="logo-bpjs">
                <i class="fas fa-heartbeat"></i> BPJS Kesehatan - Detail Kelas
            </a>
            <div class="user-info">
                <img src="../assets/images/profile/male/image_1.png" alt="profile" class="profile-img-small rounded-circle">
                <div>
                    <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                    <small class="opacity-75">BPJS Member</small>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-light ms-3">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>

        <!-- Back Button -->
        <a href="kelas.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Data Kelas
        </a>

        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="page-title">
                <i class="fas fa-info-circle text-bpjs-primary me-2"></i>
                Detail Kelas BPJS Kesehatan
            </h2>
            <p class="page-subtitle">Informasi lengkap tentang kelas <?php echo htmlspecialchars($kelas['nama_kelas']); ?></p>
        </div>

        <!-- Detail Card -->
        <div class="card card-bpjs">
            <div class="card-header card-header-bpjs">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informasi Kelas
                </h5>
            </div>
            <div class="card-body p-4">
                <!-- Header Info -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h3 class="text-bpjs-primary mb-2"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h3>
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <span class="kelas-badge <?php echo $badge_class; ?>">
                                <i class="fas fa-tag me-1"></i>
                                <?php echo htmlspecialchars($kelas['kode_kelas']); ?>
                            </span>
                            <span class="text-muted">
                                <i class="far fa-calendar-alt me-1"></i>
                                Dibuat: <?php echo $created_at; ?>
                            </span>
                            <span class="text-muted">
                                <i class="fas fa-sync-alt me-1"></i>
                                Diperbarui: <?php echo $updated_at; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <!-- **PERBAIKAN: Box iuran yang lebih menarik** -->
                        <div class="iuran-box">
                            <h1 class="text-success mb-2"><?php echo $iuran_formatted; ?></h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Iuran per bulan
                            </p>
                            <small class="text-muted">Per peserta</small>
                        </div>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="mb-5">
                    <h5 class="text-bpjs-secondary mb-3">
                        <i class="fas fa-align-left me-2"></i>Deskripsi Kelas
                    </h5>
                    <div class="deskripsi-box">
                        <?php 
                        if (!empty(trim($kelas['deskripsi']))): 
                            echo nl2br(htmlspecialchars($kelas['deskripsi']));
                        else: 
                        ?>
                            <div class="text-muted fst-italic">
                                <i class="fas fa-info-circle me-2"></i>
                                Tidak ada deskripsi tersedia untuk kelas ini.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistik -->
                <div class="row mb-5">
                    <div class="col-md-4 mb-3">
                        <div class="info-box">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper bg-info text-white p-3 rounded-circle me-3">
                                    <i class="fas fa-users fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Jumlah Peserta</h6>
                                    <h3 class="text-info mb-0"><?php echo $jumlah_peserta; ?></h3>
                                    <small class="text-muted">Peserta aktif</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="info-box">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper bg-warning text-white p-3 rounded-circle me-3">
                                    <i class="fas fa-history fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Terakhir Diperbarui</h6>
                                    <p class="mb-1 fw-medium"><?php echo date('d M Y', strtotime($kelas['updated_at'])); ?></p>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($kelas['updated_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="info-box">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper <?php echo $jumlah_peserta > 0 ? 'bg-success' : 'bg-secondary'; ?> text-white p-3 rounded-circle me-3">
                                    <i class="fas fa-<?php echo $jumlah_peserta > 0 ? 'check-circle' : 'hourglass-half'; ?> fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Status Kelas</h6>
                                    <h5 class="mb-0 <?php echo $jumlah_peserta > 0 ? 'text-success' : 'text-secondary'; ?>">
                                        <?php echo $jumlah_peserta > 0 ? 'Aktif' : 'Tersedia'; ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?php echo $jumlah_peserta > 0 ? 'Memiliki peserta' : 'Belum ada peserta'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fasilitas -->
                <div class="mb-5">
                    <h5 class="text-bpjs-secondary mb-3">
                        <i class="fas fa-check-circle me-2"></i>
                        Fasilitas Kelas
                    </h5>
                    
                    <!-- **PERBAIKAN: Tampilkan fasilitas dengan format yang benar** -->
                    <?php if (count($fasilitas_array) > 0): ?>
                        <div class="row">
                            <?php foreach ($fasilitas_array as $index => $fasilitas): 
                                if (!empty(trim($fasilitas))): ?>
                            <div class="col-md-6 mb-3">
                                <div class="fasilitas-item">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-check fasilitas-icon mt-1"></i>
                                        <div>
                                            <p class="mb-0 fw-medium"><?php echo htmlspecialchars(trim($fasilitas)); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p class="mb-0">Tidak ada fasilitas yang tercatat untuk kelas ini</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between pt-4 border-top">
                    <div>
                        <a href="kelas.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i> Kembali
                        </a>
                        <a href="peserta.php?kelas=<?php echo $kelas_id; ?>" class="btn btn-outline-info">
                            <i class="fas fa-user-friends me-2"></i> Lihat Peserta
                        </a>
                    </div>
                    <div>
                        <a href="edit_kelas.php?id=<?php echo $kelas_id; ?>" class="btn btn-bpjs-primary">
                            <i class="fas fa-edit me-2"></i> Edit Kelas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer-bpjs">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">BPJS Kesehatan System &copy; <?php echo date('Y'); ?></p>
                    <small>Detail Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?> (<?php echo htmlspecialchars($kelas['kode_kelas']); ?>)</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>Terakhir dilihat: <?php echo date('d M Y H:i:s'); ?></small>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add animation to info boxes
        const infoBoxes = document.querySelectorAll('.info-box, .iuran-box');
        infoBoxes.forEach(box => {
            box.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            box.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
    </script>
</body>
</html>