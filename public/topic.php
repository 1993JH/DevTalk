<?php
require_once __DIR__ . '/../src/helpers.php';

$id = $_GET['id'] ?? '';

$stmt = db()->prepare(
    'SELECT t.*, u.username
     FROM topics t JOIN users u ON u.id = t.user_id
     WHERE t.id = ?'
);
$stmt->execute([$id]);
$topic = $stmt->fetch();

if (!$topic) {
    http_response_code(404);
    render_header('Not found');
    echo '<div class="alert alert-warning">Topic not found.</div>';
    render_footer();
    exit;
}

$cstmt = db()->prepare(
    'SELECT c.*, u.username
     FROM comments c JOIN users u ON u.id = c.user_id
     WHERE c.topic_id = ?
     ORDER BY c.created_at ASC'
);
$cstmt->execute([$id]);
$comments = $cstmt->fetchAll();

$user = current_user();

render_header($topic['title']);
?>
<article class="card shadow-sm mb-4">
    <div class="card-body">
        <h1 class="h3"><?= htmlspecialchars($topic['title']) ?></h1>
        <p class="text-body-secondary small">
            by <?= htmlspecialchars($topic['username']) ?> ·
            <?= fmt_date($topic['created_at']) ?>
            <?php if ($topic['updated_at'] !== $topic['created_at']): ?>
                · edited <?= fmt_date($topic['updated_at']) ?>
            <?php endif; ?>
        </p>

        <?php if (!empty($topic['image_url'])): ?>
            <p><img class="img-fluid rounded" src="fetch_image.php?url=<?= urlencode($topic['image_url']) ?>" alt="topic image"></p>
        <?php endif; ?>

        <div class="topic-body"><?= $topic['body'] ?></div>

        <?php if ($user): ?>
            <div class="mt-3 d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary"
                    href="edit_topic.php?id=<?= (int) $topic['id'] ?>">Edit</a>
                <a class="btn btn-sm btn-outline-danger"
                    href="delete_topic.php?id=<?= (int) $topic['id'] ?>"
                    onclick="return confirm('Delete this topic?')">Delete</a>
            </div>
        <?php endif; ?>
    </div>
</article>

<h2 class="h5 mb-3"><?= count($comments) ?> Comment<?= count($comments) === 1 ? '' : 's' ?></h2>

<div class="vstack gap-3 mb-4">
    <?php foreach ($comments as $c): ?>
        <div class="card comment">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between">
                    <span class="small text-body-secondary">
                        <span class="avatar me-1"><?= strtoupper(substr($c['username'], 0, 1)) ?></span>
                        <strong><?= htmlspecialchars($c['username']) ?></strong>
                        · <?= fmt_date($c['created_at']) ?>
                    </span>
                    <?php if ($user): ?>
                        <a class="small text-danger text-decoration-none"
                            href="delete_comment.php?id=<?= (int) $c['id'] ?>&topic_id=<?= (int) $topic['id'] ?>">delete</a>
                    <?php endif; ?>
                </div>
                <div class="mt-1"><?= $c['body'] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($user): ?>
    <form method="post" action="add_comment.php" class="card card-body shadow-sm">
        <input type="hidden" name="topic_id" value="<?= (int) $topic['id'] ?>">
        <label class="form-label">Add a comment</label>
        <textarea name="body" class="form-control mb-2" rows="3" required></textarea>
        <div><button class="btn btn-primary">Post comment</button></div>
    </form>
<?php else: ?>
    <p class="text-body-secondary"><a href="login.php">Log in</a> to join the discussion.</p>
<?php endif; ?>

<?php render_footer(); ?>