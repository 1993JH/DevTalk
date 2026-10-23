<?php
require_once __DIR__ . '/../src/helpers.php';
require_login();

$id = $_GET['id'] ?? '';

$owner = db()->prepare('SELECT user_id FROM topics WHERE id = ?');
$owner->execute([$id]);
$topic = $owner->fetch();
if (!$topic) {
    header('Location: index.php');
    exit;
}
require_owner((int) $topic['user_id']);

$stmt = db()->prepare('DELETE FROM topics WHERE id = ?');
$stmt->execute([$id]);

header('Location: index.php');
exit;
