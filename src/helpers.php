<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function render_header(string $title = APP_NAME): void
{
    $user = current_user();
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title) ?> · <?= APP_NAME ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/app.css" rel="stylesheet">
    </head>

    <body class="bg-body-tertiary">
        <nav class="navbar navbar-expand-lg bg-dark" data-bs-theme="dark">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="index.php">💬 <?= APP_NAME ?></a>
                <div class="d-flex align-items-center gap-2">
                    <form class="d-flex me-2" role="search" action="search.php" method="get">
                        <input class="form-control form-control-sm" type="search" name="q"
                            placeholder="Search topics…" aria-label="Search">
                    </form>
                    <?php if ($user): ?>
                        <a class="btn btn-sm btn-primary" href="create_topic.php">New Topic</a>
                        <a class="btn btn-sm btn-outline-light" href="profile.php?id=<?= $user['id'] ?>">
                            <?= htmlspecialchars($user['username']) ?>
                        </a>
                        <a class="btn btn-sm btn-outline-light" href="logout.php">Log out</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-outline-light" href="login.php">Log in</a>
                        <a class="btn btn-sm btn-primary" href="register.php">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        <main class="container py-4">
        <?php
    }

    function render_footer(): void
    {
        ?>
        </main>
        <footer class="container text-center text-body-secondary py-4 small">
            <?= APP_NAME ?> — CST8265 lab application. For educational use only.
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>

    </html>
<?php
    }

    function fmt_date(?string $ts): string
    {
        if (!$ts) {
            return '';
        }
        return date('M j, Y g:i A', strtotime($ts));
    }
