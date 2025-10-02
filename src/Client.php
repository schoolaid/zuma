<?php
namespace SchoolAid\Zuma;

use Exception;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    protected GuzzleClient $httpClient;
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected ?string $token = null;
    protected string $cacheKey;
    protected int $tokenTtl = 3600; // 1 hour default

    public function __construct(string $baseUrl, string $username, string $password)
    {
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cacheKey = 'zuma_token_' . md5($username);

        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'timeout'  => config('zuma.timeout', 30),
            'verify'   => config('zuma.verify_ssl', true),
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        // Set token TTL from config
        $this->tokenTtl = config('zuma.token_ttl', 3600);

        // Try to load token from cache
        $this->loadTokenFromCache();
    }

    protected function loadTokenFromCache(): void
    {
        $cachedToken = Cache::get($this->cacheKey);
        if ($cachedToken) {
            $this->token = $cachedToken;
        }
    }

    public function authenticate(): void
    {
        try {
            $response = $this->httpClient->post('/commerce/login', [
                'json' => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ]);

            $data        = json_decode($response->getBody()->getContents(), true);
            $this->token = $data['token'] ?? null;

            if (!$this->token) {
                throw new Exception('Authentication failed: No token received');
            }

            // Cache the token
            Cache::put($this->cacheKey, $this->token, $this->tokenTtl);
        } catch (GuzzleException $e) {
            throw new Exception('Authentication failed: ' . $e->getMessage());
        }
    }

    public function request(string $method, string $endpoint, array $data = []): array
    {
        if (!$this->token && $endpoint !== '/commerce/login') {
            $this->authenticate();
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ];

        if (!empty($data)) {
            $options['json'] = $data;
        }

        try {
            $response = $this->httpClient->request($method, $endpoint, $options);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            $message    = $e->getMessage();

            if ($statusCode === 401 && $endpoint !== '/commerce/login') {
                // Clear cache and re-authenticate
                Cache::forget($this->cacheKey);
                $this->token = null;
                $this->authenticate();

                return $this->request($method, $endpoint, $data);
            }

            throw new Exception("Request failed ({$statusCode}): {$message}");
        }
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
        Cache::put($this->cacheKey, $token, $this->tokenTtl);
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
        $this->token = null;
    }
}
