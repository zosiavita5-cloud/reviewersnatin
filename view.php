
<?php
require_once __DIR__ . '/includes/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid reviewer ID.');
}

// Get reviewer and its folder visibility.
$stmt = $pdo->prepare(
    "SELECT
        r.*,
        u.name AS uploader,
        f.visibility AS folder_visibility,
        f.user_id AS folder_owner_id
     FROM reviewers r
     JOIN users u ON u.id = r.user_id
     LEFT JOIN reviewer_folders f ON f.id = r.folder_id
     WHERE r.id = ?
       AND r.status = 'approved'
     LIMIT 1"
);

$stmt->execute([$id]);
$reviewer = $stmt->fetch();

if (!$reviewer) {
    http_response_code(404);
    exit('Reviewer not found.');
}

// Enforce folder privacy before serving any file.
if ($reviewer['folder_id'] !== null) {
    // If the folder was deleted or its record is missing, deny access.
    if ($reviewer['folder_visibility'] === null) {
        http_response_code(404);
        exit('Reviewer folder not found.');
    }

    $isOwner = isset($_SESSION['user_id'])
        && (int) $_SESSION['user_id'] === (int) $reviewer['user_id'];

    $isPrivate = $reviewer['folder_visibility'] === 'private';

    if ($isPrivate && !$isOwner) {
        // Use 404 so private folder contents are not disclosed.
        http_response_code(404);
        exit('Reviewer not found.');
    }

    // Ensure the folder belongs to the uploader.
    if ((int) $reviewer['folder_owner_id'] !== (int) $reviewer['user_id']) {
        http_response_code(404);
        exit('Reviewer folder not found.');
    }
}

// Validate file extension.
$originalName = $reviewer['original_name'] ?: $reviewer['filename'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, ['pdf', 'docx'], true)) {
    http_response_code(415);
    exit('Unsupported file type.');
}

// Resolve the stored file safely inside /uploads.
$uploadDir = realpath(__DIR__ . '/uploads');

$filePath = $uploadDir
    ? realpath($uploadDir . DIRECTORY_SEPARATOR . $reviewer['filename'])
    : false;

if (
    !$uploadDir ||
    !$filePath ||
    dirname($filePath) !== $uploadDir ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit('The reviewer file could not be found.');
}

$download = isset($_GET['download']) && $_GET['download'] === '1';

$safeName = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    $originalName
);

if ($download || $extension === 'docx') {
    $mime = $extension === 'pdf'
        ? 'application/pdf'
        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

if ($extension === 'pdf') {
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $safeName . '"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

http_response_code(415);
exit('Unsupported file type.');
