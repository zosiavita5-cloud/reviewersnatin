
<?php
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');

// Show only approved reviewers that are either:
// 1. Not assigned to a folder (public library), or
// 2. Inside a folder marked public and owned by the uploader.
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
                    Hi, <?= e($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Student') ?>!
                </span>

                <form action="logout.php" method="post" class="inline-form">
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
                <a class="btn btn-small btn-primary" href="register.php">
                    Join us ✨
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main>
    <section class="hero">
        <div class="hero-inner">
            <span class="hero-pill">✿ LEARN · SHARE · GROW</span>

            <h1>
                Your next exam starts<br>
                with a <span>good reviewer.</span>
            </h1>

            <p>
                A cozy little library of study materials shared by students,
                for students. Find your next study buddy in a file. 🌱
            </p>

            <form class="search-form" method="get" action="index.php">
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
        <div class="section-heading">
            <div>
                <p class="eyebrow">THE STUDY SHELF</p>

                <h2>
                    <?= $search !== '' ? 'Search results' : 'Public reviewers' ?>
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

        <?php if ($reviewers): ?>
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

                        <h3><?= e($reviewer['title']) ?></h3>

                        <p class="file-name">
                            <?= e($fileName) ?>
                        </p>

                        <div class="card-meta">
                            <span>✎ <?= e($reviewer['uploader']) ?></span>

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