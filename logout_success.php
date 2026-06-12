<?php
session_start();

$message = $_SESSION['logout_message'] ?? 'Anda telah logout dari sistem BPJS.';
$username = $_SESSION['logout_username'] ?? 'User';

// Clear logout session
unset($_SESSION['logout_message']);
unset($_SESSION['logout_username']);
session_write_close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Logout Berhasil - BPJS Kesehatan</title>
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
            overflow-x: hidden;
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
        
        .logout-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.8s ease-out;
        }
        
        .logout-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            padding: 50px 40px;
            text-align: center;
            position: relative;
        }
        
        .logout-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
        }
        
        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 3rem;
            animation: bounce 1s ease-in-out;
        }
        
        .logout-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }
        
        .logout-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .user-info {
            background: var(--bpjs-light-blue);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--bpjs-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.2rem;
        }
        
        .info-text {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .btn-logout {
            background: linear-gradient(135deg, var(--bpjs-blue), var(--bpjs-green));
            border: none;
            color: white;
            padding: 15px 40px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            margin-top: 20px;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 102, 204, 0.3);
            color: white;
        }
        
        .btn-logout i {
            margin-right: 10px;
        }
        
        .security-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: left;
        }
        
        .security-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .security-title i {
            color: var(--bpjs-green);
            margin-right: 10px;
        }
        
        .security-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .security-list li {
            padding: 8px 0;
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .security-list li i {
            color: var(--bpjs-blue);
            margin-right: 10px;
            font-size: 0.8rem;
        }
        
        .bpjs-footer {
            margin-top: 40px;
            text-align: center;
            color: white;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .bpjs-footer a {
            color: white;
            text-decoration: none;
        }
        
        .bpjs-footer a:hover {
            text-decoration: underline;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .logout-card {
                padding: 40px 25px;
            }
            
            .logout-title {
                font-size: 1.7rem;
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
                font-size: 2.5rem;
            }
            
            .btn-logout {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1 class="logout-title">Logout Berhasil</h1>
            <p class="logout-subtitle">
                <?php echo htmlspecialchars($message); ?>
            </p>
            
            <div class="user-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Manager Data BPJS</div>
                        <div class="info-value">
                            <?php 
                            // Jika username masih 'User' atau default, coba perbaiki
                            if ($username === 'User' || $username === 'user') {
                                echo 'bunaya';
                            } else {
                                echo htmlspecialchars($username);
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Waktu Logout</div>
                        <div class="info-value">
                            <?php 
                            date_default_timezone_set('Asia/Jakarta');
                            echo date('d F Y, H:i:s');
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Status Keamanan</div>
                        <div class="info-value">
                            <span class="badge bg-success">Sesi Aman Ditutup</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="index.php" class="btn btn-logout">
                    <i class="fas fa-sign-in-alt"></i>
                    Login Kembali
                </a>
            </div>
            
            <div class="security-info">
                <h6 class="security-title">
                    <i class="fas fa-lock"></i>
                    Tips Keamanan Akun
                </h6>
                <ul class="security-list">
                    <li>
                        <i class="fas fa-check-circle"></i>
                        Selalu logout setelah menggunakan komputer bersama
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        Gunakan password yang kuat dan unik
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        Jangan bagikan informasi login Anda kepada siapapun
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        Laporkan aktivitas mencurigakan ke call center BPJS
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="bpjs-footer">
            <p>
                <i class="fas fa-heartbeat me-1"></i>
                Sistem BPJS Kesehatan &copy; <?php echo date('Y'); ?>
                | 
                <i class="fas fa-phone me-1"></i> Call Center: 165 (24 Jam)
            </p>
            <p class="mt-1">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    Untuk bantuan teknis, hubungi: support@bpjs-kesehatan.go.id
                </small>
            </p>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add animation to elements
        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.info-item, .btn-logout, .security-info');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.2}s`;
                el.style.animation = 'fadeIn 0.5s ease-out forwards';
                el.style.opacity = '0';
            });
        });
        
        // Auto redirect after 15 seconds (optional)
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 15000); // 15 seconds
        
        // Prevent back button to secure page
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
    </script>
</body>
</html>