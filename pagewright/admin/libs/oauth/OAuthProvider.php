<?php
declare(strict_types=1);

interface OAuthProvider
{
    public function name(): string;

    public function authorizationUrl(string $redirectUri, string $state): string;

    /** Exchange code for access token */
    public function tokenFromCode(string $redirectUri, string $code): string;

    /** Fetch normalized user profile */
    public function fetchUser(string $accessToken): array;
}
