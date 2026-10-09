<?php
require_once __DIR__ . '/../src/helpers.php';
require_login();

$id = $_GET['id'] ?? '';

$stmt = db()->prepare('DELETE FROM topics WHERE id = ?');
$stmt->execute([$id]);

header('Location: index.php');
exit;
