
<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';

if (isset($_GET['deleted'])) {
    $success = 'Reviewer deleted successfully.';
}

if (isset($_GET['moved'])) {
    $success = 'Reviewer moved successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $folderId = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);

        if ($action === 'create') {
            $name = trim($_POST['folder_name'] ?? '');
            $visibility = $_POST['visibility'] ?? 'private';

            if ($name === '' || mb_strlen($name) > 150) {
                $error = 'Enter a folder name up to 150 characters.';
            } elseif (!in_array($visibility, ['private', 'public'], true)) {
                $error = 'Invalid folder visibility.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO reviewer_folders (user_id, folder_name, visibility)
                     VALUES (?, ?, ?)'
                );
                $stmt->execute([$userId, $name, $visibility]);
                $success = 'Folder created successfully!';
            }
        } elseif ($action === 'rename') {
            $name = trim($_POST['folder_name'] ?? '');

            if (!$folderId || $name === '' || mb_strlen($name) > 150) {
                $error = 'Enter a valid folder name.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE reviewer_folders SET folder_name = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([$name, $folderId, $userId]);
                $success = $stmt->rowCount()
                    ? 'Folder renamed.'
                    : 'No changes made, or folder not found.';
            }
        } elseif ($action === 'visibility') {
            $visibility = $_POST['visibility'] ?? '';

            if (!$folderId || !in_array($visibility, ['private', 'public'], true)) {
                $error = 'Invalid folder or visibility.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE reviewer_folders SET visibility = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([$visibility, $folderId, $userId]);
                $success = 'Folder visibility updated.';
            }
        } elseif ($action === 'delete') {
            if (!$folderId) {
                $error = 'Invalid folder.';
            } else {
                $check = $pdo->prepare(
                    'SELECT id FROM reviewer_folders WHERE id = ? AND user_id = ?'
                );
                $check->execute([$folderId, $userId]);

                if (!$check->fetch()) {
                    $error = 'Folder not found.';
                } else {
                    $count = $pdo->prepare(
                        'SELECT COUNT(*) FROM reviewers WHERE folder_id = ?'
                    );
                    $count->execute([$folderId]);

                    if ((int) $count->fetchColumn() > 0) {
                        $error = 'This folder still has reviewers. Move or delete them first.';
                    } else {
                        $stmt = $pdo->prepare(
                            'DELETE FROM reviewer_folders WHERE id = ? AND user_id = ?'
                        );
                        $stmt->execute([$folderId, $userId]);
                        $success = 'Folder deleted.';
                    }
                }
            }
        } elseif ($action === 'move_reviewer') {
            $reviewerId = filter_input(INPUT_POST, 'reviewer_id', FILTER_VALIDATE_INT);
            $destinationValue = $_POST['destination_folder_id'] ?? '';

            if (!$reviewerId || ($destinationValue !== '' &&
                (!ctype_digit((string) $destinationValue) || (int) $destinationValue < 1))) {
                $error = 'Invalid reviewer or destination folder.';
            } else {
                $destinationId = $destinationValue === '' ? null : (int) $destinationValue;

                $check = $pdo->prepare(
                    'SELECT id FROM reviewers WHERE id = ? AND user_id = ?'
                );
                $check->execute([$reviewerId, $userId]);

                if (!$check->fetch()) {
                    $error = 'Reviewer not found or you do not own it.';
                } else {
                    if ($destinationId !== null) {
                        $check = $pdo->prepare(
                            'SELECT id FROM reviewer_folders WHERE id = ? AND user_id = ?'
                        );
                        $check->execute([$destinationId, $userId]);

                        if (!$check->fetch()) {
                            $error = 'Destination folder not found.';
                        }
                    }

                    if ($error === '') {
                        $stmt = $pdo->prepare(
                            'UPDATE reviewers SET folder_id = ?
                             WHERE id = ? AND user_id = ?'
                        );
                        $stmt->execute([$destinationId, $reviewerId, $userId]);
                        $success = 'Reviewer moved successfully.';
                    }
                }
            }
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT f.id, f.folder_name, f.visibility, f.created_at,
            COUNT(r.id) AS reviewer_count
     FROM reviewer_folders f
     LEFT JOIN reviewers r ON r.folder_id = f.id
     WHERE f.user_id = ?
     GROUP BY f.id, f.folder_name, f.visibility, f.created_at
     ORDER BY f.created_at DESC'
);
$stmt->execute([$userId]);
$folders = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT id, title, subject, original_name, folder_id
     FROM reviewers
     WHERE user_id = ?
     ORDER BY uploaded_at DESC'
);
$stmt->execute([$userId]);
$myReviewers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Folders | GreenReview</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <div class="nav-wrap">
        <a class="brand" href="index.php">🍃 GreenReview</a>
        <nav class="nav-links">
            <a href="index.php">Library</a>
            <a class="active" href="folders.php">My Folders</a>
            <a href="upload.php">Upload</a>
        </nav>
    </div>
</header>

<main class="page-container">
    <p class="eyebrow">KEEP YOUR STUDY FILES ORGANIZED</p>
    <h1>My Folders <span class="leaf">✿</span></h1>
    <p class="muted">Create, organize, and manage your reviewer collections.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>＋ Create a folder</h2>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create">

            <label for="folder_name">Folder name</label>
            <input id="folder_name" name="folder_name" maxlength="150"
                   required placeholder="e.g. Midterm Exam Reviewer">

            <label for="visibility">Visibility</label>
            <select id="visibility" name="visibility">
                <option value="private">🔒 Private — only me</option>
                <option value="public">🌱 Public — share with everyone</option>
            </select>
            <button class="btn btn-primary" type="submit">Create folder</button>
        </form>
    </section>

    <h2>Your collections</h2>
    <section class="reviewer-grid">
        <?php if (!$folders): ?>
            <div class="panel">
                <h3>No folders yet 🌱</h3>
                <p class="muted">Create your first folder above.</p>
            </div>
        <?php else: ?>
            <?php foreach ($folders as $folder): ?>
                <article class="reviewer-card">
                    <div class="reviewer-icon">📁</div>
                    <h2><?= e($folder['folder_name']) ?></h2>
                    <p><?= (int) $folder['reviewer_count'] ?> reviewer(s)
                        · <?= e($folder['visibility']) ?></p>

                    <a class="btn btn-primary"
                       href="folder.php?id=<?= (int) $folder['id'] ?>">
                        Open folder →
                    </a>

                    <form method="post" class="form-stack">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="folder_id" value="<?= (int) $folder['id'] ?>">
                        <label>Rename folder</label>
                        <input name="folder_name" maxlength="150" required
                               value="<?= e($folder['folder_name']) ?>">
                        <button class="btn" type="submit">Save name</button>
                    </form>

                    <form method="post" class="form-stack">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="visibility">
                        <input type="hidden" name="folder_id" value="<?= (int) $folder['id'] ?>">
                        <label>Visibility</label>
                        <select name="visibility">
                            <option value="private" <?= $folder['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                            <option value="public" <?= $folder['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                        </select>
                        <button class="btn" type="submit">Update visibility</button>
                    </form>

                    <form method="post" class="form-stack"
                          onsubmit="return confirm('Delete this empty folder?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="folder_id" value="<?= (int) $folder['id'] ?>">
                        <button class="btn" type="submit">🗑️ Delete empty folder</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <h2>My uploaded reviewers</h2>
    <p class="muted">Move reviewers between folders, or remove your own uploads.</p>

    <section class="reviewer-grid">
        <?php if (!$myReviewers): ?>
            <div class="panel"><p>No uploaded reviewers yet.</p></div>
        <?php else: ?>
            <?php foreach ($myReviewers as $reviewer): ?>
                <article class="reviewer-card">
                    <div class="reviewer-icon">📄</div>
                    <h3><?= e($reviewer['title']) ?></h3>
                    <p><?= e($reviewer['subject']) ?></p>
                    <p class="muted"><?= e($reviewer['original_name'] ?: 'Reviewer file') ?></p>

                    <a class="btn btn-primary"
                       href="view.php?id=<?= (int) $reviewer['id'] ?>">
                        View reviewer
                    </a>

                    <form method="post" class="form-stack">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="move_reviewer">
                        <input type="hidden" name="reviewer_id" value="<?= (int) $reviewer['id'] ?>">

                        <label>Move to folder</label>
                        <select name="destination_folder_id">
                            <option value="">No folder — Public Library</option>
                            <?php foreach ($folders as $destination): ?>
                                <option value="<?= (int) $destination['id'] ?>"
                                    <?= (string) $reviewer['folder_id'] === (string) $destination['id'] ? 'selected' : '' ?>>
                                    <?= e($destination['folder_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn" type="submit">Move reviewer</button>
                    </form>

                    <form method="post" action="delete_reviewer.php"
                          class="form-stack"
                          onsubmit="return confirm('Permanently delete this reviewer and its uploaded file?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="reviewer_id" value="<?= (int) $reviewer['id'] ?>">
                        <button class="btn" type="submit">🗑️ Delete reviewer</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
