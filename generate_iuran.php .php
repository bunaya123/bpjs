<?php
/**
 * Script untuk generate iuran otomatis (CRON JOB)
 * Jadwalkan: 0 0 1 * * (setiap tanggal 1 bulan)
 */

require_once '../config.php';

// Set header untuk CLI
if (php_sapi_name() === 'cli') {
    echo "Starting iuran generation...\n";
} else {
    // Jika diakses via web, cek auth
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        die("Access denied.");
    }
}

// Function untuk generate nomor transaksi
function generateNoTransaksi($conn) {
    $year_month = date('ym');
    $sql = "SELECT MAX(CAST(SUBSTRING(no_transaksi, 11) AS UNSIGNED)) as last_num 
            FROM iuran 
            WHERE no_transaksi LIKE 'TRX-$year_month%'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $next_num = ($row['last_num'] ?? 0) + 1;
    
    return sprintf('TRX-%s-%05d', $year_month, $next_num);
}

// Main process
try {
    echo "========================================\n";
    echo "IURAN GENERATION - " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n\n";
    
    // Ambil semua peserta aktif
    $sql_peserta = "SELECT p.id as peserta_id, k.iuran_per_bulan 
                   FROM peserta p 
                   JOIN kelas k ON p.kelas_id = k.id 
                   WHERE p.status = 'active'";
    $result_peserta = mysqli_query($conn, $sql_peserta);
    $total_peserta = mysqli_num_rows($result_peserta);
    
    echo "Total peserta aktif: $total_peserta\n";
    
    $next_month = date('Y-m', strtotime('+1 month'));
    $due_date = date('Y-m-d', strtotime('+1 month 10 days'));
    
    echo "Bulan yang digenerate: $next_month\n";
    echo "Tanggal jatuh tempo: $due_date\n\n";
    
    $generated = 0;
    $skipped = 0;
    
    while ($peserta = mysqli_fetch_assoc($result_peserta)) {
        // Cek apakah sudah ada iuran untuk bulan depan
        $sql_check = "SELECT id FROM iuran 
                     WHERE peserta_id = {$peserta['peserta_id']} 
                     AND bulan_tahun = '$next_month'";
        $result_check = mysqli_query($conn, $sql_check);
        
        if (mysqli_num_rows($result_check) == 0) {
            // Generate iuran baru
            $no_transaksi = generateNoTransaksi($conn);
            
            $sql_insert = "INSERT INTO iuran (
                peserta_id, no_transaksi, bulan_tahun, jumlah, 
                total_bayar, tanggal_jatuh_tempo, status, created_at
            ) VALUES (
                {$peserta['peserta_id']}, 
                '$no_transaksi',
                '$next_month',
                {$peserta['iuran_per_bulan']},
                {$peserta['iuran_per_bulan']},
                '$due_date',
                'Belum Bayar',
                NOW()
            )";
            
            if (mysqli_query($conn, $sql_insert)) {
                $generated++;
                echo "[✓] Generated for peserta ID {$peserta['peserta_id']}: {$peserta['iuran_per_bulan']}\n";
            } else {
                echo "[✗] Error: " . mysqli_error($conn) . "\n";
            }
        } else {
            $skipped++;
            echo "[i] Skipped (already exists) for peserta ID {$peserta['peserta_id']}\n";
        }
    }
    
    echo "\n========================================\n";
    echo "SUMMARY\n";
    echo "========================================\n";
    echo "Generated: $generated\n";
    echo "Skipped: $skipped\n";
    echo "Total: $total_peserta\n";
    echo "========================================\n";
    
    // Update denda untuk iuran yang lewat tempo
    echo "\nUpdating overdue iuran...\n";
    
    $sql_update_denda = "
        UPDATE iuran i
        SET 
            denda = CASE 
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 1 AND 7 
                THEN LEAST(jumlah * 0.005, 10000)
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 8 AND 14 
                THEN LEAST(jumlah * 0.01, 20000)
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 15 AND 30 
                THEN LEAST(jumlah * 0.02, 50000)
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) > 30 
                THEN jumlah * 0.05
                ELSE 0
            END,
            total_bayar = jumlah + CASE 
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 1 AND 7 
                THEN LEAST(jumlah * 0.005, 10000)
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 8 AND 14 
                THEN LEAST(jumlah * 0.01, 20000)
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) BETWEEN 15 AND 30 
                THEN LEAST(jumlah * 0.02, 50000)
                WHEN DATEDIFF(CURDATE(), tanggal_jatuh_tempo) > 30 
                THEN jumlah * 0.05
                ELSE 0
            END,
            status = 'Lewat Jatuh Tempo',
            updated_at = NOW()
        WHERE status IN ('Belum Bayar', 'Lewat Jatuh Tempo')
        AND tanggal_jatuh_tempo < CURDATE()
        AND DATEDIFF(CURDATE(), tanggal_jatuh_tempo) > 0";
    
    if (mysqli_query($conn, $sql_update_denda)) {
        $affected = mysqli_affected_rows($conn);
        echo "Updated $affected overdue iuran with penalties\n";
    }
    
    echo "\nIURAN GENERATION COMPLETED SUCCESSFULLY\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}