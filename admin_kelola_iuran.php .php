<?php
session_start();
require_once '../../config.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Ambil semua iuran dengan filter
$filter_status = $_GET['status'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';
$filter_peserta = $_GET['peserta'] ?? '';

$sql = "SELECT i.*, p.nama, p.no_kartu, k.nama_kelas 
        FROM iuran i
        JOIN peserta p ON i.peserta_id = p.id
        JOIN kelas k ON p.kelas_id = k.id
        WHERE 1=1";
        
if ($filter_status) {
    $sql .= " AND i.status = '$filter_status'";
}
if ($filter_bulan) {
    $sql .= " AND i.bulan_tahun = '$filter_bulan'";
}
if ($filter_peserta) {
    $sql .= " AND (p.nama LIKE '%$filter_peserta%' OR p.no_kartu LIKE '%$filter_peserta%')";
}

$sql .= " ORDER BY i.tanggal_jatuh_tempo DESC";
$result = mysqli_query($conn, $sql);
$iuran_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Ambil bulan unik untuk filter
$sql_bulan = "SELECT DISTINCT bulan_tahun FROM iuran ORDER BY bulan_tahun DESC";
$result_bulan = mysqli_query($conn, $sql_bulan);
$bulan_list = mysqli_fetch_all($result_bulan, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Kelola Iuran</title>
    <link rel="stylesheet" href="../../assets/css/shared/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <style>
        .stats-card {
            border-radius: 10px;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .bg-belum { background: linear-gradient(135deg, #ffc107, #ff9800); }
        .bg-lunas { background: linear-gradient(135deg, #28a745, #20c997); }
        .bg-lewat { background: linear-gradient(135deg, #dc3545, #fd7e14); }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="mdi mdi-cash-multiple"></i> Kelola Iuran
                        </h4>
                        <div>
                            <button class="btn btn-light btn-sm" data-toggle="modal" data-target="#modalGenerate">
                                <i class="mdi mdi-plus"></i> Generate Iuran
                            </button>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="row p-3">
                        <div class="col-md-4">
                            <div class="stats-card bg-belum">
                                <i class="mdi mdi-clock-outline"></i>
                                <h3>
                                    <?php 
                                    $sql_count = "SELECT COUNT(*) as total FROM iuran WHERE status = 'Belum Bayar'";
                                    $result = mysqli_query($conn, $sql_count);
                                    echo mysqli_fetch_assoc($result)['total'];
                                    ?>
                                </h3>
                                <p class="mb-0">Belum Bayar</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card bg-lunas">
                                <i class="mdi mdi-check-circle"></i>
                                <h3>
                                    <?php 
                                    $sql_count = "SELECT COUNT(*) as total FROM iuran WHERE status = 'Lunas'";
                                    $result = mysqli_query($conn, $sql_count);
                                    echo mysqli_fetch_assoc($result)['total'];
                                    ?>
                                </h3>
                                <p class="mb-0">Lunas</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card bg-lewat">
                                <i class="mdi mdi-alert-circle"></i>
                                <h3>
                                    <?php 
                                    $sql_count = "SELECT COUNT(*) as total FROM iuran WHERE status = 'Lewat Jatuh Tempo'";
                                    $result = mysqli_query($conn, $sql_count);
                                    echo mysqli_fetch_assoc($result)['total'];
                                    ?>
                                </h3>
                                <p class="mb-0">Lewat Tempo</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter -->
                    <div class="card-body">
                        <div class="filter-card">
                            <form method="GET" class="row">
                                <div class="col-md-3">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="Belum Bayar" <?php echo $filter_status == 'Belum Bayar' ? 'selected' : ''; ?>>Belum Bayar</option>
                                        <option value="Lunas" <?php echo $filter_status == 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                                        <option value="Lewat Jatuh Tempo" <?php echo $filter_status == 'Lewat Jatuh Tempo' ? 'selected' : ''; ?>>Lewat Tempo</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label>Bulan/Tahun</label>
                                    <select name="bulan" class="form-control">
                                        <option value="">Semua Bulan</option>
                                        <?php foreach ($bulan_list as $b): ?>
                                        <option value="<?php echo $b['bulan_tahun']; ?>" 
                                            <?php echo $filter_bulan == $b['bulan_tahun'] ? 'selected' : ''; ?>>
                                            <?php echo date('F Y', strtotime($b['bulan_tahun'] . '-01')); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label>Cari Peserta</label>
                                    <input type="text" name="peserta" class="form-control" 
                                           placeholder="Nama / No. Kartu" value="<?php echo $filter_peserta; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="mdi mdi-filter"></i> Filter
                                        </button>
                                        <a href="kelola_iuran.php" class="btn btn-secondary">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Table -->
                        <div class="table-responsive">
                            <table id="tableIuran" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No. Kartu</th>
                                        <th>Nama</th>
                                        <th>Bulan</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Iuran</th>
                                        <th>Denda</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($iuran_list as $i): ?>
                                    <tr>
                                        <td><?php echo $i['no_kartu']; ?></td>
                                        <td><?php echo $i['nama']; ?></td>
                                        <td><?php echo date('M Y', strtotime($i['bulan_tahun'] . '-01')); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($i['tanggal_jatuh_tempo'])); ?></td>
                                        <td>Rp <?php echo number_format($i['jumlah'], 0, ',', '.'); ?></td>
                                        <td>
                                            <?php if ($i['denda'] > 0): ?>
                                                <span class="text-danger">Rp <?php echo number_format($i['denda'], 0, ',', '.'); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>Rp <?php echo number_format($i['total_bayar'], 0, ',', '.'); ?></strong></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $i['status'] == 'Lunas' ? 'success' : 
                                                     ($i['status'] == 'Belum Bayar' ? 'warning' : 'danger');
                                            ?>">
                                                <?php echo $i['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $i['tanggal_bayar'] ? date('d/m/Y', strtotime($i['tanggal_bayar'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="lihatDetail(<?php echo $i['id']; ?>)">
                                                    <i class="mdi mdi-eye"></i>
                                                </button>
                                                <?php if ($i['status'] != 'Lunas'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="markAsPaid(<?php echo $i['id']; ?>)">
                                                    <i class="mdi mdi-check"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="editIuran(<?php echo $i['id']; ?>)">
                                                    <i class="mdi mdi-pencil"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Generate -->
    <div class="modal fade" id="modalGenerate">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="generate_iuran.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Generate Iuran</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Bulan/Tahun</label>
                            <input type="month" name="bulan_tahun" class="form-control" required 
                                   value="<?php echo date('Y-m'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Jatuh Tempo</label>
                            <input type="date" name="tanggal_jatuh_tempo" class="form-control" required 
                                   value="<?php echo date('Y-m-10'); ?>">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="force_generate" class="form-check-input" id="forceGenerate">
                            <label class="form-check-label" for="forceGenerate">
                                Generate ulang jika sudah ada
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Generate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tableIuran').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
                }
            });
        });
        
        function lihatDetail(id) {
            window.open(`detail_iuran.php?id=${id}`, '_blank');
        }
        
        function markAsPaid(id) {
            if (confirm('Tandai iuran ini sebagai LUNAS?')) {
                window.location.href = `update_status.php?id=${id}&status=Lunas`;
            }
        }
        
        function editIuran(id) {
            // Implement edit modal
            alert('Edit iuran ID: ' + id);
        }
    </script>
</body>
</html>