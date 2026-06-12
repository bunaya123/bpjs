<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$peserta_id = isset($_GET['peserta_id']) ? intval($_GET['peserta_id']) : 0;

if ($peserta_id <= 0) {
    die("ID peserta tidak valid");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_pembayaran = $_POST['tanggal_pembayaran'];
    $jumlah_dibayarkan = $_POST['jumlah_dibayarkan'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $denda = $_POST['denda'] ?: 0;
    $status_pembayaran = $_POST['status_pembayaran'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    $sql = "INSERT INTO pembayaran_bpjs 
            (peserta_id, tanggal_pembayaran, jumlah_dibayarkan, metode_pembayaran, denda, status_pembayaran, keterangan) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isdssss", 
        $peserta_id, 
        $tanggal_pembayaran, 
        $jumlah_dibayarkan,
        $metode_pembayaran,
        $denda,
        $status_pembayaran,
        $keterangan
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Pembayaran berhasil ditambahkan";
        header("Location: detail_peserta.php?id=" . $peserta_id);
        exit();
    } else {
        $error = "Gagal menambahkan pembayaran: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

// Ambil data peserta untuk header
$sql_peserta = "SELECT nama, no_kartu FROM peserta WHERE id = ?";
$stmt_peserta = mysqli_prepare($conn, $sql_peserta);
mysqli_stmt_bind_param($stmt_peserta, "i", $peserta_id);
mysqli_stmt_execute($stmt_peserta);
$result_peserta = mysqli_stmt_get_result($stmt_peserta);
$peserta = mysqli_fetch_assoc($result_peserta);
mysqli_stmt_close($stmt_peserta);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Tambah Pembayaran - BPJS Kesehatan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Tambah Pembayaran</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Pembayaran untuk: <strong><?php echo htmlspecialchars($peserta['nama']); ?></strong> 
                        (No. Kartu: <?php echo htmlspecialchars($peserta['no_kartu']); ?>)
                    </div>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Pembayaran <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tanggal_pembayaran" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jumlah Dibayarkan (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="jumlah_dibayarkan" required 
                                       placeholder="Contoh: 50000">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                                <select class="form-select" name="metode_pembayaran" required>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="tunai">Tunai</option>
                                    <option value="debit">Kartu Debit</option>
                                    <option value="kredit">Kartu Kredit</option>
                                    <option value="ewallet">E-Wallet</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Denda (Rp)</label>
                                <input type="number" class="form-control" name="denda" 
                                       placeholder="Biarkan kosong jika tidak ada denda">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Pembayaran <span class="text-danger">*</span></label>
                                <select class="form-select" name="status_pembayaran" required>
                                    <option value="success">Lunas</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Gagal</option>
                                </select>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Keterangan (Opsional)</label>
                                <textarea class="form-control" name="keterangan" rows="3" 
                                          placeholder="Catatan tambahan..."></textarea>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="detail_peserta.php?id=<?php echo $peserta_id; ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Simpan Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>