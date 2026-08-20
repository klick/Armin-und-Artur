<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function expectDocumentation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return string */
function readDocumentation(string $root, string $relativePath): string
{
    $content = file_get_contents($root . '/' . $relativePath);
    expectDocumentation(is_string($content), "Unable to read {$relativePath}");
    expectDocumentation(str_starts_with($content, '# '), "{$relativePath} must start with an H1");

    return $content;
}

$readme = readDocumentation($root, 'README.md');
$format = readDocumentation($root, 'resources/story-reading/README.md');
$api = readDocumentation($root, 'STORY_API.md');
$batch = readDocumentation($root, 'scripts/story-reading/README.md');
$deployment = readDocumentation($root, 'DEPLOYMENT.md');

foreach ([
    'resources/story-reading/README.md',
    'STORY_API.md',
    'scripts/story-reading/README.md',
    'DEPLOYMENT.md',
    'CRAFT5_UPGRADE.md',
] as $linkedDocument) {
    expectDocumentation(str_contains($readme, $linkedDocument), "README must link to {$linkedDocument}");
}

foreach ([
    'schemaVersion',
    'story',
    'cms',
    'readingPolicy',
    'formatArchitecture',
    'cast',
    'scenes',
    'speakerResolution',
    'providerNotes',
    'originalText',
] as $contractField) {
    expectDocumentation(str_contains($format, $contractField), "Format guide must document {$contractField}");
}

$artefacts = glob($root . '/resources/story-reading/*.reading.json');
expectDocumentation(is_array($artefacts) && count($artefacts) === 15, 'Repository documentation expects exactly 15 current reading artefacts');
foreach ($artefacts as $artefactPath) {
    $id = basename($artefactPath, '.reading.json');
    expectDocumentation(str_contains($format, "`{$id}`"), "Format guide must inventory {$id}");
}

foreach (['eip155:8453', '0.01 USDC', 'PAYMENT-REQUIRED', 'PAYMENT-SIGNATURE', 'PAYMENT-RESPONSE'] as $paymentTerm) {
    expectDocumentation(str_contains($api, $paymentTerm), "Story API guide must document {$paymentTerm}");
}
expectDocumentation(!str_contains($api, 'This pilot does not enable or advertise a mainnet purchase path'), 'Story API guide must not claim that production mainnet is disabled');

foreach (['p|blockquote', 'soft_hyphen_removed', 'requires_editorial_annotation', 'machine_validated_pending_editorial_review'] as $pipelineTerm) {
    expectDocumentation(str_contains($batch, $pipelineTerm), "Batch guide must document {$pipelineTerm}");
}

foreach (['deploy-production.yml', 'git merge --ff-only', 'web/bilder/', 'no automatic'] as $deploymentTerm) {
    expectDocumentation(str_contains($deployment, $deploymentTerm), "Deployment guide must document {$deploymentTerm}");
}
expectDocumentation(!str_contains($deployment, 'is not yet a Git worktree'), 'Deployment guide must not describe the completed checkout conversion as pending');

foreach ([$readme, $format, $api, $batch, $deployment] as $document) {
    expectDocumentation(
        preg_match('/(CDP_API_KEY_ID=[^Y\n]|CDP_API_KEY_SECRET=[^B\n]|STORY_API_X402_PAY_TO=0x[a-fA-F0-9]{40})/', $document) !== 1,
        'Repository documentation must not contain deployed credentials or a concrete recipient address'
    );
}

echo "Repository documentation checks passed\n";
