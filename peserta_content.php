<?php
// sections/peserta_content.php

// Parameter filter
$limit = 15; // Jumlah data per halaman
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $limit;

// Filter tambahan
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : 'all';
$filter_faskes = isset($_GET['faskes']) ? $_GET['faskes'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Query dinamis
$where_conditions = [];
$params = [];
$types = '';

if ($filter_status != 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_kelas != 'all') {
    $where_conditions[] = "kelas_bpjs = ?";
    $params[] = $filter_kelas;
    $types .= 's';
}

if ($filter_faskes != 'all') {
    $where_conditions[] = "faskes = ?";
    $params[] = $filter_faskes;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(no_kartu LIKE ? OR nik LIKE ? OR nama LIKE ? OR no_telepon LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= str_repeat('s', 5);
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Hitung total data
$count_sql = "SELECT COUNT(*) as total FROM peserta $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$row_count = mysqli_fetch_assoc($count_result);
$total_data = $row_count['total'];
$total_pages = ceil($total_data / $limit);
mysqli_stmt_close($count_stmt);

// Ambil data peserta dengan filter
$params_limit = array_merge($params, [$limit, $offset]);
$types_limit = $types . 'ii';

$sql_peserta = "SELECT * FROM peserta $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt_peserta = mysqli_prepare($conn, $sql_peserta);
if (!empty($params_limit)) {
    mysqli_stmt_bind_param($stmt_peserta, $types_limit, ...$params_limit);
}
mysqli_stmt_execute($stmt_peserta);
$result_peserta = mysqli_stmt_get_result($stmt_peserta);
$peserta = mysqli_fetch_all($result_peserta, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_peserta);

// Ambil data unik untuk filter dropdown
$sql_kelas = "SELECT DISTINCT kelas_bpjs FROM peserta WHERE kelas_bpjs IS NOT NULL ORDER BY kelas_bpjs";
$sql_faskes = "SELECT DISTINCT faskes FROM peserta WHERE faskes IS NOT NULL ORDER BY faskes";
$result_kelas = mysqli_query($conn, $sql_kelas);
$result_faskes = mysqli_query($conn, $sql_faskes);
$kelas_options = mysqli_fetch_all($result_kelas, MYSQLI_ASSOC);
$faskes_options = mysqli_fetch_all($result_faskes, MYSQLI_ASSOC);

// Statistik
$stats_sql = "SELECT 
    status,
    COUNT(*) as jumlah,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM peserta), 2) as persentase
    FROM peserta 
    GROUP BY status";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = [];
while ($row = mysqli_fetch_assoc($stats_result)) {
    $stats[$row['status']] = $row;
}
?>

<!-- PAGE HEADER -->
<div class="row">
    <div class="col-12 py-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="dashboard.php?page=dashboard" style="color: #6c757d;">
                        <i class="mdi mdi-home mr-1"></i> Home
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page" style="color: #007bff;">
                    Data Peserta
                </li>
            </ol>
        </nav>
        <h4 class="page-title"><i class="mdi mdi-account-multiple mr-2 text-primary"></i>Data Peserta BPJS</h4>
        <p class="text-gray" style="color: #6c757d; font-size: 16px;">
            <i class="mdi mdi-information-outline mr-2"></i>Manajemen data peserta BPJS Kesehatan
        </p>
    </div>
</div>

<!-- STATISTICS CARDS -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 col-6 equel-grid">
        <div class="grid">
            <div class="grid-body text-gray">
                <div class="d-flex justify-content-between">
                    <p><?php echo number_format($total_data); ?></p>
                    <p class="text-primary">Total</p>
                </div>
                <p class="text-black">Jumlah Peserta</p>
                <div class="wrapper w-50 mt-4">
                    <div class="text-center">
                        <i class="mdi mdi-account-multiple mdi-3x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 col-6 equel-grid">
        <div class="grid">
            <div class="grid-body text-gray">
                <div class="d-flex justify-content-between">
                    <p><?php echo number_format($stats['active']['jumlah'] ?? 0); ?></p>
                    <p class="text-success">Aktif</p>
                </div>
                <p class="text-black">Peserta Aktif</p>
                <div class="wrapper w-50 mt-4">
                    <div class="text-center">
                        <i class="mdi mdi-account-check mdi-3x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 col-6 equel-grid">
        <div class="grid">
            <div class="grid-body text-gray">
                <div class="d-flex justify-content-between">
                    <p><?php echo number_format($stats['pending']['jumlah'] ?? 0); ?></p>
                    <p class="text-warning">Pending</p>
                </div>
                <p class="text-black">Peserta Pending</p>
                <div class="wrapper w-50 mt-4">
                    <div class="text-center">
                        <i class="mdi mdi-clock-outline mdi-3x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 col-6 equel-grid">
        <div class="grid">
            <div class="grid-body text-gray">
                <div class="d-flex justify-content-between">
                    <p><?php echo number_format($stats['inactive']['jumlah'] ?? 0); ?></p>
                    <p class="text-danger">Non-Aktif</p>
                </div>
                <p class="text-black">Peserta Non-Aktif</p>
                <div class="wrapper w-50 mt-4">
                    <div class="text-center">
                        <i class="mdi mdi-account-remove mdi-3x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER SECTION -->
<div class="grid mb-4">
    <div class="grid-body">
        <form method="GET" action="">
            <!-- Tambahkan parameter page untuk tetap di halaman peserta -->
            <input type="hidden" name="page" value="peserta">
            
            <div class="row">
                <div class="col-lg-5 mb-3">
                    <div class="search-box">
                        <i class="mdi mdi-magnify search-icon"></i>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Cari nama, NIK, atau no kartu..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-lg-2 mb-3">
                    <select class="form-control" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Non-Aktif</option>
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <select class="form-control" name="kelas" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_kelas == 'all' ? 'selected' : ''; ?>>Semua Kelas</option>
                        <?php foreach ($kelas_options as $kelas): ?>
                            <option value="<?php echo htmlspecialchars($kelas['kelas_bpjs']); ?>" 
                                <?php echo $filter_kelas == $kelas['kelas_bpjs'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kelas['kelas_bpjs']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 mb-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="mdi mdi-filter mr-1"></i> Filter
                    </button>
                </div>
            </div>
        </form>
        
        <!-- QUICK FILTER BUTTONS -->
        <div class="quick-filter mt-3">
            <span class="me-2 text-muted"><i class="mdi mdi-lightning-bolt mr-1"></i>Filter Cepat:</span>
            <a href="dashboard.php?page=peserta&status=all" 
               class="btn btn-sm btn-outline-secondary <?php echo $filter_status == 'all' ? 'active' : ''; ?>">
                Semua (<?php echo $total_data; ?>)
            </a>
            <a href="dashboard.php?page=peserta&status=active" 
               class="btn btn-sm btn-outline-success <?php echo $filter_status == 'active' ? 'active' : ''; ?>">
                Aktif (<?php echo $stats['active']['jumlah'] ?? 0; ?>)
            </a>
            <a href="dashboard.php?page=peserta&status=pending" 
               class="btn btn-sm btn-outline-warning <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">
                Pending (<?php echo $stats['pending']['jumlah'] ?? 0; ?>)
            </a>
            <a href="dashboard.php?page=peserta&status=inactive" 
               class="btn btn-sm btn-outline-danger <?php echo $filter_status == 'inactive' ? 'active' : ''; ?>">
                Non-Aktif (<?php echo $stats['inactive']['jumlah'] ?? 0; ?>)
            </a>
        </div>
    </div>
</div>

<!-- DATA TABLE -->
<div class="grid">
    <div class="grid-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5>Daftar Peserta</h5>
            <a href="tambah_peserta.php" class="btn btn-primary">
                <i class="mdi mdi-plus mr-1"></i> Tambah Peserta
            </a>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>IDENTITAS</th>
                        <th>DATA PRIBADI</th>
                        <th>KONTAK</th>
                        <th>BPJS</th>
                        <th width="120">STATUS</th>
                        <th width="150">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($peserta)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-4">
                                    <i class="mdi mdi-database-off mdi-3x text-muted mb-3"></i>
                                    <h5 class="mt-3">Tidak ada data ditemukan</h5>
                                    <p class="text-muted">Coba ubah filter atau tambah data peserta baru</p>
                                    <a href="tambah_peserta.php" class="btn btn-primary mt-2">
                                        <i class="mdi mdi-plus mr-1"></i> Tambah Peserta
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = ($page - 1) * $limit + 1; ?>
                        <?php foreach ($peserta as $p): ?>
                        <tr>
                            <!-- Kolom No -->
                            <td class="text-center fw-bold">
                                <?php echo $no++; ?>
                            </td>
                            
                            <!-- Kolom Identitas -->
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="gender-icon <?php echo $p['jenis_kelamin'] == 'L' ? 'gender-male' : 'gender-female'; ?>">
                                        <?php echo $p['jenis_kelamin'] == 'L' ? 'L' : 'P'; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold mb-1"><?php echo htmlspecialchars($p['nama']); ?></div>
                                        <div class="text-muted small">
                                            <div><i class="mdi mdi-card-account-details mr-1"></i> <?php echo htmlspecialchars($p['no_kartu']); ?></div>
                                            <div><i class="mdi mdi-fingerprint mr-1"></i> <?php echo htmlspecialchars($p['nik']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom Data Pribadi -->
                            <td>
                                <div class="mb-2">
                                    <div class="text-muted small mb-1">Tempat/Tgl Lahir:</div>
                                    <div>
                                        <?php echo htmlspecialchars($p['tempat_lahir'] ?? '-'); ?>, 
                                        <?php echo date('d/m/Y', strtotime($p['tanggal_lahir'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted small mb-1">Alamat:</div>
                                    <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($p['alamat'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($p['alamat'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom Kontak -->
                            <td>
                                <div class="mb-2">
                                    <div class="text-muted small mb-1">Telepon:</div>
                                    <div>
                                        <i class="mdi mdi-phone mr-1"></i>
                                        <?php echo htmlspecialchars($p['no_telepon'] ?? '-'); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted small mb-1">Email:</div>
                                    <div>
                                        <i class="mdi mdi-email mr-1"></i>
                                        <?php echo htmlspecialchars($p['email'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom BPJS -->
                            <td>
                                <div class="mb-2">
                                    <div class="text-muted small mb-1">Faskes:</div>
                                    <div class="faskes-badge">
                                        <?php echo htmlspecialchars($p['faskes'] ?? '-'); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted small mb-1">Kelas:</div>
                                    <div class="kelas-badge <?php 
                                        echo 'kelas-' . str_replace(' ', '-', strtolower($p['kelas_bpjs'] ?? 'default')); 
                                    ?>">
                                        <?php echo htmlspecialchars($p['kelas_bpjs'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom Status -->
                            <td>
                                <?php 
                                $status = $p['status'] ?? 'pending';
                                $badge_class = $status == 'active' ? 'badge-success' : ($status == 'inactive' ? 'badge-danger' : 'badge-warning');
                                $badge_text = $status == 'active' ? 'Aktif' : ($status == 'inactive' ? 'Non-Aktif' : 'Pending');
                                ?>
                                
                                <span class="badge <?php echo $badge_class; ?> px-3 py-2">
                                    <?php echo htmlspecialchars($badge_text); ?>
                                </span>
                            </td>
                            
                            <!-- Kolom Aksi -->
                            <td>
                                <div class="action-buttons d-flex">
                                    <a href="detail_peserta.php?id=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-info" title="Detail">
                                        <i class="mdi mdi-eye"></i>
                                    </a>
                                    <a href="edit_peserta.php?id=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-warning mx-1" title="Edit">
                                        <i class="mdi mdi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete" 
                                            data-id="<?php echo $p['id']; ?>"
                                            data-nama="<?php echo htmlspecialchars($p['nama']); ?>"
                                            data-toggle="modal" 
                                            data-target="#deleteModal"
                                            title="Hapus">
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="row mt-4">
            <div class="col-md-6">
                <p class="text-muted mb-0">
                    <i class="mdi mdi-information mr-1"></i>
                    Menampilkan <strong><?php echo count($peserta); ?></strong> dari <strong><?php echo number_format($total_data); ?></strong> data
                </p>
            </div>
            <div class="col-md-6">
                <nav aria-label="Page navigation" class="d-flex justify-content-end">
                    <ul class="pagination mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="dashboard.php?page=peserta&page_num=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="mdi mdi-chevron-left"></i>
                            </a>
                        </li>
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="dashboard.php?page=peserta&page_num=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="dashboard.php?page=peserta&page_num=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="mdi mdi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.search-box {
    position: relative;
}

.search-box .form-control {
    padding-left: 45px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    height: 45px;
}

.search-box .search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #007bff;
    z-index: 10;
}

.gender-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
    margin-right: 12px;
    color: white;
}

.gender-male {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.gender-female {
    background: linear-gradient(135deg, #e83e8c 0%, #c2185b 100%);
}

.faskes-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: #e8f4ff;
    color: #0066cc;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 500;
    border-left: 3px solid #0066cc;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kelas-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
    min-width: 80px;
    text-align: center;
}

.kelas-1 {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.kelas-2 {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.kelas-3 {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.kelas-default {
    background: #e2e3e5;
    color: #383d41;
    border: 1px solid #d6d8db;
}

.quick-filter .btn {
    border-radius: 20px;
    padding: 8px 16px;
    margin-right: 8px;
    margin-bottom: 8px;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.quick-filter .btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.action-buttons .btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 3px;
    transition: all 0.3s;
}

.action-buttons .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}
</style>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-delete mr-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center py-3">
                    <i class="mdi mdi-alert-circle-outline mdi-3x text-warning mb-3"></i>
                    <h5>Apakah Anda yakin?</h5>
                    <p>Data peserta <strong id="deleteNama"></strong> akan dihapus secara permanen.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <a id="deleteConfirm" href="#" class="btn btn-danger">
                    <i class="mdi mdi-delete mr-1"></i> Hapus
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Delete confirmation
    $('.btn-delete').click(function() {
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        $('#deleteNama').text(nama);
        $('#deleteConfirm').attr('href', 'delete_peserta.php?id=' + id);
    });
});
</script>