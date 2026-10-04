<?php
/**
 * NAFCONM - Secure Password Recovery
 * Features: Deeply Professional HTML Email Template, Anti-Enumeration, Token Hashing, CSRF Protection
 */
session_start();
require_once 'config/db.php';

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. CSRF Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security validation failed. Please refresh the page.";
    } else {
        // 2. Rate Limiting (Prevent spamming the reset endpoint)
        if (isset($_SESSION['last_reset_time']) && (time() - $_SESSION['last_reset_time']) < 60) {
            $error = "Please wait 60 seconds before requesting another reset link.";
        } else {
            $email = trim($_POST['email']);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                $_SESSION['last_reset_time'] = time(); // Update rate limit timer

                // 3. Search for User
                $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows === 1) {
                    $user = $res->fetch_assoc();
                    
                    // 4. Generate Cryptographically Secure Token
                    $raw_token = bin2hex(random_bytes(32));
                    $hashed_token = hash('sha256', $raw_token); // Store hashed token to prevent DB breach exploitation
                    $expires = date("Y-m-d H:i:s", time() + 3600); // 1 Hour Expiry

                    try {
                        // 5. Save Hashed Token to Database
                        $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                        $upd->bind_param("ssi", $hashed_token, $expires, $user['id']);
                        $upd->execute();
                        
                        // 6. ENTERPRISE HTML EMAIL DISPATCH
                        $reset_link = "https://nafconm.edu.ng/portal/reset_password.php?token=" . $raw_token . "&email=" . urlencode($email);
                        $subject = "Password Reset Request - NAFCONM Portal";
                        
                        // Email Headers for HTML content
                        $headers  = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
                        $headers .= "From: NAFCONM IT Services <noreply@nafconm.edu.ng>" . "\r\n";
                        $headers .= "Reply-To: support@nafconm.edu.ng" . "\r\n";

                        // Professional HTML Email Template
                        $email_body = '
                        <!DOCTYPE html>
                        <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <style>
                                body { font-family: "Segoe UI", Arial, sans-serif; background-color: #f4f7fa; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
                                .email-wrapper { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
                                .email-header { background-color: #1e293b; padding: 30px 40px; text-align: center; }
                                .email-header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 900; letter-spacing: 1px; }
                                .email-body { padding: 40px; color: #334155; line-height: 1.6; font-size: 16px; }
                                .btn-primary { display: inline-block; padding: 14px 30px; background-color: #5D87FF; color: #ffffff !important; text-decoration: none; border-radius: 50px; font-weight: bold; margin: 25px 0; text-align: center; }
                                .security-notice { background-color: #fff8f1; border-left: 4px solid #f59e0b; padding: 15px 20px; margin-top: 30px; font-size: 14px; color: #92400e; }
                                .raw-link { word-break: break-all; color: #5D87FF; font-size: 13px; margin-top: 20px; display: block; }
                                .email-footer { background-color: #f8fafc; padding: 25px 40px; text-align: center; font-size: 13px; color: #64748b; border-top: 1px solid #e2e8f0; }
                            </style>
                        </head>
                        <body>
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f7fa; padding: 20px 0;">
                                <tr>
                                    <td align="center">
                                        <div class="email-wrapper" style="text-align: left;">
                                            <div class="email-header">
                                                <h1>NAFCONM PORTAL</h1>
                                            </div>
                                            <div class="email-body">
                                                <h2 style="color: #0f172a; margin-top: 0;">Password Reset Request</h2>
                                                <p>Hello,</p>
                                                <p>We recently received a request to reset the password for your Nafisatu College of Nursing & Midwifery (NAFCONM) portal account associated with this email address.</p>
                                                <p>To set up a new password, please click the secure link below. For your protection, this link is only valid for <strong>1 hour</strong>.</p>
                                                
                                                <div style="text-align: center;">
                                                    <a href="' . $reset_link . '" class="btn-primary">Reset My Password</a>
                                                </div>

                                                <p style="font-size: 14px; color: #64748b;">If the button above does not work, copy and paste the following URL into your web browser:</p>
                                                <a href="' . $reset_link . '" class="raw-link">' . $reset_link . '</a>

                                                <div class="security-notice">
                                                    <strong>Security Notice:</strong> If you did not initiate this request, you can safely ignore this email. Your password will not change, and your account remains secure.
                                                </div>
                                            </div>
                                            <div class="email-footer">
                                                <p style="margin: 0 0 10px 0;"><strong>Nafisatu College of Nursing & Midwifery</strong><br>Kano State, Nigeria</p>
                                                <p style="margin: 0;">&copy; ' . date('Y') . ' NAFCONM IT Services. All rights reserved.</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </body>
                        </html>
                        ';

                        // Dispatch Email (Silent failure to preserve anti-enumeration)
                        @mail($email, $subject, $email_body, $headers);

                    } catch (Exception $e) {
                        // Silently handle exceptions to prevent data leaks
                    }
                }
                
                // 7. Anti-Enumeration Response
                if (empty($message)) {
                    $message = "success|If an account with that email exists, a password reset link has been sent to it. Please check your inbox and spam folder.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NAFCONM Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <style>
        body { background-color: #f4f7fa; font-family: 'Inter', system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .recovery-card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); overflow: hidden; max-width: 500px; width: 100%; margin: 20px; background: #fff; padding: 40px; }
        .form-floating > label { color: #64748b; font-weight: 500; padding-left: 1.25rem; }
        .form-control { border-radius: 12px; border: 1.5px solid #e2e8f0; padding-left: 1.25rem; transition: 0.3s ease; }
        .form-control:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); }
        .fw-black { font-weight: 900 !important; }
        .hover-link { transition: 0.2s; }
        .hover-link:hover { color: #1e4db7 !important; text-decoration: underline !important; }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .error-shake { animation: shake 0.4s; }
    </style>
</head>
<body>

    <div class="recovery-card text-center">
        
        <img src="assets/images/logos/favicon.png" width="80" class="mb-3" alt="NAFCONM Logo">
        <h3 class="fw-black text-dark mb-2">Reset Password</h3>
        <p class="text-muted small mb-4 px-3" style="line-height: 1.6;">Enter the email address associated with your account and we will send you a secure link to reset your password.</p>

        <?php if (!empty($error)): ?>
            <div class="alert bg-danger-subtle text-danger border-0 rounded-3 small fw-bold mb-4 error-shake text-start d-flex align-items-center">
                <i class="ti ti-alert-triangle fs-5 me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): list($color, $txt) = explode('|', $message); ?>
            <div class="alert bg-success-subtle text-success border-0 rounded-3 small fw-medium mb-4 text-start d-flex">
                <i class="ti ti-mail-fast fs-2 me-3 mt-1"></i>
                <div><?= $txt ?></div>
            </div>
            <a href="login.php" class="btn btn-primary w-100 py-3 rounded-pill fw-black shadow-sm mb-3">Return to Login</a>
        <?php else: ?>

            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="recoveryForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-floating mb-4 text-start">
                    <input type="email" name="email" class="form-control" id="email" placeholder="name@example.com" required autocomplete="email">
                    <label for="email">Official Email Address</label>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary w-100 py-3 rounded-pill fw-black shadow-sm mb-3 d-flex justify-content-center align-items-center transition-all">
                    <span id="btnText">Send Reset Link</span> <i id="btnIcon" class="ti ti-send ms-2 fs-5"></i>
                    <div id="btnSpinner" class="spinner-border spinner-border-sm text-light ms-2 d-none" role="status"></div>
                </button>
                
                <a href="login.php" class="text-muted small fw-bold text-decoration-none hover-link"><i class="ti ti-arrow-left me-1"></i> Back to Login</a>
            </form>

        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const recoveryForm = document.getElementById('recoveryForm');
            if (recoveryForm) {
                const submitBtn = document.getElementById('submitBtn');
                const btnText = document.getElementById('btnText');
                const btnIcon = document.getElementById('btnIcon');
                const btnSpinner = document.getElementById('btnSpinner');

                recoveryForm.addEventListener('submit', function(e) {
                    if(!recoveryForm.checkValidity()) {
                        return; // Let browser handle empty HTML5 validation
                    }
                    
                    // Form is valid, lock button to prevent double-click / spamming
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-75');
                    btnText.innerText = "Sending Link...";
                    btnIcon.classList.add('d-none');
                    btnSpinner.classList.remove('d-none');
                });
            }
        });
    </script>
</body>
</html>