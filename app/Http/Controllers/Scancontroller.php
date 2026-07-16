<?php

declare(strict_types=1);

namespace HKL\Forensics\Http\Controllers;

use HKL\Forensics\Core\Inventory\BatchFileInventory;
use RuntimeException;
use Throwable;

final class ScanController
{
    public function __construct(
        private readonly BatchFileInventory $inventory =
            new BatchFileInventory()
    ) {
    }

    /**
     * Start een nieuwe scan.
     *
     * @return array<string,mixed>
     */
    public function start(string $scanPath): array
    {
        $scanPath = trim($scanPath);

        if ($scanPath === '') {
            throw new RuntimeException(
                'Geen scanpad opgegeven.'
            );
        }

        $scanId = $this->generateScanId();

        $storage = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR
            . 'storage';

        try {

            $progress = $this->inventory->initialize(
                $scanId,
                $scanPath,
                $storage
            );

            return [

                'success' => true,

                'scan_id' => $scanId,

                'message' => 'Scan succesvol gestart.',

                'progress' => $progress,
            ];

        } catch (Throwable $exception) {

            return [

                'success' => false,

                'message' => $exception->getMessage(),
            ];
        }
    }

    private function generateScanId(): string
    {
        return 'HKL-'
            . date('Ymd-His')
            . '-'
            . random_int(1000,9999);
    }
}