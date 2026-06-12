<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek jika request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid!";
    header("Location: obat.php");
    exit();
}

// Ambil dan sanitasi data dari form
$kode_obat = isset($_POST['kode_obat']) ? mysqli_real_escape_string($conn, trim($_POST['kode_obat'])) : '';
$nama_obat = isset($_POST['nama_obat']) ? mysqli_real_escape_string($conn, trim($_POST['nama_obat'])) : '';
$jenis = isset($_POST['jenis']) ? mysqli_real_escape_string($conn, trim($_POST['jenis'])) : NULL;
$satuan = isset($_POST['satuan']) ? mysqli_real_escape_string($conn, trim($_POST['satuan'])) : '';
$stok = isset($_POST['stok']) ? intval($_POST['stok']) : 0;
$harga = isset($_POST['harga']) ? intval($_POST['harga']) : 0;
$tanggal_expired = isset($_POST['tanggal_expired']) && !empty($_POST['tanggal_expired']) ? mysqli_real_escape_string($conn, $_POST['tanggal_expired']) : NULL;
$status = isset($_POST['status']) ? mysqli_real_escape_string($conn, trim($_POST['status'])) : '';
$keterangan = isset($_POST['keterangan']) ? mysqli_real_escape_string($conn, trim($_POST['keterangan'])) : NULL;

// Validasi data wajib
if (empty($kode_obat) || empty($nama_obat) || empty($satuan) || empty($status)) {
    $_SESSION['error'] = "Semua field yang wajib diisi tidak boleh kosong!";
    header("Location: obat.php");
    exit();
}

// Validasi stok dan harga tidak negatif
if ($stok < 0 || $harga < 0) {
    $_SESSION['error'] = "Stok dan harga tidak boleh negatif!";
    header("Location: obat.php");
    exit();
}

// Cek apakah kode obat sudah ada
$sql_check = "SELECT id FROM obat WHERE kode_obat = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "s", $kode_obat);
mysqli_stmt_execute($stmt_check);
mysqli_stmt_store_result($stmt_check);

if (mysqli_stmt_num_rows($stmt_check) > 0) {
    mysqli_stmt_close($stmt_check);
    $_SESSION['error'] = "Kode obat '$kode_obat' sudah digunakan!";
    header("Location: obat.php");
    exit();
}
mysqli_stmt_close($stmt_check);

// Insert data obat baru
$sql_insert = "INSERT INTO obat (kode_obat, nama_obat, jenis, satuan, stok, harga, tanggal_expired, status, keterangan, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt_insert = mysqli_prepare($conn, $sql_insert);

if ($stmt_insert) {
    mysqli_stmt_bind_param($stmt_insert, "ssssiisss", 
        $kode_obat, 
        $nama_obat, 
        $jenis, 
        $satuan, 
        $stok, 
        $harga, 
        $tanggal_expired, 
        $status, 
        $keterangan
    );

    if (mysqli_stmt_execute($stmt_insert)) {
        $_SESSION['success'] = "Data obat berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan data obat: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt_insert);
} else {
    $_SESSION['error'] = "Gagal menyiapkan query: " . mysqli_error($conn);
}

// Redirect kembali ke halaman obat
header("Location: obat.php");
exit();
?>