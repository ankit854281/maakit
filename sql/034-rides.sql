-- ============================================================
-- 034: (table "rides") Sawari (ride) aur parcel booking — maakit.in/ride.php
--
-- Grahak yahan bike / auto / e-rickshaw ya parcel bookh karta
-- hai. Booking Smart Sarathi ko "kaam" bankar jaati hai
-- (inc/sarathi-bridge.php), aur wahan ki halat yahan aati hai.
-- Do baar chalne par bhi theek.
-- ============================================================
CREATE TABLE IF NOT EXISTS rides (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  trip_no       VARCHAR(20)  NOT NULL,
  kind          VARCHAR(10)  NOT NULL DEFAULT 'ride',      -- ride / parcel
  vehicle       VARCHAR(12)  DEFAULT NULL,                 -- bike / auto / erick
  name          VARCHAR(80)  NOT NULL,
  mobile        VARCHAR(15)  NOT NULL,
  customer_id   INT          DEFAULT NULL,
  pick_address  VARCHAR(250) NOT NULL,
  pick_geo      VARCHAR(40)  DEFAULT NULL,
  drop_name     VARCHAR(80)  DEFAULT NULL,
  drop_mobile   VARCHAR(15)  DEFAULT NULL,
  drop_address  VARCHAR(250) NOT NULL,
  drop_geo      VARCHAR(40)  DEFAULT NULL,
  items         VARCHAR(300) DEFAULT NULL,
  note          VARCHAR(300) DEFAULT NULL,
  seats         TINYINT      DEFAULT NULL,
  pay_by        VARCHAR(10)  DEFAULT NULL,                 -- parcel: sender / receiver
  when_at       VARCHAR(5)   DEFAULT NULL,                 -- 'HH:MM' = aaj baad me, NULL = abhi
  km            DECIMAL(6,1) DEFAULT NULL,
  fare_est      INT          DEFAULT NULL,
  pick_code     CHAR(4)      NOT NULL,
  drop_code     CHAR(4)      NOT NULL,
  status        VARCHAR(12)  NOT NULL DEFAULT 'new',       -- new / assigned / picked / delivered / cancelled
  sarathi_job   VARCHAR(20)  DEFAULT NULL,
  send_error    VARCHAR(200) DEFAULT NULL,
  rider_name    VARCHAR(80)  DEFAULT NULL,
  rider_mobile  VARCHAR(15)  DEFAULT NULL,
  checked_at    DATETIME     DEFAULT NULL,
  picked_at     DATETIME     DEFAULT NULL,
  done_at       DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (trip_no),
  KEY (mobile),
  KEY (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
