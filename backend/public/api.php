<?php
// backend/public/api.php
require_once 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$pdo = Database::connect();

$sensitiveFields = ['ip', 'user_agent', 'country', 'city', 'isp', 'referrer'];
$profileFields = ['screen_width', 'screen_height', 'language', 'timezone', 'platform', 'device_memory', 'cpu_cores'];

function anonymizeData($row, $level)
{
    global $sensitiveFields, $profileFields;

    if ($level === 'none' || $level === 'withdrawn') {
        foreach ($sensitiveFields as $field) {
            if (isset($row[$field])) {
                $row[$field] = maskValue($field, $row[$field]);
            }
        }
        foreach ($profileFields as $field) {
            if (isset($row[$field]) && !empty($row[$field])) {
                if ($level === 'withdrawn') {
                    $row[$field] = '';
                }
            }
        }
        if ($level === 'withdrawn' && isset($row['remark'])) {
            $row['remark'] = '';
        }
    } elseif ($level === 'partial') {
        foreach (['ip', 'user_agent', 'country', 'city', 'isp', 'referrer'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = maskValue($field, $row[$field]);
            }
        }
    }

    return $row;
}

function maskValue($field, $value)
{
    if (empty($value)) return '';

    switch ($field) {
        case 'ip':
            if (strpos($value, '.') !== false) {
                $parts = explode('.', $value);
                if (count($parts) >= 2) {
                    return $parts[0] . '.' . $parts[1] . '.*.*';
                }
            }
            return substr($value, 0, 3) . '***';
        case 'user_agent':
            if (strlen($value) > 20) {
                return substr($value, 0, 10) . '***' . substr($value, -5);
            }
            return '***';
        case 'country':
        case 'city':
            if (strlen($value) > 2) {
                return mb_substr($value, 0, 1) . '*';
            }
            return '*';
        case 'isp':
            if (strlen($value) > 4) {
                return mb_substr($value, 0, 2) . '**';
            }
            return '**';
        case 'referrer':
            try {
                $url = parse_url($value);
                return ($url['scheme'] ?? 'https') . '://***';
            } catch (Exception $e) {
                return '***';
            }
        default:
            return '***';
    }
}

function getPrivacyLevel($row)
{
    $consent = $row['privacy_consent'] ?? 0;
    $anonymized = $row['is_anonymized'] ?? 0;

    if ($anonymized == 1) {
        return 'withdrawn';
    }
    if ($consent == 1) {
        return 'full';
    }
    return 'none';
}

try {
    switch ($action) {
        case 'collect':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid method');
            }
            $input = json_decode(file_get_contents('php://input'), true);

            $mode = $input['mode'] ?? 'full';
            $visitorId = $input['visitor_id'] ?? null;

            if ($visitorId) {
                $existing = $pdo->prepare("SELECT * FROM visitors WHERE id = ?");
                $existing->execute([$visitorId]);
                $visitor = $existing->fetch();
                if ($visitor) {
                    echo json_encode(['status' => 'success', 'id' => $visitorId, 'consent' => (int)$visitor['privacy_consent']]);
                    break;
                }
            }

            $ip = $_SERVER['REMOTE_ADDR'];
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = $_SERVER['HTTP_X_REAL_IP'];
            }
            $ip = trim($ip);

            $country = $input['country'] ?? '';
            $city = $input['city'] ?? '';
            $isp = $input['isp'] ?? '';

            if ($mode === 'full' && empty($country) && empty($city)) {
                $geoUrl = "http://ip-api.com/json/{$ip}?lang=zh-CN";
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'ignore_errors' => true
                    ]
                ]);
                $geoJson = @file_get_contents($geoUrl, false, $context);
                if ($geoJson) {
                    $geoData = json_decode($geoJson, true);
                    if ($geoData && $geoData['status'] === 'success') {
                        $country = $geoData['country'] ?? '';
                        $city = $geoData['city'] ?? '';
                        $isp = $geoData['isp'] ?? '';
                    }
                }
            }

            if ($mode === 'anonymous') {
                $data = [
                    ':ip' => '',
                    ':user_agent' => '',
                    ':country' => '',
                    ':city' => '',
                    ':isp' => '',
                    ':browser' => '',
                    ':browser_version' => '',
                    ':os' => '',
                    ':os_version' => '',
                    ':device_type' => '',
                    ':screen_width' => 0,
                    ':screen_height' => 0,
                    ':window_width' => 0,
                    ':window_height' => 0,
                    ':language' => '',
                    ':timezone' => '',
                    ':platform' => '',
                    ':cookie_enabled' => 0,
                    ':touch_points' => 0,
                    ':device_memory' => 0,
                    ':cpu_cores' => 0,
                    ':connection_type' => '',
                    ':referrer' => '',
                    ':remark' => '',
                    ':privacy_consent' => 0,
                    ':consent_time' => null,
                    ':is_anonymized' => 0,
                    ':page_load_time' => $input['page_load_time'] ?? null,
                    ':page_size' => $input['page_size'] ?? null
                ];

                $sql = "INSERT INTO visitors (
                    ip, user_agent, country, city, isp,
                    browser, browser_version, os, os_version, device_type,
                    screen_width, screen_height, window_width, window_height,
                    language, timezone, platform, cookie_enabled,
                    touch_points, device_memory, cpu_cores, connection_type,
                    referrer, remark, privacy_consent, consent_time, is_anonymized,
                    page_load_time, page_size
                ) VALUES (
                    :ip, :user_agent, :country, :city, :isp,
                    :browser, :browser_version, :os, :os_version, :device_type,
                    :screen_width, :screen_height, :window_width, :window_height,
                    :language, :timezone, :platform, :cookie_enabled,
                    :touch_points, :device_memory, :cpu_cores, :connection_type,
                    :referrer, :remark, :privacy_consent, :consent_time, :is_anonymized,
                    :page_load_time, :page_size
                )";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                $newId = $pdo->lastInsertId();

                echo json_encode(['status' => 'success', 'id' => $newId, 'consent' => 0]);
            } else {
                $data = [
                    ':ip' => $ip,
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    ':country' => $country,
                    ':city' => $city,
                    ':isp' => $isp,
                    ':browser' => $input['browser'] ?? '未知',
                    ':browser_version' => $input['browser_version'] ?? '',
                    ':os' => $input['os'] ?? '未知',
                    ':os_version' => $input['os_version'] ?? '',
                    ':device_type' => $input['device_type'] ?? '桌面设备',
                    ':screen_width' => $input['screen_width'] ?? 0,
                    ':screen_height' => $input['screen_height'] ?? 0,
                    ':window_width' => $input['window_width'] ?? 0,
                    ':window_height' => $input['window_height'] ?? 0,
                    ':language' => $input['language'] ?? '',
                    ':timezone' => $input['timezone'] ?? '',
                    ':platform' => $input['platform'] ?? '',
                    ':cookie_enabled' => isset($input['cookie_enabled']) ? ($input['cookie_enabled'] ? 1 : 0) : 0,
                    ':touch_points' => $input['touch_points'] ?? 0,
                    ':device_memory' => $input['device_memory'] ?? 0,
                    ':cpu_cores' => $input['cpu_cores'] ?? 0,
                    ':connection_type' => $input['connection_type'] ?? '',
                    ':referrer' => $input['referrer'] ?? '',
                    ':remark' => '',
                    ':privacy_consent' => 1,
                    ':consent_time' => date('Y-m-d H:i:s'),
                    ':is_anonymized' => 0,
                    ':page_load_time' => $input['page_load_time'] ?? null,
                    ':page_size' => $input['page_size'] ?? null
                ];

                $sql = "INSERT INTO visitors (
                    ip, user_agent, country, city, isp,
                    browser, browser_version, os, os_version, device_type,
                    screen_width, screen_height, window_width, window_height,
                    language, timezone, platform, cookie_enabled,
                    touch_points, device_memory, cpu_cores, connection_type,
                    referrer, remark, privacy_consent, consent_time, is_anonymized,
                    page_load_time, page_size
                ) VALUES (
                    :ip, :user_agent, :country, :city, :isp,
                    :browser, :browser_version, :os, :os_version, :device_type,
                    :screen_width, :screen_height, :window_width, :window_height,
                    :language, :timezone, :platform, :cookie_enabled,
                    :touch_points, :device_memory, :cpu_cores, :connection_type,
                    :referrer, :remark, :privacy_consent, :consent_time, :is_anonymized,
                    :page_load_time, :page_size
                )";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                $newId = $pdo->lastInsertId();

                echo json_encode(['status' => 'success', 'id' => $newId, 'consent' => 1]);
            }
            break;

        case 'consent':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid method');
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            $grant = $input['grant'] ?? false;

            if (!$id)
                throw new Exception('ID required');

            $stmt = $pdo->prepare("SELECT * FROM visitors WHERE id = ?");
            $stmt->execute([$id]);
            $visitor = $stmt->fetch();

            if (!$visitor) {
                throw new Exception('记录不存在');
            }

            if ($grant) {
                $updateStmt = $pdo->prepare("UPDATE visitors SET privacy_consent = 1, consent_time = ?, is_anonymized = 0 WHERE id = ?");
                $updateStmt->execute([date('Y-m-d H:i:s'), $id]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE visitors SET privacy_consent = 2, withdraw_time = ?, is_anonymized = 1 WHERE id = ?");
                $updateStmt->execute([date('Y-m-d H:i:s'), $id]);
            }

            echo json_encode(['status' => 'success']);
            break;

        case 'list':
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            $search = $_GET['search'] ?? '';

            $where = "WHERE 1=1";
            $params = [];

            if ($search) {
                $where .= " AND (remark LIKE :search";
                $params[':search'] = "%$search%";

                $where .= " OR (privacy_consent = 1 AND is_anonymized = 0 AND (";
                $where .= "ip LIKE :search OR city LIKE :search OR country LIKE :search OR isp LIKE :search";
                $where .= " OR browser LIKE :search OR os LIKE :search";
                $where .= "))";
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors $where");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT * FROM visitors $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $list = $stmt->fetchAll();

            foreach ($list as &$row) {
                $level = getPrivacyLevel($row);
                $row = anonymizeData($row, $level);
            }
            unset($row);

            echo json_encode([
                'status' => 'success',
                'data' => $list,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ]);
            break;

        case 'detail':
            $id = $_GET['id'] ?? 0;
            if (!$id)
                throw new Exception('ID required');

            $stmt = $pdo->prepare("SELECT * FROM visitors WHERE id = ?");
            $stmt->execute([$id]);
            $visitor = $stmt->fetch();

            if (!$visitor) {
                throw new Exception('记录不存在');
            }

            $level = getPrivacyLevel($visitor);
            $visitor = anonymizeData($visitor, $level);

            echo json_encode([
                'status' => 'success',
                'data' => $visitor
            ]);
            break;

        case 'remark':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid method');
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            $remark = $input['remark'] ?? '';

            if (!$id)
                throw new Exception('ID required');

            $stmt = $pdo->prepare("SELECT is_anonymized, privacy_consent FROM visitors WHERE id = ?");
            $stmt->execute([$id]);
            $visitor = $stmt->fetch();

            if (!$visitor) {
                throw new Exception('记录不存在');
            }

            if ($visitor['is_anonymized'] == 1) {
                throw new Exception('已撤回授权的记录无法编辑备注');
            }

            $stmt = $pdo->prepare("UPDATE visitors SET remark = :remark WHERE id = :id");
            $stmt->execute([':remark' => $remark, ':id' => $id]);

            echo json_encode(['status' => 'success']);
            break;

        case 'stats':
            $today = date('Y-m-d');

            $todayStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE DATE(created_at) = :today");
            $todayStmt->execute([':today' => $today]);
            $todayCount = $todayStmt->fetchColumn();

            $totalStmt = $pdo->query("SELECT COUNT(*) FROM visitors");
            $totalCount = $totalStmt->fetchColumn();

            $consentedStmt = $pdo->query("SELECT COUNT(*) FROM visitors WHERE privacy_consent = 1 AND is_anonymized = 0");
            $consentedCount = $consentedStmt->fetchColumn();

            $anonStmt = $pdo->query("SELECT COUNT(*) FROM visitors WHERE is_anonymized = 1");
            $anonCount = $anonStmt->fetchColumn();

            $slowStmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE page_load_time > 2000");
            $slowStmt->execute();
            $slowCount = $slowStmt->fetchColumn();

            $avgStmt = $pdo->query("SELECT AVG(page_load_time) FROM visitors WHERE page_load_time IS NOT NULL");
            $avgResult = $avgStmt->fetchColumn();
            $avgLoadTime = $avgResult ? round($avgResult, 0) : 0;

            echo json_encode([
                'status' => 'success',
                'total' => $totalCount,
                'today' => $todayCount,
                'consented' => $consentedCount,
                'anonymized' => $anonCount,
                'slow_pages' => $slowCount,
                'avg_load_time' => $avgLoadTime
            ]);
            break;

        case 'export':
            $format = $_GET['format'] ?? 'csv';
            $search = $_GET['search'] ?? '';

            $where = "WHERE 1=1";
            $params = [];

            if ($search) {
                $where .= " AND remark LIKE :search";
                $params[':search'] = "%$search%";
            }

            $stmt = $pdo->prepare("SELECT * FROM visitors $where ORDER BY created_at DESC");
            $stmt->execute($params);
            $list = $stmt->fetchAll();

            foreach ($list as &$row) {
                $level = getPrivacyLevel($row);
                $row = anonymizeData($row, $level);
                unset($row['user_agent']);
            }
            unset($row);

            if ($format === 'json') {
                echo json_encode([
                    'status' => 'success',
                    'data' => $list
                ]);
            } else {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="visitors.csv"');

                $output = fopen('php://output', 'w');
                if (!empty($list)) {
                    fputcsv($output, array_keys($list[0]));
                    foreach ($list as $row) {
                        fputcsv($output, $row);
                    }
                }
                fclose($output);
                exit;
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => '未知操作']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
