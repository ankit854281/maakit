-- ============================================================
-- 005 — "chaat" naam ka naya group
--
-- Home page par "Samosa & Momos" ka dibba chahiye — 20 minute me
-- aa jane wali chhoti cheezein. Abhi ye sab 'khana' me padi hain,
-- poori thali aur biryani ke saath, isliye dab jati hain.
--
-- Isliye bana khana do hisson me:
--   chaat  -> samosa, momos, chowmein, pakodi — ₹10 se ₹60
--   khana  -> thali, biryani, curry — ₹80 se upar
--
-- Section dono ka wahi 'khana' rahega. Sirf group badal raha hai.
-- Dobara chalane par bhi kuchh nahi bigadta.
-- ============================================================

-- Hindi naam sahi match hon, isliye charset pehle hi tay kar dijiye
SET NAMES utf8mb4;

UPDATE items SET grp = 'chaat'
WHERE section = 'khana' AND grp = 'khana' AND name IN (
  'समोसा', 'कचौड़ी', 'पूरी-सब्ज़ी', 'छोले भटूरे', 'पकौड़ी',
  'चाट / टिक्की', 'गोलगप्पे', 'आलू पराठा', 'चाउमीन', 'मोमोज़',
  'बर्गर', 'मैगी (बनी हुई)'
);
