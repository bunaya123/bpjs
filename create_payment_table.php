<?php
// create_payment_table.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';

$sql = "CREATE TABLE IF NOT EXISTS pembayaran_bpjs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    peserta_id INT NOT NULL,
    tanggal_pembayaran DATE NOT NULL,
    jumlah_dibayarkan DECIMAL(12,2) NOT NULL,
    metode_pembayaran VARCHAR(50) NOT NULL,
    denda DECIMAL(12,2) DEFAULT 0,
    status_pembayaran ENUM('success', 'pending', 'failed') DEFAULT 'pending',
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql)) {
    $message .= "✅ Tabel pembayaran_bpjs berhasil dibuat!<br>";
    
    // Buat index untuk performa
    mysqli_query($conn, "CREATE INDEX IF NOT EXISTS idx_peserta_id ON pembayaran_bpjs(peserta_id)");
    mysqli_query($conn, "CREATE INDEX IF NOT EXISTS idx_tanggal_pembayaran ON pembayaran_bpjs(tanggal_pembayaran)");
    
    $message .= "✅ Index berhasil dibuat!<br>";
    $message .= "✅ Sistem pembayaran siap digunakan.";
} else {
    $message = "❌ Error membuat tabel: " . mysqli_error($conn);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat Tabel Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-database me-2"></i> Setup Tabel Pembayaran</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <?php echo nl2br($message); ?>
                        </div>
                        <div class="text-center mt-4">
                            <a href="peserta_bpjs.php" class="btn btn-primary me-2">
                                <i class="fas fa-list me-1"></i> Ke Daftar Peserta
                            </a>
                            <?php if (strpos($message, '✅') !== false): ?>
                            <a href="detail_peserta.php?id=<?php echo $_GET['peserta_id'] ?? ''; ?>" class="btn btn-success">
                                <i class="fas fa-arrow-left me-1"></i> Kembali ke Detail
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>