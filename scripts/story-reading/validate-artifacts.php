#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/StoryReadingBatch.php';

$options = getopt('', ['manifest:', 'sources-dir:', 'artifacts-dir:', 'schema:', 'semantic-only']);
$manifestPath = isset($options['manifest']) ? (string)$options['manifest'] : '';
$sourcesDirectory = isset($options['sources-dir']) ? rtrim((string)$options['sources-dir'], '/') : '';
$artifactsDirectory = isset($options['artifacts-dir']) ? rtrim((string)$options['artifacts-dir'], '/') : '';
$schemaPath = isset($options['schema'])
    ? (string)$options['schema']
    : dirname(__DIR__, 2) . '/resources/story-reading/story-reading.schema.json';
$semanticOnly = array_key_exists('semantic-only', $options);

if ($manifestPath === '' || $sourcesDirectory === '' || $artifactsDirectory === '') {
    fwrite(STDERR, "Usage: php scripts/story-reading/validate-artifacts.php --manifest=PATH --sources-dir=PATH --artifacts-dir=PATH [--schema=PATH] [--semantic-only]\n");
    exit(2);
}

try {
    $stories = StoryReadingBatch::readManifest($manifestPath);
    foreach ($stories as $selection) {
        $id = (string)$selection['id'];
        $sourcePath = "{$sourcesDirectory}/{$id}.source.json";
        $artifactPath = "{$artifactsDirectory}/{$id}.reading.json";
        $source = StoryReadingBatch::readJson($sourcePath);
        $artifact = StoryReadingBatch::readJson($artifactPath);

        if (!$semanticOnly) {
            validateJsonSchema($schemaPath, $artifactPath);
        }
        StoryReadingBatch::validateArtifact($selection, $source, $artifact, $artifactPath);
        fwrite(STDOUT, "valid {$id}: JSON Schema " . ($semanticOnly ? 'skipped' : '1.2') . ", source fidelity and editorial references\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function validateJsonSchema(string $schemaPath, string $artifactPath): void
{
    if (!is_file($schemaPath)) {
        throw new RuntimeException("JSON Schema not found: {$schemaPath}");
    }
    $command = [
        'npx', '--yes', 'ajv-cli@5', 'validate',
        '--spec=draft2020', '--strict=false',
        '-s', $schemaPath,
        '-d', $artifactPath,
    ];
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start AJV JSON Schema validation.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException("JSON Schema validation failed for {$artifactPath}:\n" . trim($stdout . "\n" . $stderr));
    }
}
