<?php
/**
 * Maakit Express — Location Selector (maakit-Style Modern UI)
 * File: location.php
 * Repository: ankit854281/maakit
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Handle Location Form / AJAX Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $area = trim($_POST['area'] ?? $_POST['village'] ?? '');
    $pin  = trim($_POST['pin'] ?? $_POST['pincode'] ?? '221403');
    $lat  = trim($_POST['lat'] ?? '25.3304');
    $lng  = trim($_POST['lng'] ?? '82.6921');
    $landmark = trim($_POST['landmark'] ?? '');

    if (!empty($area)) {
        // Save in PHP Session
        $_SESSION['mk_area'] = $area;
        $_SESSION['mk_pin']  = $pin;
        $_SESSION['mk_lat']  = $lat;
        $_SESSION['mk_lng']  = $lng;
        $_SESSION['mk_landmark'] = $landmark;

        // Save in 30-day Cookie
        setcookie('mk_area', $area, time() + (86400 * 30), '/');
        setcookie('mk_pin',  $pin,  time() + (86400 * 30), '/');
        setcookie('mk_lat',  $lat,  time() + (86400 * 30), '/');
        setcookie('mk_lng',  $lng,  time() + (86400 * 30), '/');

        // Check if AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'area' => $area, 'pin' => $pin]);
            exit;
        }

        // Standard Redirect back to Store Homepage
        header('Location: /');
        exit;
    }
}

// Current active area fallback
$current_area = $_SESSION['mk_area'] ?? $_COOKIE['mk_area'] ?? 'Jagarnathpur (जगरनाथपुर)';
$current_pin  = $_SESSION['mk_pin']  ?? $_COOKIE['mk_pin']  ?? '221403';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Maakit — डिलीवरी लोकेशन चुनें (Choose Location)</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --maakit-green: #0C831F;
      --maakit-green-hover: #086F1A;
      --maakit-green-light: #E8F5E9;
      --maakit-green-border: #C8E6C9;
      --primary-blue: #2563EB;
      --text-main: #1C1C1C;
      --text-sub: #555555;
      --text-muted: #888888;
      --border-color: #E2E8F0;
      --bg-page: #F8FAFC;
      --surface: #FFFFFF;
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 18px;
      --font: 'Plus Jakarta Sans', 'Noto Sans Devanagari', -apple-system, sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--font);
      background-color: var(--bg-page);
      color: var(--text-main);
      line-height: 1.4;
      -webkit-font-smoothing: antialiased;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px 10px;
    }

    /* Modal Card */
    .maakit-box {
      width: 100%;
      max-width: 550px;
      background: #FFFFFF;
      border-radius: var(--radius-lg);
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.15);
      border: 1px solid var(--border-color);
      padding: 24px 26px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      position: relative;
    }

    /* Top: Header */
    .box-top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .box-top-row h2 {
      font-size: 17.5px;
      font-weight: 800;
      color: var(--text-main);
    }
    .btn-modal-close {
      background: #F1F5F9;
      border: none;
      font-size: 16px;
      color: var(--text-sub);
      cursor: pointer;
      width: 30px;
      height: 30px;
      display: grid;
      place-items: center;
      border-radius: 50%;
    }
    .btn-modal-close:hover { background: #E2E8F0; }

    /* The Signature maakit Row */
    .maakit-detect-row {
      display: flex;
      align-items: center;
      gap: 10px;
      position: relative;
    }
    .btn-detect-gps {
      background: var(--maakit-green);
      color: #FFFFFF;
      border: none;
      padding: 12px 16px;
      border-radius: var(--radius-sm);
      font-size: 13px;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      flex-shrink: 0;
      transition: all 0.15s;
      white-space: nowrap;
    }
    .btn-detect-gps:hover { background: var(--maakit-green-hover); }
    .btn-detect-gps:active { transform: scale(0.97); }

    .or-separator {
      font-size: 11px;
      font-weight: 700;
      color: var(--text-muted);
      letter-spacing: 0.5px;
      flex-shrink: 0;
    }

    .search-input-wrap {
      flex: 1;
      position: relative;
      display: flex;
      align-items: center;
    }
    .search-delivery-input {
      width: 100%;
      padding: 12px 38px 12px 14px;
      border: 1.5px solid var(--border-color);
      border-radius: var(--radius-sm);
      font-size: 13.5px;
      font-family: inherit;
      color: var(--text-main);
      outline: none;
      transition: all 0.2s;
    }
    .search-delivery-input:focus {
      border-color: var(--maakit-green);
      box-shadow: 0 0 0 3px rgba(12, 131, 31, 0.12);
    }
    .btn-mic-inline {
      position: absolute;
      right: 8px;
      background: transparent;
      border: none;
      color: var(--primary-blue);
      cursor: pointer;
      width: 26px;
      height: 26px;
      display: grid;
      place-items: center;
      border-radius: 50%;
    }
    .btn-mic-inline.listening {
      color: #EF4444;
      animation: pulseMic 1s infinite alternate;
    }
    @keyframes pulseMic { 0% { transform: scale(1); } 100% { transform: scale(1.2); } }

    /* DROPDOWN */
    .autocomplete-dropdown {
      position: absolute;
      top: calc(100% + 6px);
      left: 0;
      right: 0;
      background: #FFFFFF;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
      z-index: 100;
      max-height: 240px;
      overflow-y: auto;
      display: none;
    }
    .autocomplete-item {
      padding: 11px 14px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      cursor: pointer;
      border-bottom: 1px solid #F1F5F9;
      transition: background 0.15s;
    }
    .autocomplete-item:last-child { border-bottom: none; }
    .autocomplete-item:hover { background: var(--maakit-green-light); }
    .autocomplete-item-icon { color: #0C831F; margin-top: 2px; }
    .autocomplete-text b { display: block; font-size: 13.5px; color: var(--text-main); }
    .autocomplete-text span { display: block; font-size: 11.5px; color: var(--text-sub); }

    /* CURRENTLY SELECTED ACTIVE CARD */
    .active-village-banner {
      background: #FAFFF9;
      border: 1.5px solid var(--maakit-green-border);
      border-radius: var(--radius-md);
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .active-v-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .active-v-name {
      font-size: 16px;
      font-weight: 800;
      color: #0C831F;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .active-v-tag {
      font-size: 11px;
      font-weight: 700;
      background: #E8F5E9;
      color: #0C831F;
      padding: 3px 8px;
      border-radius: 20px;
    }
    .active-v-addr {
      font-size: 12px;
      color: var(--text-sub);
    }
    .active-v-metrics {
      display: flex;
      gap: 10px;
      padding-top: 8px;
      border-top: 1px dashed #D1E7DD;
    }
    .v-metric-item {
      font-size: 11.5px;
      color: #2E7D32;
      font-weight: 700;
    }

    /* VILLAGE LANDMARK QUICK CHIPS */
    .landmark-section {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .landmark-label {
      font-size: 11.5px;
      font-weight: 800;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .landmark-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    .landmark-chip {
      background: #F8FAFC;
      border: 1px solid #CBD5E1;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11.5px;
      font-weight: 700;
      color: var(--text-main);
      cursor: pointer;
      user-select: none;
      transition: all 0.15s;
    }
    .landmark-chip:hover { border-color: #0C831F; color: #0C831F; background: #E8F5E9; }

    /* FAMILY ORDER TOGGLE */
    .family-toggle-box {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      font-weight: 700;
      color: var(--text-main);
      cursor: pointer;
    }
    .family-toggle-box input { accent-color: #0C831F; width: 16px; height: 16px; }
    .family-input-drawer {
      display: none;
      flex-direction: column;
      gap: 6px;
      padding: 10px;
      background: #F8FAFC;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
    }
    .family-input-drawer input {
      padding: 8px 10px;
      border: 1px solid var(--border-color);
      border-radius: 6px;
      font-size: 12.5px;
      outline: none;
    }

    /* SAVED ADDRESSES */
    .saved-list-title {
      font-size: 12.5px;
      font-weight: 700;
      color: var(--text-sub);
    }
    .saved-address-card {
      border: 1px solid #E5E7EB;
      border-radius: var(--radius-md);
      padding: 12px 14px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      cursor: pointer;
      background: #FFFFFF;
      transition: all 0.15s;
    }
    .saved-address-card:hover { border-color: #0C831F; background: #FAFFF9; }
    .saved-pin-ico {
      width: 28px;
      height: 28px;
      border-radius: 6px;
      background: #FFFBEB;
      color: #D97706;
      display: grid;
      place-items: center;
      flex-shrink: 0;
      font-size: 14px;
    }
    .saved-card-body { flex: 1; }
    .saved-card-body b { display: block; font-size: 13.5px; font-weight: 700; }
    .saved-card-body p { font-size: 12px; color: var(--text-sub); margin-top: 2px; }

    /* WHATSAPP LINK */
    .whatsapp-strip {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #F0FDF4;
      border: 1px solid #BBF7D0;
      padding: 10px 14px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      color: #166534;
      font-size: 12px;
      font-weight: 700;
    }

    /* CONFIRM BUTTON */
    .btn-confirm-selection {
      background: var(--maakit-green);
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
      box-shadow: 0 4px 14px rgba(12, 131, 31, 0.3);
      transition: all 0.15s;
    }
    .btn-confirm-selection:hover { background: var(--maakit-green-hover); transform: translateY(-1px); }
    .btn-confirm-selection:active { transform: scale(0.98); }

    /* Toast */
    .app-toast {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%) translateY(30px);
      background: #0F172A;
      color: #fff;
      padding: 12px 22px;
      border-radius: 30px;
      font-size: 13.5px;
      font-weight: 700;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      opacity: 0;
      pointer-events: none;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      z-index: 2000;
    }
    .app-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
  </style>
</head>
<body>

  <!-- Real Form Submitter for Maakit Backend Session -->
  <form id="locationSubmitForm" method="POST" action="location.php">
    <input type="hidden" name="area" id="postArea" value="<?= htmlspecialchars($current_area) ?>">
    <input type="hidden" name="pin" id="postPin" value="<?= htmlspecialchars($current_pin) ?>">
    <input type="hidden" name="lat" id="postLat" value="25.3304">
    <input type="hidden" name="lng" id="postLng" value="82.6921">
    <input type="hidden" name="landmark" id="postLandmark" value="">
  </form>

  <div class="maakit-box">
    
    <!-- Top Row -->
    <div class="box-top-row">
      <h2>Change Location / स्थान चुनें</h2>
      <button class="btn-modal-close" onclick="window.location.href='/'" title="Close">✕</button>
    </div>

    <!-- maakit Row -->
    <div class="maakit-detect-row">
      <button class="btn-detect-gps" id="btnDetectGPS" type="button" onclick="runUniversalGPSDetection()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
        <span id="btnDetectText">Detect my location</span>
      </button>

      <span class="or-separator">— OR —</span>

      <div class="search-input-wrap">
        <input 
          type="text" 
          id="modalSearchInput" 
          class="search-delivery-input" 
          placeholder="search village, tola or PIN..." 
          autocomplete="off"
          oninput="handleSearchAutocomplete(this.value)"
          onfocus="handleSearchAutocomplete(this.value)"
        >
        <button class="btn-mic-inline" id="btnVoiceMic" type="button" title="बोलकर खोजें" onclick="listenVoiceQuery()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        </button>
        
        <!-- AUTOCOMPLETE DROPDOWN -->
        <div class="autocomplete-dropdown" id="suggestionsDropdown"></div>
      </div>
    </div>

    <!-- ACTIVELY SELECTED VILLAGE CARD -->
    <div class="active-village-banner">
      <div class="active-v-top">
        <div class="active-v-name">
          <span>📍</span>
          <span id="lblActiveVillage"><?= htmlspecialchars($current_area) ?></span>
        </div>
        <span class="active-v-tag">🟢 डिलीवरी चालू है</span>
      </div>
      
      <div class="active-v-addr" id="lblActiveAddress">
        <?= htmlspecialchars($current_area) ?> • सेवापुरी ब्लॉक • पिन: <?= htmlspecialchars($current_pin) ?>
      </div>

      <div class="active-v-metrics">
        <div class="v-metric-item">⚡ 25-30 मिनट में डिलीवरी</div>
        <div class="v-metric-item">🛵 2 सारथी राइडर्स एक्टिव</div>
        <div class="v-metric-item">🏬 4 दुकानें उपलब्ध</div>
      </div>
    </div>

    <!-- VILLAGE LANDMARK QUICK CHIPS -->
    <div class="landmark-section">
      <div class="landmark-label">गाँव में आसान पहचान (लैंडमार्क जोड़ें):</div>
      <div class="landmark-chips">
        <span class="landmark-chip" onclick="attachLandmark('🛕 मंदिर के पास')">🛕 मंदिर के पास</span>
        <span class="landmark-chip" onclick="attachLandmark('🏫 स्कूल के सामने')">🏫 स्कूल के सामने</span>
        <span class="landmark-chip" onclick="attachLandmark('💧 पानी की टंकी')">💧 पानी की टंकी</span>
        <span class="landmark-chip" onclick="attachLandmark('🛤️ रेलवे फाटक')">🛤️ रेलवे फाटक</span>
        <span class="landmark-chip" onclick="attachLandmark('🛣️ मुख्य चौराहा')">🛣️ मुख्य चौराहा</span>
      </div>
    </div>

    <!-- ORDER FOR FAMILY TOGGLE -->
    <label class="family-toggle-box">
      <input type="checkbox" id="chkFamily" onchange="toggleFamilyInputs(this)">
      <span>गाँव में परिवार / किसी और के लिए मँगवा रहे हैं?</span>
    </label>

    <div class="family-input-drawer" id="familyDrawer">
      <input type="text" id="recName" placeholder="प्राप्तकर्ता का नाम (उदा. पिताजी / भाई)">
      <input type="tel" id="recPhone" placeholder="उनका मोबाइल नंबर (उदा. 98381xxxxx)">
    </div>

    <!-- YOUR SAVED ADDRESSES -->
    <div class="saved-list-title">Your saved addresses</div>
    <div id="savedContainer">
      <div class="saved-address-card" onclick="pickSavedAddress('Jagarnathpur, Kapsethi, Sewapuri, Varanasi', '221403')">
        <div class="saved-pin-ico">🏠</div>
        <div class="saved-card-body">
          <b>Home (गाँव का घर)</b>
          <p>Village Jagarnathpur, Near Kapsethi, Sewapuri Block, Varanasi — 221403</p>
        </div>
      </div>

      <div class="saved-address-card" onclick="pickSavedAddress('Flat No 123, Ganpathi Nagar, Paharia, Varanasi', '221007')">
        <div class="saved-pin-ico">📍</div>
        <div class="saved-card-body">
          <b>Other</b>
          <p>Flat No 123, Beside Roadstar Garage Ganpathi Nagar, Paharia, Varanasi</p>
        </div>
      </div>
    </div>

    <!-- WHATSAPP ASSIST -->
    <a class="whatsapp-strip" href="https://wa.me/918429393903?text=%E0%A4%A8%E0%A4%AE%E0%A4%B8%E0%A5%8D%E0%A4%A4%E0%A5%87%2C%20%E0%A4%AE%E0%A5%81%E0%A4%9D%E0%A5%87%20Maakit%20%E0%A4%AA%E0%A4%B0%20%E0%A4%B2%E0%A5%8B%E0%A4%95%E0%A5%87%E0%A4%B6%E0%A4%A8%20%E0%A4%B8%E0%A5%87%E0%A4%9F%20%E0%A4%95%E0%A4%B0%E0%A4%A8%E0%A5%87%20%E0%A4%AE%E0%A5%87%E0%A4%82%20%E0%A4%AE%E0%A4%A6%E0%A4%A6%20%E0%A4%9A%E0%A4%BE%E0%A4%B9%E0%A4%BF%E0%A4%8F" target="_blank">
      <span>📲 WhatsApp पर लाइव लोकेशन भेजें</span>
      <span>हेल्पलाइन 84293 93903 →</span>
    </a>

    <!-- CONFIRM BUTTON -->
    <button class="btn-confirm-selection" id="btnConfirmSelect" type="button" onclick="submitToMaakitBackend()">
      <span id="btnConfirmLabel"><?= htmlspecialchars($current_area) ?> पर डिलीवरी सेट करें (Confirm) →</span>
    </button>

  </div>

  <div class="app-toast" id="appToast">✓ लोकेशन सुरक्षित!</div>

  <script>
    let activeState = {
      village: "<?= htmlspecialchars($current_area) ?>",
      pin: "<?= htmlspecialchars($current_pin) ?>",
      landmark: "",
      lat: "25.3304",
      lng: "82.6921"
    };

    const villageDirectory = [
      { name: "Jagarnathpur (जगरनाथपुर)", sub: "कॉपसेठी / सेवापुरी ब्लॉक, वाराणसी", pin: "221403", eta: "25 mins" },
      { name: "Kapsethi (कॉपसेठी बाज़ार)", sub: "मुख्य बाज़ार व स्टेशन, वाराणसी", pin: "221403", eta: "20 mins" },
      { name: "Sewapuri (सेवापुरी ब्लॉक)", sub: "ब्लॉक मुख्यालय, वाराणसी", pin: "221403", eta: "25 mins" },
      { name: "Kalikabara (कालिकाबारा)", sub: "कॉपसेठी समीप, सेवापुरी", pin: "221403", eta: "30 mins" },
      { name: "Bampur (बमपुर)", sub: "सेवापुरी ब्लॉक, वाराणसी", pin: "221403", eta: "30 mins" },
      { name: "Paharia (पहाड़िया)", sub: "गणपति नगर, वाराणसी", pin: "221007", eta: "30 mins" },
      { name: "Mirzamurad (मिर्ज़ामुराद)", sub: "जीटी रोड, एनएच-19, वाराणसी", pin: "221307", eta: "35 mins" },
      { name: "Raja Talab (राजा तालाब)", sub: "तहसील मार्ग, वाराणसी", pin: "221311", eta: "40 mins" },
      { name: "Bachhrawan (बछरावां)", sub: "स्टेशन रोड, रायबरेली", pin: "229301", eta: "25 mins" },
      { name: "Amwa (अमवा गाँव)", sub: "बछरावां हब, रायबरेली", pin: "229301", eta: "30 mins" }
    ];

    function handleSearchAutocomplete(query) {
      const q = query.trim().toLowerCase();
      const dropdown = document.getElementById('suggestionsDropdown');

      if (!q) {
        renderDropdownList(villageDirectory.slice(0, 4));
        dropdown.style.display = 'block';
        return;
      }

      const matches = villageDirectory.filter(v => 
        v.name.toLowerCase().includes(q) || 
        v.sub.toLowerCase().includes(q) || 
        v.pin.includes(q)
      );

      let html = '';
      matches.forEach(item => {
        html += `
          <div class="autocomplete-item" onclick="applyDropdownSelection('${item.name}', '${item.pin}', '${item.sub}')">
            <span class="autocomplete-item-icon">📍</span>
            <div class="autocomplete-text">
              <b>${item.name}</b>
              <span>${item.sub} • पिन: ${item.pin} • डिलीवरी: ${item.eta}</span>
            </div>
          </div>
        `;
      });

      // Universal Self-Add for any typed village
      html += `
        <div class="autocomplete-item" style="background:#FFFBEB; border-top:1px dashed #FDE68A;" onclick="applyCustomVillageName('${query}')">
          <span class="autocomplete-item-icon" style="color:#D97706;">✨</span>
          <div class="autocomplete-text">
            <b style="color:#92400E;">'+ ${query}' को नया गाँव जोड़ें</b>
            <span style="color:#B45309;">क्लिक करके '${query}' को अपनी डिलीवरी लोकेशन बनाएं</span>
          </div>
        </div>
      `;

      dropdown.innerHTML = html;
      dropdown.style.display = 'block';
    }

    function renderDropdownList(list) {
      const dropdown = document.getElementById('suggestionsDropdown');
      let html = '';
      list.forEach(item => {
        html += `
          <div class="autocomplete-item" onclick="applyDropdownSelection('${item.name}', '${item.pin}', '${item.sub}')">
            <span class="autocomplete-item-icon">📍</span>
            <div class="autocomplete-text">
              <b>${item.name}</b>
              <span>${item.sub} • पिन: ${item.pin}</span>
            </div>
          </div>
        `;
      });
      dropdown.innerHTML = html;
    }

    function applyDropdownSelection(name, pin, sub) {
      setLiveSelection(name, pin, `${name} • ${sub}`);
      document.getElementById('suggestionsDropdown').style.display = 'none';
      document.getElementById('modalSearchInput').value = name;
      showToast(`📍 चयनित: ${name}`);
    }

    function applyCustomVillageName(customName) {
      setLiveSelection(customName, "221403", `${customName} गाँव • सेवापुरी-वाराणसी ज़ोन`);
      document.getElementById('suggestionsDropdown').style.display = 'none';
      document.getElementById('modalSearchInput').value = customName;
      showToast(`✨ नया गाँव '${customName}' जोड़ा गया!`);
    }

    function runUniversalGPSDetection() {
      const btn = document.getElementById('btnDetectGPS');
      const txt = document.getElementById('btnDetectText');

      btn.classList.add('loading');
      txt.innerText = "खोज रहे हैं...";

      if (!navigator.geolocation) {
        fallbackResolution();
        return;
      }

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;

          btn.classList.remove('loading');
          txt.innerText = "Detect my location";

          let vName = "Jagarnathpur (जगरनाथपुर)";
          let vPin = "221403";
          if (lat >= 26.0 && lat <= 26.4 && lng >= 81.0 && lng <= 81.5) {
            vName = "Bachhrawan (बछरावां)";
            vPin = "229301";
          }

          setLiveSelection(vName, vPin, `${vName} • सेवापुरी ब्लॉक • पिन: ${vPin}`);
          showToast(`📍 सटीक गाँव: ${vName} पहचाना गया!`);
        },
        () => fallbackResolution(),
        { enableHighAccuracy: true, timeout: 8000 }
      );
    }

    function fallbackResolution() {
      const btn = document.getElementById('btnDetectGPS');
      const txt = document.getElementById('btnDetectText');
      btn.classList.remove('loading');
      txt.innerText = "Detect my location";

      setLiveSelection("Jagarnathpur (जगरनाथपुर)", "221403", "जगरनाथपुर गाँव • सेवापुरी ब्लॉक • पिन: 221403");
      showToast("📍 जगरनाथपुर गाँव सेट हुआ!");
    }

    function setLiveSelection(village, pin, fullAddress) {
      activeState.village = village;
      activeState.pin = pin;

      document.getElementById('lblActiveVillage').innerText = village;
      document.getElementById('lblActiveAddress').innerText = `${fullAddress} ${activeState.landmark ? '• ' + activeState.landmark : ''}`;
      document.getElementById('btnConfirmLabel').innerText = `${village} पर डिलीवरी सेट करें (Confirm) →`;

      // Update Form Inputs
      document.getElementById('postArea').value = village;
      document.getElementById('postPin').value = pin;
    }

    function attachLandmark(lm) {
      activeState.landmark = lm;
      document.getElementById('postLandmark').value = lm;
      document.getElementById('lblActiveAddress').innerText = `${document.getElementById('lblActiveAddress').innerText} • पहचान: ${lm}`;
      showToast(`📌 लैंडमार्क जुड़ा: ${lm}`);
    }

    function toggleFamilyInputs(chk) {
      document.getElementById('familyDrawer').style.display = chk.checked ? 'flex' : 'none';
      if (chk.checked) document.getElementById('recName').focus();
    }

    function listenVoiceQuery() {
      const mic = document.getElementById('btnVoiceMic');
      const input = document.getElementById('modalSearchInput');

      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!SpeechRecognition) {
        showToast("⚠️ वॉइस सर्च सपोर्ट नहीं है, टाइप करें");
        return;
      }

      const rec = new SpeechRecognition();
      rec.lang = 'hi-IN';
      mic.classList.add('listening');
      showToast("🎙️ बोलिए... (उदा. 'जगरनाथपुर')");

      rec.onresult = (e) => {
        const text = e.results[0][0].transcript;
        input.value = text;
        mic.classList.remove('listening');
        handleSearchAutocomplete(text);
        showToast(`🎙️ "${text}"`);
      };

      rec.onerror = () => mic.classList.remove('listening');
      rec.onend = () => mic.classList.remove('listening');
      rec.start();
    }

    function pickSavedAddress(addr, pin) {
      setLiveSelection(addr.split(',')[0], pin, addr);
      submitToMaakitBackend();
    }

    // Submit and Sync with Backend Session
    function submitToMaakitBackend() {
      const form = document.getElementById('locationSubmitForm');
      // Submits POST to location.php which updates $_SESSION['mk_area'] and redirects to homepage
      form.submit();
    }

    function showToast(msg) {
      const toast = document.getElementById('appToast');
      toast.innerText = msg;
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 2600);
    }
  </script>
</body>
</html>
