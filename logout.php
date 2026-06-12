<?php
session_start();

// Jika user masih login, hancurkan session
if (isset($_SESSION['user_id'])) {
    // AMBIL NAMA YANG BENAR: full_name dulu, jika tidak ada baru username
    $nama_user = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
    
    // Logout reason
    $reason = $_GET['reason'] ?? 'manual';
    $reasons = [
        'manual' => 'Anda telah logout.',
        'timeout' => 'Sesi telah berakhir karena tidak ada aktivitas.',
        'midnight' => 'Sesi telah berakhir (auto logout).'
    ];
    $message = $reasons[$reason] ?? 'Anda telah logout.';
    
    // Debug: Tampilkan data session sebelum dihapus
    error_log("Logout - User ID: " . ($_SESSION['user_id'] ?? 'none'));
    error_log("Logout - Full Name: " . ($_SESSION['full_name'] ?? 'bunaya'));
    error_log("Logout - Username: " . ($_SESSION['username'] ?? 'bunaya'));
    error_log("Logout - Nama yang akan ditampilkan: " . $nama_user);
    
    // Destroy session
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(), '', 0, '/');
    
    // Start new session for logout message
    session_start();
    $_SESSION['logout_message'] = $message;
    $_SESSION['logout_username'] = $nama_user; // GUNAKAN VARIABLE YANG SUDAH DIPERBAIKI
    
    header("Location: logout_success.php");
    exit();
} else {
    // Jika tidak ada session, langsung redirect ke login
    header("Location: index.php");
    exit();
}
?>