<?php
require_once __DIR__ . '/../src/helpers.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body  = $_POST['body'] ?? '';
    $image = trim($_POST['image_url'] ?? '');

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO topics (user_id, title, body, image_url) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$_SESSION['user_id'], $title, $body, $image]);
        $newId = (int) db()->lastInsertId();
        header('Location: topic.php?id=' . $newId);
        exit;
    }
}

render_header('New Topic');
?>
<h1 class="h4 mb-3">Create a new topic</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="post" class="card card-body shadow-sm">
    <div class="mb-3">
        <label class="form-label">Title</label>
        <input name="title" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Body <span class="text-body-secondary small">(HTML allowed)</span></label>
        <textarea name="body" class="form-control" rows="8" required></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Image URL <span class="text-body-secondary small">(optional — shown via image preview)</span></label>
        <input name="image_url" class="form-control" placeholder="https://…">
    </div>
    <div><button class="btn btn-primary">Publish</button></div>
</form>
<?php render_footer(); ?>