<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$no_transaksi = $_GET['trx'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($no_transaksi)) {
    header("Location: pembayaran.php");
    exit();
}

// Ambil data transaksi
$sql = "SELECT pi.*, p.nama, p.no_kartu, k.nama_kelas 
        FROM pembayaran_iuran pi
        LEFT JOIN peserta p ON pi.peserta_id = p.id
        LEFT JOIN kelas k ON p.kelas_id = k.id
        WHERE pi.no_transaksi = ? AND pi.user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $no_transaksi, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$transaksi = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$transaksi) {
    header("Location: pembayaran.php");
    exit();
}

// Proses upload bukti bayar jika ada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['bukti_bayar'])) {
    $target_dir = "uploads/bukti_bayar/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES["bukti_bayar"]["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Cek apakah file adalah gambar
    $check = getimagesize($_FILES["bukti_bayar"]["tmp_name"]);
    if ($check !== false) {
        // Upload file
        if (move_uploaded_file($_FILES["bukti_bayar"]["tmp_name"], $target_file)) {
            // Update database
            $sql_update = "UPDATE pembayaran_iuran 
                          SET bukti_bayar = ?, status = 'verified' 
                          WHERE no_transaksi = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ss", $file_name, $no_transaksi);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            $success = "Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.";
        } else {
            $error = "Maaf, terjadi kesalahan saat mengupload file.";
        }
    } else {
        $error = "File yang diupload bukan gambar.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Konfirmasi Pembayaran - BPJS Kesehatan</title>
    
    <!-- Template CSS -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    
    <style>
    .confirmation-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .confirmation-header {
        text-align: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #28a745;
    }
    .status-badge {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        border-radius: 50px;
        font-weight: bold;
        margin: 1rem 0;
    }
    .status-pending {
        background: #fff3cd;
        color: #856404;
    }
    .status-verified {
        background: #d4edda;
        color: #155724;
    }
    .status-success {
        background: #d1ecf1;
        color: #0c5460;
    }
    .payment-details {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
        margin: 1.5rem 0;
    }
    .invoice-box {
        background: #e3f2fd;
        border: 2px dashed #0073e6;
        padding: 1.5rem;
        border-radius: 10px;
        margin: 1.5rem 0;
        text-align: center;
    }
    .btn-upload {
        background: #0073e6;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-upload:hover {
        background: #0056b3;
    }
    .alert {
        padding: 1rem;
        border-radius: 5px;
        margin: 1rem 0;
    }
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    </style>
</head>
<body>
    <?php include 'partials/header.php'; ?>
    
    <div class="page-body">
        <?php include 'partials/sidebar.php'; ?>
        
        <div class="page-content-wrapper">
            <div class="confirmation-container">
                <div class="confirmation-header">
                    <div style="font-size: 4rem; color: #28a745;">
                        <i class="mdi mdi-check-circle"></i>
                    </div>
                    <h2>Pembayaran Berhasil Diproses!</h2>
                    <p>No. Transaksi: <strong><?php echo $transaksi['no_transaksi']; ?></strong></p>
                    
                    <div class="status-badge 
                        <?php 
                        if ($transaksi['status'] == 'verified') echo 'status-verified';
                        elseif ($transaksi['status'] == 'success') echo 'status-success';
                        else echo 'status-pending';
                        ?>">
                        <i class="mdi mdi-information-outline"></i>
                        Status: <?php echo strtoupper($transaksi['status']); ?>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="mdi mdi-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="mdi mdi-alert-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Detail Transaksi -->
                <div class="payment-details">
                    <h4><i class="mdi mdi-receipt"></i> Detail Transaksi</h4>
                    <div class="row" style="margin-top: 1rem;">
                        <div class="col-md-6">
                            <p><strong>No. Transaksi:</strong><br>
                            <?php echo $transaksi['no_transaksi']; ?></p>
                            <p><strong>Tanggal Transaksi:</strong><br>
                            <?php echo date('d M Y H:i', strtotime($transaksi['tanggal_bayar'])); ?></p>
                            <p><strong>Nama Peserta:</strong><br>
                            <?php echo htmlspecialchars($transaksi['nama'] ?? '-'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>No. Kartu:</strong><br>
                            <?php echo htmlspecialchars($transaksi['no_kartu'] ?? '-'); ?></p>
                            <p><strong>Kelas:</strong><br>
                            <?php echo htmlspecialchars($transaksi['nama_kelas'] ?? '-'); ?></p>
                            <p><strong>Metode Pembayaran:</strong><br>
                            <?php echo htmlspecialchars($transaksi['metode_pembayaran'] ?? '-'); ?></p>
                        </div>
                    </div>
                    
                    <div class="invoice-box">
                        <h4><i class="mdi mdi-cash-usd"></i> Rincian Pembayaran</h4>
                        <div style="font-size: 2.5rem; font-weight: bold; color: #28a745;">
                            Rp <?php echo number_format($transaksi['total_bayar'], 0, ',', '.'); ?>
                        </div>
                        <p style="margin-top: 0.5rem;">
                            Periode: <?php echo date('F Y', strtotime($transaksi['periode'] . '-01')); ?><br>
                            Jumlah Bulan: <?php echo $transaksi['jumlah_bulan']; ?> Bulan
                        </p>
                    </div>
                </div>
                
                <!-- Instruksi Pembayaran -->
                <?php if ($transaksi['status'] == 'pending' && empty($transaksi['bukti_bayar'])): ?>
                    <div class="payment-details">
                        <h4><i class="mdi mdi-information-outline"></i> Instruksi Pembayaran</h4>
                        
                        <div style="background: #fff3cd; padding: 1rem; border-radius: 5px; margin: 1rem 0;">
                            <h5>Transfer Bank BCA</h5>
                            <p><strong>No. Rekening:</strong> 123-456-7890<br>
                            <strong>Atas Nama:</strong> BPJS Kesehatan<br>
                            <strong>Cabang:</strong> Kantor Pusat Jakarta</p>
                            <p><em>Harap transfer tepat sesuai nominal: <strong>Rp <?php echo number_format($transaksi['total_bayar'], 0, ',', '.'); ?></strong></em></p>
                        </div>
                        
                        <!-- Form Upload Bukti Bayar -->
                        <form method="POST" action="" enctype="multipart/form-data">
                            <h5><i class="mdi mdi-cloud-upload"></i> Upload Bukti Pembayaran</h5>
                            <p>Setelah melakukan transfer, upload bukti pembayaran untuk verifikasi:</p>
                            
                            <div style="margin: 1rem 0;">
                                <input type="file" name="bukti_bayar" id="bukti_bayar" accept="image/*" required 
                                       style="padding: 1rem; border: 2px dashed #0073e6; border-radius: 5px; width: 100%;">
                                <small>Format: JPG, PNG, JPEG (Max: 2MB)</small>
                            </div>
                            
                            <button type="submit" class="btn-upload">
                                <i class="mdi mdi-upload"></i> Upload Bukti Pembayaran
                            </button>
                        </form>
                    </div>
                <?php elseif ($transaksi['status'] == 'verified'): ?>
                    <div class="alert alert-success">
                        <h5><i class="mdi mdi-check-circle"></i> Bukti Pembayaran Terverifikasi</h5>
                        <p>Pembayaran Anda telah diverifikasi oleh admin. Berikut bukti yang telah diupload:</p>
                        <?php if ($transaksi['bukti_bayar']): ?>
                            <div style="text-align: center; margin-top: 1rem;">
                                <img src="uploads/bukti_bayar/<?php echo $transaksi['bukti_bayar']; ?>" 
                                     alt="Bukti Bayar" 
                                     style="max-width: 300px; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Informasi Tambahan -->
                <div class="payment-details">
                    <h4><i class="mdi mdi-information"></i> Informasi Penting</h4>
                    <ul style="margin-left: 1.5rem;">
                        <li>Simpan nomor transaksi ini untuk keperluan referensi</li>
                        <li>Pembayaran akan diproses dalam 1-2 jam kerja setelah verifikasi</li>
                        <li>Untuk pertanyaan, hubungi Call Center 165</li>
                        <li>Notifikasi akan dikirim ke email setelah pembayaran berhasil</li>
                    </ul>
                </div>
                
                <!-- Tombol Aksi -->
                <div style="display: flex; gap: 10px; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="dashboard.php" class="btn" style="flex: 1; background: #6c757d; color: white; padding: 1rem; text-align: center; border-radius: 5px; text-decoration: none;">
                        <i class="mdi mdi-home"></i> Dashboard
                    </a>
                    <a href="riwayat_pembayaran.php" class="btn" style="flex: 1; background: #0073e6; color: white; padding: 1rem; text-align: center; border-radius: 5px; text-decoration: none;">
                        <i class="mdi mdi-history"></i> Riwayat Pembayaran
                    </a>
                    <a href="cetak_invoice.php?trx=<?php echo $no_transaksi; ?>" class="btn" style="flex: 1; background: #28a745; color: white; padding: 1rem; text-align: center; border-radius: 5px; text-decoration: none;">
                        <i class="mdi mdi-printer"></i> Cetak Invoice
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Preview image sebelum upload
    document.getElementById('bukti_bayar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Tampilkan preview
                const preview = document.createElement('img');
                preview.src = e.target.result;
                preview.style.maxWidth = '200px';
                preview.style.marginTop = '10px';
                preview.style.borderRadius = '5px';
                
                // Hapus preview sebelumnya jika ada
                const oldPreview = document.querySelector('.image-preview');
                if (oldPreview) oldPreview.remove();
                
                preview.className = 'image-preview';
                e.target.parentNode.appendChild(preview);
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Auto download invoice setelah 3 detik jika baru bayar
    setTimeout(function() {
        <?php if ($transaksi['status'] == 'pending'): ?>
            window.print();
        <?php endif; ?>
    }, 3000);
    </script>
</body>
</html>