<?php

require_once 'config.php';

$error = '';
$success = '';

// Proses registrasi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    
    // Validasi dasar
    if (empty($username) || empty($password) || empty($email)) {
        $error = "Please fill all required fields!";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Cek koneksi database
        if (!$conn) {
            $error = "Database connection failed!";
        } else {
            // Cek jika username atau email sudah ada
            $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Username or email already exists!";
                } else {
                    // SOLUSI: Gunakan password_hash dengan PASSWORD_DEFAULT (menghasilkan hash 60+ karakter)
                    // PASTIKAN kolom password di database adalah VARCHAR(255)!
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Coba query dengan created_at dulu
                    $insert_sql = "INSERT INTO users (username, password, email, full_name, created_at) VALUES (?, ?, ?, ?, NOW())";
                    $stmt = mysqli_prepare($conn, $insert_sql);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $email, $full_name);
                    } else {
                        // Jika gagal (mungkin kolom created_at tidak ada), coba tanpa created_at
                        $insert_sql = "INSERT INTO users (username, password, email, full_name) VALUES (?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $insert_sql);
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $email, $full_name);
                        }
                    }
                    
                    if (isset($stmt) && $stmt) {
                        if (mysqli_stmt_execute($stmt)) {
                            // Redirect ke login dengan pesan sukses
                            header("Location: login.php?registered=success");
                            exit();
                        } else {
                            // Debug error
                            $error_msg = mysqli_error($conn);
                            $error = "Registration failed! Error: " . $error_msg;
                            
                            // Jika error karena kolom password terlalu pendek
                            if (strpos($error_msg, 'Data too long for column') !== false && strpos($error_msg, 'password') !== false) {
                                $error .= "<br><strong>SOLUSI:</strong> Ubah tipe data kolom 'password' di database menjadi VARCHAR(255).<br>";
                                $error .= "Jalankan query ini di phpMyAdmin:<br>";
                                $error .= "<code>ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL;</code>";
                            }
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Failed to prepare SQL statement!";
                    }
                }
                mysqli_stmt_close($check_stmt);
            } else {
                $error = "Database query preparation failed!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login System - Register</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 40px 30px;
        }
        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-register:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-right: none;
            padding: 0 15px;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .strength-0 { width: 20%; background: #dc3545; }
        .strength-1 { width: 40%; background: #dc3545; }
        .strength-2 { width: 60%; background: #ffc107; }
        .strength-3 { width: 80%; background: #28a745; }
        .strength-4 { width: 100%; background: #28a745; }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 576px) {
            .register-body {
                padding: 30px 20px;
            }
            .register-header {
                padding: 20px;
            }
        }
        .error-solution {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
    </style>
  </head>
  <body>
    <div class="register-card">
        <div class="register-header">
            <i class="fas fa-user-plus fa-3x mb-3"></i>
            <h2>Create Account</h2>
            <p>Join our community today</p>
        </div>
        
        <div class="register-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php 
                    // Tampilkan error dengan parsing HTML jika ada
                    echo str_replace("\n", "<br>", htmlspecialchars($error));
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label for="full_name">Full Name <small class="text-muted">(Optional)</small></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        </div>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               placeholder="Enter your full name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Choose a username" required 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>"
                               minlength="3" maxlength="50">
                    </div>
                    <small class="text-muted">Minimum 3 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="Enter your email" required 
                               value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                        </div>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Create password" required minlength="6">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="password-strength strength-0" id="password-strength"></div>
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                        </div>
                        <input type="password" class="form-control" id="confirm_password" 
                               name="confirm_password" placeholder="Confirm password" required minlength="6">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div id="password-match" class="mt-2"></div>
                </div>
                
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" data-toggle="modal" data-target="#termsModal">Terms & Conditions</a>
                        <span class="text-danger">*</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-register" id="submitBtn">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </button>
            </form>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <!-- Terms & Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms & Conditions</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>By creating an account, you agree to our terms and conditions:</p>
                    <ul>
                        <li>You must provide accurate information</li>
                        <li>You are responsible for maintaining the confidentiality of your account</li>
                        <li>You must not use the service for illegal activities</li>
                        <li>We reserve the right to terminate accounts that violate our policies</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').click(function() {
                var passwordInput = $('#password');
                var icon = $(this).find('i');
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Toggle confirm password visibility
            $('#toggleConfirmPassword').click(function() {
                var passwordInput = $('#confirm_password');
                var icon = $(this).find('i');
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Password strength indicator
            $('#password').on('keyup', function() {
                var password = $(this).val();
                var strength = 0;
                
                // Length check
                if (password.length >= 8) strength++;
                
                // Lowercase check
                if (password.match(/[a-z]+/)) strength++;
                
                // Uppercase check
                if (password.match(/[A-Z]+/)) strength++;
                
                // Number check
                if (password.match(/[0-9]+/)) strength++;
                
                // Special character check
                if (password.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/)) strength++;
                
                // Limit strength to 4
                strength = Math.min(strength, 4);
                
                // Update strength bar
                $('#password-strength').removeClass().addClass('password-strength strength-' + strength);
            });
            
            // Password match check
            $('#confirm_password').on('keyup', function() {
                var password = $('#password').val();
                var confirmPassword = $(this).val();
                
                if (confirmPassword === '') {
                    $('#password-match').html('');
                } else if (password === confirmPassword) {
                    $('#password-match').html('<small class="text-success"><i class="fas fa-check-circle"></i> Passwords match</small>');
                } else {
                    $('#password-match').html('<small class="text-danger"><i class="fas fa-times-circle"></i> Passwords do not match</small>');
                }
            });
            
            // Form validation before submit
            $('#registerForm').submit(function(e) {
                var password = $('#password').val();
                var confirmPassword = $('#confirm_password').val();
                var terms = $('#terms').is(':checked');
                
                if (!terms) {
                    e.preventDefault();
                    alert('Please agree to the Terms & Conditions!');
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                // Disable submit button to prevent double submission
                $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Creating Account...');
                
                return true;
            });
            
            // Auto-check terms if modal is closed with OK
            $('#termsModal').on('hidden.bs.modal', function () {
                $('#terms').prop('checked', true);
            });
        });
    </script>
  </body>
</html>
<?php 
// Tutup koneksi jika ada
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>