<?php
require_once __DIR__ . '/../src/config.php';

$url = $_GET['url'] ?? '';

if ($url === '') {
    http_response_code(400);
    echo 'No url provided.';
    exit;
}

/**
 * SSRF guard: only fetch ordinary public web images.
 *  - scheme must be http/https (blocks file://, gopher://, ftp://, …)
 *  - every IP the host resolves to must be public (blocks localhost,
 *    127.0.0.0/8, 10/172.16/192.168, link-local, and 169.254.169.254 metadata)
 *  - redirects are disabled so a public URL can't 302 to an internal one
 *  - the response must actually be an image
 */
$parts = parse_url($url);
if (
    $parts === false
    || empty($parts['scheme'])
    || empty($parts['host'])
    || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
) {
    http_response_code(400);
    echo 'Only http(s) image URLs are allowed.';
    exit;
}

$host = trim($parts['host'], '[]'); // strip brackets from IPv6 literals

// Collect every address this host resolves to. gethostbynamel() uses the same
// system resolver (incl. /etc/hosts) that the fetch will, so names like
// "localhost" are caught; dns_get_record() adds IPv6 (AAAA) addresses.
$ips = [];
if (filter_var($host, FILTER_VALIDATE_IP)) {
    $ips[] = $host;
} else {
    foreach (gethostbynamel($host) ?: [] as $ip4) {
        $ips[] = $ip4;
    }
    foreach (@dns_get_record($host, DNS_A + DNS_AAAA) ?: [] as $rec) {
        if (!empty($rec['ip'])) {
            $ips[] = $rec['ip'];
        }
        if (!empty($rec['ipv6'])) {
            $ips[] = $rec['ipv6'];
        }
    }
}

if (!$ips) {
    http_response_code(400);
    echo 'Could not resolve host.';
    exit;
}

foreach ($ips as $ip) {
    $public = filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
    if ($public === false) {
        http_response_code(403);
        echo 'Refusing to fetch a private or reserved address.';
        exit;
    }
}

$context = stream_context_create([
    'http' => [
        'timeout'        => 5,
        'follow_location' => 0,
        'max_redirects'  => 0,
    ],
]);
$data = @file_get_contents($url, false, $context);

if ($data === false) {
    http_response_code(502);
    echo 'Could not fetch: ' . htmlspecialchars($url);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($data) ?: 'application/octet-stream';

if (strncmp($mime, 'image/', 6) !== 0) {
    http_response_code(415);
    echo 'Fetched resource is not an image.';
    exit;
}

header('Content-Type: ' . $mime);
echo $data;
