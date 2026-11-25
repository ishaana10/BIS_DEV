/*
 * Sends browser errors to temp/nulog.txt (through core/nuclientlog.php) so they can be read next to the server errors:
 * uncaught errors, unhandled promise rejections, console.error / console.warn, and files that failed to load (script, css, image, iframe).
 * Loaded first, before jQuery, so nothing that happens later is missed. It never throws and never changes what the page does.
 */
(function () {
	'use strict';
	if (window.nuClientErrorsInstalled) return;
	window.nuClientErrorsInstalled = true;

	var script = document.currentScript;
	var endpoint = script && script.src ? script.src.replace(/[^\/?#]*(\?.*)?$/, '') + 'nuclientlog.php' : 'core/nuclientlog.php';
	var queue = [], seen = {}, distinct = 0, timer = null, started = Date.now();
	var MAX_DISTINCT = 25, MAX_LEN = 600;

	function str(v) {
		try {
			if (v instanceof Error) return (v.name + ': ' + v.message + ' ' + (v.stack || '')).replace(/\s+/g, ' ');
			if (typeof v === 'string') return v;
			if (v && v.nodeType === 1) return '<' + v.tagName.toLowerCase() + (v.id ? '#' + v.id : '') + '>';
			return JSON.stringify(v);
		} catch (e) {
			return String(v);
		}
	}

	function context() {
		var c = { t: Math.round((Date.now() - started) / 100) / 10, url: location.pathname + location.search.substr(0, 60), frame: window !== window.top ? 1 : 0, ready: document.readyState };
		try { c.jq = typeof window.jQuery === 'function' ? window.jQuery.fn.jquery : 'none'; } catch (e) {}
		try { if (window.nuFORM && window.nuFORM.form_id) c.form = window.nuFORM.form_id + (window.nuFORM.record_id ? '/' + window.nuFORM.record_id : ''); } catch (e) {}
		return c;
	}

	function flush() {
		timer = null;
		if (!queue.length) return;
		var body = JSON.stringify({ type: 'js', ua: navigator.userAgent.substr(0, 90), items: queue });
		queue = [];
		try {
			if (!(navigator.sendBeacon && navigator.sendBeacon(endpoint, body))) {
				var x = new XMLHttpRequest();
				x.open('POST', endpoint, true);
				x.send(body);
			}
		} catch (e) {}
	}

	function log(kind, message, where) {
		try {
			message = String(message).replace(/\s+/g, ' ').substr(0, MAX_LEN);
			if (message.indexOf('nuclientlog.php') !== -1) return;
			var key = kind + '|' + message + '|' + (where || '');
			if (seen[key]) { seen[key].n++; return; }
			if (distinct >= MAX_DISTINCT) return;
			distinct++;
			var item = { kind: kind, msg: message, where: where || '', n: 1, c: context() };
			seen[key] = item;
			queue.push(item);
			if (!timer) timer = setTimeout(flush, 1500);
		} catch (e) {}
	}

	window.addEventListener('error', function (e) {
		try {
			var t = e.target;
			if (t && t !== window && t.nodeType === 1) {
				log('LOADFAIL', '<' + t.tagName.toLowerCase() + '> ' + (t.src || t.href || t.currentSrc || ''), '');
				return;
			}
			log('ERROR', e.message || str(e.error), (e.filename || '') + ':' + (e.lineno || 0) + ':' + (e.colno || 0) + (e.error && e.error.stack ? ' | ' + str(e.error.stack).substr(0, 300) : ''));
		} catch (x) {}
	}, true);

	window.addEventListener('unhandledrejection', function (e) {
		log('REJECTION', str(e.reason), '');
	});

	['error', 'warn'].forEach(function (level) {
		var original = console[level];
		if (typeof original !== 'function') return;
		console[level] = function () {
			try {
				var parts = [];
				for (var i = 0; i < arguments.length; i++) parts.push(str(arguments[i]));
				log('CONSOLE.' + level.toUpperCase(), parts.join(' '), '');
			} catch (e) {}
			return original.apply(this, arguments);
		};
	});

	window.addEventListener('pagehide', flush);
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flush(); });
	window.nuClientErrorsFlush = flush;
})();
