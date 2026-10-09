<?php
require_once __DIR__ . '/../src/helpers.php';
require_login();

$id      = $_GET['id'] ?? '';
$topicId = $_GET['topic_id'] ?? '';

$stmt = db()->prepare('DELETE FROM comments WHERE id = ?');
$stmt->execute([$id]);

header('Location: topic.php?id=' . (int) $topicId);
exit;
