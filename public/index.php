<?php
require_once __DIR__ . '/../src/helpers.php';

$sql = 'SELECT t.id, t.title, t.body, t.created_at, u.username
        FROM topics t
        JOIN users u ON u.id = t.user_id
        ORDER BY t.created_at DESC';
$topics = db()->query($sql)->fetchAll();

render_header('Home');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Latest Topics</h1>
    <?php if (is_logged_in()): ?>
        <a class="btn btn-primary" href="create_topic.php">New Topic</a>
    <?php endif; ?>
</div>

<?php if (!$topics): ?>
    <p class="text-body-secondary">No topics yet. Be the first to post!</p>
<?php endif; ?>

<div class="vstack gap-3">
    <?php foreach ($topics as $t): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 card-title">
                    <a href="topic.php?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a>
                </h2>
                <p class="card-text text-body-secondary small mb-2">
                    by <?= htmlspecialchars($t['username']) ?> · <?= fmt_date($t['created_at']) ?>
                </p>
                <p class="card-text text-truncate text-body-secondary">
                    <?= htmlspecialchars(mb_substr(strip_tags($t['body']), 0, 160)) ?>
                </p>
                <a href="topic.php?id=<?= (int) $t['id'] ?>" class="stretched-link"></a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php render_footer(); ?>