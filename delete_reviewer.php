
<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!verifyCsrf()) {
    http_response_code(403);
    exit('Invalid request token. Please go back and try again.');
}

$reviewerId = filter_input(INPUT_POST, 'reviewer_id', FILTER_VALIDATE_INT);

if (!$reviewerId || $reviewerId < 1) {
    http_response_code(400);
    exit('Invalid reviewer ID.');
}

$userId = (int) $_SESSION['user_id'];

// Only retrieve a reviewer owned by the currently logged-in user.
$stmt = $pdo->prepare(
    'SELECT id, filename, folder_id
     FROM reviewers
     WHERE id = ? AND user_id = ?
     LIMIT 1'
);
$stmt->execute([$reviewerId, $userId]);
$reviewer = $stmt->fetch();

if (!$reviewer) {
    http_response_code(404);
    exit('Reviewer not found, or you do not have permission to delete it.');
}

$uploadDir = realpath(__DIR__ . '/uploads');
$filePath = false;

if ($uploadDir) {
    $filePath = realpath(
        $uploadDir . DIRECTORY_SEPARATOR . $reviewer['filename']
    );
}

// Refuse deletion if the stored path is invalid or outside uploads.
if (
    !$uploadDir ||
    !$filePath ||
    dirname($filePath) !== $uploadDir ||
    !is_file($filePath)
) {
    http_response_code(500);
    exit('The stored reviewer file could not be safely located. Nothing was deleted.');
}

try {
    $pdo->beginTransaction();

    // Re-check ownership while deleting the database record.
    $delete = $pdo->prepare(
        'DELETE FROM reviewers
         WHERE id = ? AND user_id = ?'
    );
    $delete->execute([$reviewerId, $userId]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('Reviewer ownership check failed.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    exit('Could not delete the reviewer. Please try again.');
}

// Delete the physical file after the database deletion succeeds.
if (is_file($filePath) && !unlink($filePath)) {
    error_log('Could not remove uploaded reviewer file: ' . $filePath);
    exit('The reviewer record was deleted, but the stored file could not be removed. Please contact the site administrator.');
}

// Redirect back to the folder if it belonged to one; otherwise return to library.
if ($reviewer['folder_id'] !== null) {
    header('Location: folder.php?id=' . (int) $reviewer['folder_id'] . '&deleted=1');
} else {
    header('Location: index.php?deleted=1');
}
exit;
