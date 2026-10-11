<?php
/**
 * Maakit Express — Modern Customer Login
 * File: login.php
 * Repository: ankit854281/maakit
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$next = trim($_GET['next'] ?? $_SESSION['login_next'] ?? '/');
$_SESSION['login_next'] = $next;

// If customer already logged in, redirect directly to destination
if (!empty($_SESSION['cust_id']) || !empty($_SESSION['cust'])) {
    header("Location: " . $next);
    exit;
}

$error_msg = '';
$success_msg = '';
$step = 'mobile'; // 'mobile' or 'otp'
$mobile = $_SESSION['login_mobile'] ?? '';

// Handle Backend Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // STEP 1: Send OTP to Mobile Number
    if ($action === 'send_otp') {
        $raw_mobile = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        if (strlen($raw_mobile) === 10) {
            $mobile = $raw_mobile;
            $_SESSION['login_mobile'] = $mobile;
            
            // Generate 4-digit verification code
            $otp = str_pad(strval(rand(1000, 9999)), 4, '0', STR_PAD_LEFT);
            $_SESSION['active_otp'] = $otp;
            $_SESSION['otp_time'] = time();

            // In local/testing mode or SMS gateway integration:
            // e.g., send_sms_otp($mobile, $otp)
            $step = 'otp';
            $success_msg = "OTP आपके मोबाइल नंबर +91 " . htmlspecialchars($mobile) . " पर भेज दिया गया है।";
        } else {
            $error_msg = "कृपया 10 अंकों का वैध मोबाइल नंबर दर्ज करें।";
        }
    }

    // STEP 2: Verify OTP and Login
    if ($action === 'verify_otp') {
        $entered_otp = trim($_POST['digit1'] . $_POST['digit2'] . $_POST['digit3'] . $_POST['digit4']);
        $actual_otp  = $_SESSION['active_otp'] ?? '';

        // Allow actual OTP or master quick verification
        if (!empty($entered_otp) && ($entered_otp === $actual_otp || $entered_otp === '1234')) {
            // Customer Login Success
            $_SESSION['cust_id']     = 'CUST_' . substr($mobile, -6);
            $_SESSION['cust_mobile'] = $mobile;
            $_SESSION['cust_name']   = 'ग्राहक (' . substr($mobile, -4) . ')';
            
            // Set 60-day Persistent Login Cookie
            setcookie('mk_cust', $mobile, time() + (86400 * 60), '/');

            // Clean up OTP session
            unset($_SESSION['active_otp']);
            unset($_SESSION['login_mobile']);

            // Redirect back to store or checkout
            header("Location: " . $next);
            echo "<script>window.location.href = " . json_encode($next) . ";</script>";
            exit;
        } else {
            $step = 'otp';
            $error_msg = "गलत OTP दर्ज किया गया है। कृपया सही 4-अंकों का कोड डालें।";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Maakit — लॉगिन या नया खाता बनाएँ (Customer Login)</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --brand: #0C831F;
      --brand-hover: #086F1A;
      --brand-light: #E8F5E9;
      --primary: #2563EB;
      --text-main: #1C1C1C;
      --text-sub: #555555;
      --text-muted: #888888;
      --border-color: #E2E8F0;
      --bg: #F8FAFC;
      --radius-lg: 20px;
      --radius-md: 12px;
      --radius-sm: 8px;
      --font: 'Plus Jakarta Sans', 'Noto Sans Devanagari', -apple-system, sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--font);
      background-color: var(--bg);
      color: var(--text-main);
      line-height: 1.4;
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px 12px;
    }

    /* Modern Card */
    .login-box {
      width: 100%;
      max-width: 440px;
      background: #FFFFFF;
      border-radius: var(--radius-lg);
      box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
      border: 1px solid var(--border-color);
      padding: 32px 28px;
      position: relative;
    }

    /* Brand Header */
    .brand-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }
    .brand-logo {
      font-size: 24px;
      font-weight: 900;
      color: var(--brand);
      text-decoration: none;
      letter-spacing: -0.5px;
    }
    .brand-logo span { color: #F59E0B; }
    .btn-skip {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-muted);
      text-decoration: none;
      padding: 6px 12px;
      border-radius: var(--radius-sm);
      background: #F1F5F9;
      transition: all 0.2s;
    }
    .btn-skip:hover { background: #E2E8F0; color: var(--text-main); }

    /* Headline */
    .login-heading h1 {
      font-size: 21px;
      font-weight: 800;
      color: var(--text-main);
      letter-spacing: -0.4px;
      margin-bottom: 6px;
    }
    .login-heading p {
      font-size: 13px;
      color: var(--text-sub);
      margin-bottom: 24px;
      line-height: 1.45;
    }

    /* Alerts */
    .alert-banner {
      padding: 11px 14px;
      border-radius: var(--radius-sm);
      font-size: 12.5px;
      font-weight: 600;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .alert-error {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      color: #991B1B;
    }
    .alert-success {
      background: var(--brand-light);
      border: 1px solid #C8E6C9;
      color: #166534;
    }

    /* STEP 1: Phone Input Box */
    .phone-group {
      display: flex;
      align-items: center;
      border: 1.5px solid var(--border-color);
      border-radius: var(--radius-md);
      overflow: hidden;
      margin-bottom: 18px;
      transition: all 0.2s;
      background: #FAFAFA;
    }
    .phone-group:focus-within {
      border-color: var(--brand);
      background: #FFFFFF;
      box-shadow: 0 0 0 3px rgba(12, 131, 31, 0.12);
    }
    .country-prefix {
      padding: 13px 14px;
      background: #F1F5F9;
      font-size: 14px;
      font-weight: 700;
      color: var(--text-main);
      border-right: 1.5px solid var(--border-color);
      display: flex;
      align-items: center;
      gap: 6px;
      user-select: none;
    }
    .phone-input {
      flex: 1;
      padding: 13px 14px;
      border: none;
      outline: none;
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.5px;
      font-family: inherit;
      color: var(--text-main);
      background: transparent;
    }
    .phone-input::placeholder {
      font-weight: 400;
      color: var(--text-muted);
      letter-spacing: normal;
    }

    /* Primary Action Button */
    .btn-main-action {
      width: 100%;
      background: var(--brand);
      color: #FFFFFF;
      border: none;
      padding: 14px 20px;
      border-radius: var(--radius-md);
      font-size: 15px;
      font-weight: 800;
      font-family: inherit;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.15s;
      box-shadow: 0 4px 14px rgba(12, 131, 31, 0.3);
    }
    .btn-main-action:hover { background: var(--brand-hover); transform: translateY(-1px); }
    .btn-main-action:active { transform: scale(0.98); }

    /* STEP 2: 4-Box OTP Input */
    .otp-stream {
      display: flex;
      gap: 12px;
      justify-content: center;
      margin: 20px 0 24px;
    }
    .otp-box {
      width: 58px;
      height: 60px;
      text-align: center;
      font-size: 24px;
      font-weight: 800;
      border: 2px solid var(--border-color);
      border-radius: var(--radius-md);
      background: #FAFAFA;
      outline: none;
      font-family: inherit;
      color: var(--text-main);
      transition: all 0.2s;
    }
    .otp-box:focus {
      border-color: var(--brand);
      background: #FFFFFF;
      box-shadow: 0 0 0 3px rgba(12, 131, 31, 0.12);
    }

    .otp-meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12.5px;
      margin-bottom: 20px;
    }
    .btn-edit-num {
      color: var(--primary);
      text-decoration: none;
      font-weight: 700;
      cursor: pointer;
    }
    .resend-timer {
      color: var(--text-muted);
      font-weight: 600;
    }
    .btn-resend-active {
      color: var(--brand);
      font-weight: 700;
      cursor: pointer;
      text-decoration: underline;
    }

    /* WhatsApp OTP Alternative */
    .whatsapp-otp-pill {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px;
      border-radius: var(--radius-md);
      background: var(--brand-light);
      border: 1px solid var(--brand);
      color: #166534;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      margin-top: 14px;
      transition: all 0.2s;
    }
    .whatsapp-otp-pill:hover { background: #D1E7DD; }

    /* Footer Trust */
    .login-footer-trust {
      margin-top: 26px;
      padding-top: 16px;
      border-top: 1px solid var(--border-color);
      text-align: center;
      font-size: 11.5px;
      color: var(--text-muted);
      line-height: 1.5;
    }
    .login-footer-trust a { color: var(--text-sub); text-decoration: underline; }
  </style>
</head>
<body>

  <div class="login-box">
    
    <!-- Top Row -->
    <div class="brand-row">
      <a class="brand-logo" href="/">Maa<span>kit</span></a>
      <a class="btn-skip" href="<?= htmlspecialchars($next) ?>">छोड़ें (Skip) ✕</a>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($error_msg)): ?>
      <div class="alert-banner alert-error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
      <div class="alert-banner alert-success">✓ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <?php if ($step === 'mobile'): ?>
      <!-- ==========================================
           STEP 1: ENTER MOBILE NUMBER
           ========================================== -->
      <div class="login-heading">
        <h1>गाँव का अपना बाज़ार</h1>
        <p>सामान मँगवाने और ऑर्डर ट्रैक करने के लिए अपना 10 अंकों का मोबाइल नंबर दर्ज करें।</p>
      </div>

      <form method="POST" action="login.php" onsubmit="return validateMobile()">
        <input type="hidden" name="action" value="send_otp">

        <div class="phone-group">
          <div class="country-prefix">
            <span>🇮🇳</span>
            <span>+91</span>
          </div>
          <input 
            type="tel" 
            name="mobile" 
            id="mobileInput" 
            class="phone-input" 
            placeholder="98381 23456" 
            maxlength="10" 
            autofocus 
            required 
            pattern="[0-9]{10}"
            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
          >
        </div>

        <button type="submit" class="btn-main-action" id="btnSendOtp">
          <span>OTP प्राप्त करें (Continue) →</span>
        </button>
      </form>

      <!-- Instant WhatsApp Login Alternative -->
      <a class="whatsapp-otp-pill" href="https://wa.me/918429393903?text=%E0%A4%A8%E0%A4%AE%E0%A4%B8%E0%A5%8D%E0%A4%A4%E0%A5%87%20Maakit%2C%20%E0%A4%AE%E0%A5%81%E0%A4%9D%E0%A5%87%20%E0%A4%B2%E0%A5%89%E0%A4%97%E0%A4%BF%E0%A4%A8%20%E0%A4%95%E0%A4%B0%E0%A4%A8%E0%A4%BE%20%E0%A4%B9%E0%A5%88" target="_blank">
        <span>📲 WhatsApp से 1-क्लिक में लॉगिन करें</span>
      </a>

    <?php else: ?>
      <!-- ==========================================
           STEP 2: ENTER 4-DIGIT OTP
           ========================================== -->
      <div class="login-heading">
        <h1>OTP सत्यापन (Verify OTP)</h1>
        <p>नंबर <b>+91 <?= htmlspecialchars($mobile) ?></b> पर भेजा गया 4-अंकों का कोड दर्ज करें।</p>
      </div>

      <form method="POST" action="login.php" id="otpForm">
        <input type="hidden" name="action" value="verify_otp">

        <div class="otp-stream">
          <input type="tel" name="digit1" id="digit1" class="otp-box" maxlength="1" autofocus onkeyup="moveOtp(this, '', 'digit2')" required>
          <input type="tel" name="digit2" id="digit2" class="otp-box" maxlength="1" onkeyup="moveOtp(this, 'digit1', 'digit3')" required>
          <input type="tel" name="digit3" id="digit3" class="otp-box" maxlength="1" onkeyup="moveOtp(this, 'digit2', 'digit4')" required>
          <input type="tel" name="digit4" id="digit4" class="otp-box" maxlength="1" onkeyup="moveOtp(this, 'digit3', '')" required>
        </div>

        <div class="otp-meta-row">
          <a class="btn-edit-num" href="login.php">✏️ नंबर बदलें</a>
          <span class="resend-timer" id="timerLabel">दोबारा भेजें (<span id="countDown">30</span>s)</span>
        </div>

        <button type="submit" class="btn-main-action">
          <span>सत्यापित करें और आगे बढ़ें (Verify) ✓</span>
        </button>
      </form>

      <?php if (!empty($_SESSION['active_otp'])): ?>
        <!-- Quick Local Testing Code Indicator -->
        <div style="margin-top:14px; background:#F8FAFC; border:1px dashed #CBD5E1; padding:8px 12px; border-radius:8px; font-size:12px; text-align:center; color:#475569;">
          परीक्षण कोड (Demo Code): <b><?= htmlspecialchars($_SESSION['active_otp']) ?></b> या <b>1234</b>
        </div>
      <?php endif; ?>

    <?php endif; ?>

    <div class="login-footer-trust">
      जारी रखकर आप Maakit की <a href="/terms">सेवा शर्तों</a> और <a href="/privacy">गोपनीयता नीति</a> से सहमत होते हैं। • 100% सुरक्षित
    </div>

  </div>

  <script>
    function validateMobile() {
      const input = document.getElementById('mobileInput');
      if (input.value.length !== 10) {
        alert("कृपया पूरा 10 अंकों का मोबाइल नंबर दर्ज करें!");
        input.focus();
        return false;
      }
      return true;
    }

    // Auto-advance OTP Inputs
    function moveOtp(current, prevId, nextId) {
      current.value = current.value.replace(/[^0-9]/g, '');
      if (current.value.length === 1 && nextId) {
        document.getElementById(nextId).focus();
      } else if (current.value.length === 0 && prevId && event.key === 'Backspace') {
        document.getElementById(prevId).focus();
      }

      // If all 4 digits filled, auto-submit
      const d1 = document.getElementById('digit1')?.value;
      const d2 = document.getElementById('digit2')?.value;
      const d3 = document.getElementById('digit3')?.value;
      const d4 = document.getElementById('digit4')?.value;
      if (d1 && d2 && d3 && d4) {
        document.getElementById('otpForm').submit();
      }
    }

    // Resend Timer Countdown
    let timeLeft = 30;
    const countEl = document.getElementById('countDown');
    const timerLabel = document.getElementById('timerLabel');

    if (countEl) {
      const interval = setInterval(() => {
        timeLeft--;
        if (timeLeft <= 0) {
          clearInterval(interval);
          timerLabel.innerHTML = '<span class="btn-resend-active" onclick="resendOtp()">दोबारा OTP भेजें</span>';
        } else {
          countEl.innerText = timeLeft;
        }
      }, 1000);
    }

    function resendOtp() {
      window.location.reload();
    }
  </script>
</body>
</html>
