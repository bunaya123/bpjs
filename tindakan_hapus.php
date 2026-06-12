<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek jika ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID tindakan tidak valid!";
    header("Location: tindakan.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Cek apakah tindakan ada
$sql_check = "SELECT * FROM tindakan WHERE id = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $id);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);

if (mysqli_num_rows($result_check) === 0) {
    $_SESSION['error'] = "Data tindakan tidak ditemukan!";
    header("Location: tindakan.php");
    exit();
}

$tindakan = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

// Hapus data
$sql_delete = "DELETE FROM tindakan WHERE id = ?";
$stmt_delete = mysqli_prepare($conn, $sql_delete);
mysqli_stmt_bind_param($stmt_delete, "i", $id);

if (mysqli_stmt_execute($stmt_delete)) {
    $_SESSION['success'] = "Data tindakan '{$tindakan['nama_tindakan']}' berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus data: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt_delete);

// Redirect kembali ke halaman tindakan
header("Location: tindakan.php");
exit();
?>