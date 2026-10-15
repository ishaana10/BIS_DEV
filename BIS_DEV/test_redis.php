<?php
/**
 * Test Redis Diagnostics & Cache Management for BIS_DEV
 */

if (file_exists(__DIR__ . '/nuconfig.php')) {
    require_once __DIR__ . '/nuconfig.php';
} else {
    require_once __DIR__ . '/nuconfig-sample.php';
}

require_once __DIR__ . '/core/Cache.php';

echo "--- BIS_DEV Redis Diagnostic Test ---\n";

echo "PHP Redis Extension Loaded: " . (extension_loaded('redis') ? "YES" : "NO") . "\n";

$cache = NuCache::getInstance();
echo "NuCache Enabled: " . ($cache->isEnabled() ? "YES" : "NO") . "\n";

if ($cache->isEnabled()) {
    $testKey = "test_key_" . time();
    $testVal = ["status" => "ok", "time" => time()];

    $setOk = $cache->set($testKey, $testVal, 60);
    echo "Cache SET test: " . ($setOk ? "SUCCESS" : "FAILED") . "\n";

    $getVal = $cache->get($testKey);
    echo "Cache GET test: " . (is_array($getVal) && $getVal['status'] === 'ok' ? "SUCCESS" : "FAILED") . "\n";

    $delOk = $cache->delete($testKey);
    echo "Cache DEL test: " . ($delOk ? "SUCCESS" : "FAILED") . "\n";
} else {
    echo "Redis is currently disabled or unreachable in configuration/environment.\n";
}

echo "--- Test Completed ---\n";
