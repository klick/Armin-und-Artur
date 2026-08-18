<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use modules\storyapi\services\CdpJwtFactory;

function expectJwt(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function decodeJwtPart(string $value): array
{
    $padding = strlen($value) % 4;
    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if (!is_string($decoded)) {
        throw new RuntimeException('JWT part is not valid base64url.');
    }

    return json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
}

function expectJwtRuntime(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException) {
        return;
    }

    throw new RuntimeException($message);
}

$keyPair = sodium_crypto_sign_keypair();
$secretKey = sodium_crypto_sign_secretkey($keyPair);
$publicKey = sodium_crypto_sign_publickey($keyPair);
$factory = new CdpJwtFactory();
$jwt = $factory->create(
    'organizations/example/apiKeys/story-api',
    base64_encode($secretKey),
    'POST',
    'https://api.cdp.coinbase.com/platform/v2/x402/verify',
    1_700_000_000,
    '0123456789abcdef0123456789abcdef',
);

$parts = explode('.', $jwt);
expectJwt(count($parts) === 3, 'CDP JWT must have three parts');
$header = decodeJwtPart($parts[0]);
$payload = decodeJwtPart($parts[1]);
$signature = base64_decode(strtr($parts[2] . str_repeat('=', (4 - strlen($parts[2]) % 4) % 4), '-_', '+/'), true);

expectJwt(($header['alg'] ?? null) === 'EdDSA', 'CDP JWT must use EdDSA');
expectJwt(($header['kid'] ?? null) === 'organizations/example/apiKeys/story-api', 'CDP JWT must identify the API key');
expectJwt(($payload['uris'] ?? null) === ['POST api.cdp.coinbase.com/platform/v2/x402/verify'], 'CDP JWT must be scoped to the exact request');
expectJwt(!array_key_exists('aud', $payload), 'Current CDP JWTs must not include the legacy audience claim');
expectJwt(($payload['iat'] ?? null) === 1_700_000_000, 'CDP JWT must include its issued-at timestamp');
expectJwt(($payload['exp'] ?? null) === 1_700_000_120, 'CDP JWT must expire after 120 seconds');
expectJwt(is_string($signature) && sodium_crypto_sign_verify_detached($signature, $parts[0] . '.' . $parts[1], $publicKey), 'CDP JWT signature must verify');

expectJwtRuntime(
    static fn() => $factory->create('key', base64_encode($secretKey), 'POST', 'https://example.com/platform/v2/x402/verify'),
    'CDP JWT must reject a non-CDP host',
);
expectJwtRuntime(
    static fn() => $factory->create('key', 'not-a-secret', 'POST', 'https://api.cdp.coinbase.com/platform/v2/x402/verify'),
    'CDP JWT must reject an invalid Ed25519 secret',
);

echo "CDP Ed25519 JWT checks passed\n";
