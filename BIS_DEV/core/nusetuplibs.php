<?php

require_once('nusystemupdatelibs.php');
require_once(dirname(__FILE__). '/../nuconfig.php'); // nuconfig must be loaded before using nubuilder_session_dat

function nuImportNewDB() {

	$t = nuRunQuery("SHOW TABLES");
	while($r = db_fetch_row($t)){
		if($r[0] == 'zzzzsys_object'){return;}
	}
	$file						= __DIR__."/../nubuilder4.sql";
	@$handle					= fopen($file, "r");
	$temp						= "";
	if($handle){
		while(($line = fgets($handle)) !== false){
			if($line[0] != "-" AND $line[0] != "/" AND $line[0] != "\n"){
				$line 			= trim($line);
				$temp 			.= $line;
				if(substr($line, -1) == ";"){
					$temp	= rtrim($temp,';');
					$temp	= str_replace('ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER','', $temp);

					$objList1 = '`information_schema`.`tables`.`TABLE_NAME` AS `zzzzsys_object_list_id` from `information_schema`.`tables` where `information_schema`.`tables`.`TABLE_SCHEMA`';
					$objList2 = 'TABLE_NAME AS zzzzsys_object_list_id from information_schema.tables WHERE TABLE_SCHEMA';
					$temp	= str_replace($objList1, $objList2, $temp);

					nuRunQuery($temp);
					$temp	= "";
				}
			}
		}
	}

	nuImportLanguageFiles();
}

function nuConfigScript() {

	global $nuConfigSettingsFromDB;

	$loadDBConfig = isset($nuConfigSettingsFromDB) && $nuConfigSettingsFromDB == true;
	$code = null;
	$js = null;

	if ($loadDBConfig) {

		$t = nuRunQueryNoDebug("SELECT cfg_setting, cfg_value, cfg_category FROM zzzzsys_config ORDER BY cfg_setting, cfg_order");
		if (db_num_rows($t) > 0) {

			$code = "";
			$js = "";

			while ($r = db_fetch_array($t)) {

				$cat		= $r['cfg_category'];
				$setting	= $r['cfg_setting'];
				$php		= $setting[0] == '$';

				if ($php) {
					$code .= $r['cfg_setting'] . " = " . nuCleanConfigValue($r['cfg_value']) .";\n";
				} else {

					if ($js == "") $code .= "\$nuJSOptions = \"\n";
					$js .= $r['cfg_category']. "['". $r['cfg_setting'] . "']"	. " = " . nuCleanConfigValue($r['cfg_value']) ."; \n";

				}

			}

			$code .= $js."\";\n";

		}

	}

	if (function_exists('nuRenderRedisSetupTab')) {
		$js .= "\n window.nuRedisSetupHtml = " . json_encode(nuRenderRedisSetupTab()) . ";\n";
		$js .= "\n" . nuRenderRedisSetupJS() . "\n";
	}

    return [
        'code' => $code,
        'js' => $js
    ];

}

function nuCleanConfigValue($v) {

    if (in_array(strtolower($v), ["true", "false"])) {
        return strtolower($v);
    } else {
        if (is_numeric($v)) {
            return $v;
        } else {
            return "'". addslashes($v)."'";
        }
    }

}

function nuRenderRedisSetupTab() {
    return <<<'HTML'
<div id="nu-redis-panel" class="nu-redis-container" style="font-family: system-ui, -apple-system, sans-serif; padding: 20px; background: #f8fafc; border-radius: 12px; margin-top: 15px;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 15px;">
        <div>
            <h3 style="margin: 0; color: #1e293b; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
                ⚙️ REDIS HIGH-PERFORMANCE CACHE & SESSION MANAGEMENT STATUS
            </h3>
            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 11px;">Live memory diagnostics, latency benchmark, active sessions, and key metrics</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" onclick="nuFetchRedisDiagnostics()" style="padding: 6px 12px; background: #e2e8f0; color: #1e293b; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer;">🔄 Refresh Metrics</button>
            <button type="button" onclick="nuTestRedisPing()" style="padding: 6px 12px; background: #2563eb; color: #ffffff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer;">⏲️ Test Latency (Benchmark)</button>
        </div>
    </div>

    <!-- Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 15px;">
        <div style="padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">CONNECTION STATUS</div>
            <div id="nu-redis-card-status" style="font-size: 13px; font-weight: 700; margin-top: 4px; color: #16a34a;">🟢 Connected & Operational</div>
            <div id="nu-redis-card-scheme" style="font-size: 10px; color: #94a3b8; font-family: monospace; margin-top: 2px;">Scheme: unix</div>
        </div>
        <div style="padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">REDIS MEMORY & ENGINE</div>
            <div id="nu-redis-card-memory" style="font-size: 13px; font-weight: 700; margin-top: 4px; color: #0f172a;">527.65K</div>
            <div id="nu-redis-card-version" style="font-size: 10px; color: #94a3b8; font-family: monospace; margin-top: 2px;">Version: --</div>
        </div>
        <div style="padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">CACHED APP OBJECTS</div>
            <div id="nu-redis-card-keys" style="font-size: 16px; font-weight: 700; margin-top: 4px; color: #2563eb;">0</div>
            <div style="font-size: 10px; color: #94a3b8; font-family: monospace; margin-top: 2px;">Prefix: nuvis:*</div>
        </div>
        <div style="padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">REDIS ACTIVE SESSIONS</div>
            <div id="nu-redis-card-sessions" style="font-size: 16px; font-weight: 700; margin-top: 4px; color: #16a34a;">0</div>
            <div style="font-size: 10px; color: #94a3b8; font-family: monospace; margin-top: 2px;">Prefix: nuvis:sess:*</div>
        </div>
    </div>

    <!-- Daemon Banner -->
    <div style="padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <div>
            <div style="font-size: 12px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 4px;">💭 A2 Hosting Turbo Max Redis Daemon Initialization</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Socket Path: <code id="nu-redis-socket-display" style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">/home/ictfjcom/.redis/redis.sock</code></div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" onclick="nuTriggerStartRedisDaemon()" style="padding: 8px 12px; background: #b45309; color: #ffffff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer;">⏻ Start A2 Hosting Redis Daemon (~/.redis.on)</button>
            <button type="button" onclick="nuFlushRedisCache()" style="padding: 8px 12px; background: #e11d48; color: #ffffff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer;">🧹 Flush Nuvis Cache</button>
        </div>
    </div>

    <!-- Benchmark Result Box -->
    <div id="nu-redis-benchmark-result" style="display: none; padding: 12px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; margin-bottom: 15px; font-size: 11px; font-family: monospace; color: #1e3a8a;">
        <div id="nu-redis-benchmark-text"></div>
    </div>

    <!-- Keys Table -->
    <div>
        <div style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px;">SAMPLE CACHED KEYS & TIME TO LIVE (TTL)</div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; text-align: left; font-size: 11px; font-family: monospace;">
                <thead>
                    <tr style="background: #f1f5f9; color: #64748b; text-transform: uppercase; font-size: 10px;">
                        <th style="padding: 8px 12px;">CACHE KEY NAME</th>
                        <th style="padding: 8px 12px;">FULL REDIS KEY</th>
                        <th style="padding: 8px 12px; text-align: right;">TTL (TIME TO LIVE)</th>
                    </tr>
                </thead>
                <tbody id="nu-redis-keys-tbody">
                    <tr><td colspan="3" style="padding: 12px; text-align: center; color: #94a3b8; font-family: sans-serif; italic;">Loading cached keys...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

HTML;
}

function nuRenderRedisSetupJS() {
    return <<<'JS'
function nuFetchRedisDiagnostics() {
    fetch('actions/redis_actions.php?action=get_redis_status')
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const socketEl = document.getElementById('nu-redis-socket-display');
            if (socketEl) socketEl.textContent = d.socket_path || '/home/ictfjcom/.redis/redis.sock';

            const statusEl = document.getElementById('nu-redis-card-status');
            if (statusEl) {
                statusEl.innerHTML = d.is_connected
                    ? '🟢 Connected & Operational'
                    : '🟡 Socket Offline (~/.redis.on required)';
            }

            const memEl = document.getElementById('nu-redis-card-memory');
            if (memEl) memEl.textContent = d.used_memory || '--';

            const verEl = document.getElementById('nu-redis-card-version');
            if (verEl) verEl.textContent = 'Version: ' + (d.redis_version || '--');

            const keysEl = document.getElementById('nu-redis-card-keys');
            if (keysEl) keysEl.textContent = d.cached_keys_count || 0;

            const sessEl = document.getElementById('nu-redis-card-sessions');
            if (sessEl) sessEl.textContent = d.active_sessions_count || 0;

            const tbody = document.getElementById('nu-redis-keys-tbody');
            if (tbody) {
                if (d.keys_sample && d.keys_sample.length > 0) {
                    function escapeHtml(str) {
                        if (!str) return '';
                        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    }
                    tbody.innerHTML = d.keys_sample.map(k => `
                        <tr style="border-top: 1px solid #f1f5f9;">
                            <td style="padding: 8px 12px; font-weight: 700; color: #2563eb;">${escapeHtml(k.key)}</td>
                            <td style="padding: 8px 12px; color: #475569;">${escapeHtml(k.full_key)}</td>
                            <td style="padding: 8px 12px; text-align: right; color: #16a34a; font-weight: 700;">${escapeHtml(k.ttl)}</td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="padding: 12px; text-align: center; color: #94a3b8; font-family: sans-serif;">No active keys currently cached under prefix.</td></tr>';
                }
            }
        }
    })
    .catch(err => {
        console.error('Error fetching Redis status:', err);
    });
}

function nuTestRedisPing() {
    const resBox = document.getElementById('nu-redis-benchmark-result');
    const resText = document.getElementById('nu-redis-benchmark-text');
    if (resBox) resBox.style.display = 'block';
    if (resText) resText.innerHTML = 'Running SET/GET/DEL latency benchmark...';

    fetch('actions/redis_actions.php?action=test_redis_ping')
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            resText.innerHTML = '✔ ' + d.message;
            nuFetchRedisDiagnostics();
        } else {
            resText.innerHTML = '❌ Benchmark Failed: ' + (d.error || 'Error');
        }
    })
    .catch(err => {
        if (resText) resText.innerHTML = '❌ Benchmark Failed: Network/Server Error';
        console.error('Error running latency benchmark:', err);
    });
}

function nuTriggerStartRedisDaemon() {
    fetch('actions/redis_actions.php?action=start_redis_daemon')
    .then(r => r.json())
    .then(d => {
        alert(d.message || 'Daemon trigger set.');
        nuFetchRedisDiagnostics();
    })
    .catch(err => {
        alert('Failed to trigger Redis daemon.');
        console.error('Error starting daemon:', err);
    });
}

function nuFlushRedisCache() {
    if (!confirm('Are you sure you want to flush all Nuvis cached objects in Redis?')) return;
    fetch('actions/redis_actions.php?action=flush_cache')
    .then(r => r.json())
    .then(d => {
        alert(d.message || 'Cache flushed.');
        nuFetchRedisDiagnostics();
    })
    .catch(err => {
        alert('Failed to flush cache.');
        console.error('Error flushing cache:', err);
    });
}

function nuInjectRedisSetupTab() {
    if (document.getElementById('nu-redis-tab-btn')) return;

    var refTab = document.querySelector('.nuTab[data-nu-tab-filter]');
    var formFilter = refTab ? refTab.getAttribute('data-nu-form-filter') : 'nusetup';

    var container = refTab ? refTab.parentNode : (document.querySelector('.nuTabTitleColumn') || document.getElementById('nuTabHolder'));
    if (!container) return;

    var tabBtn = document.createElement('div');
    tabBtn.id = 'nu-redis-tab-btn';
    tabBtn.className = 'nuTab nuDragNoSelect';
    tabBtn.innerHTML = 'Redis Cache & Sessions';
    tabBtn.setAttribute('data-nu-tab-filter', 'redis');
    tabBtn.setAttribute('data-nu-form-filter', formFilter);
    tabBtn.style.cssText = 'cursor: pointer; display: inline-block; margin: 0 8px; padding: 2px 6px; font-weight: normal;';

    tabBtn.onclick = function() {
        if (typeof nuSelectTab === 'function') {
            nuSelectTab(this, true);
        }
        if (typeof nuFetchRedisDiagnostics === 'function') {
            nuFetchRedisDiagnostics();
        }
    };

    container.appendChild(tabBtn);

    var panelDiv = document.createElement('div');
    panelDiv.id = 'nu-redis-tab-panel';
    panelDiv.setAttribute('data-nu-tab', 'redis');
    panelDiv.setAttribute('data-nu-form', formFilter);
    panelDiv.style.display = 'none';
    panelDiv.style.cssText = 'position: relative; z-index: 100; background: #f8fafc; border-radius: 12px; margin-top: 15px; border: 1px solid #e2e8f0; clear: both;';
    panelDiv.innerHTML = window.nuRedisSetupHtml || '';

    var formRecordContainer = document.getElementById('nuRECORD') || document.body;
    formRecordContainer.appendChild(panelDiv);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(nuInjectRedisSetupTab, 600);
    });
} else {
    setTimeout(nuInjectRedisSetupTab, 600);
}
JS;
}

?>
