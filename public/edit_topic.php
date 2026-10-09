<?php
require_once __DIR__ . '/../src/helpers.php';
require_login();

$id = $_GET['id'] ?? $_POST['id'] ?? '';

$stmt = db()->prepare('SELECT * FROM topics WHERE id = ?');
$stmt->execute([$id]);
$topic = $stmt->fetch();

if (!$topic) {
    render_header('Not found');
    echo '<div class="alert alert-warning">Topic not found.</div>';
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body  = $_POST['body'] ?? '';
    $image = trim($_POST['image_url'] ?? '');

    $upd = db()->prepare(
        'UPDATE topics SET title = ?, body = ?, image_url = ?, updated_at = datetime("now")
         WHERE id = ?'
    );
    $upd->execute([$title, $body, $image, $id]);
    header('Location: topic.php?id=' . (int) $id);
    exit;
}

render_header('Edit Topic');
?>
<h1 class="h4 mb-3">Edit topic</h1>
<form method="post" class="card card-body shadow-sm">
    <input type="hidden" name="id" value="<?= (int) $topic['id'] ?>">
    <div class="mb-3">
        <label class="form-label">Title</label>
        <input name="title" class="form-control" value="<?= htmlspecialchars($topic['title']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Body <span class="text-body-secondary small">(HTML allowed)</span></label>
        <textarea name="body" class="form-control" rows="8" required><?= htmlspecialchars($topic['body']) ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Image URL</label>
        <input name="image_url" class="form-control" value="<?= htmlspecialchars($topic['image_url']) ?>">
    </div>
    <div><button class="btn btn-primary">Save changes</button></div>
</form>
<?php render_footer(); ?>