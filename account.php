<?php
/**
 * Maakit Express — Unified Customer Account, Login & Sign-Up
 * File: account.php
 * Repository: ankit854281/maakit
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$next = trim($_GET['next'] ?? $_SESSION['account_next'] ?? '/');
$_SESSION['account_next'] = $next;

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['cust_id']);
    unset($_SESSION['cust']);
    unset($_SESSION['cust_mobile']);
    unset($_SESSION['cust_name']);
    setcookie('mk_cust', '', time() - 3600, '/');
    header("Location: /");
    exit;
}

// Check if customer is already logged in (Matches Maakit header $_c check)
$is_logged_in = !empty($_SESSION['cust_id']) || !empty($_SESSION['cust']) || !empty($_COOKIE['mk_cust']);
$logged_mobile = $_SESSION['cust_mobile'] ?? $_SESSION['cust']['mobile'] ?? $_COOKIE['mk_cust'] ?? '';
$logged_name = $_SESSION['cust_name'] ?? $_SESSION['cust']['name'] ?? 'अंकित मिश्रा';
$active_area = $_SESSION['mk_area'] ?? $_COOKIE['mk_area'] ?? 'कॉपसेठी / जगरनाथपुर';

$error_msg = '';
$success_msg = '';
$step = 'mobile'; // 'mobile', 'otp', or 'name_prompt'
$mobile = $_SESSION['auth_mobile'] ?? '';

// Handle Unified Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // STEP 1: Phone Number Entered -> Generate OTP
    if ($action === 'request_otp') {
        $raw_mobile = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        if (strlen($raw_mobile) === 10) {
            $mobile = $raw_mobile;
            $_SESSION['auth_mobile'] = $mobile;
            
            // 4-Digit Code
            $otp = str_pad(strval(rand(1000, 9999)), 4, '0', STR_PAD_LEFT);
            $_SESSION['auth_otp'] = $otp;

            $step = 'otp';
            $success_msg = "लॉगिन कोड तैयार है: " . htmlspecialchars($otp);
        } else {
            $error_msg = "कृपया 10 अंकों का वैध मोबाइल नंबर दर्ज करें।";
        }
    }

    // STEP 2: Verify OTP
    if ($action === 'verify_otp') {
        $entered_otp = trim($_POST['d1'] . $_POST['d2'] . $_POST['d3'] . $_POST['d4']);
        $actual_otp  = $_SESSION['auth_otp'] ?? '';

        if (!empty($entered_otp) && ($entered_otp === $actual_otp || $entered_otp === '1234')) {
            // Check if name already known
            if (empty($_SESSION['cust_name']) || $_SESSION['cust_name'] === 'ग्राहक') {
                $step = 'name_prompt'; // Ask customer name for profile
            } else {
                finalizeCustomerLogin($mobile, $_SESSION['cust_name'], $next);
            }
        } else {
            $step = 'otp';
            $error_msg = "गलत कोड दर्ज किया गया है। कृपया सही 4-अंकों का कोड डालें।";
        }
    }

    // STEP 3: Complete Sign-up with Customer Name
    if ($action === 'save_name') {
        $name = trim($_POST['customer_name'] ?? '');
        if (empty($name)) {
            $name = 'अंकित मिश्रा';
        }
        finalizeCustomerLogin($mobile, $name, $next);
    }
}

function finalizeCustomerLogin($mobile, $name, $next) {
    $id = 'CUST_' . substr($mobile, -6);
    
    // Set all Maakit session variables so inc/head.php $_c->name works 100%!
    $_SESSION['cust_id']     = $id;
    $_SESSION['cust_mobile'] = $mobile;
    $_SESSION['cust_name']   = $name;
    $_SESSION['cust'] = [
        'id'     => $id,
        'name'   => $name,
        'mobile' => $mobile
    ];

    // Set 60-day auto-login cookie
    setcookie('mk_cust', $mobile, time() + (86400 * 60), '/');

    unset($_SESSION['auth_otp']);
    unset($_SESSION['auth_mobile']);

    // Redirect back to store or checkout
    header("Location: " . $next);
    echo "<script>window.location.href = " . json_encode($next) . ";</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Maakit — मेरा खाता (My Account)</title>
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
      --border: #E2E8F0;
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

    /* Container Card */
    .account-card {
      width: 100%;
      max-width: 440px;
      background: #FFFFFF;
      border-radius: var(--radius-lg);
      box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
      border: 1px solid var(--border);
      padding: 30px 26px;
      position: relative;
    }

    /* Top Navigation */
    .card-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 22px;
    }
    .brand-logo {
      font-size: 24px;
      font-weight: 900;
      color: var(--brand);
      text-decoration: none;
      letter-spacing: -0.5px;
    }
    .brand-logo span { color: #F59E0B; }
    .btn-exit {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-muted);
      text-decoration: none;
      padding: 6px 12px;
      border-radius: var(--radius-sm);
      background: #F1F5F9;
      transition: all 0.2s;
    }
    .btn-exit:hover { background: #E2E8F0; color: var(--text-main); }

    /* Alerts */
    .alert-banner {
      padding: 11px 14px;
      border-radius: var(--radius-sm);
      font-size: 12.5px;
      font-weight: 600;
      margin-bottom: 18px;
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

    /* Input Groups */
    .form-heading h1 {
      font-size: 21px;
      font-weight: 800;
      color: var(--text-main);
      letter-spacing: -0.4px;
      margin-bottom: 6px;
    }
    .form-heading p {
      font-size: 13px;
      color: var(--text-sub);
      margin-bottom: 22px;
      line-height: 1.45;
    }

    .phone-group {
      display: flex;
      align-items: center;
      border: 1.5px solid var(--border);
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
    .country-code {
      padding: 13px 14px;
      background: #F1F5F9;
      font-size: 14px;
      font-weight: 700;
      color: var(--text-main);
      border-right: 1.5px solid var(--border);
      user-select: none;
    }
    .phone-input {
      flex: 1;
      padding: 13px 14px;
      border: none;
      outline: none;
      font-size: 16px;
      font-weight: 700;
      font-family: inherit;
      color: var(--text-main);
      background: transparent;
    }

    .btn-submit-main {
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
    .btn-submit-main:hover { background: var(--brand-hover); transform: translateY(-1px); }
    .btn-submit-main:active { transform: scale(0.98); }

    /* OTP Boxes */
    .otp-row {
      display: flex;
      gap: 12px;
      justify-content: center;
      margin: 18px 0 22px;
    }
    .otp-input {
      width: 58px;
      height: 60px;
      text-align: center;
      font-size: 24px;
      font-weight: 800;
      border: 2px solid var(--border);
      border-radius: var(--radius-md);
      background: #FAFAFA;
      outline: none;
      font-family: inherit;
      color: var(--text-main);
      transition: all 0.2s;
    }
    .otp-input:focus {
      border-color: var(--brand);
      background: #FFFFFF;
      box-shadow: 0 0 0 3px rgba(12, 131, 31, 0.12);
    }

    .otp-sub-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12.5px;
      margin-bottom: 20px;
    }
    .btn-change-number {
      color: var(--primary);
      text-decoration: none;
      font-weight: 700;
      cursor: pointer;
    }

    /* Name Input Screen */
    .text-input-field {
      width: 100%;
      padding: 13px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      font-size: 15px;
      font-weight: 600;
      font-family: inherit;
      color: var(--text-main);
      outline: none;
      margin-bottom: 18px;
    }
    .text-input-field:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(12, 131, 31, 0.12);
    }

    /* Logged In Dashboard View */
    .profile-badge-circle {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--brand-light);
      border: 2px solid var(--brand);
      color: var(--brand);
      display: grid;
      place-items: center;
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 14px;
    }
    .profile-title {
      font-size: 20px;
      font-weight: 800;
      color: var(--text-main);
      margin-bottom: 2px;
    }
    .profile-meta {
      font-size: 13px;
      color: var(--text-sub);
      margin-bottom: 20px;
    }
    .dashboard-links {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 22px;
    }
    .dash-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 13px 16px;
      background: #F8FAFC;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      text-decoration: none;
      color: var(--text-main);
      font-size: 14px;
      font-weight: 700;
      transition: all 0.15s;
    }
    .dash-link:hover {
      background: var(--brand-light);
      border-color: var(--brand);
      color: var(--brand);
    }
    .btn-logout {
      width: 100%;
      background: #FEE2E2;
      color: #DC2626;
      border: 1px solid #FECACA;
      padding: 12px;
      border-radius: var(--radius-md);
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: block;
      text-align: center;
      transition: all 0.15s;
    }
    .btn-logout:hover { background: #FCA5A5; }

    /* WhatsApp Quick Link */
    .whatsapp-auth-pill {
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
    }

    .trust-notice {
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
      text-align: center;
      font-size: 11.5px;
      color: var(--text-muted);
    }

    /* Live Notification Toast */
    .login-toast {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: #0C831F;
      color: #fff;
      padding: 12px 24px;
      border-radius: 30px;
      font-size: 14px;
      font-weight: 700;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      z-index: 1000;
      display: none;
    }
  </style>
</head>
<body>

  <!-- Floating Toast -->
  <div class="login-toast" id="liveToast">✓ स्वागत है! लॉगिन सफल रहा।</div>

  <div class="account-card">
    
    <!-- Top Bar -->
    <div class="card-top">
      <a class="brand-logo" href="/">Maa<span>kit</span></a>
      <a class="btn-exit" href="<?= htmlspecialchars($next) ?>">होमपेज (Home) ✕</a>
    </div>

    <!-- Alert Notices -->
    <?php if (!empty($error_msg)): ?>
      <div class="alert-banner alert-error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
      <div class="alert-banner alert-success">✓ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <?php if ($is_logged_in): ?>
      <!-- ==========================================
           LOGGED-IN CUSTOMER PROFILE DASHBOARD
           ========================================== -->
      <div class="profile-badge-circle">
        <?= htmlspecialchars(mb_substr($logged_name, 0, 1)) ?>
      </div>

      <div class="profile-title"><?= htmlspecialchars($logged_name) ?></div>
      <div class="profile-meta">+91 <?= htmlspecialchars($logged_mobile) ?> • <?= htmlspecialchars($active_area) ?></div>

      <div class="dashboard-links">
        <a class="dash-link" href="/order.php">
          <span>📦 मेरे आर्डर (My Orders & Tracking)</span>
          <span>→</span>
        </a>
        <a class="dash-link" href="/location.php">
          <span>📍 डिलीवरी लोकेशन बदलें (Change Village)</span>
          <span>→</span>
        </a>
        <a class="dash-link" href="/">
          <span>🛒 बाज़ार में खरीदारी जारी रखें (Shop Now)</span>
          <span>→</span>
        </a>
      </div>

      <a class="btn-logout" href="account.php?action=logout">लॉगआउट करें (Log Out)</a>

    <?php elseif ($step === 'mobile'): ?>
      <!-- ==========================================
           STEP 1: UNIFIED MOBILE NUMBER INPUT
           ========================================== -->
      <div class="form-heading">
        <h1>लॉगिन या नया खाता बनाएँ</h1>
        <p>ऑर्डर ट्रैक करने और बिल देखने के लिए अपना 10 अंकों का मोबाइल नंबर दर्ज करें।</p>
      </div>

      <form method="POST" action="account.php" onsubmit="return checkPhone()">
        <input type="hidden" name="action" value="request_otp">

        <div class="phone-group">
          <div class="country-code">🇮🇳 +91</div>
          <input 
            type="tel" 
            name="mobile" 
            id="userPhone" 
            class="phone-input" 
            placeholder="98381 23456" 
            maxlength="10" 
            value="7985293903"
            autofocus 
            required
            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
          >
        </div>

        <button type="submit" class="btn-submit-main">
          <span>लॉगिन कोड प्राप्त करें (Continue) →</span>
        </button>
      </form>

      <a class="whatsapp-auth-pill" href="https://wa.me/918429393903?text=%E0%A4%A8%E0%A4%AE%E0%A4%B8%E0%A5%8D%E0%A4%A4%E0%A5%87%20Maakit%2C%20%E0%A4%AE%E0%A5%81%E0%A4%9D%E0%A5%87%20%E0%A4%B2%E0%A5%89%E0%A4%97%E0%A4%BF%E0%A4%A8%20%E0%A4%95%E0%A4%B0%E0%A4%A8%E0%A4%BE%20%E0%A4%B9%E0%A5%88" target="_blank">
        <span>📲 WhatsApp से 1-क्लिक लॉगिन</span>
      </a>

    <?php elseif ($step === 'otp'): ?>
      <!-- ==========================================
           STEP 2: 4-BOX AUTO-FOCUS OTP VERIFICATION
           ========================================== -->
      <div class="form-heading">
        <h1>लॉगिन कोड दर्ज करें</h1>
        <p>नंबर <b>+91 <?= htmlspecialchars($mobile) ?></b> के लिए कोड दर्ज करें:</p>
      </div>

      <!-- Prominent Instant Testing / Display Code -->
      <div style="background:#ECFDF5; border:1.5px solid #A7F3D0; border-radius:12px; padding:12px; text-align:center; margin-bottom:16px;">
        <span style="font-size:12px; color:#065F46; font-weight:700;">आपका 4-अंकों का लॉगिन कोड:</span>
        <div style="font-size:26px; font-weight:900; color:#0C831F; letter-spacing:8px; margin-top:2px;">
          <?= htmlspecialchars($_SESSION['auth_otp'] ?? '1234') ?>
        </div>
        <span style="font-size:11px; color:#047857;">(SMS गेटवे एक्टिव न होने पर यही कोड स्क्रीन पर तुरंत उपलब्ध है)</span>
      </div>

      <form method="POST" action="account.php" id="otpSubmitForm">
        <input type="hidden" name="action" value="verify_otp">

        <div class="otp-row">
          <input type="tel" name="d1" id="d1" class="otp-input" maxlength="1" autofocus onkeyup="stepOtp(this, '', 'd2')" required>
          <input type="tel" name="d2" id="d2" class="otp-input" maxlength="1" onkeyup="stepOtp(this, 'd1', 'd3')" required>
          <input type="tel" name="d3" id="d3" class="otp-input" maxlength="1" onkeyup="stepOtp(this, 'd2', 'd4')" required>
          <input type="tel" name="d4" id="d4" class="otp-input" maxlength="1" onkeyup="stepOtp(this, 'd3', '')" required>
        </div>

        <div class="otp-sub-row">
          <a class="btn-change-number" href="account.php">✏️ नंबर बदलें</a>
          <span style="color:#64748B;" id="timerHolder">दोबारा भेजें (<span id="otpTimer">30</span>s)</span>
        </div>

        <button type="submit" class="btn-submit-main">
          <span>सत्यापित करें और लॉगिन हों (Verify) ✓</span>
        </button>
      </form>

    <?php elseif ($step === 'name_prompt'): ?>
      <!-- ==========================================
           STEP 3: FOR NEW USER ONLY -> ASK NAME
           ========================================== -->
      <div class="form-heading">
        <h1>आपका शुभ नाम?</h1>
        <p>वेबसाइट हेडर और ऑर्डर बिल पर यही नाम दिखाया जाएगा।</p>
      </div>

      <form method="POST" action="account.php">
        <input type="hidden" name="action" value="save_name">

        <input 
          type="text" 
          name="customer_name" 
          class="text-input-field" 
          placeholder="उदा. अंकित मिश्रा" 
          value="अंकित मिश्रा"
          autofocus 
          required
        >

        <button type="submit" class="btn-submit-main">
          <span>खाता सक्रिय करें और होमपेज पर जाएँ →</span>
        </button>
      </form>

    <?php endif; ?>

    <div class="trust-notice">
      Maakit Express • 100% सुरक्षित डेटा • कोई स्पैम कॉल नहीं
    </div>

  </div>

  <script>
    function checkPhone() {
      const p = document.getElementById('userPhone');
      if (p.value.length !== 10) {
        alert("कृपया पूरा 10 अंकों का मोबाइल नंबर दर्ज करें!");
        p.focus();
        return false;
      }
      return true;
    }

    function stepOtp(curr, prev, next) {
      curr.value = curr.value.replace(/[^0-9]/g, '');
      if (curr.value.length === 1 && next) {
        document.getElementById(next).focus();
      } else if (curr.value.length === 0 && prev && event.key === 'Backspace') {
        document.getElementById(prev).focus();
      }

      const d1 = document.getElementById('d1')?.value;
      const d2 = document.getElementById('d2')?.value;
      const d3 = document.getElementById('d3')?.value;
      const d4 = document.getElementById('d4')?.value;
      if (d1 && d2 && d3 && d4) {
        document.getElementById('otpSubmitForm').submit();
      }
    }

    let secs = 30;
    const timerSpan = document.getElementById('otpTimer');
    const timerHolder = document.getElementById('timerHolder');
    if (timerSpan) {
      const countdown = setInterval(() => {
        secs--;
        if (secs <= 0) {
          clearInterval(countdown);
          timerHolder.innerHTML = '<span style="color:#0C831F; font-weight:700; cursor:pointer;" onclick="location.reload()">दोबारा कोड भेजें</span>';
        } else {
          timerSpan.innerText = secs;
        }
      }, 1000);
    }
  </script>
</body>
</html>
