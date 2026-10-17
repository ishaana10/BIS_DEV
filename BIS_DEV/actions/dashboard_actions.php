<?php
/**
 * Dashboard Actions API Endpoint
 * Handles customizable low-code dashboard layouts and lazy-loaded widget metrics.
 * Compatible with PHP 7.3+
 */

ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', '0');

session_start();

function sendDashboardJson(array $response) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

if (empty($_SESSION['nubuilder_session_data']) && empty($_SESSION['nu_session_id'])) {
    sendDashboardJson(['status' => 'error', 'message' => 'Unauthorized']);
}

$setupFile = __DIR__ . '/../core/nuchoosesetup.php';
if (file_exists($setupFile)) {
    require_once $setupFile;
}

if (file_exists(__DIR__ . '/../core/Cache.php')) {
    require_once __DIR__ . '/../core/Cache.php';
}

header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$userId = isset($_SESSION['nubuilder_session_data']['USER_ID']) ? $_SESSION['nubuilder_session_data']['USER_ID'] : (isset($_SESSION['nu_session_id']) ? $_SESSION['nu_session_id'] : 'guest');

$cache = NuCache::getInstance();

switch ($action) {
    case 'get_layout':
        $layoutKey = "dashboard:layout:{$userId}";
        $layout = $cache->get($layoutKey);

        if (!$layout) {
            // Default low-code widget layout
            $layout = [
                'widgets' => [
                    [
                        'id' => 'w_kpi_users',
                        'type' => 'kpi',
                        'title' => 'Active Users',
                        'width' => 'col-3',
                        'config' => ['metric' => 'users', 'color' => '#2563eb', 'icon' => 'fa-users']
                    ],
                    [
                        'id' => 'w_kpi_forms',
                        'type' => 'kpi',
                        'title' => 'Configured Forms',
                        'width' => 'col-3',
                        'config' => ['metric' => 'forms', 'color' => '#10b981', 'icon' => 'fa-th-list']
                    ],
                    [
                        'id' => 'w_kpi_records',
                        'type' => 'kpi',
                        'title' => 'Total Objects',
                        'width' => 'col-3',
                        'config' => ['metric' => 'objects', 'color' => '#8b5cf6', 'icon' => 'fa-cube']
                    ],
                    [
                        'id' => 'w_kpi_redis',
                        'type' => 'kpi',
                        'title' => 'Redis Memory',
                        'width' => 'col-3',
                        'config' => ['metric' => 'redis', 'color' => '#f59e0b', 'icon' => 'fa-database']
                    ],
                    [
                        'id' => 'w_chart_activity',
                        'type' => 'chart',
                        'title' => 'System Activity Overview',
                        'width' => 'col-8',
                        'config' => ['chartType' => 'bar']
                    ],
                    [
                        'id' => 'w_shortcuts',
                        'type' => 'shortcuts',
                        'title' => 'Quick Actions',
                        'width' => 'col-4',
                        'config' => []
                    ],
                    [
                        'id' => 'w_recent_forms',
                        'type' => 'table',
                        'title' => 'Recently Modified Forms',
                        'width' => 'col-12',
                        'config' => []
                    ]
                ]
            ];
            $cache->set($layoutKey, $layout, 86400);
        }

        sendDashboardJson(['status' => 'success', 'layout' => $layout]);
        break;

    case 'save_layout':
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        $layout = isset($data['layout']) ? $data['layout'] : (isset($_POST['layout']) ? $_POST['layout'] : null);

        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }

        if (!$layout || !isset($layout['widgets'])) {
            sendDashboardJson(['status' => 'error', 'message' => 'Invalid layout payload']);
        }

        $layoutKey = "dashboard:layout:{$userId}";
        $cache->set($layoutKey, $layout, 86400);

        sendDashboardJson(['status' => 'success', 'message' => 'Dashboard layout saved successfully']);
        break;

    case 'get_widget_data':
        $widgetId = isset($_GET['widget_id']) ? $_GET['widget_id'] : (isset($_POST['widget_id']) ? $_POST['widget_id'] : '');
        $widgetType = isset($_GET['widget_type']) ? $_GET['widget_type'] : (isset($_POST['widget_type']) ? $_POST['widget_type'] : '');

        $cacheKey = "dashboard:widget:data:{$widgetId}";
        $cachedData = $cache->get($cacheKey);
        if ($cachedData) {
            sendDashboardJson(['status' => 'success', 'data' => $cachedData, 'cached' => true]);
        }

        $data = [];

        if ($widgetType === 'kpi') {
            $metric = isset($_GET['metric']) ? $_GET['metric'] : 'users';
            if ($metric === 'users') {
                $count = 1;
                if (function_exists('nuRunQuery')) {
                    $res = @nuRunQuery("SELECT COUNT(*) AS total FROM zzzzsys_user");
                    if ($res && $r = db_fetch_object($res)) { $count = (int)$r->total; }
                }
                $data = ['value' => $count, 'change' => '+12%', 'subtext' => 'Registered System Users'];
            } elseif ($metric === 'forms') {
                $count = 0;
                if (function_exists('nuRunQuery')) {
                    $res = @nuRunQuery("SELECT COUNT(*) AS total FROM zzzzsys_form");
                    if ($res && $r = db_fetch_object($res)) { $count = (int)$r->total; }
                }
                $data = ['value' => $count, 'change' => '+5', 'subtext' => 'Active System Forms'];
            } elseif ($metric === 'objects') {
                $count = 0;
                if (function_exists('nuRunQuery')) {
                    $res = @nuRunQuery("SELECT COUNT(*) AS total FROM zzzzsys_object");
                    if ($res && $r = db_fetch_object($res)) { $count = (int)$r->total; }
                }
                $data = ['value' => $count, 'change' => '+8%', 'subtext' => 'UI Controls & Fields'];
            } elseif ($metric === 'redis') {
                $mem = '0 KB';
                if ($cache->isEnabled() && ($client = $cache->client())) {
                    $info = @$client->info('memory');
                    $mem = isset($info['used_memory_human']) ? $info['used_memory_human'] : 'Active';
                }
                $data = ['value' => $mem, 'change' => 'Cached', 'subtext' => 'Redis Memory Usage'];
            }
        } elseif ($widgetType === 'chart') {
            $data = [
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'series' => [
                    ['name' => 'Form Loads', 'data' => [120, 210, 180, 310, 290, 150, 90]],
                    ['name' => 'Cache Hits', 'data' => [450, 890, 720, 1100, 980, 510, 320]]
                ]
            ];
        } elseif ($widgetType === 'table') {
            $forms = [];
            if (function_exists('nuRunQuery')) {
                $res = @nuRunQuery("SELECT zzzzsys_form_id, sfo_code, sfo_description FROM zzzzsys_form ORDER BY zzzzsys_form_id DESC LIMIT 5");
                if ($res) {
                    while ($r = db_fetch_object($res)) {
                        $forms[] = [
                            'id' => $r->zzzzsys_form_id,
                            'code' => $r->sfo_code,
                            'description' => $r->sfo_description
                        ];
                    }
                }
            }
            if (empty($forms)) {
                $forms = [
                    ['id' => 'nuuser', 'code' => 'Users', 'description' => 'User Management Form'],
                    ['id' => 'nusetup', 'code' => 'Setup', 'description' => 'System Setup & Config']
                ];
            }
            $data = ['rows' => $forms];
        } elseif ($widgetType === 'shortcuts') {
            $data = [
                'items' => [
                    ['title' => 'System Setup', 'icon' => 'fa-cog', 'action' => 'nuOpenSetup()'],
                    ['title' => 'Form Builder', 'icon' => 'fa-file-text-o', 'action' => "nuSelectForm('nuform')"],
                    ['title' => 'User Management', 'icon' => 'fa-users', 'action' => "nuSelectForm('nuuser')"],
                    ['title' => 'Flush Redis Cache', 'icon' => 'fa-bolt', 'action' => 'nuFlushRedisCache()']
                ]
            ];
        }

        $cache->set($cacheKey, $data, 30);
        sendDashboardJson(['status' => 'success', 'data' => $data, 'cached' => false]);
        break;

    case 'get_nav_items':
        $userRole = isset($_SESSION['nubuilder_session_data']['sal_code']) ? $_SESSION['nubuilder_session_data']['sal_code'] : (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Admin');

        $cacheKey = "menu:user:{$userId}:{$userRole}";
        $cachedNav = $cache->get($cacheKey);
        if ($cachedNav) {
            sendDashboardJson(['status' => 'success', 'role' => $userRole, 'items' => $cachedNav, 'cached' => true]);
        }

        $navItems = [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-dashboard', 'action' => 'nuOpenDashboard()', 'is_home' => false]
        ];

        if (function_exists('nuRunQuery')) {
            // Get Assigned Home Form for Access Level
            $homeFormId = null;
            $homeSql = "SELECT sal_code, sal_zzzzsys_form_id FROM zzzzsys_user JOIN zzzzsys_access ON zzzzsys_access_id = sus_zzzzsys_access_id WHERE zzzzsys_user_id = ?";
            $homeStmt = @nuRunQuery($homeSql, [$userId]);
            if ($homeStmt && $rHome = db_fetch_object($homeStmt)) {
                $homeFormId = $rHome->sal_zzzzsys_form_id;
                if (!empty($rHome->sal_code)) { $userRole = $rHome->sal_code; }
            }

            if (!empty($homeFormId)) {
                $fStmt = @nuRunQuery("SELECT sfo_code, sfo_description FROM zzzzsys_form WHERE zzzzsys_form_id = ?", [$homeFormId]);
                if ($fStmt && $rF = db_fetch_object($fStmt)) {
                    $navItems[] = [
                        'id' => 'home_' . $homeFormId,
                        'form_id' => $homeFormId,
                        'label' => $rF->sfo_description ?: $rF->sfo_code,
                        'icon' => 'fa-home',
                        'is_home' => true
                    ];
                }
            }

            // Get All Accessible Forms assigned to User Access Level
            $formsSql = "SELECT DISTINCT zzzzsys_form.zzzzsys_form_id, sfo_code, sfo_description FROM zzzzsys_user JOIN zzzzsys_access ON zzzzsys_access_id = sus_zzzzsys_access_id JOIN zzzzsys_access_form ON zzzzsys_access_id = slf_zzzzsys_access_id JOIN zzzzsys_form ON zzzzsys_form_id = slf_zzzzsys_form_id WHERE zzzzsys_user_id = ? ORDER BY sfo_description, sfo_code";
            $formsStmt = @nuRunQuery($formsSql, [$userId]);
            if ($formsStmt) {
                while ($rForm = db_fetch_object($formsStmt)) {
                    if ($rForm->zzzzsys_form_id === $homeFormId) continue; // avoid duplicate home
                    $navItems[] = [
                        'id' => 'form_' . $rForm->zzzzsys_form_id,
                        'form_id' => $rForm->zzzzsys_form_id,
                        'label' => $rForm->sfo_description ?: $rForm->sfo_code,
                        'icon' => 'fa-file-text-o',
                        'is_home' => false
                    ];
                }
            }
        }

        // Fallback default navigation if DB forms are empty
        if (count($navItems) <= 1) {
            $navItems[] = ['id' => 'setup', 'label' => 'System Setup', 'icon' => 'fa-cog', 'action' => 'nuOpenSetup()', 'is_home' => false];
            $navItems[] = ['id' => 'forms', 'label' => 'Form Builder', 'icon' => 'fa-file-text-o', 'action' => "nuSelectForm('nuform')", 'is_home' => false];
            $navItems[] = ['id' => 'users', 'label' => 'User Management', 'icon' => 'fa-users', 'action' => "nuSelectForm('nuuser')", 'is_home' => false];
        }

        $cache->set($cacheKey, $navItems, 600);
        sendDashboardJson(['status' => 'success', 'role' => $userRole, 'items' => $navItems, 'cached' => false]);
        break;

    default:
        sendDashboardJson(['status' => 'error', 'message' => 'Unknown dashboard action']);
}
