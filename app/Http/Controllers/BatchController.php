<?php

declare(strict_types=1);

namespace HKL\Forensics\Http\Controllers;

use HKL\Forensics\Core\Inventory\BatchFileInventory;
use RuntimeException;
use Throwable;

final class BatchController
{
    public function __construct(
        private readonly BatchFileInventory $inventory =
            new BatchFileInventory()
    ) {
    }

    /**
     * Verwerkt één batch van een lopende scan.
     *
     * @return array<string,mixed>
     */
    public function process(
        string $scanId,
        int $batchSize = 100
    ): array {

        $scanId = trim($scanId);

        if ($scanId === '') {
            throw new RuntimeException(
                'Geen scan-ID opgegeven.'
            );
        }

        $storage = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR
            . 'storage';

        try {

            $progress = $this->inventory->processBatch(
                $scanId,
                $storage,
                $batchSize
            );

            return [

                'success' => true,

                'scan_id' => $scanId,

                'progress' => $progress,

            ];

        } catch (Throwable $exception) {

            return [

                'success' => false,

                'scan_id' => $scanId,

                'message' => $exception->getMessage(),

            ];

        }

    }
}