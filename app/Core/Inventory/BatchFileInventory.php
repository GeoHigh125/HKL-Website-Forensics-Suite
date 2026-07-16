<?php

declare(strict_types=1);

namespace HKL\Forensics\Core\Inventory;

use FilesystemIterator;
use HKL\Forensics\Core\Classification\DirectoryClassifier;
use HKL\Forensics\Core\Classification\FileClassifier;
use HKL\Forensics\Core\Hashing\HashEngine;
use HKL\Forensics\Core\Metadata\MetadataEngine;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class BatchFileInventory
{
    private const MANIFEST_FILE = 'manifest.json';
    private const PROGRESS_FILE = 'progress.json';
    private const INVENTORY_FILE = 'inventory.jsonl';

    public function __construct(
        private readonly DirectoryClassifier $directoryClassifier =
            new DirectoryClassifier(),

        private readonly FileClassifier $fileClassifier =
            new FileClassifier(),

        private readonly MetadataEngine $metadataEngine =
            new MetadataEngine(),

        private readonly HashEngine $hashEngine =
            new HashEngine(),
    ) {
    }

    /**
     * Maakt een nieuwe batchscan aan en verzamelt eerst alleen
     * de lijst met bestanden.
     *
     * @return array<string, mixed>
     */
    public function initialize(
        string $scanId,
        string $rootPath,
        string $storagePath
    ): array {
        $rootPath = $this->requireReadableDirectory($rootPath);

        $scanDirectory = $this->createScanDirectory(
            $storagePath,
            $scanId
        );

        $files = $this->collectFiles($rootPath);

        $manifest = [
            'scan_id' => $scanId,
            'root_path' => $rootPath,
            'created_at' => date(DATE_ATOM),
            'total_files' => count($files),
            'files' => $files,
        ];

        $progress = [
            'scan_id' => $scanId,
            'status' => count($files) === 0
                ? 'completed'
                : 'pending',

            'phase' => count($files) === 0
                ? 'completed'
                : 'inventory',

            'total_files' => count($files),
            'processed_files' => 0,
            'failed_files' => 0,
            'current_file' => null,
            'percentage' => count($files) === 0
                ? 100
                : 0,

            'statistics' => $this->emptyStatistics(),
            'started_at' => null,
            'updated_at' => date(DATE_ATOM),
            'completed_at' => count($files) === 0
                ? date(DATE_ATOM)
                : null,
        ];

        $this->writeJson(
            $scanDirectory . DIRECTORY_SEPARATOR . self::MANIFEST_FILE,
            $manifest
        );

        $this->writeJson(
            $scanDirectory . DIRECTORY_SEPARATOR . self::PROGRESS_FILE,
            $progress
        );

        $inventoryPath = $scanDirectory
            . DIRECTORY_SEPARATOR
            . self::INVENTORY_FILE;

        if (file_put_contents($inventoryPath, '') === false) {
            throw new RuntimeException(
                'Het inventarisbestand kon niet worden aangemaakt.'
            );
        }

        return $progress;
    }

    /**
     * Verwerkt één batch.
     *
     * @return array<string, mixed>
     */
    public function processBatch(
        string $scanId,
        string $storagePath,
        int $batchSize = 100
    ): array {
        $batchSize = max(1, min($batchSize, 500));

        $scanDirectory = $this->scanDirectory(
            $storagePath,
            $scanId
        );

        $manifest = $this->readJson(
            $scanDirectory . DIRECTORY_SEPARATOR . self::MANIFEST_FILE
        );

        $progress = $this->readJson(
            $scanDirectory . DIRECTORY_SEPARATOR . self::PROGRESS_FILE
        );

        if (($progress['status'] ?? null) === 'completed') {
            return $progress;
        }

        $files = $manifest['files'] ?? [];

        if (!is_array($files)) {
            throw new RuntimeException(
                'Het scanmanifest bevat geen geldige bestandenlijst.'
            );
        }

        $totalFiles = count($files);

        $startOffset = (int) (
            $progress['processed_files'] ?? 0
        );

        $batchFiles = array_slice(
            $files,
            $startOffset,
            $batchSize
        );

        if (($progress['started_at'] ?? null) === null) {
            $progress['started_at'] = date(DATE_ATOM);
        }

        $progress['status'] = 'running';
        $progress['phase'] = 'inventory';

        foreach ($batchFiles as $relativePath) {
            if (!is_string($relativePath)) {
                continue;
            }

            $absolutePath = $this->joinPath(
                (string) $manifest['root_path'],
                $relativePath
            );

            $progress['current_file'] = $relativePath;

            try {
                $record = $this->analyzeFile(
                    (string) $manifest['root_path'],
                    $absolutePath,
                    $relativePath
                );

                $this->appendInventoryRecord(
                    $scanDirectory,
                    $record
                );

                $this->updateStatistics(
                    $progress['statistics'],
                    $record
                );
            } catch (Throwable $exception) {
                $progress['failed_files'] =
                    (int) ($progress['failed_files'] ?? 0) + 1;

                $this->appendInventoryRecord(
                    $scanDirectory,
                    [
                        'relative_path' => $relativePath,
                        'absolute_path' => $absolutePath,
                        'status' => 'failed',
                        'error' => $exception->getMessage(),
                    ]
                );
            }

            $progress['processed_files'] =
                (int) ($progress['processed_files'] ?? 0) + 1;
        }

        $processed = (int) $progress['processed_files'];

        $progress['percentage'] = $totalFiles > 0
            ? round(($processed / $totalFiles) * 100, 2)
            : 100;

        $progress['updated_at'] = date(DATE_ATOM);

        if ($processed >= $totalFiles) {
            $progress['status'] = 'completed';
            $progress['phase'] = 'completed';

            $progress['last_file'] =
                $progress['current_file'] ?? null;

            $progress['percentage'] = 100;
            $progress['completed_at'] = date(DATE_ATOM);
        }

        $this->writeJson(
            $scanDirectory . DIRECTORY_SEPARATOR . self::PROGRESS_FILE,
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
            $this->scanDirectory($storagePath, $scanId)
            . DIRECTORY_SEPARATOR
            . self::PROGRESS_FILE
        );
    }

    /**
     * Leest de complete inventaris uit het JSONL-bestand.
     *
     * Gebruik dit pas nadat de scan is voltooid.
     *
     * @return list<array<string, mixed>>
     */
    public function inventory(
        string $scanId,
        string $storagePath
    ): array {
        $inventoryPath = $this->scanDirectory(
            $storagePath,
            $scanId
        ) . DIRECTORY_SEPARATOR . self::INVENTORY_FILE;

        if (!is_file($inventoryPath)) {
            throw new RuntimeException(
                'Het inventarisbestand bestaat niet.'
            );
        }

        $handle = fopen($inventoryPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'Het inventarisbestand kon niet worden geopend.'
            );
        }

        $records = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $record = json_decode(
                    $line,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (is_array($record)) {
                    $records[] = $record;
                }
            }
        } finally {
            fclose($handle);
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    private function collectFiles(string $rootPath): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $rootPath,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $files[] = $this->relativePath(
                $rootPath,
                $item->getPathname()
            );
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeFile(
        string $rootPath,
        string $absolutePath,
        string $relativePath
    ): array {
        $directoryInfo = $this->directoryClassifier->classify(
            $rootPath,
            dirname($absolutePath)
        );

        $fileInfo = $this->fileClassifier->classify(
            $absolutePath
        );

        $metadata = $this->metadataEngine->analyze(
            $absolutePath
        );

        /*
         * Voor de normale inventarisatiescan berekenen we alleen SHA-256.
         * SHA-1 en MD5 worden later onderdeel van de uitgebreide modus.
         */
        $sha256 = $this->hashEngine->sha256(
            $absolutePath
        );

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'status' => 'processed',
            'directory' => $directoryInfo,
            'file' => $fileInfo,
            'metadata' => $metadata,
            'hashes' => [
                'sha256' => $sha256,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $statistics
     * @param array<string, mixed> $record
     */
    private function updateStatistics(
        array &$statistics,
        array $record
    ): void {
        $statistics['total'] =
            (int) ($statistics['total'] ?? 0) + 1;

        $type = $record['file']['type'] ?? 'unknown';

        if (!is_string($type)) {
            $type = 'unknown';
        }

        if (!isset($statistics['types'][$type])) {
            $statistics['types'][$type] = 0;
        }

        $statistics['types'][$type]++;

        $category = $record['directory']['category']
            ?? 'unknown';

        if (!is_string($category)) {
            $category = 'unknown';
        }

        if (!isset($statistics['categories'][$category])) {
            $statistics['categories'][$category] = 0;
        }

        $statistics['categories'][$category]++;

        if (($record['file']['is_executable'] ?? false) === true) {
            $statistics['executable'] =
                (int) ($statistics['executable'] ?? 0) + 1;
        }

        $size = (int) (
            $record['metadata']['size'] ?? 0
        );

        $statistics['total_size'] =
            (int) ($statistics['total_size'] ?? 0) + $size;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStatistics(): array
    {
        return [
            'total' => 0,
            'total_size' => 0,
            'executable' => 0,
            'types' => [],
            'categories' => [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function appendInventoryRecord(
        string $scanDirectory,
        array $record
    ): void {
        $json = json_encode(
            $record,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        $result = file_put_contents(
            $scanDirectory
            . DIRECTORY_SEPARATOR
            . self::INVENTORY_FILE,

            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($result === false) {
            throw new RuntimeException(
                'Een inventarisrecord kon niet worden opgeslagen.'
            );
        }
    }

    private function createScanDirectory(
        string $storagePath,
        string $scanId
    ): string {
        $scansDirectory = rtrim(
            $storagePath,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . 'scans';

        if (
            !is_dir($scansDirectory)
            && !mkdir($scansDirectory, 0775, true)
            && !is_dir($scansDirectory)
        ) {
            throw new RuntimeException(
                'De map storage/scans kon niet worden aangemaakt.'
            );
        }

        $scanDirectory = $scansDirectory
            . DIRECTORY_SEPARATOR
            . $this->safeScanId($scanId);

        if (is_dir($scanDirectory)) {
            throw new RuntimeException(
                'Er bestaat al een scan met dit scan-ID.'
            );
        }

        if (
            !mkdir($scanDirectory, 0775, true)
            && !is_dir($scanDirectory)
        ) {
            throw new RuntimeException(
                'De scanmap kon niet worden aangemaakt.'
            );
        }

        return $scanDirectory;
    }

    private function scanDirectory(
        string $storagePath,
        string $scanId
    ): string {
        $scanDirectory = rtrim(
            $storagePath,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR
            . 'scans'
            . DIRECTORY_SEPARATOR
            . $this->safeScanId($scanId);

        if (!is_dir($scanDirectory)) {
            throw new RuntimeException(
                'De opgegeven scan bestaat niet.'
            );
        }

        return $scanDirectory;
    }

    private function safeScanId(string $scanId): string
    {
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

        return $safeScanId;
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
                'JSON-bestand kon niet worden opgeslagen: '
                . $filename
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

    private function requireReadableDirectory(
        string $path
    ): string {
        $realPath = realpath($path);

        if (
            $realPath === false
            || !is_dir($realPath)
        ) {
            throw new RuntimeException(
                'De scanmap bestaat niet.'
            );
        }

        if (!is_readable($realPath)) {
            throw new RuntimeException(
                'De scanmap is niet leesbaar.'
            );
        }

        return $realPath;
    }

    private function relativePath(
        string $rootPath,
        string $filename
    ): string {
        $normalizedRoot = rtrim(
            $this->normalizePath($rootPath),
            '/'
        );

        $normalizedFile = $this->normalizePath(
            $filename
        );

        if (
            !str_starts_with(
                $normalizedFile,
                $normalizedRoot . '/'
            )
        ) {
            throw new RuntimeException(
                'Bestand ligt niet binnen het scanpad.'
            );
        }

        return ltrim(
            substr(
                $normalizedFile,
                strlen($normalizedRoot)
            ),
            '/'
        );
    }

    private function joinPath(
        string $rootPath,
        string $relativePath
    ): string {
        return rtrim(
            $rootPath,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}