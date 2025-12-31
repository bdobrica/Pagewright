<?php
declare(strict_types=1);

final class GitHubProvider implements OAuthProvider
{
    private HttpClient $http;

    public function __construct()
    {
        $this->http = new HttpClient();
    }

    public function name(): string { return 'github'; }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $params = [
            'client_id' => GITHUB_CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', GITHUB_SCOPES),
            'state' => $state,
            'allow_signup' => 'true',
        ];
        return 'https://github.com/login/oauth/authorize?' . Http::query($params);
    }

    public function tokenFromCode(string $redirectUri, string $code): string
    {
        $tokenUrl = 'https://github.com/login/oauth/access_token';
        $payload = [
            'client_id' => GITHUB_CLIENT_ID,
            'client_secret' => GITHUB_CLIENT_SECRET,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $resp = $this->http->postForm($tokenUrl, $payload, ['Accept: application/json']);
        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('GitHub token exchange failed.');
        }
        return (string)$data['access_token'];
    }

    public function fetchUser(string $accessToken): array
    {
        // GitHub user API endpoint documented by GitHub. :contentReference[oaicite:5]{index=5}
        $url = 'https://api.github.com/user';
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/vnd.github+json',
            'User-Agent: Pagewright',
        ];
        $resp = $this->http->getJson($url, $headers);
        $data = json_decode($resp, true);

        if (!is_array($data) || empty($data['id'])) {
            throw new RuntimeException('GitHub user fetch failed.');
        }

        return [
            'provider' => 'github',
            'subject' => (string)$data['id'],
            'email' => (string)($data['email'] ?? ''), // may be null
            'name' => (string)($data['name'] ?? $data['login'] ?? ''),
            'avatar' => (string)($data['avatar_url'] ?? ''),
        ];
    }
}
