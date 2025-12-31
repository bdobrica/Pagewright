<?php
declare(strict_types=1);

require_once __DIR__ . '/load.php';

Session::start();
Storage::ensureStorage();

$error = $_GET['error'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$providerName = $_POST['provider'] ?? $_GET['provider'] ?? '';

if ($action === 'login') {
    $limiter = new RateLimiter('login', 5, 300); // 5 attempts per 5 minutes
    
    if ($limiter->isLimited()) {
        $resetTime = $limiter->getResetTime();
        $minutes = ceil($resetTime / 60);
        Http::redirect(Http::adminUrl('error=' . rawurlencode("Too many login attempts. Please try again in $minutes minute(s).")));
    }
    
    try {
        // Validate CSRF token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Session::validateCsrfToken($csrfToken)) {
            $limiter->recordAttempt();
            Logger::security('Invalid CSRF token on login attempt');
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
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
        $limiter->recordAttempt();
        $errorMessage = Logger::sanitizeException($e, 'Authentication failed. Please try again.');
        Http::redirect(Http::adminUrl('error=' . rawurlencode($errorMessage)));
    }
}

// Validate session and check for timeout
$isLoggedIn = Session::validate();
$user = Session::user();
$adminCount = Storage::adminCount();
$sessionExpired = isset($_GET['session_expired']) && $_GET['session_expired'] === '1';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?> Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/picocss/2.1.1/pico.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars(Http::baseUrl() . '/assets/css/editor.css') ?>">
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

  <?php if ($sessionExpired): ?>
    <article aria-label="Session Expired" style="border-left: 4px solid var(--pico-color-amber-500, #f59e0b); padding-left: 1rem;">
      <strong>Session expired:</strong>
      <p>Your session has expired due to inactivity. Please sign in again.</p>
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
    <h1>✨ Edit Your Site</h1>
    <p class="text-muted">Tell the AI what you want to change - it will figure out the rest.</p>
    
    <!-- Chat Container -->
    <div class="chat-container">
      
      <!-- Conversation History -->
      <div id="conversation" class="conversation">
        <div class="welcome-message">
          <h3>👋 Welcome to Pagewright!</h3>
          <p>I can help you edit your website. Just tell me what you'd like to do.</p>
          <p><strong>Examples:</strong></p>
          <ul>
            <li>"Add a contact page with email and phone number"</li>
            <li>"Update the home page with a pricing section"</li>
            <li>"Create a blog page with recent posts"</li>
            <li>"Change the site colors to blue and white"</li>
          </ul>
        </div>
      </div>
      
      <!-- Input Area -->
      <div class="input-area">
        <div class="input-wrapper">
          <textarea 
            id="prompt-input" 
            placeholder="What would you like to do?"
            rows="3"></textarea>
          <button id="send-btn" type="button">Send</button>
        </div>
        <div class="action-bar">
          <button id="reset-btn" type="button" class="secondary">New Conversation</button>
          <button id="publish-all-btn" type="button" class="contrast">Publish Site</button>
          <a href="/?preview=true" target="_blank" class="preview-link">View Preview →</a>
        </div>
      </div>
      
    </div>
    
    <!-- Account Info (collapsed) -->
    <details class="account-details">
      <summary>👤 Account</summary>
      <ul>
        <li><strong>Provider:</strong> <?= htmlspecialchars($user['provider'] ?? '') ?></li>
        <li><strong>Name:</strong> <?= htmlspecialchars($user['name'] ?? '') ?></li>
        <li><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '') ?></li>
      </ul>
    </details>
    
    <script src="<?= htmlspecialchars(Http::baseUrl() . '/assets/js/editor.js') ?>"></script>
  <?php endif; ?>

</main>
</body>
</html>
