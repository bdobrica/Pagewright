<?php
declare(strict_types=1);

interface OAuthProvider
{
    /**
     * Get the provider name
     * @return string Provider identifier (e.g., 'github', 'google')
     */
    public function name(): string;

    /**
     * Generate the OAuth authorization URL
     * @param string $redirectUri Callback URL after authorization
     * @param string $state CSRF protection state parameter
     * @return string Authorization URL to redirect user to
     */
    public function authorizationUrl(string $redirectUri, string $state): string;

    /**
     * Exchange authorization code for access token
     * @param string $redirectUri Callback URL (must match authorization request)
     * @param string $code Authorization code from provider
     * @return string Access token
     * @throws RuntimeException if token exchange fails
     */
    public function tokenFromCode(string $redirectUri, string $code): string;

    /**
     * Fetch normalized user profile using access token
     * @param string $accessToken OAuth access token
     * @return array{provider: string, subject: string, email: string, name: string, avatar: string} Normalized user data
     * @throws RuntimeException if user fetch fails
     */
    public function fetchUser(string $accessToken): array;
}
