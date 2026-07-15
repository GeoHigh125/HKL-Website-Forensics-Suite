<?php

declare(strict_types=1);

namespace HKL\Forensics\Modules\MediaWiki;

use HKL\Forensics\Core\PathGuard;

final class MediaWikiDetector
{
    /**
     * Onderzoekt of de opgegeven map een MediaWiki-installatie bevat.
     *
     * @return array{
     *     platform: string,
     *     recognized: bool,
     *     version: string|null,
     *     root_path: string,
     *     required_checks: array<string, bool>,
     *     optional_checks: array<string, bool>,
     *     missing_required: list<string>,
     *     warnings: list<string>
     * }
     */
    public function detect(string $targetPath): array
    {
        $rootPath = PathGuard::requireReadableDirectory($targetPath);

        $requiredChecks = [
            'LocalSettings.php' => is_file($rootPath . DIRECTORY_SEPARATOR . 'LocalSettings.php'),
            'index.php'         => is_file($rootPath . DIRECTORY_SEPARATOR . 'index.php'),
            'includes'          => is_dir($rootPath . DIRECTORY_SEPARATOR . 'includes'),
        ];

        $optionalChecks = [
            'api.php'        => is_file($rootPath . DIRECTORY_SEPARATOR . 'api.php'),
            'load.php'       => is_file($rootPath . DIRECTORY_SEPARATOR . 'load.php'),
            'maintenance'    => is_dir($rootPath . DIRECTORY_SEPARATOR . 'maintenance'),
            'resources'      => is_dir($rootPath . DIRECTORY_SEPARATOR . 'resources'),
            'extensions'     => is_dir($rootPath . DIRECTORY_SEPARATOR . 'extensions'),
            'skins'          => is_dir($rootPath . DIRECTORY_SEPARATOR . 'skins'),
            'images'         => is_dir($rootPath . DIRECTORY_SEPARATOR . 'images'),
            'vendor'         => is_dir($rootPath . DIRECTORY_SEPARATOR . 'vendor'),
            'tests'          => is_dir($rootPath . DIRECTORY_SEPARATOR . 'tests'),
            'composer.json'  => is_file($rootPath . DIRECTORY_SEPARATOR . 'composer.json'),
            'composer.lock'  => is_file($rootPath . DIRECTORY_SEPARATOR . 'composer.lock'),
        ];

        $missingRequired = [];

        foreach ($requiredChecks as $item => $exists) {
            if (!$exists) {
                $missingRequired[] = $item;
            }
        }

        $recognized = $missingRequired === [];

        $warnings = [];

        if (!$optionalChecks['extensions']) {
            $warnings[] = 'De map extensions ontbreekt.';
        }

        if (!$optionalChecks['skins']) {
            $warnings[] = 'De map skins ontbreekt.';
        }

        if (!$optionalChecks['images']) {
            $warnings[] = 'De map images ontbreekt.';
        }

        if (!$optionalChecks['vendor']) {
            $warnings[] = 'De map vendor ontbreekt. Dit kan normaal zijn bij oudere MediaWiki-installaties.';
        }

        return [
            'platform' => 'MediaWiki',
            'recognized' => $recognized,
            'version' => $recognized
                ? $this->detectVersion($rootPath)
                : null,
            'root_path' => $rootPath,
            'required_checks' => $requiredChecks,
            'optional_checks' => $optionalChecks,
            'missing_required' => $missingRequired,
            'warnings' => $warnings,
        ];
    }

    private function detectVersion(string $rootPath): ?string
    {
        $candidates = [
            $rootPath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'Defines.php',
            $rootPath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'DefaultSettings.php',
            $rootPath . DIRECTORY_SEPARATOR . 'RELEASE-NOTES',
        ];

        foreach ($candidates as $candidate) {
            if (!is_file($candidate) || !is_readable($candidate)) {
                continue;
            }

            $contents = file_get_contents($candidate);

            if ($contents === false) {
                continue;
            }

            $version = $this->extractVersion($contents);

            if ($version !== null) {
                return $version;
            }
        }

        return null;
    }

    private function extractVersion(string $contents): ?string
    {
        $patterns = [
            '/MW_VERSION\s*[,=]\s*[\'"]([^\'"]+)[\'"]/i',
            '/\$wgVersion\s*=\s*[\'"]([^\'"]+)[\'"]/i',
            '/MediaWiki\s+([0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[a-z0-9.]+)?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}