<?php
session_start();
require_once '../config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$pembayaran_id = $_GET['id'] ?? 0;

// Ambil data pembayaran
$sql = "SELECT pb.*, i.bulan_tahun, p.nama, p.no_kartu 
        FROM pembayaran pb
        JOIN iuran i ON pb.iuran_id = i.id
        JOIN peserta p ON pb.peserta_id = p.id
        WHERE pb.id = ? AND pb.peserta_id IN (SELECT id FROM peserta WHERE user_id = ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $pembayaran_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pembayaran = mysqli_fetch_assoc($stmt);

if (!$pembayaran) {
    $_SESSION['error'] = "Data pembayaran tidak ditemukan.";
    header("Location: pembayaran.php");
    exit();
}

// Proses upload bukti
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bukti_bayar = uploadBuktiBayar();
    
    if ($bukti_bayar) {
        // Update pembayaran dengan bukti
        $sql_update = "UPDATE pembayaran SET bukti_bayar = ?, status = 'Diproses' WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "si", $bukti_bayar, $pembayaran_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $_SESSION['success'] = "Bukti pembayaran berhasil diupload! Akan diverifikasi oleh admin.";
            header("Location: pembayaran.php");
            exit();
        } else {
            $_SESSION['error'] = "Gagal menyimpan bukti pembayaran.";
        }
    }
}

function uploadBuktiBayar() {
    $target_dir = "../uploads/bukti_bayar/";
    
    // Buat folder jika belum ada
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES["bukti_bayar"]["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Cek apakah file gambar
    $check = getimagesize($_FILES["bukti_bayar"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error'] = "File harus berupa gambar.";
        return false;
    }
    
    // Cek ukuran file (max 5MB)
    if ($_FILES["bukti_bayar"]["size"] > 5000000) {
        $_SESSION['error'] = "Ukuran file terlalu besar (max 5MB).";
        return false;
    }
    
    // Hanya format tertentu
    $allowed_types = ["jpg", "jpeg", "png", "gif", "pdf"];
    if (!in_array($imageFileType, $allowed_types)) {
        $_SESSION['error'] = "Hanya format JPG, JPEG, PNG, GIF, PDF yang diizinkan.";
        return false;
    }
    
    // Upload file
    if (move_uploaded_file($_FILES["bukti_bayar"]["tmp_name"], $target_file)) {
        return $file_name;
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat upload.";
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Pembayaran</title>
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <style>
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background-color: #f8f9fa;
            border-color: #0056b3;
        }
        .preview-image {
            max-width: 300px;
            max-height: 300px;
            margin: 20px auto;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Konfirmasi Pembayaran</h4>
                    </div>
                    <div class="card-body">
                        <!-- Info Pembayaran -->
                        <div class="alert alert-info">
                            <h5>Detail Pembayaran</h5>
                            <p><strong>No. Pembayaran:</strong> <?php echo $pembayaran['no_pembayaran']; ?></p>
                            <p><strong>Bulan:</strong> <?php echo $pembayaran['bulan_tahun']; ?></p>
                            <p><strong>Jumlah:</strong> Rp <?php echo number_format($pembayaran['jumlah_bayar'], 0, ',', '.'); ?></p>
                            <p><strong>Metode:</strong> <?php echo $pembayaran['metode_bayar']; ?></p>
                            <p><strong>Reference:</strong> <?php echo $pembayaran['reference_number']; ?></p>
                        </div>
                        
                        <!-- Form Upload -->
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Upload Bukti Pembayaran</label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="mdi mdi-cloud-upload" style="font-size: 48px; color: #007bff;"></i>
                                    <p class="mt-3">Klik atau seret file ke sini</p>
                                    <p class="text-muted">Format: JPG, PNG, GIF, PDF (max 5MB)</p>
                                    <input type="file" name="bukti_bayar" id="buktiInput" accept="image/*,.pdf" hidden required>
                                </div>
                                <img id="preview" class="preview-image img-thumbnail" alt="Preview">
                            </div>
                            
                            <div class="form-group">
                                <label>Catatan (Opsional)</label>
                                <textarea name="catatan" class="form-control" rows="3" 
                                          placeholder="Tambahkan catatan jika diperlukan"></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="mdi mdi-check"></i> Konfirmasi Pembayaran
                                </button>
                                <a href="pembayaran.php" class="btn btn-secondary btn-lg">Kembali</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Upload area click
        document.getElementById('uploadArea').addEventListener('click', function() {
            document.getElementById('buktiInput').click();
        });
        
        // Preview image
        document.getElementById('buktiInput').addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').style.display = 'block';
                    document.getElementById('preview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            document.getElementById('uploadArea').addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            document.getElementById('uploadArea').addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            document.getElementById('uploadArea').addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            document.getElementById('uploadArea').classList.add('bg-light');
        }
        
        function unhighlight(e) {
            document.getElementById('uploadArea').classList.remove('bg-light');
        }
        
        document.getElementById('uploadArea').addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            var dt = e.dataTransfer;
            var files = dt.files;
            document.getElementById('buktiInput').files = files;
            
            // Trigger change event
            var event = new Event('change');
            document.getElementById('buktiInput').dispatchEvent(event);
        }
    </script>
</body>
</html>