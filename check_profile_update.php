<?php
// check_profile_update.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['error' => 'No user ID']);
    exit();
}

$user_id = intval($_GET['user_id']);

// Ambil data terbaru dari database
$sql = "SELECT profile_pic FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Cek apakah ada perubahan
$current_pic = $_SESSION['last_profile_pic'] ?? '';
$new_pic = $user['profile_pic'] ?? '';
$updated = ($current_pic !== $new_pic);

// Update session dengan foto terbaru
$_SESSION['last_profile_pic'] = $new_pic;

echo json_encode([
    'updated' => $updated,
    'profile_pic' => $new_pic,
    'timestamp' => time()
]);

mysqli_close($conn);
?>