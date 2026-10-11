<?php
// Maakit.in & Smart Sarathi - shop.php (Retail & 15-Min Delivery Catalog)
$current_city = "Bhadohi";
$pincode = "221401";
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maakit Grocery & Daily Needs - 15 Mins Fast Delivery</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding-bottom: 90px; }
        .glass-header { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 50; border-bottom: 1px solid #e2e8f0; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .max-w-7xl { max-width: 80rem; margin-left: auto; margin-right: auto; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-3 { gap: 0.75rem; }
        .gap-2 { gap: 0.5rem; }
        .p-3 { padding: 0.75rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .rounded-full { border-radius: 9999px; }
        .rounded-2xl { border-radius: 1rem; }
        .rounded-xl { border-radius: 0.75rem; }
        .rounded { border-radius: 0.25rem; }
        .bg-white { background-color: #ffffff; }
        .bg-slate-100 { background-color: #f1f5f9; }
        .bg-green-100 { background-color: #dcfce7; }
        .bg-green-600 { background-color: #16a34a; }
        .bg-amber-500 { background-color: #f59e0b; }
        .text-slate-900 { color: #0f172a; }
        .text-slate-800 { color: #1e293b; }
        .text-slate-700 { color: #334155; }
        .text-slate-500 { color: #64748b; }
        .text-slate-400 { color: #94a3b8; }
        .text-green-600 { color: #16a34a; }
        .text-green-700 { color: #15803d; }
        .text-white { color: #ffffff; }
        .text-xs { font-size: 0.75rem; line-height: 1rem; }
        .text-[11px] { font-size: 11px; }
        .text-[10px] { font-size: 10px; }
        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        .font-extrabold { font-weight: 800; }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .border { border-width: 1px; border-style: solid; }
        .border-slate-100 { border-color: #f1f5f9; }
        .border-slate-200 { border-color: #e2e8f0; }
        .grid { display: grid; }
        .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 768px) {
            .md\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        .space-y-5 > * + * { margin-top: 1.25rem; }
        .h-32 { height: 8rem; }
        .h-9 { height: 2.25rem; }
        .w-9 { width: 2.25rem; }
        .flex-col { flex-direction: column; }
        .relative { position: relative; }
        .absolute { position: absolute; }
        .top-2 { top: 0.5rem; }
        .left-2 { left: 0.5rem; }
        .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .line-through { text-decoration: line-through; }
        .ml-1 { margin-left: 0.25rem; }
        .mt-1 { margin-top: 0.25rem; }
        .mt-3 { margin-top: 0.75rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .pt-2 { padding-top: 0.5rem; }
        .border-t { border-top-width: 1px; }
        .overflow-x-auto { overflow-x: auto; }
        .flex-shrink-0 { flex-shrink: 0; }
        .fixed { position: fixed; }
        .bottom-0 { bottom: 0; }
        .left-0 { left: 0; }
        .right-0 { right: 0; }
        .z-50 { z-index: 50; }
        .hover\:bg-slate-50:hover { background-color: #f8fafc; }
        .hover\:bg-green-700:hover { background-color: #15803d; }
        .transition { transition: all 150ms ease; }
        a { text-decoration: none; }
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
                    <h1 class="text-sm font-bold text-slate-900">दैनिक किराना & ग्रॉसरी</h1>
                    <p class="text-[11px] text-green-600 font-semibold">⚡ 15 मिनट में डिलीवरी - <?php echo $current_city; ?></p>
                </div>
            </div>
            <a href="cart.php" class="relative bg-green-100 text-green-700 px-3 py-1.5 rounded-full font-bold text-xs flex items-center gap-1.5 shadow-xs">
                🛒 कार्ट (2)
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 py-4 space-y-5">

        <!-- Category Horizontal Filter -->
        <div class="flex items-center gap-2 overflow-x-auto hide-scrollbar pb-1">
            <button class="flex-shrink-0 bg-green-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm">सभी उत्पाद</button>
            <button class="flex-shrink-0 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold hover:bg-slate-50">दूध & डेयरी</button>
            <button class="flex-shrink-0 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold hover:bg-slate-50">ताजा सब्जियां</button>
            <button class="flex-shrink-0 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold hover:bg-slate-50">चावल, आटा & दाल</button>
            <button class="flex-shrink-0 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold hover:bg-slate-50">स्नैक्स & ड्रिंक्स</button>
        </div>

        <!-- Product Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            
            <!-- Product 1 -->
            <div class="bg-white rounded-2xl p-3 border border-slate-100 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="h-32 bg-slate-100 rounded-xl mb-2 flex items-center justify-center text-slate-400 text-xs font-medium relative">
                        <span class="absolute top-2 left-2 bg-amber-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">10% OFF</span>
                        Product Image
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold bg-slate-100 px-1.5 py-0.5 rounded">⚡ 12 mins</span>
                    <h3 class="font-bold text-xs text-slate-800 mt-1 line-clamp-2">Amul Taaza Fresh Milk (1 Ltr)</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Packet</p>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-extrabold text-slate-900">₹34</span>
                        <span class="text-[10px] text-slate-400 line-through ml-1">₹38</span>
                    </div>
                    <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-1.5 rounded-xl shadow-xs transition">
                        + जोड़ें
                    </button>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="bg-white rounded-2xl p-3 border border-slate-100 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="h-32 bg-slate-100 rounded-xl mb-2 flex items-center justify-center text-slate-400 text-xs font-medium relative">
                        <span class="absolute top-2 left-2 bg-green-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">Best Seller</span>
                        Product Image
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold bg-slate-100 px-1.5 py-0.5 rounded">⚡ 15 mins</span>
                    <h3 class="font-bold text-xs text-slate-800 mt-1 line-clamp-2">Aashirvaad Shudh Chakki Atta (5 kg)</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Bag</p>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-extrabold text-slate-900">₹245</span>
                        <span class="text-[10px] text-slate-400 line-through ml-1">₹270</span>
                    </div>
                    <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-1.5 rounded-xl shadow-xs transition">
                        + जोड़ें
                    </button>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="bg-white rounded-2xl p-3 border border-slate-100 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="h-32 bg-slate-100 rounded-xl mb-2 flex items-center justify-center text-slate-400 text-xs font-medium relative">
                        Product Image
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold bg-slate-100 px-1.5 py-0.5 rounded">⚡ 10 mins</span>
                    <h3 class="font-bold text-xs text-slate-800 mt-1 line-clamp-2">Tata Salt Vacuum Evaporated (1 kg)</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Pouch</p>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-extrabold text-slate-900">₹28</span>
                    </div>
                    <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-1.5 rounded-xl shadow-xs transition">
                        + जोड़ें
                    </button>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="bg-white rounded-2xl p-3 border border-slate-100 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="h-32 bg-slate-100 rounded-xl mb-2 flex items-center justify-center text-slate-400 text-xs font-medium relative">
                        <span class="absolute top-2 left-2 bg-amber-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">15% OFF</span>
                        Product Image
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold bg-slate-100 px-1.5 py-0.5 rounded">⚡ 15 mins</span>
                    <h3 class="font-bold text-xs text-slate-800 mt-1 line-clamp-2">Fortune Sun Lite Sunflower Oil (1 Ltr)</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Pouch</p>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-extrabold text-slate-900">₹130</span>
                        <span class="text-[10px] text-slate-400 line-through ml-1">₹150</span>
                    </div>
                    <button class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-1.5 rounded-xl shadow-xs transition">
                        + जोड़ें
                    </button>
                </div>
            </div>

        </div>

    </main>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-200 px-3 py-2">
        <div class="max-w-md mx-auto flex items-center justify-around">
            <a href="index.php" class="flex flex-col items-center text-slate-500 hover:text-green-600 text-xs font-medium gap-0.5">
                🏠 <span>होम</span>
            </a>
            <a href="shop.php" class="flex flex-col items-center text-green-600 text-xs font-bold gap-0.5">
                🛒 <span>ग्रॉसरी</span>
            </a>
            <a href="cart.php" class="relative flex flex-col items-center text-slate-500 hover:text-green-600 text-xs font-medium gap-0.5">
                <span class="absolute -top-1 -right-1 bg-amber-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center">2</span>
                🛍️ <span>कार्ट</span>
            </a>
            <a href="orders.php" class="flex flex-col items-center text-slate-500 hover:text-green-600 text-xs font-medium gap-0.5">
                📦 <span>ऑर्डर्स</span>
            </a>
            <a href="account.php" class="flex flex-col items-center text-slate-500 hover:text-green-600 text-xs font-medium gap-0.5">
                👤 <span>अकाउंट</span>
            </a>
        </div>
    </nav>

</body>
</html>
