<?php
//-- In the Finance Report procedure, replace the whole $revsql = "..."; block (the one that uses combinedIcfinal2 RIGHT JOIN invoiceview) with this.
//-- Needs the rpt_certificate table (docs/sql/rpt_certificate). LEFT JOIN from the invoice side gives the same rows as the old RIGHT JOIN:
//-- with a station filter, unmatched rows are dropped by the station condition; without one they are kept, as before.
$revsql = "
SELECT
    c.cert_date AS IcDate, c.station, inv.in_qty, inv.in_amount, inv.f_code, inv.f_activity
FROM
    invoiceview inv
    LEFT JOIN rpt_certificate c ON c.rid = inv.postbordericn_id
WHERE in_date >= ? AND in_date < ? $wherestation
";
