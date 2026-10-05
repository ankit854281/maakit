-- ============================================================
-- 006 — do nayi shreniyan: Puja ka saaman, aur Khad-Beej-Chara
--
-- Ye research se nikli thin:
--   * Puja  — Blinkit me "Celebrations & Rituals", Zepto me
--             "Puja Essentials". Dono bade app ye rakhte hain.
--   * Khad  — kisi app me nahi hai, kyonki wo sirf bade shehar me
--             chalte hain. Bhadohi kheti wala ilaka hai, isliye
--             yahi Maakit ka apna mauka hai.
--
-- Section dono ka 'saaman' hai (kiraana wala hissa).
-- INSERT IGNORE hai, isliye dobara chalane par dohra nahi padega.
-- (items.name par UNIQUE hai — isi bharose ye chalta hai.)
-- ============================================================
SET NAMES utf8mb4;

-- ---------- Puja ka saaman ----------
INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no, section) VALUES
('अगरबत्ती',          '1 पैकेट',  'pooja', 'agarbatti incense dhoop अगरबत्ती धूप',                     1, 1700, 'saaman'),
('धूपबत्ती',          '1 पैकेट',  'pooja', 'dhoop dhoopbatti धूपबत्ती',                                0, 1705, 'saaman'),
('कपूर',              '50 ग्राम', 'pooja', 'kapoor camphor कपूर',                                      1, 1710, 'saaman'),
('रोली / कुमकुम',     '50 ग्राम', 'pooja', 'roli kumkum tilak रोली कुमकुम तिलक',                       0, 1715, 'saaman'),
('मौली / कलावा',      '1 पीस',   'pooja', 'mauli kalava raksha sutra मौली कलावा रक्षा सूत्र',          0, 1720, 'saaman'),
('हवन सामग्री',       '1 पैकेट',  'pooja', 'hawan samagri havan हवन सामग्री',                          0, 1725, 'saaman'),
('नारियल',            '1 पीस',   'pooja', 'nariyal coconut gola नारियल गोला',                         1, 1730, 'saaman'),
('सुपारी',            '100 ग्राम','pooja', 'supari betel nut सुपारी',                                  0, 1735, 'saaman'),
('मिट्टी का दीया',     '1 दर्जन', 'pooja', 'diya deepak mitti दीया दीपक मिट्टी',                       1, 1740, 'saaman'),
('रुई की बत्ती',       '1 पैकेट',  'pooja', 'rui batti wick bati रुई बत्ती',                            0, 1745, 'saaman'),
('गंगाजल',            '500 मि.ली.','pooja','gangajal ganga jal गंगाजल',                                0, 1750, 'saaman'),
('चंदन',              '50 ग्राम', 'pooja', 'chandan sandal चंदन',                                      0, 1755, 'saaman'),
('फूल माला',          '1 पीस',   'pooja', 'phool mala genda flower garland फूल माला गेंदा',           1, 1760, 'saaman'),
('पूजा की थाली',      '1 सेट',   'pooja', 'puja thali set पूजा थाली सेट',                             0, 1765, 'saaman');

-- ---------- Khad, Beej aur Chara ----------
INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no, section) VALUES
('यूरिया',             '1 बोरी',  'khad', 'urea khad यूरिया खाद',                                     1, 1800, 'saaman'),
('DAP',                '1 बोरी',  'khad', 'dap khad डीएपी खाद',                                       1, 1805, 'saaman'),
('पोटाश',              '1 बोरी',  'khad', 'potash potas पोटाश',                                       0, 1810, 'saaman'),
('ज़िंक',               '1 पैकेट',  'khad', 'zinc jink ज़िंक जिंक',                                      0, 1815, 'saaman'),
('वर्मी कम्पोस्ट',      '1 बोरी',  'khad', 'vermi compost gobar khaad वर्मी कम्पोस्ट गोबर खाद',        0, 1820, 'saaman'),
('गेहूँ का बीज',        '1 बोरी',  'khad', 'gehu beej wheat seed गेहूँ बीज',                           1, 1825, 'saaman'),
('धान का बीज',         '1 बोरी',  'khad', 'dhan beej paddy rice seed धान बीज',                        1, 1830, 'saaman'),
('सरसों का बीज',       '1 किलो',  'khad', 'sarson beej mustard seed सरसों बीज',                       0, 1835, 'saaman'),
('सब्ज़ी का बीज',       '1 पैकेट',  'khad', 'sabzi beej vegetable seed सब्ज़ी बीज',                      0, 1840, 'saaman'),
('कीटनाशक दवा',        '1 पीस',   'khad', 'keetnashak pesticide spray dawa कीटनाशक दवा स्प्रे',       0, 1845, 'saaman'),
('खरपतवार नाशक',       '1 पीस',   'khad', 'kharpatwar weedicide नाशक खरपतवार',                       0, 1850, 'saaman'),
('चोकर',               '1 बोरी',  'khad', 'chokar bran pashu aahar चोकर पशु आहार',                    1, 1855, 'saaman'),
('सरसों की खली',       '1 बोरी',  'khad', 'khali sarson oil cake खली',                                1, 1860, 'saaman'),
('चुनी',               '1 बोरी',  'khad', 'chuni chuna pashu चुनी',                                   0, 1865, 'saaman'),
('भूसा',               '1 बोरी',  'khad', 'bhusa straw chara भूसा चारा',                              0, 1870, 'saaman'),
('पशु की दवा',         '1 पीस',   'khad', 'pashu dawa cattle medicine पशु दवा',                       0, 1875, 'saaman');

-- ---------- jo pehle se the, par galat jagah ----------
-- अगरबत्ती "साफ़-सफ़ाई" me padi thi aur फूल माला "दूसरा सामान" me.
-- Dono pooja ki cheezein hain, isliye wahin bhej diya.
-- नारियल ko 'fal' me hi rehne diya (wo sach me fal hai), par uske
-- shabdon me pooja jod diya taaki dhoondhne par mil jaye.
UPDATE items SET grp = 'pooja' WHERE name IN ('अगरबत्ती', 'फूल माला');

UPDATE items
   SET words = CONCAT(COALESCE(words, ''), ' pooja puja nariyal gola पूजा नारियल गोला')
 WHERE name = 'नारियल' AND words NOT LIKE '%pooja%';
