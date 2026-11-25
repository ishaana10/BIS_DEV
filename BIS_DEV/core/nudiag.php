<?php

/*
	Diagnostics written to temp/nulog.txt (via nuLog) to help find out why requests fail, for example with a 503 from the web server.

	BUSY        - many requests of this site were running at the same moment
	UNFINISHED  - a request started and never finished (the PHP process was killed or timed out: typical for a 503)
	PHPERR      - a PHP warning raised while handling an API call
	CLIENT      - an error the browser received from nuapi.php (see nuclientlog.php)

	Settings in nuconfig.php (all optional):
	$nuConfigLogBusyAt = 8;        log when this many requests run at once (0 = never)
	$nuConfigDiagOff   = true;     switch all of this off
*/

function nuDiagDir() {
	return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'inflight' . DIRECTORY_SEPARATOR;
}

function nuDiagClean($s) {
	return substr(trim(preg_replace('/\s+/', ' ', (string) $s)), 0, 300);
}

function nuDiagStart($callType, $formId) {

	global $nuConfigLogBusyAt;

	$dir = nuDiagDir();
	if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
	if (!is_dir($dir) || !is_writable($dir)) { return; }

	$now = time();
	$marker = $dir . $now . '_' . getmypid() . '_' . mt_rand(1000, 9999) . '.req';
	@file_put_contents($marker, json_encode(['t' => $now, 'call' => nuDiagClean($callType), 'form' => nuDiagClean($formId)]));

	register_shutdown_function(function () use ($marker) { @unlink($marker); });

	$running = 0;
	$stale   = 0;

	foreach ((array) glob($dir . '*.req') as $file) {

		$mtime = @filemtime($file);
		if ($mtime === false) { continue; }		//-- the request finished and removed its marker while we were counting

		$age = $now - $mtime;

		if ($age > 180) {		//-- started more than 3 minutes ago and never removed: the process died
			if ($stale++ < 5) {
				$info = json_decode((string) @file_get_contents($file), true);
				nuLog('UNFINISHED', 'call=' . nuObjKey($info, 'call') . ' form=' . nuObjKey($info, 'form'), 'started ' . gmdate('H:i:s', (int) nuObjKey($info, 't', 0)) . ' UTC');
			}
			@unlink($file);
		} else {
			$running++;
		}

	}

	$busyAt = isset($nuConfigLogBusyAt) ? (int) $nuConfigLogBusyAt : 8;
	if ($busyAt > 0 && $running >= $busyAt) {

		$flag = $dir . 'lastbusy.txt';		//-- at most one BUSY line every 10 seconds
		if (!is_file($flag) || $now - (int) @filemtime($flag) >= 10) {
			@touch($flag);
			nuLog('BUSY', "running=$running", 'call=' . nuDiagClean($callType) . ' form=' . nuDiagClean($formId) . ' ' . gmdate('H:i:s') . ' UTC');
		}

	}

}

function nuDiagInstallErrorHandler() {

	set_error_handler(function ($no, $str, $file, $line) {

		static $count = 0;

		if ((error_reporting() & $no) && ($no & (E_WARNING | E_USER_WARNING | E_RECOVERABLE_ERROR | E_USER_ERROR)) && $count++ < 10) {
			nuLog('PHPERR', nuDiagClean($str), basename($file) . ':' . $line . ' ' . gmdate('H:i:s') . ' UTC');
		}

		return false;			//-- normal PHP error handling continues

	});

}
