SET NAMES utf8mb4;

-- ============================================================
-- Maakit — App jaisa order system (dusra aur AAKHRI SQL)
-- phpMyAdmin me apna database chunkar, SQL tab me ye poora
-- code paste karke "Go" dabaiye. Purana data delete NAHI hoga.
-- ============================================================

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  unit VARCHAR(40) NOT NULL,
  grp VARCHAR(20) NOT NULL,
  words VARCHAR(255) DEFAULT NULL,
  popular TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_no INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_item (name, unit),
  INDEX (grp), INDEX (popular), INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- orders me naye column (agar pehle se hain to error aayega — koi dikkat nahi)
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='items_json')=0,'ALTER TABLE orders ADD COLUMN items_json TEXT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='goods_note')=0,'ALTER TABLE orders ADD COLUMN goods_note VARCHAR(255) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rated')=0,'ALTER TABLE orders ADD COLUMN rated TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------- Saaman ki list ----------

INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no) VALUES
('आटा (गेहूँ)','5 किलो','anaj','aata atta gehu wheat flour आटा गेहूँ',1,10),
('आटा (गेहूँ)','10 किलो','anaj','aata atta gehu wheat flour आटा गेहूँ',1,20),
('मैदा','1 किलो','anaj','maida flour मैदा',0,30),
('सूजी / रवा','1 किलो','anaj','suji rava sooji सूजी रवा',0,40),
('बेसन','1 किलो','anaj','besan gram flour बेसन',0,50),
('चावल (साधारण)','1 किलो','anaj','chawal rice चावल भात',1,60),
('चावल (बासमती)','1 किलो','anaj','chawal basmati rice चावल बासमती',0,70),
('अरहर / तूर दाल','1 किलो','anaj','arhar toor daal dal अरहर तूर दाल',1,80),
('मूँग दाल','1 किलो','anaj','moong daal dal मूँग दाल',0,90),
('मसूर दाल','1 किलो','anaj','masoor daal dal मसूर दाल',0,100),
('चना दाल','1 किलो','anaj','chana daal dal चना दाल',0,110),
('उड़द दाल','1 किलो','anaj','urad daal dal उड़द दाल',0,120),
('काला चना','1 किलो','anaj','kala chana काला चना',0,130),
('राजमा','1 किलो','anaj','rajma राजमा',0,140),
('पोहा','1 किलो','anaj','poha chiwda पोहा चिउड़ा',0,150),
('दलिया','1 किलो','anaj','daliya दलिया',0,160),
('सत्तू','1 किलो','anaj','sattu सत्तू',0,170),
('मक्के का आटा','1 किलो','anaj','makka makai corn flour मक्का मकई',0,180),
('सरसों तेल','1 लीटर','tel','sarson tel mustard oil सरसों तेल',1,190),
('सरसों तेल','5 लीटर','tel','sarson tel mustard oil सरसों तेल',0,200),
('रिफाइंड तेल','1 लीटर','tel','refined oil soyabean रिफाइंड तेल',1,210),
('देसी घी','1 किलो','tel','ghee desi घी',0,220),
('वनस्पति / डालडा','1 किलो','tel','vanaspati dalda वनस्पति डालडा',0,230),
('चीनी','1 किलो','tel','cheeni chini sugar चीनी शक्कर',1,240),
('गुड़','1 किलो','tel','gur gud jaggery गुड़',0,250);

INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no) VALUES
('नमक','1 किलो','tel','namak salt नमक',1,260),
('चाय पत्ती','250 ग्राम','tel','chai patti tea चाय पत्ती',1,270),
('कॉफ़ी','50 ग्राम','tel','coffee कॉफ़ी',0,280),
('हल्दी पाउडर','100 ग्राम','masala','haldi turmeric हल्दी',1,290),
('लाल मिर्च पाउडर','100 ग्राम','masala','mirch lal chilli मिर्च',1,300),
('धनिया पाउडर','100 ग्राम','masala','dhaniya coriander धनिया',1,310),
('गरम मसाला','100 ग्राम','masala','garam masala गरम मसाला',0,320),
('जीरा','100 ग्राम','masala','jeera cumin जीरा',0,330),
('राई / सरसों','100 ग्राम','masala','rai sarson mustard राई सरसों',0,340),
('काली मिर्च','50 ग्राम','masala','kali mirch pepper काली मिर्च',0,350),
('अजवाइन','50 ग्राम','masala','ajwain अजवाइन',0,360),
('हींग','10 ग्राम','masala','hing asafoetida हींग',0,370),
('तेज पत्ता','50 ग्राम','masala','tej patta bay leaf तेज पत्ता',0,380),
('सब्ज़ी मसाला','100 ग्राम','masala','sabzi masala सब्ज़ी मसाला',0,390),
('छोला मसाला','100 ग्राम','masala','chhola chole masala छोला',0,400),
('मेथी दाना','100 ग्राम','masala','methi fenugreek मेथी',0,410),
('सौंफ','100 ग्राम','masala','saunf fennel सौंफ',0,420),
('दूध (पैकेट)','500 मि.ली.','dairy','doodh milk दूध पैकेट',1,430),
('दूध (खुला)','1 लीटर','dairy','doodh milk दूध खुला',1,440),
('दही','500 ग्राम','dairy','dahi curd yogurt दही',1,450),
('पनीर','250 ग्राम','dairy','paneer cheese पनीर',0,460),
('मक्खन','100 ग्राम','dairy','makhan butter मक्खन',0,470),
('अंडे','1 दर्जन','dairy','anda ande egg अंडा अंडे',1,480),
('खोया / मावा','250 ग्राम','dairy','khoya mawa खोया मावा',0,490),
('ब्रेड','1 पैकेट','nashta','bread ब्रेड डबल रोटी',1,500);

INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no) VALUES
('बिस्कुट','1 पैकेट','nashta','biscuit बिस्कुट',1,510),
('रस्क / टोस्ट','1 पैकेट','nashta','rusk toast रस्क टोस्ट',0,520),
('नमकीन','200 ग्राम','nashta','namkeen mixture नमकीन',1,530),
('मैगी / नूडल्स','1 पैकेट','nashta','maggi noodles मैगी नूडल',1,540),
('कॉर्नफ्लेक्स','500 ग्राम','nashta','cornflakes कॉर्नफ्लेक्स',0,550),
('चिप्स / कुरकुरे','1 पैकेट','nashta','chips kurkure चिप्स कुरकुरे',0,560),
('मूँगफली','500 ग्राम','nashta','mungfali peanut मूँगफली',0,570),
('आलू','1 किलो','sabzi','aalu alu potato आलू',1,580),
('प्याज़','1 किलो','sabzi','pyaz pyaaz onion प्याज़',1,590),
('टमाटर','1 किलो','sabzi','tamatar tomato टमाटर',1,600),
('लहसुन','250 ग्राम','sabzi','lahsun garlic लहसुन',1,610),
('अदरक','250 ग्राम','sabzi','adrak ginger अदरक',1,620),
('हरी मिर्च','250 ग्राम','sabzi','hari mirch chilli हरी मिर्च',1,630),
('धनिया पत्ती','1 गुच्छा','sabzi','dhaniya patti coriander धनिया पत्ती',1,640),
('भिंडी','1 किलो','sabzi','bhindi okra भिंडी',0,650),
('लौकी','1 किलो','sabzi','lauki bottle gourd लौकी',0,660),
('बैंगन','1 किलो','sabzi','baingan brinjal बैंगन',0,670),
('गोभी','1 किलो','sabzi','gobhi cauliflower गोभी',0,680),
('पालक','1 किलो','sabzi','palak spinach पालक',0,690),
('नींबू','1 दर्जन','sabzi','nimbu lemon नींबू',0,700),
('कद्दू','1 किलो','sabzi','kaddu pumpkin कद्दू',0,710),
('परवल','1 किलो','sabzi','parwal parval परवल',0,720),
('मटर','1 किलो','sabzi','matar peas मटर',0,730),
('गाजर','1 किलो','sabzi','gajar carrot गाजर',0,740),
('शिमला मिर्च','250 ग्राम','sabzi','shimla capsicum शिमला मिर्च',0,750);

INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no) VALUES
('केला','1 दर्जन','fal','kela banana केला',1,760),
('सेब','1 किलो','fal','seb apple सेब',1,770),
('संतरा','1 किलो','fal','santra orange संतरा',0,780),
('पपीता','1 किलो','fal','papita papaya पपीता',0,790),
('अंगूर','1 किलो','fal','angoor grapes अंगूर',0,800),
('अनार','1 किलो','fal','anaar pomegranate अनार',0,810),
('अमरूद','1 किलो','fal','amrood guava अमरूद',0,820),
('तरबूज़','1 पीस','fal','tarbuj watermelon तरबूज़',0,830),
('नारियल','1 पीस','fal','nariyal coconut नारियल',0,840),
('कपड़े धोने का पाउडर','1 किलो','safai','surf powder detergent कपड़ा धोने पाउडर सर्फ',1,850),
('कपड़े धोने की टिकिया','1 पीस','safai','sabun tikiya soap साबुन टिकिया',1,860),
('बर्तन धोने की टिकिया','1 पीस','safai','bartan vim sabun बर्तन टिकिया',1,870),
('बर्तन धोने का लिक्विड','500 मि.ली.','safai','bartan liquid vim बर्तन लिक्विड',0,880),
('फ़िनाइल / फ़र्श क्लीनर','1 लीटर','safai','phenyl floor cleaner फिनाइल फर्श',0,890),
('टॉयलेट क्लीनर','500 मि.ली.','safai','toilet cleaner harpic टॉयलेट',0,900),
('झाड़ू','1 पीस','safai','jhadu broom झाड़ू',0,910),
('माचिस','1 पैकेट','safai','machis matchbox माचिस',1,920),
('अगरबत्ती','1 पैकेट','safai','agarbatti incense अगरबत्ती धूप',1,930),
('मच्छर कॉइल','1 पैकेट','safai','mosquito coil मच्छर कॉइल',0,940),
('कचरा बैग','1 पैकेट','safai','garbage bag कचरा बैग',0,950),
('नहाने का साबुन','1 पीस','sabun','sabun soap नहाने साबुन',1,960),
('शैम्पू (सैशे)','1 लड़ी','sabun','shampoo sachet शैम्पू लड़ी',1,970),
('शैम्पू (बोतल)','180 मि.ली.','sabun','shampoo bottle शैम्पू बोतल',0,980),
('टूथपेस्ट','100 ग्राम','sabun','toothpaste manjan टूथपेस्ट मंजन',1,990),
('टूथब्रश','1 पीस','sabun','toothbrush brush टूथब्रश',0,1000);

INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no) VALUES
('नारियल तेल','200 मि.ली.','sabun','nariyal tel coconut oil नारियल तेल',1,1010),
('सैनिटरी पैड','1 पैकेट','sabun','sanitary pad napkin पैड',1,1020),
('डायपर (बच्चों का)','1 पैकेट','sabun','diaper pampers डायपर',0,1030),
('शेविंग क्रीम','1 पीस','sabun','shaving cream शेविंग क्रीम',0,1040),
('रेज़र / ब्लेड','1 पैकेट','sabun','razor blade रेज़र ब्लेड',0,1050),
('कंघी','1 पीस','sabun','kanghi comb कंघी',0,1060),
('क्रीम / लोशन','1 पीस','sabun','cream lotion क्रीम लोशन',0,1070),
('समोसा','1 पीस','khana','samosa समोसा',1,1080),
('कचौड़ी','1 पीस','khana','kachori कचौड़ी',1,1090),
('पूरी-सब्ज़ी','1 प्लेट','khana','poori sabzi पूरी सब्ज़ी',1,1100),
('छोले भटूरे','1 प्लेट','khana','chhole bhature छोले भटूरे',0,1110),
('पकौड़ी','250 ग्राम','khana','pakodi pakora पकौड़ी',0,1120),
('चाट / टिक्की','1 प्लेट','khana','chaat tikki चाट टिक्की',0,1130),
('गोलगप्पे','1 प्लेट','khana','golgappa pani puri गोलगप्पे',0,1140),
('आलू पराठा','1 पीस','khana','paratha पराठा',0,1150),
('वेज थाली','1 थाली','khana','veg thali थाली खाना',1,1160),
('दाल-चावल','1 प्लेट','khana','daal chawal दाल चावल',0,1170),
('तंदूरी रोटी','1 पीस','khana','roti tandoori रोटी',0,1180),
('पनीर बटर मसाला','1 प्लेट','khana','paneer butter masala पनीर',0,1190),
('मिक्स वेज','1 प्लेट','khana','mix veg मिक्स वेज',0,1200),
('जीरा राइस','1 प्लेट','khana','jeera rice जीरा राइस',0,1210),
('वेज बिरयानी','1 प्लेट','khana','veg biryani बिरयानी',0,1220),
('चाउमीन','1 प्लेट','khana','chowmein noodles चाउमीन',1,1230),
('मोमोज़','1 प्लेट','khana','momos मोमोज़',1,1240),
('बर्गर','1 पीस','khana','burger बर्गर',0,1250);

INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no) VALUES
('पिज़्ज़ा (छोटा)','1 पीस','khana','pizza पिज़्ज़ा',0,1260),
('मैगी (बनी हुई)','1 प्लेट','khana','maggi मैगी',0,1270),
('अंडा करी','1 प्लेट','khana','anda curry egg अंडा करी',0,1280),
('चिकन करी','1 प्लेट','khana','chicken curry चिकन',0,1290),
('चिकन बिरयानी','1 प्लेट','khana','chicken biryani चिकन बिरयानी',0,1300),
('मटन करी','1 प्लेट','khana','mutton मटन',0,1310),
('जलेबी','250 ग्राम','mithai','jalebi जलेबी',1,1320),
('रसगुल्ला','1 किलो','mithai','rasgulla रसगुल्ला',0,1330),
('गुलाब जामुन','1 किलो','mithai','gulab jamun गुलाब जामुन',0,1340),
('बेसन लड्डू','1 किलो','mithai','laddoo besan लड्डू',0,1350),
('मोतीचूर लड्डू','1 किलो','mithai','motichoor laddoo मोतीचूर लड्डू',0,1360),
('बर्फ़ी','1 किलो','mithai','barfi बर्फ़ी',0,1370),
('पेड़ा','1 किलो','mithai','peda पेड़ा',0,1380),
('इमरती','1 किलो','mithai','imarti इमरती',0,1390),
('रसमलाई','1 पीस','mithai','rasmalai रसमलाई',0,1400),
('केक','500 ग्राम','mithai','cake birthday केक',1,1410),
('केक','1 किलो','mithai','cake birthday केक',0,1420),
('पेस्ट्री','1 पीस','mithai','pastry पेस्ट्री',0,1430),
('पैटीज़','1 पीस','mithai','patties पैटीज़',0,1440),
('क्रीम रोल','1 पीस','mithai','cream roll क्रीम रोल',0,1450),
('कोल्ड ड्रिंक','750 मि.ली.','peene','cold drink pepsi coke कोल्ड ड्रिंक',1,1460),
('पानी की बोतल','1 लीटर','peene','pani bottle water पानी बोतल',1,1470),
('लस्सी','1 गिलास','peene','lassi लस्सी',0,1480),
('मट्ठा / छाछ','1 गिलास','peene','mattha chhach मट्ठा छाछ',0,1490),
('जूस','1 गिलास','peene','juice जूस',0,1500);

INSERT IGNORE INTO items (name, unit, grp, words, popular, sort_no) VALUES
('शेक','1 गिलास','peene','shake शेक',0,1510),
('चाय','1 कप','peene','chai tea चाय',0,1520),
('गैस सिलेंडर (रीफिल)','1 सिलेंडर','anya','gas cylinder सिलेंडर गैस',1,1530),
('मोमबत्ती','1 पैकेट','anya','mombatti candle मोमबत्ती',0,1540),
('बैटरी (AA)','2 पीस','anya','battery cell बैटरी सेल',0,1550),
('बल्ब (LED)','1 पीस','anya','bulb led बल्ब',1,1560),
('कॉपी / नोटबुक','1 पीस','anya','copy notebook कॉपी नोटबुक',1,1570),
('पेन','1 पीस','anya','pen पेन कलम',1,1580),
('मोबाइल रीचार्ज कूपन','1 पीस','anya','recharge coupon रीचार्ज',0,1590),
('रस्सी','1 पीस','anya','rassi rope रस्सी',0,1600),
('तार / वायर','1 पीस','anya','wire tar तार वायर',0,1610),
('पूजा का सामान','1 सेट','anya','puja samagri पूजा सामग्री रोली कलावा',1,1620),
('फूल माला','1 पीस','anya','phool mala flower फूल माला',0,1630),
('चारा / पशु आहार','1 बोरी','anya','chara pashu aahar चारा भूसा',0,1640),
('खाद','1 बोरी','anya','khaad urea fertilizer खाद यूरिया',0,1650);


-- ============================================================
-- Booking, customer account, photo — ye sab ek hi baar
-- ============================================================

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  mobile VARCHAR(15) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  village VARCHAR(60) DEFAULT NULL,
  landmark VARCHAR(120) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_no VARCHAR(20) NOT NULL,
  code VARCHAR(6) NOT NULL,
  service VARCHAR(20) NOT NULL,
  customer_id INT DEFAULT NULL,
  name VARCHAR(80) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  village VARCHAR(60) DEFAULT NULL,
  address VARCHAR(200) DEFAULT NULL,
  answers TEXT,
  summary TEXT,
  note TEXT,
  business_id INT DEFAULT NULL,
  quote INT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Naya',
  handled_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (service), INDEX (status), INDEX (mobile), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- orders me aur naye column
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='customer_id')=0,'ALTER TABLE orders ADD COLUMN customer_id INT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='photo')=0,'ALTER TABLE orders ADD COLUMN photo VARCHAR(120) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='bill_photo')=0,'ALTER TABLE orders ADD COLUMN bill_photo VARCHAR(120) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- items me photo ka khana (photo na ho to icon dikhega)
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='items' AND COLUMN_NAME='photo')=0,'ALTER TABLE items ADD COLUMN photo VARCHAR(120) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- Ticker, offer banner, location, area request, search report
-- ============================================================

CREATE TABLE IF NOT EXISTS banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title_en VARCHAR(80) NOT NULL,
  title_hi VARCHAR(80) NOT NULL,
  sub_en VARCHAR(140) DEFAULT NULL,
  sub_hi VARCHAR(140) DEFAULT NULL,
  link VARCHAR(160) DEFAULT NULL,
  photo VARCHAR(120) DEFAULT NULL,
  tone VARCHAR(12) NOT NULL DEFAULT 'brand',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_no INT NOT NULL DEFAULT 0,
  starts DATE DEFAULT NULL,
  ends DATE DEFAULT NULL,
  clicks INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  village VARCHAR(80) NOT NULL,
  block VARCHAR(80) DEFAULT NULL,
  name VARCHAR(80) DEFAULT NULL,
  mobile VARCHAR(15) NOT NULL,
  note VARCHAR(200) DEFAULT NULL,
  status ENUM('new','planned','live','no') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (village), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS search_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  q VARCHAR(80) NOT NULL,
  hits INT NOT NULL DEFAULT 0,
  times INT NOT NULL DEFAULT 1,
  last_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq (q),
  INDEX (hits), INDEX (times)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- orders: location aur samay
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='lat')=0,'ALTER TABLE orders ADD COLUMN lat DECIMAL(10,7) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='lng')=0,'ALTER TABLE orders ADD COLUMN lng DECIMAL(10,7) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='confirmed_at')=0,'ALTER TABLE orders ADD COLUMN confirmed_at DATETIME NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='assigned_at')=0,'ALTER TABLE orders ADD COLUMN assigned_at DATETIME NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='picked_at')=0,'ALTER TABLE orders ADD COLUMN picked_at DATETIME NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='delivered_at')=0,'ALTER TABLE orders ADD COLUMN delivered_at DATETIME NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- villages: kis din se chalu, dikhana hai ya nahi
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='villages' AND COLUMN_NAME='live')=0,'ALTER TABLE villages ADD COLUMN live TINYINT(1) NOT NULL DEFAULT 1','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- shuruati offer (aap admin se badal sakte hain)
INSERT IGNORE INTO banners (id, title_en, title_hi, sub_en, sub_hi, link, tone, sort_no) VALUES
(1, 'First delivery FREE', 'पहली डिलीवरी फ़्री', 'Your first Maakit order costs you nothing extra', 'पहला ऑर्डर — डिलीवरी चार्ज बिल्कुल नहीं', '/order.php', 'brand', 10),
(2, 'Book a Bolero', 'बोलेरो बुक कीजिए', 'Hospital, station, wedding — car at your door', 'अस्पताल, स्टेशन, शादी — गाड़ी घर से', '/book.php?s=gaadi', 'gold', 20),
(3, 'Shop bill photo, every time', 'हर बार बिल की फ़ोटो', 'You pay the shop price. Nothing added.', 'दुकान का ही दाम — हम कुछ नहीं जोड़ते', '/order.php', 'dark', 30);

-- gaon ka English naam (English me website khulne par yahi dikhega)
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='villages' AND COLUMN_NAME='name_en')=0,'ALTER TABLE villages ADD COLUMN name_en VARCHAR(60) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE villages SET name_en='Barwa'      WHERE name='बरवा'      AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Bhagwanpur' WHERE name='भगवानपुर'  AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Surhan'     WHERE name='सुरहन'     AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Sawarpur'   WHERE name='सवारपुर'   AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Tikaitpur'  WHERE name='टिकैतपुर'  AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Nahwanipur' WHERE name='नहवानीपुर' AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Dabethua'   WHERE name='डबेथुआ'    AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Jhaua'      WHERE name='झौआ'       AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Pilkhani'   WHERE name='पिलखनी'    AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Gajepur'    WHERE name='गजेपुर'    AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Kandhiya'   WHERE name='कंधिया'    AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Amwa'       WHERE name='अमवा'      AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Govindpur'  WHERE name='गोविंदपुर' AND (name_en IS NULL OR name_en='');
UPDATE villages SET name_en='Lathiya'    WHERE name='लठिया'     AND (name_en IS NULL OR name_en='');

-- ============================================================
-- Transport — gaadi wale apna registration khud karte hain
-- ============================================================

CREATE TABLE IF NOT EXISTS transports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  owner VARCHAR(80) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  mobile2 VARCHAR(15) DEFAULT NULL,
  village VARCHAR(80) DEFAULT NULL,

  vtype VARCHAR(30) NOT NULL,
  vname VARCHAR(60) DEFAULT NULL,
  vnumber VARCHAR(20) DEFAULT NULL,
  seats INT NOT NULL DEFAULT 0,
  ac TINYINT(1) NOT NULL DEFAULT 0,
  photo VARCHAR(120) DEFAULT NULL,

  rate_km INT DEFAULT NULL,
  rate_min INT DEFAULT NULL,
  rate_day INT DEFAULT NULL,
  rate_wait INT DEFAULT NULL,
  outstation TINYINT(1) NOT NULL DEFAULT 1,
  night TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,

  dl_ok TINYINT(1) NOT NULL DEFAULT 0,
  rc_ok TINYINT(1) NOT NULL DEFAULT 0,
  ins_ok TINYINT(1) NOT NULL DEFAULT 0,
  permit_ok TINYINT(1) NOT NULL DEFAULT 0,
  ins_exp DATE DEFAULT NULL,
  fit_exp DATE DEFAULT NULL,

  available TINYINT(1) NOT NULL DEFAULT 1,
  avail_updated DATETIME DEFAULT NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME DEFAULT NULL,
  trips INT NOT NULL DEFAULT 0,
  access_code VARCHAR(12) DEFAULT NULL,
  status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_biz (business_id),
  INDEX (status), INDEX (vtype), INDEX (available), INDEX (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- booking me kaun si gaadi gayi, kitna commission
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='service_bookings' AND COLUMN_NAME='transport_id')=0,'ALTER TABLE service_bookings ADD COLUMN transport_id INT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='service_bookings' AND COLUMN_NAME='commission')=0,'ALTER TABLE service_bookings ADD COLUMN commission INT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- Maal gaadi, live location, settings
-- ============================================================

CREATE TABLE IF NOT EXISTS live_tracks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  acc INT DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order (order_id),
  INDEX (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  v VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v) VALUES
('open_time',  '08:00'),
('close_time', '20:00'),
('closed_today', '0'),
('closed_note_hi', ''),
('closed_note_en', ''),
('off_days', '');

-- transports: maal dhone wali jaankari
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transports' AND COLUMN_NAME='goods')=0,'ALTER TABLE transports ADD COLUMN goods TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transports' AND COLUMN_NAME='passenger')=0,'ALTER TABLE transports ADD COLUMN passenger TINYINT(1) NOT NULL DEFAULT 1','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transports' AND COLUMN_NAME='capacity')=0,'ALTER TABLE transports ADD COLUMN capacity DECIMAL(5,2) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transports' AND COLUMN_NAME='rate_trip')=0,'ALTER TABLE transports ADD COLUMN rate_trip INT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transports' AND COLUMN_NAME='loading')=0,'ALTER TABLE transports ADD COLUMN loading TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- purani gaadiyan: tractor/dala maal wali hain
UPDATE transports SET goods=1, passenger=0 WHERE vtype IN ('tractor','dala');
