<?php
/**
 * NAFCONM - Enterprise Tier-1 Login Router
 * Features: Brute-Force Protection, Strict Security Headers, Glassmorphism UI
 */

// 1. STRICT SECURE SESSION CONFIGURATION
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. ENTERPRISE SECURITY HEADERS
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

require_once 'config/db.php';

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect already logged-in users
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $role = strtolower($_SESSION["user_type"] ?? '');
    if ($role === 'superadmin') { header("Location: /app/index.php"); exit; } 
    elseif ($role === 'teacher' || $role === 'staff') { header("Location: /teachers/index.php"); exit; } 
    elseif ($role === 'student') { header("Location: /student/index.php"); exit; }
}

// 3. BRUTE FORCE PROTECTION (Rate Limiting)
$max_attempts = 5;
$lockout_time = 300; // 5 minutes

if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_end'])) $_SESSION['lockout_end'] = 0;

if ($_SESSION['lockout_end'] > time()) {
    $remaining_time = ceil(($_SESSION['lockout_end'] - time()) / 60);
    $fatal_error = "Account temporarily locked due to multiple failed attempts. Try again in $remaining_time minute(s).";
}

$username = $password = $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($fatal_error)) {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security validation failed. Please refresh the page.";
    } else {
        $username = trim($_POST["username"] ?? '');
        $password = trim($_POST["password"] ?? '');

        if (empty($username) || empty($password)) {
            $error = "Please enter your Staff ID, Matric No, or Email and Password.";
        } else {
            // Prepared statement checking username or email
            $stmt = $conn->prepare("SELECT id, username, email, password, user_type FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?) LIMIT 1");
            $stmt->bind_param("ss", $username, $username);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    if (password_verify($password, $user['password'])) {
                        // SUCCESS: Reset brute-force counters and secure session
                        $_SESSION['login_attempts'] = 0;
                        session_regenerate_id(true);

                        $role = strtolower(trim($user['user_type']));

                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = (int)$user['id'];
                        $_SESSION["username"] = $user['username'];
                        $_SESSION["user_type"] = $role;
                        $_SESSION["privileges"] = []; // Hydrated dynamically on dashboard

                        // Role-based routing
                        if ($role === 'superadmin') { header("Location: app/index.php"); } 
                        elseif ($role === 'teacher' || $role === 'staff') { header("Location: teachers/index.php"); } 
                        elseif ($role === 'student') { header("Location: student/index.php"); } 
                        else { header("Location: /index.php"); }
                        exit;
                    } else {
                        $_SESSION['login_attempts']++;
                        $error = "Invalid password. Please check your credentials.";
                    }
                } else {
                    $_SESSION['login_attempts']++;
                    $error = "No account found matching that ID or Email.";
                }

                // Trigger Lockout if threshold reached
                if ($_SESSION['login_attempts'] >= $max_attempts) {
                    $_SESSION['lockout_end'] = time() + $lockout_time;
                    $error = "Too many failed attempts. Your account has been temporarily locked for security.";
                }

            } else {
                $error = "Database anomaly detected. System administrators have been notified.";
            }
            $stmt->close();
        }
    }
}

// Dynamic Intelligence: Time-based Greeting
date_default_timezone_set('Africa/Lagos');
$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 17) $greeting = "Good Afternoon";
else $greeting = "Good Evening";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Login - NAFCONM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --brand-primary: #0f172a;
            --brand-accent: #5D87FF;
        }
        
        body { 
            background-color: var(--brand-primary);
            font-family: 'Inter', system-ui, sans-serif; 
            display: flex; align-items: center; justify-content: center; 
            min-height: 100vh; margin: 0; 
            overflow: hidden;
            color: #f8fafc;
        }

        /* Animated Glowing Background Orbs */
        .animated-bg {
            position: absolute;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0;
            overflow: hidden;
        }
        .orb {
            position: absolute;
            filter: blur(100px);
            border-radius: 50%;
            animation: floatOrb 20s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0.6;
        }
        .orb-1 { width: 500px; height: 500px; background: #5D87FF; top: -10%; left: -10%; animation-duration: 25s; }
        .orb-2 { width: 600px; height: 600px; background: #13DEB9; bottom: -20%; right: -10%; animation-duration: 30s; animation-direction: alternate-reverse; }
        .orb-3 { width: 400px; height: 400px; background: #8a2be2; top: 30%; left: 40%; animation-duration: 22s; }

        @keyframes floatOrb {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(150px, 100px) scale(1.2); }
        }

        /* Glassmorphism Card Container */
        .login-wrapper {
            position: relative;
            z-index: 10;
            max-width: 1000px; 
            width: 100%; 
            margin: 20px;
            padding: 15px;
        }
        
        .glass-card { 
            background: rgba(255, 255, 255, 0.03); 
            backdrop-filter: blur(25px); 
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 30px; 
            box-shadow: 0 30px 60px -12px rgba(0,0,0,0.5); 
            overflow: hidden; 
        }
        
        /* Left Brand Panel (More transparent to see background) */
        .glass-panel-left {
            background: rgba(255, 255, 255, 0.02);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Form Inputs - Glass Effect */
        .form-floating > label { color: rgba(255, 255, 255, 0.7); font-weight: 500; }
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #fff;
        }
        
        .form-control { 
            background: rgba(255, 255, 255, 0.05) !important; 
            border: 1px solid rgba(255, 255, 255, 0.1) !important; 
            color: #fff !important;
            border-radius: 16px; 
            padding-left: 1.25rem; font-weight: 600;
            transition: all 0.3s ease;
        }
        .form-control:focus { 
            border-color: rgba(255, 255, 255, 0.5) !important; 
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.1) !important; 
            background: rgba(255, 255, 255, 0.1) !important;
        }
        
        .form-control::placeholder { color: transparent; } /* Hidden for floating label logic */
        
        .fw-black { font-weight: 900 !important; }
        
        .password-toggle { 
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%); 
            cursor: pointer; color: rgba(255, 255, 255, 0.6); z-index: 10; transition: 0.2s;
            padding: 5px;
        }
        .password-toggle:hover { color: #fff; }

        .btn-login {
            background: linear-gradient(135deg, #5D87FF 0%, #3e63d3 100%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50px;
            padding: 15px 20px;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(93, 135, 255, 0.3);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(93, 135, 255, 0.4);
        }

        /* Subtle badge styling */
        .glass-badge {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>

    <!-- Animated Background Orbs -->
    <div class="animated-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="login-wrapper">
        <div class="glass-card d-flex flex-column flex-lg-row">
            
            <!-- Brand Panel -->
            <div class="col-lg-5 glass-panel-left p-5 text-center d-flex flex-column justify-content-center align-items-center">
                <div class="bg-white p-3 rounded-4 shadow-lg mb-4" style="background: rgba(255,255,255,0.1) !important; border: 1px solid rgba(255,255,255,0.2);">
                    <img src="assets/images/logos/favicon.png" width="90" alt="NAFCONM Logo" style="filter: drop-shadow(0px 10px 10px rgba(0,0,0,0.3));">
                </div>
                <h2 class="fw-black mb-3" style="letter-spacing: 1px;">NAFCONM</h2>
                <p class="opacity-75 small mb-0 fw-medium" style="line-height: 1.8; max-width: 85%;">
                    Nafisatu College of Nursing & Midwifery.<br>Central Academic & Administrative Hub.
                </p>
                
                <div class="mt-5 pt-4 border-top border-light border-opacity-10 w-100">
                    <p class="small text-white-50 mb-0 font-monospace d-flex align-items-center justify-content-center">
                        <span class="spinner-grow spinner-grow-sm text-success me-2" role="status" style="width: 10px; height: 10px;"></span> 
                        Secure & Encrypted Connection
                    </p>
                </div>
            </div>

            <!-- Intelligent Form Panel -->
            <div class="col-lg-7 p-4 p-md-5 p-xl-6 d-flex flex-column justify-content-center">
                <div class="mb-4">
                    <span class="badge glass-badge px-3 py-2 rounded-pill fw-bold mb-3 d-inline-block">
                        <i class="ti ti-sun me-1"></i> <?= $greeting ?>
                    </span>
                    <h2 class="fw-black text-white mb-1">Welcome Back</h2>
                    <p class="text-white-50 fw-medium mb-0">Authenticate your identity to continue.</p>
                </div>

                <?php if (!empty($fatal_error)): ?>
                    <div class="alert bg-danger text-white bg-opacity-25 border border-danger border-opacity-50 rounded-4 p-4 fw-bold d-flex align-items-center shadow-sm" style="backdrop-filter: blur(10px);">
                        <i class="ti ti-lock-access fs-2 me-3"></i>
                        <div><?= htmlspecialchars($fatal_error) ?></div>
                    </div>
                <?php else: ?>
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div class="form-floating mb-3">
                            <input type="text" name="username" class="form-control" id="username" placeholder="Staff ID / Matric / Email" value="<?= htmlspecialchars($username) ?>" required autocomplete="username" autofocus>
                            <label for="username"><i class="ti ti-user me-2"></i>Staff ID / Matric No. / Email</label>
                        </div>
                        
                        <div class="form-floating mb-4 position-relative">
                            <input type="password" name="password" class="form-control pe-5" id="password" placeholder="Password" required autocomplete="current-password">
                            <label for="password"><i class="ti ti-lock me-2"></i>Security Password</label>
                            <div class="password-toggle" id="togglePassword">
                                <i class="ti ti-eye fs-5"></i>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4 px-1">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input border-secondary bg-transparent" id="remember" style="cursor: pointer;">
                                <label class="form-check-label small fw-bold text-white-50" for="remember" style="cursor: pointer;">Remember Device</label>
                            </div>
                            <a href="forgot_password.php" class="text-white small fw-bold text-decoration-none border-bottom border-light pb-1 opacity-75 hover-opacity-100 transition-all">Forgot Password?</a>
                        </div>

                        <button type="submit" id="loginBtn" class="btn btn-login w-100 d-flex justify-content-center align-items-center mt-2">
                            <span id="btnText">Authenticate Securely</span> 
                            <i id="btnIcon" class="ti ti-arrow-right ms-2 fs-5"></i>
                            <div id="btnSpinner" class="spinner-border spinner-border-sm text-white ms-2 d-none" role="status"></div>
                        </button>
                        
                        <div class="text-center mt-5">
                            <p class="text-white-50 small fw-medium mb-0">
                                Prospective applicant? 
                                <a href="apply.php" class="text-white fw-bold text-decoration-none border-bottom border-white pb-1 ms-1">Start Application</a>
                            </p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Interactive Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // 1. Password Visibility Engine
            const togglePassword = document.querySelector('#togglePassword');
            if (togglePassword) {
                togglePassword.addEventListener('click', function () {
                    const passwordInput = document.querySelector('#password');
                    const icon = this.querySelector('i');
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    icon.classList.toggle('ti-eye');
                    icon.classList.toggle('ti-eye-off');
                    icon.style.color = type === 'text' ? '#fff' : 'rgba(255, 255, 255, 0.6)';
                });
            }

            // 2. Submit Animation & Double-Click Prevention
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    // Let HTML5 validation finish first
                    if (this.checkValidity()) {
                        const btn = document.getElementById('loginBtn');
                        const text = document.getElementById('btnText');
                        const icon = document.getElementById('btnIcon');
                        const spinner = document.getElementById('btnSpinner');

                        btn.style.pointerEvents = 'none';
                        btn.style.opacity = '0.8';
                        text.innerText = "Encrypting & Verifying...";
                        icon.classList.add('d-none');
                        spinner.classList.remove('d-none');
                    }
                });
            }

            // 3. SweetAlert2 Threat Notification
            <?php if (!empty($error) && empty($fatal_error)): ?>
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Authentication Failed',
                    text: <?= json_encode($error) ?>,
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true,
                    background: 'rgba(15, 23, 42, 0.9)',
                    backdropFilter: 'blur(10px)',
                    color: '#fff',
                    iconColor: '#ff6b6b',
                    customClass: { popup: 'rounded-4 shadow-lg border border-secondary border-opacity-25' }
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>