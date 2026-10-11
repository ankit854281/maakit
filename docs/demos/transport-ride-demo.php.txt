<?php
// Maakit.in & Smart Sarathi - transport.php (Smart Sarathi Cab, Auto & Bike Ride Booking)
$current_city = "Bhadohi";
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Sarathi - Cab, Auto & Bike Rides in Bhadohi</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding-bottom: 90px; }
        .glass-header { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 50; border-bottom: 1px solid #e2e8f0; }
        .max-w-7xl { max-width: 80rem; margin-left: auto; margin-right: auto; }
        .max-w-md { max-width: 28rem; margin-left: auto; margin-right: auto; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-3 { gap: 0.75rem; }
        .gap-2 { gap: 0.5rem; }
        .p-4 { padding: 1rem; }
        .p-3 { padding: 0.75rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .py-2.5 { padding-top: 0.625rem; padding-bottom: 0.625rem; }
        .rounded-full { border-radius: 9999px; }
        .rounded-2xl { border-radius: 1rem; }
        .rounded-xl { border-radius: 0.75rem; }
        .bg-white { background-color: #ffffff; }
        .bg-slate-100 { background-color: #f1f5f9; }
        .bg-blue-600 { background-color: #2563eb; }
        .bg-blue-50 { background-color: #eff6ff; }
        .text-slate-900 { color: #0f172a; }
        .text-slate-800 { color: #1e293b; }
        .text-slate-700 { color: #334155; }
        .text-slate-500 { color: #64748b; }
        .text-blue-600 { color: #2563eb; }
        .text-blue-700 { color: #1d4ed8; }
        .text-white { color: #ffffff; }
        .text-xs { font-size: 0.75rem; line-height: 1rem; }
        .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .text-[11px] { font-size: 11px; }
        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .shadow-md { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .border { border-width: 1px; border-style: solid; }
        .border-slate-200 { border-color: #e2e8f0; }
        .border-blue-200 { border-color: #bfdbfe; }
        .space-y-4 > * + * { margin-top: 1rem; }
        .w-9 { width: 2.25rem; }
        .h-9 { height: 2.25rem; }
        .w-full { width: 100%; }
        .grid { display: grid; }
        .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .text-center { text-align: center; }
        .fixed { position: fixed; }
        .bottom-0 { bottom: 0; }
        .left-0 { left: 0; }
        .right-0 { right: 0; }
        .z-50 { z-index: 50; }
        .hover\:bg-blue-700:hover { background-color: #1d4ed8; }
        .transition { transition: all 150ms ease; }
        a { text-decoration: none; }
        input { border: none; outline: none; background: transparent; width: 100%; font-family: inherit; }
        button { border: none; background: none; cursor: pointer; }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="glass-header px-4 py-3">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="index.php" class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-700 hover:bg-slate-200 transition">
                    ←
                </a>
                <div>
                    <h1 class="text-sm font-bold text-slate-900">Smart Sarathi Transport</h1>
                    <p class="text-[11px] text-blue-600 font-semibold">🚀 सुरक्षित & तेज राइड्स - <?php echo $current_city; ?></p>
                </div>
            </div>
            <a href="tel:112" class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-full font-bold text-xs flex items-center gap-1">
                🚨 SOS हेल्प
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-md mx-auto px-4 py-4 space-y-4">

        <!-- Live Status Banner -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-3 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                    🛺
                </div>
                <div>
                    <h3 class="text-xs font-bold text-slate-800">आसपास राइडर्स उपलब्ध</h3>
                    <p class="text-[11px] text-slate-500">भदोही में अभी 18 ऑटो & कैब एक्टिव हैं</p>
                </div>
            </div>
            <span class="text-[10px] bg-green-100 text-green-700 font-bold px-2 py-1 rounded-full">⚡ Live</span>
        </div>

        <!-- Ride Booking Form Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-md space-y-4">
            <h2 class="text-sm font-bold text-slate-900">कहाँ जाना है? राइड बुक करें</h2>

            <!-- Pickup Location -->
            <div class="bg-slate-100 px-3 py-2.5 rounded-xl flex items-center gap-3 border border-slate-200">
                <span class="text-green-600 font-bold">🟢</span>
                <input type="text" placeholder="पिकअप स्थान (जैसे: गोपीगंज तिराहा, भदोही)" value="Current Location: Bhadohi City">
            </div>

            <!-- Drop Location -->
            <div class="bg-slate-100 px-3 py-2.5 rounded-xl flex items-center gap-3 border border-slate-200">
                <span class="text-rose-600 font-bold">📍</span>
                <input type="text" placeholder="ड्रॉप स्थान (कहाँ जाना है?)">
            </div>

            <!-- Vehicle Type Selection -->
            <div>
                <label class="text-xs font-bold text-slate-700 mb-2 block">वाहन का प्रकार चुनें:</label>
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-blue-50 border-2 border-blue-600 rounded-xl p-3 text-center cursor-pointer">
                        <div class="text-2xl mb-1">🛺</div>
                        <h4 class="font-bold text-xs text-slate-800">Auto</h4>
                        <p class="text-[10px] text-blue-600 font-bold">~₹60</p>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-center cursor-pointer">
                        <div class="text-2xl mb-1">🏍️</div>
                        <h4 class="font-bold text-xs text-slate-800">Bike</h4>
                        <p class="text-[10px] text-slate-500 font-bold">~₹35</p>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-center cursor-pointer">
                        <div class="text-2xl mb-1">🚗</div>
                        <h4 class="font-bold text-xs text-slate-800">Cab</h4>
                        <p class="text-[10px] text-slate-500 font-bold">~₹150</p>
                    </div>
                </div>
            </div>

            <!-- Book Ride Button -->
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-3 rounded-xl shadow-md transition">
                🚀 सारथी राइड बुक करें
            </button>
        </div>

        <!-- Active Rides / History Section -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs space-y-3">
            <h3 class="text-xs font-bold text-slate-800 flex items-center justify-between">
                <span>हाल की राइड्स</span>
                <span class="text-blue-600 text-[11px]">सभी देखें</span>
            </h3>
            <div class="bg-slate-50 p-3 rounded-xl flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-800">Civil Lines → Station Road</p>
                    <p class="text-[10px] text-slate-500">Auto • Completed 2 hrs ago</p>
                </div>
                <span class="text-xs font-extrabold text-slate-900">₹65</span>
            </div>
        </div>

    </main>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-200 px-3 py-2">
        <div class="max-w-md mx-auto flex items-center justify-around">
            <a href="index.php" class="flex flex-col items-center text-slate-500 hover:text-blue-600 text-xs font-medium gap-0.5">
                🏠 <span>होम</span>
            </a>
            <a href="shop.php" class="flex flex-col items-center text-slate-500 hover:text-blue-600 text-xs font-medium gap-0.5">
                🛒 <span>ग्रॉसरी</span>
            </a>
            <a href="transport.php" class="flex flex-col items-center text-blue-600 text-xs font-bold gap-0.5">
                🛺 <span>सारथी</span>
            </a>
            <a href="orders.php" class="flex flex-col items-center text-slate-500 hover:text-blue-600 text-xs font-medium gap-0.5">
                📦 <span>ऑर्डर्स</span>
            </a>
            <a href="account.php" class="flex flex-col items-center text-slate-500 hover:text-blue-600 text-xs font-medium gap-0.5">
                👤 <span>अकाउंट</span>
            </a>
        </div>
    </nav>

</body>
</html>
