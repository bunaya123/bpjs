<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['action']) || $_POST['action'] != 'save') {
    header("Location: pendaftaran.php");
    exit();
}

// Ambil data dari form
$nama = mysqli_real_escape_string($conn, $_POST['nama']);
$nik = mysqli_real_escape_string($conn, $_POST['nik']);
$tempat_lahir = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
$tanggal_lahir = $_POST['tanggal_lahir'];
$jenis_kelamin = $_POST['jenis_kelamin'];
$alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
$provinsi = mysqli_real_escape_string($conn, $_POST['provinsi']);
$kota = mysqli_real_escape_string($conn, $_POST['kota']);
$no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$pekerjaan = isset($_POST['pekerjaan']) ? mysqli_real_escape_string($conn, $_POST['pekerjaan']) : '';

// Data pendaftaran
$kelas_id = $_POST['kelas_id'];
$nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
// Hapus $faskes_id karena tidak ada di tabel peserta
$tahun_berlaku = $_POST['tahun_berlaku'];

// Data pembayaran
$metode_pembayaran = $_POST['metode_pembayaran'];
$nama_bank = isset($_POST['nama_bank']) ? mysqli_real_escape_string($conn, $_POST['nama_bank']) : '';
$no_rekening = isset($_POST['no_rekening']) ? mysqli_real_escape_string($conn, $_POST['no_rekening']) : '';
$no_kartu_kredit = isset($_POST['no_kartu_kredit']) ? mysqli_real_escape_string($conn, $_POST['no_kartu_kredit']) : '';
$nama_kartu = isset($_POST['nama_kartu']) ? mysqli_real_escape_string($conn, $_POST['nama_kartu']) : '';
$status_pembayaran = $_POST['status_pembayaran'];

// Harga berdasarkan kelas (perbaikan sesuai kelas BPJS yang umum)
$harga_kelas = [
    1 => 80000,   // Kelas 1
    2 => 51000,   // Kelas 2
    3 => 35000    // Kelas 3
];

$iuran_bulanan = $harga_kelas[$kelas_id] ?? 35000;
$biaya_admin = 5000;
$ppn = $iuran_bulanan * 0.1;
$total_pembayaran = $iuran_bulanan + $biaya_admin + $ppn;

// Generate nomor pendaftaran
$no_pendaftaran = 'REG' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Generate nomor kartu BPJS (format: 000xxxxxxxxxxx)
$no_kartu = '000' . str_pad(mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);

// Tanggal
$tanggal_daftar = date('Y-m-d');
$tanggal_berlaku = $tahun_berlaku . '-01-01';
$tanggal_expired = $tahun_berlaku . '-12-31';

// Handle upload bukti pembayaran
$bukti_pembayaran = '';
if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $file_extension = strtolower(pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION));
    
    if (in_array($file_extension, $allowed_extensions)) {
        $upload_dir = '../uploads/bukti_pembayaran/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $new_filename = 'BPJS_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $upload_path)) {
            $bukti_pembayaran = 'bukti_pembayaran/' . $new_filename;
        }
    }
}

// Ambil nama faskes dari form
$faskes_nama = mysqli_real_escape_string($conn, $_POST['faskes_nama'] ?? '');

// Debug: Tampilkan data sebelum insert
error_log("Faskes Nama: " . $faskes_nama);
error_log("Nama Kelas: " . $nama_kelas);
error_log("Kelas ID: " . $kelas_id);

// Simpan ke tabel peserta (PERBAIKAN: Hapus faskes_id dari query)
$sql = "INSERT INTO peserta (
    no_kartu, nik, nama, jenis_kelamin, tempat_lahir, tanggal_lahir,
    alamat, provinsi, kota, no_telepon, email, pekerjaan,
    faskes, kelas_bpjs, kelas_id,
    no_pendaftaran, tanggal_daftar, tanggal_berlaku, tanggal_expired,
    metode_pembayaran, nama_bank, no_rekening, no_kartu_kredit, nama_kartu,
    iuran_bulanan, biaya_admin, ppn, total_pembayaran,
    status_pembayaran, bukti_pembayaran, status, created_at, updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW()
)";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

// PERBAIKAN: Sesuaikan jumlah parameter dan tipe data
// Parameter: sssssssssssssissssssssddddsss (27 parameter)
mysqli_stmt_bind_param($stmt, "ssssssssssssssissssssssddddsss",
    $no_kartu, $nik, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir,
    $alamat, $provinsi, $kota, $no_telepon, $email, $pekerjaan,
    $faskes_nama, $nama_kelas, $kelas_id, // Hapus $faskes_id
    $no_pendaftaran, $tanggal_daftar, $tanggal_berlaku, $tanggal_expired,
    $metode_pembayaran, $nama_bank, $no_rekening, $no_kartu_kredit, $nama_kartu,
    $iuran_bulanan, $biaya_admin, $ppn, $total_pembayaran,
    $status_pembayaran, $bukti_pembayaran
);

if (mysqli_stmt_execute($stmt)) {
    $peserta_id = mysqli_insert_id($conn);
    
    // Jika pembayaran sudah verified/paid, aktifkan peserta
    if ($status_pembayaran == 'verified' || $status_pembayaran == 'paid') {
        $sql_update = "UPDATE peserta SET status = 'active' WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "i", $peserta_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }
    
    mysqli_stmt_close($stmt);
    
    // Redirect ke detail
    header("Location: detail_peserta.php?id=" . $peserta_id);
    exit();
} else {
    $error = mysqli_error($conn);
    mysqli_stmt_close($stmt);
    
    // Debug: Tampilkan error dan query
    error_log("SQL Error: " . $error);
    error_log("SQL Query: " . $sql);
    
    // Redirect kembali dengan error
    header("Location: pendaftaran.php?error=" . urlencode("Gagal menyimpan data: " . $error));
    exit();
}
?>