<?php
/**
 * Action: Redis Diagnostics & Cache Management API Handler (AJAX) for BIS_DEV
 */

if (file_exists(__DIR__ . '/../nuconfig.php')) {
    require_once __DIR__ . '/../nuconfig.php';
} else {
    require_once __DIR__ . '/../nuconfig-sample.php';
}

require_once __DIR__ . '/../core/Cache.php';

header('Content-Type: application/json');

// Session authorization check for BIS_DEV
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isGlobeAdmin = $_SESSION['nubuilder_session_data']['isGlobeadmin'] ?? false;
$isAuthUser   = !empty($_SESSION['nubuilder_session_data']['SESSION_ID']);

if (!$isGlobeAdmin && !$isAuthUser) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: Authentication required']);
    exit();
}

$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($jsonInput['action'] ?? ($_POST['action'] ?? ''));

try {
    $cache = \NuCache::getInstance();
    global $nuConfigRedis;
    $cfg = $nuConfigRedis ?? [];
    $socketPath = $cfg['path'] ?? ((getenv('HOME') ?: '') . '/.redis/redis.sock');
    $socketExists = file_exists($socketPath);

    if ($action === 'get_redis_status') {
        $homeDir = getenv('HOME');
        if ($homeDir) {
            @touch($homeDir . '/.redis.on');
        }

        $socketExists = file_exists($socketPath);
        $isConnected = $cache->isEnabled();
        $client = $cache->client();

        $redisVersion = 'N/A';
        $usedMemory = 'N/A';
        $connectedClients = 0;
        $cachedKeysCount = 0;
        $activeSessionsCount = 0;
        $keysList = [];

        if ($isConnected && $client) {
            try {
                $info = $client->info();
                $redisVersion = $info['redis_version'] ?? 'Unknown';
                $usedMemory = $info['used_memory_human'] ?? 'Unknown';
                $connectedClients = (int)($info['connected_clients'] ?? 1);

                $prefix = $cfg['prefix'] ?? 'nuvis:';
                $allKeys = $client->keys($prefix . '*');

                if (is_array($allKeys)) {
                    $cachedKeysCount = count($allKeys);
                    $sessionKeys = array_filter($allKeys, fn($k) => str_contains($k, 'sess:'));
                    $activeSessionsCount = count($sessionKeys);

                    foreach (array_slice($allKeys, 0, 15) as $k) {
                        $ttl = $client->ttl($k);
                        $shortKey = str_replace($prefix, '', $k);
                        $keysList[] = [
                            'key' => $shortKey,
                            'full_key' => $k,
                            'ttl' => $ttl > 0 ? $ttl . 's' : ($ttl === -1 ? 'No expiry' : 'Expired')
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[Redis Status Action] Error: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'redis_extension' => extension_loaded('redis'),
            'config_enabled' => !empty($cfg['enabled']),
            'is_connected' => $isConnected,
            'scheme' => $cfg['scheme'] ?? 'unix',
            'socket_path' => $socketPath,
            'socket_exists' => $socketExists,
            'redis_version' => $redisVersion,
            'used_memory' => $usedMemory,
            'connected_clients' => $connectedClients,
            'cached_keys_count' => $cachedKeysCount,
            'active_sessions_count' => $activeSessionsCount,
            'keys_sample' => $keysList,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }

    if ($action === 'test_redis_ping') {
        if (!$cache->isEnabled()) {
            echo json_encode([
                'success' => false,
                'error' => 'Redis is not connected or daemon is offline.'
            ]);
            exit();
        }

        $start = microtime(true);
        $testKey = 'ping_test_' . uniqid();
        $testVal = 'OK_' . microtime(true);

        $cache->set($testKey, $testVal, 10);
        $retrieved = $cache->get($testKey);
        $cache->delete($testKey);

        $latencyMs = round((microtime(true) - $start) * 1000, 2);

        if ($retrieved === $testVal) {
            echo json_encode([
                'success' => true,
                'latency_ms' => $latencyMs,
                'message' => "SET/GET/DEL benchmark completed in {$latencyMs} ms. Cache reads and writes are 100% operational!"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Benchmark read mismatch or key storage failure.'
            ]);
        }
        exit();
    }

    if ($action === 'flush_cache') {
        $flushed = $cache->flush();
        echo json_encode([
            'success' => $flushed,
            'message' => $flushed ? 'All application cache keys under prefix successfully flushed.' : 'Cache flush attempted.'
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
