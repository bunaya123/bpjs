<?php
// simpan_kelas.php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Variabel untuk pesan
$message = '';
$message_type = '';
$redirect_url = 'kelas.php';

// Proses form berdasarkan aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required_fields = ['kode_kelas', 'nama_kelas', 'iuran_per_bulan'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $message = "Field $field harus diisi!";
            $message_type = "danger";
            break;
        }
    }
    
    if (!$message) {
        $kode_kelas = mysqli_real_escape_string($conn, $_POST['kode_kelas']);
        $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
        $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? '');
        $iuran_per_bulan = mysqli_real_escape_string($conn, $_POST['iuran_per_bulan']);
        $fasilitas = mysqli_real_escape_string($conn, $_POST['fasilitas'] ?? '');
        
        // Tentukan aksi (tambah atau edit)
        if (isset($_POST['tambah'])) {
            // Cek kode kelas sudah ada
            $check_sql = "SELECT id FROM kelas WHERE kode_kelas = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $kode_kelas);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $message = "Kode kelas sudah digunakan!";
                $message_type = "danger";
            } else {
                // Insert data baru
                $sql = "INSERT INTO kelas (kode_kelas, nama_kelas, deskripsi, iuran_per_bulan, fasilitas) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssds", 
                    $kode_kelas, 
                    $nama_kelas, 
                    $deskripsi, 
                    $iuran_per_bulan, 
                    $fasilitas
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Data kelas berhasil ditambahkan!";
                    $message_type = "success";
                } else {
                    $message = "Gagal menambahkan data kelas: " . mysqli_error($conn);
                    $message_type = "danger";
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($check_stmt);
            
        } elseif (isset($_POST['edit']) && isset($_POST['id'])) {
            $id = $_POST['id'];
            
            // Cek kode kelas sudah ada (kecuali untuk dirinya sendiri)
            $check_sql = "SELECT id FROM kelas WHERE kode_kelas = ? AND id != ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "si", $kode_kelas, $id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $message = "Kode kelas sudah digunakan oleh kelas lain!";
                $message_type = "danger";
            } else {
                // Update data
                $sql = "UPDATE kelas SET 
                        kode_kelas = ?, 
                        nama_kelas = ?, 
                        deskripsi = ?, 
                        iuran_per_bulan = ?, 
                        fasilitas = ? 
                        WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssdsi", 
                    $kode_kelas, 
                    $nama_kelas, 
                    $deskripsi, 
                    $iuran_per_bulan, 
                    $fasilitas, 
                    $id
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Data kelas berhasil diperbarui!";
                    $message_type = "success";
                    // UBAH: Redirect ke edit_kelas.php bukan detail_kelas.php
                    $redirect_url = "edit_kelas.php?id=$id";
                } else {
                    $message = "Gagal memperbarui data kelas: " . mysqli_error($conn);
                    $message_type = "danger";
                    // Tetap redirect ke edit_kelas.php jika error
                    $redirect_url = "edit_kelas.php?id=$id";
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($check_stmt);
        }
    }
    
    // Redirect dengan pesan
    $redirect_params = $message ? "?message=" . urlencode($message) . "&type=$message_type" : "";
    header("Location: $redirect_url$redirect_params");
    exit();
    
} elseif (isset($_GET['hapus'])) {
    // Proses hapus data
    $id = $_GET['hapus'];
    
    // Cek apakah kelas memiliki peserta
    $check_sql = "SELECT COUNT(*) as total FROM peserta WHERE kelas_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if ($check_data['total'] > 0) {
        $message = "Tidak dapat menghapus kelas yang masih memiliki peserta!";
        $message_type = "danger";
    } else {
        // Hapus data
        $sql = "DELETE FROM kelas WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Data kelas berhasil dihapus!";
            $message_type = "success";
        } else {
            $message = "Gagal menghapus data kelas: " . mysqli_error($conn);
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    }
    
    // Redirect dengan pesan
    $redirect_params = $message ? "?message=" . urlencode($message) . "&type=$message_type" : "";
    header("Location: $redirect_url$redirect_params");
    exit();
}

// Jika akses langsung ke file tanpa parameter yang valid
header("Location: kelas.php");
exit();
?> 