<?php
declare(strict_types=1);

require_once __DIR__ . '/load.php';

Session::start();
Storage::ensureStorage();

$error = $_GET['error'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$providerName = $_POST['provider'] ?? $_GET['provider'] ?? '';

if ($action === 'login') {
    try {
        // Validate CSRF token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Session::validateCsrfToken($csrfToken)) {
            throw new RuntimeException('Invalid CSRF token. Please try again.');
        }

        $provider = OAuthManager::get($providerName);
        $state = OAuthManager::newState();
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_provider'] = $provider->name();

        // Regenerate CSRF token after successful validation
        Session::regenerateCsrfToken();

        $authUrl = $provider->authorizationUrl(OAuthManager::callbackUrl(), $state);
        Http::redirect($authUrl);
    } catch (Throwable $e) {
        Http::redirect(Http::adminUrl('error=' . rawurlencode($e->getMessage())));
    }
}

$isLoggedIn = Session::isLoggedIn();
$user = Session::user();
$adminCount = Storage::adminCount();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?> Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/picocss/2.1.1/pico.min.css">
</head>
<body>
<main class="container">
  <nav>
    <ul><li><strong><?= htmlspecialchars(APP_NAME) ?></strong></li></ul>
    <ul>
      <?php if ($isLoggedIn): ?>
        <li><a href="<?= htmlspecialchars(Http::baseUrl() . '/oauth/logout.php') ?>">Logout</a></li>
      <?php endif; ?>
    </ul>
  </nav>

  <?php if ($error): ?>
    <article aria-label="Error" style="border-left: 4px solid var(--pico-del-color, #d93526); padding-left: 1rem;">
      <strong>Authentication error:</strong>
      <p><?= htmlspecialchars($error) ?></p>
    </article>
  <?php endif; ?>

  <?php if (!$isLoggedIn): ?>
    <h1>Sign in</h1>
    <p>
      <?php if ($adminCount === 0): ?>
        First login will create the initial admin user.
      <?php else: ?>
        Only approved admins can access this area.
      <?php endif; ?>
    </p>

    <article>
      <form method="post" action="">
        <label for="provider">OAuth provider</label>
        <select id="provider" name="provider" required>
          <option value="" selected disabled>Choose…</option>
          <option value="google">Google</option>
          <option value="github">GitHub</option>
        </select>

        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
        <button type="submit">Continue</button>
      </form>
    </article>

  <?php else: ?>
    <h1>Dashboard</h1>
    <article>
      <p>You're logged in.</p>
      <ul>
        <li><strong>Provider:</strong> <?= htmlspecialchars($user['provider'] ?? '') ?></li>
        <li><strong>Name:</strong> <?= htmlspecialchars($user['name'] ?? '') ?></li>
        <li><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '') ?></li>
      </ul>
      <p style="opacity:0.8">Next: add your “Prompt → Preview → Publish” screen here.</p>
    </article>
  <?php endif; ?>

</main>
</body>
</html>
