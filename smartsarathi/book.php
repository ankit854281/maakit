<?php
// ============================================================
//  सारथी — पार्सल भेजिए  →  ab maakit.in par
//
//  Smart Sarathi sirf riders ka app hai. Grahak parcel/sawari
//  maakit.in/ride.php se bookh karte hain; wahan se booking
//  API ke raaste yahan "kaam" banti hai aur rider ko milti hai.
//  Purane link (WhatsApp, poster) toote nahi, isliye ye panna
//  wahan bhej deta hai.
// ============================================================
require_once __DIR__ . '/inc/public.php';
header('Location: ' . sr_maakit_url('/ride.php?t=parcel'), true, 301);
exit;
