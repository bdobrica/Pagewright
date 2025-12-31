<?php
declare(strict_types=1);

require_once __DIR__ . '/../load.php';

Session::start();
Storage::ensureStorage();

// Rate limit OAuth callbacks to prevent abuse
$limiter = new RateLimiter('oauth_callback', 10, 300); // 10 attempts per 5 minutes

if ($limiter->isLimited()) {
    $resetTime = $limiter->getResetTime();
    $minutes = ceil($resetTime / 60);
    Http::redirect(Http::adminUrl('error=' . rawurlencode("Too many authentication attempts. Please try again in $minutes minute(s).")));
}

try {
    $code  = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $err   = $_GET['error'] ?? '';

    if ($err) {
        $limiter->recordAttempt();
        Logger::security('OAuth provider returned error', ['error' => $err]);
        throw new RuntimeException('Authentication provider error. Please try again.');
    }
    if (!$code) {
        $limiter->recordAttempt();
        Logger::security('Missing OAuth authorization code');
        throw new RuntimeException('Authentication failed. Missing authorization code.');
    }
    if (!$state || empty($_SESSION['oauth_state']) || !hash_equals((string)$_SESSION['oauth_state'], (string)$state)) {
        $limiter->recordAttempt();
        Logger::security('OAuth state mismatch (possible CSRF)', [
            'expected' => $_SESSION['oauth_state'] ?? 'none',
            'received' => $state,
        ]);
        throw new RuntimeException('Security validation failed. Please try again.');
    }

    $providerName = (string)($_SESSION['oauth_provider'] ?? '');
    if ($providerName === '') {
        throw new RuntimeException('Missing provider in session.');
    }

    $provider = OAuthManager::get($providerName);

    $accessToken = $provider->tokenFromCode(OAuthManager::callbackUrl(), $code);
    $profile = $provider->fetchUser($accessToken);

    // First-login bootstrap OR existing admin check
    $existing = Storage::findAdmin($profile['provider'], $profile['subject']);
    $adminCount = Storage::adminCount();

    if ($adminCount === 0) {
        Storage::addAdmin([
            'provider' => $profile['provider'],
            'subject'  => $profile['subject'],
            'email'    => $profile['email'],
            'name'     => $profile['name'],
            'created_at' => gmdate('c'),
        ]);
        $existing = Storage::findAdmin($profile['provider'], $profile['subject']);
    }

    if (!$existing) {
        Logger::security('Unauthorized admin login attempt', [
            'provider' => $profile['provider'],
            'email' => $profile['email'],
        ]);
        throw new RuntimeException('This account is not authorized. Please contact an administrator.');
    }

    Logger::info('User logged in successfully', [
        'provider' => $profile['provider'],
        'email' => $profile['email'],
    ]);

    Session::login($profile);

    // Clear rate limiter on successful login
    $limiter->clear();

    // Cleanup transient oauth session keys
    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

    Http::redirect(Http::adminUrl());
} catch (Throwable $e) {
    // Record failed attempt
    $limiter->recordAttempt();
    // Cleanup transient oauth session keys
    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

    Http::redirect(Http::adminUrl('error=' . rawurlencode($e->getMessage())));
}
