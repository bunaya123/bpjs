<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek apakah ada parameter id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID faskes tidak valid!";
    header("Location: faskes.php");
    exit();
}

$id = intval($_GET['id']);

// Cek apakah faskes ada
$check_sql = "SELECT * FROM faskes WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);
$faskes = mysqli_fetch_assoc($result);

if (!$faskes) {
    $_SESSION['error'] = "Data faskes tidak ditemukan!";
    header("Location: faskes.php");
    exit();
}

// Proses hapus data
$delete_sql = "DELETE FROM faskes WHERE id = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "i", $id);

if (mysqli_stmt_execute($delete_stmt)) {
    $_SESSION['success'] = "Data faskes berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus data: " . mysqli_error($conn);
}

// Redirect ke halaman faskes
header("Location: faskes.php");
exit();
?>