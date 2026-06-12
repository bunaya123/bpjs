<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Ambil data user saat ini
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Proses update profile (termasuk foto)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } 
    // Validasi nomor telepon (opsional, minimal 10 digit, maksimal 15 digit)
    elseif (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $error = "Format nomor telepon tidak valid! Gunakan 10-15 digit angka.";
    } else {
        // Default: gunakan foto lama
        $profile_pic = $user['profile_pic'];
        
        // Proses upload foto jika ada
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            
            // Validasi file
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            $file_type = mime_content_type($file['tmp_name']);
            $file_size = $file['size'];
            
            // Cek tipe file
            if (!in_array($file_type, $allowed_types)) {
                $error = "Format file tidak didukung! Hanya JPG, PNG, GIF yang diperbolehkan.";
            }
            // Cek ukuran file
            elseif ($file_size > $max_size) {
                $error = "Ukuran file terlalu besar! Maksimal 2MB.";
            } else {
                // Generate nama file unik
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = 'uploads/profile_pics/';
                
                // Buat folder jika belum ada
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Path lengkap
                $upload_path = $upload_dir . $new_filename;
                
                // Hapus foto lama jika bukan default
                if ($user['profile_pic'] != 'default.png' && file_exists($upload_dir . $user['profile_pic'])) {
                    unlink($upload_dir . $user['profile_pic']);
                }
                
                // Upload file
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $profile_pic = $new_filename;
                } else {
                    $error = "Gagal mengupload foto!";
                }
            }
        }
        
        // Jika tidak ada error, update database
        if (empty($error)) {
            $update_sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, profile_pic = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ssssi", $full_name, $email, $phone, $profile_pic, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Profile berhasil diperbarui!";
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                $_SESSION['profile_pic'] = $profile_pic;
                
                // Refresh data user
                $sql = "SELECT * FROM users WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
            } else {
                $error = "Gagal memperbarui profile: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit Profile - BPJS Kesehatan</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bpjs-blue: #0066cc;
            --bpjs-green: #00a859;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
        }
        
        .profile-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .profile-avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .change-photo-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
            background: var(--bpjs-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
            transition: all 0.3s;
        }
        
        .change-photo-btn:hover {
            background: var(--bpjs-green);
            transform: scale(1.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--bpjs-blue);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
        }
        
        .btn-back {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            color: #666;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
        }
        
        .photo-preview {
            max-width: 200px;
            margin: 10px auto;
            text-align: center;
        }
        
        .preview-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--bpjs-blue);
            margin-bottom: 10px;
        }
        
        .role-badge {
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .role-icon {
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #0066cc, #004d99);">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="dashboard.php">
                <i class="fas fa-heartbeat me-2"></i>
                BPJS Kesehatan
            </a>
            <div class="navbar-nav ms-auto">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="profile-card">
                    <div class="profile-header text-center">
                        <div class="profile-avatar-container">
                            <?php
                            $profile_pic = $user['profile_pic'] ?? 'default.png';
                            $profile_path = 'uploads/profile_pics/' . $profile_pic;
                            
                            if (file_exists($profile_path) && $profile_pic != 'default.png') {
                                echo '<img src="' . $profile_path . '?t=' . time() . '" alt="Profile" class="profile-avatar" id="current-avatar">';
                            } else {
                                echo '<div class="avatar-placeholder" id="avatar-placeholder">';
                                echo strtoupper(substr($user['username'], 0, 1));
                                echo '</div>';
                            }
                            ?>
                            
                            <div class="change-photo-btn" onclick="document.getElementById('profile-pic').click()">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        
                        <h3>Profile Manager Data BPJS</h3>
                        <div class="role-badge mb-2">
                            <i class="fas fa-database me-2"></i>Manager Data
                        </div>
                        <p class="text-muted">Perbarui informasi dan foto profil Anda</p>
                    </div>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- PERHATIAN: enctype="multipart/form-data" HARUS ADA untuk upload file -->
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- Hidden file input -->
                        <input type="file" id="profile-pic" name="profile_pic" 
                               accept="image/jpeg,image/png,image/jpg,image/gif"
                               style="display: none;"
                               onchange="previewPhoto(this)">
                        
                        <!-- Preview section -->
                        <div class="photo-preview" id="preview-container" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Preview foto baru:
                            </div>
                            <img id="preview-image" class="preview-img" src="" alt="Preview">
                            <p class="text-muted">Foto akan diperbarui setelah klik Simpan</p>
                        </div>
                        
                        <!-- Informasi Akun -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box">
                                    <div class="info-label">Username</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <small class="text-muted">Username tidak dapat diubah</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box">
                                    <div class="info-label">Posisi</div>
                                    <div class="info-value">Manajer Data</div>
                                    <small class="text-muted">BPJS Kesehatan</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box">
                                    <div class="info-label">Status Akun</div>
                                    <div class="info-value">
                                        <span class="badge bg-success">Aktif</span>
                                    </div>
                                    <small class="text-muted">Member sejak: <?php echo date('d M Y', strtotime($user['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                   placeholder="Masukkan nama lengkap" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   placeholder="Masukkan email" required>
                            <small class="text-muted">Email akan digunakan untuk notifikasi</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Nomor Telepon</label>
                            <div class="input-group">
                                <span class="input-group-text">+62</span>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                       placeholder="81234567890"
                                       pattern="[0-9]{10,15}"
                                       title="Masukkan 10-15 digit nomor telepon">
                            </div>
                            <small class="text-muted">Contoh: 81234567890 (tanpa 0 di depan)</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Foto Profil</label>
                            <div class="alert alert-light">
                                <div class="d-flex align-items-center">
                                    <div class="role-icon">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <strong>Manajer Data BPJS</strong><br>
                                        Klik ikon kamera untuk mengganti foto. Format: JPG, PNG, GIF (maks. 2MB)
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-back">
                                <i class="fas fa-times me-2"></i> Batal
                            </a>
                            <button type="submit" name="update_profile" class="btn btn-save">
                                <i class="fas fa-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Preview foto sebelum upload
        function previewPhoto(input) {
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-image');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.style.display = 'none';
            }
        }
        
        // Validasi form
        document.querySelector('form').addEventListener('submit', function(e) {
            const fullName = document.querySelector('input[name="full_name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const fileInput = document.getElementById('profile-pic');
            
            // Validasi nama
            if (!fullName) {
                e.preventDefault();
                alert('Nama lengkap harus diisi!');
                return false;
            }
            
            // Validasi email
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                e.preventDefault();
                alert('Format email tidak valid!');
                return false;
            }
            
            // Validasi nomor telepon (opsional)
            if (phone) {
                const phonePattern = /^[0-9]{10,15}$/;
                if (!phonePattern.test(phone)) {
                    e.preventDefault();
                    alert('Format nomor telepon tidak valid! Gunakan 10-15 digit angka.');
                    return false;
                }
            }
            
            // Validasi file jika ada
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                // Validasi ukuran
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('Ukuran file terlalu besar! Maksimal 2MB.');
                    return false;
                }
                
                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Format file tidak didukung! Hanya JPG, PNG, GIF yang diperbolehkan.');
                    return false;
                }
            }
            
            return true;
        });
        
        // Buka file dialog saat klik avatar
        document.querySelector('.profile-avatar-container').addEventListener('click', function(e) {
            if (e.target.closest('.change-photo-btn') || 
                e.target.classList.contains('avatar-placeholder') || 
                e.target.id === 'current-avatar') {
                document.getElementById('profile-pic').click();
            }
        });
        
        // Format nomor telepon
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>