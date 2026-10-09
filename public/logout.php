<?php
require_once __DIR__ . '/../src/helpers.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
