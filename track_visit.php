<?php
/**
 * track_visit.php
 * Called via fetch() from the frontend (index.html / v1 / v2).
 * Logs each page visit into the site_visits table.
 * Place this file in the same folder as dashboard.php (i.e. the admin folder's sibling — wherever config.php is accessible).
 */

require_once dirname(__DIR__) . '/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Get visitor IP (handle proxies / Cloudflare)
function get_client_ip(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            // X-Forwarded-For can be a comma-separated list; take the first
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// Skip bots / crawlers to keep counts clean
function is_bot(string $ua): bool {
    $bots = ['bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'wget', 'curl', 'python', 'scrapy', 'headless'];
    $ua_lower = strtolower($ua);
    foreach ($bots as $b) {
        if (str_contains($ua_lower, $b)) return true;
    }
    return false;
}

$ip         = get_client_ip();
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$page       = substr(trim($_POST['page'] ?? '/'), 0, 255);
$page       = $page === '' ? '/' : $page;

// Skip bots
if (is_bot($user_agent)) {
    http_response_code(204);
    exit();
}

// Optional: deduplicate — skip if same IP visited the same page within the last 30 minutes
$safe_ip   = mysqli_real_escape_string($conn, $ip);
$safe_page = mysqli_real_escape_string($conn, $page);

$dupe = mysqli_query($conn, "
    SELECT id FROM site_visits
    WHERE ip = '$safe_ip'
      AND page = '$safe_page'
      AND visited_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    LIMIT 1
");

if (mysqli_num_rows($dupe) > 0) {
    http_response_code(204); // Already counted recently
    exit();
}

$safe_ua = mysqli_real_escape_string($conn, $user_agent);

mysqli_query($conn, "
    INSERT INTO site_visits (ip, user_agent, page, visited_at)
    VALUES ('$safe_ip', '$safe_ua', '$safe_page', NOW())
");

http_response_code(204); // No Content — silent success