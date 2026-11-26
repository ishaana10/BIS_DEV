-- Keeps rpt_certificate current. Each trigger ignores its own errors, so it can never block a save.
-- Import this file in phpMyAdmin (Import tab); the DELIMITER lines are understood there.

DELIMITER $$
-- Each trigger ignores its own errors, so a problem here can never stop a user from saving a record.

DROP TRIGGER IF EXISTS trg_rptc_brdingn_ai$$
CREATE TRIGGER trg_rptc_brdingn_ai AFTER INSERT ON brdingn FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('brdingn', NEW.brdingn_id, IF(NEW.br_date < '1000-01-01', NULL, NEW.br_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_brdingn_au$$
CREATE TRIGGER trg_rptc_brdingn_au AFTER UPDATE ON brdingn FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('brdingn', NEW.brdingn_id, IF(NEW.br_date < '1000-01-01', NULL, NEW.br_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_brdingn_ad$$
CREATE TRIGGER trg_rptc_brdingn_ad AFTER DELETE ON brdingn FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'brdingn' AND rid = OLD.brdingn_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_postbordericn_ai$$
CREATE TRIGGER trg_rptc_postbordericn_ai AFTER INSERT ON postbordericn FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('postbordericn', NEW.postbordericn_id, IF(NEW.po_date < '1000-01-01', NULL, NEW.po_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_postbordericn_au$$
CREATE TRIGGER trg_rptc_postbordericn_au AFTER UPDATE ON postbordericn FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('postbordericn', NEW.postbordericn_id, IF(NEW.po_date < '1000-01-01', NULL, NEW.po_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_postbordericn_ad$$
CREATE TRIGGER trg_rptc_postbordericn_ad AFTER DELETE ON postbordericn FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'postbordericn' AND rid = OLD.postbordericn_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_brdingn_extended_ai$$
CREATE TRIGGER trg_rptc_brdingn_extended_ai AFTER INSERT ON brdingn_extended FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('brdingn_extended', NEW.brdingn_extended_id, IF(NEW.bex_date < '1000-01-01', NULL, NEW.bex_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_brdingn_extended_au$$
CREATE TRIGGER trg_rptc_brdingn_extended_au AFTER UPDATE ON brdingn_extended FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('brdingn_extended', NEW.brdingn_extended_id, IF(NEW.bex_date < '1000-01-01', NULL, NEW.bex_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_brdingn_extended_ad$$
CREATE TRIGGER trg_rptc_brdingn_extended_ad AFTER DELETE ON brdingn_extended FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'brdingn_extended' AND rid = OLD.brdingn_extended_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_fumigations_ai$$
CREATE TRIGGER trg_rptc_fumigations_ai AFTER INSERT ON fumigations FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('fumigations', NEW.fumigations_id, IF(NEW.fu_date < '1000-01-01', NULL, NEW.fu_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_fumigations_au$$
CREATE TRIGGER trg_rptc_fumigations_au AFTER UPDATE ON fumigations FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('fumigations', NEW.fumigations_id, IF(NEW.fu_date < '1000-01-01', NULL, NEW.fu_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_fumigations_ad$$
CREATE TRIGGER trg_rptc_fumigations_ad AFTER DELETE ON fumigations FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'fumigations' AND rid = OLD.fumigations_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_baf_treatments_ai$$
CREATE TRIGGER trg_rptc_baf_treatments_ai AFTER INSERT ON baf_treatments FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('baf_treatments', NEW.baf_treatments_id, IF(NEW.bt_date < '1000-01-01', NULL, NEW.bt_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_baf_treatments_au$$
CREATE TRIGGER trg_rptc_baf_treatments_au AFTER UPDATE ON baf_treatments FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('baf_treatments', NEW.baf_treatments_id, IF(NEW.bt_date < '1000-01-01', NULL, NEW.bt_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_baf_treatments_ad$$
CREATE TRIGGER trg_rptc_baf_treatments_ad AFTER DELETE ON baf_treatments FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'baf_treatments' AND rid = OLD.baf_treatments_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_transit_ai$$
CREATE TRIGGER trg_rptc_transit_ai AFTER INSERT ON transit FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('transit', NEW.transit_id, IF(NEW.tr_date < '1000-01-01', NULL, NEW.tr_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_transit_au$$
CREATE TRIGGER trg_rptc_transit_au AFTER UPDATE ON transit FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('transit', NEW.transit_id, IF(NEW.tr_date < '1000-01-01', NULL, NEW.tr_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_transit_ad$$
CREATE TRIGGER trg_rptc_transit_ad AFTER DELETE ON transit FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'transit' AND rid = OLD.transit_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_exports_ai$$
CREATE TRIGGER trg_rptc_exports_ai AFTER INSERT ON exports FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('exports', NEW.exports_id, IF(NEW.exp_date < '1000-01-01', NULL, NEW.exp_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_exports_au$$
CREATE TRIGGER trg_rptc_exports_au AFTER UPDATE ON exports FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('exports', NEW.exports_id, IF(NEW.exp_date < '1000-01-01', NULL, NEW.exp_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_exports_ad$$
CREATE TRIGGER trg_rptc_exports_ad AFTER DELETE ON exports FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'exports' AND rid = OLD.exports_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_permit_application_ai$$
CREATE TRIGGER trg_rptc_permit_application_ai AFTER INSERT ON permit_application FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('permit_application', NEW.permit_application_id, IF(NEW.per_date < '1000-01-01', NULL, NEW.per_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_permit_application_au$$
CREATE TRIGGER trg_rptc_permit_application_au AFTER UPDATE ON permit_application FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('permit_application', NEW.permit_application_id, IF(NEW.per_date < '1000-01-01', NULL, NEW.per_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_permit_application_ad$$
CREATE TRIGGER trg_rptc_permit_application_ad AFTER DELETE ON permit_application FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'permit_application' AND rid = OLD.permit_application_id;
END$$

DROP TRIGGER IF EXISTS trg_rptc_aircraft_clearance_ai$$
CREATE TRIGGER trg_rptc_aircraft_clearance_ai AFTER INSERT ON aircraft_clearance FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('aircraft_clearance', NEW.aircraft_clearance_id, IF(NEW.air_date < '1000-01-01', NULL, NEW.air_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_aircraft_clearance_au$$
CREATE TRIGGER trg_rptc_aircraft_clearance_au AFTER UPDATE ON aircraft_clearance FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
    VALUES ('aircraft_clearance', NEW.aircraft_clearance_id, IF(NEW.air_date < '1000-01-01', NULL, NEW.air_date), NEW.station, NEW.enteredby)
    ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);
END$$

DROP TRIGGER IF EXISTS trg_rptc_aircraft_clearance_ad$$
CREATE TRIGGER trg_rptc_aircraft_clearance_ad AFTER DELETE ON aircraft_clearance FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    DELETE FROM rpt_certificate WHERE source = 'aircraft_clearance' AND rid = OLD.aircraft_clearance_id;
END$$


DELIMITER ;
