<?php
declare(strict_types=1);

final class OAuthManager
{
    /** @return array<string, OAuthProvider> */
    public static function providers(): array
    {
        return [
            'google' => new GoogleProvider(),
            'github' => new GitHubProvider(),
        ];
    }

    public static function get(string $name): OAuthProvider
    {
        $providers = self::providers();
        if (!isset($providers[$name])) {
            throw new InvalidArgumentException('Unknown OAuth provider.');
        }
        return $providers[$name];
    }

    public static function callbackUrl(): string
    {
        return Http::baseUrl() . '/oauth/callback.php';
    }

    public static function newState(): string
    {
        return bin2hex(random_bytes(16));
    }
}
