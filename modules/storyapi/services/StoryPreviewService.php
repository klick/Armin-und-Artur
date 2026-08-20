<?php

namespace modules\storyapi\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\Json;
use modules\storyapi\Module;
use yii\base\InvalidArgumentException;

/**
 * DEV-only ElevenLabs preview rendering for one narrator voice.
 *
 * Provider audio tags are assembled around an unchanged excerpt from the
 * canonical artefact. No API key or provider voice ID is returned to the UI.
 */
class StoryPreviewService extends Component
{
    private const API_BASE_URL = 'https://api.elevenlabs.io/v1';
    private const OUTPUT_FORMAT = 'mp3_44100_128';
    private const DEFAULT_MAX_CHARACTERS = 2000;
    private const MIN_MAX_CHARACTERS = 100;
    private const MAX_MAX_CHARACTERS = 5000;

    /** @return array{configured: bool, voiceName: string, modelId: string, maxCharacters: int} */
    public function getConfiguration(): array
    {
        return [
            'configured' => $this->apiKey() !== '' && $this->voiceId() !== '',
            'voiceName' => trim((string)(App::env('STORY_PREVIEW_ELEVENLABS_VOICE_NAME') ?: 'Grandpa - Familiar & Warm')),
            'modelId' => 'eleven_v3',
            'maxCharacters' => $this->maxCharacters(),
        ];
    }

    /**
     * @return array<int, array{id: string, title: string, scenes: array<int, array<string, mixed>>}>
     */
    public function getStoryIndex(): array
    {
        $stories = [];
        foreach (glob(Craft::getAlias('@root/resources/story-reading/*.reading.json')) ?: [] as $path) {
            $artifact = $this->decodeFile($path);
            if (($artifact['providerNotes']['contentProfile'] ?? null) !== 'fairy_tale') {
                continue;
            }

            $storyId = (string)($artifact['story']['id'] ?? '');
            $storyTitle = (string)($artifact['story']['title'] ?? '');
            if ($storyId === '' || $storyTitle === '') {
                throw new \RuntimeException("Invalid story identity in {$path}");
            }

            $scenes = [];
            foreach ($artifact['scenes'] ?? [] as $scene) {
                if (!is_array($scene) || !is_string($scene['id'] ?? null)) {
                    throw new \RuntimeException("Invalid scene metadata in {$path}");
                }
                $prepared = self::prepareScene($artifact, $scene['id'], $this->maxCharacters());
                $scenes[] = [
                    'id' => $scene['id'],
                    'title' => (string)($scene['title'] ?? $scene['id']),
                    'direction' => (string)($scene['direction'] ?? ''),
                    'originalCharacters' => $prepared['originalCharacters'],
                    'previewCharacters' => $prepared['previewCharacters'],
                    'truncated' => $prepared['truncated'],
                ];
            }

            $stories[] = ['id' => $storyId, 'title' => $storyTitle, 'scenes' => $scenes];
        }

        usort($stories, static fn(array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        return $stories;
    }

    /**
     * @return array{audio: string, contentType: string, characterCost: ?string, requestId: ?string, preview: array<string, mixed>}
     */
    public function render(string $storyId, string $sceneId): array
    {
        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            throw new \RuntimeException('The reading previewer is available only in DEV_MODE.');
        }
        if ($this->apiKey() === '' || $this->voiceId() === '') {
            throw new \RuntimeException('Configure ELEVENLABS_API_KEY and STORY_PREVIEW_ELEVENLABS_VOICE_ID in the local .env first.');
        }

        $artifact = $this->stories()->getArtifact($storyId);
        if (($artifact['providerNotes']['contentProfile'] ?? null) !== 'fairy_tale') {
            throw new InvalidArgumentException('The current ElevenLabs reference profile is restricted to fairy tales.');
        }
        $preview = self::prepareScene($artifact, $sceneId, $this->maxCharacters());

        $url = sprintf(
            '%s/text-to-speech/%s?output_format=%s',
            self::API_BASE_URL,
            rawurlencode($this->voiceId()),
            self::OUTPUT_FORMAT,
        );
        $payload = [
            'text' => $preview['providerText'],
            'model_id' => $preview['modelId'],
            'voice_settings' => ['stability' => $preview['stability']],
        ];
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Could not initialise the ElevenLabs request.');
        }

        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => Json::encode($payload),
            CURLOPT_HTTPHEADER => [
                'Accept: audio/mpeg',
                'Content-Type: application/json',
                'xi-api-key: ' . $this->apiKey(),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HEADERFUNCTION => static function($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $separator = strpos($headerLine, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($headerLine, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($headerLine, $separator + 1));
                }
                return strlen($headerLine);
            },
        ]);

        $audio = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string)(curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: '');
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($audio)) {
            throw new \RuntimeException('ElevenLabs request failed: ' . ($curlError ?: 'unknown transport error'));
        }
        if ($status < 200 || $status >= 300) {
            $providerError = 'HTTP ' . $status;
            try {
                $decoded = Json::decode($audio, true);
                $detail = is_array($decoded) ? ($decoded['detail'] ?? null) : null;
                if (is_array($detail) && is_string($detail['message'] ?? null)) {
                    $providerError = $detail['message'];
                } elseif (is_string($detail)) {
                    $providerError = $detail;
                }
            } catch (\Throwable) {
                // Keep the status-only error; never return arbitrary provider HTML.
            }
            throw new \RuntimeException('ElevenLabs rejected the preview: ' . $providerError);
        }
        if (!str_starts_with(strtolower($contentType), 'audio/')) {
            throw new \RuntimeException('ElevenLabs returned an unexpected content type.');
        }

        return [
            'audio' => $audio,
            'contentType' => $contentType,
            'characterCost' => $responseHeaders['character-cost'] ?? null,
            'requestId' => $responseHeaders['request-id'] ?? null,
            'preview' => $preview,
        ];
    }

    /**
     * @param array<string, mixed> $artifact
     * @return array{storyId: string, sceneId: string, sceneTitle: string, direction: string, sourceText: string, providerText: string, originalCharacters: int, previewCharacters: int, truncated: bool, modelId: string, stability: float}
     */
    public static function prepareScene(array $artifact, string $sceneId, int $maxCharacters): array
    {
        if ($maxCharacters < self::MIN_MAX_CHARACTERS || $maxCharacters > self::MAX_MAX_CHARACTERS) {
            throw new InvalidArgumentException('Preview character limit is outside the safe range.');
        }

        $scenes = $artifact['scenes'] ?? null;
        $paragraphs = $artifact['originalText']['paragraphs'] ?? null;
        if (!is_array($scenes) || $scenes === [] || !is_array($paragraphs) || $paragraphs === []) {
            throw new InvalidArgumentException('The reading artefact has no previewable scenes or text.');
        }

        $sceneIndex = null;
        foreach ($scenes as $index => $scene) {
            if (is_array($scene) && ($scene['id'] ?? null) === $sceneId) {
                $sceneIndex = $index;
                break;
            }
        }
        if ($sceneIndex === null) {
            throw new InvalidArgumentException('Unknown scene.');
        }

        $fullText = implode("\n\n", array_map(static fn(mixed $paragraph): string => (string)$paragraph, $paragraphs));
        $scene = $scenes[$sceneIndex];
        $anchor = (string)($scene['anchor'] ?? '');
        $start = strpos($fullText, $anchor);
        if ($anchor === '' || $start === false) {
            throw new InvalidArgumentException('Scene anchor is absent from originalText.');
        }

        $end = strlen($fullText);
        $nextScene = $scenes[$sceneIndex + 1] ?? null;
        if (is_array($nextScene)) {
            $nextAnchor = (string)($nextScene['anchor'] ?? '');
            $nextPosition = $nextAnchor === '' ? false : strpos($fullText, $nextAnchor, $start + strlen($anchor));
            if ($nextPosition === false) {
                throw new InvalidArgumentException('Next scene anchor is absent or out of order.');
            }
            $end = $nextPosition;
        }

        $sourceText = trim(substr($fullText, $start, $end - $start));
        $originalCharacters = mb_strlen($sourceText);
        $truncated = $originalCharacters > $maxCharacters;
        if ($truncated) {
            $candidate = mb_substr($sourceText, 0, $maxCharacters);
            $boundaries = [mb_strrpos($candidate, '.'), mb_strrpos($candidate, '!'), mb_strrpos($candidate, '?')];
            $boundaries = array_filter($boundaries, static fn(int|false $position): bool => $position !== false);
            $boundary = $boundaries === [] ? false : max($boundaries);
            $minimumUsefulBoundary = (int)floor($maxCharacters * 0.6);
            $sourceText = rtrim(mb_substr($candidate, 0, $boundary !== false && $boundary >= $minimumUsefulBoundary ? $boundary + 1 : $maxCharacters));
        }

        $preset = $artifact['providerNotes']['elevenLabs']['fairyTalePreset'] ?? null;
        if (!is_array($preset)) {
            throw new InvalidArgumentException('The fairy-tale ElevenLabs preset is missing.');
        }
        $promptPrefix = trim((string)($preset['promptPrefix'] ?? ''));
        if ($promptPrefix === '') {
            throw new InvalidArgumentException('The fairy-tale ElevenLabs prompt prefix is missing.');
        }

        return [
            'storyId' => (string)($artifact['story']['id'] ?? ''),
            'sceneId' => $sceneId,
            'sceneTitle' => (string)($scene['title'] ?? $sceneId),
            'direction' => (string)($scene['direction'] ?? ''),
            'sourceText' => $sourceText,
            'providerText' => $promptPrefix . "\n\n" . $sourceText,
            'originalCharacters' => $originalCharacters,
            'previewCharacters' => mb_strlen($sourceText),
            'truncated' => $truncated,
            'modelId' => (string)($preset['modelId'] ?? ''),
            'stability' => (float)($preset['stability'] ?? 0.5),
        ];
    }

    private function maxCharacters(): int
    {
        $configured = filter_var(App::env('STORY_PREVIEW_MAX_CHARACTERS'), FILTER_VALIDATE_INT);

        return is_int($configured) && $configured >= self::MIN_MAX_CHARACTERS && $configured <= self::MAX_MAX_CHARACTERS
            ? $configured
            : self::DEFAULT_MAX_CHARACTERS;
    }

    private function apiKey(): string
    {
        return trim((string)App::env('ELEVENLABS_API_KEY'));
    }

    private function voiceId(): string
    {
        return trim((string)App::env('STORY_PREVIEW_ELEVENLABS_VOICE_ID'));
    }

    private function stories(): StoryReadingService
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('story-api');

        return $module->getStories();
    }

    /** @return array<string, mixed> */
    private function decodeFile(string $path): array
    {
        try {
            $decoded = Json::decode((string)file_get_contents($path), true);
        } catch (\Throwable $exception) {
            throw new \RuntimeException("Invalid reading artefact {$path}", 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException("Reading artefact must be an object: {$path}");
        }

        return $decoded;
    }
}
