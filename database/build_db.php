<?php

/**
 * Rebuild and pre-populate the SQLite database.
 *
 * Usage:
 *   composer reset-db
 *   php database/build_db.php
 *
 * This DROPs existing tables, recreates the schema, and seeds sample data so
 * the forum is ready to use out of the box.
 */

require_once __DIR__ . '/../src/config.php';

$dbFile = DB_PATH;

// Start from a clean file so autoincrement ids are predictable.
if (file_exists($dbFile)) {
    unlink($dbFile);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- Schema ---------------------------------------------------------------
$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);

// --- Seed users -----------------------------------------------------------
$users = [
    ['admin', 'admin@devtalk.local', 'Sup3rSecret!', 'Site administrator. Please be respectful.', 1],
    ['alice', 'alice@devtalk.local', 'password123', 'Front-end dev who loves CSS tricks.', 0],
    ['bob',   'bob@example.com',     'hunter2',      'Backend engineer. SQL enthusiast.', 0],
    ['carol', 'carol@example.com',   'letmein2024',  'Security-curious student.', 0],
];

$insUser = $pdo->prepare(
    'INSERT INTO users (username, email, password, bio, is_admin, created_at)
     VALUES (?, ?, ?, ?, ?, datetime("now", ?))'
);
// stagger created_at a little
$offsets = ['-30 days', '-25 days', '-20 days', '-15 days'];
foreach ($users as $i => $u) {
    $insUser->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $offsets[$i]]);
}

// --- Seed topics ----------------------------------------------------------
// user_id, title, body (may contain HTML), image_url, created_at offset
$topics = [
    [
        1,
        'Welcome to DevTalk!',
        "<p>Welcome to the DevTalk community forum. Introduce yourself and share what you're working on.</p>"
            . "<p>You can use <strong>HTML</strong> in your posts — bold, links, even images:</p>"
            . '<p><img src="assets/img/welcome.png" alt="welcome banner"></p>',
        '',
        '-14 days'
    ],

    [
        2,
        'Favourite CSS layout tricks?',
        "<p>I've been using CSS grid for everything lately. The <code>grid-template-areas</code> syntax "
            . "makes responsive layouts so readable. What are your go-to tricks?</p>",
        '',
        '-10 days'
    ],

    [
        3,
        'PDO vs mysqli — which do you prefer?',
        "<p>Starting a new PHP project. I lean towards PDO for the cleaner API and multi-driver support, "
            . "but curious what everyone else uses and why.</p>",
        '',
        '-7 days'
    ],

    [
        1,
        'Forum rules & posting guidelines',
        "<p>Keep it civil, stay on topic, and no spam. Posts that include images should link to the source. "
            . "Admins reserve the right to remove content.</p>",
        '',
        '-6 days'
    ],

    [
        4,
        'How do you keep up with security news?',
        "<p>There's so much happening in web security. Do you follow newsletters, podcasts, specific blogs? "
            . "Trying to build a good reading list.</p>",
        '',
        '-2 days'
    ],
];

$insTopic = $pdo->prepare(
    'INSERT INTO topics (user_id, title, body, image_url, created_at, updated_at)
     VALUES (?, ?, ?, ?, datetime("now", ?), datetime("now", ?))'
);
foreach ($topics as $t) {
    $insTopic->execute([$t[0], $t[1], $t[2], $t[3], $t[4], $t[4]]);
}

// --- Seed comments --------------------------------------------------------
// topic_id, user_id, body, created_at offset
$comments = [
    [1, 2, 'Hi everyone! Excited to be here. 👋', '-13 days'],
    [1, 3, 'Welcome aboard. This place looks great.', '-12 days'],
    [2, 3, 'Flexbox for components, grid for page layout. That combo never fails me.', '-9 days'],
    [2, 4, 'Totally agree. Also <code>gap</code> is underrated.', '-9 days'],
    [3, 2, 'PDO all the way. Prepared statements are just nicer.', '-6 days'],
    [3, 4, 'mysqli still works but PDO is more flexible for sure.', '-5 days'],
    [5, 2, 'I like the "tl;dr sec" newsletter. Great weekly summary.', '-1 days'],
];

$insComment = $pdo->prepare(
    'INSERT INTO comments (topic_id, user_id, body, created_at)
     VALUES (?, ?, ?, datetime("now", ?))'
);
foreach ($comments as $c) {
    $insComment->execute([$c[0], $c[1], $c[2], $c[3]]);
}

// Ensure uploads dir exists and has the seed image referenced above.
@mkdir(UPLOAD_DIR, 0775, true);

echo "Database rebuilt at: {$dbFile}\n";
echo "Seeded " . count($users) . " users, " . count($topics) . " topics, "
    . count($comments) . " comments.\n";
echo "Sample logins (username / password):\n";
foreach ($users as $u) {
    echo "  - {$u[0]} / {$u[2]}" . ($u[4] ? "  (admin)" : "") . "\n";
}
