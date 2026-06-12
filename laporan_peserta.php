<?php
session_start();

// CARA PALING AMAN: Gunakan require_once dengan path absolut
$root_dir = dirname(__DIR__); // Naik satu level dari KELAS1
$config_file = $root_dir . '/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
} else {
    // Coba cari di beberapa lokasi yang mungkin
    $possible_paths = [
        dirname(__DIR__) . '/config.php',
        __DIR__ . '/../config.php',
        __DIR__ . '/config.php',
        'C:/laragon/www/projekuas/bpjs/src/config.php'
    ];
    
    $found = false;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        die("Error: File config.php tidak ditemukan.");
    }
}

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user termasuk profile_pic (sama seperti dashboard)
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Cek foto profil
$profile_pic = $user['profile_pic'] ?? '';
$profile_path = 'uploads/profile_pics/' . $profile_pic;
$has_custom_profile = (!empty($profile_pic) && file_exists($profile_path));
$default_avatar = '../assets/images/profile/male/image_1.png';

// PROSES FILTER LAPORAN
$filter_nama = $_GET['nama'] ?? '';
$filter_no_kartu = $_GET['no_kartu'] ?? '';
$filter_nik = $_GET['nik'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_kelas = $_GET['kelas'] ?? '';
$filter_segmen = $_GET['segmen'] ?? '';
$filter_tanggal_mulai = $_GET['tgl_mulai'] ?? '';
$filter_tanggal_selesai = $_GET['tgl_selesai'] ?? '';
$search = $_GET['search'] ?? '';

// Query data peserta dengan filter
$query = "SELECT 
            p.id,
            p.no_kartu,
            p.nik,
            p.nama,
            p.jenis_kelamin,
            p.tempat_lahir,
            p.tanggal_lahir,
            p.alamat,
            p.no_telepon,
            p.email,
            p.faskes,
            p.kelas_bpjs,
            p.status,
            p.tanggal_daftar,
            p.status_pembayaran,
            p.pekerjaan,
            p.provinsi,
            p.kota,
            p.iuran_bulanan,
            p.total_pembayaran,
            COALESCE(p.segmen_peserta, 
                CASE 
                    WHEN p.kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN p.kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) as segmen_peserta,
            COALESCE(p.gaji_dilaporkan, 0) as gaji_dilaporkan,
            p.created_at,
            k.nama_kelas
          FROM peserta p
          LEFT JOIN kelas k ON p.kelas_id = k.id
          WHERE 1=1";

$params = [];
$types = "";

// Filter berdasarkan nama
if (!empty($filter_nama)) {
    $query .= " AND p.nama LIKE ?";
    $params[] = "%$filter_nama%";
    $types .= "s";
}

// Filter berdasarkan nomor kartu
if (!empty($filter_no_kartu)) {
    $query .= " AND p.no_kartu LIKE ?";
    $params[] = "%$filter_no_kartu%";
    $types .= "s";
}

// Filter berdasarkan NIK
if (!empty($filter_nik)) {
    $query .= " AND p.nik LIKE ?";
    $params[] = "%$filter_nik%";
    $types .= "s";
}

// Filter berdasarkan status
if (!empty($filter_status) && $filter_status != 'semua' && $filter_status != 'all') {
    $query .= " AND p.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// Filter berdasarkan kelas
if (!empty($filter_kelas) && $filter_kelas != 'semua' && $filter_kelas != 'all') {
    $query .= " AND p.kelas_bpjs LIKE ?";
    $params[] = "%$filter_kelas%";
    $types .= "s";
}

// Filter berdasarkan segmen
if (!empty($filter_segmen) && $filter_segmen != 'semua' && $filter_segmen != 'all') {
    $query .= " AND COALESCE(p.segmen_peserta, 
                CASE 
                    WHEN p.kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN p.kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = ?";
    $params[] = $filter_segmen;
    $types .= "s";
}

// Filter berdasarkan tanggal daftar
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $query .= " AND DATE(p.tanggal_daftar) BETWEEN ? AND ?";
    $params[] = $filter_tanggal_mulai;
    $params[] = $filter_tanggal_selesai;
    $types .= "ss";
} elseif (!empty($filter_tanggal_mulai)) {
    $query .= " AND DATE(p.tanggal_daftar) >= ?";
    $params[] = $filter_tanggal_mulai;
    $types .= "s";
} elseif (!empty($filter_tanggal_selesai)) {
    $query .= " AND DATE(p.tanggal_daftar) <= ?";
    $params[] = $filter_tanggal_selesai;
    $types .= "s";
}

// Pencarian umum (search)
if (!empty($search)) {
    $query .= " AND (p.nama LIKE ? OR p.no_kartu LIKE ? OR p.nik LIKE ? OR p.no_telepon LIKE ? OR p.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= str_repeat('s', 5);
}

$query .= " ORDER BY p.tanggal_daftar DESC, p.id DESC";

// Eksekusi query
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result_peserta = mysqli_stmt_get_result($stmt);

// Hitung total peserta
$total_peserta = mysqli_num_rows($result_peserta);

// Ambil data kelas untuk filter dropdown
$query_kelas = "SELECT DISTINCT kelas_bpjs FROM peserta WHERE kelas_bpjs IS NOT NULL ORDER BY kelas_bpjs";
$result_kelas = mysqli_query($conn, $query_kelas);
$kelas_list = [];
while ($row = mysqli_fetch_assoc($result_kelas)) {
    $kelas_list[] = $row['kelas_bpjs'];
}

// Ambil data segmen untuk filter dropdown
$query_segmen = "SELECT DISTINCT 
                COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) as segmen 
                FROM peserta 
                WHERE 1=1 
                GROUP BY segmen 
                ORDER BY segmen";
$result_segmen = mysqli_query($conn, $query_segmen);
$segmen_list = [];
while ($row = mysqli_fetch_assoc($result_segmen)) {
    $segmen_list[] = $row['segmen'];
}

// PERBAIKAN BESAR: Query statistik yang diperbaiki - HITUNG SESUAI DATA AKTUAL
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as nonaktif,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status_pembayaran IN ('verified', 'paid') THEN 1 ELSE 0 END) as bayar_verified,
    
    -- Hitung segmen berdasarkan kelas jika segmen_peserta NULL
    SUM(
        CASE 
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PPU' THEN 1 
            ELSE 0 
        END
    ) as ppu,
    
    SUM(
        CASE 
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PBPU' THEN 1 
            ELSE 0 
        END
    ) as pbpu,
    
    SUM(
        CASE 
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PBI' THEN 1 
            ELSE 0 
        END
    ) as pbi,
    
    -- Gaji
    SUM(CASE WHEN COALESCE(gaji_dilaporkan, 0) > 0 THEN 1 ELSE 0 END) as punya_gaji,
    SUM(COALESCE(gaji_dilaporkan, 0)) as total_gaji,
    
    -- Iuran dari database
    SUM(COALESCE(iuran_bulanan, 0)) as total_iuran_database,
    
    -- Perhitungan iuran aktual yang benar
    SUM(
        CASE 
            -- PPU: 5% dari gaji dilaporkan
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 THEN COALESCE(gaji_dilaporkan, 0) * 0.05
            
            -- PBPU: iuran bulanan dari database
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PBPU' THEN COALESCE(iuran_bulanan, 0)
            
            -- PBI: 0
            ELSE 0
        END
    ) as total_iuran_aktual,
    
    -- Iuran yang sudah dibayar (hanya untuk yang status pembayaran verified/paid)
    SUM(
        CASE 
            WHEN status_pembayaran IN ('verified', 'paid') THEN
                CASE 
                    -- PPU: 5% dari gaji
                    WHEN COALESCE(segmen_peserta, 
                        CASE 
                            WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                            WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                            ELSE 'PBI'
                        END
                    ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 THEN COALESCE(gaji_dilaporkan, 0) * 0.05
                    
                    -- PBPU: iuran bulanan
                    WHEN COALESCE(segmen_peserta, 
                        CASE 
                            WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                            WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                            ELSE 'PBI'
                        END
                    ) = 'PBPU' THEN COALESCE(iuran_bulanan, 0)
                    
                    -- PBI: 0
                    ELSE 0
                END
            ELSE 0
        END
    ) as total_iuran_sudah_dibayar,
    
    -- Hitung peserta yang sudah bayar
    COUNT(CASE WHEN status_pembayaran IN ('verified', 'paid') THEN 1 END) as jumlah_peserta_sudah_bayar,
    
    -- Hitung peserta yang wajib bayar (PPU dan PBPU saja, PBI tidak wajib bayar)
    COUNT(
        CASE 
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) IN ('PPU', 'PBPU') THEN 1 
        END
    ) as jumlah_peserta_wajib_bayar
    
    FROM peserta WHERE 1=1";

// Tambahkan kondisi filter ke statistik
$where_stats = [];
if (!empty($filter_nama)) {
    $where_stats[] = "nama LIKE '%" . mysqli_real_escape_string($conn, $filter_nama) . "%'";
}
if (!empty($filter_no_kartu)) {
    $where_stats[] = "no_kartu LIKE '%" . mysqli_real_escape_string($conn, $filter_no_kartu) . "%'";
}
if (!empty($filter_nik)) {
    $where_stats[] = "nik LIKE '%" . mysqli_real_escape_string($conn, $filter_nik) . "%'";
}
if (!empty($filter_status) && $filter_status != 'semua' && $filter_status != 'all') {
    $where_stats[] = "status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
if (!empty($filter_kelas) && $filter_kelas != 'semua' && $filter_kelas != 'all') {
    $where_stats[] = "kelas_bpjs = '" . mysqli_real_escape_string($conn, $filter_kelas) . "'";
}
if (!empty($filter_segmen) && $filter_segmen != 'semua' && $filter_segmen != 'all') {
    $where_stats[] = "COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = '" . mysqli_real_escape_string($conn, $filter_segmen) . "'";
}
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $where_stats[] = "DATE(tanggal_daftar) BETWEEN '" . mysqli_real_escape_string($conn, $filter_tanggal_mulai) . "' AND '" . mysqli_real_escape_string($conn, $filter_tanggal_selesai) . "'";
}
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_stats[] = "(nama LIKE '%$search_escaped%' OR no_kartu LIKE '%$search_escaped%' OR nik LIKE '%$search_escaped%' OR no_telepon LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%')";
}

if (!empty($where_stats)) {
    $sql_stats .= " AND " . implode(" AND ", $where_stats);
}

$result_stats = mysqli_query($conn, $sql_stats);
if (!$result_stats) {
    die("Error dalam query statistik: " . mysqli_error($conn));
}
$stats = mysqli_fetch_assoc($result_stats);

// Format function
function formatRupiah($angka) {
    if ($angka === null || $angka == 0) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Hitung persentase
function hitungPersentase($jumlah, $total) {
    if ($total == 0) return 0;
    return round(($jumlah / $total) * 100, 2);
}

// Hitung rata-rata iuran yang benar
$rata_iuran_aktual = 0;
if (isset($stats['jumlah_peserta_wajib_bayar']) && $stats['jumlah_peserta_wajib_bayar'] > 0) {
    $rata_iuran_aktual = $stats['total_iuran_aktual'] / $stats['jumlah_peserta_wajib_bayar'];
}

// Hitung rata-rata iuran yang sudah dibayar
$rata_iuran_dibayar = 0;
if (isset($stats['jumlah_peserta_sudah_bayar']) && $stats['jumlah_peserta_sudah_bayar'] > 0) {
    $rata_iuran_dibayar = $stats['total_iuran_sudah_dibayar'] / $stats['jumlah_peserta_sudah_bayar'];
}

// DEBUG: Tampilkan data untuk troubleshooting
$debug_mode = false; // Set ke false untuk production
if ($debug_mode) {
    echo "<pre>";
    echo "STATISTIK:\n";
    print_r($stats);
    echo "\n\nTOTAL PESERTA: " . $total_peserta;
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Laporan Peserta BPJS - <?php echo htmlspecialchars($user['username']); ?></title>
    
    <!-- CSS SAMA SEPERTI DASHBOARD -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.css">
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    
    <style>
    /* Style tambahan untuk laporan */
    :root {
        --bpjs-blue: #0066cc;
        --bpjs-blue-light: #0073e6;
        --bpjs-green: #28a745;
        --bpjs-red: #dc3545;
        --bpjs-yellow: #ffc107;
        --bpjs-gray: #6c757d;
    }
    
    .filter-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 4px solid var(--bpjs-blue);
        border-radius: 10px;
    }
    
    .stat-card {
        border-radius: 10px;
        transition: transform 0.3s;
        border: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.08);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .badge-bpjs {
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 12px;
    }
    
    .badge-bpjs-active {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .badge-bpjs-inactive {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .badge-bpjs-pending {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .badge-bpjs-paid {
        background-color: #cce5ff;
        color: #004085;
        border: 1px solid #b8daff;
    }
    
    .badge-bpjs-verified {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .badge-bpjs-segmen-ppu {
        background-color: #e3f2fd;
        color: #1565c0;
        border: 1px solid #bbdefb;
    }
    
    .badge-bpjs-segmen-pbpu {
        background-color: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }
    
    .badge-bpjs-segmen-pbi {
        background-color: #fff3e0;
        color: #ef6c00;
        border: 1px solid #ffe0b2;
    }
    
    .table-bpjs th {
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, #0056b3 100%);
        color: white;
        border-color: #0056b3;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 13px;
    }
    
    .table-bpjs tbody tr:hover {
        background-color: rgba(0, 102, 204, 0.05);
    }
    
    .table-bpjs tbody td {
        vertical-align: middle;
    }
    
    /* FOTO PROFIL SIDEBAR - SAMA DENGAN OBAT.PHP */
    .display-avatar {
        position: relative;
    }
    .avatar-edit-btn {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 28px;
        height: 28px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        background-color: var(--bpjs-blue);
        opacity: 0;
        transition: opacity 0.3s;
    }
    .display-avatar:hover .avatar-edit-btn {
        opacity: 1;
    }
    /* Foto profil yang diperbesar */
    .display-avatar .profile-img.img-lg {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
    }
    
    .export-btn-group .btn {
        border-radius: 20px;
        padding: 8px 20px;
        font-weight: 500;
    }
    
    .print-header {
        display: none;
    }
    
    /* Tombol Aksi */
    .btn-action-group {
        display: flex;
        gap: 5px;
    }
    
    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }
    
    .btn-action-detail {
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, #00a8ff 100%);
        color: white;
        border: none;
    }
    
    .btn-action-edit {
        background: linear-gradient(135deg, var(--bpjs-yellow) 0%, #fd7e14 100%);
        color: white;
        border: none;
    }
    
    .btn-action-history {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }
    
    .btn-action-payment {
        background: linear-gradient(135deg, var(--bpjs-green) 0%, #20c997 100%);
        color: white;
        border: none;
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    /* Gaji dan Iuran info */
    .gaji-info {
        font-size: 11px;
        background: #f8f9fa;
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
        margin-top: 3px;
    }
    
    .gaji-info i {
        font-size: 10px;
    }
    
    /* Iuran badge */
    .iuran-badge {
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 15px;
        display: inline-block;
        min-width: 80px;
        text-align: center;
    }
    
    .iuran-paid {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .iuran-unpaid {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .iuran-partial {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .iuran-pbi {
        background-color: #e2e3e5;
        color: #383d41;
        border: 1px solid #d6d8db;
    }
    
    /* Status Pembayaran di Tabel */
    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
        min-width: 70px;
    }
    
    .status-verified {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .status-paid {
        background-color: #cce5ff;
        color: #004085;
        border: 1px solid #b8daff;
    }
    
    .status-pending {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .status-failed {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    /* Tanda Tangan untuk Print */
    .signature-box {
        margin-top: 50px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }
    
    .signature-line {
        margin-top: 60px;
        border-bottom: 1px solid #333;
        width: 200px;
        display: inline-block;
    }
    
    .print-footer {
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
        font-size: 11px;
        text-align: center;
        color: #666;
    }
    
    @media print {
        .sidebar, .t-header, .no-print, .filter-section, .export-btn-group, .btn-action-group {
            display: none !important;
        }
        
        .print-header {
            display: block !important;
        }
        
        .page-content-wrapper {
            margin-left: 0 !important;
            width: 100% !important;
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .btn-action-group {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }
        
        .table-bpjs th, .table-bpjs td {
            padding: 8px 5px;
            font-size: 12px;
        }
        
        .badge-bpjs {
            padding: 3px 8px;
            font-size: 10px;
        }
        
        .iuran-badge {
            font-size: 10px;
            padding: 3px 6px;
            min-width: 60px;
        }
        
        .status-badge {
            font-size: 9px;
            padding: 2px 5px;
            min-width: 50px;
        }
    }
    
    /* Custom card header */
    .card-header-bpjs {
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, #0056b3 100%);
        color: white;
        border-radius: 10px 10px 0 0 !important;
        padding: 15px 20px;
    }
    
    .card-header-bpjs h5 {
        margin-bottom: 0;
        font-weight: 500;
    }
    
    .card-header-bpjs i {
        margin-right: 10px;
    }
    
    /* Iuran amount */
    .iuran-amount {
        font-weight: 600;
        font-size: 14px;
        color: #28a745;
    }
    
    .iuran-amount.pbi {
        color: #6c757d;
        font-style: italic;
    }
    
    /* Filter row */
    .filter-row {
        background: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #dee2e6;
    }
    
    /* DEBUG Info */
    .debug-info {
        background: #fff3cd;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-size: 12px;
        border-left: 4px solid #ffc107;
    }
    </style>
  </head>
  <body class="header-fixed">
    <div class="page-body">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="user-profile">
                <div class="display-avatar animated-avatar">
                    <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                        <!-- Jika ada foto profil yang diupload -->
                        <img class="profile-img img-lg rounded-circle" 
                             src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                             alt="profile image"
                             onerror="this.style.display='none'; document.getElementById('avatar-default-obat').style.display='block';">
                    <?php endif; ?>
                    
                    <!-- Foto default (akan ditampilkan jika tidak ada custom photo) -->
                    <img id="avatar-default-obat" 
                         class="profile-img img-lg rounded-circle" 
                         src="<?php echo $has_custom_profile ? '' : $default_avatar; ?>" 
                         alt="profile image"
                         style="<?php echo $has_custom_profile ? 'display: none;' : ''; ?>">
                    
                    <!-- Tombol Edit Foto -->
                    <a href="profile.php" 
                       class="btn btn-primary btn-xs rounded-circle avatar-edit-btn" 
                       title="Edit Profile Picture">
                        <i class="mdi mdi-camera" style="font-size: 14px; color: white;"></i>
                    </a>
                </div>
                <div class="info-wrapper">
                    <p class="user-name"><?php echo htmlspecialchars($user['username']); ?></p>
                    <h6 class="display-income">BPJS Member</h6>
                </div>
            </div>
            <ul class="navigation-menu">
                
                <!-- Dashboard Menu -->
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="dashboard.php">
                        <span class="link-title">Dashboard</span>
                        <i class="mdi mdi-gauge link-icon"></i>
                    </a>
                </li>
                
                <!-- MENU DATA MASTER -->
                <li class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['peserta_bpjs.php', 'faskes.php', 'dokter.php', 'obat.php', 'tindakan.php', 'kelas.php']) ? 'active' : ''; ?>">
                    <a href="#data-master" data-toggle="collapse" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['peserta_bpjs.php', 'faskes.php', 'dokter.php', 'obat.php', 'tindakan.php', 'kelas.php']) ? 'true' : 'false'; ?>">
                        <span class="link-title">Data Master</span>
                        <i class="mdi mdi-database link-icon"></i>
                    </a>
                    <ul class="collapse navigation-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['peserta_bpjs.php', 'faskes.php', 'dokter.php', 'obat.php', 'tindakan.php', 'kelas.php']) ? 'show' : ''; ?>" id="data-master">
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'peserta_bpjs.php' ? 'active' : ''; ?>">
                            <a href="peserta_bpjs.php">Data Peserta</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'faskes.php' ? 'active' : ''; ?>">
                            <a href="faskes.php">Data Faskes</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dokter.php' ? 'active' : ''; ?>">
                            <a href="dokter.php">Data Dokter</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'obat.php' ? 'active' : ''; ?>">
                            <a href="obat.php">Data Obat</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'tindakan.php' ? 'active' : ''; ?>">
                            <a href="tindakan.php">Data Tindakan</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kelas.php' ? 'active' : ''; ?>">
                            <a href="kelas.php">Data Kelas</a>
                        </li>
                    </ul>
                </li>
                
                <!-- MENU TRANSAKSI -->
                <li class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['pendaftaran.php', 'pembayaran.php', 'kunjungan.php', 'klaim.php', 'transfer_faskes.php', 'refund.php']) ? 'active' : ''; ?>">
                    <a href="#transaksi" data-toggle="collapse" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['pendaftaran.php', 'pembayaran.php', 'kunjungan.php', 'klaim.php', 'transfer_faskes.php', 'refund.php']) ? 'true' : 'false'; ?>">
                        <span class="link-title">Transaksi</span>
                        <i class="mdi mdi-cash-multiple link-icon"></i>
                    </a>
                    <ul class="collapse navigation-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['pendaftaran.php', 'pembayaran.php', 'kunjungan.php', 'klaim.php', 'transfer_faskes.php', 'refund.php']) ? 'show' : ''; ?>" id="transaksi">
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'pendaftaran.php' ? 'active' : ''; ?>">
                            <a href="pendaftaran.php">Pendaftaran</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'pembayaran.php' ? 'active' : ''; ?>">
                            <a href="pembayaran.php">Pembayaran</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kunjungan.php' ? 'active' : ''; ?>">
                            <a href="kunjungan.php">Kunjungan</a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'klaim.php' ? 'active' : ''; ?>">
                            <a href="klaim.php">Klaim</a>
                        </li>
                    </ul>
                </li>
                
                <!-- MENU LAPORAN -->
                <li class="active">
                    <a href="#laporan" data-toggle="collapse" aria-expanded="true">
                        <span class="link-title">Laporan</span>
                        <i class="mdi mdi-chart-bar link-icon"></i>
                    </a>
                    <ul class="collapse navigation-submenu show" id="laporan">
                        <li class="active">
                            <a href="laporan_peserta.php">Laporan Peserta</a>
                        </li>
                        <li>
                            <a href="laporan_kunjungan.php">Laporan Kunjungan</a>
                        </li>
                        <li>
                            <a href="laporan_klaim.php">Laporan Klaim</a>
                        </li>
                        <li>
                            <a href="laporan_keuangan.php">Laporan Keuangan</a>
                        </li>
                        <li>
                            <a href="laporan_statistik.php">Laporan Statistik</a>
                        </li>
                    </ul>
                </li>
                
                <!-- MENU ACCOUNT SETTINGS -->
                <li class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'ubah_password.php']) ? 'active' : ''; ?>">
                    <a href="#account-settings" data-toggle="collapse" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'ubah_password.php']) ? 'true' : 'false'; ?>">
                        <span class="link-title">Account Settings</span>
                        <i class="mdi mdi-account-cog link-icon"></i>
                    </a>
                    <ul class="collapse navigation-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'ubah_password.php']) ? 'show' : ''; ?>" id="account-settings">
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                            <a href="profile.php">
                                <i class="mdi mdi-account-edit mr-2"></i> Profile & Photo
                            </a>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ubah_password.php' ? 'active' : ''; ?>">
                            <a href="ubah_password.php">
                                <i class="mdi mdi-key-change mr-2"></i> Change Password
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-category-divider">SYSTEM</li>
                <li>
                    <a href="logout.php" class="text-danger">
                        <span class="link-title">Logout</span>
                        <i class="mdi mdi-logout link-icon"></i>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-upgrade-banner">
                <p class="text-gray">BPJS Kesehatan Member</p>
                <a class="btn upgrade-btn" href="pendaftaran.php">Register Now</a>
            </div>
        </div>

        <div class="page-content-wrapper">
            <div class="page-content-wrapper-inner">
                <div class="content-viewport">
            
            <!-- HEADER LAPORAN -->
            <div class="row">
              <div class="col-12 py-4">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="text-dark">
                      <i class="mdi mdi-chart-bar text-primary mr-2"></i>
                      Laporan Data Peserta BPJS
                    </h4>
                    <p class="text-muted mb-0">
                      <i class="mdi mdi-account-multiple mr-1"></i>
                      Total <span class="badge badge-primary"><?php echo $total_peserta; ?></span> peserta ditemukan
                      <?php if (!empty($filter_nama) || !empty($filter_no_kartu) || !empty($filter_status) || !empty($filter_tanggal_mulai) || !empty($search)): ?>
                        <span class="text-muted ml-2">(Hasil Filter)</span>
                      <?php endif; ?>
                    </p>
                  </div>
                  <div class="export-btn-group no-print">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                      <i class="mdi mdi-arrow-left"></i> Kembali
                    </a>
                    <button class="btn btn-outline-primary ml-2" onclick="printLaporan()">
                      <i class="mdi mdi-printer"></i> Cetak
                    </button>
                    <button class="btn btn-success ml-2" onclick="exportToExcel()">
                      <i class="mdi mdi-file-excel"></i> Excel
                    </button>
                
                    </a>
                  </div>
                </div>
              </div>
            </div>
            
            <?php if ($debug_mode): ?>
            <!-- DEBUG INFO -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="debug-info">
                        <strong>DEBUG INFO:</strong><br>
                        Total Peserta: <?php echo $total_peserta; ?><br>
                        Jumlah Sudah Bayar: <?php echo $stats['jumlah_peserta_sudah_bayar'] ?? 0; ?><br>
                        Jumlah Wajib Bayar: <?php echo $stats['jumlah_peserta_wajib_bayar'] ?? 0; ?><br>
                        Total Iuran Aktual: <?php echo formatRupiah($stats['total_iuran_aktual'] ?? 0); ?><br>
                        Total Iuran Sudah Dibayar: <?php echo formatRupiah($stats['total_iuran_sudah_dibayar'] ?? 0); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- STATISTIK YANG DIPERBAIKI -->
            <div class="row mb-4">
              <div class="col-xl-3 col-lg-6">
                <div class="card stat-card border-primary">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="text-muted mb-1">Total Peserta</h6>
                        <h3 class="mb-0 text-primary"><?php echo $stats['total'] ?? 0; ?></h3>
                        <small class="text-muted"><?php echo $total_peserta; ?> data ditemukan</small>
                      </div>
                      <div class="icon-wrapper rounded-circle bg-primary text-white p-3">
                        <i class="mdi mdi-account-multiple mdi-24px"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-lg-6">
                <div class="card stat-card border-success">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="text-muted mb-1">Total Gaji Peserta</h6>
                        <h3 class="mb-0 text-success"><?php echo formatRupiah($stats['total_gaji'] ?? 0); ?></h3>
                        <small class="text-muted">
                          <?php echo $stats['punya_gaji'] ?? 0; ?> peserta melaporkan gaji
                        </small>
                      </div>
                      <div class="icon-wrapper rounded-circle bg-success text-white p-3">
                        <i class="mdi mdi-currency-usd mdi-24px"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-lg-6">
                <div class="card stat-card border-warning">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="text-muted mb-1">Total Iuran Aktual</h6>
                        <h3 class="mb-0 text-warning"><?php echo formatRupiah($stats['total_iuran_aktual'] ?? 0); ?></h3>
                        <small class="text-muted">
                          <?php echo $stats['jumlah_peserta_wajib_bayar'] ?? 0; ?> peserta wajib bayar
                        </small>
                      </div>
                      <div class="icon-wrapper rounded-circle bg-warning text-white p-3">
                        <i class="mdi mdi-cash-multiple mdi-24px"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-xl-3 col-lg-6">
                <div class="card stat-card border-info">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="text-muted mb-1">Iuran Sudah Dibayar</h6>
                        <h3 class="mb-0 text-info">
                          <?php echo formatRupiah($stats['total_iuran_sudah_dibayar'] ?? 0); ?>
                        </h3>
                        <small class="text-muted">
                          <?php echo $stats['jumlah_peserta_sudah_bayar'] ?? 0; ?> peserta sudah bayar
                        </small>
                      </div>
                      <div class="icon-wrapper rounded-circle bg-info text-white p-3">
                        <i class="mdi mdi-chart-bar mdi-24px"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- INFO TAMBAHAN STATISTIK -->
            <div class="row mb-4">
              <div class="col-md-6">
                <div class="alert alert-info">
                  <div class="d-flex align-items-center">
                    <i class="mdi mdi-information-outline mr-3" style="font-size: 24px;"></i>
                    <div>
                      <h6 class="alert-heading mb-1">Informasi Iuran</h6>
                      <p class="mb-0 small">
                        <strong>PPU:</strong> 5% dari gaji dilaporkan<br>
                        <strong>PBPU:</strong> Iuran tetap sesuai kelas<br>
                        <strong>PBI:</strong> Ditanggung pemerintah<br>
                        <strong>Rata-rata iuran:</strong> <?php echo formatRupiah($rata_iuran_aktual); ?> per peserta
                      </p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="alert alert-success">
                  <div class="d-flex align-items-center">
                    <i class="mdi mdi-cash-multiple mr-3" style="font-size: 24px;"></i>
                    <div>
                      <h6 class="alert-heading mb-1">Status Pembayaran</h6>
                      <p class="mb-0">
                        <strong>Total Iuran yang Harus Dibayar:</strong> <?php echo formatRupiah($stats['total_iuran_aktual'] ?? 0); ?><br>
                        <strong>Total Iuran yang Sudah Dibayar:</strong> <?php echo formatRupiah($stats['total_iuran_sudah_dibayar'] ?? 0); ?><br>
                        <strong>Selisih:</strong> <?php echo formatRupiah(($stats['total_iuran_aktual'] ?? 0) - ($stats['total_iuran_sudah_dibayar'] ?? 0)); ?><br>
                        <strong>Persentase Pembayaran:</strong> <?php echo hitungPersentase($stats['jumlah_peserta_sudah_bayar'] ?? 0, $stats['jumlah_peserta_wajib_bayar'] ?? 0); ?>%
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- FILTER SECTION -->
            <div class="row mb-4">
              <div class="col-12">
                <div class="card filter-card">
                  <div class="card-body">
                    <h5 class="card-title text-dark mb-3">
                      <i class="mdi mdi-filter-outline mr-2"></i>
                      Filter Laporan
                    </h5>
                    <form method="GET" action="" class="filter-form">
                      <div class="row">
                        <div class="col-md-4">
                          <div class="form-group">
                            <label for="search" class="form-label small font-weight-bold">Pencarian Umum</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Cari nama, NIK, no kartu, telepon, email...">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group">
                            <label for="nama" class="form-label small font-weight-bold">Nama Peserta</label>
                            <input type="text" class="form-control" id="nama" name="nama" 
                                   value="<?php echo htmlspecialchars($filter_nama); ?>" 
                                   placeholder="Nama peserta...">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group">
                            <label for="no_kartu" class="form-label small font-weight-bold">No. Kartu</label>
                            <input type="text" class="form-control" id="no_kartu" name="no_kartu"
                                   value="<?php echo htmlspecialchars($filter_no_kartu); ?>"
                                   placeholder="No. Kartu">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group">
                            <label for="nik" class="form-label small font-weight-bold">NIK</label>
                            <input type="text" class="form-control" id="nik" name="nik"
                                   value="<?php echo htmlspecialchars($filter_nik); ?>"
                                   placeholder="NIK">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group">
                            <label for="status" class="form-label small font-weight-bold">Status</label>
                            <select class="form-control" id="status" name="status">
                              <option value="semua">Semua Status</option>
                              <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Aktif</option>
                              <option value="inactive" <?php echo ($filter_status == 'inactive') ? 'selected' : ''; ?>>Tidak Aktif</option>
                              <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Menunggu</option>
                            </select>
                          </div>
                        </div>
                      </div>
                      
                      <div class="row mt-3">
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="kelas" class="form-label small font-weight-bold">Kelas</label>
                            <select class="form-control" id="kelas" name="kelas">
                              <option value="semua">Semua Kelas</option>
                              <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?php echo htmlspecialchars($kelas); ?>" 
                                  <?php echo ($filter_kelas == $kelas) ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($kelas); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="segmen" class="form-label small font-weight-bold">Segmen</label>
                            <select class="form-control" id="segmen" name="segmen">
                              <option value="semua">Semua Segmen</option>
                              <?php foreach ($segmen_list as $segmen): ?>
                                <option value="<?php echo htmlspecialchars($segmen); ?>" 
                                  <?php echo ($filter_segmen == $segmen) ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($segmen); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="tgl_mulai" class="form-label small font-weight-bold">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="tgl_mulai" name="tgl_mulai"
                                   value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>">
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="tgl_selesai" class="form-label small font-weight-bold">Tanggal Selesai</label>
                            <input type="date" class="form-control" id="tgl_selesai" name="tgl_selesai"
                                   value="<?php echo htmlspecialchars($filter_tanggal_selesai); ?>">
                          </div>
                        </div>
                      </div>
                      
                      <div class="row mt-3">
                        <div class="col-md-12 d-flex align-items-end">
                          <div>
                            <button type="submit" class="btn btn-primary">
                              <i class="mdi mdi-filter"></i> Terapkan Filter
                            </button>
                            <a href="laporan_peserta.php" class="btn btn-secondary">
                              <i class="mdi mdi-refresh"></i> Reset
                            </a>
                            </a>
                            <a href="pendaftaran.php" class="btn btn-info ml-2">
                              <i class="mdi mdi-account-plus"></i> Pendaftaran Baru
                            </a>
                          </div>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- TABEL LAPORAN -->
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-header card-header-bpjs">
                    <h5 class="mb-0">
                      <i class="mdi mdi-table mr-2"></i>
                      Daftar Peserta BPJS - Informasi Iuran
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div>
                        <span class="badge badge-light">
                          <i class="mdi mdi-database mr-1"></i>
                          <?php echo $total_peserta; ?> Data ditemukan
                        </span>
                        <?php if ($total_peserta > 0): ?>
                        <span class="badge badge-primary ml-2">
                          <i class="mdi mdi-account mr-1"></i>
                          PPU: <?php echo $stats['ppu'] ?? 0; ?>
                        </span>
                        <span class="badge badge-success ml-2">
                          <i class="mdi mdi-account-tie mr-1"></i>
                          PBPU: <?php echo $stats['pbpu'] ?? 0; ?>
                        </span>
                        <span class="badge badge-warning ml-2">
                          <i class="mdi mdi-account-heart mr-1"></i>
                          PBI: <?php echo $stats['pbi'] ?? 0; ?>
                        </span>
                        <?php endif; ?>
                      </div>
                      <div>
                        <a href="peserta_bpjs.php" class="btn btn-outline-primary btn-sm no-print">
                          <i class="mdi mdi-arrow-left mr-1"></i> Kembali ke Data Master
                        </a>
                      </div>
                    </div>
                    
                    <?php if ($total_peserta > 0): ?>
                      <div class="table-responsive" id="table-laporan">
                        <table class="table table-bordered table-hover table-bpjs">
                          <thead>
                            <tr>
                              <th width="50">#</th>
                              <th>Nama Peserta</th>
                              <th>NIK</th>
                              <th>No. Kartu</th>
                              <th>Faskes</th>
                              <th width="80">Kelas</th>
                              <th width="100">Status</th>
                              <th width="100">Segmen</th>
                              <th width="150">Iuran Bulanan</th>
                              <th width="120">Status Pembayaran</th>
                              <th width="180" class="text-center no-print">Aksi</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php 
                            $no = 1; 
                            mysqli_data_seek($result_peserta, 0); // Reset pointer
                            $total_iuran_aktual = 0;
                            $total_iuran_dibayar = 0;
                            ?>
                            <?php while ($peserta = mysqli_fetch_assoc($result_peserta)): 
                              // Tentukan segmen (dengan fallback ke kelas)
                              $segmen = $peserta['segmen_peserta'] ?? 
                                        ($peserta['kelas_bpjs'] == 'Kelas 1' ? 'PPU' : 
                                         ($peserta['kelas_bpjs'] == 'Kelas 2' ? 'PBPU' : 'PBI'));
                              $gaji = $peserta['gaji_dilaporkan'] ?? 0;
                              
                              // Tentukan iuran berdasarkan segmen
                              if ($segmen == 'PPU') {
                                  $iuran = $gaji * 0.05; // 5% dari gaji
                              } elseif ($segmen == 'PBPU') {
                                  $iuran = $peserta['iuran_bulanan'] ?? 51000; // Iuran tetap (default 51,000)
                              } else {
                                  $iuran = 0; // PBI - iuran ditanggung pemerintah
                              }
                              
                              // Tentukan status iuran berdasarkan status_pembayaran
                              $status_pembayaran = $peserta['status_pembayaran'] ?? 'pending';
                              $iuran_status = '';
                              $iuran_status_class = '';
                              
                              // Tampilkan status pembayaran
                              $status_pembayaran_text = '';
                              $status_pembayaran_class = '';
                              switch($status_pembayaran) {
                                  case 'verified':
                                  case 'paid':
                                      $status_pembayaran_text = 'Lunas';
                                      $status_pembayaran_class = 'status-verified';
                                      $iuran_status = 'Lunas';
                                      $iuran_status_class = 'iuran-paid';
                                      $total_iuran_dibayar += $iuran;
                                      break;
                                  case 'partial':
                                      $status_pembayaran_text = 'Sebagian';
                                      $status_pembayaran_class = 'status-pending';
                                      $iuran_status = 'Sebagian';
                                      $iuran_status_class = 'iuran-partial';
                                      $total_iuran_dibayar += ($iuran * 0.5); // asumsi setengah
                                      break;
                                  case 'failed':
                                      $status_pembayaran_text = 'Gagal';
                                      $status_pembayaran_class = 'status-failed';
                                      $iuran_status = 'Gagal';
                                      $iuran_status_class = 'iuran-unpaid';
                                      break;
                                  default:
                                      $status_pembayaran_text = 'Belum Bayar';
                                      $status_pembayaran_class = 'status-pending';
                                      $iuran_status = 'Belum Bayar';
                                      $iuran_status_class = 'iuran-unpaid';
                                      break;
                              }
                              
                              if ($segmen == 'PBI') {
                                  $iuran_status = 'Ditanggung Pemerintah';
                                  $iuran_status_class = 'iuran-pbi';
                                  $status_pembayaran_text = 'Ditanggung';
                                  $status_pembayaran_class = 'status-verified';
                              }
                              
                              $total_iuran_aktual += $iuran;
                            ?>
                              <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td>
                                  <div class="d-flex align-items-center">
                                    <div>
                                      <strong><?php echo htmlspecialchars($peserta['nama']); ?></strong>
                                      <?php if ($peserta['jenis_kelamin'] == 'L'): ?>
                                        <br><small class="text-muted"><i class="mdi mdi-gender-male mr-1"></i> Laki-laki</small>
                                      <?php else: ?>
                                        <br><small class="text-muted"><i class="mdi mdi-gender-female mr-1"></i> Perempuan</small>
                                      <?php endif; ?>
                                      <?php if ($peserta['tanggal_lahir']): ?>
                                        <br><small class="text-muted"><?php echo date('d/m/Y', strtotime($peserta['tanggal_lahir'])); ?></small>
                                      <?php endif; ?>
                                      <?php if ($peserta['pekerjaan']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($peserta['pekerjaan']); ?></small>
                                      <?php endif; ?>
                                      <?php if ($gaji > 0 && $segmen == 'PPU'): ?>
                                        <br><span class="gaji-info">
                                          <i class="mdi mdi-currency-usd mr-1"></i>
                                          Gaji: <?php echo formatRupiah($gaji); ?>
                                        </span>
                                      <?php endif; ?>
                                    </div>
                                  </div>
                                </td>
                                <td><?php echo htmlspecialchars($peserta['nik']); ?></td>
                                <td>
                                  <span class="font-weight-bold text-primary">
                                    <?php echo htmlspecialchars($peserta['no_kartu']); ?>
                                  </span>
                                </td>
                                <td>
                                  <small><?php echo htmlspecialchars($peserta['faskes'] ?? '-'); ?></small>
                                </td>
                                <td class="text-center">
                                  <span class="badge badge-light border">
                                    <?php echo htmlspecialchars($peserta['kelas_bpjs'] ?? '-'); ?>
                                  </span>
                                </td>
                                <td class="text-center">
                                  <?php 
                                  $status_class = '';
                                  $status_text = '';
                                  switch($peserta['status']) {
                                    case 'active':
                                      $status_class = 'badge-bpjs-active';
                                      $status_text = 'Aktif';
                                      break;
                                    case 'inactive':
                                      $status_class = 'badge-bpjs-inactive';
                                      $status_text = 'Non-Aktif';
                                      break;
                                    case 'pending':
                                      $status_class = 'badge-bpjs-pending';
                                      $status_text = 'Menunggu';
                                      break;
                                  }
                                  ?>
                                  <span class="badge badge-bpjs <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                  </span>
                                </td>
                                <td class="text-center">
                                  <?php 
                                  $segmen_class = '';
                                  switch($segmen) {
                                    case 'PPU':
                                      $segmen_class = 'badge-bpjs-segmen-ppu';
                                      break;
                                    case 'PBPU':
                                      $segmen_class = 'badge-bpjs-segmen-pbpu';
                                      break;
                                    case 'PBI':
                                    default:
                                      $segmen_class = 'badge-bpjs-segmen-pbi';
                                      break;
                                  }
                                  ?>
                                  <span class="badge badge-bpjs <?php echo $segmen_class; ?>">
                                    <?php echo htmlspecialchars($segmen); ?>
                                  </span>
                                </td>
                                <td class="text-center">
                                  <?php if ($segmen == 'PBI'): ?>
                                    <span class="iuran-badge iuran-pbi">
                                      <i class="mdi mdi-shield-check mr-1"></i>
                                      Ditanggung Pemerintah
                                    </span>
                                  <?php else: ?>
                                    <div>
                                      <div class="iuran-amount <?php echo ($segmen == 'PBI') ? 'pbi' : ''; ?>">
                                        <?php echo formatRupiah($iuran); ?>
                                      </div>
                                      <div class="mt-1">
                                        <span class="iuran-badge <?php echo $iuran_status_class; ?>">
                                          <?php if ($iuran_status_class == 'iuran-paid'): ?>
                                            <i class="mdi mdi-check-circle mr-1"></i>
                                          <?php elseif ($iuran_status_class == 'iuran-unpaid'): ?>
                                            <i class="mdi mdi-alert-circle mr-1"></i>
                                          <?php elseif ($iuran_status_class == 'iuran-partial'): ?>
                                            <i class="mdi mdi-progress-clock mr-1"></i>
                                          <?php endif; ?>
                                          <?php echo $iuran_status; ?>
                                        </span>
                                      </div>
                                    </div>
                                  <?php endif; ?>
                                </td>
                                <td class="text-center">
                                  <span class="status-badge <?php echo $status_pembayaran_class; ?>">
                                    <?php if ($status_pembayaran_class == 'status-verified'): ?>
                                      <i class="mdi mdi-check-circle mr-1"></i>
                                    <?php elseif ($status_pembayaran_class == 'status-pending'): ?>
                                      <i class="mdi mdi-clock mr-1"></i>
                                    <?php elseif ($status_pembayaran_class == 'status-failed'): ?>
                                      <i class="mdi mdi-close-circle mr-1"></i>
                                    <?php endif; ?>
                                    <?php echo $status_pembayaran_text; ?>
                                  </span>
                                </td>
                                <td class="text-center no-print">
                                  <div class="btn-action-group">
                                    <!-- Tombol Detail -->
                                    <a href="detail_peserta.php?id=<?php echo $peserta['id']; ?>" 
                                       class="btn btn-action btn-action-detail" 
                                       title="Detail Peserta"
                                       data-toggle="tooltip">
                                      <i class="mdi mdi-eye"></i>
                                    </a>
                                    
                                    <!-- Tombol Edit/Update -->
                                    <a href="edit_peserta.php?id=<?php echo $peserta['id']; ?>" 
                                       class="btn btn-action btn-action-edit" 
                                       title="Edit/Update Data"
                                       data-toggle="tooltip">
                                      <i class="mdi mdi-pencil"></i>
                                    </a>
                                    
                                    <!-- Tombol Tambah Pembayaran -->
                                    <a href="tambah_pembayaran.php?peserta_id=<?php echo $peserta['id']; ?>" 
                                       class="btn btn-action btn-action-payment" 
                                       title="Tambah Pembayaran"
                                       data-toggle="tooltip">
                                      <i class="mdi mdi-plus-circle"></i>
                                    </a>
                                  </div>
                                </td>
                              </tr>
                            <?php endwhile; ?>
                          </tbody>
                          <tfoot>
                            <tr style="background-color: #f8f9fa;">
                              <td colspan="8" class="text-right font-weight-bold">Total Iuran Aktual (Semua Peserta):</td>
                              <td class="text-center font-weight-bold text-primary">
                                <?php echo formatRupiah($total_iuran_aktual); ?>
                              </td>
                              <td class="text-center font-weight-bold">
                                <span class="badge badge-light">Total</span>
                              </td>
                              <td></td>
                            </tr>
                            <tr style="background-color: #f8f9fa;">
                              <td colspan="8" class="text-right font-weight-bold">Total Iuran Sudah Dibayar:</td>
                              <td class="text-center font-weight-bold text-success">
                                <?php echo formatRupiah($total_iuran_dibayar); ?>
                              </td>
                              <td class="text-center font-weight-bold">
                                <span class="badge badge-light">Dibayar</span>
                              </td>
                              <td></td>
                            </tr>
                            <tr style="background-color: #e3f2fd;">
                              <td colspan="8" class="text-right font-weight-bold">Selisih (Belum Dibayar):</td>
                              <td class="text-center font-weight-bold text-danger">
                                <?php echo formatRupiah($total_iuran_aktual - $total_iuran_dibayar); ?>
                              </td>
                              <td class="text-center font-weight-bold">
                                <span class="badge badge-danger">Kekurangan</span>
                              </td>
                              <td></td>
                            </tr>
                          </tfoot>
                        </table>
                      </div>
                      
                    <?php else: ?>
                      <div class="text-center py-5">
                        <i class="mdi mdi-database-remove mdi-5x text-muted"></i>
                        <h5 class="mt-3 text-muted">Tidak ada data peserta ditemukan</h5>
                        <p class="text-muted">Coba gunakan filter yang berbeda atau tambah data peserta baru</p>
                        <div class="mt-3">
                          <a href="laporan_peserta.php" class="btn btn-primary">
                            <i class="mdi mdi-refresh"></i> Reset Filter
                          </a>
                          <a href="tambah_peserta.php" class="btn btn-success ml-2">
                            <i class="mdi mdi-plus"></i> Tambah Peserta Baru
                          </a>
                          <a href="pendaftaran.php" class="btn btn-info ml-2">
                            <i class="mdi mdi-account-plus"></i> Pendaftaran Baru
                          </a>
                          <a href="peserta_bpjs.php" class="btn btn-outline-primary ml-2">
                            <i class="mdi mdi-database"></i> Data Master Peserta
                          </a>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- FOOTER -->
            <footer class="footer mt-4">
              <div class="row">
                <div class="col-sm-6 text-center text-sm-left mt-3 mt-sm-0">
                  <small class="text-muted d-block">
                    <i class="mdi mdi-calendar mr-1"></i>
                    Laporan Peserta BPJS &copy; <?php echo date('Y'); ?>
                  </small>
                  <small class="text-gray mt-2">
                    <i class="mdi mdi-clock-outline mr-1"></i>
                    Dicetak: <?php echo date('d-m-Y H:i:s'); ?>
                  </small>
                  <?php if ($total_peserta > 0): ?>
                  <small class="text-gray d-block mt-1">
                    <i class="mdi mdi-cash mr-1"></i>
                    Total Iuran Aktual: <strong><?php echo formatRupiah($stats['total_iuran_aktual'] ?? 0); ?></strong> per bulan
                  </small>
                  <small class="text-gray d-block mt-1">
                    <i class="mdi mdi-cash-check mr-1"></i>
                    Sudah Dibayar: <strong><?php echo formatRupiah($stats['total_iuran_sudah_dibayar'] ?? 0); ?></strong>
                  </small>
                  <?php endif; ?>
                </div>
                <div class="col-sm-6 text-center text-sm-right order-sm-1">
                  <ul class="text-gray">
                  </ul>
                </div>
              </div>
            </footer>
            
          </div>
        </div>
      </div>
    </div>
    
    <!-- HEADER UNTUK PRINT -->
    <div class="print-header" id="print-header" style="display: none;">
      <div style="text-align: center; margin-bottom: 30px;">
        <!-- Logo dan Header BPJS -->
        <table style="width: 100%; margin-bottom: 20px;">
          <tr>
            <td style="width: 20%; text-align: left; vertical-align: top;">
              <!-- Logo BPJS -->
              <div style="text-align: center;">
                <div style="background: #0066cc; color: white; padding: 10px; border-radius: 5px; display: inline-block;">
                  <strong style="font-size: 16px;">BPJS</strong><br>
                  <span style="font-size: 12px;">KESEHATAN</span>
                </div>
              </div>
            </td>
            <td style="width: 60%; text-align: center; vertical-align: top;">
              <h2 style="margin: 0; color: #0066cc; font-weight: bold;">BADAN PENYELENGGARA JAMINAN SOSIAL</h2>
              <h3 style="margin: 5px 0 10px 0; color: #333;">LAPORAN DATA PESERTA BPJS KESEHATAN</h3>
              <p style="margin: 0; font-size: 12px;">
                <strong>Alamat Kantor:</strong> Jl. Letjen Sutoyo No. 79, Cililitan, Jakarta Timur 13640<br>
                <strong>Telp:</strong> (021) 1500-400 | <strong>Email:</strong> contact@bpjs-kesehatan.go.id<br>
                <strong>Website:</strong> www.bpjs-kesehatan.go.id
              </p>
            </td>
            <td style="width: 20%; text-align: right; vertical-align: top;">
              <!-- Logo Republik -->
              <div style="text-align: center;">
                <div style="background: #ff0000; color: white; padding: 10px; border-radius: 5px; display: inline-block;">
                  <strong style="font-size: 16px;">REP</strong><br>
                  <span style="font-size: 12px;">INDONESIA</span>
                </div>
              </div>
            </td>
          </tr>
        </table>
        
        <hr style="border: 2px solid #0066cc; margin: 10px 0;">
        
        <!-- Informasi Laporan -->
        <div style="text-align: left; font-size: 12px; margin-bottom: 20px;">
          <table style="width: 100%; border-collapse: collapse;">
            <tr>
              <td style="width: 50%; vertical-align: top;">
                <strong>INFORMASI LAPORAN</strong><br>
                <strong>Periode:</strong> 
                <?php 
                if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
                  echo date('d F Y', strtotime($filter_tanggal_mulai)) . ' s/d ' . date('d F Y', strtotime($filter_tanggal_selesai));
                } else {
                  echo 'Semua Periode';
                }
                ?><br>
                <strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i:s'); ?><br>
                <strong>Jumlah Data:</strong> <?php echo $total_peserta; ?> Peserta<br>
                <strong>Status Filter:</strong> 
                <?php 
                $filter_active = [];
                if (!empty($filter_nama)) $filter_active[] = "Nama: $filter_nama";
                if (!empty($filter_no_kartu)) $filter_active[] = "No Kartu: $filter_no_kartu";
                if (!empty($filter_status) && $filter_status != 'semua') $filter_active[] = "Status: $filter_status";
                if (!empty($filter_kelas) && $filter_kelas != 'semua') $filter_active[] = "Kelas: $filter_kelas";
                if (!empty($filter_segmen) && $filter_segmen != 'semua') $filter_active[] = "Segmen: $filter_segmen";
                
                echo empty($filter_active) ? 'Semua Data' : implode(', ', $filter_active);
                ?>
              </td>
              <td style="width: 50%; vertical-align: top;">
                <strong>STATISTIK KEUANGAN</strong><br>
                <strong>Total Iuran Aktual:</strong> <?php echo formatRupiah($stats['total_iuran_aktual'] ?? 0); ?><br>
                <strong>Iuran Sudah Dibayar:</strong> <?php echo formatRupiah($stats['total_iuran_sudah_dibayar'] ?? 0); ?><br>
                <strong>Kekurangan:</strong> <?php echo formatRupiah(($stats['total_iuran_aktual'] ?? 0) - ($stats['total_iuran_sudah_dibayar'] ?? 0)); ?><br>
                <strong>Persentase Bayar:</strong> 
                <?php 
                if (($stats['jumlah_peserta_wajib_bayar'] ?? 0) > 0) {
                  echo round((($stats['jumlah_peserta_sudah_bayar'] ?? 0) / ($stats['jumlah_peserta_wajib_bayar'] ?? 0)) * 100, 2) . '%';
                } else {
                  echo '0%';
                }
                ?><br>
                <strong>Peserta Aktif:</strong> <?php echo $stats['aktif'] ?? 0; ?> | 
                <strong>Non-Aktif:</strong> <?php echo $stats['nonaktif'] ?? 0; ?>
              </td>
            </tr>
          </table>
        </div>
        
        <hr style="border: 1px solid #ddd; margin: 10px 0;">
      </div>
    </div>
    
    <!-- SCRIPT -->
    <script src="../assets/vendors/js/core.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
      // Fungsi export ke Excel
      function exportToExcel() {
        try {
          // Clone table tanpa kolom aksi
          const originalTable = document.getElementById('table-laporan').getElementsByTagName('table')[0];
          const tableClone = originalTable.cloneNode(true);
          
          // Hapus kolom aksi
          const rows = tableClone.getElementsByTagName('tr');
          for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            if (cells.length > 0 && cells[cells.length - 1].classList.contains('no-print')) {
              cells[cells.length - 1].remove();
            }
            const headers = rows[i].getElementsByTagName('th');
            if (headers.length > 0 && headers[headers.length - 1].classList.contains('no-print')) {
              headers[headers.length - 1].remove();
            }
          }
          
          const ws = XLSX.utils.table_to_sheet(tableClone);
          const wb = XLSX.utils.book_new();
          XLSX.utils.book_append_sheet(wb, ws, "Laporan Peserta");
          
          const fileName = `Laporan_Peserta_BPJS_<?php echo date('Y-m-d'); ?>.xlsx`;
          XLSX.writeFile(wb, fileName);
          
          alert('Laporan berhasil diekspor ke Excel!');
        } catch (error) {
          alert('Error saat mengekspor ke Excel: ' + error.message);
          console.error(error);
        }
      }
      
      // Fungsi print laporan
      function printLaporan() {
        const printContents = document.getElementById('table-laporan').innerHTML;
        const originalContents = document.body.innerHTML;
        const printHeader = document.getElementById('print-header').innerHTML;
        
        // Clone table untuk print
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = printContents;
        const table = tempDiv.getElementsByTagName('table')[0];
        
        // Hapus kolom aksi dari tabel print
        const rows = table.getElementsByTagName('tr');
        for (let i = 0; i < rows.length; i++) {
          const cells = rows[i].getElementsByTagName('td');
          if (cells.length > 0 && cells[cells.length - 1].classList.contains('no-print')) {
            cells[cells.length - 1].remove();
          }
          const headers = rows[i].getElementsByTagName('th');
          if (headers.length > 0 && headers[headers.length - 1].classList.contains('no-print')) {
            headers[headers.length - 1].remove();
          }
        }
        
        // Dapatkan nama user untuk tanda tangan (ambil dari user yang login)
        const userName = "<?php echo $user['full_name'] ? htmlspecialchars($user['full_name']) : htmlspecialchars($user['username']); ?>";
        const userPosition = "<?php echo $user['username'] == 'admin' ? 'Administrator' : 'Petugas BPJS'; ?>";
        
        // Tambahkan tanda tangan dengan nama user
        const signatureSection = `
          <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
            <table style="width: 100%;">
              <tr>
                <td style="width: 70%;">
                  <p style="font-size: 11px; color: #666;">
                    <strong>Keterangan:</strong><br>
                    1. Laporan ini dicetak secara otomatis dari Sistem Informasi BPJS Kesehatan<br>
                    2. Data diambil dari database yang diperbarui hingga <?php echo date('d F Y H:i:s'); ?><br>
                    3. Laporan ini sah dan dapat dipertanggungjawabkan<br>
                    4. Untuk informasi lebih lanjut hubungi call center 1500-400
                  </p>
                </td>
                <td style="width: 30%; text-align: center;">
                  <div style="margin-top: 60px;">
                    <p>Jakarta, <?php echo date('d F Y'); ?></p>
                    <p>Petugas yang bertanggung jawab,</p>
                    <br><br><br><br>
                    <p><strong><u>${userName}</u></strong></p>
                    <p>${userPosition}</p>
                    <p>ID: <?php echo htmlspecialchars($user['id']); ?></p>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          
          <div class="print-footer" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 11px; text-align: center; color: #666;">
            <p>Laporan ini dicetak secara otomatis dari Sistem Informasi BPJS Kesehatan</p>
            <p>&copy; <?php echo date('Y'); ?> BPJS Kesehatan. Hak Cipta Dilindungi Undang-Undang.</p>
            <p>Dicetak oleh: <?php echo htmlspecialchars($user['username']); ?> | User ID: <?php echo $user_id; ?></p>
          </div>
        `;
        
        document.body.innerHTML = 
          '<html><head><title>Laporan Peserta BPJS</title>' +
          '<style>' +
          'body {padding: 20px; font-family: "Arial", "Helvetica", sans-serif;}' +
          'table {width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px;}' +
          'th {background-color: #0066cc; color: white; padding: 8px; text-align: center; border: 1px solid #0056b3; font-weight: bold;}' +
          'td {padding: 6px; border: 1px solid #ddd; vertical-align: middle; text-align: center;}' +
          '.badge {padding: 2px 6px; border-radius: 8px; font-size: 9px; display: inline-block;}' +
          '.badge-bpjs-active {background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}' +
          '.badge-bpjs-inactive {background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}' +
          '.badge-bpjs-pending {background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7;}' +
          '.badge-bpjs-segmen-ppu {background-color: #e3f2fd; color: #1565c0;}' +
          '.badge-bpjs-segmen-pbpu {background-color: #e8f5e9; color: #2e7d32;}' +
          '.badge-bpjs-segmen-pbi {background-color: #fff3e0; color: #ef6c00;}' +
          '.iuran-badge {font-size: 9px; padding: 1px 5px; border-radius: 8px; margin-top: 1px;}' +
          '.iuran-paid {background-color: #d4edda; color: #155724;}' +
          '.iuran-unpaid {background-color: #f8d7da; color: #721c24;}' +
          '.iuran-partial {background-color: #fff3cd; color: #856404;}' +
          '.iuran-pbi {background-color: #e2e3e5; color: #383d41;}' +
          '.iuran-amount {font-weight: 600; color: #28a745; font-size: 10px;}' +
          '.iuran-amount.pbi {color: #6c757d; font-style: italic;}' +
          '.status-badge {font-size: 9px; padding: 1px 5px; border-radius: 8px;}' +
          '.status-verified {background-color: #d4edda; color: #155724;}' +
          '.status-pending {background-color: #fff3cd; color: #856404;}' +
          '.status-failed {background-color: #f8d7da; color: #721c24;}' +
          '.print-header h2, .print-header h3 {font-family: "Arial", sans-serif;}' +
          '.print-header p {margin: 3px 0;}' +
          '@media print {' +
          '  @page { margin: 0.5cm; }' +
          '  body { padding: 10px; }' +
          '}' +
          '</style>' +
          '</head><body>' +
          printHeader +
          table.outerHTML +
          signatureSection +
          '</body></html>';
        
        window.print();
        setTimeout(function() {
          document.body.innerHTML = originalContents;
          window.location.reload();
        }, 100);
      }
      
      $(document).ready(function() {
        // Auto submit form saat enter
        const searchFields = ['search', 'nama', 'no_kartu', 'nik'];
        searchFields.forEach(field => {
          const input = document.getElementById(field);
          if (input) {
            input.addEventListener('keypress', function(e) {
              if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('.filter-form').submit();
              }
            });
          }
        });
        
        // Set tanggal default
        const tglMulai = document.getElementById('tgl_mulai');
        const tglSelesai = document.getElementById('tgl_selesai');
        
        if (tglMulai && !tglMulai.value) {
          const today = new Date();
          const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
          tglMulai.value = firstDay.toISOString().split('T')[0];
        }
        
        if (tglSelesai && !tglSelesai.value) {
          const today = new Date();
          const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
          tglSelesai.value = lastDay.toISOString().split('T')[0];
        }
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip({
          trigger: 'hover',
          placement: 'top'
        });
        
        // Set active menu
        $('.navigation-menu li').removeClass('active');
        $('.navigation-menu li a[href="laporan_peserta.php"]').parent().addClass('active');
        $('.navigation-menu li a[href="#laporan"]').parent().addClass('active');
        $('#laporan').addClass('show');
      });
    </script>
  </body>
</html>