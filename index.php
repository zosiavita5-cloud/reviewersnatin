
<?php
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');

/*
|--------------------------------------------------------------------------
| Fetch public reviewer folders
|--------------------------------------------------------------------------
| Public folders appear even if they do not contain reviewers yet.
| Private folders are never displayed on the public homepage.
*/
$folderSql = "
    SELECT
        f.id,
        f.folder_name,
        f.created_at,
        u.name AS uploader,
        COUNT(
            CASE WHEN r.status = 'approved' THEN r.id END
        ) AS reviewer_count
    FROM reviewer_folders f
    JOIN users u ON u.id = f.user_id
    LEFT JOIN reviewers r
        ON r.folder_id = f.id
        AND r.user_id = f.user_id
    WHERE f.visibility = 'public'
    GROUP BY
        f.id,
        f.folder_name,
        f.created_at,
        u.name
    ORDER BY f.created_at DESC
";

$folderStmt = $pdo->query($folderSql);
$publicFolders = $folderStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Fetch public approved reviewers
|--------------------------------------------------------------------------
| Reviewers without a folder are part of the public library.
| Reviewers inside a folder appear only when that folder is public.
*/
$sql = "
    SELECT
        r.id,
        r.title,
        r.subject,
        r.original_name,
        r.filename,
        r.uploaded_at,
        u.name AS uploader
    FROM reviewers r
    JOIN users u ON u.id = r.user_id
    WHERE r.status = 'approved'
      AND (
          r.folder_id IS NULL
          OR EXISTS (
              SELECT 1
              FROM reviewer_folders f
              WHERE f.id = r.folder_id
                AND f.user_id = r.user_id
                AND f.visibility = 'public'
          )
      )
";

$params = [];

if ($search !== '') {
    $sql .= " AND (r.title LIKE ? OR r.subject LIKE ?)";
    $term = '%' . $search . '%';
    $params = [$term, $term];
}

$sql .= " ORDER BY r.uploaded_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviewers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>GreenReview | Public Reviewer Library</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .public-folders {
            margin-bottom: 48px;
        }

        .folder-card {
            display: flex;
            flex-direction: column;
        }

        .folder-card .btn-card {
            margin-top: auto;
        }

        .folder-description {
            color: #718071;
            font-size: 0.94rem;
            line-height: 1.6;
            margin: 8px 0 18px;
        }

        .folder-card .card-meta {
            margin-bottom: 18px;
        }

        .section-divider {
            border: 0;
            border-top: 1px solid #e5ece3;
            margin: 36px 0;
        }

        @media (max-width: 600px) {
            .section-heading {
                gap: 16px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="nav-wrap">
        <a class="brand" href="index.php">🍃 GreenReview</a>

        <nav class="nav-links">
            <a class="active" href="index.php">🏡 Library</a>

            <?php if (isLoggedIn()): ?>
                <a href="upload.php">＋ Upload</a>
                <a href="folders.php">📁 My Uploads</a>

                <span class="welcome-user">
                    Hi, <?= e(
                        $_SESSION['user_name']
                        ?? $_SESSION['name']
                        ?? 'Student'
                    ) ?>!
                </span>

                <form
                    action="logout.php"
                    method="post"
                    class="inline-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrfToken()) ?>"
                    >

                    <button class="nav-logout" type="submit">
                        Log out
                    </button>
                </form>
            <?php else: ?>
                <a href="login.php">Log in</a>

                <a
                    class="btn btn-small btn-primary"
                    href="register.php"
                >
                    Join us ✨
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main>
    <section class="hero">
        <div class="hero-inner">
            <span class="hero-pill">
                ✿ LEARN · SHARE · GROW
            </span>

            <h1>
                Your next exam starts<br>
                with a <span>good reviewer.</span>
            </h1>

            <p>
                A cozy little library of study materials shared by students,
                for students. Find your next study buddy in a file. 🌱
            </p>

            <form
                class="search-form"
                method="get"
                action="index.php"
            >
                <span class="search-symbol">⌕</span>

                <input
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Search by title or subject..."
                    aria-label="Search reviewers"
                >

                <button class="btn btn-primary" type="submit">
                    Search
                </button>
            </form>

            <div class="hero-note">
                🌿 Free to browse · Community-powered
            </div>
        </div>

        <div class="hero-decoration decoration-one">✿</div>
        <div class="hero-decoration decoration-two">❋</div>
    </section>

    <section class="page-container">

        <?php if ($search === ''): ?>
            <!-- PUBLIC REVIEWER FOLDERS -->
            <section class="public-folders">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">
                            ORGANIZED STUDY MATERIALS
                        </p>

                        <h2>📁 Public Reviewer Folders</h2>

                        <p class="muted">
                            Browse study materials organized by students.
                        </p>
                    </div>
                </div>

                <?php if (!empty($publicFolders)): ?>
                    <div class="reviewer-grid">
                        <?php foreach ($publicFolders as $folder): ?>
                            <article class="reviewer-card folder-card">
                                <div class="file-icon">📂</div>

                                <span class="subject-tag">
                                    PUBLIC FOLDER
                                </span>

                                <h3>
                                    <?= e($folder['folder_name']) ?>
                                </h3>

                                <p class="folder-description">
                                    Shared by
                                    <?= e($folder['uploader']) ?>
                                </p>

                                <div class="card-meta">
                                    <span>
                                        📄
                                        <?= (int)$folder['reviewer_count'] ?>
                                        reviewer(s)
                                    </span>

                                    <span>
                                        <?= e(date(
                                            'M j, Y',
                                            strtotime($folder['created_at'])
                                        )) ?>
                                    </span>
                                </div>

                                <a
                                    class="btn btn-card"
                                    href="folder.php?id=<?= (int)$folder['id'] ?>"
                                >
                                    Open folder →
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-symbol">📁</div>

                        <h3>No public folders yet</h3>

                        <p>
                            Public folders shared by students will appear here.
                        </p>

                        <?php if (isLoggedIn()): ?>
                            <a
                                class="btn btn-primary"
                                href="folders.php"
                            >
                                Manage my folders
                            </a>
                        <?php else: ?>
                            <a
                                class="btn btn-primary"
                                href="register.php"
                            >
                                Join GreenReview ✨
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <hr class="section-divider">
        <?php endif; ?>

        <!-- PUBLIC REVIEWER LIBRARY -->
        <div class="section-heading">
            <div>
                <p class="eyebrow">THE STUDY SHELF</p>

                <h2>
                    <?= $search !== ''
                        ? 'Search results'
                        : 'Public reviewers' ?>
                </h2>

                <p class="muted">
                    <?= count($reviewers) ?> reviewer(s) found

                    <?php if ($search !== ''): ?>
                        for “<?= e($search) ?>”
                    <?php endif; ?>
                </p>
            </div>

            <?php if (isLoggedIn()): ?>
                <a class="btn btn-secondary" href="upload.php">
                    ＋ Share a reviewer
                </a>
            <?php else: ?>
                <a class="btn btn-secondary" href="register.php">
                    Share your notes ✨
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($reviewers)): ?>
            <div class="reviewer-grid">
                <?php foreach ($reviewers as $reviewer): ?>
                    <?php
                    $fileName = $reviewer['original_name']
                        ?: $reviewer['filename'];

                    $extension = strtolower(
                        pathinfo($fileName, PATHINFO_EXTENSION)
                    );

                    $isPdf = $extension === 'pdf';
                    ?>

                    <article class="reviewer-card">
                        <div class="file-icon">
                            <?= $isPdf ? '📕' : '📘' ?>
                        </div>

                        <span class="subject-tag">
                            <?= e($reviewer['subject']) ?>
                        </span>

                        <h3>
                            <?= e($reviewer['title']) ?>
                        </h3>

                        <p class="file-name">
                            <?= e($fileName) ?>
                        </p>

                        <div class="card-meta">
                            <span>
                                ✎ <?= e($reviewer['uploader']) ?>
                            </span>

                            <span>
                                <?= e(date(
                                    'M j, Y',
                                    strtotime($reviewer['uploaded_at'])
                                )) ?>
                            </span>
                        </div>

                        <a
                            class="btn btn-card"
                            href="view.php?id=<?= (int)$reviewer['id'] ?>"
                        >
                            View reviewer →
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-symbol">🌱</div>

                <h3>No reviewers found yet</h3>

                <p>
                    Try another search, or be the first to share a reviewer
                    with the community.
                </p>

                <?php if (isLoggedIn()): ?>
                    <a class="btn btn-primary" href="upload.php">
                        Upload a reviewer
                    </a>
                <?php else: ?>
                    <a class="btn btn-primary" href="register.php">
                        Join GreenReview ✨
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </section>
</main>

<footer class="site-footer">
    <a class="brand" href="index.php">🍃 GreenReview</a>

    <p>
        Little notes can make a big difference.
        Study well, grow together. ♡
    </p>
</footer>

</body>
</html>
