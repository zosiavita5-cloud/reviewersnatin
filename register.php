
<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!verifyCsrf()) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif ($name === '' || strlen($name) > 100) {
        $error = 'Please enter a name with up to 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)'
            );

            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'That email is already registered.';
            } else {
                error_log($e->getMessage());
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account | GreenReview</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand brand-center" href="index.php">🍃 GreenReview</a>
        <p class="eyebrow">YOUR STUDY COMMUNITY</p>
        <h1>Create your account</h1>
        <p class="muted">Join the community and share useful reviewers.</p>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token"
                   value="<?= e(csrfToken()) ?>">

            <label for="name">Full name</label>
            <input id="name" name="name" type="text"
                   maxlength="100" required autocomplete="name"
                   value="<?= e($name) ?>" placeholder="Your name">

            <label for="email">Email address</label>
            <input id="email" name="email" type="email"
                   maxlength="150" required autocomplete="email"
                   value="<?= e($email) ?>" placeholder="you@example.com">

            <label for="password">Password</label>
            <input id="password" name="password" type="password"
                   minlength="8" required autocomplete="new-password"
                   placeholder="At least 8 characters">

            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password"
                   type="password" minlength="8" required
                   autocomplete="new-password"
                   placeholder="Enter password again">

            <button class="btn btn-primary btn-full" type="submit">
                Create account ✨
            </button>
        </form>

        <p class="auth-switch">
            Already have an account?
            <a href="login.php">Log in</a>
        </p>
        <p class="auth-switch"><a href="index.php">← Back to library</a></p>
    </main>
</body>
</html>
