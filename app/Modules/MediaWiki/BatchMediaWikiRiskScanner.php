<?php

declare(strict_types=1);

namespace HKL\Forensics\Modules\MediaWiki;

use JsonException;
use RuntimeException;
use Throwable;

final class BatchMediaWikiRiskScanner
{
    private const INVENTORY_FILE = 'inventory.jsonl';
    private const PROGRESS_FILE = 'risk-progress.json';
    private const FINDINGS_FILE = 'findings.jsonl';
    private const SUMMARY_FILE = 'risk-summary.json';

    public function __construct(
        private readonly MediaWikiRiskScanner $riskScanner =
            new MediaWikiRiskScanner()
    ) {
    }

    /**
     * Initialiseert de risicoanalyse voor een bestaande bestandsscan.
     *
     * @return array<string, mixed>
     */
    public function initialize(
        string $scanId,
        string $storagePath
    ): array {
        $scanDirectory = $this->scanDirectory(
            $scanId,
            $storagePath
        );

        $inventoryPath = $scanDirectory
            . DIRECTORY_SEPARATOR
            . self::INVENTORY_FILE;

        if (!is_file($inventoryPath)) {
            throw new RuntimeException(
                'inventory.jsonl bestaat niet voor deze scan.'
            );
        }

        if (!is_readable($inventoryPath)) {
            throw new RuntimeException(
                'inventory.jsonl is niet leesbaar.'
            );
        }

        $totalRecords = $this->countInventoryRecords(
            $inventoryPath
        );

        $progress = [
            'scan_id' => $scanId,
            'status' => $totalRecords === 0
                ? 'completed'
                : 'pending',
            'phase' => $totalRecords === 0
                ? 'completed'
                : 'risk-analysis',
            'total_records' => $totalRecords,
            'processed_records' => 0,
            'failed_records' => 0,
            'findings' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'current_file' => null,
            'percentage' => $totalRecords === 0
                ? 100
                : 0,
            'started_at' => null,
            'updated_at' => date(DATE_ATOM),
            'completed_at' => $totalRecords === 0
                ? date(DATE_ATOM)
                : null,
        ];

        $this->writeJson(
            $scanDirectory
                . DIRECTORY_SEPARATOR
                . self::PROGRESS_FILE,
            $progress
        );

        $findingsPath = $scanDirectory
            . DIRECTORY_SEPARATOR
            . self::FINDINGS_FILE;

        if (file_put_contents($findingsPath, '') === false) {
            throw new RuntimeException(
                'findings.jsonl kon niet worden aangemaakt.'
            );
        }

        $this->writeSummary(
            $scanDirectory,
            $progress
        );

        return $progress;
    }

    /**
     * Verwerkt één batch inventarisrecords.
     *
     * @return array<string, mixed>
     */
    public function processBatch(
        string $scanId,
        string $storagePath,
        int $batchSize = 250
    ): array {
        $batchSize = max(1, min($batchSize, 1000));

        $scanDirectory = $this->scanDirectory(
            $scanId,
            $storagePath
        );

        $progressPath = $scanDirectory
            . DIRECTORY_SEPARATOR
            . self::PROGRESS_FILE;

        $progress = $this->readJson($progressPath);

        if (($progress['status'] ?? null) === 'completed') {
            return $progress;
        }

        $inventoryPath = $scanDirectory
            . DIRECTORY_SEPARATOR
            . self::INVENTORY_FILE;

        $offset = (int) (
            $progress['processed_records'] ?? 0
        );

        $records = $this->readBatch(
            $inventoryPath,
            $offset,
            $batchSize
        );

        if (($progress['started_at'] ?? null) === null) {
            $progress['started_at'] = date(DATE_ATOM);
        }

        $progress['status'] = 'running';
        $progress['phase'] = 'risk-analysis';

        foreach ($records as $record) {
            $relativePath = (string) (
                $record['relative_path'] ?? ''
            );

            $progress['current_file'] = $relativePath;

            try {
                $findings = $this->riskScanner->scanRecord(
                    $record
                );

                foreach ($findings as $finding) {
                    $this->appendFinding(
                        $scanDirectory,
                        $finding
                    );

                    $this->updateFindingStatistics(
                        $progress,
                        $finding
                    );
                }
            } catch (Throwable $exception) {
                $progress['failed_records'] =
                    (int) (
                        $progress['failed_records'] ?? 0
                    ) + 1;

                $this->appendFinding(
                    $scanDirectory,
                    [
                        'path' => $relativePath,
                        'severity' => 'low',
                        'category' => 'analysis-error',
                        'reason' => 'Bestand kon niet worden geanalyseerd.',
                        'indicators' => [
                            $exception->getMessage(),
                        ],
                    ]
                );
            }

            $progress['processed_records'] =
                (int) (
                    $progress['processed_records'] ?? 0
                ) + 1;
        }

        $total = (int) (
            $progress['total_records'] ?? 0
        );

        $processed = (int) (
            $progress['processed_records'] ?? 0
        );

        $progress['percentage'] = $total > 0
            ? round(($processed / $total) * 100, 2)
            : 100;

        $progress['updated_at'] = date(DATE_ATOM);

        if ($processed >= $total) {
            $progress['status'] = 'completed';
            $progress['phase'] = 'completed';
            $progress['percentage'] = 100;
            $progress['last_file'] =
                $progress['current_file'] ?? null;
            $progress['completed_at'] = date(DATE_ATOM);
        }

        $this->writeJson(
            $progressPath,
            $progress
        );

        $this->writeSummary(
            $scanDirectory,
            $progress
        );

        return $progress;
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(
        string $scanId,
        string $storagePath
    ): array {
        return $this->readJson(
            $this->scanDirectory($scanId, $storagePath)
                . DIRECTORY_SEPARATOR
                . self::PROGRESS_FILE
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findings(
        string $scanId,
        string $storagePath,
        ?string $severity = null
    ): array {
        $filename = $this->scanDirectory(
            $scanId,
            $storagePath
        ) . DIRECTORY_SEPARATOR . self::FINDINGS_FILE;

        if (!is_file($filename)) {
            throw new RuntimeException(
                'findings.jsonl bestaat niet.'
            );
        }

        $handle = fopen($filename, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'findings.jsonl kon niet worden geopend.'
            );
        }

        $results = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $finding = json_decode(
                    $line,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (!is_array($finding)) {
                    continue;
                }

                if (
                    $severity !== null
                    && ($finding['severity'] ?? null)
                        !== $severity
                ) {
                    continue;
                }

                $results[] = $finding;
            }
        } finally {
            fclose($handle);
        }

        usort(
            $results,
            static fn (array $left, array $right): int =>
                self::severityWeight(
                    (string) ($right['severity'] ?? '')
                )
                <=>
                self::severityWeight(
                    (string) ($left['severity'] ?? '')
                )
        );

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readBatch(
        string $inventoryPath,
        int $offset,
        int $batchSize
    ): array {
        $handle = fopen($inventoryPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'inventory.jsonl kon niet worden geopend.'
            );
        }

        $records = [];
        $lineNumber = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                if ($lineNumber < $offset) {
                    $lineNumber++;

                    continue;
                }

                if (count($records) >= $batchSize) {
                    break;
                }

                $lineNumber++;

                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                try {
                    $record = json_decode(
                        $line,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (JsonException) {
                    continue;
                }

                if (is_array($record)) {
                    $records[] = $record;
                }
            }
        } finally {
            fclose($handle);
        }

        return $records;
    }

    private function countInventoryRecords(
        string $inventoryPath
    ): int {
        $handle = fopen($inventoryPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'inventory.jsonl kon niet worden geopend.'
            );
        }

        $count = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) !== '') {
                    $count++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $finding
     */
    private function appendFinding(
        string $scanDirectory,
        array $finding
    ): void {
        $json = json_encode(
            $finding,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        $result = file_put_contents(
            $scanDirectory
                . DIRECTORY_SEPARATOR
                . self::FINDINGS_FILE,
            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($result === false) {
            throw new RuntimeException(
                'Een risicovondst kon niet worden opgeslagen.'
            );
        }
    }

    /**
     * @param array<string, mixed> $progress
     * @param array<string, mixed> $finding
     */
    private function updateFindingStatistics(
        array &$progress,
        array $finding
    ): void {
        $progress['findings'] =
            (int) ($progress['findings'] ?? 0) + 1;

        $severity = (string) (
            $finding['severity'] ?? 'low'
        );

        if (
            in_array(
                $severity,
                ['critical', 'high', 'medium', 'low'],
                true
            )
        ) {
            $progress[$severity] =
                (int) ($progress[$severity] ?? 0) + 1;
        }
    }

    /**
     * @param array<string, mixed> $progress
     */
    private function writeSummary(
        string $scanDirectory,
        array $progress
    ): void {
        $summary = [
            'scan_id' => $progress['scan_id'] ?? null,
            'status' => $progress['status'] ?? null,
            'total_records' =>
                $progress['total_records'] ?? 0,
            'processed_records' =>
                $progress['processed_records'] ?? 0,
            'failed_records' =>
                $progress['failed_records'] ?? 0,
            'findings' => $progress['findings'] ?? 0,
            'critical' => $progress['critical'] ?? 0,
            'high' => $progress['high'] ?? 0,
            'medium' => $progress['medium'] ?? 0,
            'low' => $progress['low'] ?? 0,
            'updated_at' => date(DATE_ATOM),
        ];

        $this->writeJson(
            $scanDirectory
                . DIRECTORY_SEPARATOR
                . self::SUMMARY_FILE,
            $summary
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(
        string $filename,
        array $data
    ): void {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        if (
            file_put_contents(
                $filename,
                $json . PHP_EOL,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'JSON-bestand kon niet worden opgeslagen.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $filename): array
    {
        if (!is_file($filename)) {
            throw new RuntimeException(
                'JSON-bestand bestaat niet: ' . $filename
            );
        }

        $contents = file_get_contents($filename);

        if ($contents === false) {
            throw new RuntimeException(
                'JSON-bestand kon niet worden gelezen.'
            );
        }

        $data = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($data)) {
            throw new RuntimeException(
                'JSON-bestand bevat geen geldig object.'
            );
        }

        return $data;
    }

    private function scanDirectory(
        string $scanId,
        string $storagePath
    ): string {
        $safeScanId = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            $scanId
        );

        if (
            $safeScanId === null
            || $safeScanId === ''
        ) {
            throw new RuntimeException(
                'Ongeldig scan-ID.'
            );
        }

        $directory = rtrim(
            $storagePath,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR
            . 'scans'
            . DIRECTORY_SEPARATOR
            . $safeScanId;

        if (!is_dir($directory)) {
            throw new RuntimeException(
                'De opgegeven scanmap bestaat niet.'
            );
        }

        return $directory;
    }

    private static function severityWeight(
        string $severity
    ): int {
        return match ($severity) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}