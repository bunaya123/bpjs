<?php
// hapus_kelas.php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil ID kelas dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kelas.php");
    exit();
}

$kelas_id = $_GET['id'];

// Ambil data kelas untuk konfirmasi
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

// Cek apakah kelas memiliki peserta
$check_sql = "SELECT COUNT(*) as total FROM peserta WHERE kelas_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $kelas_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$check_data = mysqli_fetch_assoc($check_result);
$jumlah_peserta = $check_data['total'];
mysqli_stmt_close($check_stmt);

// Proses hapus jika dikonfirmasi
if (isset($_POST['confirm_delete'])) {
    if ($jumlah_peserta > 0) {
        // Redirect dengan pesan error
        header("Location: kelas.php?error=Tidak+dapat+menghapus+kelas+yang+masih+memiliki+peserta");
        exit();
    }
    
    $sql = "DELETE FROM kelas WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $kelas_id);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: kelas.php?success=Data+kelas+berhasil+dihapus");
        exit();
    } else {
        header("Location: kelas.php?error=Gagal+menghapus+data+kelas");
        exit();
    }
    mysqli_stmt_close($stmt);
}

// Redirect jika bukan metode POST (langsung akses URL)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Tampilkan halaman konfirmasi
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi Hapus Kelas - BPJS Kesehatan</title>
        <link rel="stylesheet" href="../assets/css/shared/style.css">
        <style>
            body {
                background: #f5f5f5;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            
            .confirmation-box {
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                padding: 30px;
                max-width: 500px;
                width: 100%;
                text-align: center;
            }
            
            .icon-danger {
                color: #f44336;
                font-size: 48px;
                margin-bottom: 20px;
            }
            
            .btn {
                padding: 10px 20px;
                border-radius: 5px;
                border: none;
                cursor: pointer;
                font-weight: 500;
                transition: all 0.3s;
                margin: 0 10px;
            }
            
            .btn-danger {
                background: #f44336;
                color: white;
            }
            
            .btn-danger:hover {
                background: #d32f2f;
            }
            
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            
            .btn-secondary:hover {
                background: #5a6268;
            }
            
            .info-box {
                background: #f8f9fa;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
                text-align: left;
            }
            
            .warning {
                color: #ff9800;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 10px;
                border-radius: 5px;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class="confirmation-box">
            <div class="icon-danger">
                <i class="mdi mdi-alert-circle"></i>
            </div>
            
            <h3>Konfirmasi Hapus Kelas</h3>
            <p>Apakah Anda yakin ingin menghapus kelas berikut?</p>
            
            <div class="info-box">
                <p><strong>Kode Kelas:</strong> <?php echo htmlspecialchars($kelas['kode_kelas']); ?></p>
                <p><strong>Nama Kelas:</strong> <?php echo htmlspecialchars($kelas['nama_kelas']); ?></p>
                <p><strong>Iuran/Bulan:</strong> Rp <?php echo number_format($kelas['iuran_per_bulan'], 0, ',', '.'); ?></p>
                <p><strong>Jumlah Peserta:</strong> <?php echo $jumlah_peserta; ?></p>
            </div>
            
            <?php if ($jumlah_peserta > 0): ?>
            <div class="warning">
                <i class="mdi mdi-alert"></i>
                <strong>Peringatan!</strong> Kelas ini masih memiliki <?php echo $jumlah_peserta; ?> peserta. 
                Anda tidak dapat menghapus kelas yang masih memiliki peserta.
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div style="margin-top: 30px;">
                    <a href="kelas.php" class="btn btn-secondary">
                        <i class="mdi mdi-close"></i> Batal
                    </a>
                    
                    <?php if ($jumlah_peserta == 0): ?>
                    <button type="submit" name="confirm_delete" class="btn btn-danger" 
                            onclick="return confirm('Apakah Anda benar-benar yakin? Tindakan ini tidak dapat dibatalkan.')">
                        <i class="mdi mdi-delete"></i> Hapus Permanen
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <script src="../assets/vendors/js/core.js"></script>
    </body>
    </html>
    <?php
    exit();
}
?>