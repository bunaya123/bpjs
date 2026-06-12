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
        'C:/laragon/www/projektuas/bpjs/src/config.php'
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

// Cek koneksi database
if (!$conn) {
    die("Error: Tidak dapat terhubung ke database.");
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

// PROSES FILTER PERIODE
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_jenis = $_GET['jenis'] ?? 'semua';
$filter_tanggal_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$filter_tanggal_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// ==================== STATISTIK PESERTA ====================
// Query statistik peserta
$where_peserta = [];
$params_peserta = [];
$types_peserta = "";

// Filter berdasarkan periode
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $where_peserta[] = "DATE(tanggal_daftar) BETWEEN ? AND ?";
    $params_peserta[] = $filter_tanggal_mulai;
    $params_peserta[] = $filter_tanggal_selesai;
    $types_peserta .= "ss";
}

// Build WHERE clause untuk peserta
$where_clause_peserta = "";
if (!empty($where_peserta)) {
    $where_clause_peserta = "WHERE " . implode(" AND ", $where_peserta);
}

$sql_stats_peserta = "SELECT 
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
    
    -- Iuran aktual (SAMA DENGAN LAPORAN KEUANGAN)
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
    
    -- Iuran yang sudah dibayar
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
    ) as total_iuran_dibayar,
    
    -- Gaji
    SUM(COALESCE(gaji_dilaporkan, 0)) as total_gaji,
    SUM(CASE WHEN COALESCE(gaji_dilaporkan, 0) > 0 THEN 1 ELSE 0 END) as peserta_berpenghasilan
    
    FROM peserta " . $where_clause_peserta;

// Eksekusi query statistik peserta
if (!empty($params_peserta)) {
    $stmt_peserta = mysqli_prepare($conn, $sql_stats_peserta);
    mysqli_stmt_bind_param($stmt_peserta, $types_peserta, ...$params_peserta);
    mysqli_stmt_execute($stmt_peserta);
    $result_peserta = mysqli_stmt_get_result($stmt_peserta);
} else {
    $result_peserta = mysqli_query($conn, $sql_stats_peserta);
}

// Cek error query peserta
if (!$result_peserta) {
    die("Error query statistik peserta: " . mysqli_error($conn));
}

$stats_peserta = mysqli_fetch_assoc($result_peserta);

// ==================== STATISTIK KUNJUNGAN ====================
// Query statistik kunjungan
$where_kunjungan = [];
$params_kunjungan = [];
$types_kunjungan = "";

// Filter berdasarkan periode
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $where_kunjungan[] = "DATE(tanggal_kunjungan) BETWEEN ? AND ?";
    $params_kunjungan[] = $filter_tanggal_mulai;
    $params_kunjungan[] = $filter_tanggal_selesai;
    $types_kunjungan .= "ss";
}

// Build WHERE clause untuk kunjungan
$where_clause_kunjungan = "";
if (!empty($where_kunjungan)) {
    $where_clause_kunjungan = "WHERE " . implode(" AND ", $where_kunjungan);
}

$sql_stats_kunjungan = "SELECT 
    COUNT(*) as total_kunjungan,
    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) as diproses,
    SUM(CASE WHEN status = 'terdaftar' THEN 1 ELSE 0 END) as terdaftar,
    SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as batal,
    
    -- Jenis pelayanan
    SUM(CASE WHEN jenis_pelayanan = 'rawat_jalan' THEN 1 ELSE 0 END) as rawat_jalan,
    SUM(CASE WHEN jenis_pelayanan = 'rawat_inap' THEN 1 ELSE 0 END) as rawat_inap,
    SUM(CASE WHEN jenis_pelayanan = 'ugd' THEN 1 ELSE 0 END) as ugd,
    SUM(CASE WHEN jenis_pelayanan = 'rutin' THEN 1 ELSE 0 END) as rutin,
    
    -- Biaya
    SUM(COALESCE(biaya_administrasi, 0)) as total_biaya_kunjungan,
    AVG(COALESCE(biaya_administrasi, 0)) as rata_biaya_kunjungan,
    
    -- Peserta unik
    COUNT(DISTINCT peserta_id) as peserta_unik,
    
    -- Faskes unik
    COUNT(DISTINCT faskes_id) as faskes_unik
    
    FROM kunjungan " . $where_clause_kunjungan;

// Eksekusi query statistik kunjungan
if (!empty($params_kunjungan)) {
    $stmt_kunjungan = mysqli_prepare($conn, $sql_stats_kunjungan);
    mysqli_stmt_bind_param($stmt_kunjungan, $types_kunjungan, ...$params_kunjungan);
    mysqli_stmt_execute($stmt_kunjungan);
    $result_kunjungan = mysqli_stmt_get_result($stmt_kunjungan);
} else {
    $result_kunjungan = mysqli_query($conn, $sql_stats_kunjungan);
}

// Cek error query kunjungan
if (!$result_kunjungan) {
    die("Error query statistik kunjungan: " . mysqli_error($conn));
}

$stats_kunjungan = mysqli_fetch_assoc($result_kunjungan);

// ==================== STATISTIK KLAIM ====================
// Query statistik klaim
$where_klaim = [];
$params_klaim = [];
$types_klaim = "";

// Filter berdasarkan periode
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $where_klaim[] = "DATE(tanggal_klaim) BETWEEN ? AND ?";
    $params_klaim[] = $filter_tanggal_mulai;
    $params_klaim[] = $filter_tanggal_selesai;
    $types_klaim .= "ss";
}

// Build WHERE clause untuk klaim
$where_clause_klaim = "";
if (!empty($where_klaim)) {
    $where_clause_klaim = "WHERE " . implode(" AND ", $where_klaim);
}

$sql_stats_klaim = "SELECT 
    COUNT(*) as total_klaim,
    
    -- Status klaim
    SUM(CASE WHEN status_klaim = 'pending' THEN 1 ELSE 0 END) as klaim_pending,
    SUM(CASE WHEN status_klaim = 'approved' THEN 1 ELSE 0 END) as klaim_approved,
    SUM(CASE WHEN status_klaim = 'rejected' THEN 1 ELSE 0 END) as klaim_rejected,
    
    -- Nominal klaim
    SUM(COALESCE(nominal_klaim, 0)) as total_nominal_klaim,
    AVG(COALESCE(nominal_klaim, 0)) as rata_nominal_klaim,
    MAX(COALESCE(nominal_klaim, 0)) as maks_nominal_klaim,
    MIN(COALESCE(nominal_klaim, 0)) as min_nominal_klaim,
    
    -- Peserta unik yang mengajukan klaim
    COUNT(DISTINCT peserta_id) as peserta_klaim_unik,
    
    -- Tanggal
    MIN(DATE(tanggal_klaim)) as tanggal_klaim_pertama,
    MAX(DATE(tanggal_klaim)) as tanggal_klaim_terakhir
    
    FROM klaim " . $where_clause_klaim;

// Eksekusi query statistik klaim
if (!empty($params_klaim)) {
    $stmt_klaim = mysqli_prepare($conn, $sql_stats_klaim);
    mysqli_stmt_bind_param($stmt_klaim, $types_klaim, ...$params_klaim);
    mysqli_stmt_execute($stmt_klaim);
    $result_klaim = mysqli_stmt_get_result($stmt_klaim);
} else {
    $result_klaim = mysqli_query($conn, $sql_stats_klaim);
}

// Cek error query klaim
if (!$result_klaim) {
    die("Error query statistik klaim: " . mysqli_error($conn));
}

$stats_klaim = mysqli_fetch_assoc($result_klaim);

// ==================== STATISTIK KEUANGAN ====================
// Query statistik keuangan (SESUAI DENGAN LAPORAN KEUANGAN)
$where_keuangan = [];
$params_keuangan = [];
$types_keuangan = "";

// Filter berdasarkan periode
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $where_keuangan[] = "DATE(tanggal_daftar) BETWEEN ? AND ?";
    $params_keuangan[] = $filter_tanggal_mulai;
    $params_keuangan[] = $filter_tanggal_selesai;
    $types_keuangan .= "ss";
}

$where_keuangan[] = "status_pembayaran IN ('verified', 'paid')";

// Build WHERE clause untuk keuangan
$where_clause_keuangan = "";
if (!empty($where_keuangan)) {
    $where_clause_keuangan = "WHERE " . implode(" AND ", $where_keuangan);
}

$sql_stats_keuangan = "SELECT 
    COUNT(*) as total_pembayaran,
    
    -- Hitung iuran aktual berdasarkan segmen (SAMA DENGAN LAPORAN KEUANGAN)
    SUM(
        CASE 
            -- PPU: 5% dari gaji
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
            THEN COALESCE(gaji_dilaporkan, 0) * 0.05
            
            -- PBPU: iuran bulanan
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PBPU' 
            THEN COALESCE(iuran_bulanan, 0)
            
            -- PBI: 0
            ELSE 0
        END
    ) as total_iuran,
    
    -- Biaya admin
    SUM(COALESCE(biaya_admin, 0)) as total_biaya_admin,
    
    -- PPN (10% dari iuran)
    SUM(
        CASE 
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
            THEN (COALESCE(gaji_dilaporkan, 0) * 0.05) * 0.10
            
            WHEN COALESCE(segmen_peserta, 
                CASE 
                    WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                    WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                    ELSE 'PBI'
                END
            ) = 'PBPU' 
            THEN COALESCE(iuran_bulanan, 0) * 0.10
            
            ELSE 0
        END
    ) as total_ppn,
    
    -- Total keseluruhan = Iuran + Admin + PPN
    (
        SUM(
            CASE 
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
                THEN COALESCE(gaji_dilaporkan, 0) * 0.05
                
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PBPU' 
                THEN COALESCE(iuran_bulanan, 0)
                
                ELSE 0
            END
        ) + 
        SUM(COALESCE(biaya_admin, 0)) +
        SUM(
            CASE 
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
                THEN (COALESCE(gaji_dilaporkan, 0) * 0.05) * 0.10
                
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PBPU' 
                THEN COALESCE(iuran_bulanan, 0) * 0.10
                
                ELSE 0
            END
        )
    ) as total_keseluruhan,
    
    AVG(
        (
            CASE 
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
                THEN COALESCE(gaji_dilaporkan, 0) * 0.05
                
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PBPU' 
                THEN COALESCE(iuran_bulanan, 0)
                
                ELSE 0
            END
        ) + 
        COALESCE(biaya_admin, 0) +
        (
            CASE 
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
                THEN (COALESCE(gaji_dilaporkan, 0) * 0.05) * 0.10
                
                WHEN COALESCE(segmen_peserta, 
                    CASE 
                        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                        ELSE 'PBI'
                    END
                ) = 'PBPU' 
                THEN COALESCE(iuran_bulanan, 0) * 0.10
                
                ELSE 0
            END
        )
    ) as rata_pembayaran,
    
    -- Metode pembayaran
    SUM(CASE WHEN metode_pembayaran = 'transfer_bank' THEN 1 ELSE 0 END) as transfer_bank,
    SUM(CASE WHEN metode_pembayaran = 'kartu_kredit' THEN 1 ELSE 0 END) as kartu_kredit,
    SUM(CASE WHEN metode_pembayaran = 'virtual_account' THEN 1 ELSE 0 END) as virtual_account,
    SUM(CASE WHEN metode_pembayaran = 'tunai' THEN 1 ELSE 0 END) as tunai,
    
    -- Segmen untuk statistik
    SUM(CASE WHEN COALESCE(segmen_peserta, 
        CASE 
            WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
            WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
            ELSE 'PBI'
        END) = 'PPU' THEN 1 ELSE 0 END) as pembayaran_ppu,
    
    SUM(CASE WHEN COALESCE(segmen_peserta, 
        CASE 
            WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
            WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
            ELSE 'PBI'
        END) = 'PBPU' THEN 1 ELSE 0 END) as pembayaran_pbpu,
    
    SUM(CASE WHEN COALESCE(segmen_peserta, 
        CASE 
            WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
            WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
            ELSE 'PBI'
        END) = 'PBI' THEN 1 ELSE 0 END) as pembayaran_pbi
    
    FROM peserta " . $where_clause_keuangan;

// Eksekusi query statistik keuangan
if (!empty($params_keuangan)) {
    $stmt_keuangan = mysqli_prepare($conn, $sql_stats_keuangan);
    mysqli_stmt_bind_param($stmt_keuangan, $types_keuangan, ...$params_keuangan);
    mysqli_stmt_execute($stmt_keuangan);
    $result_keuangan = mysqli_stmt_get_result($stmt_keuangan);
} else {
    $result_keuangan = mysqli_query($conn, $sql_stats_keuangan);
}

// Cek error query keuangan
if (!$result_keuangan) {
    die("Error query statistik keuangan: " . mysqli_error($conn));
}

$stats_keuangan = mysqli_fetch_assoc($result_keuangan);

// ==================== STATISTIK TAMBAHAN ====================
// Data untuk chart trend bulanan
$sql_trend = "SELECT 
    DATE_FORMAT(tanggal_daftar, '%Y-%m') as bulan,
    COUNT(*) as jumlah_peserta,
    -- Total pembayaran sesuai rumus laporan keuangan
    SUM(
        CASE 
            WHEN status_pembayaran IN ('verified', 'paid') THEN
                (
                    CASE 
                        WHEN COALESCE(segmen_peserta, 
                            CASE 
                                WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                                WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                                ELSE 'PBI'
                            END
                        ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
                        THEN COALESCE(gaji_dilaporkan, 0) * 0.05
                        
                        WHEN COALESCE(segmen_peserta, 
                            CASE 
                                WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                                WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                                ELSE 'PBI'
                            END
                        ) = 'PBPU' 
                        THEN COALESCE(iuran_bulanan, 0)
                        
                        ELSE 0
                    END
                ) + 
                COALESCE(biaya_admin, 0) +
                (
                    CASE 
                        WHEN COALESCE(segmen_peserta, 
                            CASE 
                                WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                                WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                                ELSE 'PBI'
                            END
                        ) = 'PPU' AND COALESCE(gaji_dilaporkan, 0) > 0 
                        THEN (COALESCE(gaji_dilaporkan, 0) * 0.05) * 0.10
                        
                        WHEN COALESCE(segmen_peserta, 
                            CASE 
                                WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
                                WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
                                ELSE 'PBI'
                            END
                        ) = 'PBPU' 
                        THEN COALESCE(iuran_bulanan, 0) * 0.10
                        
                        ELSE 0
                    END
                )
            ELSE 0
        END
    ) as total_pembayaran
    FROM peserta 
    WHERE DATE(tanggal_daftar) BETWEEN DATE_SUB(NOW(), INTERVAL 6 MONTH) AND NOW()
    GROUP BY DATE_FORMAT(tanggal_daftar, '%Y-%m')
    ORDER BY bulan DESC
    LIMIT 6";

$result_trend = mysqli_query($conn, $sql_trend);
$trend_data = [];
$trend_labels = [];
$trend_peserta = [];
$trend_pembayaran = [];

if ($result_trend) {
    while ($row = mysqli_fetch_assoc($result_trend)) {
        $trend_labels[] = date('M Y', strtotime($row['bulan'] . '-01'));
        $trend_peserta[] = (int)$row['jumlah_peserta'];
        $trend_pembayaran[] = (float)$row['total_pembayaran'];
    }
} else {
    // Default values jika query error
    $trend_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'];
    $trend_peserta = [0, 0, 0, 0, 0, 0];
    $trend_pembayaran = [0, 0, 0, 0, 0, 0];
}

// Data untuk chart trend klaim bulanan
$sql_trend_klaim = "SELECT 
    DATE_FORMAT(tanggal_klaim, '%Y-%m') as bulan,
    COUNT(*) as jumlah_klaim,
    SUM(COALESCE(nominal_klaim, 0)) as total_nominal_klaim,
    SUM(CASE WHEN status_klaim = 'approved' THEN nominal_klaim ELSE 0 END) as total_klaim_approved
    FROM klaim 
    WHERE DATE(tanggal_klaim) BETWEEN DATE_SUB(NOW(), INTERVAL 6 MONTH) AND NOW()
    GROUP BY DATE_FORMAT(tanggal_klaim, '%Y-%m')
    ORDER BY bulan DESC
    LIMIT 6";

$result_trend_klaim = mysqli_query($conn, $sql_trend_klaim);
$trend_klaim_labels = [];
$trend_klaim_jumlah = [];
$trend_klaim_nominal = [];
$trend_klaim_approved = [];

if ($result_trend_klaim) {
    while ($row = mysqli_fetch_assoc($result_trend_klaim)) {
        $trend_klaim_labels[] = date('M Y', strtotime($row['bulan'] . '-01'));
        $trend_klaim_jumlah[] = (int)$row['jumlah_klaim'];
        $trend_klaim_nominal[] = (float)$row['total_nominal_klaim'];
        $trend_klaim_approved[] = (float)$row['total_klaim_approved'];
    }
} else {
    // Default values jika query error
    $trend_klaim_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'];
    $trend_klaim_jumlah = [0, 0, 0, 0, 0, 0];
    $trend_klaim_nominal = [0, 0, 0, 0, 0, 0];
    $trend_klaim_approved = [0, 0, 0, 0, 0, 0];
}

// ==================== PERBAIKAN: QUERY UNTUK CHART SEGMEN ====================
// Solusi 1: Gunakan query yang lebih sederhana
$segmen_labels = ['PPU', 'PBPU', 'PBI'];
$segmen_data = [0, 0, 0];

// Query untuk segmen PPU (Kelas 1)
$sql_ppu = "SELECT COUNT(*) as jumlah FROM peserta WHERE 1=1";
if (!empty($where_clause_peserta)) {
    $sql_ppu .= " AND (" . str_replace("WHERE ", "", $where_clause_peserta) . ")";
}
$sql_ppu .= " AND COALESCE(segmen_peserta, 
    CASE 
        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
        ELSE 'PBI'
    END) = 'PPU'";

// Query untuk segmen PBPU (Kelas 2)
$sql_pbpu = "SELECT COUNT(*) as jumlah FROM peserta WHERE 1=1";
if (!empty($where_clause_peserta)) {
    $sql_pbpu .= " AND (" . str_replace("WHERE ", "", $where_clause_peserta) . ")";
}
$sql_pbpu .= " AND COALESCE(segmen_peserta, 
    CASE 
        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
        ELSE 'PBI'
    END) = 'PBPU'";

// Query untuk segmen PBI (Kelas 3)
$sql_pbi = "SELECT COUNT(*) as jumlah FROM peserta WHERE 1=1";
if (!empty($where_clause_peserta)) {
    $sql_pbi .= " AND (" . str_replace("WHERE ", "", $where_clause_peserta) . ")";
}
$sql_pbi .= " AND COALESCE(segmen_peserta, 
    CASE 
        WHEN kelas_bpjs LIKE '%Kelas 1%' THEN 'PPU'
        WHEN kelas_bpjs LIKE '%Kelas 2%' THEN 'PBPU'
        ELSE 'PBI'
    END) = 'PBI'";

// Eksekusi query untuk masing-masing segmen dengan prepared statement
try {
    // PPU
    if (!empty($params_peserta)) {
        $stmt_ppu = mysqli_prepare($conn, $sql_ppu);
        mysqli_stmt_bind_param($stmt_ppu, $types_peserta, ...$params_peserta);
        mysqli_stmt_execute($stmt_ppu);
        $result_ppu = mysqli_stmt_get_result($stmt_ppu);
    } else {
        $result_ppu = mysqli_query($conn, $sql_ppu);
    }
    if ($result_ppu) {
        $row_ppu = mysqli_fetch_assoc($result_ppu);
        $segmen_data[0] = (int)$row_ppu['jumlah'];
    }
    
    // PBPU
    if (!empty($params_peserta)) {
        $stmt_pbpu = mysqli_prepare($conn, $sql_pbpu);
        mysqli_stmt_bind_param($stmt_pbpu, $types_peserta, ...$params_peserta);
        mysqli_stmt_execute($stmt_pbpu);
        $result_pbpu = mysqli_stmt_get_result($stmt_pbpu);
    } else {
        $result_pbpu = mysqli_query($conn, $sql_pbpu);
    }
    if ($result_pbpu) {
        $row_pbpu = mysqli_fetch_assoc($result_pbpu);
        $segmen_data[1] = (int)$row_pbpu['jumlah'];
    }
    
    // PBI
    if (!empty($params_peserta)) {
        $stmt_pbi = mysqli_prepare($conn, $sql_pbi);
        mysqli_stmt_bind_param($stmt_pbi, $types_peserta, ...$params_peserta);
        mysqli_stmt_execute($stmt_pbi);
        $result_pbi = mysqli_stmt_get_result($stmt_pbi);
    } else {
        $result_pbi = mysqli_query($conn, $sql_pbi);
    }
    if ($result_pbi) {
        $row_pbi = mysqli_fetch_assoc($result_pbi);
        $segmen_data[2] = (int)$row_pbi['jumlah'];
    }
} catch (Exception $e) {
    // Jika error, gunakan data dari statistik peserta yang sudah ada
    $segmen_data[0] = $stats_peserta['ppu'] ?? 0;
    $segmen_data[1] = $stats_peserta['pbpu'] ?? 0;
    $segmen_data[2] = $stats_peserta['pbi'] ?? 0;
}

// Format function
function formatRupiah($angka) {
    if ($angka === null || $angka == 0) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatAngka($angka) {
    return number_format($angka, 0, ',', '.');
}

function hitungPersentase($jumlah, $total) {
    if ($total == 0) return 0;
    return round(($jumlah / $total) * 100, 2);
}

// Hitung persentase untuk berbagai statistik
$persentase_aktif = hitungPersentase($stats_peserta['aktif'] ?? 0, $stats_peserta['total'] ?? 1);
$persentase_bayar = hitungPersentase($stats_peserta['bayar_verified'] ?? 0, $stats_peserta['total'] ?? 1);
$persentase_selesai = hitungPersentase($stats_kunjungan['selesai'] ?? 0, $stats_kunjungan['total_kunjungan'] ?? 1);
$persentase_rawat_jalan = hitungPersentase($stats_kunjungan['rawat_jalan'] ?? 0, $stats_kunjungan['total_kunjungan'] ?? 1);

// Hitung persentase untuk klaim
$persentase_klaim_pending = hitungPersentase($stats_klaim['klaim_pending'] ?? 0, $stats_klaim['total_klaim'] ?? 1);
$persentase_klaim_approved = hitungPersentase($stats_klaim['klaim_approved'] ?? 0, $stats_klaim['total_klaim'] ?? 1);
$persentase_klaim_rejected = hitungPersentase($stats_klaim['klaim_rejected'] ?? 0, $stats_klaim['total_klaim'] ?? 1);

// Hitung persentase untuk keuangan
$persentase_iuran = hitungPersentase($stats_keuangan['total_iuran'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1);
$persentase_admin = hitungPersentase($stats_keuangan['total_biaya_admin'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1);
$persentase_ppn = hitungPersentase($stats_keuangan['total_ppn'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1);

// List bulan untuk filter
$bulan_list = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', 
    '04' => 'April', '05' => 'Mei', '06' => 'Juni', 
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September', 
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Statistik BPJS - <?php echo htmlspecialchars($user['username']); ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.css">
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
    :root {
        --bpjs-blue: #0066cc;
        --bpjs-blue-light: #0073e6;
        --bpjs-green: #28a745;
        --bpjs-red: #dc3545;
        --bpjs-yellow: #ffc107;
        --bpjs-purple: #6f42c1;
        --bpjs-teal: #20c997;
        --bpjs-gray: #6c757d;
        --bpjs-orange: #fd7e14;
    }
    
    .page-header-statistik {
        background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
        color: white;
        padding: 25px 30px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0, 102, 204, 0.2);
    }
    
    .stat-card {
        border-radius: 12px;
        transition: all 0.3s;
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        height: 100%;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        margin-bottom: 15px;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 5px;
        line-height: 1.2;
    }
    
    .stat-label {
        font-size: 14px;
        color: #6c757d;
        font-weight: 500;
        margin-bottom: 8px;
    }
    
    .stat-change {
        font-size: 12px;
        display: flex;
        align-items: center;
        margin-top: 5px;
    }
    
    .stat-change.positive {
        color: #28a745;
    }
    
    .stat-change.negative {
        color: #dc3545;
    }
    
    .chart-card {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        height: 100%;
    }
    
    .chart-card .card-body {
        padding: 20px;
    }
    
    .chart-title {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 15px;
        color: #333;
        display: flex;
        align-items: center;
    }
    
    .chart-title i {
        margin-right: 10px;
        color: var(--bpjs-blue);
    }
    
    .filter-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 4px solid var(--bpjs-blue);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .filter-title {
        font-weight: 600;
        color: #495057;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    
    .filter-title i {
        margin-right: 8px;
        color: var(--bpjs-blue);
    }
    
    .progress-stat {
        height: 10px;
        border-radius: 5px;
        margin-top: 5px;
    }
    
    .table-statistik th {
        background: linear-gradient(135deg, var(--bpjs-blue) 0%, #0056b3 100%);
        color: white;
        border-color: #0056b3;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 13px;
    }
    
    .table-statistik tbody tr:hover {
        background-color: rgba(0, 102, 204, 0.05);
    }
    
    .summary-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
        border-left: 4px solid var(--bpjs-green);
    }
    
    .summary-section h5 {
        color: var(--bpjs-green);
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .highlight-number {
        font-size: 32px;
        font-weight: 700;
        color: var(--bpjs-blue);
        margin: 10px 0;
    }
    
    .highlight-number small {
        font-size: 14px;
        color: #6c757d;
        font-weight: 400;
    }
    
    .comparison-card {
        background: white;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 15px;
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
    .comparison-title {
        font-size: 13px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 8px;
    }
    
    .comparison-value {
        font-size: 18px;
        font-weight: 700;
        color: #333;
    }
    
    .comparison-change {
        font-size: 12px;
        margin-top: 3px;
    }
    
    .insight-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .insight-card .stat-value {
        color: white;
    }
    
    .insight-card .stat-label {
        color: rgba(255,255,255,0.9);
    }
    
    .export-btn-group .btn {
        border-radius: 20px;
        padding: 8px 20px;
        font-weight: 500;
        margin-left: 10px;
    }
    
    @media (max-width: 768px) {
        .page-header-statistik {
            padding: 15px;
        }
        
        .stat-value {
            font-size: 22px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 22px;
        }
        
        .chart-container {
            height: 250px !important;
        }
        
        .export-btn-group .btn {
            margin-bottom: 10px;
            margin-left: 0;
        }
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    /* Style untuk print */
    @media print {
        .sidebar, .t-header, .no-print, .filter-section, .export-btn-group, 
        .chart-card canvas, .insight-card, .btn-action-group, 
        .filter-card, .filter-form, button, a.btn {
            display: none !important;
        }
        
        .print-header {
            display: block !important;
        }
        
        .page-content-wrapper {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        .stat-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        
        .chart-card .card-body {
            padding: 10px !important;
        }
        
        .table-statistik {
            font-size: 10px !important;
        }
    }
    </style>
  </head>
  <body class="header-fixed">

  <div class="page-body">
      <!-- SIDEBAR - MENU TETAP SAMA -->
      <div class="sidebar">
          <div class="user-profile">
              <div class="display-avatar animated-avatar">
                  <?php if ($has_custom_profile && !empty($profile_pic)): ?>
                      <!-- Jika ada foto profil yang diupload -->
                      <img class="profile-img img-lg rounded-circle" 
                          src="uploads/profile_pics/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
                          alt="profile image"
                          onerror="this.style.display='none'; document.getElementById('avatar-default-faskes').style.display='block';">
                  <?php endif; ?>
                  
                  <!-- Foto default (akan ditampilkan jika tidak ada custom photo) -->
                  <img id="avatar-default-faskes" 
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
                      <li>
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
                      <li class="active">
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
              
              <!-- HEADER STATISTIK -->
              <div class="page-header-statistik">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h1><i class="mdi mdi-chart-pie mr-2"></i> Laporan Statistik</h1>
                      <p class="mb-0" style="opacity: 0.9;">
                        Analisis komprehensif data peserta, kunjungan, klaim, dan keuangan BPJS
                        <?php if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)): ?>
                          <br><small>Periode: <?php echo date('d M Y', strtotime($filter_tanggal_mulai)); ?> - <?php echo date('d M Y', strtotime($filter_tanggal_selesai)); ?></small>
                        <?php endif; ?>
                      </p>
                    </div>
                    <div class="export-btn-group no-print">
                      <button class="btn btn-light" onclick="printStatistik()">
                        <i class="mdi mdi-printer"></i> Cetak
                      </button>
                      <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="mdi mdi-file-excel"></i> Excel
                      </button>
                    </div>
                  </div>
                </div>
                
              <!-- FILTER SECTION -->
              <div class="filter-card no-print">
                <h5 class="filter-title"><i class="mdi mdi-filter-outline"></i> Filter Periode</h5>
                <form method="GET" action="" class="filter-form">
                  <div class="row">
                    <div class="col-md-3">
                      <div class="form-group">
                        <label>Tanggal Mulai</label>
                        <input type="date" class="form-control" name="tgl_mulai" 
                              value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>">
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label>Tanggal Selesai</label>
                        <input type="date" class="form-control" name="tgl_selesai" 
                              value="<?php echo htmlspecialchars($filter_tanggal_selesai); ?>">
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label>Tahun</label>
                        <select class="form-control" name="tahun" onchange="this.form.submit()">
                          <?php for ($i = date('Y'); $i >= 2020; $i--): ?>
                          <option value="<?php echo $i; ?>" <?php echo $filter_tahun == $i ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                          </option>
                          <?php endfor; ?>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label>Bulan</label>
                        <select class="form-control" name="bulan" onchange="this.form.submit()">
                          <option value="all" <?php echo $filter_bulan == 'all' ? 'selected' : ''; ?>>Semua Bulan</option>
                          <?php 
                          $bulan_list = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', 
                                        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                                        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
                          foreach ($bulan_list as $key => $nama): ?>
                          <option value="<?php echo $key; ?>" <?php echo $filter_bulan == $key ? 'selected' : ''; ?>>
                            <?php echo $nama; ?>
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="row mt-2">
                    <div class="col-md-12">
                      <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-filter"></i> Terapkan Filter
                      </button>
                      <a href="laporan_statistik.php" class="btn btn-secondary">
                        <i class="mdi mdi-refresh"></i> Reset
                      </a>
                      <small class="text-muted ml-3">
                        <i class="mdi mdi-information-outline"></i>
                        Menampilkan data untuk periode yang dipilih
                      </small>
                    </div>
                  </div>
                </form>
              </div>
              
              <!-- SUMMARY SECTION -->
              <div class="summary-section">
                <h5><i class="mdi mdi-chart-line mr-2"></i> Ringkasan Utama</h5>
                <div class="row">
                  <div class="col-md-3 text-center">
                    <div class="highlight-number">
                      <?php echo formatAngka($stats_peserta['total'] ?? 0); ?>
                      <br><small>Total Peserta</small>
                    </div>
                    <p class="text-muted mb-0">
                      <?php echo formatAngka($stats_peserta['aktif'] ?? 0); ?> aktif
                      (<?php echo $persentase_aktif; ?>%)
                    </p>
                  </div>
                  <div class="col-md-3 text-center">
                    <div class="highlight-number">
                      <?php echo formatAngka($stats_kunjungan['total_kunjungan'] ?? 0); ?>
                      <br><small>Total Kunjungan</small>
                    </div>
                    <p class="text-muted mb-0">
                      <?php echo formatAngka($stats_kunjungan['peserta_unik'] ?? 0); ?> peserta unik
                    </p>
                  </div>
                  <div class="col-md-3 text-center">
                    <div class="highlight-number">
                      <?php echo formatAngka($stats_klaim['total_klaim'] ?? 0); ?>
                      <br><small>Total Klaim</small>
                    </div>
                    <p class="text-muted mb-0">
                      <?php echo formatRupiah($stats_klaim['total_nominal_klaim'] ?? 0); ?> nilai
                    </p>
                  </div>
                  <div class="col-md-3 text-center">
                    <div class="highlight-number">
                      <?php echo formatRupiah($stats_keuangan['total_keseluruhan'] ?? 0); ?>
                      <br><small>Total Penerimaan</small>
                    </div>
                    <p class="text-muted mb-0">
                      <?php echo formatAngka($stats_keuangan['total_pembayaran'] ?? 0); ?> transaksi
                    </p>
                  </div>
                </div>
              </div>
              
              <!-- STATISTIK PESERTA -->
              <div class="row mb-4">
                <div class="col-md-12">
                  <div class="card chart-card">
                    <div class="card-body">
                      <h5 class="chart-title"><i class="mdi mdi-account-multiple"></i> Statistik Peserta</h5>
                      <div class="row">
                        <!-- Kartu Statistik Peserta -->
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #0066cc 0%, #003366 100%);">
                                <i class="mdi mdi-account-multiple"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_peserta['total'] ?? 0); ?></div>
                              <div class="stat-label">Total Peserta</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                                <i class="mdi mdi-check-circle"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_peserta['aktif'] ?? 0); ?></div>
                              <div class="stat-label">Peserta Aktif</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-success" style="width: <?php echo $persentase_aktif; ?>%"></div>
                              </div>
                              <div class="stat-change positive">
                                <i class="mdi mdi-arrow-up"></i>
                                <?php echo $persentase_aktif; ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                                <i class="mdi mdi-cash-usd"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_peserta['bayar_verified'] ?? 0); ?></div>
                              <div class="stat-label">Sudah Membayar</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-warning" style="width: <?php echo $persentase_bayar; ?>%"></div>
                              </div>
                              <div class="stat-change positive">
                                <i class="mdi mdi-arrow-up"></i>
                                <?php echo $persentase_bayar; ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #20c997 0%, #17a589 100%);">
                                <i class="mdi mdi-currency-usd"></i>
                              </div>
                              <div class="stat-value"><?php echo formatRupiah($stats_peserta['total_iuran_aktual'] ?? 0); ?></div>
                              <div class="stat-label">Total Iuran Aktual</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-teal" style="width: 100%"></div>
                              </div>
                              <div class="stat-change positive">
                                <?php echo formatRupiah($stats_peserta['total_iuran_dibayar'] ?? 0); ?> sudah dibayar
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Detail Segmen Peserta -->
                      <div class="row mt-3">
                        <div class="col-md-4">
                          <div class="comparison-card">
                            <div class="comparison-title">PPU (Kelas 1)</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_peserta['ppu'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_peserta['ppu'] ?? 0, $stats_peserta['total'] ?? 1); ?>% dari total
                            </div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="comparison-card">
                            <div class="comparison-title">PBPU (Kelas 2)</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_peserta['pbpu'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_peserta['pbpu'] ?? 0, $stats_peserta['total'] ?? 1); ?>% dari total
                            </div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="comparison-card">
                            <div class="comparison-title">PBI (Kelas 3)</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_peserta['pbi'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_peserta['pbi'] ?? 0, $stats_peserta['total'] ?? 1); ?>% dari total
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- STATISTIK KUNJUNGAN -->
              <div class="row mb-4">
                <div class="col-md-12">
                  <div class="card chart-card">
                    <div class="card-body">
                      <h5 class="chart-title"><i class="mdi mdi-hospital-building"></i> Statistik Kunjungan</h5>
                      <div class="row">
                        <!-- Kartu Statistik Kunjungan -->
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%);">
                                <i class="mdi mdi-hospital"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_kunjungan['total_kunjungan'] ?? 0); ?></div>
                              <div class="stat-label">Total Kunjungan</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-purple" style="width: 100%"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                                <i class="mdi mdi-check-circle"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_kunjungan['selesai'] ?? 0); ?></div>
                              <div class="stat-label">Kunjungan Selesai</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-success" style="width: <?php echo $persentase_selesai; ?>%"></div>
                              </div>
                              <div class="stat-change positive">
                                <i class="mdi mdi-arrow-up"></i>
                                <?php echo $persentase_selesai; ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                                <i class="mdi mdi-walk"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_kunjungan['rawat_jalan'] ?? 0); ?></div>
                              <div class="stat-label">Rawat Jalan</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-warning" style="width: <?php echo $persentase_rawat_jalan; ?>%"></div>
                              </div>
                              <div class="stat-change positive">
                                <i class="mdi mdi-arrow-up"></i>
                                <?php echo $persentase_rawat_jalan; ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%);">
                                <i class="mdi mdi-cash"></i>
                              </div>
                              <div class="stat-value"><?php echo formatRupiah($stats_kunjungan['total_biaya_kunjungan'] ?? 0); ?></div>
                              <div class="stat-label">Total Biaya</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-info" style="width: 100%"></div>
                              </div>
                              <div class="stat-change positive">
                                Rata-rata: <?php echo formatRupiah($stats_kunjungan['rata_biaya_kunjungan'] ?? 0); ?>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Detail Jenis Kunjungan -->
                      <div class="row mt-3">
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Peserta Unik</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_kunjungan['peserta_unik'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              Rata-rata <?php echo $stats_kunjungan['total_kunjungan'] > 0 ? 
                                  round($stats_kunjungan['total_kunjungan'] / $stats_kunjungan['peserta_unik'], 1) : 0; ?> kunjungan per peserta
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Faskes Dilayani</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_kunjungan['faskes_unik'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              Rata-rata <?php echo $stats_kunjungan['faskes_unik'] > 0 ? 
                                  round($stats_kunjungan['total_kunjungan'] / $stats_kunjungan['faskes_unik'], 1) : 0; ?> kunjungan per faskes
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Rawat Inap</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_kunjungan['rawat_inap'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_kunjungan['rawat_inap'] ?? 0, $stats_kunjungan['total_kunjungan'] ?? 1); ?>% dari total
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">UGD</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_kunjungan['ugd'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_kunjungan['ugd'] ?? 0, $stats_kunjungan['total_kunjungan'] ?? 1); ?>% dari total
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- STATISTIK KLAIM -->
              <div class="row mb-4">
                <div class="col-md-12">
                  <div class="card chart-card">
                    <div class="card-body">
                      <h5 class="chart-title"><i class="mdi mdi-file-document-box-check"></i> Statistik Klaim</h5>
                      <div class="row">
                        <!-- Kartu Statistik Klaim -->
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #fd7e14 0%, #e76a00 100%);">
                                <i class="mdi mdi-file-document-box"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_klaim['total_klaim'] ?? 0); ?></div>
                              <div class="stat-label">Total Klaim</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar" style="width: 100%; background-color: #fd7e14;"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                                <i class="mdi mdi-check-circle"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_klaim['klaim_approved'] ?? 0); ?></div>
                              <div class="stat-label">Klaim Disetujui</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-success" style="width: <?php echo $persentase_klaim_approved; ?>%"></div>
                              </div>
                              <div class="stat-change positive">
                                <i class="mdi mdi-arrow-up"></i>
                                <?php echo $persentase_klaim_approved; ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                                <i class="mdi mdi-clock"></i>
                              </div>
                              <div class="stat-value"><?php echo formatAngka($stats_klaim['klaim_pending'] ?? 0); ?></div>
                              <div class="stat-label">Klaim Pending</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-warning" style="width: <?php echo $persentase_klaim_pending; ?>%"></div>
                              </div>
                              <div class="stat-change positive">
                                <i class="mdi mdi-clock-outline"></i>
                                <?php echo $persentase_klaim_pending; ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);">
                                <i class="mdi mdi-cash"></i>
                              </div>
                              <div class="stat-value"><?php echo formatRupiah($stats_klaim['total_nominal_klaim'] ?? 0); ?></div>
                              <div class="stat-label">Total Nilai Klaim</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-danger" style="width: 100%"></div>
                              </div>
                              <div class="stat-change positive">
                                Rata-rata: <?php echo formatRupiah($stats_klaim['rata_nominal_klaim'] ?? 0); ?>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Detail Klaim -->
                      <div class="row mt-3">
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Peserta Klaim Unik</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_klaim['peserta_klaim_unik'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              Rata-rata <?php echo $stats_klaim['peserta_klaim_unik'] > 0 ? 
                                  round($stats_klaim['total_klaim'] / $stats_klaim['peserta_klaim_unik'], 1) : 0; ?> klaim per peserta
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Klaim Ditolak</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_klaim['klaim_rejected'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo $persentase_klaim_rejected; ?>% dari total
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Klaim Tertinggi</div>
                            <div class="comparison-value"><?php echo formatRupiah($stats_klaim['maks_nominal_klaim'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              Nilai maksimum klaim
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Klaim Terendah</div>
                            <div class="comparison-value"><?php echo formatRupiah($stats_klaim['min_nominal_klaim'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              Nilai minimum klaim
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- STATISTIK KEUANGAN -->
              <div class="row mb-4">
                <div class="col-md-12">
                  <div class="card chart-card">
                    <div class="card-body">
                      <h5 class="chart-title"><i class="mdi mdi-cash-multiple"></i> Statistik Keuangan</h5>
                      <div class="row">
                        <!-- Kartu Statistik Keuangan -->
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                                <i class="mdi mdi-cash-multiple"></i>
                              </div>
                              <div class="stat-value"><?php echo formatRupiah($stats_keuangan['total_keseluruhan'] ?? 0); ?></div>
                              <div class="stat-label">Total Penerimaan</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #0066cc 0%, #003366 100%);">
                                <i class="mdi mdi-cash-usd"></i>
                              </div>
                              <div class="stat-value"><?php echo formatRupiah($stats_keuangan['total_iuran'] ?? 0); ?></div>
                              <div class="stat-label">Total Iuran</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                              </div>
                              <div class="stat-change positive">
                                <?php echo hitungPersentase($stats_keuangan['total_iuran'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1); ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);">
                                <i class="mdi mdi-bank"></i>
                              </div>
                              <div class="stat-value"><?php echo formatRupiah($stats_keuangan['total_biaya_admin'] ?? 0); ?></div>
                              <div class="stat-label">Biaya Admin</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-secondary" style="width: 100%"></div>
                              </div>
                              <div class="stat-change positive">
                                <?php echo hitungPersentase($stats_keuangan['total_biaya_admin'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1); ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="col-md-3">
                          <div class="stat-card">
                            <div class="card-body">
                              <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);">
                                <i class="mdi mdi-calculator"></i>
                              </div>
                              <div class="stat-value"><?php echo formatRupiah($stats_keuangan['total_ppn'] ?? 0); ?></div>
                              <div class="stat-label">Total PPN</div>
                              <div class="progress progress-stat">
                                <div class="progress-bar bg-danger" style="width: 100%"></div>
                              </div>
                              <div class="stat-change positive">
                                <?php echo hitungPersentase($stats_keuangan['total_ppn'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1); ?>% dari total
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Detail Metode Pembayaran -->
                      <div class="row mt-3">
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Transfer Bank</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_keuangan['transfer_bank'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_keuangan['transfer_bank'] ?? 0, $stats_keuangan['total_pembayaran'] ?? 1); ?>% transaksi
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Kartu Kredit</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_keuangan['kartu_kredit'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_keuangan['kartu_kredit'] ?? 0, $stats_keuangan['total_pembayaran'] ?? 1); ?>% transaksi
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Virtual Account</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_keuangan['virtual_account'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_keuangan['virtual_account'] ?? 0, $stats_keuangan['total_pembayaran'] ?? 1); ?>% transaksi
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="comparison-card">
                            <div class="comparison-title">Tunai</div>
                            <div class="comparison-value"><?php echo formatAngka($stats_keuangan['tunai'] ?? 0); ?></div>
                            <div class="comparison-change positive">
                              <?php echo hitungPersentase($stats_keuangan['tunai'] ?? 0, $stats_keuangan['total_pembayaran'] ?? 1); ?>% transaksi
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- TABEL RINGKASAN -->
              <div class="row">
                <div class="col-md-12">
                  <div class="card chart-card">
                    <div class="card-body">
                      <h5 class="chart-title"><i class="mdi mdi-table"></i> Ringkasan Statistik</h5>
                      <div class="table-responsive">
                        <table class="table table-bordered table-statistik">
                          <thead>
                            <tr>
                              <th width="30%">Kategori</th>
                              <th width="20%">Indikator</th>
                              <th width="20%">Nilai</th>
                              <th width="15%">Persentase</th>
                              <th width="15%">Keterangan</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td rowspan="4" class="align-middle"><strong>PESERTA</strong></td>
                              <td>Total Peserta</td>
                              <td><?php echo formatAngka($stats_peserta['total'] ?? 0); ?></td>
                              <td>100%</td>
                              <td>Semua peserta terdaftar</td>
                            </tr>
                            <tr>
                              <td>Peserta Aktif</td>
                              <td><?php echo formatAngka($stats_peserta['aktif'] ?? 0); ?></td>
                              <td><?php echo $persentase_aktif; ?>%</td>
                              <td>Status aktif</td>
                            </tr>
                            <tr>
                              <td>Sudah Membayar</td>
                              <td><?php echo formatAngka($stats_peserta['bayar_verified'] ?? 0); ?></td>
                              <td><?php echo $persentase_bayar; ?>%</td>
                              <td>Lunas/verified</td>
                            </tr>
                            <tr>
                              <td>Total Iuran Aktual</td>
                              <td><?php echo formatRupiah($stats_peserta['total_iuran_aktual'] ?? 0); ?></td>
                              <td>-</td>
                              <td>Per bulan</td>
                            </tr>
                            
                            <tr>
                              <td rowspan="4" class="align-middle"><strong>KUNJUNGAN</strong></td>
                              <td>Total Kunjungan</td>
                              <td><?php echo formatAngka($stats_kunjungan['total_kunjungan'] ?? 0); ?></td>
                              <td>100%</td>
                              <td>Semua kunjungan</td>
                            </tr>
                            <tr>
                              <td>Kunjungan Selesai</td>
                              <td><?php echo formatAngka($stats_kunjungan['selesai'] ?? 0); ?></td>
                              <td><?php echo $persentase_selesai; ?>%</td>
                              <td>Status selesai</td>
                            </tr>
                            <tr>
                              <td>Peserta Unik</td>
                              <td><?php echo formatAngka($stats_kunjungan['peserta_unik'] ?? 0); ?></td>
                              <td>-</td>
                              <td>Peserta yang berkunjung</td>
                            </tr>
                            <tr>
                              <td>Total Biaya</td>
                              <td><?php echo formatRupiah($stats_kunjungan['total_biaya_kunjungan'] ?? 0); ?></td>
                              <td>-</td>
                              <td>Biaya administrasi</td>
                            </tr>
                            
                            <tr>
                              <td rowspan="4" class="align-middle"><strong>KLAIM</strong></td>
                              <td>Total Klaim</td>
                              <td><?php echo formatAngka($stats_klaim['total_klaim'] ?? 0); ?></td>
                              <td>100%</td>
                              <td>Semua pengajuan klaim</td>
                            </tr>
                            <tr>
                              <td>Klaim Disetujui</td>
                              <td><?php echo formatAngka($stats_klaim['klaim_approved'] ?? 0); ?></td>
                              <td><?php echo $persentase_klaim_approved; ?>%</td>
                              <td>Status approved</td>
                            </tr>
                            <tr>
                              <td>Klaim Pending</td>
                              <td><?php echo formatAngka($stats_klaim['klaim_pending'] ?? 0); ?></td>
                              <td><?php echo $persentase_klaim_pending; ?>%</td>
                              <td>Menunggu verifikasi</td>
                            </tr>
                            <tr>
                              <td>Total Nilai Klaim</td>
                              <td><?php echo formatRupiah($stats_klaim['total_nominal_klaim'] ?? 0); ?></td>
                              <td>-</td>
                              <td>Nilai total klaim</td>
                            </tr>
                            
                            <tr>
                              <td rowspan="4" class="align-middle"><strong>KEUANGAN</strong></td>
                              <td>Total Pembayaran</td>
                              <td><?php echo formatRupiah($stats_keuangan['total_keseluruhan'] ?? 0); ?></td>
                              <td>100%</td>
                              <td>Total penerimaan</td>
                            </tr>
                            <tr>
                              <td>Total Iuran</td>
                              <td><?php echo formatRupiah($stats_keuangan['total_iuran'] ?? 0); ?></td>
                              <td><?php echo hitungPersentase($stats_keuangan['total_iuran'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1); ?>%</td>
                              <td>Dari total penerimaan</td>
                            </tr>
                            <tr>
                              <td>Rata-rata Pembayaran</td>
                              <td><?php echo formatRupiah($stats_keuangan['rata_pembayaran'] ?? 0); ?></td>
                              <td>-</td>
                              <td>Per transaksi</td>
                            </tr>
                            <tr>
                              <td>Transaksi Sukses</td>
                              <td><?php echo formatAngka($stats_keuangan['total_pembayaran'] ?? 0); ?></td>
                              <td>100%</td>
                              <td>Pembayaran verified</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- FOOTER -->
              <footer class="footer mt-4 no-print">
                <div class="row">
                  <div class="col-sm-6 text-center text-sm-left mt-3 mt-sm-0">
                    <small class="text-muted d-block">
                      <i class="mdi mdi-calendar mr-1"></i>
                      Statistik BPJS &copy; <?php echo date('Y'); ?>
                    </small>
                    <small class="text-gray mt-2">
                      <i class="mdi mdi-clock-outline mr-1"></i>
                      Diperbarui: <?php echo date('d-m-Y H:i:s'); ?>
                    </small>
                    <small class="text-gray d-block mt-1">
                      <i class="mdi mdi-filter mr-1"></i>
                      Periode: <?php echo date('d M Y', strtotime($filter_tanggal_mulai)); ?> - <?php echo date('d M Y', strtotime($filter_tanggal_selesai)); ?>
                    </small>
                  </div>
                  <div class="col-sm-6 text-center text-sm-right order-sm-1">
                    <ul class="text-gray">
                      <li>User: <?php echo htmlspecialchars($user['username']); ?></li>
                      <li>ID: <?php echo $user_id; ?></li>
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
                <h3 style="margin: 5px 0 10px 0; color: #333;">LAPORAN STATISTIK BPJS KESEHATAN</h3>
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
                  <strong>INFORMASI LAPORAN STATISTIK</strong><br>
                  <strong>Periode:</strong> 
                  <?php 
                  if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
                    echo date('d F Y', strtotime($filter_tanggal_mulai)) . ' s/d ' . date('d F Y', strtotime($filter_tanggal_selesai));
                  } else {
                    echo 'Semua Periode';
                  }
                  ?><br>
                  <strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i:s'); ?><br>
                  <strong>Pengguna:</strong> <?php echo htmlspecialchars($user['username']); ?><br>
                  <strong>Tahun:</strong> <?php echo $filter_tahun; ?> | 
                  <strong>Bulan:</strong> <?php echo $filter_bulan == 'all' ? 'Semua Bulan' : $bulan_list[$filter_bulan] ?? $filter_bulan; ?>
                </td>
                <td style="width: 50%; vertical-align: top;">
                  <strong>RINGKASAN UTAMA</strong><br>
                  <strong>Total Peserta:</strong> <?php echo formatAngka($stats_peserta['total'] ?? 0); ?><br>
                  <strong>Total Kunjungan:</strong> <?php echo formatAngka($stats_kunjungan['total_kunjungan'] ?? 0); ?><br>
                  <strong>Total Klaim:</strong> <?php echo formatAngka($stats_klaim['total_klaim'] ?? 0); ?><br>
                  <strong>Total Penerimaan:</strong> <?php echo formatRupiah($stats_keuangan['total_keseluruhan'] ?? 0); ?><br>
                  <strong>Peserta Aktif:</strong> <?php echo $stats_peserta['aktif'] ?? 0; ?> 
                  (<?php echo $persentase_aktif; ?>%)<br>
                  <strong>Klaim Disetujui:</strong> <?php echo $stats_klaim['klaim_approved'] ?? 0; ?> 
                  (<?php echo $persentase_klaim_approved; ?>%)
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
      
      <script>
      // Inisialisasi Chart.js
      let trendChart, klaimTrendChart, segmenChart, statusKlaimChart;
      
      document.addEventListener('DOMContentLoaded', function() {
          // Trend Chart (Line Chart)
          const trendCtx = document.getElementById('trendChart').getContext('2d');
          trendChart = new Chart(trendCtx, {
              type: 'line',
              data: {
                  labels: <?php echo json_encode(array_reverse($trend_labels)); ?>,
                  datasets: [
                      {
                          label: 'Jumlah Peserta',
                          data: <?php echo json_encode(array_reverse($trend_peserta)); ?>,
                          borderColor: '#0066cc',
                          backgroundColor: 'rgba(0, 102, 204, 0.1)',
                          borderWidth: 2,
                          fill: true,
                          tension: 0.4,
                          yAxisID: 'y'
                      },
                      {
                          label: 'Total Pembayaran (juta)',
                          data: <?php echo json_encode(array_map(function($val) { return $val / 1000000; }, array_reverse($trend_pembayaran))); ?>,
                          borderColor: '#28a745',
                          backgroundColor: 'rgba(40, 167, 69, 0.1)',
                          borderWidth: 2,
                          fill: true,
                          tension: 0.4,
                          yAxisID: 'y1'
                      }
                  ]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  interaction: {
                      mode: 'index',
                      intersect: false,
                  },
                  scales: {
                      y: {
                          type: 'linear',
                          display: true,
                          position: 'left',
                          title: {
                              display: true,
                              text: 'Jumlah Peserta'
                          },
                          grid: {
                              drawOnChartArea: true,
                          },
                      },
                      y1: {
                          type: 'linear',
                          display: true,
                          position: 'right',
                          title: {
                              display: true,
                              text: 'Total Pembayaran (juta Rp)'
                          },
                          grid: {
                              drawOnChartArea: false,
                          },
                      },
                  },
                  plugins: {
                      legend: {
                          position: 'top',
                      },
                      tooltip: {
                          callbacks: {
                              label: function(context) {
                                  let label = context.dataset.label || '';
                                  if (label.includes('Pembayaran')) {
                                      return label + ': Rp ' + (context.raw * 1000000).toLocaleString('id-ID');
                                  }
                                  return label + ': ' + context.raw.toLocaleString('id-ID');
                              }
                          }
                      }
                  }
              }
          });
          
          // Trend Klaim Chart (Bar Chart)
          const klaimTrendCtx = document.getElementById('klaimTrendChart').getContext('2d');
          klaimTrendChart = new Chart(klaimTrendCtx, {
              type: 'bar',
              data: {
                  labels: <?php echo json_encode(array_reverse($trend_klaim_labels)); ?>,
                  datasets: [
                      {
                          label: 'Jumlah Klaim',
                          data: <?php echo json_encode(array_reverse($trend_klaim_jumlah)); ?>,
                          backgroundColor: 'rgba(253, 126, 20, 0.8)',
                          borderColor: '#fd7e14',
                          borderWidth: 1,
                          yAxisID: 'y'
                      },
                      {
                          label: 'Total Nilai Klaim (juta)',
                          data: <?php echo json_encode(array_map(function($val) { return $val / 1000000; }, array_reverse($trend_klaim_nominal))); ?>,
                          backgroundColor: 'rgba(0, 102, 204, 0.6)',
                          borderColor: '#0066cc',
                          borderWidth: 1,
                          yAxisID: 'y1',
                          type: 'line'
                      }
                  ]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  interaction: {
                      mode: 'index',
                      intersect: false,
                  },
                  scales: {
                      y: {
                          type: 'linear',
                          display: true,
                          position: 'left',
                          title: {
                              display: true,
                              text: 'Jumlah Klaim'
                          },
                          grid: {
                              drawOnChartArea: true,
                          },
                      },
                      y1: {
                          type: 'linear',
                          display: true,
                          position: 'right',
                          title: {
                              display: true,
                              text: 'Total Nilai (juta Rp)'
                          },
                          grid: {
                              drawOnChartArea: false,
                          },
                      },
                  },
                  plugins: {
                      legend: {
                          position: 'top',
                      },
                      tooltip: {
                          callbacks: {
                              label: function(context) {
                                  let label = context.dataset.label || '';
                                  if (label.includes('Nilai')) {
                                      return label + ': Rp ' + (context.raw * 1000000).toLocaleString('id-ID');
                                  }
                                  return label + ': ' + context.raw.toLocaleString('id-ID');
                              }
                          }
                      }
                  }
              }
          });
          
          // Segmen Chart (Doughnut Chart)
          const segmenCtx = document.getElementById('segmenChart').getContext('2d');
          segmenChart = new Chart(segmenCtx, {
              type: 'doughnut',
              data: {
                  labels: <?php echo json_encode($segmen_labels); ?>,
                  datasets: [{
                      data: <?php echo json_encode($segmen_data); ?>,
                      backgroundColor: [
                          '#0066cc', // PPU - Blue
                          '#28a745', // PBPU - Green
                          '#ffc107'  // PBI - Yellow
                      ],
                      borderColor: [
                          '#0056b3',
                          '#1e7e34',
                          '#e0a800'
                      ],
                      borderWidth: 1
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                      legend: {
                          position: 'bottom',
                      },
                      tooltip: {
                          callbacks: {
                              label: function(context) {
                                  const label = context.label || '';
                                  const value = context.raw || 0;
                                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                  const percentage = Math.round((value / total) * 100);
                                  return `${label}: ${value.toLocaleString('id-ID')} (${percentage}%)`;
                              }
                          }
                      }
                  }
              }
          });
          
          // Status Klaim Chart (Pie Chart)
          const statusKlaimCtx = document.getElementById('statusKlaimChart').getContext('2d');
          const statusKlaimData = [
              <?php echo $stats_klaim['klaim_pending'] ?? 0; ?>,
              <?php echo $stats_klaim['klaim_approved'] ?? 0; ?>,
              <?php echo $stats_klaim['klaim_rejected'] ?? 0; ?>
          ];
          
          statusKlaimChart = new Chart(statusKlaimCtx, {
              type: 'pie',
              data: {
                  labels: ['Pending', 'Approved', 'Rejected'],
                  datasets: [{
                      data: statusKlaimData,
                      backgroundColor: [
                          '#ffc107', // Pending - Yellow
                          '#28a745', // Approved - Green
                          '#dc3545'  // Rejected - Red
                      ],
                      borderColor: [
                          '#e0a800',
                          '#1e7e34',
                          '#bd2130'
                      ],
                      borderWidth: 1
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                      legend: {
                          position: 'bottom',
                      },
                      tooltip: {
                          callbacks: {
                              label: function(context) {
                                  const label = context.label || '';
                                  const value = context.raw || 0;
                                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                  const percentage = Math.round((value / total) * 100);
                                  return `${label}: ${value.toLocaleString('id-ID')} (${percentage}%)`;
                              }
                          }
                      }
                  }
              }
          });
          
          // Set tanggal default jika kosong
          const tglMulai = document.querySelector('input[name="tgl_mulai"]');
          const tglSelesai = document.querySelector('input[name="tgl_selesai"]');
          
          if (tglMulai && !tglMulai.value) {
              const today = new Date();
              const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
              tglMulai.value = firstDay.toISOString().split('T')[0];
          }
          
          if (tglSelesai && !tglSelesai.value) {
              const today = new Date();
              tglSelesai.value = today.toISOString().split('T')[0];
          }
      });
      
      // Fungsi export ke Excel
      function exportToExcel() {
          try {
              // Buat workbook baru
              const wb = XLSX.utils.book_new();
              
              // Data untuk sheet statistik
              const statistikData = [
                  ['DASHBOARD STATISTIK BPJS'],
                  ['Periode:', '<?php echo date("d M Y", strtotime($filter_tanggal_mulai)); ?> - <?php echo date("d M Y", strtotime($filter_tanggal_selesai)); ?>'],
                  ['Dicetak:', '<?php echo date("d-m-Y H:i:s"); ?>'],
                  [''],
                  ['KATEGORI STATISTIK', 'INDIKATOR', 'NILAI', 'PERSENTASE', 'KETERANGAN'],
                  
                  // Data Peserta
                  ['PESERTA', 'Total Peserta', <?php echo $stats_peserta['total'] ?? 0; ?>, '100%', 'Semua peserta terdaftar'],
                  ['PESERTA', 'Peserta Aktif', <?php echo $stats_peserta['aktif'] ?? 0; ?>, '<?php echo $persentase_aktif; ?>%', 'Status aktif'],
                  ['PESERTA', 'Sudah Membayar', <?php echo $stats_peserta['bayar_verified'] ?? 0; ?>, '<?php echo $persentase_bayar; ?>%', 'Lunas/verified'],
                  ['PESERTA', 'Total Iuran Aktual', <?php echo $stats_peserta['total_iuran_aktual'] ?? 0; ?>, '-', 'Per bulan'],
                  
                  // Data Kunjungan
                  ['KUNJUNGAN', 'Total Kunjungan', <?php echo $stats_kunjungan['total_kunjungan'] ?? 0; ?>, '100%', 'Semua kunjungan'],
                  ['KUNJUNGAN', 'Kunjungan Selesai', <?php echo $stats_kunjungan['selesai'] ?? 0; ?>, '<?php echo $persentase_selesai; ?>%', 'Status selesai'],
                  ['KUNJUNGAN', 'Peserta Unik', <?php echo $stats_kunjungan['peserta_unik'] ?? 0; ?>, '-', 'Peserta yang berkunjung'],
                  ['KUNJUNGAN', 'Total Biaya', <?php echo $stats_kunjungan['total_biaya_kunjungan'] ?? 0; ?>, '-', 'Biaya administrasi'],
                  
                  // Data Klaim
                  ['KLAIM', 'Total Klaim', <?php echo $stats_klaim['total_klaim'] ?? 0; ?>, '100%', 'Semua pengajuan klaim'],
                  ['KLAIM', 'Klaim Disetujui', <?php echo $stats_klaim['klaim_approved'] ?? 0; ?>, '<?php echo $persentase_klaim_approved; ?>%', 'Status approved'],
                  ['KLAIM', 'Klaim Pending', <?php echo $stats_klaim['klaim_pending'] ?? 0; ?>, '<?php echo $persentase_klaim_pending; ?>%', 'Menunggu verifikasi'],
                  ['KLAIM', 'Total Nilai Klaim', <?php echo $stats_klaim['total_nominal_klaim'] ?? 0; ?>, '-', 'Nilai total klaim'],
                  
                  // Data Keuangan
                  ['KEUANGAN', 'Total Pembayaran', <?php echo $stats_keuangan['total_keseluruhan'] ?? 0; ?>, '100%', 'Total penerimaan'],
                  ['KEUANGAN', 'Total Iuran', <?php echo $stats_keuangan['total_iuran'] ?? 0; ?>, '<?php echo hitungPersentase($stats_keuangan['total_iuran'] ?? 0, $stats_keuangan['total_keseluruhan'] ?? 1); ?>%', 'Dari total penerimaan'],
                  ['KEUANGAN', 'Rata-rata Pembayaran', <?php echo $stats_keuangan['rata_pembayaran'] ?? 0; ?>, '-', 'Per transaksi'],
                  ['KEUANGAN', 'Transaksi Sukses', <?php echo $stats_keuangan['total_pembayaran'] ?? 0; ?>, '100%', 'Pembayaran verified'],
              ];
              
              // Convert ke worksheet
              const ws = XLSX.utils.aoa_to_sheet(statistikData);
              
              // Tambah worksheet ke workbook
              XLSX.utils.book_append_sheet(wb, ws, "Statistik");
              
              // Download file
              const fileName = `Statistik_BPJS_<?php echo date('Y-m-d'); ?>.xlsx`;
              XLSX.writeFile(wb, fileName);
              
              alert('Statistik berhasil diekspor ke Excel!');
          } catch (error) {
              alert('Error saat mengekspor ke Excel: ' + error.message);
              console.error(error);
          }
      }
      
      // Fungsi print statistik
      function printStatistik() {
          const printContents = document.querySelector('.content-viewport').innerHTML;
          const originalContents = document.body.innerHTML;
          const printHeader = document.getElementById('print-header').innerHTML;
          
          // Clone elemen yang ingin dicetak
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = printContents;
          
          // Hapus elemen yang tidak perlu dicetak
          const elementsToRemove = tempDiv.querySelectorAll('.no-print, .export-btn-group, .filter-card, .filter-form, .sidebar, .t-header, [data-toggle="tooltip"], button, a.btn, canvas, .chart-container, .insight-card');
          elementsToRemove.forEach(el => el.remove());
          
          // Dapatkan nama user untuk tanda tangan
          const userName = "<?php echo $user['full_name'] ? htmlspecialchars($user['full_name']) : htmlspecialchars($user['username']); ?>";
          const userPosition = "<?php echo $user['username'] == 'admin' ? 'Administrator' : 'Petugas BPJS'; ?>";
          
          // Tambahkan tanda tangan
          const signatureSection = `
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; page-break-before: always;">
              <table style="width: 100%;">
                <tr>
                  <td style="width: 70%;">
                    <p style="font-size: 11px; color: #666;">
                      <strong>Keterangan:</strong><br>
                      1. Laporan Statistik ini dicetak secara otomatis dari Sistem Informasi BPJS Kesehatan<br>
                      2. Data diambil dari database yang diperbarui hingga <?php echo date('d F Y H:i:s'); ?><br>
                      3. Laporan ini sah dan dapat dipertanggungjawabkan<br>
                      4. Untuk informasi lebih lanjut hubungi call center 1500-400<br>
                      5. Statistik meliputi: Data Peserta, Kunjungan, Klaim, dan Keuangan
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
            '<html><head><title>Laporan Statistik BPJS</title>' +
            '<style>' +
            'body {padding: 20px; font-family: "Arial", "Helvetica", sans-serif;}' +
            'table {width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px;}' +
            'th {background-color: #0066cc; color: white; padding: 8px; text-align: center; border: 1px solid #0056b3; font-weight: bold;}' +
            'td {padding: 6px; border: 1px solid #ddd; vertical-align: middle; text-align: center;}' +
            '.stat-card {border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px;}' +
            '.stat-value {font-size: 24px; font-weight: bold; color: #0066cc;}' +
            '.stat-label {font-size: 12px; color: #666;}' +
            '.comparison-card {border: 1px solid #eee; border-radius: 6px; padding: 10px; margin-bottom: 10px;}' +
            '.comparison-value {font-size: 16px; font-weight: bold;}' +
            '.comparison-title {font-size: 12px; color: #666;}' +
            '.progress-stat {height: 8px; border-radius: 4px; background-color: #f0f0f0; margin-top: 5px;}' +
            '.progress-bar {height: 100%; border-radius: 4px;}' +
            '.bg-primary {background-color: #0066cc !important;}' +
            '.bg-success {background-color: #28a745 !important;}' +
            '.bg-warning {background-color: #ffc107 !important;}' +
            '.bg-info {background-color: #17a2b8 !important;}' +
            '.bg-danger {background-color: #dc3545 !important;}' +
            '.bg-purple {background-color: #6f42c1 !important;}' +
            '.bg-teal {background-color: #20c997 !important;}' +
            '.print-header h2, .print-header h3 {font-family: "Arial", sans-serif;}' +
            '.print-header p {margin: 3px 0;}' +
            '@media print {' +
            '  @page { margin: 0.5cm; }' +
            '  body { padding: 10px; }' +
            '  .page-break { page-break-before: always; }' +
            '}' +
            '</style>' +
            '</head><body>' +
            printHeader +
            tempDiv.innerHTML +
            signatureSection +
            '</body></html>';
          
          window.print();
          setTimeout(function() {
              document.body.innerHTML = originalContents;
              window.location.reload();
          }, 100);
      }
      
      // Auto submit form saat enter
      const filterForm = document.querySelector('.filter-form');
      if (filterForm) {
          filterForm.addEventListener('keypress', function(e) {
              if (e.key === 'Enter') {
                  e.preventDefault();
                  filterForm.submit();
              }
          });
      }
      </script>
    </body>
  </html>
  <?php 
  // Tutup koneksi database
  if (isset($conn)) {
      mysqli_close($conn);
  }
  ?>