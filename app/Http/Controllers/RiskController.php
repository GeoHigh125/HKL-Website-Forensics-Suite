<?php

declare(strict_types=1);

namespace HKL\Forensics\Http\Controllers;

use HKL\Forensics\Modules\MediaWiki\BatchMediaWikiRiskScanner;
use RuntimeException;
use Throwable;

final class RiskController
{
    public function __construct(
        private readonly BatchMediaWikiRiskScanner $riskScanner =
            new BatchMediaWikiRiskScanner()
    ) {
    }

    /**
     * Initialiseert de risicoanalyse voor een bestaande scan.
     *
     * @return array<string, mixed>
     */
    public function start(string $scanId): array
    {
        $scanId = $this->requireScanId($scanId);

        try {
            $progress = $this->riskScanner->initialize(
                $scanId,
                $this->storagePath()
            );

            return [
                'success' => true,
                'scan_id' => $scanId,
                'message' => 'Risicoanalyse succesvol gestart.',
                'progress' => $progress,
            ];
        } catch (Throwable $exception) {
            return $this->errorResponse(
                $scanId,
                $exception
            );
        }
    }

    /**
     * Verwerkt één batch van de risicoanalyse.
     *
     * @return array<string, mixed>
     */
    public function process(
        string $scanId,
        int $batchSize = 250
    ): array {
        $scanId = $this->requireScanId($scanId);

        $batchSize = max(
            1,
            min($batchSize, 1000)
        );

        try {
            $progress = $this->riskScanner->processBatch(
                $scanId,
                $this->storagePath(),
                $batchSize
            );

            return [
                'success' => true,
                'scan_id' => $scanId,
                'progress' => $progress,
            ];
        } catch (Throwable $exception) {
            return $this->errorResponse(
                $scanId,
                $exception
            );
        }
    }

    /**
     * Geeft de actuele voortgang terug.
     *
     * @return array<string, mixed>
     */
    public function progress(string $scanId): array
    {
        $scanId = $this->requireScanId($scanId);

        try {
            $progress = $this->riskScanner->progress(
                $scanId,
                $this->storagePath()
            );

            return [
                'success' => true,
                'scan_id' => $scanId,
                'progress' => $progress,
            ];
        } catch (Throwable $exception) {
            return $this->errorResponse(
                $scanId,
                $exception
            );
        }
    }

    /**
     * Geeft de gevonden risico's terug.
     *
     * De severity-filter is optioneel:
     * critical, high, medium of low.
     *
     * @return array<string, mixed>
     */
    public function findings(
        string $scanId,
        ?string $severity = null
    ): array {
        $scanId = $this->requireScanId($scanId);

        $severity = $this->normalizeSeverity(
            $severity
        );

        try {
            $findings = $this->riskScanner->findings(
                $scanId,
                $this->storagePath(),
                $severity
            );

            return [
                'success' => true,
                'scan_id' => $scanId,
                'severity_filter' => $severity,
                'total' => count($findings),
                'findings' => $findings,
            ];
        } catch (Throwable $exception) {
            return $this->errorResponse(
                $scanId,
                $exception
            );
        }
    }

    private function requireScanId(
        string $scanId
    ): string {
        $scanId = trim($scanId);

        if ($scanId === '') {
            throw new RuntimeException(
                'Geen scan-ID opgegeven.'
            );
        }

        if (
            preg_match(
                '/^[A-Za-z0-9_-]+$/',
                $scanId
            ) !== 1
        ) {
            throw new RuntimeException(
                'Het scan-ID bevat ongeldige tekens.'
            );
        }

        return $scanId;
    }

    private function normalizeSeverity(
        ?string $severity
    ): ?string {
        if ($severity === null) {
            return null;
        }

        $severity = strtolower(
            trim($severity)
        );

        if ($severity === '') {
            return null;
        }

        $allowed = [
            'critical',
            'high',
            'medium',
            'low',
        ];

        if (!in_array($severity, $allowed, true)) {
            throw new RuntimeException(
                'Ongeldig risiconiveau opgegeven.'
            );
        }

        return $severity;
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR
            . 'storage';
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(
        string $scanId,
        Throwable $exception
    ): array {
        return [
            'success' => false,
            'scan_id' => $scanId,
            'message' => $exception->getMessage(),
        ];
    }
}