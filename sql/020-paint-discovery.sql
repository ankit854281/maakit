-- Discovery templates, not inventory or priced offers. Owners choose packs/prices.
SET NAMES utf8mb4;
INSERT IGNORE INTO catalog_items
(id,shop_type,sub_cat,name_en,name_hi,unit_hint,is_sewa,sort_no) VALUES
(1511,'Paint Store','Paint & Painting Tools','Asian Paints Interior Emulsion','एशियन पेंट्स इंटीरियर इमल्शन','लीटर',0,340),
(1512,'Paint Store','Paint & Painting Tools','Berger Interior Emulsion','बर्जर इंटीरियर इमल्शन','लीटर',0,341),
(1513,'Paint Store','Paint & Painting Tools','Nerolac Interior Emulsion','नेरोलैक इंटीरियर इमल्शन','लीटर',0,342),
(1514,'Paint Store','Paint & Painting Tools','Dulux Interior Emulsion','ड्यूलक्स इंटीरियर इमल्शन','लीटर',0,343),
(1515,'Paint Store','Paint & Painting Tools','Indigo Interior Emulsion','इंडिगो इंटीरियर इमल्शन','लीटर',0,344),
(1516,'Paint Store','Paint & Painting Tools','JSW Paints Interior Emulsion','जेएसडब्ल्यू पेंट्स इंटीरियर इमल्शन','लीटर',0,345);
