<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Ambil ID dari parameter URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: peserta_bpjs.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Ambil data peserta untuk mendapatkan nama (untuk pesan konfirmasi)
$sql_select = "SELECT nama FROM peserta WHERE id = ?";
$stmt_select = mysqli_prepare($conn, $sql_select);
mysqli_stmt_bind_param($stmt_select, "i", $id);
mysqli_stmt_execute($stmt_select);
$result_select = mysqli_stmt_get_result($stmt_select);

if (mysqli_num_rows($result_select) == 0) {
    $_SESSION['error'] = "Data peserta tidak ditemukan!";
    header("Location: peserta_bpjs.php");
    exit();
}

$peserta = mysqli_fetch_assoc($result_select);
$nama_peserta = $peserta['nama'];
mysqli_stmt_close($stmt_select);

// Hapus data
$sql_delete = "DELETE FROM peserta WHERE id = ?";
$stmt_delete = mysqli_prepare($conn, $sql_delete);
mysqli_stmt_bind_param($stmt_delete, "i", $id);

if (mysqli_stmt_execute($stmt_delete)) {
    $_SESSION['success'] = "Data peserta <strong>$nama_peserta</strong> berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus data: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt_delete);
header("Location: peserta_bpjs.php");
exit();