<?php
declare(strict_types=1);

final class GitHubProvider implements OAuthProvider
{
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

        $resp = $this->postForm($tokenUrl, $payload, ['Accept: application/json']);
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
        $resp = $this->getJson($url, $headers);
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

    private function postForm(string $url, array $fields, array $extraHeaders = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $extraHeaders),
            CURLOPT_TIMEOUT => 20,
        ]);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($out === false || $code >= 400) {
            throw new RuntimeException('HTTP error contacting GitHub.');
        }
        curl_close($ch);
        return $out;
    }

    private function getJson(string $url, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($out === false || $code >= 400) {
            throw new RuntimeException('HTTP error fetching GitHub profile.');
        }
        curl_close($ch);
        return $out;
    }
}
