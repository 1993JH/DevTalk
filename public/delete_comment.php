<?php
require_once __DIR__ . '/../src/helpers.php';
require_login();

$id      = $_GET['id'] ?? '';
$topicId = $_GET['topic_id'] ?? '';

$owner = db()->prepare('SELECT user_id FROM comments WHERE id = ?');
$owner->execute([$id]);
$comment = $owner->fetch();
if (!$comment) {
    header('Location: topic.php?id=' . (int) $topicId);
    exit;
}
require_owner((int) $comment['user_id']);

$stmt = db()->prepare('DELETE FROM comments WHERE id = ?');
$stmt->execute([$id]);

header('Location: topic.php?id=' . (int) $topicId);
exit;
