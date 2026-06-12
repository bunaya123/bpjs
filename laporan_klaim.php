<?php
session_start();
require_once 'config.php';

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user
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
$default_avatar = '../assets/images/faces/avatar-default.png';

// Update last activity
$_SESSION['last_activity'] = time();

// Handle form actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle cetak/export
$export_type = $_GET['export'] ?? '';

// Tambah/Edit Data Klaim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? 0;
    $peserta_id = $_POST['peserta_id'];
    $no_klaim = $_POST['no_klaim'];
    $diagnosa = $_POST['diagnosa'];
    $nominal_klaim = $_POST['nominal_klaim'];
    $status_klaim = $_POST['status_klaim'];
    $tanggal_klaim = $_POST['tanggal_klaim'];
    $catatan = $_POST['catatan'] ?? '';
    
    // Generate nomor klaim jika kosong
    if (empty($no_klaim)) {
        $no_klaim = 'KL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    if ($id > 0) {
        // Update data klaim
        $sql = "UPDATE klaim SET 
                peserta_id = ?,
                no_klaim = ?,
                diagnosa = ?,
                nominal_klaim = ?,
                status_klaim = ?,
                tanggal_klaim = ?,
                catatan = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issdsssi", 
            $peserta_id, $no_klaim, $diagnosa, $nominal_klaim, 
            $status_klaim, $tanggal_klaim, $catatan, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Data klaim berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Gagal memperbarui data klaim!";
        }
        mysqli_stmt_close($stmt);
    } else {
        // Tambah data klaim baru
        $sql = "INSERT INTO klaim 
                (peserta_id, no_klaim, diagnosa, nominal_klaim, status_klaim, tanggal_klaim, catatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issdsss", 
            $peserta_id, $no_klaim, $diagnosa, $nominal_klaim, 
            $status_klaim, $tanggal_klaim, $catatan);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Data klaim berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan data klaim!";
        }
        mysqli_stmt_close($stmt);
    }
    
    header("Location: klaim.php");
    exit();
}

// Hapus data klaim
if ($action == 'delete' && $id > 0) {
    $sql = "DELETE FROM klaim WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Data klaim berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus data klaim!";
    }
    mysqli_stmt_close($stmt);
    
    header("Location: klaim.php");
    exit();
}

// Handle cetak/export
if ($export_type == 'pdf' || $export_type == 'excel' || $export_type == 'print') {
    // Ambil data dengan filter yang sama
    $search = $_GET['search'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    // Query data klaim
    $sql = "SELECT k.*, p.nama as nama_peserta, p.no_kartu, p.kelas_bpjs, p.alamat 
            FROM klaim k 
            LEFT JOIN peserta p ON k.peserta_id = p.id 
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $sql .= " AND (p.nama LIKE ? OR p.no_kartu LIKE ? OR k.no_klaim LIKE ? OR k.diagnosa LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }
    
    if (!empty($filter_status)) {
        $sql .= " AND k.status_klaim = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
    
    if (!empty($start_date)) {
        $sql .= " AND k.tanggal_klaim >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    
    if (!empty($end_date)) {
        $sql .= " AND k.tanggal_klaim <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    
    $sql .= " ORDER BY k.tanggal_klaim DESC";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $sql);
    }
    
    $klaim_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Hitung statistik
    $total_klaim = count($klaim_list);
    $total_nominal = 0;
    $pending_count = 0;
    $approved_count = 0;
    $rejected_count = 0;
    
    foreach ($klaim_list as $row) {
        $total_nominal += (float)($row['nominal_klaim'] ?? 0);
        if ($row['status_klaim'] == 'pending') $pending_count++;
        if ($row['status_klaim'] == 'approved') $approved_count++;
        if ($row['status_klaim'] == 'rejected') $rejected_count++;
    }
    
    if ($export_type == 'pdf') {
        // Export ke PDF (sederhana)
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="laporan_klaim_' . date('Y-m-d') . '.pdf"');
        
        $html = generatePDFContent($klaim_list, $search, $filter_status, $total_klaim, $total_nominal);
        echo $html;
        exit();
        
    } elseif ($export_type == 'excel') {
        // Export ke Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="laporan_klaim_' . date('Y-m-d') . '.xls"');
        
        echo generateExcelContent($klaim_list, $search, $filter_status, $total_klaim, $total_nominal);
        exit();
        
    } elseif ($export_type == 'print') {
        // Tampilkan halaman untuk print (SAMA SEPERTI LAPORAN PESERTA)
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Cetak Laporan Klaim BPJS - <?php echo date('Y-m-d'); ?></title>
            <style>
                @media print {
                    body { margin: 0; padding: 0; font-family: 'Arial', 'Helvetica', sans-serif; }
                    .no-print { display: none !important; }
                    .print-only { display: block !important; }
                    .page-break { page-break-after: always; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                }
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white; }
                .print-header { margin-bottom: 30px; }
                .header-main { text-align: center; margin-bottom: 20px; }
                .header-main h1 { color: #0066cc; margin: 0 0 5px 0; font-size: 24px; }
                .header-main h2 { color: #333; margin: 0 0 15px 0; font-size: 18px; }
                .header-main p { color: #666; margin: 5px 0; font-size: 12px; }
                .info-box { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #0066cc; border-radius: 5px; }
                .summary { background: #f0f8ff; padding: 15px; margin: 20px 0; border-radius: 5px; border: 1px solid #cce5ff; }
                .summary h4 { margin-top: 0; color: #0066cc; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px; }
                .badge { padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; display: inline-block; }
                .badge-pending { background: #ffc107; color: #000; }
                .badge-approved { background: #28a745; color: #fff; }
                .badge-rejected { background: #dc3545; color: #fff; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .text-left { text-align: left; }
                .table-print { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table-print th { background: #0066cc; color: white; padding: 10px; border: 1px solid #0056b3; }
                .table-print td { padding: 8px; border: 1px solid #ddd; vertical-align: top; }
                .table-print tr:nth-child(even) { background-color: #f9f9f9; }
                .table-print tr:hover { background-color: #f5f5f5; }
                .total-row { background-color: #e8f4ff !important; font-weight: bold; }
                .signature-section { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; }
                .signature-box { text-align: right; float: right; width: 300px; }
                .signature-line { border-top: 1px solid #333; width: 200px; margin-top: 60px; }
                .print-controls { margin-top: 20px; text-align: center; }
                .print-controls button { padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 5px; }
                .logo-section { text-align: center; margin-bottom: 20px; }
                .logo-bpjs { display: inline-block; background: #0066cc; color: white; padding: 10px 20px; border-radius: 5px; font-weight: bold; }
                .logo-rep { display: inline-block; background: #ff0000; color: white; padding: 10px 20px; border-radius: 5px; font-weight: bold; margin-left: 20px; }
                .header-table { width: 100%; margin-bottom: 20px; }
                .header-table td { vertical-align: top; }
                .header-info { font-size: 12px; }
                .status-info { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin: 2px; }
                .status-pending { background: #ffc107; color: #000; }
                .status-approved { background: #28a745; color: #fff; }
                .status-rejected { background: #dc3545; color: #fff; }
                .nominal { font-weight: bold; color: #dc3545; }
                .diagnosa-short { max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
                
                /* SAMA SEPERTI LAPORAN PESERTA */
                .print-header-bpjs { 
                    margin-bottom: 30px; 
                    padding-bottom: 20px; 
                    border-bottom: 2px solid #0066cc;
                }
                .header-bpjs-logo {
                    text-align: center;
                    margin-bottom: 15px;
                }
                .bpjs-logo-box {
                    background: #0066cc;
                    color: white;
                    padding: 10px 25px;
                    border-radius: 5px;
                    display: inline-block;
                    font-weight: bold;
                    font-size: 18px;
                }
                .header-bpjs-title {
                    text-align: center;
                    margin-bottom: 10px;
                }
                .header-bpjs-title h1 {
                    margin: 0;
                    color: #0066cc;
                    font-size: 22px;
                }
                .header-bpjs-title h2 {
                    margin: 5px 0 0 0;
                    color: #333;
                    font-size: 16px;
                }
                .header-bpjs-address {
                    text-align: center;
                    font-size: 11px;
                    color: #666;
                    margin-bottom: 15px;
                }
                .filter-info {
                    background: #f8f9fa;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 15px;
                    font-size: 12px;
                    border-left: 3px solid #28a745;
                }
                .statistics-box {
                    background: #e3f2fd;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    font-size: 12px;
                }
                .statistics-box h4 {
                    margin-top: 0;
                    color: #1565c0;
                    border-bottom: 1px solid #bbdefb;
                    padding-bottom: 5px;
                }
                .stat-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 10px;
                }
                .stat-item {
                    background: white;
                    padding: 10px;
                    border-radius: 5px;
                    text-align: center;
                    border: 1px solid #bbdefb;
                }
                .stat-value {
                    font-size: 18px;
                    font-weight: bold;
                    color: #0066cc;
                }
                .stat-label {
                    font-size: 11px;
                    color: #666;
                }
                .table-bpjs-print {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    font-size: 11px;
                }
                .table-bpjs-print thead th {
                    background: #0066cc;
                    color: white;
                    padding: 8px;
                    border: 1px solid #0056b3;
                    text-align: center;
                    font-weight: bold;
                }
                .table-bpjs-print tbody td {
                    padding: 6px;
                    border: 1px solid #ddd;
                    vertical-align: middle;
                }
                .table-bpjs-print tbody tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                .table-bpjs-print tfoot td {
                    padding: 8px;
                    border: 1px solid #ddd;
                    background-color: #e9ecef;
                    font-weight: bold;
                }
                .signature-area {
                    margin-top: 50px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                }
                .signature-table {
                    width: 100%;
                    margin-top: 40px;
                }
                .signature-cell {
                    text-align: center;
                    width: 33.33%;
                }
                .signature-line {
                    border-top: 1px solid #333;
                    width: 200px;
                    margin: 0 auto;
                    margin-top: 60px;
                }
                .print-footer {
                    margin-top: 30px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    font-size: 10px;
                    text-align: center;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <!-- HEADER SAMA SEPERTI LAPORAN PESERTA -->
            <div class="print-header-bpjs">
                <div class="header-bpjs-logo">
                    <div class="bpjs-logo-box">
                        BPJS KESEHATAN
                    </div>
                </div>
                
                <div class="header-bpjs-title">
                    <h1>BADAN PENYELENGGARA JAMINAN SOSIAL</h1>
                    <h2>LAPORAN DATA KLAIM BPJS KESEHATAN</h2>
                </div>
                
                <div class="header-bpjs-address">
                    <p><strong>Alamat Kantor:</strong> Jl. Letjen Sutoyo No. 79, Cililitan, Jakarta Timur 13640</p>
                    <p><strong>Telp:</strong> (021) 1500-400 | <strong>Email:</strong> contact@bpjs-kesehatan.go.id</p>
                    <p><strong>Website:</strong> www.bpjs-kesehatan.go.id</p>
                </div>
                
                <div class="filter-info">
                    <table width="100%">
                        <tr>
                            <td width="50%" valign="top">
                                <strong>INFORMASI LAPORAN</strong><br>
                                <strong>Periode:</strong> 
                                <?php 
                                if (!empty($start_date) && !empty($end_date)) {
                                    echo date('d F Y', strtotime($start_date)) . ' s/d ' . date('d F Y', strtotime($end_date));
                                } else {
                                    echo 'Semua Periode';
                                }
                                ?><br>
                                <strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i:s'); ?><br>
                                <strong>Jumlah Data:</strong> <?php echo $total_klaim; ?> Klaim<br>
                                <strong>Status Filter:</strong> 
                                <?php 
                                $filter_active = [];
                                if (!empty($filter_status) && $filter_status != 'semua') $filter_active[] = "Status: " . ucfirst($filter_status);
                                if (!empty($search)) $filter_active[] = "Pencarian: $search";
                                if (!empty($start_date)) $filter_active[] = "Dari: " . date('d/m/Y', strtotime($start_date));
                                if (!empty($end_date)) $filter_active[] = "Sampai: " . date('d/m/Y', strtotime($end_date));
                                
                                echo empty($filter_active) ? 'Semua Data' : implode(', ', $filter_active);
                                ?>
                            </td>
                            <td width="50%" valign="top">
                                <strong>STATISTIK KLAIM</strong><br>
                                <strong>Total Nilai Klaim:</strong> Rp <?php echo number_format($total_nominal, 0, ',', '.'); ?><br>
                                <strong>Pending:</strong> <?php echo $pending_count; ?> | 
                                <strong>Approved:</strong> <?php echo $approved_count; ?> | 
                                <strong>Rejected:</strong> <?php echo $rejected_count; ?><br>
                                <strong>Rata-rata per Klaim:</strong> Rp <?php echo number_format($total_klaim > 0 ? $total_nominal / $total_klaim : 0, 0, ',', '.'); ?><br>
                                <strong>Persentase Approved:</strong> 
                                <?php 
                                if ($total_klaim > 0) {
                                    echo round(($approved_count / $total_klaim) * 100, 2) . '%';
                                } else {
                                    echo '0%';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="statistics-box">
                    <h4>STATISTIK DETAIL</h4>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($total_klaim); ?></div>
                            <div class="stat-label">Total Klaim</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">Rp <?php echo number_format($total_nominal, 0, ',', '.'); ?></div>
                            <div class="stat-label">Total Nilai</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($pending_count); ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($approved_count); ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TABEL DATA KLAIM -->
            <table class="table-bpjs-print">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th width="120">No. Klaim</th>
                        <th width="150">Nama Peserta</th>
                        <th width="100">No. Kartu</th>
                        <th width="150">Diagnosa</th>
                        <th width="100">Nominal</th>
                        <th width="80">Status</th>
                        <th width="80">Tanggal</th>
                        <th width="100">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($klaim_list) > 0): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($klaim_list as $row): 
                            // Tentukan class untuk status badge
                            $status_class = '';
                            if ($row['status_klaim'] == 'pending') {
                                $status_class = 'status-pending';
                            } elseif ($row['status_klaim'] == 'approved') {
                                $status_class = 'status-approved';
                            } else {
                                $status_class = 'status-rejected';
                            }
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['no_klaim']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nama_peserta']); ?></td>
                            <td><?php echo htmlspecialchars($row['no_kartu']); ?></td>
                            <td class="diagnosa-short" title="<?php echo htmlspecialchars($row['diagnosa']); ?>">
                                <?php 
                                $diagnosa = $row['diagnosa'] ?? '-';
                                if (strlen($diagnosa) > 30) {
                                    echo htmlspecialchars(substr($diagnosa, 0, 30)) . '...';
                                } else {
                                    echo htmlspecialchars($diagnosa);
                                }
                                ?>
                            </td>
                            <td class="nominal text-right">Rp <?php echo number_format($row['nominal_klaim'], 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <span class="status-info <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['status_klaim'])); ?>
                                </span>
                            </td>
                            <td class="text-center"><?php echo date('d/m/Y', strtotime($row['tanggal_klaim'])); ?></td>
                            <td class="text-center">
                                <?php if (!empty($row['catatan'])): ?>
                                    <span title="<?php echo htmlspecialchars($row['catatan']); ?>">
                                        <i class="mdi mdi-note-text" style="color: #666;"></i> Ada
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 20px;">
                                <em>Tidak ada data klaim ditemukan</em>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (count($klaim_list) > 0): ?>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right"><strong>TOTAL KLAIM:</strong></td>
                        <td class="nominal text-right"><strong>Rp <?php echo number_format($total_nominal, 0, ',', '.'); ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            
            <!-- SIGNATURE SECTION SAMA SEPERTI LAPORAN PESERTA -->
            <div class="signature-area">
                <table class="signature-table">
                    <tr>
                        <td class="signature-cell">
                            <p>Mengetahui,</p>
                            <div class="signature-line"></div>
                            <p><strong>Kepala Cabang</strong></p>
                            <p>BPJS Kesehatan</p>
                        </td>
                        <td class="signature-cell">
                            <p>Diperiksa oleh,</p>
                            <div class="signature-line"></div>
                            <p><strong>Supervisor</strong></p>
                            <p>Divisi Klaim</p>
                        </td>
                        <td class="signature-cell">
                            <p>Dibuat oleh,</p>
                            <div class="signature-line"></div>
                            <p><strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong></p>
                            <p><?php echo $user['username'] == 'admin' ? 'Administrator' : 'Petugas BPJS'; ?></p>
                            <p>ID: <?php echo htmlspecialchars($user['id']); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="print-footer">
                    <p><strong>Keterangan:</strong></p>
                    <p>1. Laporan ini dicetak secara otomatis dari Sistem Informasi BPJS Kesehatan</p>
                    <p>2. Data diambil dari database yang diperbarui hingga <?php echo date('d F Y H:i:s'); ?></p>
                    <p>3. Laporan ini sah dan dapat dipertanggungjawabkan</p>
                    <p>4. Untuk informasi lebih lanjut hubungi call center 1500-400</p>
                    <p>&copy; <?php echo date('Y'); ?> BPJS Kesehatan. Hak Cipta Dilindungi Undang-Undang.</p>
                </div>
            </div>
            
            <!-- CONTROLS UNTUK PRINT -->
            <div class="print-controls no-print">
                <button onclick="window.print()" style="background: #0066cc; color: white; border: none;">
                    <i class="mdi mdi-printer"></i> Cetak Laporan
                </button>
                <button onclick="window.close()" style="background: #dc3545; color: white; border: none; margin-left: 10px;">
                    <i class="mdi mdi-close"></i> Tutup
                </button>
            </div>
            
            <script>
                window.onload = function() {
                    // Auto print jika diinginkan
                    // window.print();
                };
                
                document.addEventListener('keydown', function(e) {
                    // Ctrl+P untuk print
                    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                        e.preventDefault();
                        window.print();
                    }
                    // Esc untuk tutup
                    if (e.key === 'Escape') {
                        window.close();
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit();
    }
}

// Ambil data untuk edit
$klaim_data = null;
if ($action == 'edit' && $id > 0) {
    $sql = "SELECT k.*, p.nama as nama_peserta 
            FROM klaim k 
            LEFT JOIN peserta p ON k.peserta_id = p.id 
            WHERE k.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $klaim_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Ambil data peserta untuk dropdown
$sql_peserta = "SELECT id, no_kartu, nama FROM peserta WHERE status = 'active' ORDER BY nama";
$result_peserta = mysqli_query($conn, $sql_peserta);
$peserta_list = mysqli_fetch_all($result_peserta, MYSQLI_ASSOC);

// Query data klaim untuk tabel
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Query data klaim
$sql = "SELECT k.*, p.nama as nama_peserta, p.no_kartu, p.kelas_bpjs 
        FROM klaim k 
        LEFT JOIN peserta p ON k.peserta_id = p.id 
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (p.nama LIKE ? OR p.no_kartu LIKE ? OR k.no_klaim LIKE ? OR k.diagnosa LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if (!empty($filter_status)) {
    $sql .= " AND k.status_klaim = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($start_date)) {
    $sql .= " AND k.tanggal_klaim >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $sql .= " AND k.tanggal_klaim <= ?";
    $params[] = $end_date;
    $types .= 's';
}

// Sorting berdasarkan tanggal klaim
$sql .= " ORDER BY k.tanggal_klaim DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

$klaim_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Hitung statistik untuk tampilan normal
$total_klaim = count($klaim_list);
$total_nominal = 0;
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

foreach ($klaim_list as $row) {
    $total_nominal += (float)($row['nominal_klaim'] ?? 0);
    if ($row['status_klaim'] == 'pending') $pending_count++;
    if ($row['status_klaim'] == 'approved') $approved_count++;
    if ($row['status_klaim'] == 'rejected') $rejected_count++;
}

// Fungsi untuk generate konten PDF
function generatePDFContent($data, $search, $filter_status, $total, $total_nominal) {
    $html = '<h1 style="text-align:center; color:#0066cc;">Laporan Data Klaim BPJS</h1>';
    $html .= '<p style="text-align:center;">Tanggal: ' . date('d/m/Y H:i:s') . '</p>';
    $html .= '<p>Total Data: ' . number_format($total) . '</p>';
    $html .= '<p>Total Nilai: Rp ' . number_format($total_nominal, 0, ',', '.') . '</p>';
    return $html;
}

// Fungsi untuk generate konten Excel
function generateExcelContent($data, $search, $filter_status, $total, $total_nominal) {
    $output = "Laporan Data Klaim BPJS\n";
    $output .= "Tanggal: " . date('d/m/Y H:i:s') . "\n";
    $output .= "Total Data: " . number_format($total) . "\n";
    $output .= "Total Nilai: Rp " . number_format($total_nominal, 0, ',', '.') . "\n\n";
    
    $output .= "No\tNo Klaim\tNama Peserta\tNo Kartu\tDiagnosa\tNominal\tStatus\tTanggal\n";
    
    $no = 1;
    foreach ($data as $row) {
        $output .= $no++ . "\t";
        $output .= $row['no_klaim'] . "\t";
        $output .= $row['nama_peserta'] . "\t";
        $output .= $row['no_kartu'] . "\t";
        $output .= $row['diagnosa'] . "\t";
        $output .= number_format($row['nominal_klaim'], 0, ',', '.') . "\t";
        $output .= ucfirst($row['status_klaim']) . "\t";
        $output .= date('d/m/Y', strtotime($row['tanggal_klaim'])) . "\n";
    }
    
    return $output;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manajemen Klaim - BPJS Kesehatan</title>
    
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/iconfonts/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    
    <!-- vendor css for this page -->
    <link rel="stylesheet" href="../assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/shared/style.css">
    
    <!-- Layout styles -->
    <link rel="stylesheet" href="../assets/css/demo_1/style.css">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    
    <style>
        /* BPJS Theme Colors */
        :root {
            --bpjs-primary: #0066cc;
            --bpjs-secondary: #0099ff;
            --bpjs-light-blue: #e6f2ff;
            --bpjs-dark-blue: #004d99;
        }
        
        /* Badge Status Styles */
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
            border: none;
        }
        
        .badge-approved {
            background-color: #28a745;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
            border: none;
        }
        
        .badge-rejected {
            background-color: #dc3545;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
            border: none;
        }
        
        /* Action Buttons */
        .action-buttons .btn {
            margin-right: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        /* Foto Profil */
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
            background-color: #0066cc;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .display-avatar:hover .avatar-edit-btn {
            opacity: 1;
        }
        
        .display-avatar .profile-img.img-lg {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        /* Nominal Cell */
        .nominal-cell {
            font-weight: 600;
            color: #dc3545;
        }
        
        /* BPJS Custom Cards */
        .bpjs-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .bpjs-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        .bpjs-card-primary {
            background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
            color: white;
        }
        
        .bpjs-card-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .bpjs-card-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
        }
        
        .bpjs-card-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            color: white;
        }
        
        /* Header Styles */
        .page-header-bpjs {
            background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        /* Form Styles */
        .form-control-bpjs {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        
        .form-control-bpjs:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        /* Button Styles */
        .btn-bpjs {
            background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-bpjs:hover {
            background: linear-gradient(135deg, #004d99 0%, #0066cc 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 102, 204, 0.3);
        }
        
        /* Search Box */
        .search-box-bpjs {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #0066cc;
        }
        
        /* Status Filter */
        .status-filter-bpjs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .status-filter-bpjs .btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .status-filter-bpjs .btn.active {
            font-weight: 600;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Table Styles */
        .table-bpjs th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #0066cc;
        }
        
        .table-bpjs td {
            vertical-align: middle;
            padding: 12px 15px;
        }
        
        .table-bpjs tbody tr:hover {
            background-color: #f5f9ff;
        }
        
        /* Card Headers */
        .card-header-bpjs {
            background: linear-gradient(135deg, #0066cc 0%, #0099ff 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        /* Footer */
        .footer-bpjs {
            background-color: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            padding: 20px 0;
            margin-top: 40px;
        }
        
        /* Required Field */
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        /* Alert Styles */
        .alert-bpjs {
            border-left: 4px solid;
            border-radius: 6px;
        }
        
        .alert-success {
            border-color: #28a745;
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            border-color: #dc3545;
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Export Button Styles */
        .btn-export {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-export:hover {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        
        .dropdown-export {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-export-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1000;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .dropdown-export-content a {
            color: #495057;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
        }
        
        .dropdown-export-content a:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-export:hover .dropdown-export-content {
            display: block;
        }
        
        /* Print Header (tersembunyi di tampilan normal) */
        .print-header-section {
            display: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .status-filter-bpjs {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                margin-bottom: 5px;
            }
            
            .search-box-bpjs {
                padding: 15px;
            }
            
            .export-buttons {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        /* Date Filter */
        .date-filter {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 3px solid #28a745;
        }
        
        .date-filter label {
            font-weight: 500;
            color: #495057;
        }
        
        /* Progress Bar untuk Statistik */
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .progress-bar-custom div {
            height: 100%;
            display: inline-block;
        }
    </style>
</head>
<body class="header-fixed">

    <!-- partial -->
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
                            onerror="this.style.display='none'; document.getElementById('avatar-default-klaim').style.display='block';">
                    <?php endif; ?>
                    
                    <!-- Foto default -->
                    <img id="avatar-default-klaim" 
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
                <li>
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
                <li>
                    <a href="#laporan" data-toggle="collapse" aria-expanded="false">
                    <span class="link-title">Laporan</span>
                    <i class="mdi mdi-chart-bar link-icon"></i>
                    </a>
                    <ul class="collapse navigation-submenu" id="laporan">
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
            <div class="sidebar-upgrade-banner" style="background: linear-gradient(135deg, var(--bpjs-primary) 0%, var(--bpjs-secondary) 100%); color: white;">
            <p class="text-white">BPJS Kesehatan Member</p>
            <a class="btn upgrade-btn" href="pendaftaran.php" style="background: white; color: var(--bpjs-primary);">Register Now</a>
            </div>
        </div>
        <!-- partial -->
        <div class="page-content-wrapper">
            <div class="page-content-wrapper-inner">
            <div class="content-viewport">    
                <!-- Header -->
                <div class="page-header-bpjs">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-2"><i class="mdi mdi-file-document-box-check mr-2"></i> Laporan Data Klaim BPJS</h3>
                           
                            <!-- Tombol Export akan ditampilkan nanti di bagian Search Box -->
                        </div>
                    </div>
                </div>
                
                <!-- Notifikasi -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-bpjs alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle"></i> <?php echo $_SESSION['success']; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-bpjs alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle"></i> <?php echo $_SESSION['error']; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if ($action == 'add' || $action == 'edit'): ?>
                
                <!-- Form Tambah/Edit Klaim -->
                <div class="row">
                    <div class="col-12">
                        <div class="grid bpjs-card">
                            <div class="grid-body">
                                <div class="card-header-bpjs">
                                    <h5 class="mb-0">
                                        <i class="mdi mdi-<?php echo $action == 'edit' ? 'pencil' : 'plus'; ?> mr-2"></i>
                                        <?php echo $action == 'edit' ? 'Edit Data Klaim' : 'Tambah Data Klaim Baru'; ?>
                                    </h5>
                                </div>
                                
                                <div class="p-4">
                                    <form method="POST" action="klaim.php">
                                        <input type="hidden" name="id" value="<?php echo $klaim_data['id'] ?? ''; ?>">
                                        
                                        <!-- Informasi Peserta -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="font-weight-bold required">Peserta BPJS</label>
                                                    <select class="form-control form-control-bpjs" name="peserta_id" required>
                                                        <option value="">Pilih Peserta</option>
                                                        <?php foreach ($peserta_list as $peserta): ?>
                                                        <option value="<?php echo $peserta['id']; ?>" 
                                                            <?php echo ($klaim_data['peserta_id'] ?? '') == $peserta['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($peserta['no_kartu'] . ' - ' . $peserta['nama']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">No. Klaim</label>
                                                    <input type="text" class="form-control form-control-bpjs" name="no_klaim" 
                                                        value="<?php echo $klaim_data['no_klaim'] ?? ''; ?>" 
                                                        placeholder="Biarkan kosong untuk generate otomatis">
                                                    <small class="text-muted">Format: KL-YYYYMMDD-XXXX</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Detail Klaim -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="font-weight-bold required">Diagnosa</label>
                                                    <input type="text" class="form-control form-control-bpjs" name="diagnosa" 
                                                        value="<?php echo $klaim_data['diagnosa'] ?? ''; ?>" 
                                                        required placeholder="Masukkan diagnosa">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold required">Nominal Klaim</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">Rp</span>
                                                        </div>
                                                        <input type="number" class="form-control form-control-bpjs" name="nominal_klaim" 
                                                            value="<?php echo $klaim_data['nominal_klaim'] ?? ''; ?>" 
                                                            required min="0" step="1000" placeholder="0">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold required">Tanggal Klaim</label>
                                                    <input type="date" class="form-control form-control-bpjs" name="tanggal_klaim" 
                                                        value="<?php echo $klaim_data['tanggal_klaim'] ?? date('Y-m-d'); ?>" 
                                                        required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Status dan Catatan -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="font-weight-bold required">Status Klaim</label>
                                                    <select class="form-control form-control-bpjs" name="status_klaim" required>
                                                        <option value="pending" <?php echo ($klaim_data['status_klaim'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="approved" <?php echo ($klaim_data['status_klaim'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                        <option value="rejected" <?php echo ($klaim_data['status_klaim'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Catatan</label>
                                                    <textarea class="form-control form-control-bpjs" name="catatan" rows="3" 
                                                            placeholder="Tambahkan catatan jika diperlukan..."><?php echo $klaim_data['catatan'] ?? ''; ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Tombol Aksi -->
                                        <div class="mt-4 pt-3 border-top text-right">
                                            <a href="klaim.php" class="btn btn-secondary">
                                                <i class="mdi mdi-close"></i> Batal
                                            </a>
                                            <button type="submit" class="btn btn-bpjs ml-2">
                                                <i class="mdi mdi-content-save"></i> Simpan Data
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                
                <!-- Search dan Filter -->
                <div class="search-box-bpjs">
                    <form method="GET" action="klaim.php">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white">
                                            <i class="mdi mdi-magnify"></i>
                                        </span>
                                    </div>
                                    <input type="text" class="form-control form-control-bpjs" name="search" 
                                        value="<?php echo htmlspecialchars($search); ?>" 
                                        placeholder="Cari berdasarkan nama, no kartu, no klaim, atau diagnosa...">
                                    <div class="input-group-append">
                                        <button class="btn btn-bpjs" type="submit">
                                            Cari
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-right mt-3 mt-md-0">
                                <div class="d-flex justify-content-end align-items-center export-buttons">
                                    <div class="dropdown-export mr-2">
                                        <button class="btn btn-export" type="button">
                                            <i class="mdi mdi-printer mr-1"></i> Cetak/Export
                                            <i class="mdi mdi-chevron-down ml-1"></i>
                                        </button>
                                        <div class="dropdown-export-content">
                                            <a href="klaim.php?export=print&<?php echo http_build_query($_GET); ?>" target="_blank">
                                                <i class="mdi mdi-printer mr-2"></i> Print (Tampilan Lengkap)
                                            </a>
                                            <a href="klaim.php?export=pdf&<?php echo http_build_query($_GET); ?>" target="_blank">
                                                <i class="mdi mdi-file-pdf mr-2"></i> PDF
                                            </a>
                                            <a href="klaim.php?export=excel&<?php echo http_build_query($_GET); ?>" target="_blank">
                                                <i class="mdi mdi-file-excel mr-2"></i> Excel
                                            </a>
                                        </div>
                                    </div>
                                    <a href="?action=add" class="btn btn-bpjs">
                                        <i class="mdi mdi-plus-circle"></i> Tambah Klaim
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Status -->
                        <div class="status-filter-bpjs">
                            <a href="klaim.php?<?php 
                                $query = $_GET;
                                unset($query['status']);
                                echo http_build_query($query); ?>" 
                               class="btn btn-outline-primary <?php echo empty($filter_status) ? 'active' : ''; ?>">
                                Semua Status
                            </a>
                            <a href="klaim.php?<?php 
                                $query = $_GET;
                                $query['status'] = 'pending';
                                echo http_build_query($query); ?>" 
                               class="btn btn-outline-warning <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">
                                <i class="mdi mdi-clock"></i> Pending
                            </a>
                            <a href="klaim.php?<?php 
                                $query = $_GET;
                                $query['status'] = 'approved';
                                echo http_build_query($query); ?>" 
                               class="btn btn-outline-success <?php echo $filter_status == 'approved' ? 'active' : ''; ?>">
                                <i class="mdi mdi-check-circle"></i> Approved
                            </a>
                            <a href="klaim.php?<?php 
                                $query = $_GET;
                                $query['status'] = 'rejected';
                                echo http_build_query($query); ?>" 
                               class="btn btn-outline-danger <?php echo $filter_status == 'rejected' ? 'active' : ''; ?>">
                                <i class="mdi mdi-close-circle"></i> Rejected
                            </a>
                        </div>
                        
                        <!-- Filter Tanggal -->
                        <div class="date-filter">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Dari Tanggal:</label>
                                    <input type="date" class="form-control form-control-bpjs" name="start_date" 
                                           value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label>Sampai Tanggal:</label>
                                    <input type="date" class="form-control form-control-bpjs" name="end_date" 
                                           value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-md-3 align-self-end">
                                    <button type="submit" class="btn btn-bpjs w-100">
                                        <i class="mdi mdi-filter"></i> Terapkan Filter
                                    </button>
                                </div>
                                <div class="col-md-3 align-self-end">
                                    <a href="klaim.php" class="btn btn-outline-secondary w-100">
                                        <i class="mdi mdi-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Statistik -->
                <?php if ($total_klaim > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="grid bpjs-card bpjs-card-primary">
                            <div class="grid-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1">Total Klaim</p>
                                        <h3 class="mb-0"><?php echo number_format($total_klaim); ?></h3>
                                    </div>
                                    <i class="mdi mdi-file-document-box-outline mdi-3x opacity-75"></i>
                                </div>
                                <small class="d-block mt-2">Total data klaim</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="grid bpjs-card bpjs-card-success">
                            <div class="grid-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1">Total Nilai</p>
                                        <h3 class="mb-0">Rp <?php echo number_format($total_nominal, 0, ',', '.'); ?></h3>
                                    </div>
                                    <i class="mdi mdi-cash mdi-3x opacity-75"></i>
                                </div>
                                <small class="d-block mt-2">Kumulatif klaim</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="grid bpjs-card bpjs-card-warning">
                            <div class="grid-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1">Pending</p>
                                        <h3 class="mb-0"><?php echo number_format($pending_count); ?></h3>
                                    </div>
                                    <i class="mdi mdi-clock-outline mdi-3x opacity-75"></i>
                                </div>
                                <small class="d-block mt-2">Menunggu verifikasi</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="grid bpjs-card bpjs-card-danger">
                            <div class="grid-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1">Approved</p>
                                        <h3 class="mb-0"><?php echo number_format($approved_count); ?></h3>
                                    </div>
                                    <i class="mdi mdi-check-circle-outline mdi-3x opacity-75"></i>
                                </div>
                                <small class="d-block mt-2">Klaim disetujui</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar Distribusi Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header card-header-bpjs">
                                <h5 class="mb-0"><i class="mdi mdi-chart-pie mr-2"></i> Distribusi Status Klaim</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <span class="badge badge-warning mr-2">Pending: <?php echo $pending_count; ?> (<?php echo $total_klaim > 0 ? round(($pending_count / $total_klaim) * 100, 1) : 0; ?>%)</span>
                                        <span class="badge badge-success mr-2">Approved: <?php echo $approved_count; ?> (<?php echo $total_klaim > 0 ? round(($approved_count / $total_klaim) * 100, 1) : 0; ?>%)</span>
                                        <span class="badge badge-danger">Rejected: <?php echo $rejected_count; ?> (<?php echo $total_klaim > 0 ? round(($rejected_count / $total_klaim) * 100, 1) : 0; ?>%)</span>
                                    </div>
                                    <div class="text-muted">
                                        Total: <?php echo $total_klaim; ?> klaim
                                    </div>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <?php 
                                    $pending_percent = $total_klaim > 0 ? ($pending_count / $total_klaim) * 100 : 0;
                                    $approved_percent = $total_klaim > 0 ? ($approved_count / $total_klaim) * 100 : 0;
                                    $rejected_percent = $total_klaim > 0 ? ($rejected_count / $total_klaim) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-warning" style="width: <?php echo $pending_percent; ?>%">
                                        <?php echo round($pending_percent, 1); ?>%
                                    </div>
                                    <div class="progress-bar bg-success" style="width: <?php echo $approved_percent; ?>%">
                                        <?php echo round($approved_percent, 1); ?>%
                                    </div>
                                    <div class="progress-bar bg-danger" style="width: <?php echo $rejected_percent; ?>%">
                                        <?php echo round($rejected_percent, 1); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabel Data Klaim -->
                <div class="row">
                    <div class="col-12">
                        <div class="grid bpjs-card">
                            <div class="grid-body">
                                <div class="card-header-bpjs">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="mdi mdi-view-list mr-2"></i> Daftar Klaim Peserta BPJS</h5>
                                        <div class="export-info">
                                            <small class="text-white">
                                                <i class="mdi mdi-information-outline"></i>
                                                <?php echo number_format($total_klaim); ?> data ditemukan
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover table-bpjs" id="klaimTable">
                                        <thead>
                                            <tr>
                                                <th width="50">No</th>
                                                <th>No Klaim</th>
                                                <th>Nama Peserta</th>
                                                <th>No Kartu</th>
                                                <th>Diagnosa</th>
                                                <th>Nominal</th>
                                                <th>Status</th>
                                                <th>Tanggal</th>
                                                <th width="100">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($klaim_list) > 0): ?>
                                            <?php $no = 1; ?>
                                            <?php foreach ($klaim_list as $row): 
                                                $status_badge = '';
                                                if ($row['status_klaim'] == 'pending') {
                                                    $status_badge = 'badge-pending';
                                                } elseif ($row['status_klaim'] == 'approved') {
                                                    $status_badge = 'badge-approved';
                                                } else {
                                                    $status_badge = 'badge-rejected';
                                                }
                                            ?>
                                            <tr>
                                                <td class="text-center"><?php echo $no++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['no_klaim']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['nama_peserta']); ?></td>
                                                <td><?php echo htmlspecialchars($row['no_kartu']); ?></td>
                                                <td>
                                                    <?php 
                                                    $diagnosa = $row['diagnosa'] ?? '-';
                                                    if (strlen($diagnosa) > 40) {
                                                        echo htmlspecialchars(substr($diagnosa, 0, 40)) . '...';
                                                    } else {
                                                        echo htmlspecialchars($diagnosa);
                                                    }
                                                    ?>
                                                    <?php if (!empty($row['catatan'])): ?>
                                                    <br><small class="text-muted" title="<?php echo htmlspecialchars($row['catatan']); ?>">
                                                        <i class="mdi mdi-note-text-outline"></i> ada catatan
                                                    </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="nominal-cell"><strong>Rp <?php echo number_format($row['nominal_klaim'], 0, ',', '.'); ?></strong></td>
                                                <td>
                                                    <span class="badge <?php echo $status_badge; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($row['status_klaim'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($row['tanggal_klaim'])); ?></td>
                                                <td class="action-buttons">
                                                    <a href="klaim.php?action=edit&id=<?php echo $row['id']; ?>" 
                                                    class="btn btn-warning btn-sm" title="Edit">
                                                        <i class="mdi mdi-pencil"></i>
                                                    </a>
                                                    <a href="klaim.php?action=delete&id=<?php echo $row['id']; ?>" 
                                                    class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus data klaim ini?')" title="Hapus">
                                                        <i class="mdi mdi-delete"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center">
                                                    <div class="alert alert-info mt-3">
                                                        <i class="mdi mdi-information-outline"></i>
                                                        Tidak ada data klaim ditemukan.
                                                        <?php if (!empty($search) || !empty($filter_status) || !empty($start_date) || !empty($end_date)): ?>
                                                        <br>
                                                        <a href="klaim.php" class="btn btn-sm btn-outline-primary mt-2">
                                                            Tampilkan semua data
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Summary -->
                                <?php if (count($klaim_list) > 0): ?>
                                <div class="mt-4 p-4 bg-light rounded">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="mdi mdi-chart-bar mr-2"></i> Ringkasan Data:</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li><i class="mdi mdi-checkbox-blank-circle text-primary mr-2"></i> Total Data: <strong><?php echo number_format($total_klaim); ?></strong> klaim</li>
                                                <li><i class="mdi mdi-checkbox-blank-circle text-success mr-2"></i> Total Nilai: <strong>Rp <?php echo number_format($total_nominal, 0, ',', '.'); ?></strong></li>
                                                <li><i class="mdi mdi-checkbox-blank-circle text-info mr-2"></i> Rata-rata per Klaim: <strong>Rp <?php echo number_format($total_klaim > 0 ? $total_nominal / $total_klaim : 0, 0, ',', '.'); ?></strong></li>
                                                <li><i class="mdi mdi-checkbox-blank-circle text-warning mr-2"></i> Periode: 
                                                    <strong>
                                                        <?php echo !empty($start_date) ? date('d/m/Y', strtotime($start_date)) : 'Semua' ?> 
                                                        - 
                                                        <?php echo !empty($end_date) ? date('d/m/Y', strtotime($end_date)) : 'Semua' ?>
                                                    </strong>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="mdi mdi-chart-pie mr-2"></i> Distribusi Status:</h6>
                                            <div class="progress-bar-custom">
                                                <?php 
                                                $pending_percent = $total_klaim > 0 ? ($pending_count / $total_klaim) * 100 : 0;
                                                $approved_percent = $total_klaim > 0 ? ($approved_count / $total_klaim) * 100 : 0;
                                                $rejected_percent = $total_klaim > 0 ? ($rejected_count / $total_klaim) * 100 : 0;
                                                ?>
                                                <div style="width: <?php echo $pending_percent; ?>%; background: #ffc107;"></div>
                                                <div style="width: <?php echo $approved_percent; ?>%; background: #28a745;"></div>
                                                <div style="width: <?php echo $rejected_percent; ?>%; background: #dc3545;"></div>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <span class="badge badge-warning mr-2"></span> Pending: <?php echo $pending_count; ?> (<?php echo round($pending_percent, 1); ?>%)
                                                    <span class="badge badge-success mr-2 ml-3"></span> Approved: <?php echo $approved_count; ?> (<?php echo round($approved_percent, 1); ?>%)
                                                    <span class="badge badge-danger mr-2 ml-3"></span> Rejected: <?php echo $rejected_count; ?> (<?php echo round($rejected_percent, 1); ?>%)
                                                </small>
                                            </div>
                                            <div class="mt-3">
                                                <a href="klaim.php?export=print&<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="mdi mdi-printer mr-1"></i> Cetak Laporan Lengkap
                                                </a>
                                                <button onclick="quickPrintKlaim()" class="btn btn-sm btn-outline-success ml-2">
                                                    <i class="mdi mdi-file-pdf mr-1"></i> Print Cepat
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<!-- plugins:js -->
<script src="../assets/vendors/js/core.js"></script>
<script src="../assets/vendors/jquery/jquery.min.js"></script>
<script src="../assets/vendors/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- endinject -->

<!-- Vendor Js For This Page -->
<script src="../assets/vendors/datatables.net/jquery.dataTables.js"></script>
<script src="../assets/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
<!-- End vendor js for this page -->

<!-- build:js -->
<script src="../assets/js/template.js"></script>
<!-- endbuild -->

<!-- Include xlsx untuk export Excel -->
<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables
    if ($('#klaimTable').length) {
        $('#klaimTable').DataTable({
            "pageLength": 25,
            "order": [[7, 'desc']],
            "language": {
                "search": "Cari:",
                "lengthMenu": "Tampilkan _MENU_ data per halaman",
                "zeroRecords": "Data tidak ditemukan",
                "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                "infoEmpty": "Tidak ada data",
                "infoFiltered": "(difilter dari _MAX_ total data)",
                "paginate": {
                    "first": "Pertama",
                    "last": "Terakhir",
                    "next": "Selanjutnya",
                    "previous": "Sebelumnya"
                }
            },
            "columnDefs": [
                { "orderable": false, "targets": 8 } // Nonaktifkan sorting untuk kolom aksi
            ]
        });
    }
    
    // Auto-generate no klaim jika peserta dipilih
    $('select[name="peserta_id"]').on('change', function() {
        if ($(this).val() && !$('input[name="no_klaim"]').val()) {
            const date = new Date().toISOString().split('T')[0].replace(/-/g, '');
            const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
            $('input[name="no_klaim"]').val('KL-' + date + '-' + random);
        }
    });
    
    // Format nominal input
    $('input[name="nominal_klaim"]').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        $(this).val(value);
    });
    
    // Auto close alert setelah 5 detik
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Set default date untuk filter
    if (!$('input[name="start_date"]').val()) {
        // Default: awal bulan ini
        let firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
        let firstDayStr = firstDay.toISOString().split('T')[0];
        $('input[name="start_date"]').val(firstDayStr);
    }
    
    if (!$('input[name="end_date"]').val()) {
        // Default: hari ini
        let today = new Date().toISOString().split('T')[0];
        $('input[name="end_date"]').val(today);
    }
    
    // Tambah event listener untuk tombol dropdown export
    $('.dropdown-export').hover(function() {
        $(this).find('.dropdown-export-content').stop(true, true).fadeIn(200);
    }, function() {
        $(this).find('.dropdown-export-content').stop(true, true).fadeOut(200);
    });
});

// Fungsi untuk quick print
function quickPrintKlaim() {
    // Buka halaman print yang sudah dibuat
    window.open('klaim.php?export=print&<?php echo http_build_query($_GET); ?>', '_blank');
}

// Fungsi untuk export ke Excel
function exportToExcel() {
    // Ambil data dari tabel
    const table = document.getElementById('klaimTable');
    const ws = XLSX.utils.table_to_sheet(table);
    
    // Buat workbook
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Laporan Klaim");
    
    // Simpan file
    const fileName = "Laporan_Klaim_BPJS_<?php echo date('Y-m-d'); ?>.xlsx";
    XLSX.writeFile(wb, fileName);
}

// Fungsi untuk export ke PDF
function exportToPDF() {
    // Redirect ke halaman PDF export
    window.location.href = 'klaim.php?export=pdf&<?php echo http_build_query($_GET); ?>';
}

// Fungsi untuk print cepat (tanpa membuka halaman baru)
function quickPrintSimple() {
    // Ambil konten tabel
    const table = document.getElementById('klaimTable').cloneNode(true);
    
    // Hapus kolom aksi
    const rows = table.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        if (cells.length > 0) {
            // Hapus kolom terakhir (aksi)
            rows[i].deleteCell(cells.length - 1);
        }
        const headers = rows[i].getElementsByTagName('th');
        if (headers.length > 0) {
            // Hapus header terakhir (aksi)
            rows[i].deleteCell(headers.length - 1);
        }
    }
    
    // Buat window print
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Laporan Klaim BPJS - Quick Print</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #0066cc; text-align: center; }
                .info { margin-bottom: 20px; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #0066cc; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border: 1px solid #ddd; }
                .total { background: #f8f9fa; font-weight: bold; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>Laporan Data Klaim BPJS</h1>
            <div class="info">
                <p><strong>Tanggal Cetak:</strong> ${new Date().toLocaleDateString('id-ID')}</p>
                <p><strong>Total Data:</strong> <?php echo number_format($total_klaim); ?></p>
                <p><strong>Total Nilai:</strong> Rp <?php echo number_format($total_nominal, 0, ',', '.'); ?></p>
            </div>
            ${table.outerHTML}
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #0066cc; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Cetak
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                    Tutup
                </button>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>
</body>
</html> 