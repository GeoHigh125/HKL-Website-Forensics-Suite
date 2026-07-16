<?php

declare(strict_types=1);

namespace HKL\Forensics\Http\Controllers;

use HKL\Forensics\Core\Inventory\BatchFileInventory;
use RuntimeException;
use Throwable;

final class ProgressController
{
    public function __construct(
        private readonly BatchFileInventory $inventory =
            new BatchFileInventory()
    ) {
    }

    /**
     * Leest de actuele voortgang van een scan.
     *
     * @return array<string, mixed>
     */
    public function show(string $scanId): array
    {
        $scanId = trim($scanId);

        if ($scanId === '') {
            throw new RuntimeException(
                'Geen scan-ID opgegeven.'
            );
        }

        $storagePath = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR
            . 'storage';

        try {
            $progress = $this->inventory->progress(
                $scanId,
                $storagePath
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