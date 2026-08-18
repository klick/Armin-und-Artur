<?php

namespace modules\storyapi\services;

use craft\helpers\Json;

/**
 * Generates the short-lived Ed25519 bearer JWT required by CDP server APIs.
 *
 * The secret is read at request time, never persisted by this class, and the
 * resulting token is scoped to one HTTP method, host, and path for 120 seconds.
 */
final class CdpJwtFactory
{
    private const CDP_HOST = 'api.cdp.coinbase.com';

    public function create(
        string $keyId,
        string $keySecret,
        string $requestMethod,
        string $requestUrl,
        ?int $now = null,
        ?string $nonce = null,
    ): string {
        $keyId = trim($keyId);
        if ($keyId === '') {
            throw new \RuntimeException('CDP_API_KEY_ID is required for the CDP facilitator.');
        }

        $parts = parse_url($requestUrl);
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== self::CDP_HOST
            || !isset($parts['path'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new \RuntimeException('CDP JWTs may only target an HTTPS api.cdp.coinbase.com path.');
        }

        $decodedSecret = base64_decode(trim($keySecret), true);
        if (!is_string($decodedSecret) || strlen($decodedSecret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \RuntimeException('CDP_API_KEY_SECRET must be a base64-encoded Ed25519 secret API key.');
        }

        $method = strtoupper(trim($requestMethod));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            throw new \RuntimeException('Unsupported HTTP method for a CDP JWT.');
        }

        $issuedAt = $now ?? time();
        $jwtNonce = $nonce ?? bin2hex(random_bytes(16));
        if (!preg_match('/^[a-zA-Z0-9_-]{16,128}$/', $jwtNonce)) {
            throw new \RuntimeException('CDP JWT nonce is invalid.');
        }

        $header = [
            'alg' => 'EdDSA',
            'typ' => 'JWT',
            'kid' => $keyId,
            'nonce' => $jwtNonce,
        ];
        $payload = [
            'sub' => $keyId,
            'iss' => 'cdp',
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + 120,
            'uris' => [$method . ' ' . self::CDP_HOST . $parts['path']],
        ];

        $message = $this->base64Url(Json::encode($header)) . '.' . $this->base64Url(Json::encode($payload));
        $signature = sodium_crypto_sign_detached($message, $decodedSecret);

        return $message . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
