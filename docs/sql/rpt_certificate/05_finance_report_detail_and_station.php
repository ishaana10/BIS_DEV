<?php
//-- Replace the whole  if ($reporttorun == 1 || $reporttorun == 0) { ... }  block with this.
//-- One query instead of two (both read the same invoice rows), date range instead of MONTH()/YEAR(), and the new rpt_certificate table.
//-- Needs $dateparams = [dateFrom, dateTo] (already set earlier in the procedure) and $wherestation.
if ($reporttorun == 1 || $reporttorun == 0) {

    $result['title'] = 'Finance Report';

    $allrevenuetitle = ['activity' => 'Activity', 'qty' => 'Quantity', 'amount' => 'Amount', 'code' => 'Code'];
    $stationTitle    = ['station' => 'Station', 'amount' => 'Amount'];

    $financesql = "
        SELECT inv.f_activity AS activity, inv.f_code AS code, COALESCE(c.station, 'LTK') AS station,
               SUM(inv.in_qty) AS qty, SUM(inv.in_amount) AS amount
        FROM invoiceview inv
            LEFT JOIN rpt_certificate c ON c.rid = inv.postbordericn_id
        WHERE in_date >= ? AND in_date < ? $wherestation
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
