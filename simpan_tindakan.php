<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek jika request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid!";
    header("Location: tindakan.php");
    exit();
}

// Validasi ID
if (!isset($_POST['id']) || empty($_POST['id'])) {
    $_SESSION['error'] = "ID tindakan tidak valid!";
    header("Location: tindakan.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_POST['id']);

// Ambil dan sanitize data
$kode_tindakan = mysqli_real_escape_string($conn, trim($_POST['kode_tindakan']));
$nama_tindakan = mysqli_real_escape_string($conn, trim($_POST['nama_tindakan']));
$deskripsi = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
$kategori = mysqli_real_escape_string($conn, trim($_POST['kategori'] ?? ''));
$jenis_tindakan = mysqli_real_escape_string($conn, trim($_POST['jenis_tindakan'] ?? ''));
$unit = mysqli_real_escape_string($conn, trim($_POST['unit'] ?? ''));
$status = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'aktif'));
$persyaratan = mysqli_real_escape_string($conn, trim($_POST['persyaratan'] ?? ''));
$catatan = mysqli_real_escape_string($conn, trim($_POST['catatan'] ?? ''));
$waktu_estimasi = !empty($_POST['waktu_estimasi']) ? (int)$_POST['waktu_estimasi'] : NULL;

// Validasi jenis_tindakan sesuai ENUM
$jenis_tindakan_allowed = ['Rawat Jalan', 'Rawat Inap', 'IGD', 'Laboratorium', 'Radiologi', 'Fisioterapi'];
if (!in_array($jenis_tindakan, $jenis_tindakan_allowed)) {
    $jenis_tindakan = 'Rawat Jalan'; // default value
}

// Validasi status
$status_allowed = ['aktif', 'tidak aktif'];
if (!in_array($status, $status_allowed)) {
    $status = 'aktif'; // default value
}

// Format tarif (hapus titik dan koma)
$tarif_bpjs_input = trim($_POST['tarif_bpjs'] ?? '0');
$tarif_non_bpjs_input = trim($_POST['tarif_non_bpjs'] ?? '0');

$tarif_bpjs = (float) preg_replace('/[^\d]/', '', $tarif_bpjs_input);
$tarif_non_bpjs = (float) preg_replace('/[^\d]/', '', $tarif_non_bpjs_input);

// Validasi minimal tarif
if ($tarif_bpjs < 10000) {
    $tarif_bpjs = 10000;
}
if ($tarif_non_bpjs < 10000) {
    $tarif_non_bpjs = 10000;
}

// Validasi maksimal tarif
if ($tarif_bpjs > 1000000000) {
    $tarif_bpjs = 1000000000;
}
if ($tarif_non_bpjs > 1000000000) {
    $tarif_non_bpjs = 1000000000;
}

// Simpan data form di session untuk ditampilkan kembali jika error
$_SESSION['form_data'] = [
    'id' => $id,
    'kode_tindakan' => $kode_tindakan,
    'nama_tindakan' => $nama_tindakan,
    'deskripsi' => $deskripsi,
    'kategori' => $kategori,
    'tarif_bpjs' => $tarif_bpjs_input,
    'tarif_non_bpjs' => $tarif_non_bpjs_input,
    'jenis_tindakan' => $jenis_tindakan,
    'unit' => $unit,
    'waktu_estimasi' => $waktu_estimasi,
    'persyaratan' => $persyaratan,
    'status' => $status,
    'catatan' => $catatan
];

// Validasi required fields
$errors = [];
if (empty($kode_tindakan)) {
    $errors[] = "Kode tindakan wajib diisi";
}

if (empty($nama_tindakan)) {
    $errors[] = "Nama tindakan wajib diisi";
}

if (empty($jenis_tindakan)) {
    $errors[] = "Jenis tindakan wajib dipilih";
}

// Cek apakah kode tindakan sudah digunakan oleh record lain
$check_sql = "SELECT id FROM tindakan WHERE kode_tindakan = ? AND id != ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "si", $kode_tindakan, $id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    $errors[] = "Kode tindakan sudah digunakan oleh data lain";
}
mysqli_stmt_close($check_stmt);

// Jika ada error, kembali ke form edit
if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
    header("Location: tindakan_edit.php?id=" . $id);
    exit();
}

// Update data ke database
$sql = "UPDATE tindakan SET 
        kode_tindakan = ?,
        nama_tindakan = ?,
        deskripsi = ?,
        kategori = ?,
        tarif_bpjs = ?,
        tarif_non_bpjs = ?,
        jenis_tindakan = ?,
        unit = ?,
        waktu_estimasi = ?,
        persyaratan = ?,
        status = ?,
        catatan = ?,
        updated_at = NOW()
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssddssisssi", 
    $kode_tindakan,
    $nama_tindakan,
    $deskripsi,
    $kategori,
    $tarif_bpjs,
    $tarif_non_bpjs,
    $jenis_tindakan,
    $unit,
    $waktu_estimasi,
    $persyaratan,
    $status,
    $catatan,
    $id
);

if (mysqli_stmt_execute($stmt)) {
    // Hapus data form dari session
    unset($_SESSION['form_data']);
    
    $_SESSION['success'] = "Data tindakan berhasil diperbarui!";
    header("Location: tindakan_detail.php?id=" . $id);
    exit();
} else {
    // Tangkap error MySQL
    $error_msg = mysqli_error($conn);
    
    // Debug: Log error untuk developer
    error_log("MySQL Error: " . $error_msg);
    
    // Pesan error yang lebih user-friendly
    if (strpos($error_msg, "Data truncated") !== false) {
        $_SESSION['error'] = "Error: Data tidak sesuai format. Pastikan jenis tindakan dipilih dengan benar (Rawat Jalan, Rawat Inap, IGD, Laboratorium, Radiologi, atau Fisioterapi).";
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan data: " . $error_msg;
    }
    
    header("Location: tindakan_edit.php?id=" . $id);
    exit();
}

mysqli_stmt_close($stmt);
?>