<?php
/*
	One-off diagnostic for the slow Finance Report (combinedIcfinal2 RIGHT JOIN invoiceview).
	Read-only: SHOW CREATE VIEW, SHOW INDEX, EXPLAIN and two COUNT(*) queries capped at 20 seconds.
	Upload to BIS_DEV/core/, log in to the app as globeadmin in the same browser, open  .../core/zz_viewdiag.php
	and send me the text of the page. DELETE THIS FILE afterwards.
*/

require_once('nuchoosesetup.php');

header('Content-Type: text/plain; charset=utf-8');

$sid = $_SESSION['nubuilder_session_data']['SESSION_ID'] ?? '';
$ok  = false;
if ($sid != '') {
	$row = nuRunQuery("SELECT sss_access FROM zzzzsys_session WHERE zzzzsys_session_id = ?", [$sid])->fetch(PDO::FETCH_NUM);
	$j   = $row ? json_decode($row[0], true) : null;
	$ok  = $j && ($j['session']['global_access'] ?? '') == '1';
}
if (!$ok) { http_response_code(403); die("Log in as globeadmin in this browser first, then reload this page.\n"); }

function q($sql, $params = []) {
	global $nuDB;
	try {
		$st = $nuDB->prepare($sql);
		$st->execute($params);
		return $st;
	} catch (Exception $e) {
		echo "   ERROR: " . $e->getMessage() . "\n";
		return null;
	}
}

function show($title) { echo "\n==== $title ====\n"; }

show('Server');
$v = q('SELECT VERSION()'); if ($v) echo $v->fetchColumn() . "\n";

$defs = [];
foreach (['invoiceview', 'combinedIcfinal2'] as $view) {
	show("SHOW CREATE VIEW $view");
	$st = q("SHOW CREATE VIEW `$view`");
	if ($st && ($r = $st->fetch(PDO::FETCH_NUM))) {
		$defs[$view] = $r[1];
		echo preg_replace('/\s+/', ' ', $r[1]) . "\n";
	}
}

//-- every table or view the two views read from
$names = [];
foreach ($defs as $d) {
	if (preg_match_all('/`[^`]+`\.`([^`]+)`/', $d, $m)) { foreach ($m[1] as $n) { $names[$n] = true; } }
}
unset($names['invoiceview'], $names['combinedIcfinal2']);

show('Tables and views used by the two views');
foreach (array_keys($names) as $n) {
	$info = q("SELECT TABLE_TYPE, ENGINE, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$n]);
	$i = $info ? $info->fetch(PDO::FETCH_NUM) : null;
	echo "\n-- $n : " . ($i ? "type={$i[0]} engine={$i[1]} rows~{$i[2]}" : 'not found') . "\n";
	if ($i && $i[0] == 'BASE TABLE') {
		$idx = q("SHOW INDEX FROM `$n`");
		$list = [];
		if ($idx) { foreach ($idx->fetchAll(PDO::FETCH_ASSOC) as $x) { $list[$x['Key_name']][] = $x['Column_name']; } }
		foreach ($list as $k => $cols) { echo "   index $k (" . implode(', ', $cols) . ")\n"; }
		if (!$list) echo "   (no indexes)\n";
	}
}

$from = '2026-11-01'; $to = '2026-12-01';
$queries = [
	'Finance Report' => "SELECT IcDate, station,in_qty, in_amount ,f_code,f_activity FROM combinedIcfinal2 RIGHT JOIN invoiceview ON invoiceview.postbordericn_id = combinedIcfinal2.rid WHERE in_date >= '$from' AND in_date < '$to' AND station = 'LTK'",
	'Certificates per officer' => "SELECT officer, COUNT(DISTINCT rid) AS numberofcert FROM combinedIcfinal2 RIGHT JOIN invoiceview ON invoiceview.postbordericn_id = combinedIcfinal2.rid WHERE in_date >= '$from' AND in_date < '$to' AND station = 'LTK' GROUP BY officer",
];
foreach ($queries as $title => $sql) {
	show("EXPLAIN: $title");
	$st = q('EXPLAIN ' . $sql);
	if ($st) { foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { echo implode(' | ', array_map(function ($v) { return $v === null ? 'NULL' : $v; }, $r)) . "\n"; } }
}

show('Timed counts (stopped after 20 seconds)');
q('SET SESSION max_statement_time = 20');	//-- MariaDB
$tests = [
	"invoiceview, November 2026" => "SELECT COUNT(*) FROM invoiceview WHERE in_date >= '$from' AND in_date < '$to'",
	"invoiceview, all rows" => "SELECT COUNT(*) FROM invoiceview",
	"combinedIcfinal2, all rows" => "SELECT COUNT(*) FROM combinedIcfinal2",
];
foreach ($tests as $label => $sql) {
	$t = microtime(true);
	$st = q($sql);
	$n = $st ? $st->fetchColumn() : 'failed / timed out';
	echo str_pad($label, 32) . ' ' . str_pad((string) $n, 12) . round(microtime(true) - $t, 2) . " s\n";
}
echo "\nDone. Delete this file from the server.\n";
