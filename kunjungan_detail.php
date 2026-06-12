<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$kunjungan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($kunjungan_id == 0) {
    header("Location: kunjungan.php");
    exit();
}

// Ambil data kunjungan
$sql = "SELECT k.*, 
        p.nama as peserta_nama, p.no_bpjs,
        f.nama as faskes_nama, f.alamat as faskes_alamat,
        d.nama as dokter_nama, d.spesialisasi
        FROM kunjungan k
        LEFT JOIN peserta_bpjs p ON k.peserta_id = p.id
        LEFT JOIN faskes f ON k.faskes_id = f.id
        LEFT JOIN dokter d ON k.dokter_id = d.id
        WHERE k.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $kunjungan_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kunjungan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$kunjungan) {
    header("Location: kunjungan.php");
    exit();
}

// Proses tambah tindakan/obat
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    $jenis_item = mysqli_real_escape_string($conn, $_POST['jenis_item']);
    $item_id = mysqli_real_escape_string($conn, $_POST['item_id']);
    $jumlah = mysqli_real_escape_string($conn, $_POST['jumlah']);
    
    // Ambil harga dari tabel sesuai jenis
    if ($jenis_item == 'tindakan') {
        $sql_price = "SELECT harga FROM tindakan WHERE id = ?";
    } else {
        $sql_price = "SELECT harga FROM obat WHERE id = ?";
    }
    
    $stmt_price = mysqli_prepare($conn, $sql_price);
    mysqli_stmt_bind_param($stmt_price, "i", $item_id);
    mysqli_stmt_execute($stmt_price);
    $result_price = mysqli_stmt_get_result($stmt_price);
    $item_data = mysqli_fetch_assoc($result_price);
    mysqli_stmt_close($stmt_price);
    
    $harga = $item_data['harga'] ?? 0;
    $subtotal = $harga * $jumlah;
    
    // Simpan ke kunjungan_detail
    $sql_insert = "INSERT INTO kunjungan_detail 
                  (kunjungan_id, jenis_item, item_id, jumlah, harga, subtotal)
                  VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "issidd", 
        $kunjungan_id, $jenis_item, $item_id, $jumlah, $harga, $subtotal
    );
    mysqli_stmt_execute($stmt_insert);
    mysqli_stmt_close($stmt_insert);
}

// Ambil detail kunjungan
$sql_detail = "SELECT kd.*, 
              CASE 
                WHEN kd.jenis_item = 'tindakan' THEN t.nama
                WHEN kd.jenis_item = 'obat' THEN o.nama
              END as item_nama
              FROM kunjungan_detail kd
              LEFT JOIN tindakan t ON kd.jenis_item = 'tindakan' AND kd.item_id = t.id
              LEFT JOIN obat o ON kd.jenis_item = 'obat' AND kd.item_id = o.id
              WHERE kd.kunjungan_id = ?
              ORDER BY kd.created_at DESC";
$stmt_detail = mysqli_prepare($conn, $sql_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $kunjungan_id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

// Hitung total
$total_biaya = $kunjungan['biaya_administrasi'];
while ($detail = mysqli_fetch_assoc($result_detail)) {
    $total_biaya += $detail['subtotal'];
}
mysqli_data_seek($result_detail, 0); // Reset pointer

// Ambil data dropdown
$tindakan_list = mysqli_query($conn, "SELECT id, nama, harga FROM tindakan ORDER BY nama");
$obat_list = mysqli_query($conn, "SELECT id, nama, harga FROM obat ORDER BY nama");
?>

<!-- Tampilan HTML untuk detail kunjungan (mirip dengan kunjungan.php, dengan tambahan form detail) -->