<?php
declare(strict_types=1);

/**
 * HTTP Client for making external API requests
 * 
 * Simple cURL wrapper for OAuth and API communications.
 * Provides consistent error handling and logging.
 */
final class HttpClient
{
    private int $timeout;

    public function __construct(int $timeout = 20)
    {
        $this->timeout = $timeout;
    }

    /**
     * Make a POST request with form-encoded data
     * 
     * @param string $url Target URL
     * @param array $fields Form fields to send
     * @param array $headers Additional HTTP headers
     * @return string Response body
     * @throws RuntimeException on HTTP error
     */
    public function postForm(string $url, array $fields, array $headers = []): string
    {
        $defaultHeaders = ['Content-Type: application/x-www-form-urlencoded'];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::error('HTTP POST request failed', [
                'url' => $url,
                'error' => $error,
            ]);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        if ($httpCode >= 400) {
            Logger::warning('HTTP POST returned error status', [
                'url' => $url,
                'status' => $httpCode,
                'response' => substr((string)$response, 0, 500),
            ]);
            throw new RuntimeException("HTTP error: $httpCode");
        }

        return (string)$response;
    }

    /**
     * Make a GET request expecting JSON response
     * 
     * @param string $url Target URL
     * @param array $headers HTTP headers
     * @return array Decoded JSON array
     * @throws RuntimeException on HTTP error
     */
    public function get(string $url, array $headers = []): array
    {
        $defaultHeaders = ['Accept: application/json'];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::error('HTTP GET request failed', [
                'url' => $url,
                'error' => $error,
            ]);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        if ($httpCode >= 400) {
            Logger::warning('HTTP GET returned error status', [
                'url' => $url,
                'status' => $httpCode,
                'response' => substr((string)$response, 0, 500),
            ]);
            throw new RuntimeException("HTTP error: $httpCode");
        }

        $decoded = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }
    
    /**
     * Make a GET request expecting JSON response (legacy alias)
     * 
     * @param string $url Target URL
     * @param array $headers HTTP headers
     * @return string Response body (JSON)
     * @throws RuntimeException on HTTP error
     */
    public function getJson(string $url, array $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new RuntimeException("HTTP error: $httpCode");
        }

        return (string)$response;
    }

    /**
     * Make a POST request with JSON body
     * 
     * @param string $url Target URL
     * @param array $data Data to encode as JSON
     * @param array $headers Additional HTTP headers
     * @return array Decoded JSON response
     * @throws RuntimeException on HTTP error
     */
    public function post(string $url, array $data, array $headers = []): array
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }

        // Debug: Log the JSON being sent
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("HTTP POST to: $url");
            error_log("JSON Body length: " . strlen($json));
            error_log("JSON Body (first 500): " . substr($json, 0, 500));
            // Check if model is in the JSON
            $decoded = json_decode($json, true);
            error_log("Model in body: " . ($decoded['model'] ?? 'NOT FOUND'));
        }

        $defaultHeaders = ['Content-Type: application/json'];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        // Debug: verify what curl will send
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Headers: " . print_r($allHeaders, true));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::error('HTTP POST JSON request failed', [
                'url' => $url,
                'error' => $error,
            ]);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        if ($httpCode >= 400) {
            Logger::warning('HTTP POST JSON returned error status', [
                'url' => $url,
                'status' => $httpCode,
                'response' => substr((string)$response, 0, 500),
            ]);
            
            // Include response body in error for debugging
            $errorMsg = "HTTP error: $httpCode";
            if ($response) {
                $errorMsg .= " - " . substr((string)$response, 0, 200);
            }
            
            throw new RuntimeException($errorMsg);
        }

        $decoded = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }
    
    /**
     * Make a POST request with JSON body (legacy alias)
     * 
     * @param string $url Target URL
     * @param array $data Data to encode as JSON
     * @param array $headers Additional HTTP headers
     * @return string Response body
     * @throws RuntimeException on HTTP error
     */
    public function postJson(string $url, array $data, array $headers = []): string
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new RuntimeException("HTTP error: $httpCode");
        }

        return (string)$response;
    }
}
