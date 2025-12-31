<?php
declare(strict_types=1);

require_once __DIR__ . '/../load.php';

Session::start();
Storage::ensureStorage();

try {
    $code  = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $err   = $_GET['error'] ?? '';

    if ($err) {
        throw new RuntimeException('Provider returned error: ' . $err);
    }
    if (!$code) {
        throw new RuntimeException('Missing authorization code.');
    }
    if (!$state || empty($_SESSION['oauth_state']) || !hash_equals((string)$_SESSION['oauth_state'], (string)$state)) {
        throw new RuntimeException('Invalid state (possible CSRF).');
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
        throw new RuntimeException('This account is not an admin on this Pagewright install.');
    }

    Session::login($profile);

    // Cleanup transient oauth session keys
    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

    Http::redirect(Http::adminUrl());
} catch (Throwable $e) {
    // Cleanup transient oauth session keys
    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

    Http::redirect(Http::adminUrl('error=' . rawurlencode($e->getMessage())));
}
