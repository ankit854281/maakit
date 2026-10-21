-- ============================================================
--  पैकेट — बना हुआ सेट, एक टैप में
--
--  Ankit ne kaha: "aisa koi bot chhut raha hai jo saaman ka list
--  bana kar rakhe -- customer chahe to list dekh direct order
--  kare, isse list dekhna na pade". Aur udaharan bhi diye:
--
--     100-200 rupaye ki sabzi-dudh milakar
--     pooja ke saaman ka set
--     kapde ka set
--     2 dost: 4 samosa + 2 gujiya + 1 bottle + 2 cold drink = 80
--     hafte ka 400-500, mahine ka 4000-5000 ka budget
--
--  Gaon me aadmi 40 cheezon ki soochi me se chunna nahi chahta.
--  Wo kehta hai "sau rupaye ki sabzi bhej do". Packet wahi hai --
--  pehle se banaya hua set, jise ek tap me manga liya jata hai.
--
--  TEEN NIYAM JO IS DHANCHE ME HI BANDHE HAIN
--  ------------------------------------------
--  1. EK PACKET = EK DUKAAN. business_id packet par hai, aur
--     saara saamaan usi dukaan ka hona chahiye. Kyunki paisa
--     graahak se SEEDHA dukaan ko jata hai -- do dukaanon ka
--     packet banate hi Maakit ko beech me paisa baantna padta,
--     aur wo Maakit kabhi nahi karega (AGENTS.md ka paisa niyam).
--
--  2. PACKET KA DAAM YAHAN LIKHA HI NAHI JATA. Sirf ye likha hai
--     ki kaun saamaan kitna. Daam har baar shop_items se JODA
--     jata hai. Isliye dukandaar daam badle to packet ka daam
--     apne aap badal jata hai -- Maakit kabhi dukaan ke daam se
--     alag daam nahi dikhayega. Likha hua daam ek din purana ho
--     jata hai, aur purana daam jhooth ban jata hai.
--
--  3. PACKET BOT KHUD BANATA HAI, PAR MAALIK MANZOORI DETA HAI.
--     Isliye 'suggested' aur 'live' alag rakhe gaye hain. Bot
--     sirf 'suggested' bana sakta hai.
--
--  Ye file jitni baar chalao, kuch nahi bigadta.
-- ============================================================

CREATE TABLE IF NOT EXISTS packs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  name        VARCHAR(80)  NOT NULL,          -- "100 रुपये की सब्ज़ी", "दो दोस्त"
  note        VARCHAR(160) DEFAULT NULL,      -- ek line: kiske liye hai
  kyun        VARCHAR(80)  DEFAULT NULL,      -- bot ne kyun banaya (budget/tyohar/saath)
  status      ENUM('suggested','live','hidden') NOT NULL DEFAULT 'suggested',
  banaya_by   INT DEFAULT NULL,               -- NULL = bot ne banaya
  manzoor_by  INT DEFAULT NULL,               -- maalik, jisne live kiya
  manzoor_at  DATETIME DEFAULT NULL,
  mange       INT NOT NULL DEFAULT 0,         -- kitni baar manga gaya
  sort_no     INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_packs_biz (business_id, status, sort_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Packet ke andar ka saamaan. shop_item_id usi dukaan ka hona
-- chahiye -- ye PHP me jaancha jata hai (inc/packet.php) aur
-- jaanch me pakda jata hai.
CREATE TABLE IF NOT EXISTS pack_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  pack_id      INT NOT NULL,
  shop_item_id INT NOT NULL,
  qty          INT NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_pack_item (pack_id, shop_item_id),
  INDEX idx_pack_items (pack_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dukaan apne packet band bhi kar sakti hai (jaise items_on).
-- Khana na ho to jod dijiye; ho to kuch nahi badalta.
SET @q := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'businesses'
              AND column_name = 'packs_on');
SET @s := IF(@q = 0,
  'ALTER TABLE businesses ADD COLUMN packs_on TINYINT(1) NOT NULL DEFAULT 1',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
