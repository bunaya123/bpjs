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

// Ambil data tindakan
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Detail Tindakan - BPJS Kesehatan</title>
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
            min-height: 100vh;
        }
        
        .container-fluid {
            padding: 0;
        }
        
        /* Full Width Content */
        .full-width-content {
            width: 100%;
            padding: 20px;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Section */
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        
        .header-section h2 {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        
        .header-section p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        
        /* Cards */
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s;
            height: 100%;
        }
        
        .card-custom:hover {
            transform: translateY(-3px);
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
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
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
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-secondary-custom:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-warning-custom {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            border: none;
            color: #333;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-warning-custom:hover {
            background: linear-gradient(135deg, #f5ce61 0%, #fc9c7b 100%);
            color: #333;
            transform: translateY(-2px);
        }
        
        .btn-danger-custom {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-danger-custom:hover {
            background: linear-gradient(135deg, #ff5252 0%, #e74c3c 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Alerts */
        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        /* Detail Items */
        .detail-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .detail-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        /* Price Cards */
        .price-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        
        .price-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        
        .price-value {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0;
        }
        
        .price-bpjs {
            color: #27ae60;
        }
        
        .price-non-bpjs {
            color: #e67e22;
        }
        
        /* Badges */
        .badge-custom {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .badge-aktif {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }
        
        .badge-nonaktif {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .badge-kategori {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
            border: none;
        }
        
        .action-btn i {
            margin-right: 8px;
        }
        
        /* Info Box */
        .info-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
        }
        
        /* Footer */
        .footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
            border-top: 1px solid #eee;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-section {
                padding: 20px;
            }
            
            .card-body-custom {
                padding: 20px;
            }
            
            .btn-primary-custom,
            .btn-secondary-custom,
            .btn-warning-custom,
            .btn-danger-custom {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .price-value {
                font-size: 1.5rem;
            }
            
            .main-container {
                padding: 0 15px;
            }
        }
        
        /* Additional Info Section */
        .additional-info-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        /* Text Content */
        .text-content {
            line-height: 1.6;
            color: #555;
        }
        
        /* Modal Custom */
        .modal-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        /* Scrollbar */
        .card-body-custom::-webkit-scrollbar {
            width: 6px;
        }
        
        .card-body-custom::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .card-body-custom::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .card-body-custom::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Equal Height Columns */
        .row-equal-height {
            display: flex;
            flex-wrap: wrap;
        }
        
        .row-equal-height > [class*='col-'] {
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="full-width-content">
            <div class="main-container">
                
                <!-- Header Section -->
                <div class="header-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-clipboard-list"></i> Detail Data Tindakan</h2>
                            <p>Informasi lengkap tindakan medis BPJS Kesehatan</p>
                        </div>
                        <div class="col-md-4 text-md-right text-center mt-3 mt-md-0">
                            <a href="tindakan.php" class="btn btn-secondary-custom mr-2">
                                <i class="fas fa-arrow-left mr-2"></i> Kembali
                            </a>
                            <a href="tindakan_edit.php?id=<?php echo $tindakan['id']; ?>" class="btn btn-primary-custom">
                                <i class="fas fa-edit mr-2"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-custom">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="fas fa-exclamation-circle me-3"></i>
                            <div><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-custom">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="fas fa-check-circle me-3"></i>
                            <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Main Content -->
                <div class="row row-equal-height">
                    <!-- Kolom Kiri - Informasi Utama -->
                    <div class="col-lg-8 mb-4">
                        <div class="card-custom">
                            <div class="card-header-custom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i> Informasi Utama Tindakan</h5>
                                    </div>
                                    <div>
                                        <span class="badge-custom <?php echo $tindakan['status'] == 'aktif' ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                            <?php echo $tindakan['status'] == 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body-custom" style="overflow-y: auto; max-height: 600px;">
                                <!-- Status dan Kode -->
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <div class="detail-label">Kode Tindakan</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($tindakan['kode_tindakan']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Nama Tindakan</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($tindakan['nama_tindakan']); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Kategori dan Jenis -->
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <div class="detail-label">Kategori</div>
                                        <div>
                                            <span class="badge-kategori">
                                                <?php echo !empty($tindakan['kategori']) ? htmlspecialchars($tindakan['kategori']) : '-'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Jenis Tindakan</div>
                                        <div class="detail-value"><?php echo !empty($tindakan['jenis_tindakan']) ? htmlspecialchars($tindakan['jenis_tindakan']) : '-'; ?></div>
                                    </div>
                                </div>
                                
                                <!-- Unit dan Waktu -->
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <div class="detail-label">Unit/Lokasi</div>
                                        <div class="detail-value"><?php echo !empty($tindakan['unit']) ? htmlspecialchars($tindakan['unit']) : '-'; ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Waktu Estimasi</div>
                                        <div class="detail-value">
                                            <?php 
                                            if (!empty($tindakan['waktu_estimasi'])) {
                                                echo htmlspecialchars($tindakan['waktu_estimasi']) . ' menit';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Timestamps -->
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <div class="detail-label">
                                            <i class="far fa-calendar-plus mr-1"></i> Dibuat
                                        </div>
                                        <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($tindakan['created_at'])); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">
                                            <i class="far fa-calendar-check mr-1"></i> Diupdate
                                        </div>
                                        <div class="detail-value">
                                            <?php 
                                            if (!empty($tindakan['updated_at']) && $tindakan['updated_at'] != $tindakan['created_at']) {
                                                echo date('d/m/Y H:i', strtotime($tindakan['updated_at']));
                                            } else {
                                                echo '<span class="text-muted">Belum pernah</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Deskripsi -->
                                <div class="detail-item">
                                    <div class="detail-label">Deskripsi Tindakan</div>
                                    <div class="text-content mt-2">
                                        <?php 
                                        if (!empty($tindakan['deskripsi'])) {
                                            echo nl2br(htmlspecialchars($tindakan['deskripsi']));
                                        } else {
                                            echo '<span class="text-muted">Tidak ada deskripsi</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <!-- Persyaratan -->
                                <?php if (!empty($tindakan['persyaratan'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Persyaratan</div>
                                    <div class="text-content mt-2">
                                        <?php echo nl2br(htmlspecialchars($tindakan['persyaratan'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kolom Kanan - Tarif dan Aksi -->
                    <div class="col-lg-4">
                        <!-- Tarif Card -->
                        <div class="card-custom mb-4">
                            <div class="card-header-custom">
                                <h5 class="mb-0"><i class="fas fa-money-bill-wave mr-2"></i> Tarif Tindakan</h5>
                            </div>
                            <div class="card-body-custom">
                                <!-- Tarif BPJS -->
                                <div class="price-card">
                                    <div class="price-label">
                                        <i class="fas fa-user-injured mr-2"></i> Tarif BPJS
                                    </div>
                                    <div class="price-value price-bpjs">
                                        Rp <?php echo number_format($tindakan['tarif_bpjs'], 0, ',', '.'); ?>
                                    </div>
                                    <small class="text-muted">Untuk peserta BPJS Kesehatan</small>
                                </div>
                                
                                <!-- Tarif Non-BPJS -->
                                <div class="price-card">
                                    <div class="price-label">
                                        <i class="fas fa-user mr-2"></i> Tarif Non-BPJS
                                    </div>
                                    <div class="price-value price-non-bpjs">
                                        Rp <?php echo number_format($tindakan['tarif_non_bpjs'], 0, ',', '.'); ?>
                                    </div>
                                    <small class="text-muted">Untuk pasien umum/non-BPJS</small>
                                </div>
                                
                                <!-- Catatan -->
                                <?php if (!empty($tindakan['catatan'])): ?>
                                <div class="info-box mt-3">
                                    <div class="detail-label">Catatan</div>
                                    <div class="text-content mt-2">
                                        <?php echo nl2br(htmlspecialchars($tindakan['catatan'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Aksi Card -->
                       
                                
                                <!-- Info Penting -->
                               
                                           
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="footer">
                    <p>BPJS Kesehatan System &copy; <?php echo date('Y'); ?> - Detail Data Tindakan</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <h6 id="deleteMessage">Apakah Anda yakin ingin menghapus data ini?</h6>
                    <p class="text-muted mb-0">Data yang dihapus tidak dapat dikembalikan</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary-custom" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <a href="#" id="deleteLink" class="btn btn-danger-custom">
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
        $(document).ready(function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Smooth scroll for anchor links
            $('a[href^="#"]').on('click', function(event) {
                var target = $(this.getAttribute('href'));
                if(target.length) {
                    event.preventDefault();
                    $('html, body').stop().animate({
                        scrollTop: target.offset().top - 100
                    }, 1000);
                }
            });
            
            // Equalize card heights
            function equalizeCardHeights() {
                var leftCard = $('.col-lg-8 .card-custom');
                var rightCards = $('.col-lg-4 .card-custom');
                
                var maxHeight = leftCard.outerHeight();
                rightCards.css('min-height', maxHeight + 'px');
            }
            
            // Adjust on load and resize
            setTimeout(equalizeCardHeights, 500);
            $(window).resize(equalizeCardHeights);
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