
<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!verifyCsrf()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];

            header('Location: index.php');
            exit;
        }

        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In | GreenReview</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand brand-center" href="index.php">🍃 GreenReview</a>
        <p class="eyebrow">WELCOME BACK</p>
        <h1>Let's study smarter.</h1>
        <p class="muted">Log in to share your reviewers with everyone.</p>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert success">
                Account created! You can now log in.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token"
                   value="<?= e(csrfToken()) ?>">

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" required
                   autocomplete="email" value="<?= e($email) ?>"
                   placeholder="you@example.com">

            <label for="password">Password</label>
            <input id="password" name="password" type="password"
                   required autocomplete="current-password"
                   placeholder="Your password">

            <button class="btn btn-primary btn-full" type="submit">
                Log in 🌿
            </button>
        </form>

        <p class="auth-switch">
            New here? <a href="register.php">Create an account</a>
        </p>
        <p class="auth-switch"><a href="index.php">← Browse public reviewers</a></p>
    </main>
</body>
</html>
