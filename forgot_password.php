[file name]: forgot_password.php
[file content begin]
<?php
require_once 'config.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Cek apakah email terdaftar
    $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Generate token reset password
        $token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Simpan token ke database
        $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssi", $token, $token_expiry, $user['id']);
        mysqli_stmt_execute($update_stmt);
        
        // Simpan log reset password
        if (function_exists('log_password_reset')) {
            log_password_reset($conn, $user['id']);
        }
        
        // Kirim email reset password (simulasi)
        // Dalam implementasi nyata, kirim email dengan link reset
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
        
        $success = "Link reset password telah dikirim ke email Anda. <br><small>Simulasi: <a href='$reset_link' class='alert-link'>$reset_link</a></small>";
        
        // Catat aktivitas
        $activity_sql = "INSERT INTO password_reset_requests (user_id, email, token, created_at) VALUES (?, ?, ?, NOW())";
        $activity_stmt = mysqli_prepare($conn, $activity_sql);
        mysqli_stmt_bind_param($activity_stmt, "iss", $user['id'], $email, $token);
        mysqli_stmt_execute($activity_stmt);
        
    } else {
        $error = "Email tidak terdaftar atau akun tidak aktif!";
    }
    
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Lupa Password - BPJS Kesehatan</title>
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
            --bpjs-light-blue: #e6f2ff;
            --bpjs-dark-blue: #004d99;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0066cc 0%, #00a859 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23004d99" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            opacity: 0.3;
            z-index: -1;
        }
        
        .reset-container {
            width: 100%;
            max-width: 500px;
        }
        
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            padding: 40px;
        }
        
        .bpjs-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border: 2px solid #e6f2ff;
        }
        
        .bpjs-logo i {
            font-size: 40px;
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .reset-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .reset-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .alert-danger {
            background: #ffe6e6;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border-left: 4px solid #1565c0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-group {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
            transition: border-color 0.3s;
        }
        
        .input-group:focus-within {
            border-color: var(--bpjs-blue);
            box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.1);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: none;
            color: #666;
            padding: 15px;
        }
        
        .form-control {
            border: none;
            padding: 15px;
            font-size: 1rem;
            color: #333;
            background: white;
        }
        
        .form-control:focus {
            box-shadow: none;
            background: white;
        }
        
        .form-control::placeholder {
            color: #999;
        }
        
        .btn-reset {
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            border: none;
            color: white;
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 102, 204, 0.3);
            background: linear-gradient(135deg, #0052a3, #00994d);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-back {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            color: #333;
            padding: 12px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s;
            width: 100%;
            margin-top: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: #e9ecef;
            color: #333;
            text-decoration: none;
        }
        
        .instructions {
            background: var(--bpjs-light-blue);
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            margin-bottom: 20px;
        }
        
        .instructions h6 {
            color: var(--bpjs-blue);
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .instructions h6 i {
            margin-right: 10px;
        }
        
        .instructions ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .instructions li {
            margin-bottom: 8px;
            color: #333;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .instructions li:last-child {
            margin-bottom: 0;
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .reset-container {
                padding: 10px;
            }
            
            .reset-card {
                padding: 30px 20px;
            }
            
            .reset-title {
                font-size: 1.5rem;
            }
            
            .reset-subtitle {
                font-size: 0.9rem;
            }
        }
    </style>
  </head>
  <body>
    <div class="reset-container animate-fadeIn">
        <div class="reset-card">
            <div class="bpjs-logo">
                <i class="fas fa-key"></i>
            </div>
            
            <h2 class="reset-title">Reset Password</h2>
            <p class="reset-subtitle">
                Masukkan email yang terdaftar untuk menerima link reset password
            </p>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Link reset password akan dikirim ke email Anda dan berlaku selama 1 jam.
            </div>
            
            <form method="POST" action="" id="resetForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email Terdaftar</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="contoh: nama@email.com" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-reset">
                    <i class="fas fa-paper-plane me-2"></i> Kirim Link Reset Password
                </button>
                
                <a href="index.php" class="btn btn-back">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Login
                </a>
            </form>
            
            <div class="instructions">
                <h6><i class="fas fa-lightbulb"></i> Petunjuk Reset Password:</h6>
                <ul>
                    <li>Pastikan email yang dimasukkan sesuai dengan email pendaftaran</li>
                    <li>Periksa folder spam jika email tidak ditemukan di inbox</li>
                    <li>Link reset password hanya berlaku selama 1 jam</li>
                    <li>Hubungi call center jika mengalami kesulitan</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Forgot password page loaded');
            
            // Form validation
            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    const email = document.getElementById('email').value.trim();
                    
                    if (!email) {
                        e.preventDefault();
                        alert('Email harus diisi!');
                        return false;
                    }
                    
                    // Validasi format email sederhana
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Format email tidak valid!');
                        return false;
                    }
                    
                    console.log('Reset password form submitted for email:', email);
                    return true;
                });
            }
            
            // Auto focus on email field
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.focus();
            }
        });
    </script>
  </body>
</html>
<?php if(isset($conn)) mysqli_close($conn); ?>
[file content end]