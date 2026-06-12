<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // TAMBAHKAN INI
require_once 'config.php';
// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = md5($_POST['password']);
    
    $sql = "SELECT * FROM users WHERE username = ? AND password = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $password);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
        mysqli_stmt_execute($update_stmt);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Username atau password salah!";
    }
    
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - BPJS Kesehatan</title>
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
        
        .login-container {
            width: 100%;
            max-width: 1100px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            min-height: 600px;
        }
        
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-dark-blue));
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .login-left::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .bpjs-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .bpjs-logo i {
            font-size: 40px;
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .login-right {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .welcome-text {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            line-height: 1.2;
        }
        
        .sub-text {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 40px;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin-top: 40px;
        }
        
        .feature-list li {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .feature-list i {
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: block;
        }
        
        .input-group {
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .input-group:focus-within {
            border-color: var(--bpjs-blue);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: none;
            padding: 0 20px;
            color: #666;
            border-right: 1px solid #e0e0e0;
        }
        
        .form-control {
            border: none;
            padding: 15px;
            font-size: 1rem;
            flex: 1;
            outline: none;
        }
        
        .form-control:focus {
            box-shadow: none;
        }
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 102, 204, 0.3);
            background: linear-gradient(135deg, #0052a3, #00994d);
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }
        
        .register-link a {
            color: var(--bpjs-blue);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        
        .register-link a:hover {
            text-decoration: underline;
            color: #004d99;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #ffe6e6;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .login-subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1rem;
        }
        
        .bpjs-badge {
            background: linear-gradient(135deg, var(--bpjs-green), #00cc66);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 10px;
        }
        
        .quick-info {
            background: var(--bpjs-light-blue);
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: #333;
        }
        
        .info-item i {
            color: var(--bpjs-blue);
            margin-right: 10px;
            font-size: 1.1rem;
            min-width: 20px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .login-card {
                flex-direction: column;
            }
            
            .login-left, .login-right {
                padding: 40px 30px;
            }
            
            .welcome-text {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 576px) {
            .login-container {
                padding: 10px;
            }
            
            .login-left, .login-right {
                padding: 30px 20px;
            }
            
            .welcome-text {
                font-size: 1.8rem;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Password strength meter */
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
        }
        
        .strength-weak { width: 25%; background: #ff5252; }
        .strength-medium { width: 50%; background: #ffb74d; }
        .strength-good { width: 75%; background: #4caf50; }
        .strength-strong { width: 100%; background: #00c853; }
        
        /* Link styling */
        .forgot-password {
            color: var(--bpjs-blue);
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: #004d99;
            text-decoration: underline;
        }
        
        /* Button styling for links */
        .btn-link {
            background: none;
            border: none;
            color: var(--bpjs-blue);
            padding: 0;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .btn-link:hover {
            text-decoration: underline;
            color: #004d99;
        }
        
        /* Form check styling */
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            margin-right: 8px;
            cursor: pointer;
        }
        
        .form-check-label {
            cursor: pointer;
            font-size: 0.9rem;
            user-select: none;
        }
    </style>
  </head>
  <body>
    <div class="login-container animate-fadeIn">
        <div class="login-card">
            <!-- Left Side - Welcome & Info -->
            <div class="login-left">
                <div class="bpjs-logo">
                    <i class="fas fa-heartbeat"></i>
                </div>
                
                <h1 class="welcome-text">
                    BPJS Kesehatan
                    <span class="bpjs-badge">Digital</span>
                </h1>
                <p class="sub-text">Sistem Informasi Pelayanan Kesehatan Terpadu</p>
                
                <ul class="feature-list">
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <span>Akses aman dan terenkripsi</span>
                    </li>
                    <li>
                        <i class="fas fa-clock"></i>
                        <span>Pelayanan 24 jam non-stop</span>
                    </li>
                    <li>
                        <i class="fas fa-hospital"></i>
                        <span>Jaringan faskes di seluruh Indonesia</span>
                    </li>
                    <li>
                        <i class="fas fa-mobile-alt"></i>
                        <span>Akses mudah dari perangkat apapun</span>
                    </li>
                </ul>
                
                <div class="quick-info">
                    <div class="info-item">
                        <i class="fas fa-phone-alt"></i>
                        <span><strong>Call Center:</strong> 089512345678(24 Jam)</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-globe"></i>
                        <span><strong>Website:</strong> www.bpjs-kesehatan.go.id</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span><strong>Email:</strong> callcenter@bpjs-kesehatan.go.id</span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="login-right">
                <h2 class="login-title">Masuk ke Akun Anda</h2>
                <p class="login-subtitle">Masukkan username dan password untuk mengakses sistem</p>
                
                <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Pendaftaran berhasil! Silakan login.
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Masukkan username" required 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Masukkan password" required
                                   value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>">
                            <button class="input-group-text" type="button" id="togglePassword" style="cursor: pointer; border-left: 1px solid #e0e0e0;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2">
                            <div class="strength-meter" id="strengthMeter"></div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Ingat saya
                            </label>
                        </div>
                        <a href="forgot_password.php" class="forgot-password">
                            <i class="fas fa-key me-1"></i> Lupa password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Masuk ke Sistem
                    </button>
                </form>
                
                <div class="register-link">
                    <p>Belum punya akun? 
                        <a href="register.php" class="text-decoration-none">
                            <i class="fas fa-user-plus me-1"></i> Daftar disini
                        </a>
                    </p>
                </div>
                
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <strong>Akun Demo:</strong><br>
                        <span class="badge bg-light text-dark me-2 mb-1">admin / admin123</span>
                        <span class="badge bg-light text-dark mb-1">user1 / password123</span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Login page loaded');
            
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const strengthMeter = document.getElementById('strengthMeter');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
            }
            
            // Password strength indicator
            if (passwordInput && strengthMeter) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 8) strength++;
                    if (password.match(/[a-z]+/)) strength++;
                    if (password.match(/[A-Z]+/)) strength++;
                    if (password.match(/[0-9]+/)) strength++;
                    if (password.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/)) strength++;
                    
                    // Reset classes
                    strengthMeter.className = 'strength-meter';
                    
                    // Update strength meter
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
                });
            }
            
            // Form validation
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value.trim();
                    
                    if (!username || !password) {
                        e.preventDefault();
                        alert('Username dan password harus diisi!');
                        return false;
                    }
                    
                    console.log('Form submitted with:', {username, password});
                    return true;
                });
            }
            
            // Auto focus on username field
            const usernameField = document.getElementById('username');
            if (usernameField) {
                usernameField.focus();
            }
            
            // Debug: Test if buttons are clickable
            console.log('Testing button functionality...');
            const submitBtn = document.querySelector('.btn-login');
            if (submitBtn) {
                console.log('Submit button found:', submitBtn);
                submitBtn.addEventListener('click', function(e) {
                    console.log('Submit button clicked');
                });
            }
            
            // Test forgot password link
            const forgotLink = document.querySelector('.forgot-password');
            if (forgotLink) {
                console.log('Forgot password link found:', forgotLink);
                forgotLink.addEventListener('click', function(e) {
                    console.log('Forgot password clicked');
                });
            }
            
            // Test register link
            const registerLink = document.querySelector('.register-link a');
            if (registerLink) {
                console.log('Register link found:', registerLink);
                registerLink.addEventListener('click', function(e) {
                    console.log('Register link clicked');
                });
            }
        });
    </script>
  </body>
</html>
<?php if(isset($conn)) mysqli_close($conn); ?>