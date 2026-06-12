<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

if (!isset($_GET['id'])) {
    die('Invalid request');
}

$claim_id = (int)$_GET['id'];

// Query detail klaim
$sql = "SELECT 
    k.*,
    p.nama as nama_peserta,
    p.no_kartu,
    p.kelas_bpjs,
    p.segmen_peserta,
    p.no_telepon,
    p.email,
    p.alamat,
    p.tanggal_lahir,
    p.jenis_kelamin,
    p.faskes,
    p.pekerjaan,
    p.provinsi,
    p.kota
FROM klaim k
LEFT JOIN peserta p ON k.peserta_id = p.id
WHERE k.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $claim_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$claim = mysqli_fetch_assoc($stmt);

if (!$claim) {
    echo '<div class="alert alert-danger">Data klaim tidak ditemukan</div>';
    exit;
}

// Tentukan warna badge berdasarkan status
$status_badge = '';
$status_text = '';
switch ($claim['status_klaim']) {
    case 'pending':
        $status_badge = 'badge-warning';
        $status_text = 'Pending';
        break;
    case 'approved':
        $status_badge = 'badge-success';
        $status_text = 'Approved';
        break;
    case 'rejected':
        $status_badge = 'badge-danger';
        $status_text = 'Rejected';
        break;
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="alert <?php echo $status_badge; ?>">
            <h5 class="mb-0">
                Status Klaim: <strong><?php echo $status_text; ?></strong>
                | No Klaim: <strong><?php echo htmlspecialchars($claim['no_klaim']); ?></strong>
            </h5>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">Data Peserta</h6>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <strong>Nama Peserta:</strong>
                    <?php echo htmlspecialchars($claim['nama_peserta']); ?>
                </div>
                <div class="info-row">
                    <strong>No Kartu BPJS:</strong>
                    <?php echo htmlspecialchars($claim['no_kartu']); ?>
                </div>
                <div class="info-row">
                    <strong>Jenis Kelamin:</strong>
                    <?php echo $claim['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?>
                </div>
                <div class="info-row">
                    <strong>Tanggal Lahir:</strong>
                    <?php echo date('d/m/Y', strtotime($claim['tanggal_lahir'])); ?>
                </div>
                <div class="info-row">
                    <strong>Kelas BPJS:</strong>
                    <?php echo htmlspecialchars($claim['kelas_bpjs']); ?>
                </div>
                <div class="info-row">
                    <strong>Segmen Peserta:</strong>
                    <?php echo htmlspecialchars($claim['segmen_peserta']); ?>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">Kontak & Alamat</h6>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <strong>No Telepon:</strong>
                    <?php echo htmlspecialchars($claim['no_telepon'] ?? '-'); ?>
                </div>
                <div class="info-row">
                    <strong>Email:</strong>
                    <?php echo htmlspecialchars($claim['email'] ?? '-'); ?>
                </div>
                <div class="info-row">
                    <strong>Alamat:</strong>
                    <?php echo htmlspecialchars($claim['alamat'] ?? '-'); ?>
                </div>
                <div class="info-row">
                    <strong>Provinsi/Kota:</strong>
                    <?php echo htmlspecialchars($claim['provinsi'] ?? '-'); ?> / <?php echo htmlspecialchars($claim['kota'] ?? '-'); ?>
                </div>
                <div class="info-row">
                    <strong>Pekerjaan:</strong>
                    <?php echo htmlspecialchars($claim['pekerjaan'] ?? '-'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">Detail Klaim</h6>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <strong>Nominal Klaim:</strong>
                    <h4 class="text-danger">Rp <?php echo number_format($claim['nominal_klaim'], 0, ',', '.'); ?></h4>
                </div>
                <div class="info-row">
                    <strong>Diagnosa:</strong>
                    <p class="mb-0"><?php echo htmlspecialchars($claim['diagnosa'] ?? '-'); ?></p>
                </div>
                <div class="info-row">
                    <strong>Tanggal Klaim:</strong>
                    <?php echo date('d/m/Y', strtotime($claim['tanggal_klaim'])); ?>
                </div>
                <div class="info-row">
                    <strong>Faskes:</strong>
                    <?php echo htmlspecialchars($claim['faskes'] ?? '-'); ?>
                </div>
                <div class="info-row">
                    <strong>Tanggal Dibuat:</strong>
                    <?php echo date('d/m/Y H:i:s', strtotime($claim['created_at'])); ?>
                </div>
                <div class="info-row">
                    <strong>Terakhir Diupdate:</strong>
                    <?php echo date('d/m/Y H:i:s', strtotime($claim['updated_at'])); ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-warning">
                <h6 class="mb-0">Catatan & Info Tambahan</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($claim['catatan'])): ?>
                <div class="info-row">
                    <strong>Catatan:</strong>
                    <p class="mb-0"><?php echo htmlspecialchars($claim['catatan']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <strong>Dokumen Pendukung:</strong>
                    <?php if (!empty($claim['dokumen_pendukung'])): ?>
                    <a href="uploads/<?php echo $claim['dokumen_pendukung']; ?>" target="_blank" class="btn btn-sm btn-info">
                        <i class="mdi mdi-download"></i> Download Dokumen
                    </a>
                    <?php else: ?>
                    <span class="text-muted">Tidak ada dokumen</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>