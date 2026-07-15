<?php
declare(strict_types=1);

namespace HKL\Forensics\Modules\MediaWiki;

use HKL\Forensics\Contracts\ScannerModule;
use HKL\Forensics\Core\PathGuard;

final class MediaWikiScanner implements ScannerModule
{
    public function name(): string { return 'HKL MediaWiki Scanner'; }
    public function platform(): string { return 'MediaWiki'; }

    public function supports(string $targetPath): bool
    {
        $root = realpath($targetPath);
        return $root !== false
            && is_file($root . '/LocalSettings.php')
            && is_file($root . '/index.php')
            && is_dir($root . '/includes');
    }

    public function scan(string $targetPath): array
    {
        $root = PathGuard::requireReadableDirectory($targetPath);

        return [
            'platform' => $this->platform(),
            'target' => $root,
            'recognized' => $this->supports($root),
            'checks' => [
                'LocalSettings.php' => is_file($root . '/LocalSettings.php'),
                'index.php' => is_file($root . '/index.php'),
                'api.php' => is_file($root . '/api.php'),
                'includes' => is_dir($root . '/includes'),
                'extensions' => is_dir($root . '/extensions'),
                'skins' => is_dir($root . '/skins'),
                'images' => is_dir($root . '/images'),
                'vendor' => is_dir($root . '/vendor'),
                'tests' => is_dir($root . '/tests'),
            ],
            'read_only' => true,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}
