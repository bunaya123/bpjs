<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Ambil data user dan peserta
$sql = "SELECT u.*, p.*, k.nama_kelas, k.iuran_per_bulan 
        FROM users u
        LEFT JOIN peserta p ON u.id = p.user_id
        LEFT JOIN kelas k ON p.kelas_id = k.id 
        WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Cek apakah user sudah terdaftar
if (!$data || !$data['peserta_id']) {
    header("Location: pembayaran.php");
    exit();
}

// Proses pembayaran jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $periode = $_POST['periode'];
    $jumlah_bulan = intval($_POST['jumlah_bulan']);
    $metode_pembayaran = $_POST['metode_pembayaran'];
    
    // Hitung total bayar
    $iuran_per_bulan = $data['iuran_per_bulan'] ?? 50000;
    $total_bayar = $jumlah_bulan * $iuran_per_bulan;
    
    // Generate nomor transaksi
    $no_transaksi = 'TRX' . date('YmdHis') . rand(100, 999);
    
    // Simpan ke database
    $sql_insert = "INSERT INTO pembayaran_iuran (
        user_id, peserta_id, no_transaksi, periode, jumlah_bulan, 
        metode_pembayaran, total_bayar, status, tanggal_bayar
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "iissisd", 
        $user_id, $data['id'], $no_transaksi, $periode, $jumlah_bulan, 
        $metode_pembayaran, $total_bayar
    );
    
    if (mysqli_stmt_execute($stmt_insert)) {
        $_SESSION['pembayaran_success'] = $no_transaksi;
        header("Location: konfirmasi_pembayaran.php?trx=" . $no_transaksi);
        exit();
    } else {
        $error = "Gagal menyimpan transaksi. Silakan coba lagi.";
    }
    mysqli_stmt_close($stmt_insert);
}

// Ambil metode pembayaran dari database
$sql_metode = "SELECT * FROM metode_pembayaran WHERE status = 'active'";
$result_metode = mysqli_query($conn, $sql_metode);
$metode_pembayaran = mysqli_fetch_all($result_metode, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Proses Pembayaran - BPJS Kesehatan</title>
    
    <!-- Template CSS -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    
    <style>
    .payment-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .payment-header {
        text-align: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #0073e6;
    }
    .payment-step {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 10px;
    }
    .step-number {
        width: 40px;
        height: 40px;
        background: #0073e6;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 1rem;
    }
    .payment-info {
        background: #e3f2fd;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    .btn-pay {
        background: #28a745;
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 5px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
        margin-top: 1rem;
        transition: all 0.3s;
    }
    .btn-pay:hover {
        background: #218838;
        transform: translateY(-2px);
    }
    .alert {
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
    }
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .payment-method-option {
        display: flex;
        align-items: center;
        padding: 1rem;
        border: 2px solid #ddd;
        border-radius: 10px;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    .payment-method-option:hover {
        border-color: #0073e6;
        background: #f0f8ff;
    }
    .payment-method-option.selected {
        border-color: #0073e6;
        background: #e8f4fd;
    }
    </style>
</head>
<body>
    <?php include 'partials/header.php'; ?>
    
    <div class="page-body">
        <?php include 'partials/sidebar.php'; ?>
        
        <div class="page-content-wrapper">
            <div class="payment-container">
                <div class="payment-header">
                    <h2><i class="mdi mdi-cash-usd"></i> Proses Pembayaran Iuran</h2>
                    <p>Lengkapi data pembayaran Anda</p>
                </div>
                
                <!-- Progress Steps -->
                <div class="payment-step">
                    <div class="step-number">1</div>
                    <div>
                        <h5>Data Peserta</h5>
                        <p>Verifikasi data peserta</p>
                    </div>
                </div>
                <div class="payment-step" style="background: #e8f4fd;">
                    <div class="step-number">2</div>
                    <div>
                        <h5>Detail Pembayaran</h5>
                        <p>Pilih periode dan metode bayar</p>
                    </div>
                </div>
                <div class="payment-step">
                    <div class="step-number">3</div>
                    <div>
                        <h5>Konfirmasi</h5>
                        <p>Verifikasi pembayaran</p>
                    </div>
                </div>
                
                <!-- Info Peserta -->
                <div class="payment-info">
                    <h4>Informasi Peserta</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nama:</strong> <?php echo htmlspecialchars($data['nama'] ?? 'Belum diisi'); ?></p>
                            <p><strong>No. Kartu:</strong> <?php echo htmlspecialchars($data['no_kartu'] ?? 'Belum diisi'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Kelas:</strong> <?php echo htmlspecialchars($data['nama_kelas'] ?? 'Kelas 3'); ?></p>
                            <p><strong>Iuran per Bulan:</strong> Rp <?php echo number_format($data['iuran_per_bulan'] ?? 50000, 0, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Form Pembayaran -->
                <form method="POST" action="" id="paymentForm">
                    <div class="form-group">
                        <label><i class="mdi mdi-calendar"></i> Pilih Periode</label>
                        <select name="periode" class="form-control" required>
                            <option value="">-- Pilih Periode --</option>
                            <?php
                            // Generate periode untuk 12 bulan ke depan
                            for ($i = 0; $i < 12; $i++) {
                                $date = date('Y-m', strtotime("+$i months"));
                                $display = date('F Y', strtotime($date . '-01'));
                                echo "<option value='$date'>$display</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="mdi mdi-calendar-multiple"></i> Jumlah Bulan</label>
                        <select name="jumlah_bulan" class="form-control" required id="jumlah_bulan" onchange="calculateTotal()">
                            <option value="1">1 Bulan</option>
                            <option value="3">3 Bulan (Diskon 5%)</option>
                            <option value="6">6 Bulan (Diskon 10%)</option>
                            <option value="12">12 Bulan (Diskon 15%)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="mdi mdi-credit-card"></i> Metode Pembayaran</label>
                        <div id="paymentMethods">
                            <?php foreach($metode_pembayaran as $metode): ?>
                                <div class="payment-method-option" onclick="selectPaymentMethod(this)" data-value="<?php echo $metode['kode_metode']; ?>">
                                    <input type="radio" name="metode_pembayaran" value="<?php echo $metode['kode_metode']; ?>" style="display: none;">
                                    <div style="flex: 1;">
                                        <strong><?php echo $metode['nama_metode']; ?></strong>
                                        <?php if($metode['nama_bank']): ?>
                                            <br><small><?php echo $metode['nama_bank']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($metode['biaya_admin'] > 0): ?>
                                        <div style="color: #666;">
                                            +Rp <?php echo number_format($metode['biaya_admin'], 0, ',', '.'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="mdi mdi-cash-multiple"></i> Rincian Pembayaran</label>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                            <table width="100%">
                                <tr>
                                    <td>Iuran per bulan:</td>
                                    <td id="iuran_per_bulan" align="right">
                                        Rp <?php echo number_format($data['iuran_per_bulan'] ?? 50000, 0, ',', '.'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Jumlah bulan:</td>
                                    <td id="display_jumlah_bulan" align="right">1</td>
                                </tr>
                                <tr>
                                    <td>Subtotal:</td>
                                    <td id="subtotal" align="right">
                                        Rp <?php echo number_format($data['iuran_per_bulan'] ?? 50000, 0, ',', '.'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Diskon:</td>
                                    <td id="diskon" align="right">Rp 0</td>
                                </tr>
                                <tr>
                                    <td>Biaya admin:</td>
                                    <td id="biaya_admin" align="right">Rp 0</td>
                                </tr>
                                <tr style="border-top: 1px solid #ddd;">
                                    <td><strong>Total Bayar:</strong></td>
                                    <td id="total_display" align="right">
                                        <strong>Rp <?php echo number_format($data['iuran_per_bulan'] ?? 50000, 0, ',', '.'); ?></strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <input type="hidden" name="total_bayar" id="total_bayar" value="<?php echo $data['iuran_per_bulan'] ?? 50000; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="mdi mdi-note-text"></i> Catatan (Opsional)</label>
                        <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 10px; margin-top: 2rem;">
                        <a href="pembayaran.php" class="btn" style="flex: 1; background: #6c757d; color: white; padding: 1rem; text-align: center; border-radius: 5px; text-decoration: none;">
                            <i class="mdi mdi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn-pay">
                            <i class="mdi mdi-lock"></i> LANJUTKAN KE PEMBAYARAN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    const iuranPerBulan = <?php echo $data['iuran_per_bulan'] ?? 50000; ?>;
    
    function selectPaymentMethod(element) {
        // Remove selected class from all options
        document.querySelectorAll('.payment-method-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Add selected class to clicked option
        element.classList.add('selected');
        
        // Check the radio button
        const radio = element.querySelector('input[type="radio"]');
        radio.checked = true;
        
        // Calculate total
        calculateTotal();
    }
    
    function calculateTotal() {
        const jumlahBulan = parseInt(document.getElementById('jumlah_bulan').value);
        const subtotal = iuranPerBulan * jumlahBulan;
        
        // Hitung diskon
        let diskon = 0;
        if (jumlahBulan >= 12) diskon = subtotal * 0.15;
        else if (jumlahBulan >= 6) diskon = subtotal * 0.10;
        else if (jumlahBulan >= 3) diskon = subtotal * 0.05;
        
        // Biaya admin (default 0)
        let biayaAdmin = 0;
        
        // Cek metode pembayaran yang dipilih
        const selectedMethod = document.querySelector('.payment-method-option.selected');
        if (selectedMethod) {
            // Ambil biaya admin dari data (ini bisa disesuaikan dengan database)
            const adminText = selectedMethod.querySelector('div:last-child');
            if (adminText && adminText.textContent.includes('+Rp')) {
                biayaAdmin = parseInt(adminText.textContent.replace(/[^0-9]/g, ''));
            }
        }
        
        const total = subtotal - diskon + biayaAdmin;
        
        // Update display
        document.getElementById('display_jumlah_bulan').textContent = jumlahBulan;
        document.getElementById('subtotal').textContent = formatRupiah(subtotal);
        document.getElementById('diskon').textContent = formatRupiah(diskon);
        document.getElementById('biaya_admin').textContent = formatRupiah(biayaAdmin);
        document.getElementById('total_display').innerHTML = '<strong>' + formatRupiah(total) + '</strong>';
        document.getElementById('total_bayar').value = total;
    }
    
    function formatRupiah(angka) {
        return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotal();
    });
    </script>
</body>
</html>