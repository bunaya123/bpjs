<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$claim_id = (int)$_POST['claim_id'];
$status_klaim = $_POST['status_klaim'];
$catatan = $_POST['catatan'] ?? '';

// Validasi status
$allowed_statuses = ['pending', 'approved', 'rejected'];
if (!in_array($status_klaim, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
    exit;
}

// Update status klaim
$sql = "UPDATE klaim SET 
        status_klaim = ?,
        catatan = ?,
        updated_at = NOW()
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssi", $status_klaim, $catatan, $claim_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status']);
}

mysqli_stmt_close($stmt);