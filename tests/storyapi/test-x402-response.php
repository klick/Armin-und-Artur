<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use modules\storyapi\services\StoryReadingService;

putenv('STORY_API_X402_NETWORK=eip155:84532');
putenv('STORY_API_X402_ASSET=0x036CbD53842c5426634e7929541eC2318f3dCF7e');
putenv('STORY_API_X402_PAY_TO=0x1111111111111111111111111111111111111111');
putenv('STORY_API_X402_PRICE_ATOMIC=10000');

$service = new StoryReadingService();
$required = $service->x402PaymentRequired('https://example.test/api/v1/stories/rotkaeppchen/reading.json');

assert(($required['x402Version'] ?? null) === 2);
assert(($required['resource']['mimeType'] ?? null) === 'application/json');
assert(($required['accepts'][0]['scheme'] ?? null) === 'exact');
assert(($required['accepts'][0]['network'] ?? null) === 'eip155:84532');
assert(($required['accepts'][0]['amount'] ?? null) === '10000');
assert(($required['accepts'][0]['payTo'] ?? null) === '0x1111111111111111111111111111111111111111');

// The HTTP controller puts this identical JSON object in PAYMENT-REQUIRED.
$header = base64_encode(json_encode($required, JSON_THROW_ON_ERROR));
assert(json_decode(base64_decode($header, true), true, 512, JSON_THROW_ON_ERROR) === $required);

$paymentPayload = [
    'x402Version' => 2,
    'resource' => $required['resource'],
    'accepted' => $required['accepts'][0],
    'payload' => ['signature' => '0xdeadbeef'],
];
assert($service->decodePaymentSignature(base64_encode(json_encode($paymentPayload, JSON_THROW_ON_ERROR))) === $paymentPayload);

echo "x402 v2 payment-required contract checks passed\n";
