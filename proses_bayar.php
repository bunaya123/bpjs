<?php
session_start();
require_once '../config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Cek metode POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $_SESSION['error'] = "Akses tidak valid.";
    header("Location: pembayaran.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$iuran_id = $_POST['iuran_id'] ?? 0;
$metode_bayar = $_POST['metode_bayar'] ?? '';

// Validasi input
if (empty($iuran_id) || empty($metode_bayar)) {
    $_SESSION['error'] = "Data tidak lengkap.";
    header("Location: pembayaran.php");
    exit();
}

// Ambil data iuran
$sql_iuran = "SELECT i.*, p.id as peserta_id, p.nama, p.no_kartu, k.iuran_per_bulan
              FROM iuran i
              JOIN peserta p ON i.peserta_id = p.id
              JOIN kelas k ON p.kelas_id = k.id
              WHERE i.id = ? AND i.status != 'Lunas'";
$stmt_iuran = mysqli_prepare($conn, $sql_iuran);
mysqli_stmt_bind_param($stmt_iuran, "i", $iuran_id);
mysqli_stmt_execute($stmt_iuran);
$result_iuran = mysqli_stmt_get_result($stmt_iuran);
$iuran = mysqli_fetch_assoc($result_iuran);

if (!$iuran) {
    $_SESSION['error'] = "Tagihan tidak ditemukan atau sudah lunas.";
    header("Location: pembayaran.php");
    exit();
}

// Ambil biaya admin metode
$sql_admin = "SELECT biaya_admin FROM metode_pembayaran WHERE kode_metode = ?";
$stmt_admin = mysqli_prepare($conn, $sql_admin);
mysqli_stmt_bind_param($stmt_admin, "s", $metode_bayar);
mysqli_stmt_execute($stmt_admin);
$result_admin = mysqli_stmt_get_result($stmt_admin);
$admin_fee = mysqli_fetch_assoc($result_admin)['biaya_admin'] ?? 0;

// Hitung total
$total_bayar = $iuran['total_bayar'] + $admin_fee;

// Generate reference number
$reference_number = 'REF' . date('YmdHis') . str_pad($iuran_id, 6, '0', STR_PAD_LEFT);

// Mulai transaction
mysqli_begin_transaction($conn);

try {
    // Insert ke tabel pembayaran
    $sql_pembayaran = "INSERT INTO pembayaran (
        iuran_id, peserta_id, no_pembayaran, tanggal_bayar, 
        jumlah_bayar, metode_bayar, reference_number, status, keterangan
    ) VALUES (?, ?, ?, NOW(), ?, ?, ?, 'Pending', ?)";
    
    $no_pembayaran = 'PAY-' . date('Ymd') . '-' . str_pad($iuran_id, 5, '0', STR_PAD_LEFT);
    $keterangan = "Pembayaran iuran bulan " . $iuran['bulan_tahun'] . " via " . $metode_bayar;
    
    $stmt_pembayaran = mysqli_prepare($conn, $sql_pembayaran);
    mysqli_stmt_bind_param($stmt_pembayaran, "iissdsss", 
        $iuran_id, 
        $iuran['peserta_id'], 
        $no_pembayaran,
        $total_bayar,
        $metode_bayar,
        $reference_number,
        $keterangan
    );
    mysqli_stmt_execute($stmt_pembayaran);
    $pembayaran_id = mysqli_insert_id($conn);
    
    // Update status iuran menjadi "Diproses"
    $sql_update_iuran = "UPDATE iuran SET status = 'Diproses' WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update_iuran);
    mysqli_stmt_bind_param($stmt_update, "i", $iuran_id);
    mysqli_stmt_execute($stmt_update);
    
    // Insert riwayat status
    $sql_riwayat = "INSERT INTO riwayat_status_iuran (iuran_id, status_lama, status_baru, alasan_perubahan) 
                   VALUES (?, ?, ?, ?)";
    $stmt_riwayat = mysqli_prepare($conn, $sql_riwayat);
    $status_lama = $iuran['status'];
    $status_baru = 'Diproses';
    $alasan = "Pembayaran diproses via " . $metode_bayar . " (Ref: " . $reference_number . ")";
    mysqli_stmt_bind_param($stmt_riwayat, "isss", $iuran_id, $status_lama, $status_baru, $alasan);
    mysqli_stmt_execute($stmt_riwayat);
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Redirect ke halaman konfirmasi dengan ID pembayaran
    $_SESSION['success'] = "Pembayaran berhasil diproses! Silakan upload bukti pembayaran.";
    header("Location: konfirmasi.php?id=" . $pembayaran_id);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction jika error
    mysqli_rollback($conn);
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: pembayaran.php");
    exit();
}