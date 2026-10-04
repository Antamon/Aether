<?php
declare(strict_types=1);

/** @return list<string> Stable capability names; never include server paths or configuration values. */
function aetherMissingRuntimeRequirements(string $feature = 'core', ?array $capabilities = null): array
{
    $capabilities ??= [
        'php' => PHP_VERSION_ID >= 80100,
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'json' => extension_loaded('json'),
        'session' => extension_loaded('session'),
        'mbstring' => extension_loaded('mbstring'),
        'dom' => extension_loaded('dom'),
        'libxml' => extension_loaded('libxml'),
        'gd' => extension_loaded('gd'),
        'fileinfo' => extension_loaded('fileinfo'),
    ];
    $required = ['php','pdo','pdo_mysql','json','session','mbstring','dom','libxml'];
    if ($feature === 'portrait_upload') $required = array_merge($required, ['gd','fileinfo']);
    if ($feature === 'logo_upload') $required[] = 'fileinfo';
    return array_values(array_filter($required, static fn(string $name): bool => ($capabilities[$name] ?? false) !== true));
}

function aetherRuntimeDirectoriesWritable(array $directories): bool
{
    foreach ($directories as $directory) {
        if (!is_string($directory) || !is_dir($directory) || !is_writable($directory)) return false;
    }
    return true;
}

function aetherRequireRuntime(string $feature = 'core', array $directories = []): void
{
    if (aetherMissingRuntimeRequirements($feature) !== [] || !aetherRuntimeDirectoriesWritable($directories)) {
        throw new RuntimeException('Runtimevoorwaarden ontbreken.');
    }
}
