<?php
session_start();
require_once '../config.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil bukti bayar yang perlu diverifikasi
$sql = "SELECT pb.*, p.nama, p.no_kartu, i.bulan_tahun 
        FROM pembayaran pb
        JOIN peserta p ON pb.peserta_id = p.id
        JOIN iuran i ON pb.iuran_id = i.id
        WHERE pb.status IN ('Pending', 'Diproses')
        AND pb.bukti_bayar IS NOT NULL
        ORDER BY pb.tanggal_bayar DESC";
$result = mysqli_query($conn, $sql);
$bukti_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verifikasi Bukti Bayar</title>
    <link rel="stylesheet" href="../assets/css/shared/style.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="mdi mdi-file-document-check"></i> Verifikasi Bukti Bayar
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bukti_list)): ?>
                            <div class="text-center py-5">
                                <i class="mdi mdi-check-all" style="font-size: 4rem; color: #28a745;"></i>
                                <h4 class="mt-3">Tidak ada bukti bayar yang perlu diverifikasi</h4>
                                <p class="text-muted">Semua pembayaran sudah diverifikasi.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($bukti_list as $bukti): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo $bukti['nama']; ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $bukti['no_kartu']; ?></small>
                                            </div>
                                            <span class="badge badge-warning">Perlu Verifikasi</span>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>No. Pembayaran:</strong> <?php echo $bukti['no_pembayaran']; ?></p>
                                            <p><strong>Bulan:</strong> <?php echo $bukti['bulan_tahun']; ?></p>
                                            <p><strong>Jumlah:</strong> Rp <?php echo number_format($bukti['jumlah_bayar'], 0, ',', '.'); ?></p>
                                            <p><strong>Metode:</strong> <?php echo $bukti['metode_bayar']; ?></p>
                                            <p><strong>Tanggal:</strong> <?php echo date('d/m/Y H:i', strtotime($bukti['tanggal_bayar'])); ?></p>
                                            
                                            <!-- Preview Bukti -->
                                            <div class="text-center mb-3">
                                                <?php 
                                                $file_ext = pathinfo($bukti['bukti_bayar'], PATHINFO_EXTENSION);
                                                if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])): 
                                                ?>
                                                    <img src="../uploads/bukti_bayar/<?php echo $bukti['bukti_bayar']; ?>" 
                                                         alt="Bukti Bayar" class="img-fluid rounded" 
                                                         style="max-height: 200px;">
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="mdi mdi-file-pdf"></i> File PDF
                                                        <br>
                                                        <a href="../uploads/bukti_bayar/<?php echo $bukti['bukti_bayar']; ?>" 
                                                           target="_blank" class="btn btn-sm btn-info mt-2">
                                                            Lihat File
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="text-center">
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="verifyPayment(<?php echo $bukti['id']; ?>, 'success')">
                                                    <i class="mdi mdi-check"></i> Terima
                                                </button>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="verifyPayment(<?php echo $bukti['id']; ?>, 'reject')">
                                                    <i class="mdi mdi-close"></i> Tolak
                                                </button>
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="viewDetails(<?php echo $bukti['id']; ?>)">
                                                    <i class="mdi mdi-eye"></i> Detail
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function verifyPayment(paymentId, action) {
            if (action === 'success') {
                if (confirm('Terima pembayaran ini?')) {
                    window.location.href = `verify_payment.php?id=${paymentId}&action=accept`;
                }
            } else {
                var reason = prompt('Alasan penolakan:');
                if (reason !== null) {
                    window.location.href = `verify_payment.php?id=${paymentId}&action=reject&reason=${encodeURIComponent(reason)}`;
                }
            }
        }
        
        function viewDetails(paymentId) {
            window.open(`payment_detail.php?id=${paymentId}`, '_blank');
        }
    </script>
</body>
</html>