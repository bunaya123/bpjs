<?php
session_start();
require_once '../config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$no_reg = $_GET['no_reg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pendaftaran Berhasil</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .success { color: green; font-size: 24px; }
        .info { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="success">✓ Pendaftaran Berhasil!</div>
    <div class="info">
        <p>Nomor Pendaftaran: <strong><?php echo htmlspecialchars($no_reg); ?></strong></p>
        <p>Silakan lanjutkan proses pembayaran.</p>
    </div>
    <a href="pendaftaran.php">Kembali ke Form</a> | 
    <a href="../dashboard.php">Dashboard</a>
</body>
</html>