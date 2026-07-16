<?php

declare(strict_types=1);

namespace HKL\Forensics\Http;

use HKL\Forensics\Http\Controllers\BatchController;
use HKL\Forensics\Http\Controllers\ProgressController;
use HKL\Forensics\Http\Controllers\RiskController;
use HKL\Forensics\Http\Controllers\ScanController;
use RuntimeException;

final class ApiRouter
{
    /**
     * Verwerkt een API-aanroep.
     *
     * @return array<string,mixed>
     */
    public function dispatch(
        string $endpoint,
        array $request = []
    ): array {

        return match ($endpoint) {

            '/api/scan/start'
                => (new ScanController())->start(
                    (string) ($request['scan_path'] ?? '')
                ),

            '/api/scan/batch'
                => (new BatchController())->process(
                    (string) ($request['scan_id'] ?? ''),
                    (int) ($request['batch_size'] ?? 100)
                ),

            '/api/scan/progress'
                => (new ProgressController())->show(
                    (string) ($request['scan_id'] ?? '')
                ),

                        '/api/risk/start'
                => (new RiskController())->start(
                    (string) ($request['scan_id'] ?? '')
                ),

            '/api/risk/batch'
                => (new RiskController())->process(
                    (string) ($request['scan_id'] ?? ''),
                    (int) ($request['batch_size'] ?? 250)
                ),

            '/api/risk/progress'
                => (new RiskController())->progress(
                    (string) ($request['scan_id'] ?? '')
                ),

            '/api/risk/findings'
                => (new RiskController())->findings(
                    (string) ($request['scan_id'] ?? ''),
                    isset($request['severity'])
                        ? (string) $request['severity']
                        : null
                ),    

            default
                => throw new RuntimeException(
                    'Onbekend API-endpoint: '
                    . $endpoint
                ),
        };
    }
}