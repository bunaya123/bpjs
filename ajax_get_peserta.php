<?php
// ajax_get_peserta.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_GET['kelas_id'])) {
    $kelas_id = intval($_GET['kelas_id']);
    
    $sql = "SELECT COUNT(*) as jumlah_peserta FROM peserta WHERE kelas_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $kelas_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'jumlah_peserta' => $data['jumlah_peserta'] ?? 0
    ]);
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>