<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/shared/appVersion.php';
require_once __DIR__ . '/../api/shared/assets.php';
require_once __DIR__ . '/../api/shared/cache.php';

function assertCacheManagement(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function runCacheManagementTests(): void
{
    $applicationRoot = dirname(__DIR__);
    $fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aether-cache-test-' . bin2hex(random_bytes(5));
    $fixtureJsDirectory = $fixtureRoot . DIRECTORY_SEPARATOR . 'js';
    $fixtureVersion = $fixtureRoot . DIRECTORY_SEPARATOR . 'VERSION';

    if (!mkdir($fixtureJsDirectory, 0777, true) && !is_dir($fixtureJsDirectory)) {
        throw new RuntimeException('Kon tijdelijke assetmap niet maken.');
    }

    try {
        file_put_contents($fixtureVersion, "test-deployment\n");
        $fixtureAsset = $fixtureJsDirectory . DIRECTORY_SEPARATOR . 'app.js';
        file_put_contents($fixtureAsset, 'console.log("fixture");');

        touch($fixtureAsset, 1700000000);
        clearstatcache(true, $fixtureAsset);
        $firstUrl = aetherAssetUrl('js/app.js', '../app', $fixtureRoot, $fixtureVersion);
        assertCacheManagement(
            $firstUrl === '../app/js/app.js?v=1700000000',
            'De asset-URL bevat niet het verwachte wijzigingstijdstip of basispad.'
        );

        $unchangedUrl = aetherAssetUrl('js/app.js', '../app', $fixtureRoot, $fixtureVersion);
        assertCacheManagement($unchangedUrl === $firstUrl, 'Een ongewijzigde asset kreeg een andere URL.');

        touch($fixtureAsset, 1700000100);
        clearstatcache(true, $fixtureAsset);
        $changedUrl = aetherAssetUrl('js/app.js', '../app', $fixtureRoot, $fixtureVersion);
        assertCacheManagement($changedUrl !== $firstUrl, 'Een gewijzigde asset kreeg geen nieuwe URL.');
        assertCacheManagement(
            $changedUrl === '../app/js/app.js?v=1700000100',
            'De nieuwe asset-URL bevat niet het nieuwe wijzigingstijdstip.'
        );

        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            $missingUrl = aetherAssetUrl('js/missing.js', '', $fixtureRoot, $fixtureVersion);
        } finally {
            restore_error_handler();
        }
        assertCacheManagement(
            $missingUrl === 'js/missing.js?v=test-deployment',
            'Een ontbrekende asset gebruikt niet stil de deploymentversie als fallback.'
        );

        assertCacheManagement(
            aetherAssetUrl('https://cdn.example.test/app.js') === 'https://cdn.example.test/app.js',
            'Een externe CDN-URL werd aangepast.'
        );
        assertCacheManagement(
            aetherAssetUrl('//cdn.example.test/app.css') === '//cdn.example.test/app.css',
            'Een protocolrelatieve CDN-URL werd aangepast.'
        );

        foreach (['../js/app.js', 'img/logo.png'] as $invalidPath) {
            try {
                aetherAssetUrl($invalidPath, '', $fixtureRoot, $fixtureVersion);
                throw new RuntimeException('Ongeldig assetpad werd geaccepteerd: ' . $invalidPath);
            } catch (InvalidArgumentException) {
                // Verwacht.
            }
        }

        assertCacheManagement(
            aetherApplicationVersion($fixtureVersion) === 'test-deployment',
            'De deploymentversie werd niet uit het VERSION-bestand gelezen.'
        );
        file_put_contents($fixtureVersion, "ongeldige versie met spaties\n");
        assertCacheManagement(
            aetherApplicationVersion($fixtureVersion) === 'development',
            'Een ongeldige deploymentversie kreeg geen veilige fallback.'
        );

        $noStoreHeaders = aetherNoStoreHeaderValues(true);
        assertCacheManagement(
            str_contains($noStoreHeaders['Cache-Control'] ?? '', 'no-store'),
            'De gedeelde private cacheheaders missen no-store.'
        );

        $rootHtaccess = file_get_contents($applicationRoot . '/.htaccess');
        $apiHtaccess = file_get_contents($applicationRoot . '/api/.htaccess');
        assertCacheManagement(
            str_contains($rootHtaccess, 'max-age=31536000, immutable')
                && str_contains($rootHtaccess, 'aether_versioned_asset'),
            'De Apacheconfiguratie cachet versiegebonden assets niet langdurig.'
        );
        assertCacheManagement(
            str_contains($rootHtaccess, 'no-cache, must-revalidate')
                && str_contains($apiHtaccess, 'private, no-store'),
            'De Apacheconfiguratie mist revalidatie of API no-store.'
        );

        $entryPages = ['index.html', 'admin.html', 'companies.html', 'eventParticipation.html', 'static.html'];
        foreach ($entryPages as $entryPage) {
            $html = file_get_contents($applicationRoot . '/' . $entryPage);
            assertCacheManagement(
                !preg_match('/(?:href|src)="(?:css|js)\//', $html),
                $entryPage . ' bevat nog een lokale CSS/JS-URL zonder centrale resolver.'
            );
            assertCacheManagement(
                str_contains($html, 'asset.php?path=js/updateManager.js'),
                $entryPage . ' laadt de centrale updatechecker niet.'
            );
        }

        $indexHtml = file_get_contents($applicationRoot . '/index.html');
        assertCacheManagement(
            str_contains($indexHtml, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/'),
            'De externe Bootstrap-URL werd onverwacht gewijzigd.'
        );

        $versionEndpoint = file_get_contents($applicationRoot . '/version.php');
        assertCacheManagement(
            str_contains($versionEndpoint, 'aetherSendNoStoreHeaders(false)')
                && str_contains($versionEndpoint, "['version' => aetherApplicationVersion()]"),
            'Het versie-endpoint mist no-store of de centrale deploymentversie.'
        );

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($applicationRoot . '/version.php');
        $endpointOutput = [];
        $endpointExitCode = 0;
        exec($command, $endpointOutput, $endpointExitCode);
        $decodedVersion = json_decode(implode("\n", $endpointOutput), true);
        assertCacheManagement($endpointExitCode === 0, 'Het versie-endpoint eindigde met een foutstatus.');
        assertCacheManagement(
            is_array($decodedVersion) && ($decodedVersion['version'] ?? null) === aetherApplicationVersion(),
            'Het versie-endpoint geeft niet het verwachte JSON-contract terug.'
        );

        $updateManager = file_get_contents($applicationRoot . '/js/updateManager.js');
        assertCacheManagement(
            str_contains($updateManager, 'currentVersion !== availableVersion')
                && str_contains($updateManager, "cache: 'no-store'")
                && str_contains($updateManager, 'setInterval(checkForUpdate')
                && str_contains($updateManager, 'if (updateNotice)')
                && str_contains($updateManager, 'if (checkInProgress)')
                && str_contains($updateManager, 'hasUnsavedInput')
                && str_contains($updateManager, 'global.confirm(')
                && str_contains($updateManager, '} catch (error) {'),
            'De frontend-updateherkenning mist een van de vereiste guards of gedragingen.'
        );

        echo "Cache- en updatebeheertests geslaagd.\n";
    } finally {
        if (is_file($fixtureRoot . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js')) {
            unlink($fixtureRoot . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js');
        }
        if (is_dir($fixtureRoot . DIRECTORY_SEPARATOR . 'js')) {
            rmdir($fixtureRoot . DIRECTORY_SEPARATOR . 'js');
        }
        if (is_file($fixtureVersion)) {
            unlink($fixtureVersion);
        }
        if (is_dir($fixtureRoot)) {
            rmdir($fixtureRoot);
        }
    }
}

runCacheManagementTests();
