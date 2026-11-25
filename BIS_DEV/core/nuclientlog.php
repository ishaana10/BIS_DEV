<?php

//-- Receives errors the browser got from nuapi.php (sent with navigator.sendBeacon) and writes them to temp/nulog.txt.
//-- Only for logged in users, and at most 30 lines per minute.

session_start(['read_and_close' => true]);

if (empty($_SESSION['nubuilder_session_data']['SESSION_ID'])) {
	http_response_code(403);
	return;
}

require_once('nudatabase.php');

$dir  = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR;
$flag = $dir . 'clientlog_count.txt';
$now  = time();
$c    = explode(':', (string) @file_get_contents($flag));
$n    = (count($c) == 2 && $now - (int) $c[0] < 60) ? (int) $c[1] + 1 : 1;
$t    = ($n == 1) ? $now : (int) $c[0];
@file_put_contents($flag, $t . ':' . $n);

if ($n > 30) {
	http_response_code(204);
	return;
}

$j = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($j)) {
	http_response_code(400);
	return;
}

$clean = function ($v) { return substr(trim(preg_replace('/\s+/', ' ', (string) $v)), 0, 200); };

nuLog(
	'CLIENT',
	'status=' . (int) ($j['status'] ?? 0) . ' call=' . $clean($j['call'] ?? '') . ' form=' . $clean($j['form'] ?? '') . ' attempt=' . (int) ($j['attempt'] ?? 0) . ' retried=' . (int) ($j['retried'] ?? 0),
	gmdate('H:i:s') . ' UTC ms=' . (int) ($j['ms'] ?? 0) . ' browserRunning=' . (int) ($j['inflight'] ?? 0) . ' ' . $clean($j['text'] ?? '') . ' | ' . $clean($j['body'] ?? '')
);

http_response_code(204);
