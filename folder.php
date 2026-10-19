
<?php
require_once __DIR__ . '/includes/auth.php';

// Validate folder ID
$folderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$folderId || $folderId < 1) {
    http_response_code(400);
    exit('Invalid folder ID.');
}

// Get folder and its owner's name
$stmt = $pdo->prepare(
    "SELECT f.id, f.user_id, f.folder_name, f.visibility,
            f.created_at, u.name AS owner_name
     FROM reviewer_folders f
     JOIN users u ON u.id = f.user_id
     WHERE f.id = ?
     LIMIT 1"
);
$stmt->execute([$folderId]);
$folder = $stmt->fetch();

if (!$folder) {
    http_response_code(404);
    exit('Folder not found. It may have been deleted.');
}

// Determine whether the current user owns this folder
$isOwner = isLoggedIn()
    && (int) $_SESSION['user_id'] === (int) $folder['user_id'];

// Private folders can only be viewed by their owner
if ($folder['visibility'] === 'private' && !$isOwner) {
    http_response_code(403);
    exit('This folder is private. Only its owner can view it.');
}

// Get approved reviewers in this folder
$stmt = $pdo->prepare(
    "SELECT r.id, r.user_id, r.title, r.subject,
            r.original_name, r.filename, r.uploaded_at,
            u.name AS uploader
     FROM reviewers r
     JOIN users u ON u.id = r.user_id
     WHERE r.folder_id = ?
       AND r.user_id = ?
       AND r.status = 'approved'
     ORDER BY r.uploaded_at DESC"
);
$stmt->execute([
    $folderId,
    $folder['user_id']
]);
$reviewers = $stmt->fetchAll();

function folderFileName(array $reviewer): string {
    return $reviewer['original_name']
        ?: $reviewer['filename'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($folder['folder_name']) ?> | GreenReview</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .folder-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 22px 70px;
        }

        .folder-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .folder-heading {
            background: linear-gradient(135deg, #eaf5e9, #f7fbf4);
            border: 1px solid #d8e8d5;
            border-radius: 22px;
            padding: 28px;
            margin-bottom: 30px;
        }

        .folder-heading h1 {
            margin: 8px 0;
            color: #28583a;
            overflow-wrap: anywhere;
        }

        .folder-heading p {
            color: #607463;
            margin-bottom: 0;
        }

        .folder-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #dceedd;
            color: #28583a;
            font-size: 13px;
            font-weight: 600;
        }

        .folder-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(min(100%, 240px), 1fr)
            );
            gap: 20px;
        }

        .folder-reviewer {
            min-width: 0;
            padding: 22px;
            background: #fff;
            border: 1px solid #e0eadf;
            border-radius: 18px;
            box-shadow: 0 5px 18px rgba(38, 78, 48, .06);
            display: flex;
            flex-direction: column;
        }

        .folder-file-icon {
            font-size: 34px;
            margin-bottom: 12px;
        }

        .folder-reviewer h2 {
            font-size: 19px;
            color: #28583a;
            overflow-wrap: anywhere;
            margin: 12px 0 8px;
        }

        .folder-reviewer p {
            overflow-wrap: anywhere;
        }

        .folder-file-name {
            color: #738174;
            font-size: 13px;
        }

        .folder-meta {
            font-size: 13px;
            color: #718071;
            margin-top: auto;
            padding: 12px 0;
        }

        .folder-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .folder-actions .btn {
            text-align: center;
        }

        .folder-empty {
            text-align: center;
            padding: 48px 20px;
            background: #f7fbf5;
            border: 1px dashed #bfd5bd;
            border-radius: 20px;
        }

        .folder-empty .empty-symbol {
            font-size: 42px;
        }

        .folder-empty h2 {
            color: #28583a;
        }

        .folder-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            color: #356746;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .folder-page {
                padding: 25px 15px 45px;
            }

            .folder-heading {
                padding: 22px;
            }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="nav-wrap">
        <a class="brand" href="index.php">🍃 GreenReview</a>

        <nav class="nav-links">
            <a href="index.php">🏡 Library</a>

            <?php if (isLoggedIn()): ?>
                <a href="upload.php">＋ Upload</a>
                <a href="folders.php">📁 My Uploads</a>
            <?php else: ?>
                <a href="login.php">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="folder-page">

    <div class="folder-top">
        <a class="folder-back" href="folders.php">
            ← Back to My Uploads
        </a>

        <?php if ($isOwner): ?>
            <a class="btn btn-primary" href="upload.php">
                ＋ Upload a reviewer
            </a>
        <?php endif; ?>
    </div>

    <section class="folder-heading">
        <span class="folder-badge">
            <?= $folder['visibility'] === 'public'
                ? '🌎 Public folder'
                : '🔒 Private folder' ?>
        </span>

        <h1>📁 <?= e($folder['folder_name']) ?></h1>

        <p>
            Created by <?= e($folder['owner_name']) ?>
            · <?= count($reviewers) ?> reviewer(s)
        </p>

        <?php if ($isOwner): ?>
            <p style="margin-top: 12px;">
                This is your folder. You can manage its reviewers
                from the My Uploads page.
            </p>
        <?php endif; ?>
    </section>

    <div class="section-heading">
        <div>
            <p class="eyebrow">YOUR STUDY SHELF</p>
            <h2>Reviewers in this folder</h2>
        </div>
    </div>

    <?php if ($reviewers): ?>
        <div class="folder-grid">
            <?php foreach ($reviewers as $reviewer): ?>
                <?php
                $fileName = folderFileName($reviewer);
                $extension = strtolower(
                    pathinfo($fileName, PATHINFO_EXTENSION)
                );
                $isPdf = $extension === 'pdf';
                ?>

                <article class="folder-reviewer">
                    <div class="folder-file-icon">
                        <?= $isPdf ? '📕' : '📘' ?>
                    </div>

                    <span class="subject-tag">
                        <?= e($reviewer['subject']) ?>
                    </span>

                    <h2><?= e($reviewer['title']) ?></h2>

                    <p class="folder-file-name">
                        <?= e($fileName) ?>
                    </p>

                    <div class="folder-meta">
                        <div>✎ <?= e($reviewer['uploader']) ?></div>
                        <div>
                            <?= e(date(
                                'M j, Y',
                                strtotime($reviewer['uploaded_at'])
                            )) ?>
                        </div>
                    </div>

                    <div class="folder-actions">
                        <a
                            class="btn btn-card"
                            href="view.php?id=<?= (int) $reviewer['id'] ?>"
                        >
                            View reviewer →
                        </a>

                        <?php if ($isOwner): ?>
                            <a
                                class="btn btn-secondary"
                                href="folders.php"
                            >
                                Manage
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <section class="folder-empty">
            <div class="empty-symbol">🌱</div>

            <h2>This folder is waiting for reviewers</h2>

            <p>
                There are no approved reviewers in this folder yet.
                Upload a study material or move one of your existing
                reviewers here.
            </p>

            <?php if ($isOwner): ?>
                <a class="btn btn-primary" href="upload.php">
                    ＋ Upload a reviewer
                </a>

                <a
                    class="btn btn-secondary"
                    href="folders.php"
                    style="margin-left: 6px;"
                >
                    Manage uploads
                </a>
            <?php endif; ?>
        </section>
    <?php endif; ?>

</main>

<footer class="site-footer">
    <a class="brand" href="index.php">🍃 GreenReview</a>
    <p>Little notes can make a big difference. Study well, grow together. ♡</p>
</footer>

</body>
</html>
```