<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Update data peserta yang sudah verified
$sql_update = "UPDATE peserta 
SET status_pembayaran = 'verified' 
WHERE id IN (18, 19, 20) AND status_pembayaran = 'pending'";

if (mysqli_query($conn, $sql_update)) {
    echo "Data berhasil diupdate. " . mysqli_affected_rows($conn) . " baris diubah.";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Tambah kolom jika belum ada
$sql_alter = "ALTER TABLE peserta 
ADD COLUMN IF NOT EXISTS segmen_peserta ENUM('PPU', 'PBPU', 'PBI') DEFAULT 'PBI',
ADD COLUMN IF NOT EXISTS gaji_dilaporkan DECIMAL(15,2) DEFAULT 0";

if (mysqli_query($conn, $sql_alter)) {
    echo "<br>Struktur tabel sudah diperbarui.";
} else {
    echo "<br>Error alter table: " . mysqli_error($conn);
}
?>