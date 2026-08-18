<?php

namespace modules\storyapi\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use yii\base\InvalidArgumentException;
use yii\web\NotFoundHttpException;

/**
 * Keeps reading artefacts out of the public web root and exposes a small,
 * deterministic catalogue derived from their canonical metadata.
 */
class StoryReadingService extends Component
{
    private const ID_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const X402_VERSION = 2;
    private const X402_SCHEME = 'exact';
    private const PILOT_CURRENCY = 'USDC';
    private const PILOT_CURRENCY_DECIMALS = 6;
    private const BASE_SEPOLIA_NETWORK = 'eip155:84532';
    private const BASE_SEPOLIA_USDC_ASSET = '0x036CbD53842c5426634e7929541eC2318f3dCF7e';
    private const BASE_MAINNET_NETWORK = 'eip155:8453';
    private ?array $catalog = null;
    private ?array $catalogByEntryId = null;

    public function getSchema(): array
    {
        return $this->decodeFile($this->schemaPath());
    }

    public function getArtifact(string $id): array
    {
        $id = $this->validateId($id);
        $path = $this->artifactPath($id);

        if (!is_file($path)) {
            throw new NotFoundHttpException('No reading artefact exists for this story.');
        }

        $artifact = $this->decodeFile($path);
        if (($artifact['story']['id'] ?? null) !== $id) {
            throw new \RuntimeException("Story artefact ID does not match its filename: {$id}");
        }

        return $artifact;
    }

    public function getCatalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $items = [];
        $itemsByEntryId = [];
        $paymentDiscovery = $this->getPaymentDiscoveryMetadata();
        $schemaUrl = UrlHelper::siteUrl('api/v1/story-reading.schema.json');
        foreach (glob($this->artifactDirectory() . DIRECTORY_SEPARATOR . '*.reading.json') ?: [] as $path) {
            $artifact = $this->decodeFile($path);
            $story = $artifact['story'] ?? [];
            if (!isset($story['id'], $story['title'], $story['language'], $story['sourceUrl'])) {
                throw new \RuntimeException("Invalid story metadata in {$path}");
            }

            $id = $this->validateId((string)$story['id']);
            $item = [
                'id' => $id,
                'title' => $story['title'],
                'language' => $story['language'],
                'sourceUrl' => $story['sourceUrl'],
                'schemaVersion' => $artifact['schemaVersion'] ?? null,
                'schemaUrl' => $schemaUrl,
                'readingUrl' => UrlHelper::siteUrl("api/v1/stories/{$id}/reading.json"),
                'access' => 'x402',
                'environment' => $paymentDiscovery['environment'],
                'payment' => $paymentDiscovery['payment'],
            ];
            $entryId = $artifact['cms']['entryId'] ?? null;
            if (is_int($entryId) && $entryId > 0) {
                $item['entryId'] = $entryId;
                $itemsByEntryId[$entryId] = $item;
            }
            $items[] = $item;
        }

        usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        $this->catalog = $items;
        $this->catalogByEntryId = $itemsByEntryId;

        return $this->catalog;
    }

    public function getCatalogItemByEntryId(int $entryId): ?array
    {
        $this->getCatalog();

        return $this->catalogByEntryId[$entryId] ?? null;
    }

    public function isX402Enabled(): bool
    {
        $configured = App::env('STORY_API_X402_ENABLED');

        if ($configured === null || $configured === '') {
            return true;
        }

        return filter_var($configured, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Public, non-secret payment metadata used by the free discovery catalogue.
     * The recipient remains in the endpoint's standard 402 challenge.
     */
    public function getPaymentDiscoveryMetadata(): array
    {
        $parameters = $this->paymentParameters();

        return [
            'environment' => $this->classifyNetwork($parameters['network']),
            'payment' => [
                'protocol' => 'x402',
                'version' => self::X402_VERSION,
                'scheme' => self::X402_SCHEME,
                'network' => $parameters['network'],
                'asset' => $parameters['asset'],
                'amount' => $parameters['amount'],
                'currency' => self::PILOT_CURRENCY,
                'decimals' => self::PILOT_CURRENCY_DECIMALS,
            ],
        ];
    }

    public function x402PaymentRequired(string $resourceUrl): array
    {
        $payTo = trim((string)App::env('STORY_API_X402_PAY_TO'));
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $payTo)) {
            throw new \RuntimeException('STORY_API_X402_PAY_TO must be a valid EVM address before the paid endpoint can be enabled.');
        }

        $parameters = $this->paymentParameters();

        return [
            'x402Version' => self::X402_VERSION,
            'error' => 'PAYMENT-SIGNATURE header is required',
            'resource' => [
                'url' => $resourceUrl,
                'description' => 'Canonical story-reading JSON',
                'mimeType' => 'application/json',
            ],
            'accepts' => [[
                'scheme' => self::X402_SCHEME,
                'network' => $parameters['network'],
                'amount' => $parameters['amount'],
                'asset' => $parameters['asset'],
                'payTo' => $payTo,
                'maxTimeoutSeconds' => 60,
                'extra' => [
                    'name' => self::PILOT_CURRENCY,
                    'version' => '2',
                ],
            ]],
        ];
    }

    public function decodePaymentSignature(string $header): array
    {
        $decoded = base64_decode($header, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('PAYMENT-SIGNATURE must be valid Base64.');
        }

        try {
            $payload = Json::decode($decoded, true);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('PAYMENT-SIGNATURE must contain JSON.', 0, $exception);
        }

        if (!is_array($payload) || ($payload['x402Version'] ?? null) !== 2) {
            throw new InvalidArgumentException('PAYMENT-SIGNATURE must contain an x402 v2 payment payload.');
        }

        return $payload;
    }

    /**
     * Verifies and settles through an x402 facilitator. No local private key is
     * stored or used by this service.
     */
    public function verifyAndSettle(array $paymentPayload, array $requirements): array
    {
        $facilitatorUrl = rtrim((string)(App::env('STORY_API_X402_FACILITATOR_URL') ?: 'https://x402.org/facilitator'), '/');
        if (!filter_var($facilitatorUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('STORY_API_X402_FACILITATOR_URL must be a valid URL.');
        }

        $request = [
            'x402Version' => 2,
            'paymentPayload' => $paymentPayload,
            'paymentRequirements' => $requirements,
        ];
        $verification = $this->postJson("{$facilitatorUrl}/verify", $request);
        if (($verification['isValid'] ?? false) !== true) {
            throw new InvalidArgumentException($verification['invalidReason'] ?? 'The facilitator rejected the payment signature.');
        }

        $settlement = $this->postJson("{$facilitatorUrl}/settle", $request);
        if (($settlement['success'] ?? false) !== true) {
            throw new \RuntimeException($settlement['errorReason'] ?? 'The facilitator could not settle the payment.');
        }

        return $settlement;
    }

    private function postJson(string $url, array $payload): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Could not initialise the HTTP client for the x402 facilitator.');
        }

        $body = Json::encode($payload);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            throw new \RuntimeException("x402 facilitator request failed ({$status}): {$error}");
        }

        try {
            $decoded = Json::decode($response, true);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('x402 facilitator returned invalid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('x402 facilitator returned an unexpected response.');
        }

        return $decoded;
    }

    private function paymentParameters(): array
    {
        $network = trim((string)(App::env('STORY_API_X402_NETWORK') ?: self::BASE_SEPOLIA_NETWORK));
        $asset = trim((string)(App::env('STORY_API_X402_ASSET') ?: self::BASE_SEPOLIA_USDC_ASSET));
        $amount = trim((string)(App::env('STORY_API_X402_PRICE_ATOMIC') ?: '10000'));

        if (!preg_match('/^eip155:[0-9]+$/', $network) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $asset) || !preg_match('/^[0-9]+$/', $amount)) {
            throw new \RuntimeException('Story API x402 network, asset, or price configuration is invalid.');
        }

        return compact('network', 'asset', 'amount');
    }

    private function classifyNetwork(string $network): string
    {
        return match ($network) {
            self::BASE_SEPOLIA_NETWORK => 'testnet',
            self::BASE_MAINNET_NETWORK => 'mainnet',
            default => 'unknown',
        };
    }

    private function artifactDirectory(): string
    {
        return Craft::getAlias('@root/resources/story-reading');
    }

    private function schemaPath(): string
    {
        return $this->artifactDirectory() . DIRECTORY_SEPARATOR . 'story-reading.schema.json';
    }

    private function artifactPath(string $id): string
    {
        return $this->artifactDirectory() . DIRECTORY_SEPARATOR . $id . '.reading.json';
    }

    private function validateId(string $id): string
    {
        if (!preg_match(self::ID_PATTERN, $id)) {
            throw new InvalidArgumentException('Story ID is invalid.');
        }

        return $id;
    }

    private function decodeFile(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read {$path}");
        }

        try {
            $decoded = Json::decode($contents, true);
        } catch (\Throwable $exception) {
            throw new \RuntimeException("Invalid JSON in {$path}", 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException("JSON object expected in {$path}");
        }

        return $decoded;
    }
}
