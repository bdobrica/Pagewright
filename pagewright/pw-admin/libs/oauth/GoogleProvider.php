<?php
declare(strict_types=1);

final class GoogleProvider implements OAuthProvider
{
    private HttpClient $http;

    public function __construct()
    {
        $this->http = new HttpClient();
    }

    /**
     * Get provider name
     * @return string Provider identifier
     */
    public function name(): string { return 'google'; }

    /**
     * Generate Google OAuth authorization URL
     * @param string $redirectUri Callback URL
     * @param string $state CSRF state parameter
     * @return string Authorization URL
     */
    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', GOOGLE_SCOPES),
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . Http::query($params);
    }

    /**
     * Exchange authorization code for access token
     * @param string $redirectUri Callback URL (must match authorization)
     * @param string $code Authorization code from Google
     * @return string Access token
     * @throws RuntimeException if token exchange fails
     */
    public function tokenFromCode(string $redirectUri, string $code): string
    {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $payload = [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $resp = $this->http->postForm($tokenUrl, $payload);
        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Google token exchange failed.');
        }
        return (string)$data['access_token'];
    }

    /**
     * Fetch user profile from Google
     * @param string $accessToken OAuth access token
     * @return array{provider: string, subject: string, email: string, name: string, avatar: string} Normalized user data
     * @throws RuntimeException if user fetch fails
     */
    public function fetchUser(string $accessToken): array
    {
        // OpenID Connect userinfo endpoint
        $url = 'https://openidconnect.googleapis.com/v1/userinfo';
        $resp = $this->http->getJson($url, ['Authorization: Bearer ' . $accessToken]);
        $data = json_decode($resp, true);

        if (!is_array($data) || empty($data['sub'])) {
            throw new RuntimeException('Google userinfo fetch failed.');
        }

        return [
            'provider' => 'google',
            'subject' => (string)$data['sub'],
            'email' => (string)($data['email'] ?? ''),
            'name' => (string)($data['name'] ?? ''),
            'avatar' => (string)($data['picture'] ?? ''),
        ];
    }
}
