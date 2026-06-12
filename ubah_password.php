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

// Ambil data user
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Proses change password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field harus diisi!";
    } elseif (md5($current_password) !== $user['password']) {
        $error = "Password saat ini salah!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // Update password di database
        $new_password_md5 = md5($new_password);
        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $new_password_md5, $user_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Password berhasil diubah!";
            
            // Log aktivitas (jika ada fungsi log_password_change di config.php)
            if (function_exists('log_password_change')) {
                log_password_change($conn, $user_id);
            }
            
            // Clear form
            $_POST = array();
        } else {
            $error = "Gagal mengubah password: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Ubah Password - BPJS Kesehatan</title>
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
        
        .password-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .password-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .password-input-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            z-index: 2;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
            padding-right: 45px;
        }
        
        .form-control:focus {
            border-color: var(--bpjs-blue);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .password-strength {
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .strength-weak { width: 25%; background: #ff5252; }
        .strength-medium { width: 50%; background: #ffb74d; }
        .strength-good { width: 75%; background: #4caf50; }
        .strength-strong { width: 100%; background: #00c853; }
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .requirement-item i {
            margin-right: 10px;
            font-size: 0.8rem;
        }
        
        .requirement-valid {
            color: var(--bpjs-green);
        }
        
        .requirement-invalid {
            color: #dc3545;
        }
        
        .btn-change {
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-change:hover {
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
            width: 100%;
            margin-top: 10px;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-back:hover {
            background: #e9ecef;
            color: #333;
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
        <div class="password-card">
            <div class="password-header">
                <div class="password-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h3>Ubah Password</h3>
                <p class="text-muted">Ganti password akun Anda</p>
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
            
            <form method="POST" action="" id="passwordForm">
                <div class="mb-3">
                    <label class="form-label">Password Saat Ini</label>
                    <div class="password-input-group">
                        <input type="password" class="form-control" name="current_password" 
                               placeholder="Masukkan password saat ini" required
                               value="<?php echo htmlspecialchars($_POST['current_password'] ?? ''); ?>">
                        <button type="button" class="password-toggle" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <div class="password-input-group">
                        <input type="password" class="form-control" name="new_password" 
                               id="new_password" placeholder="Masukkan password baru" required
                               value="<?php echo htmlspecialchars($_POST['new_password'] ?? ''); ?>">
                        <button type="button" class="password-toggle" data-target="new_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter" id="strengthMeter"></div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <div class="password-input-group">
                        <input type="password" class="form-control" name="confirm_password" 
                               placeholder="Masukkan ulang password baru" required
                               value="<?php echo htmlspecialchars($_POST['confirm_password'] ?? ''); ?>">
                        <button type="button" class="password-toggle" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatch" class="mt-2" style="font-size: 0.9rem;"></div>
                </div>
                
                <div class="password-requirements">
                    <h6 class="mb-3">Syarat Password:</h6>
                    <div class="requirement-item">
                        <i class="fas fa-circle" id="reqLength"></i>
                        <span>Minimal 6 karakter</span>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-circle" id="reqUpper"></i>
                        <span>Minimal 1 huruf besar</span>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-circle" id="reqNumber"></i>
                        <span>Minimal 1 angka</span>
                    </div>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-change">
                    <i class="fas fa-save me-2"></i> Ubah Password
                </button>
                
                <a href="dashboard.php" class="btn btn-back">
                    <i class="fas fa-times me-2"></i> Batal
                </a>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                const input = document.querySelector(`[name="${target}"]`);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'fas fa-eye-slash';
                } else {
                    input.type = 'password';
                    icon.className = 'fas fa-eye';
                }
            });
        });
        
        // Password strength checker
        const newPassword = document.getElementById('new_password');
        const strengthMeter = document.getElementById('strengthMeter');
        const confirmPassword = document.querySelector('[name="confirm_password"]');
        const passwordMatch = document.getElementById('passwordMatch');
        
        // Requirement elements
        const reqLength = document.getElementById('reqLength');
        const reqUpper = document.getElementById('reqUpper');
        const reqNumber = document.getElementById('reqNumber');
        
        newPassword.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check requirements
            const hasLength = password.length >= 6;
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            
            // Update requirement icons
            updateRequirement(reqLength, hasLength);
            updateRequirement(reqUpper, hasUpper);
            updateRequirement(reqNumber, hasNumber);
            
            // Calculate strength
            if (hasLength) strength++;
            if (hasUpper) strength++;
            if (hasNumber) strength++;
            if (password.length >= 10) strength++;
            if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength++;
            
            // Update strength meter
            strengthMeter.className = 'strength-meter';
            if (strength > 0) {
                if (strength <= 2) {
                    strengthMeter.classList.add('strength-weak');
                } else if (strength === 3) {
                    strengthMeter.classList.add('strength-medium');
                } else if (strength === 4) {
                    strengthMeter.classList.add('strength-good');
                } else if (strength >= 5) {
                    strengthMeter.classList.add('strength-strong');
                }
            }
            
            // Check password match
            checkPasswordMatch();
        });
        
        confirmPassword.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const newPass = newPassword.value;
            const confirmPass = confirmPassword.value;
            
            if (confirmPass === '') {
                passwordMatch.innerHTML = '';
                passwordMatch.className = '';
            } else if (newPass === confirmPass) {
                passwordMatch.innerHTML = '<i class="fas fa-check-circle me-1"></i> Password cocok';
                passwordMatch.className = 'text-success';
            } else {
                passwordMatch.innerHTML = '<i class="fas fa-times-circle me-1"></i> Password tidak cocok';
                passwordMatch.className = 'text-danger';
            }
        }
        
        function updateRequirement(element, isValid) {
            if (isValid) {
                element.className = 'fas fa-check-circle requirement-valid';
            } else {
                element.className = 'fas fa-times-circle requirement-invalid';
            }
        }
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const currentPass = document.querySelector('[name="current_password"]').value;
            const newPass = document.querySelector('[name="new_password"]').value;
            const confirmPass = document.querySelector('[name="confirm_password"]').value;
            
            if (newPass.length < 6) {
                e.preventDefault();
                alert('Password baru minimal 6 karakter!');
                return false;
            }
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Konfirmasi password tidak cocok!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>