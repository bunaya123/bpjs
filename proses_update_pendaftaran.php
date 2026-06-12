<?php
// proses_update_pendaftaran.php

// Mulai session hanya jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek aksi
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['action']) || $_POST['action'] != 'update') {
    header("Location: pendaftaran.php");
    exit();
}

// Cek ID
if (!isset($_POST['pendaftaran_id']) || !is_numeric($_POST['pendaftaran_id'])) {
    header("Location: pendaftaran.php");
    exit();
}

$pendaftaran_id = intval($_POST['pendaftaran_id']);

// Ambil data dari form
$data = [
    'nama' => mysqli_real_escape_string($conn, $_POST['nama']),
    'nik' => mysqli_real_escape_string($conn, $_POST['nik']),
    'tempat_lahir' => mysqli_real_escape_string($conn, $_POST['tempat_lahir']),
    'tanggal_lahir' => mysqli_real_escape_string($conn, $_POST['tanggal_lahir']),
    'jenis_kelamin' => mysqli_real_escape_string($conn, $_POST['jenis_kelamin']),
    'alamat' => mysqli_real_escape_string($conn, $_POST['alamat']),
    'provinsi' => mysqli_real_escape_string($conn, $_POST['provinsi']),
    'kota' => mysqli_real_escape_string($conn, $_POST['kota']),
    'no_telepon' => mysqli_real_escape_string($conn, $_POST['no_telepon']),
    'email' => mysqli_real_escape_string($conn, $_POST['email']),
    'pekerjaan' => mysqli_real_escape_string($conn, $_POST['pekerjaan'] ?? ''),
    'kelas_id' => intval($_POST['kelas_id']),
    'nama_kelas' => mysqli_real_escape_string($conn, $_POST['nama_kelas']),
    'faskes_id' => intval($_POST['faskes_id']),
    'tahun_berlaku' => intval($_POST['tahun_berlaku']),
    'metode_pembayaran' => mysqli_real_escape_string($conn, $_POST['metode_pembayaran']),
    'nama_bank' => mysqli_real_escape_string($conn, $_POST['nama_bank'] ?? ''),
    'no_rekening' => mysqli_real_escape_string($conn, $_POST['no_rekening'] ?? ''),
    'no_kartu_kredit' => mysqli_real_escape_string($conn, $_POST['no_kartu_kredit'] ?? ''),
    'nama_kartu' => mysqli_real_escape_string($conn, $_POST['nama_kartu'] ?? ''),
    'status_pembayaran' => mysqli_real_escape_string($conn, $_POST['status_pembayaran'] ?? 'pending'),
    'iuran_bulanan' => 0,
    'updated_at' => date('Y-m-d H:i:s')
];

// Hitung iuran berdasarkan kelas
switch ($data['kelas_id']) {
    case 1:
        $data['iuran_bulanan'] = 35000;
        break;
    case 2:
        $data['iuran_bulanan'] = 51000;
        break;
    case 3:
        $data['iuran_bulanan'] = 80000;
        break;
}

// Sebelum bagian handle file upload, tambahkan:
if (!is_dir('../uploads')) {
    mkdir('../uploads', 0777, true);
    // Buat file .htaccess untuk keamanan
    $htaccess_content = "Order deny,allow\nDeny from all";
    file_put_contents('../uploads/.htaccess', $htaccess_content);
}
// Handle file upload
if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
    $file = $_FILES['bukti_pembayaran'];
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'bukti_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $upload_path = '../uploads/' . $file_name;
        
        if (!is_dir('../uploads')) {
            mkdir('../uploads', 0777, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $data['bukti_pembayaran'] = $file_name;
        }
    }
}

// Update status jika pembayaran sudah paid
if ($data['status_pembayaran'] == 'paid') {
    // Cek apakah sudah ada nomor BPJS
    $check_sql = "SELECT no_bpjs FROM pendaftaran WHERE id = $pendaftaran_id";
    $check_result = mysqli_query($conn, $check_sql);
    if ($check_result) {
        $row = mysqli_fetch_assoc($check_result);
        if (empty($row['no_bpjs'])) {
            $no_bpjs = 'BPJS-' . str_pad($pendaftaran_id, 10, '0', STR_PAD_LEFT);
            $data['no_bpjs'] = $no_bpjs;
            $data['status'] = 'approved';
        }
    }
}

// Buat query update
$set_clause = [];
foreach ($data as $column => $value) {
    $set_clause[] = "$column = '$value'";
}
$set_string = implode(', ', $set_clause);

$sql = "UPDATE pendaftaran SET $set_string WHERE id = $pendaftaran_id";

if (mysqli_query($conn, $sql)) {
    // Redirect ke detail pendaftaran
    header("Location: pendaftaran.php?mode=view&id=$pendaftaran_id");
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
    echo "<br>SQL: " . $sql;
}
?>