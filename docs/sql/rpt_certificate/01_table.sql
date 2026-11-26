-- rpt_certificate setup. Run the files in order: 01_table.sql, 02_triggers.sql, 03_backfill_and_checks.sql
-- Adds only; nothing existing is changed. Use phpMyAdmin > Import > choose file for each.

-- ============ STEP 1: the table ============
CREATE TABLE IF NOT EXISTS rpt_certificate (
    source      VARCHAR(40)  NOT NULL,
    rid         VARCHAR(40)  NOT NULL,
    cert_date   DATE         NULL,
    station     VARCHAR(50)  NULL,
    officer     VARCHAR(255) NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (source, rid),
    KEY idx_rid (rid),
    KEY idx_station_date (station, cert_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

