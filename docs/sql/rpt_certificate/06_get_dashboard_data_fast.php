<?php
// ===== get_dashboard_data (Hidden Procedure) =====
// Supports all reports: Finance, Border, Postborder, Exports, M&S, Graphs

$nuhash      = nuHash();
$reporttorun = $nuhash['reporttorun'] ?? 0;
$year        = (int) ($nuhash['year'] ?? date('Y'));
$months      = (string) ($nuhash['months'] ?? date('m'));
$bafstation  = preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($nuhash['bafstationsel'] ?? ''));   //-- comes from the browser and is put into SQL below

if ($year < 2000 || $year > 2100) { $year = (int) date('Y'); }
$lastyear = $year - 1;

// ---------- Month ranges ----------
$monthRanges = [
    'Q1'     => '1,2,3',
    'Q2'     => '4,5,6',
    'Q3'     => '7,8,9',
    'Q4'     => '10,11,12',
    'HALF1'  => '1,2,3,4,5,6',
    'HALF2'  => '7,8,9,10,11,12',
    'YEARLY' => '1,2,3,4,5,6,7,8,9,10,11,12',
    '101'    => '1,2,3'
];

if (array_key_exists($months, $monthRanges)) {
    $months = $monthRanges[$months];
}

//-- Date ranges instead of MONTH(col) IN (...) AND YEAR(col) = ?: lets MariaDB use the index on the date column.
//-- $dc('col') gives the condition for a column, $dateparams the values (the same for every column).
$monthList = [];
foreach (explode(',', $months) as $mm) {
    $mm = (int) $mm;
    if ($mm >= 1 && $mm <= 12) { $monthList[$mm] = $mm; }
}
if (!$monthList) { $monthList[(int) date('n')] = (int) date('n'); }
sort($monthList);

$runs = [];
$start = $prev = null;
foreach ($monthList as $mm) {
    if ($start === null) { $start = $prev = $mm; continue; }
    if ($mm == $prev + 1) { $prev = $mm; continue; }
    $runs[] = [$start, $prev];
    $start = $prev = $mm;
}
$runs[] = [$start, $prev];

$dateparams = [];
foreach ($runs as $run) {
    $dateparams[] = sprintf('%04d-%02d-01', $year, $run[0]);
    $dateparams[] = $run[1] == 12 ? sprintf('%04d-01-01', $year + 1) : sprintf('%04d-%02d-01', $year, $run[1] + 1);
}
$rangeCount = count($runs);

$dc = function ($col) use ($rangeCount) {
    $parts = [];
    for ($i = 0; $i < $rangeCount; $i++) { $parts[] = "($col >= ? AND $col < ?)"; }
    return '(' . implode(' OR ', $parts) . ')';
};

//-- whole-year range used by the comparison charts (this year and last year)
$rangeFrom = sprintf('%04d-01-01', $lastyear);
$rangeTo   = sprintf('%04d-01-01', $year + 1);

// Station filter
$wherestation = $bafstation === '' ? '' : "AND station = '" . $bafstation . "'";

// ---------- Helper: build HTML table ----------
function buildTable(array $data, array $labels): string {
    if (empty($data)) {
        return '<p style="color:#888">No data available for the selected period.</p>';
    }

    $html  = '<table class="nuTable" style="border-collapse:collapse;width:100%;margin-bottom:20px">';
    $html .= '<thead><tr style="background:#f2f2f2">';
    foreach ($labels as $label) {
        $html .= '<th style="border:1px solid #ddd;padding:8px">' . htmlspecialchars($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($data as $row) {
        $html .= '<tr>';
        foreach (array_keys($labels) as $key) {
            $value = $row[$key] ?? '';
            if (is_numeric($value) && !in_array($key, ['code', 'activity', 'station', 'vesseltype', 'actype', 'type', 'purpose', 'trttype', 'commtype', 'per_request_type', 'per_bap_type', 'commodity', 'su__type'])) {
                $value = number_format((float)$value, 2);
            }
            $html .= '<td style="border:1px solid #ddd;padding:8px">' . htmlspecialchars((string) $value) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

// ---------- Helper: single total value ----------
function getTotal(string $sql, array $params): float {
    $t = nuRunQuery($sql, $params);
    $r = db_fetch_row($t);
    return $r ? (float)$r[0] : 0;
}

// ---------- Result structure ----------
$result = [
    'report'    => (int)$reporttorun,
    'title'     => '',
    'sections'  => [],
    'graphdata' => [],
    'cards'     => []          // for KPI cards (Border report etc.)
];

// =========================================================
// CASE 1 – FINANCE REPORT
// =========================================================
if ($reporttorun == 1 || $reporttorun == 0) {

    $result['title'] = 'Finance Report';

    $allrevenuetitle = ['activity' => 'Activity', 'qty' => 'Quantity', 'amount' => 'Amount', 'code' => 'Code'];
    $stationTitle    = ['station' => 'Station', 'amount' => 'Amount'];

    $financesql = "
        SELECT inv.f_activity AS activity, inv.f_code AS code, COALESCE(c.station, 'LTK') AS station,
               SUM(inv.in_qty) AS qty, SUM(inv.in_amount) AS amount
        FROM invoiceview inv
            LEFT JOIN rpt_certificate c ON c.rid = inv.postbordericn_id
        WHERE {$dc('in_date')} $wherestation
        GROUP BY inv.f_activity, inv.f_code, COALESCE(c.station, 'LTK')
    ";
    $t = nuRunQuery($financesql, $dateparams);

    $byActivity    = [];     //-- Detailed revenue
    $byStation     = [];     //-- Revenue by Station
    $totalAmount   = 0;

    while ($r = db_fetch_array($t)) {

        $activity = $r['activity'];
        $qty      = (float) $r['qty'];
        $amount   = (float) $r['amount'];

        if (!isset($byActivity[$activity])) {
            $byActivity[$activity] = ['activity' => $activity, 'qty' => 0, 'amount' => 0, 'code' => $r['code']];
        }
        $byActivity[$activity]['qty']    += $qty;
        $byActivity[$activity]['amount'] += $amount;

        $st = $r['station'];
        $byStation[$st] = ($byStation[$st] ?? 0) + $amount;

        $totalAmount += $amount;
    }

    $rows = array_values($byActivity);

    $stationRows = [];
    $graphData   = [['Station', 'Amount']];
    foreach ($byStation as $st => $amount) {
        $stationRows[] = ['station' => $st, 'amount' => $amount];
        $graphData[]   = [$st, (float) $amount];
    }

    $result['sections']['detailedrevenue']   = buildTable($rows, $allrevenuetitle);
    $result['title']                         = 'Revenue Report ($' . number_format($totalAmount, 2) . ')';
    $result['sections']['revenuebystations'] = buildTable($stationRows, $stationTitle);
    $result['graphdata']                     = $graphData;
}
// =========================================================
// CASE 2 – BORDER REPORT
// =========================================================
elseif ($reporttorun == 2) {

    $result['title'] = 'Border Report';

    // KPI cards
    $result['cards'][] = ['label' => 'Total Vessels',        'value' => getTotal("SELECT COUNT(brdingn_id) FROM brdingn WHERE {$dc('br_date')} $wherestation", $dateparams)];
    $result['cards'][] = ['label' => 'Vessel Interceptions', 'value' => getTotal("SELECT COUNT(brdingn_id) FROM brdingn WHERE {$dc('br_date')} AND br_intcptn = '1' $wherestation", $dateparams)];
    $result['cards'][] = ['label' => 'Animal Bonded',        'value' => getTotal("SELECT COUNT(brdingn_id) FROM brdingn WHERE {$dc('br_date')} AND br_anmlbnd = '1' $wherestation", $dateparams)];
    $result['cards'][] = ['label' => 'Aircraft Arrived',     'value' => getTotal("SELECT COUNT(aircraft_clearance_id) FROM aircraft_clearance WHERE {$dc('air_date')} $wherestation", $dateparams)];

    // Vessel Types
    $vesseltypetitle = ['vesseltype' => 'Vessel Type', 'numvessel' => 'Number of Vessel'];
    $vesseltypesql = "
        SELECT vesseltype.ve_type AS vesseltype, COUNT(brdingn_id) AS numvessel
        FROM brdingn
        JOIN vesseltype ON vesseltype.ve_id = brdingn.br_vtype
        WHERE {$dc('br_date')} $wherestation
        GROUP BY vesseltype.ve_type
    ";
    $t = nuRunQuery($vesseltypesql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['vesseltypetotals'] = buildTable($rows, $vesseltypetitle);

    // Vessel Activities
    $vesselActivityLabels = [
        'fcl' => 'FCL', 'lcl' => 'LCL', 'mt' => 'MT', 'nwvhcls' => 'New Vehicles',
        'usdvhcl' => 'Used Vehicles', 'blkdschge' => 'Bulk Discharge', 'brkblk' => 'Break Bulk',
        'crwtrst' => 'Crew Transit', 'pxtrst' => 'Passenger Transit', 'crwdsmbk' => 'Crew Disembark',
        'pxdsmbk' => 'Passenger Disembark', 'bginsp' => 'Baggage Inspection', 'crwemb' => 'Crew Embark',
        'garbg' => 'Garbage', 'regtransittotal' => 'Regulated Transit Total', 'transittotal' => 'Transit Total'
    ];
    $vesselactivitysql = "
        SELECT SUM(br_fcl) AS fcl, IFNULL(SUM(br_lcl),0) AS lcl, SUM(br_mt) AS mt,
               SUM(br_nwvhcls) AS nwvhcls, SUM(br_usdvhcl) AS usdvhcl, SUM(br_blkdschge) AS blkdschge,
               SUM(br_brkblk) AS brkblk, SUM(br_crwtrst) AS crwtrst, SUM(br_pxtrst) AS pxtrst,
               SUM(br_crwdsmbk) AS crwdsmbk, SUM(br_pxdsmbk) AS pxdsmbk, SUM(br_bginsp) AS bginsp,
               SUM(br_crwemb) AS crwemb, SUM(br_garbg) AS garbg,
               SUM(br_regtransittotal) AS regtransittotal, SUM(br_transittotal) AS transittotal
        FROM brdingn
        WHERE {$dc('br_date')} $wherestation
    ";
    $t = nuRunQuery($vesselactivitysql, $dateparams);
    $row = db_fetch_array($t);
    $actRows = [];
    if ($row) {
        foreach ($vesselActivityLabels as $key => $label) {
            $actRows[] = ['activity' => $label, 'total' => $row[$key] ?? 0];
        }
    }
    $result['sections']['vesselactivitytotals'] = buildTable($actRows, ['activity' => 'Activity', 'total' => 'Total']);

    // Aircraft Types
    $aircrafttypetitle = ['actype' => 'Aircraft Type', 'numaircraft' => 'Number of Aircraft'];
    $aircrafttypesql = "
        SELECT fees_new.f_activity AS actype, COUNT(aircraft_clearance_id) AS numaircraft
        FROM aircraft_clearance
        LEFT JOIN fees_new ON fees_new.f_id = aircraft_clearance.air_type
        WHERE {$dc('air_date')} $wherestation
        GROUP BY fees_new.f_activity
    ";
    $t = nuRunQuery($aircrafttypesql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['aircraftdetaillist'] = buildTable($rows, $aircrafttypetitle);

    // Aircraft Activities
    $aircraftsActivityLabels = [
        'cargoweight' => 'Cargo Weight', 'paxdisemb' => 'Pax Disemb', 'crewdisemb' => 'Crew Disemb',
        'paxtransit' => 'Pax Transit', 'crewintercept' => 'Crew Intercept', 'paxintercept' => 'Pax Intercept',
        'xray' => 'X-ray', 'detained' => 'Detained', 'confiscated' => 'Items in Amnesty bin',
        'amnestybin' => 'Amnesty bin Profiling', 'bagschecked' => 'Bags Checked', 'bagsscreened' => 'Bags Screened'
    ];
    $aircraftsdetailssql = "
        SELECT SUM(air_cargowt) AS cargoweight, SUM(air_paxdisemb) AS paxdisemb, SUM(air_crewdisemb) AS crewdisemb,
               SUM(air_paxtransit) AS paxtransit, SUM(air_crewintercept) AS crewintercept, SUM(air_paxintercept) AS paxintercept,
               SUM(air_xray) AS xray, SUM(air_detained) AS detained, SUM(air_confiscated) AS confiscated,
               SUM(air_amnestybin) AS amnestybin, SUM(air_bagchecked) AS bagschecked, SUM(air_xray) AS bagsscreened
        FROM aircraft_clearance
        WHERE {$dc('air_date')} $wherestation
    ";
    $t = nuRunQuery($aircraftsdetailssql, $dateparams);
    $row = db_fetch_array($t);
    $actRows = [];
    if ($row) {
        foreach ($aircraftsActivityLabels as $key => $label) {
            $actRows[] = ['activity' => $label, 'total' => $row[$key] ?? 0];
        }
    }
    $result['sections']['aircraftactivitytotals'] = buildTable($actRows, ['activity' => 'Activity', 'total' => 'Total']);
}

// =========================================================
// CASE 3 – POSTBORDER REPORT
// =========================================================
elseif ($reporttorun == 3) {

    $result['title'] = 'Postborder Report';

    // KPI cards
    $result['cards'][] = ['label' => 'Total Non-Compliance',     'value' => getTotal("SELECT COUNT(p.po_ic) FROM postbordericn p JOIN commoditypb_sf c ON p.postbordericn_id = c.postbordericn_id WHERE {$dc('p.po_date')} AND c.co_rsn IN (2,3,4,5,6,7,8,9,10,14,16,27,28,29) $wherestation", $dateparams)];
    $result['cards'][] = ['label' => 'Live Interceptions',       'value' => getTotal("SELECT COUNT(p.po_ic) FROM postbordericn p JOIN commoditypb_sf c ON p.postbordericn_id = c.postbordericn_id WHERE {$dc('p.po_date')} AND c.co_rsn IN (2,27) $wherestation", $dateparams)];
    $result['cards'][] = ['label' => 'Other Non-Compliance',     'value' => getTotal("SELECT COUNT(p.po_ic) FROM postbordericn p JOIN commoditypb_sf c ON p.postbordericn_id = c.postbordericn_id WHERE {$dc('p.po_date')} AND c.co_rsn IN (3,4,5,6,7,8,9,10,14,16,28,29,25) $wherestation", $dateparams)];
    $result['cards'][] = ['label' => 'Special Dispensation',     'value' => getTotal("SELECT COUNT(p.po_ic) FROM postbordericn p JOIN commoditypb_sf c ON p.postbordericn_id = c.postbordericn_id WHERE {$dc('p.po_date')} AND c.co_rsn IN (23) $wherestation", $dateparams)];

    // Mail screened
    $potitle = ['totalscreened' => 'Total Screened', 'totalhold' => 'Total Hold'];
    $posql = "
        SELECT SUM(COALESCE(pt_emssc,0))+SUM(COALESCE(pt_amlsc,0))+SUM(COALESCE(pt_rgtdsc,0))+SUM(COALESCE(pt_sufmlsc,0))+SUM(COALESCE(pt_apktsc,0)) AS totalscreened,
               SUM(COALESCE(pt_emshld,0))+SUM(COALESCE(pt_rgtdhld,0))+SUM(COALESCE(pt_amlhld,0))+SUM(COALESCE(pt_sufmlhold,0))+SUM(COALESCE(pt_apkthld,0)) AS totalhold
        FROM postoffice
        WHERE {$dc('pt_date')} $wherestation
    ";
    $t = nuRunQuery($posql, $dateparams);
    $row = db_fetch_array($t);
    $rows = $row ? [['totalscreened' => $row['totalscreened'], 'totalhold' => $row['totalhold']]] : [];
    $result['sections']['mailscreened'] = buildTable($rows, $potitle);

    // Treatments
    $treatmenttitle = ['trttype' => 'Treatment Type', 'purpose' => 'Treatment Purpose', 'numtrt' => 'Number of Treatment'];
    $treatmentsql = "
        SELECT COALESCE(bo_treatment,'Unknown') AS trttype, COALESCE(fumigationpurpose,'Unknown') AS purpose, COUNT(baf_treatments_id) AS numtrt
        FROM baf_treatments
        LEFT JOIN border_treatment ON border_treatment.border_treatment_id = baf_treatments.bt_trt_type
        LEFT JOIN fumpurpose ON fumpurpose.fum_id = baf_treatments.bt_purpose
        WHERE {$dc('bt_date')} $wherestation
        GROUP BY COALESCE(bo_treatment,'Unknown'), COALESCE(fumigationpurpose,'Unknown')
    ";
    $t = nuRunQuery($treatmentsql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['baftreatments'] = buildTable($rows, $treatmenttitle);

    // Fumigations
    $fumigationtitle = ['type' => 'Fumigation Type', 'numbfum' => 'Number of fumigation', 'volume' => 'Volume', 'dose' => 'Dosage'];
    $fumigationsql = "
        SELECT COALESCE(fumigation_type.fumigation_type,'Unknown') AS type,
               COUNT(DISTINCT fumigations.fu_bone_ref) AS numbfum,
               ROUND(SUM(COALESCE(fumigations.fu_volume,0)),2) AS volume,
               SUM(COALESCE(fumigations.fu_dosageapplied,0)) AS dose
        FROM fumigations
        LEFT JOIN fumigation_type ON fumigation_type.fumigation_type_id = fumigations.fu_type
        WHERE {$dc('fu_date')} $wherestation
        GROUP BY COALESCE(fumigation_type.fumigation_type,'Unknown')
    ";
    $t = nuRunQuery($fumigationsql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['fumigationtreatments'] = buildTable($rows, $fumigationtitle);

    // Import commodities
    $importcommoditytitle = ['commtype' => 'Commodity Type', 'totalweight' => 'Weight(tons)', 'iccount' => 'Total IC'];
    $importcommoditysql = "
        SELECT COALESCE(pbcommtype.pb_typecomm,'Unknown') AS commtype,
               ROUND(SUM(COALESCE(postbordercommdet.pc_wt,0))/1000,2) AS totalweight,
               COUNT(DISTINCT postbordericn.postbordericn_id) AS iccount
        FROM postbordericn
        JOIN postbordercommdet ON postbordericn.postbordericn_id = postbordercommdet.postbordericn_id
        JOIN pbcommtype ON pbcommtype.pbcommtype_id = postbordercommdet.pc_cat
        WHERE {$dc('po_date')} $wherestation
        GROUP BY COALESCE(pbcommtype.pb_typecomm,'Unknown')
    ";
    $t = nuRunQuery($importcommoditysql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['importcommodities'] = buildTable($rows, $importcommoditytitle);
}

// =========================================================
// CASE 4 – EXPORTS REPORT
// =========================================================
elseif ($reporttorun == 4) {

    $result['title'] = 'Exports Report';

    $result['cards'][] = ['label' => 'Re-Exports', 'value' => getTotal("SELECT COUNT(p.po_ic) FROM postbordericn p JOIN commoditypb_sf c ON p.postbordericn_id = c.postbordericn_id WHERE {$dc('p.po_date')} AND c.co_rsn IN (36) $wherestation", $dateparams)];

    // Exports
    $exportstitle = ['type' => 'Cargo Type', 'purpose' => 'Commodity Type', 'pcs' => 'QTY', 'expwt' => 'Weight', 'numic' => 'Number of Certificate'];
    $exportssql = "
        SELECT cargotype.carg_type AS type, exp_commodity_type.exp_comm_type AS purpose,
               SUM(exports_sf.esf_number_pieces) AS pcs, SUM(exports_sf.esf_weight) AS expwt,
               COUNT(DISTINCT exports.exports_id) AS numic
        FROM exports
        JOIN exports_sf ON exports.exports_id = exports_sf.exports_id
        JOIN cargotype ON cargotype.carg_id = exports.exp_exporttype
        JOIN exp_commodity_type ON exp_commodity_type.exp_commodity_type_id = exports.exp_commoditytype
        WHERE {$dc('exp_date')} $wherestation
        GROUP BY cargotype.carg_type, exp_commodity_type.exp_comm_type
    ";
    $t = nuRunQuery($exportssql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['exports'] = buildTable($rows, $exportstitle);

    // Licences
    $licencetitle = ['per_request_type' => 'Application Type', 'per_bap_type' => 'BAP Type', 'count' => 'Number of Application'];
    $licencesql = "
        SELECT TRIM(per_request_type) AS per_request_type,
               CASE WHEN LOWER(TRIM(per_bap_type)) = 'export' THEN 'Export'
                    WHEN LOWER(TRIM(per_bap_type)) = 'import' THEN 'Import'
                    ELSE TRIM(per_bap_type) END AS per_bap_type,
               COUNT(permit_application_id) AS count
        FROM permit_application
        WHERE {$dc('per_date')} $wherestation
        GROUP BY TRIM(per_request_type), TRIM(per_bap_type)
        ORDER BY TRIM(per_bap_type) DESC
    ";
    $t = nuRunQuery($licencesql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['licence'] = buildTable($rows, $licencetitle);

    // HTFA Transfer
    $htfatransfertitle = ['commodity' => 'Commodity', 'qty' => 'Quantity(Tons)', 'count' => 'Number of Transfer'];
    $htfatransfersql = "
        SELECT htfa_sf.commodity AS commodity,
               ROUND(SUM(htfa_sf.qty_passed / 1000),2) AS qty,
               COUNT(DISTINCT htfa_transfer.htfa_transfer_id) AS count
        FROM htfa_transfer
        JOIN htfa_sf ON htfa_transfer.htfa_transfer_id = htfa_sf.htfa_transfer_id
        WHERE {$dc('ht_date')} $wherestation
        GROUP BY htfa_sf.commodity
    ";
    $t = nuRunQuery($htfatransfersql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['htfatransfer'] = buildTable($rows, $htfatransfertitle);

    // Farm Registration
    $farmregtitle = ['bqa_count' => 'Total BQA Farm Registered', 'non_bqa_count' => 'Total Non-BQA Farm Registered'];
    $farmregsql = "
        SELECT SUM(CASE WHEN grw_bqa = 1 THEN 1 ELSE 0 END) AS bqa_count,
               SUM(CASE WHEN grw_non_bqa = 1 THEN 1 ELSE 0 END) AS non_bqa_count
        FROM grower_registration
        WHERE {$dc('grw_date')} $wherestation
    ";
    $t = nuRunQuery($farmregsql, $dateparams);
    $row = db_fetch_array($t);
    $rows = $row ? [['bqa_count' => $row['bqa_count'], 'non_bqa_count' => $row['non_bqa_count']]] : [];
    $result['sections']['farmreg'] = buildTable($rows, $farmregtitle);
}

// =========================================================
// CASE 5 – M & S REPORT (Surveillance)
// =========================================================
elseif ($reporttorun == 5) {

    $result['title'] = 'M & S Report';

    $surveillancetitle = ['su__type' => 'Type of Surveillance', 'numsur' => 'Total Surveillance'];
    $surveillancesql = "
        SELECT su__type, COUNT(DISTINCT surveillance_id) AS numsur
        FROM surveillance
        WHERE {$dc('su__date')} $wherestation
        GROUP BY su__type
    ";
    $t = nuRunQuery($surveillancesql, $dateparams);
    $rows = [];
    while ($r = db_fetch_array($t)) $rows[] = $r;
    $result['sections']['surveillance'] = buildTable($rows, $surveillancetitle);
}

// =========================================================
// CASE 6 – GRAPHS / COMPARISON
// =========================================================
// =========================================================
// CASE 6 – ALL COMPARISON / GRAPH REPORTS
// =========================================================
elseif ($reporttorun == 6) {

    $result['title'] = 'Comparison Graphs';
    $result['charts'] = [];   // we will fill this with every chart

    // -------------------------------------------------
    // 1. Revenue comparison (This year vs Last year)
    // -------------------------------------------------
    $revcompchart = "
        SELECT MONTH(in_date) AS mth, YEAR(in_date) AS yr, SUM(in_amount) AS amount
        FROM invoiceview
        WHERE in_date >= '$rangeFrom' AND in_date < '$rangeTo'
        GROUP BY MONTH(in_date), YEAR(in_date)
        ORDER BY yr, mth
    ";
    $t = nuRunQuery($revcompchart);
    $temp = [];
    while ($r = db_fetch_array($t)) {
        $m = (int)$r['mth'];
        $y = (int)$r['yr'];
        if (!isset($temp[$m])) $temp[$m] = [$lastyear => 0, $year => 0];
        $temp[$m][$y] = (float)$r['amount'];
    }
    $revData = [['Month', (string)$lastyear, (string)$year]];
    for ($m = 1; $m <= 12; $m++) {
        $revData[] = [
            date('M', mktime(0, 0, 0, $m, 1)),
            $temp[$m][$lastyear] ?? 0,
            $temp[$m][$year] ?? 0
        ];
    }
    $result['charts']['revenue_comparison'] = $revData;

    // -------------------------------------------------
    // 2. Aircraft comparison (This year vs Last year)
    // -------------------------------------------------
    $aircraftcompchart = "
        SELECT MONTH(air_date) AS mth, YEAR(air_date) AS yr, COUNT(aircraft_clearance_id) AS cnt
        FROM aircraft_clearance
        WHERE air_date >= '$rangeFrom' AND air_date < '$rangeTo'
        GROUP BY MONTH(air_date), YEAR(air_date)
        ORDER BY yr, mth
    ";
    $t = nuRunQuery($aircraftcompchart);
    $temp = [];
    while ($r = db_fetch_array($t)) {
        $m = (int)$r['mth'];
        $y = (int)$r['yr'];
        if (!isset($temp[$m])) $temp[$m] = [$lastyear => 0, $year => 0];
        $temp[$m][$y] = (int)$r['cnt'];
    }
    $airData = [['Month', (string)$lastyear, (string)$year]];
    for ($m = 1; $m <= 12; $m++) {
        $airData[] = [
            date('M', mktime(0, 0, 0, $m, 1)),
            $temp[$m][$lastyear] ?? 0,
            $temp[$m][$year] ?? 0
        ];
    }
    $result['charts']['aircraft_comparison'] = $airData;

    // -------------------------------------------------
    // 3. Vessel comparison (This year vs Last year)
    // -------------------------------------------------
    $vesselcompchart = "
        SELECT MONTH(br_date) AS mth, YEAR(br_date) AS yr, COUNT(brdingn_id) AS cnt
        FROM brdingn
        WHERE br_date >= '$rangeFrom' AND br_date < '$rangeTo'
        GROUP BY MONTH(br_date), YEAR(br_date)
        ORDER BY yr, mth
    ";
    $t = nuRunQuery($vesselcompchart);
    $temp = [];
    while ($r = db_fetch_array($t)) {
        $m = (int)$r['mth'];
        $y = (int)$r['yr'];
        if (!isset($temp[$m])) $temp[$m] = [$lastyear => 0, $year => 0];
        $temp[$m][$y] = (int)$r['cnt'];
    }
    $vesData = [['Month', (string)$lastyear, (string)$year]];
    for ($m = 1; $m <= 12; $m++) {
        $vesData[] = [
            date('M', mktime(0, 0, 0, $m, 1)),
            $temp[$m][$lastyear] ?? 0,
            $temp[$m][$year] ?? 0
        ];
    }
    $result['charts']['vessel_comparison'] = $vesData;

    // -------------------------------------------------
    // 4. Revenue by Station (current period) – Pie
    // -------------------------------------------------
    $stationSql = "
        SELECT COALESCE(c.station, 'LTK') AS station, SUM(in_amount) AS amount
        FROM invoiceview
        LEFT JOIN rpt_certificate c ON c.rid = invoiceview.postbordericn_id
        WHERE {$dc('in_date')} $wherestation
        GROUP BY COALESCE(c.station, 'LTK')
    ";
    $t = nuRunQuery($stationSql, $dateparams);
    $pieData = [['Station', 'Amount']];
    while ($r = db_fetch_array($t)) {
        $pieData[] = [$r['station'], (float)$r['amount']];
    }
    $result['charts']['revenue_by_station'] = $pieData;

    // -------------------------------------------------
    // 5. Top Revenue Activities (current period) – Bar
    // -------------------------------------------------
    $actSql = "
        SELECT f_activity AS activity, SUM(in_amount) AS amount
        FROM invoiceview
        LEFT JOIN rpt_certificate c ON c.rid = invoiceview.postbordericn_id
        WHERE {$dc('in_date')} $wherestation
        GROUP BY f_activity
        ORDER BY amount DESC
        LIMIT 12
    ";
    $t = nuRunQuery($actSql, $dateparams);
    $barData = [['Activity', 'Amount']];
    while ($r = db_fetch_array($t)) {
        $barData[] = [substr($r['activity'], 0, 45), (float)$r['amount']];
    }
    $result['charts']['top_activities'] = $barData;

    // Keep a simple note
    $result['sections']['note'] = '<p style="color:#64748b;font-size:13px">All comparison charts for the selected period.</p>';
}

// ---------- Send data back to JavaScript ----------
$json = json_encode($result);

$js = "
    if (typeof processDashboardData === 'function') {
        processDashboardData($json);
    } else {
        console.error('processDashboardData function is missing');
        alert('processDashboardData function is missing on the form');
    }
";

nuJavascriptCallback($js);