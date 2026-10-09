<?php
require_once __DIR__ . '/../src/config.php';

$url = $_GET['url'] ?? '';

if ($url === '') {
    http_response_code(400);
    echo 'No url provided.';
    exit;
}

$context = stream_context_create([
    'http' => ['timeout' => 5, 'follow_location' => 1],
]);
$data = @file_get_contents($url, false, $context);

if ($data === false) {
    http_response_code(502);
    echo 'Could not fetch: ' . htmlspecialchars($url);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($data) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
echo $data;
