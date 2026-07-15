<?php

declare(strict_types=1);

namespace HKL\Forensics\Services;

use HKL\Forensics\Core\Classification\DirectoryClassifier;
use HKL\Forensics\Modules\MediaWiki\MediaWikiDetector;
use HKL\Forensics\Core\Inventory\FileInventory;

final class ScanService
{
    private MediaWikiDetector $detector;

    private DirectoryClassifier $directoryClassifier;

    private FileInventory $inventory;

    public function __construct()
    {
        $this->detector = new MediaWikiDetector();
        $this->directoryClassifier = new DirectoryClassifier();
        $this->inventory = new FileInventory();
    }

    /**
     * Start een volledige MediaWiki inventarisatiescan.
     *
     * @return array<string,mixed>
     */
    public function scan(string $scanPath): array
    {
        $detection = $this->detector->detect($scanPath);

        if (!$detection['recognized']) {
            return [
                'success' => false,
                'message' => 'Geen geldige MediaWiki-installatie gevonden.',
                'detection' => $detection,
            ];
        }

        $directories = $this->directoryClassifier->classifyTree($scanPath);

        return [
            'success' => true,
            'scan_id' => $this->generateScanId(),
            'scan_type' => 'Offline Forensic Scan',
            'platform' => $detection['platform'],
            'version' => $detection['version'],
            'root_path' => $detection['root_path'],
            'generated_at' => date(DATE_ATOM),
            'detection' => $detection,
            'directories' => $directories,
            'statistics' => $this->buildStatistics($directories),
            'inventory' => $this->inventory->build($scanPath),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $directories
     * @return array<string,int>
     */
    private function buildStatistics(array $directories): array
    {
        $statistics = [];

        foreach ($directories as $directory) {

            $category = $directory['category'];

            if (!isset($statistics[$category])) {
                $statistics[$category] = 0;
            }

            $statistics[$category]++;
        }

        ksort($statistics);

        return $statistics;
    }

    private function generateScanId(): string
    {
        return 'HKL-' . date('Ymd-His');
    }
}