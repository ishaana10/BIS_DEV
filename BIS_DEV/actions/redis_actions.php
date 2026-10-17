<?php
/**
 * Action: Redis Diagnostics & Cache Management API Handler (AJAX) for BIS_DEV
 */

// Buffer all output to prevent PHP notices, warnings, or DB error messages from corrupting JSON response
ob_start();

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (file_exists(__DIR__ . '/../nuconfig.php')) {
    require_once __DIR__ . '/../nuconfig.php';
} else {
    require_once __DIR__ . '/../nuconfig-sample.php';
}

require_once __DIR__ . '/../core/Cache.php';

// Helper function to send clean JSON response
function sendJsonResponse(array $data, int $statusCode = 200): void {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

try {
    // Session authorization check for BIS_DEV
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $sessionData  = $_SESSION['nubuilder_session_data'] ?? [];
    $isGlobeAdmin = !empty($sessionData['isGlobeadmin']);
    $isAuthUser   = !empty($sessionData['SESSION_ID'])
                 || !empty($sessionData['user_id'])
                 || !empty($_SESSION['authenticated'])
                 || !empty($sessionData);

    if (!$isGlobeAdmin && !$isAuthUser) {
        sendJsonResponse(['success' => false, 'error' => 'Access denied: Globeadmin or authenticated session required'], 403);
    }

    $jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? ($jsonInput['action'] ?? ($_POST['action'] ?? ''));

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

        sendJsonResponse([
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
    }

    if ($action === 'test_redis_ping') {
        if (!$cache->isEnabled()) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Redis is not connected or daemon is offline.'
            ]);
        }

        $start = microtime(true);
        $testKey = 'ping_test_' . uniqid();
        $testVal = 'OK_' . microtime(true);

        $cache->set($testKey, $testVal, 10);
        $retrieved = $cache->get($testKey);
        $cache->delete($testKey);

        $latencyMs = round((microtime(true) - $start) * 1000, 2);

        if ($retrieved === $testVal) {
            sendJsonResponse([
                'success' => true,
                'latency_ms' => $latencyMs,
                'message' => "SET/GET/DEL benchmark completed in {$latencyMs} ms. Cache reads and writes are 100% operational!"
            ]);
        } else {
            sendJsonResponse([
                'success' => false,
                'error' => 'Benchmark read mismatch or key storage failure.'
            ]);
        }
    }

    if ($action === 'start_redis_daemon') {
        $homeDir = getenv('HOME');
        if ($homeDir) {
            @touch($homeDir . '/.redis.on');
        }

        \NuCache::resetInstance();
        $cache = \NuCache::getInstance();

        $exists = file_exists($socketPath);
        $isConnected = $cache->isEnabled();

        sendJsonResponse([
            'success' => true,
            'socket_exists' => $exists,
            'is_connected' => $isConnected,
            'message' => 'Created ~/.redis.on flag on disk. A2 Hosting background process will initialize the Redis daemon.'
        ]);
    }

    if ($action === 'flush_cache') {
        $flushed = $cache->flush();
        sendJsonResponse([
            'success' => $flushed,
            'message' => $flushed ? 'All application cache keys under prefix successfully flushed.' : 'Cache flush attempted.'
        ]);
    }

    sendJsonResponse(['success' => false, 'error' => 'Invalid action specified']);
} catch (\Throwable $e) {
    sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
