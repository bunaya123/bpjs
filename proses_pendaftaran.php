<?php
// proses_pendaftaran.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Proses data dari form pendaftaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $nik = mysqli_real_escape_string($conn, $_POST['nik']);
    $tempat_lahir = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($conn, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $provinsi = mysqli_real_escape_string($conn, $_POST['provinsi']);
    $kota = mysqli_real_escape_string($conn, $_POST['kota']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pekerjaan = mysqli_real_escape_string($conn, $_POST['pekerjaan']);
    $kelas_bpjs = mysqli_real_escape_string($conn, $_POST['kelas_bpjs']);
    $faskes = mysqli_real_escape_string($conn, $_POST['faskes']);
    $tahun_berlangganan = mysqli_real_escape_string($conn, $_POST['tahun_berlangganan']);
    $metode_pembayaran = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);
    
    // Simpan ke database
    $sql = "INSERT INTO peserta_bpjs (nama, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, provinsi, kota, no_telepon, email, pekerjaan, kelas_bpjs, faskes, tahun_berlangganan, metode_pembayaran, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssssssssssss", $nama, $nik, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $alamat, $provinsi, $kota, $no_telepon, $email, $pekerjaan, $kelas_bpjs, $faskes, $tahun_berlangganan, $metode_pembayaran);
    
    if (mysqli_stmt_execute($stmt)) {
        $peserta_id = mysqli_insert_id($conn);
        header("Location: pendaftaran_berhasil.php?id=" . $peserta_id);
        exit();
    } else {
        $error = "Gagal menyimpan data: " . mysqli_error($conn);
        header("Location: pendaftaran.php?error=" . urlencode($error));
        exit();
    }
} else {
    header("Location: pendaftaran.php");
    exit();
}
?>