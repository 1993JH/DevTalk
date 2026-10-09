<?php
require_once __DIR__ . '/../src/helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topicId = $_POST['topic_id'] ?? '';
    $body    = trim($_POST['body'] ?? '');

    if ($body !== '') {
        $stmt = db()->prepare(
            'INSERT INTO comments (topic_id, user_id, body) VALUES (?, ?, ?)'
        );
        $stmt->execute([$topicId, $_SESSION['user_id'], $body]);
    }
    header('Location: topic.php?id=' . (int) $topicId);
    exit;
}

header('Location: index.php');
exit;
