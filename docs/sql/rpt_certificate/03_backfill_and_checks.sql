-- ============ STEP 3: fill the table with the existing records (delimiter back to  ; ) ============
-- Safe to run more than once. Runs after the triggers exist, so no record is missed in between.

INSERT INTO rpt_certificate (source, rid, cert_date, station, officer)
SELECT 'brdingn' AS source, brdingn_id AS rid, IF(br_date < '1000-01-01', NULL, br_date) AS cert_date, station, enteredby AS officer FROM brdingn
UNION ALL
SELECT 'postbordericn' AS source, postbordericn_id AS rid, IF(po_date < '1000-01-01', NULL, po_date) AS cert_date, station, enteredby AS officer FROM postbordericn
UNION ALL
SELECT 'brdingn_extended' AS source, brdingn_extended_id AS rid, IF(bex_date < '1000-01-01', NULL, bex_date) AS cert_date, station, enteredby AS officer FROM brdingn_extended
UNION ALL
SELECT 'fumigations' AS source, fumigations_id AS rid, IF(fu_date < '1000-01-01', NULL, fu_date) AS cert_date, station, enteredby AS officer FROM fumigations
UNION ALL
SELECT 'baf_treatments' AS source, baf_treatments_id AS rid, IF(bt_date < '1000-01-01', NULL, bt_date) AS cert_date, station, enteredby AS officer FROM baf_treatments
UNION ALL
SELECT 'transit' AS source, transit_id AS rid, IF(tr_date < '1000-01-01', NULL, tr_date) AS cert_date, station, enteredby AS officer FROM transit
UNION ALL
SELECT 'exports' AS source, exports_id AS rid, IF(exp_date < '1000-01-01', NULL, exp_date) AS cert_date, station, enteredby AS officer FROM exports
UNION ALL
SELECT 'permit_application' AS source, permit_application_id AS rid, IF(per_date < '1000-01-01', NULL, per_date) AS cert_date, station, enteredby AS officer FROM permit_application
UNION ALL
SELECT 'aircraft_clearance' AS source, aircraft_clearance_id AS rid, IF(air_date < '1000-01-01', NULL, air_date) AS cert_date, station, enteredby AS officer FROM aircraft_clearance
ON DUPLICATE KEY UPDATE cert_date = VALUES(cert_date), station = VALUES(station), officer = VALUES(officer);


-- ============ STEP 4: check the counts match (every row: in_source = in_rpt) ============
SELECT 'brdingn' AS source, (SELECT COUNT(*) FROM brdingn) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'brdingn') AS in_rpt
UNION ALL
SELECT 'postbordericn' AS source, (SELECT COUNT(*) FROM postbordericn) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'postbordericn') AS in_rpt
UNION ALL
SELECT 'brdingn_extended' AS source, (SELECT COUNT(*) FROM brdingn_extended) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'brdingn_extended') AS in_rpt
UNION ALL
SELECT 'fumigations' AS source, (SELECT COUNT(*) FROM fumigations) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'fumigations') AS in_rpt
UNION ALL
SELECT 'baf_treatments' AS source, (SELECT COUNT(*) FROM baf_treatments) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'baf_treatments') AS in_rpt
UNION ALL
SELECT 'transit' AS source, (SELECT COUNT(*) FROM transit) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'transit') AS in_rpt
UNION ALL
SELECT 'exports' AS source, (SELECT COUNT(*) FROM exports) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'exports') AS in_rpt
UNION ALL
SELECT 'permit_application' AS source, (SELECT COUNT(*) FROM permit_application) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'permit_application') AS in_rpt
UNION ALL
SELECT 'aircraft_clearance' AS source, (SELECT COUNT(*) FROM aircraft_clearance) AS in_source, (SELECT COUNT(*) FROM rpt_certificate WHERE source = 'aircraft_clearance') AS in_rpt;

-- ============ STEP 5: check one month gives the same money as the old way ============
-- New (should take well under a second):
SELECT COUNT(*) AS lines_n, SUM(inv.in_amount) AS total
FROM invoiceview inv
JOIN rpt_certificate c ON c.rid = inv.postbordericn_id
WHERE inv.in_date >= '2026-11-01' AND inv.in_date < '2026-12-01' AND c.station = 'LTK';
-- Old (slow, one time only; the two results must be equal):
SELECT COUNT(*) AS lines_n, SUM(inv.in_amount) AS total
FROM combinedIcfinal2 o
RIGHT JOIN invoiceview inv ON inv.postbordericn_id = o.rid
WHERE inv.in_date >= '2026-11-01' AND inv.in_date < '2026-12-01' AND o.station = 'LTK';

-- ============ STEP 6: after saving one record in the app, it must appear here ============
SELECT * FROM rpt_certificate ORDER BY updated_at DESC LIMIT 5;

-- ============ ROLLBACK (only if ever needed): removes everything this script added ============
-- DROP TABLE rpt_certificate;   and  DROP TRIGGER trg_rptc_<table>_ai / _au / _ad  for each of the 9 tables.
