<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Inisialisasi variabel
$user = null;
$faskes = null;
$error = null;

try {
    // Ambil data user termasuk profile_pic
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Cek foto profil
    $profile_pic = $user['profile_pic'] ?? '';
    $profile_path = 'uploads/profile_pics/' . $profile_pic;
    $has_custom_profile = (!empty($profile_pic) && file_exists($profile_path));
    $default_avatar = '../assets/images/faces/face1.jpg';
    
    // Cek apakah koneksi database berhasil
    if (!$conn) {
        throw new Exception("Koneksi database gagal!");
    }

    // Ambil ID faskes dari URL
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['error'] = "ID faskes tidak valid!";
        header("Location: faskes.php");
        exit();
    }

    $id = intval($_GET['id']);

    // Ambil data faskes
    $sql = "SELECT * FROM faskes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        throw new Exception("Persiapan query gagal: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Eksekusi query gagal: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Gagal mendapatkan hasil query: " . mysqli_error($conn));
    }
    
    $faskes = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$faskes) {
        $_SESSION['error'] = "Data faskes tidak ditemukan!";
        header("Location: faskes.php");
        exit();
    }

} catch (Exception $e) {
    $error = $e->getMessage();
    $_SESSION['error'] = "Terjadi kesalahan: " . $error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Faskes - BPJS Kesehatan</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Material Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@6.5.95/css/materialdesignicons.min.css">
    
    <style>
        :root {
            --primary-blue: #0066cc;
            --dark-blue: #003366;
            --light-blue: #e3f2fd;
            --bpjs-green: #28a745;
            --bpjs-teal: #17a2b8;
            --bpjs-yellow: #ffc107;
            --bpjs-red: #dc3545;
            --bpjs-white: #ffffff;
            --bpjs-light: #f8f9fa;
            --bpjs-gray: #6c757d;
            --bpjs-dark: #343a40;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e8f4fd 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Header Styling */
        .header-bpjs {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-blue) 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-btn {
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            transition: background 0.3s;
        }
        
        .notification-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--bpjs-red);
            color: white;
            font-size: 0.7rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            transform: translateX(-3px);
        }
        
        /* Main Content Area - FULL WIDTH */
        .main-content {
            padding-top: 100px; /* Space for fixed header */
            min-height: 100vh;
            width: 100%;
            margin-left: 0;
        }
        
        /* Content Styling */
        .content-wrapper {
            padding: 30px;
        }
        
        .card-bpjs {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card-bpjs:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        
        .card-header-bpjs {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #0099ff 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
            border: none;
        }
        
        .info-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid var(--primary-blue);
            transition: transform 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .info-box:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,102,204,0.1);
        }
        
        .label-info {
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .value-info {
            color: var(--bpjs-dark);
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .badge-success {
            background: linear-gradient(135deg, var(--bpjs-green) 0%, #20c997 100%);
            color: white;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, var(--bpjs-red) 0%, #c82333 100%);
            color: white;
        }
        
        .badge-info {
            background: linear-gradient(135deg, var(--bpjs-teal) 0%, #138496 100%);
            color: white;
        }
        
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #0099ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.2);
        }
        
        .btn-bpjs-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-bpjs-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,102,204,0.3);
            color: white;
        }
        
        .section-title {
            color: var(--primary-blue);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 102, 204, 0.1);
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--primary-blue);
        }
        
        /* Footer */
        footer.footer {
            background: white;
            border-top: 2px solid rgba(0, 102, 204, 0.1);
            padding: 25px 30px;
            margin-top: 40px;
            border-radius: 15px 15px 0 0;
        }
        
        footer .text-gray {
            color: var(--bpjs-gray) !important;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 20px;
            }
            
            .header-bpjs {
                padding: 15px 20px;
            }
            
            .card-header-bpjs {
                padding: 20px;
            }
            
            .main-content {
                padding-top: 90px;
            }
            
            .header-actions {
                gap: 10px;
            }
            
            .notification-btn {
                width: 36px;
                height: 36px;
            }
        }
        
        @media (max-width: 576px) {
            .content-wrapper {
                padding: 15px;
            }
            
            .info-box {
                padding: 15px;
            }
            
            .card-header-bpjs h4 {
                font-size: 1.3rem;
            }
        }
        
        /* User Profile Dropdown */
        .user-dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 10px;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            color: var(--bpjs-dark);
            transition: all 0.3s;
        }
        
        .dropdown-item:hover {
            background: var(--light-blue);
            color: var(--primary-blue);
        }
        
        .dropdown-item i {
            width: 20px;
            margin-right: 10px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .d-md-flex {
                flex-direction: column;
            }
            
            .ml-md-auto {
                margin-left: 0 !important;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body class="header-fixed">
    
    

            <!-- Main Card -->
            <div class="card-bpjs">
                <div class="card-header-bpjs">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1"><i class="fas fa-hospital mr-2"></i>Detail Fasilitas BPJS Kesehatan</h4>
                            <p class="mb-0">
                                <?php if($faskes): ?>
                                    Informasi lengkap <?php echo htmlspecialchars($faskes['nama_faskes'] ?? 'Faskes'); ?>
                                <?php else: ?>
                                    Memuat informasi faskes...
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <?php if($faskes && isset($faskes[''])): ?>
                           
                                <?php echo strtoupper($faskes[''] ?? 'TIDAK DIKETAHUI'); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if(!$faskes): ?>
                        <!-- Loading State -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p>Memuat data faskes...</p>
                            <a href="faskes.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Daftar Faskes
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Data Faskes -->
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-8">
                                <!-- Informasi Umum -->
                                <h5 class="section-title">Informasi Umum</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="label-info">Kode Faskes</div>
                                            <div class="value-info">
                                                <strong class="text-primary"><?php echo htmlspecialchars($faskes['kode_faskes'] ?? '-'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="label-info">Nama Faskes</div>
                                            <div class="value-info"><?php echo htmlspecialchars($faskes['nama_faskes'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="label-info">Jenis Faskes</div>
                                            <div class="value-info">
                                                <span class="badge badge-info"><?php echo htmlspecialchars($faskes['jenis_faskes'] ?? '-'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="label-info">Nama Direktur</div>
                                            <div class="value-info"><?php echo htmlspecialchars($faskes['direktur'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Informasi Kontak -->
                                <h5 class="section-title mt-4">Informasi Kontak</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="label-info">Telepon</div>
                                            <div class="value-info">
                                                <i class="fas fa-phone mr-2 text-primary"></i>
                                                <?php echo htmlspecialchars($faskes['no_telepon'] ?? '-'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="label-info">Email</div>
                                            <div class="value-info">
                                                <i class="fas fa-envelope mr-2 text-primary"></i>
                                                <?php echo htmlspecialchars($faskes['email'] ?? '-'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="info-box">
                                            <div class="label-info">Website</div>
                                            <div class="value-info">
                                                <i class="fas fa-globe mr-2 text-primary"></i>
                                                <?php if(!empty($faskes['website'])): ?>
                                                    <a href="<?php echo htmlspecialchars($faskes['website']); ?>" target="_blank" class="text-primary">
                                                        <?php echo htmlspecialchars($faskes['website']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Informasi Lokasi -->
                                <h5 class="section-title mt-4">Informasi Lokasi</h5>
                                
                                <div class="info-box">
                                    <div class="label-info">Alamat Lengkap</div>
                                    <div class="value-info">
                                        <?php echo nl2br(htmlspecialchars($faskes['alamat'] ?? '-')); ?>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="info-box">
                                            <div class="label-info">Kota</div>
                                            <div class="value-info"><?php echo htmlspecialchars($faskes['kota'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-box">
                                            <div class="label-info">Provinsi</div>
                                            <div class="value-info"><?php echo htmlspecialchars($faskes['provinsi'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-box">
                                            <div class="label-info">Kode Pos</div>
                                            <div class="value-info"><?php echo htmlspecialchars($faskes['kode_pos'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-4">
                                <!-- Icon & Type -->
                                <div class="card-bpjs mb-4">
                                    <div class="card-body text-center">
                                        <div class="icon-circle">
                                            <i class="fas fa-hospital"></i>
                                        </div>
                                        <h5 class="text-primary mb-1"><?php echo htmlspecialchars($faskes['jenis_faskes'] ?? '-'); ?></h5>
                                        <p class="text-muted small">Tipe Fasilitas Kesehatan</p>
                                    </div>
                                </div>

                                <!-- System Info -->
                                <div class="card-bpjs mb-4">
                                    <div class="card-body">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-history mr-2"></i>Informasi Sistem
                                        </h6>
                                        <div class="mb-3">
                                            <div class="label-info">Dibuat Pada</div>
                                            <div class="value-info">
                                                <?php 
                                                    if(isset($faskes['created_at'])) {
                                                        echo date('d F Y, H:i', strtotime($faskes['created_at']));
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="label-info">Terakhir Diperbarui</div>
                                            <div class="value-info">
                                                <?php 
                                                    if(isset($faskes['updated_at'])) {
                                                        echo date('d F Y, H:i', strtotime($faskes['updated_at']));
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Location -->
                                <div class="card-bpjs">
                                    <div class="card-body">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-map-marker-alt mr-2"></i>Lokasi
                                        </h6>
                                        <div style="background: var(--light-blue); border-radius: 12px; padding: 20px; text-align: center;">
                                            <i class="fas fa-map-marked-alt fa-3x mb-3 text-primary"></i>
                                            <p class="mb-1 font-weight-bold"><?php echo htmlspecialchars($faskes['kota'] ?? '-'); ?></p>
                                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($faskes['provinsi'] ?? '-'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if($faskes): ?>
                <div class="card-footer bg-white border-top-0 pt-4 pb-4">
                    <div class="d-flex justify-content-between">
                        <a href="faskes.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left mr-2"></i>Kembali ke Daftar
                        </a>
                        <div>
                            <?php if(isset($faskes['id'])): ?>
                            <a href="faskes_edit.php?id=<?php echo $faskes['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit mr-2"></i>Edit Data
                            </a>
                            <?php endif; ?>
                            <a href="faskes.php" class="btn btn-bpjs-primary ml-2">
                                <i class="fas fa-list mr-2"></i>Semua Faskes
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="footer">
            <div class="row">
                <div class="col-sm-6 text-center text-sm-right order-sm-1">
                    <ul class="text-gray list-inline">
                        <li class="list-inline-item"><a href="#">Syarat Penggunaan</a></li>
                        <li class="list-inline-item"><a href="#">Kebijakan Privasi</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 text-center text-sm-left mt-3 mt-sm-0">
                    <small class="text-muted d-block">Sistem BPJS Kesehatan &copy; <?php echo date('Y'); ?></small>
                    <small class="text-gray mt-2">Area Member</small>
                </div>
            </div>
        </footer>
    </div>
</div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>