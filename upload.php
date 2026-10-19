
<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$error = '';
$success = '';
$userId = (int) $_SESSION['user_id'];

// Read the folder passed from folder.php.
$getFolderValue = $_GET['folder_id'] ?? '';
$getFolderId = null;

if (
    $getFolderValue !== ''
    && (
        !ctype_digit((string) $getFolderValue)
        || (int) $getFolderValue < 1
    )
) {
    http_response_code(400);
    exit('Invalid folder ID.');
}

if ($getFolderValue !== '') {
    $getFolderId = (int) $getFolderValue;
}

// Get only folders belonging to the logged-in user.
$stmt = $pdo->prepare(
    'SELECT id, folder_name, visibility
     FROM reviewer_folders
     WHERE user_id = ?
     ORDER BY folder_name ASC'
);
$stmt->execute([$userId]);
$folders = $stmt->fetchAll();

// Verify the folder passed through the URL belongs to this user.
if ($getFolderId !== null) {
    $folderExists = false;

    foreach ($folders as $folder) {
        if ((int) $folder['id'] === $getFolderId) {
            $folderExists = true;
            break;
        }
    }

    if (!$folderExists) {
        http_response_code(404);
        exit('Folder not found or you do not have permission to upload to it.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $subject = trim($_POST['subject'] ?? '');

        // POST selection takes priority; otherwise use the URL folder.
        $folderValue = $_POST['folder_id'] ?? (string) ($getFolderId ?? '');
        $folderId = null;

        if ($title === '' || mb_strlen($title) > 200) {
            $error = 'Title is required and must be under 200 characters.';
        } elseif ($subject === '' || mb_strlen($subject) > 100) {
            $error = 'Subject is required and must be under 100 characters.';
        } elseif (
            $folderValue !== ''
            && (
                !ctype_digit((string) $folderValue)
                || (int) $folderValue < 1
            )
        ) {
            $error = 'Invalid folder selection.';
        } else {
            // Verify folder ownership before accepting the selection.
            if ($folderValue !== '') {
                $folderId = (int) $folderValue;

                $check = $pdo->prepare(
                    'SELECT id
                     FROM reviewer_folders
                     WHERE id = ? AND user_id = ?'
                );
                $check->execute([$folderId, $userId]);

                if (!$check->fetch()) {
                    $error = 'That folder does not exist or does not belong to you.';
                }
            }

            $files = $_FILES['reviewers'] ?? null;

            if (
                $error === ''
                && (
                    !$files
                    || !isset($files['name'])
                    || !is_array($files['name'])
                    || count($files['name']) === 0
                )
            ) {
                $error = 'Please select at least one file.';
            }

            $validFiles = [];
            $maxSize = 10 * 1024 * 1024;
            $maxFiles = 20;

            if ($error === '') {
                $total = count($files['name']);

                if ($total > $maxFiles) {
                    $error = 'You can upload up to 20 files at a time.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);

                    for ($i = 0; $i < $total; $i++) {
                        if (
                            !isset(
                                $files['error'][$i],
                                $files['size'][$i],
                                $files['tmp_name'][$i],
                                $files['name'][$i]
                            )
                            || $files['error'][$i] !== UPLOAD_ERR_OK
                        ) {
                            $error = 'One of your files failed to upload.';
                            break;
                        }

                        if ($files['size'][$i] > $maxSize) {
                            $error = 'Each file must be 10 MB or smaller.';
                            break;
                        }

                        if (!is_uploaded_file($files['tmp_name'][$i])) {
                            $error = 'Invalid uploaded file.';
                            break;
                        }

                        $originalName = basename($files['name'][$i]);
                        $extension = strtolower(
                            pathinfo($originalName, PATHINFO_EXTENSION)
                        );
                        $mime = $finfo->file($files['tmp_name'][$i]);

                        $validMime = (
                            $extension === 'pdf'
                            && $mime === 'application/pdf'
                        ) || (
                            $extension === 'docx'
                            && in_array(
                                $mime,
                                [
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/zip',
                                    'application/x-zip'
                                ],
                                true
                            )
                        );

                        if (!$validMime) {
                            $error = 'Only valid PDF and DOCX files are allowed.';
                            break;
                        }

                        $validFiles[] = [
                            'tmp_name' => $files['tmp_name'][$i],
                            'original_name' => $originalName,
                            'extension' => $extension
                        ];
                    }
                }
            }

            if ($error === '') {
                $uploadDir = __DIR__ . '/uploads/';

                if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                    $error = 'The uploads folder is missing or not writable.';
                } else {
                    $savedPaths = [];

                    try {
                        $pdo->beginTransaction();

                        $insert = $pdo->prepare(
                            'INSERT INTO reviewers
                             (
                                 user_id,
                                 title,
                                 subject,
                                 filename,
                                 original_name,
                                 status,
                                 folder_id
                             )
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );

                        foreach ($validFiles as $item) {
                            $storedName = bin2hex(random_bytes(24))
                                . '.' . $item['extension'];

                            $destination = $uploadDir . $storedName;

                            if (!move_uploaded_file(
                                $item['tmp_name'],
                                $destination
                            )) {
                                throw new RuntimeException(
                                    'A file could not be saved.'
                                );
                            }

                            $savedPaths[] = $destination;

                            $insert->execute([
                                $userId,
                                $title,
                                $subject,
                                $storedName,
                                $item['original_name'],
                                'approved',
                                $folderId
                            ]);
                        }

                        $pdo->commit();

                        $success = count($validFiles)
                            . ' reviewer file(s) uploaded successfully!';

                        // Keep the selected folder after a successful upload.
                        $_POST = [];
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        foreach ($savedPaths as $path) {
                            if (is_file($path)) {
                                unlink($path);
                            }
                        }

                        error_log('[GreenReview upload] ' . $e->getMessage());

                        $error = 'Upload failed. Please check your files and try again.';
                    }
                }
            }
        }
    }
}

// Determine which folder should remain selected in the form.
$selectedFolderId = $_POST['folder_id']
    ?? ($getFolderId !== null ? (string) $getFolderId : '');

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !isset($_POST['folder_id'])
    && $getFolderId !== null
) {
    $selectedFolderId = (string) $getFolderId;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Upload Reviewers | GreenReview</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="nav-wrap">
        <a class="brand" href="index.php">🍃 GreenReview</a>

        <nav class="nav-links">
            <a href="index.php">Library</a>
            <a href="folders.php">My Folders</a>
            <a class="active" href="upload.php">Upload</a>

            <form action="logout.php" method="post" class="inline-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >
                <button class="nav-logout" type="submit">Log out</button>
            </form>
        </nav>
    </div>
</header>

<main class="page-container narrow">
    <p class="eyebrow">SHARE WHAT YOU KNOW</p>

    <h1>Upload reviewers <span class="leaf">✿</span></h1>

    <p class="muted">
        Add one or more PDF/DOCX files to your study shelf.
    </p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success">
            <?= e($success) ?>

            <?php if ($selectedFolderId !== ''): ?>
                <p>
                    <a href="folder.php?id=<?= (int) $selectedFolderId ?>">
                        Return to your folder →
                    </a>
                </p>
            <?php else: ?>
                <p>
                    <a href="index.php">View Public Library →</a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <form
            method="post"
            enctype="multipart/form-data"
            class="form-stack"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <label for="title">Reviewer title</label>
            <input
                id="title"
                name="title"
                type="text"
                maxlength="200"
                required
                placeholder="e.g. Midterm Exam Reviewer"
                value="<?= e($_POST['title'] ?? '') ?>"
            >

            <label for="subject">Subject / course</label>
            <input
                id="subject"
                name="subject"
                type="text"
                maxlength="100"
                required
                placeholder="e.g. Computer Programming"
                value="<?= e($_POST['subject'] ?? '') ?>"
            >

            <label for="folder_id">Choose a folder</label>

            <select id="folder_id" name="folder_id">
                <option
                    value=""
                    <?= $selectedFolderId === '' ? 'selected' : '' ?>
                >
                    No folder — Public Library
                </option>

                <?php foreach ($folders as $folder): ?>
                    <option
                        value="<?= (int) $folder['id'] ?>"
                        <?= (string) $selectedFolderId === (string) $folder['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($folder['folder_name']) ?>
                        (<?= e($folder['visibility']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="reviewers">Choose files</label>

            <input
                id="reviewers"
                name="reviewers[]"
                type="file"
                accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                multiple
                required
            >

            <p class="help-text">
                PDF or DOCX · Up to 20 files · Maximum 10 MB per file
            </p>

            <div class="notice">
                🌱 Only upload materials you have permission to share.
            </div>

            <button class="btn btn-primary btn-full" type="submit">
                Upload reviewer files ↑
            </button>
        </form>
    </section>
</main>

</body>
</html>
