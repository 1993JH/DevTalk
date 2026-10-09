<?php
require_once __DIR__ . '/../src/helpers.php';

$id = $_GET['id'] ?? ($_POST['user_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    $bio    = $_POST['bio'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $upd = db()->prepare('UPDATE users SET bio = ? WHERE id = ?');
    $upd->execute([$bio, $userId]);
    header('Location: profile.php?id=' . (int) $userId);
    exit;
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$profile = $stmt->fetch();

if (!$profile) {
    render_header('Not found');
    echo '<div class="alert alert-warning">User not found.</div>';
    render_footer();
    exit;
}

$ts = db()->prepare('SELECT id, title, created_at FROM topics WHERE user_id = ? ORDER BY created_at DESC');
$ts->execute([$id]);
$topics = $ts->fetchAll();

$me = current_user();
render_header($profile['username'] . "'s profile");
?>
<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h1 class="h4"><?= htmlspecialchars($profile['username']) ?>
                    <?php if ($profile['is_admin']): ?><span class="badge text-bg-warning">admin</span><?php endif; ?>
                </h1>

                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($profile['email']) ?></p>
                <p class="text-body-secondary small mb-2">Joined <?= fmt_date($profile['created_at']) ?></p>
                <p class="mb-0"><?= htmlspecialchars($profile['bio']) ?></p>
            </div>
        </div>

        <?php if ($me): ?>
            <form method="post" class="card card-body shadow-sm">
                <input type="hidden" name="user_id" value="<?= (int) $profile['id'] ?>">
                <label class="form-label">Update bio</label>
                <textarea name="bio" class="form-control mb-2" rows="3"><?= htmlspecialchars($profile['bio']) ?></textarea>
                <div><button class="btn btn-sm btn-primary">Save bio</button></div>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-md-7">
        <h2 class="h5 mb-3">Topics by <?= htmlspecialchars($profile['username']) ?></h2>
        <div class="vstack gap-2">
            <?php foreach ($topics as $t): ?>
                <a class="text-decoration-none" href="topic.php?id=<?= (int) $t['id'] ?>">
                    <?= htmlspecialchars($t['title']) ?>
                    <span class="text-body-secondary small">· <?= fmt_date($t['created_at']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$topics): ?><p class="text-body-secondary">No topics yet.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php render_footer(); ?>