<?php
// simpan_peserta.php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Cek apakah request POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $_SESSION['form_errors'] = ["Metode request tidak valid. Harus menggunakan POST."];
    header("Location: tambah_peserta.php");
    exit();
}

// Ambil data dari form dengan filter
$no_kartu = trim($_POST['no_kartu'] ?? '');
$nik = trim($_POST['nik'] ?? '');
$nama = trim($_POST['nama'] ?? '');
$jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
$tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
$tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
$alamat = trim($_POST['alamat'] ?? '');
$no_telepon = trim($_POST['no_telepon'] ?? '');
$email = trim($_POST['email'] ?? '');
$faskes = trim($_POST['faskes'] ?? '');
$kelas_bpjs = $_POST['kelas_bpjs'] ?? 'Kelas 3';
$status = $_POST['status'] ?? 'active';

// Validasi data
$errors = [];

// Validasi required fields
if (empty($no_kartu)) {
    $errors[] = "No Kartu BPJS harus diisi";
} elseif (!is_numeric($no_kartu)) {
    $errors[] = "No Kartu BPJS harus berupa angka";
} elseif (strlen($no_kartu) < 10) {
    $errors[] = "No Kartu BPJS minimal 10 digit";
}

if (empty($nik)) {
    $errors[] = "NIK harus diisi";
} elseif (!is_numeric($nik)) {
    $errors[] = "NIK harus berupa angka";
} elseif (strlen($nik) != 16) {
    $errors[] = "NIK harus 16 digit";
}

if (empty($nama)) {
    $errors[] = "Nama harus diisi";
} elseif (strlen($nama) < 3) {
    $errors[] = "Nama minimal 3 karakter";
}

if (empty($tanggal_lahir)) {
    $errors[] = "Tanggal lahir harus diisi";
} else {
    // Validasi format tanggal
    $date_parts = explode('-', $tanggal_lahir);
    if (count($date_parts) != 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        $errors[] = "Format tanggal lahir tidak valid";
    } else {
        // Cek tanggal tidak boleh di masa depan
        $today = date('Y-m-d');
        if ($tanggal_lahir > $today) {
            $errors[] = "Tanggal lahir tidak boleh di masa depan";
        }
    }
}

// Validasi email jika diisi
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Format email tidak valid";
}

// Validasi nomor telepon jika diisi
if (!empty($no_telepon) && !is_numeric(str_replace(['-', ' ', '+'], '', $no_telepon))) {
    $errors[] = "Nomor telepon harus berupa angka";
}

// Cek duplikat No Kartu BPJS dan NIK
if (empty($errors)) {
    $check_sql = "SELECT id, nama, no_kartu, nik FROM peserta WHERE no_kartu = ? OR nik = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ss", $no_kartu, $nik);
        
        if (mysqli_stmt_execute($check_stmt)) {
            $check_result = mysqli_stmt_get_result($check_stmt);
            $duplicates = [];
            
            while ($row = mysqli_fetch_assoc($check_result)) {
                if ($row['no_kartu'] == $no_kartu) {
                    $duplicates[] = "No Kartu BPJS <strong>$no_kartu</strong> sudah digunakan oleh: <strong>" . htmlspecialchars($row['nama']) . "</strong>";
                }
                if ($row['nik'] == $nik) {
                    $duplicates[] = "NIK <strong>$nik</strong> sudah digunakan oleh: <strong>" . htmlspecialchars($row['nama']) . "</strong>";
                }
            }
            
            mysqli_stmt_close($check_stmt);
            
            if (!empty($duplicates)) {
                $errors = array_merge($errors, $duplicates);
            }
        } else {
            $errors[] = "Gagal mengecek duplikat data: " . mysqli_error($conn);
        }
    } else {
        $errors[] = "Gagal mempersiapkan query cek duplikat: " . mysqli_error($conn);
    }
}

// Jika ada error, kembali ke form dengan data yang diisi
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = [
        'no_kartu' => $no_kartu,
        'nik' => $nik,
        'nama' => $nama,
        'jenis_kelamin' => $jenis_kelamin,
        'tempat_lahir' => $tempat_lahir,
        'tanggal_lahir' => $tanggal_lahir,
        'alamat' => $alamat,
        'no_telepon' => $no_telepon,
        'email' => $email,
        'faskes' => $faskes,
        'kelas_bpjs' => $kelas_bpjs,
        'status' => $status
    ];
    header("Location: tambah_peserta.php");
    exit();
}

// Format nomor telepon (hapus karakter non-numerik)
if (!empty($no_telepon)) {
    $no_telepon = preg_replace('/[^0-9]/', '', $no_telepon);
}

// Simpan ke database menggunakan prepared statement
$sql = "INSERT INTO peserta (
    no_kartu, 
    nik, 
    nama, 
    jenis_kelamin, 
    tempat_lahir, 
    tanggal_lahir, 
    alamat, 
    no_telepon, 
    email, 
    faskes, 
    kelas_bpjs, 
    status,
    created_at,
    updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    $_SESSION['form_errors'] = ["Gagal mempersiapkan query: " . mysqli_error($conn)];
    $_SESSION['form_data'] = $_POST;
    header("Location: tambah_peserta.php");
    exit();
}

// Bind parameter
mysqli_stmt_bind_param($stmt, "ssssssssssss", 
    $no_kartu, 
    $nik, 
    $nama, 
    $jenis_kelamin, 
    $tempat_lahir, 
    $tanggal_lahir,
    $alamat, 
    $no_telepon, 
    $email, 
    $faskes, 
    $kelas_bpjs, 
    $status
);

// Eksekusi query
if (mysqli_stmt_execute($stmt)) {
    $last_id = mysqli_insert_id($conn);
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    
    if ($affected_rows > 0) {
        // Log aktivitas (jika tabel user_activities ada)
        $user_id = $_SESSION['user_id'];
        
        // Cek apakah tabel user_activities ada
        $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'user_activities'");
        if ($check_table && mysqli_num_rows($check_table) > 0) {
            $activity_sql = "INSERT INTO user_activities (user_id, activity_type, description, created_at) 
                            VALUES (?, 'tambah_peserta', ?, NOW())";
            $activity_stmt = mysqli_prepare($conn, $activity_sql);
            if ($activity_stmt) {
                $activity_desc = "Menambahkan peserta baru: " . $nama . " (No Kartu: " . $no_kartu . ")";
                mysqli_stmt_bind_param($activity_stmt, "is", $user_id, $activity_desc);
                mysqli_stmt_execute($activity_stmt);
                mysqli_stmt_close($activity_stmt);
            }
        }
        
        // Set session success message
        $_SESSION['success_message'] = "✅ <strong>Data peserta berhasil disimpan!</strong><br>
                                       <strong>Nama:</strong> " . htmlspecialchars($nama) . "<br>
                                       <strong>No Kartu:</strong> $no_kartu<br>
                                       <strong>NIK:</strong> $nik";
        
        mysqli_stmt_close($stmt);
        
        // Redirect ke halaman daftar peserta
        header("Location: peserta_bpjs.php?success=1&new_id=" . $last_id);
        exit();
    } else {
        $errors[] = "Tidak ada data yang berhasil disimpan";
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        mysqli_stmt_close($stmt);
        header("Location: tambah_peserta.php");
        exit();
    }
} else {
    // Jika gagal menyimpan
    $error_message = "Gagal menyimpan data ke database: " . mysqli_error($conn);
    $_SESSION['form_errors'] = [$error_message];
    $_SESSION['form_data'] = [
        'no_kartu' => $no_kartu,
        'nik' => $nik,
        'nama' => $nama,
        'jenis_kelamin' => $jenis_kelamin,
        'tempat_lahir' => $tempat_lahir,
        'tanggal_lahir' => $tanggal_lahir,
        'alamat' => $alamat,
        'no_telepon' => $no_telepon,
        'email' => $email,
        'faskes' => $faskes,
        'kelas_bpjs' => $kelas_bpjs,
        'status' => $status
    ];
    mysqli_stmt_close($stmt);
    header("Location: tambah_peserta.php");
    exit();
}
?>