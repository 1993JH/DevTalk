<?php

/**
 * DevTalk API extension — add the private-messages table and seed it, and
 * create the JWT signing key.
 *
 * Runs after database/build_db.php as part of `composer reset-db`.
 * Seeded user ids: 1 admin, 2 alice, 3 bob, 4 carol.
 */

require_once __DIR__ . '/../src/config.php';

$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON;');

$pdo->exec('DROP TABLE IF EXISTS messages');
$pdo->exec(
    'CREATE TABLE messages (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id    INTEGER NOT NULL,
        recipient_id INTEGER NOT NULL,
        subject      TEXT NOT NULL,
        body         TEXT NOT NULL,
        created_at   TEXT NOT NULL DEFAULT (datetime(\'now\')),
        FOREIGN KEY (sender_id)    REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    )'
);

// sender_id, recipient_id, subject, body, created_at offset
$messages = [
    [1, 2, 'Welcome to the messaging beta',
        "Hi Alice, you're one of our first beta testers for private messages. "
        . "Tell me if anything breaks. - admin", '-5 days'],
    [2, 3, 'Code review Friday?',
        "Can you look at my CSS grid pull request before Friday? Coffee's on me.", '-4 days'],
    [3, 2, 'Re: Code review Friday?',
        "Sure. Heads up: the staging database password changed this week. "
        . "It's in the team vault now, not in the chat channel.", '-4 days'],
    [4, 1, 'Locked out of my old email',
        "My new recovery email is carol.personal@example.net and my phone number is "
        . "613-555-0147. Can you update my account?", '-3 days'],
    [1, 4, 'Re: Locked out of my old email',
        "Done. Please don't send personal details over messages next time.", '-2 days'],
    [3, 4, 'Study group',
        "Want to join our Thursday study group for the web security midterm?", '-1 days'],
];

$ins = $pdo->prepare(
    'INSERT INTO messages (sender_id, recipient_id, subject, body, created_at)
     VALUES (?, ?, ?, ?, datetime("now", ?))'
);
foreach ($messages as $m) {
    $ins->execute($m);
}

// JWT signing key: random, outside public/, Git-ignored. Created once so
// tokens survive a database reset.
$storage = __DIR__ . '/../storage';
@mkdir($storage, 0775, true);
$keyFile = $storage . '/jwt.key';
if (!is_file($keyFile)) {
    file_put_contents($keyFile, bin2hex(random_bytes(32)));
    @chmod($keyFile, 0600);
    echo "Created JWT signing key: storage/jwt.key\n";
}

echo "API extension: seeded " . count($messages) . " private messages.\n";
