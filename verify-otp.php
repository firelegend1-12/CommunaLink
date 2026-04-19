<?php
/**
 * OTP Verification Page
 * User enters the 6-digit code sent to their email
 */

require_once 'config/init.php';
apply_page_security_headers('public');

// Must have OTP email in session
if (!isset($_SESSION['otp_email'])) {
    $_SESSION['error_message'] = "Please register first.";
    header("Location: register.php");
    exit;
}

$email = $_SESSION['otp_email'];
$fullname = $_SESSION['otp_fullname'] ?? '';
$masked_email = substr($email, 0, 3) . '***' . substr($email, strpos($email, '@'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="assets/images/barangay-logo.png" type="image/png">
    <style>
        .otp-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 2.5rem;
        }
        .otp-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .otp-header .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .otp-header .icon-circle i {
            font-size: 32px;
            color: #fff;
        }
        .otp-header h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .otp-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.6;
        }
        .otp-header .email-highlight {
            color: #818cf8;
            font-weight: 600;
        }
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
        }
        .otp-inputs input {
            width: 52px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid var(--surface-color-light);
            border-radius: 12px;
            background: var(--surface-color);
            color: var(--text-primary);
            outline: none;
            transition: all 0.2s;
        }
        .otp-inputs input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        .otp-inputs input.filled {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.1);
        }
        .verify-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.5px;
        }
        .verify-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
        }
        .verify-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .resend-section {
            text-align: center;
            margin-top: 1.5rem;
        }
        .resend-section p {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        .resend-btn {
            background: none;
            border: none;
            color: #818cf8;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: underline;
        }
        .resend-btn:disabled {
            color: var(--text-light);
            cursor: not-allowed;
            text-decoration: none;
        }
        .timer {
            color: #f59e0b;
            font-weight: 600;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-light);
            font-size: 0.85rem;
            text-decoration: none;
        }
        .back-link:hover {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <header class="auth-header">
            <div class="auth-header-left">
                <img src="assets/images/barangay-logo.png" alt="Barangay Logo" style="height: 72px; width: auto;">
            </div>
        </header>

        <main class="auth-main" style="display:flex;align-items:center;justify-content:center;flex:1;">
            <div class="otp-container">
                <div class="otp-header">
                    <div class="icon-circle">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <h1>Check Your Email</h1>
                    <p>We've sent a 6-digit verification code to<br>
                    <span class="email-highlight"><?= htmlspecialchars($masked_email) ?></span></p>
                </div>

                <?php if (isset($_SESSION['otp_error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($_SESSION['otp_error']) ?>
                    </div>
                    <?php unset($_SESSION['otp_error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['otp_success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($_SESSION['otp_success']) ?>
                    </div>
                    <?php unset($_SESSION['otp_success']); ?>
                <?php endif; ?>

                <?php
                $app_env = strtolower((string) env('APP_ENV', 'production'));
                if ($app_env !== 'production' && isset($_SESSION['otp_dev_code'])):
                ?>
                    <div class="alert alert-success" style="margin-top:-.5rem;background:rgba(99,102,241,.18);border-color:rgba(129,140,248,.45);color:#c7d2fe;">
                        <i class="fas fa-flask"></i>
                        <span>Development OTP: <strong><?= htmlspecialchars((string) $_SESSION['otp_dev_code']) ?></strong></span>
                    </div>
                <?php endif; ?>

                <form action="<?= htmlspecialchars(app_url('/includes/verify-otp-handler.php')) ?>" method="POST" id="otpForm">
                    <?php echo csrf_field(); ?>
                    <div class="otp-inputs" id="otpInputs">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                    </div>
                    <input type="hidden" name="otp_code" id="otpHidden">
                    <button type="submit" class="verify-btn" id="verifyBtn" disabled>
                        <i class="fas fa-shield-alt"></i>&nbsp; Verify & Create Account
                    </button>
                </form>

                <div class="resend-section">
                    <p>Didn't receive the code?</p>
                    <form action="<?= htmlspecialchars(app_url('/includes/resend-otp-handler.php')) ?>" method="POST" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="resend-btn" id="resendBtn" disabled>
                            Resend Code <span id="timerText" class="timer"></span>
                        </button>
                    </form>
                </div>

                <a href="register.php" class="back-link"><i class="fas fa-arrow-left"></i>&nbsp; Back to Registration</a>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('#otpInputs input');
        const hidden = document.getElementById('otpHidden');
        const verifyBtn = document.getElementById('verifyBtn');
        const form = document.getElementById('otpForm');

        function updateHidden() {
            let code = '';
            inputs.forEach(i => code += i.value);
            hidden.value = code;
            verifyBtn.disabled = code.length < 6;
        }

        inputs.forEach((input, idx) => {
            input.addEventListener('input', function(e) {
                // Only allow digits
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
                this.classList.toggle('filled', this.value !== '');
                updateHidden();
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && idx > 0) {
                    inputs[idx - 1].focus();
                    inputs[idx - 1].value = '';
                    inputs[idx - 1].classList.remove('filled');
                    updateHidden();
                }
            });

            // Handle paste
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                paste.split('').forEach((char, i) => {
                    if (inputs[i]) {
                        inputs[i].value = char;
                        inputs[i].classList.add('filled');
                    }
                });
                if (inputs[Math.min(paste.length, inputs.length - 1)]) {
                    inputs[Math.min(paste.length, inputs.length - 1)].focus();
                }
                updateHidden();
            });
        });

        // Auto-focus first input
        inputs[0].focus();

        // Prevent submit if OTP incomplete
        form.addEventListener('submit', function(e) {
            if (hidden.value.length < 6) {
                e.preventDefault();
            }
        });

        // Resend cooldown timer (60 seconds)
        const resendBtn = document.getElementById('resendBtn');
        const timerText = document.getElementById('timerText');
        let cooldown = 60;
        
        function updateTimer() {
            if (cooldown > 0) {
                timerText.textContent = '(' + cooldown + 's)';
                cooldown--;
                setTimeout(updateTimer, 1000);
            } else {
                timerText.textContent = '';
                resendBtn.disabled = false;
            }
        }
        updateTimer();
    });
    </script>
</body>
</html>

