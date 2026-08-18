<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use modules\storyapi\services\StoryReadingService;
use yii\base\InvalidArgumentException;

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectInvalidArgument(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

function expectRuntime(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException) {
        return;
    }

    throw new RuntimeException($message);
}

$serviceSource = file_get_contents(dirname(__DIR__, 2) . '/modules/storyapi/services/StoryReadingService.php');
expect(
    is_string($serviceSource)
    && str_contains($serviceSource, '$curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;'),
    'CDP calls must use the IPv4 address covered by the production key allowlist',
);

putenv('STORY_API_X402_ENABLED');
unset($_SERVER['STORY_API_X402_ENABLED']);
$service = new StoryReadingService();
expect(!$service->isX402Enabled(), 'x402 must default to disabled when no environment value is configured');

putenv('STORY_API_X402_ENABLED=invalid');
expect(!$service->isX402Enabled(), 'invalid x402 configuration must fail closed');

putenv('STORY_API_X402_ENABLED=false');
expect(!$service->isX402Enabled(), 'only an explicit false value may disable x402');

putenv('STORY_API_X402_ENABLED=true');
expect($service->isX402Enabled(), 'an explicit true value must enable x402');

putenv('STORY_API_X402_NETWORK=eip155:84532');
putenv('STORY_API_X402_ASSET=0x036CbD53842c5426634e7929541eC2318f3dCF7e');
putenv('STORY_API_X402_PAY_TO=0x1111111111111111111111111111111111111111');
putenv('STORY_API_X402_PRICE_ATOMIC=10000');

$discovery = $service->getPaymentDiscoveryMetadata();
expect(($discovery['environment'] ?? null) === 'testnet', 'Base Sepolia must be classified as testnet');
expect(($discovery['payment']['protocol'] ?? null) === 'x402', 'catalogue must identify the x402 protocol');
expect(($discovery['payment']['version'] ?? null) === 2, 'catalogue must identify x402 v2');
expect(($discovery['payment']['scheme'] ?? null) === 'exact', 'catalogue must identify the exact scheme');
expect(($discovery['payment']['network'] ?? null) === 'eip155:84532', 'catalogue must expose the configured network');
expect(($discovery['payment']['asset'] ?? null) === '0x036CbD53842c5426634e7929541eC2318f3dCF7e', 'catalogue must expose the configured asset');
expect(($discovery['payment']['amount'] ?? null) === '10000', 'catalogue must expose the atomic amount');
expect(($discovery['payment']['currency'] ?? null) === 'USDC', 'catalogue must identify the pilot currency');
expect(($discovery['payment']['decimals'] ?? null) === 6, 'catalogue must expose currency decimals');
expect(!array_key_exists('payTo', $discovery['payment']), 'catalogue must leave the recipient to the signed endpoint challenge');

putenv('STORY_API_X402_NETWORK=eip155:8453');
putenv('STORY_API_X402_ASSET=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913');
expect(($service->getPaymentDiscoveryMetadata()['environment'] ?? null) === 'mainnet', 'Base must be classified as mainnet');

putenv('STORY_API_X402_NETWORK=eip155:999999');
expectRuntime(static fn() => $service->getPaymentDiscoveryMetadata(), 'unrecognised networks must be rejected');

putenv('STORY_API_X402_NETWORK=eip155:8453');
putenv('STORY_API_X402_ASSET=0x036CbD53842c5426634e7929541eC2318f3dCF7e');
expectRuntime(static fn() => $service->getPaymentDiscoveryMetadata(), 'a testnet asset must be rejected on Base mainnet');

$cdpKeyPair = sodium_crypto_sign_keypair();
putenv('STORY_API_X402_ASSET=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913');
putenv('STORY_API_X402_FACILITATOR_URL=https://api.cdp.coinbase.com/platform/v2/x402');
putenv('CDP_API_KEY_ID=organizations/example/apiKeys/story-api');
putenv('CDP_API_KEY_SECRET=' . base64_encode(sodium_crypto_sign_secretkey($cdpKeyPair)));
putenv('STORY_API_X402_MAINNET_ENABLED=false');
expectRuntime(
    static fn() => $service->x402PaymentRequired('https://example.test/api/v1/stories/rotkaeppchen/reading.json'),
    'mainnet must require a separate explicit enable switch',
);

putenv('STORY_API_X402_MAINNET_ENABLED=true');
$mainnetRequired = $service->x402PaymentRequired('https://example.test/api/v1/stories/rotkaeppchen/reading.json');
expect(($mainnetRequired['accepts'][0]['network'] ?? null) === 'eip155:8453', 'mainnet challenge must use Base');
expect(($mainnetRequired['accepts'][0]['asset'] ?? null) === '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', 'mainnet challenge must use Circle USDC');

putenv('STORY_API_X402_PRICE_ATOMIC=10001');
expectRuntime(
    static fn() => $service->x402PaymentRequired('https://example.test/api/v1/stories/rotkaeppchen/reading.json'),
    'initial mainnet pilot must reject a price above 0.01 USDC',
);

putenv('STORY_API_X402_NETWORK=eip155:84532');
putenv('STORY_API_X402_ASSET=0x036CbD53842c5426634e7929541eC2318f3dCF7e');
putenv('STORY_API_X402_PRICE_ATOMIC=10000');
$required = $service->x402PaymentRequired('https://example.test/api/v1/stories/rotkaeppchen/reading.json');

expect(($required['x402Version'] ?? null) === 2, 'x402 v2 is required');
expect(($required['resource']['mimeType'] ?? null) === 'application/json', 'JSON resource type is required');
expect(($required['accepts'][0]['scheme'] ?? null) === 'exact', 'exact payment scheme is required');
expect(($required['accepts'][0]['network'] ?? null) === 'eip155:84532', 'Base Sepolia network is required');
expect(($required['accepts'][0]['amount'] ?? null) === '10000', 'atomic price is required');
expect(($required['accepts'][0]['payTo'] ?? null) === '0x1111111111111111111111111111111111111111', 'recipient address is required');
expect(($required['accepts'][0]['network'] ?? null) === ($discovery['payment']['network'] ?? null), 'challenge and catalogue networks must match');
expect(($required['accepts'][0]['asset'] ?? null) === ($discovery['payment']['asset'] ?? null), 'challenge and catalogue assets must match');
expect(($required['accepts'][0]['amount'] ?? null) === ($discovery['payment']['amount'] ?? null), 'challenge and catalogue amounts must match');

// The HTTP controller puts this identical JSON object in PAYMENT-REQUIRED.
$header = base64_encode(json_encode($required, JSON_THROW_ON_ERROR));
expect(json_decode(base64_decode($header, true), true, 512, JSON_THROW_ON_ERROR) === $required, 'PAYMENT-REQUIRED must round-trip as Base64 JSON');

$paymentPayload = [
    'x402Version' => 2,
    'resource' => $required['resource'],
    'accepted' => $required['accepts'][0],
    'payload' => ['signature' => '0xdeadbeef'],
];
expect($service->decodePaymentSignature(base64_encode(json_encode($paymentPayload, JSON_THROW_ON_ERROR))) === $paymentPayload, 'PAYMENT-SIGNATURE must decode to its v2 payload');
expectInvalidArgument(static fn() => $service->decodePaymentSignature('!'), 'Invalid Base64 must be rejected');
expectInvalidArgument(static fn() => $service->decodePaymentSignature(base64_encode(json_encode(['x402Version' => 1], JSON_THROW_ON_ERROR))), 'Non-v2 payloads must be rejected');

echo "x402 v2 payment-required contract checks passed\n";
