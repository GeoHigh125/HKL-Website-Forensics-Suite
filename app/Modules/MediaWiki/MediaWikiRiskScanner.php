<?php

declare(strict_types=1);

namespace HKL\Forensics\Modules\MediaWiki;

use RuntimeException;

final class MediaWikiRiskScanner
{
    /**
     * @return array{
     *     scanned_files: int,
     *     findings: list<array{
     *         path: string,
     *         severity: string,
     *         category: string,
     *         reason: string,
     *         indicators: list<string>
     *     }>,
     *     statistics: array<string, int>
     * }
     */
    public function scanInventory(string $inventoryPath): array
    {
        if (!is_file($inventoryPath)) {
            throw new RuntimeException(
                'Inventarisbestand bestaat niet: ' . $inventoryPath
            );
        }

        if (!is_readable($inventoryPath)) {
            throw new RuntimeException(
                'Inventarisbestand is niet leesbaar.'
            );
        }

        $handle = fopen($inventoryPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'Inventarisbestand kon niet worden geopend.'
            );
        }

        $findings = [];
        $scannedFiles = 0;

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

                if (!is_array($record)) {
                    continue;
                }

                $scannedFiles++;

                foreach ($this->analyzeRecord($record) as $finding) {
                    $findings[] = $finding;
                }
            }
        } finally {
            fclose($handle);
        }

        usort(
            $findings,
            static fn (array $left, array $right): int =>
                self::severityWeight($right['severity'])
                <=> self::severityWeight($left['severity'])
        );

        return [
            'scanned_files' => $scannedFiles,
            'findings' => $findings,
            'statistics' => $this->buildStatistics($findings),
        ];
    }

    /**
     * Analyseert één inventarisrecord.
     *
     * @param array<string, mixed> $record
     *
     * @return list<array{
     *     path: string,
     *     severity: string,
     *     category: string,
     *     reason: string,
     *     indicators: list<string>
     * }>
     */
    public function scanRecord(array $record): array
    {
        return $this->analyzeRecord($record);
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return list<array{
     *     path: string,
     *     severity: string,
     *     category: string,
     *     reason: string,
     *     indicators: list<string>
     * }>
     */
    private function analyzeRecord(array $record): array
    {
        if (($record['status'] ?? null) !== 'processed') {
            return [];
        }

        $relativePath = (string) (
            $record['relative_path'] ?? ''
        );

        $absolutePath = (string) (
            $record['absolute_path'] ?? ''
        );

        $directoryCategory = (string) (
            $record['directory']['category'] ?? 'unknown'
        );

        $fileType = (string) (
            $record['file']['type'] ?? 'unknown'
        );

        $extension = strtolower(
            (string) ($record['file']['extension'] ?? '')
        );

        $filename = strtolower(
            basename($relativePath)
        );

        $findings = [];

        if (
            $directoryCategory === 'images'
            && $this->isExecutableExtension($extension)
        ) {
            $findings[] = $this->finding(
                $relativePath,
                'critical',
                'executable-in-images',
                'Uitvoerbaar bestand aangetroffen in de MediaWiki images-map.',
                [
                    'directory=images',
                    'extension=' . $extension,
                    'type=' . $fileType,
                ]
            );
        }

        if (
            in_array(
                $directoryCategory,
                ['cache', 'unknown'],
                true
            )
            && $this->isExecutableExtension($extension)
        ) {
            $findings[] = $this->finding(
                $relativePath,
                'high',
                'executable-in-risk-location',
                'Uitvoerbaar bestand aangetroffen in een risicolocatie.',
                [
                    'directory=' . $directoryCategory,
                    'extension=' . $extension,
                ]
            );
        }

        if ($this->hasSuspiciousFilename($filename)) {
            $findings[] = $this->finding(
                $relativePath,
                'high',
                'suspicious-filename',
                'Bestandsnaam komt overeen met een veelgebruikte webshell- of backdoornaam.',
                [
                    'filename=' . $filename,
                ]
            );
        }

        if ($this->hasDoubleExecutableExtension($filename)) {
            $findings[] = $this->finding(
                $relativePath,
                'high',
                'double-extension',
                'Bestand heeft een verdachte dubbele extensie.',
                [
                    'filename=' . $filename,
                ]
            );
        }

        if (
            $fileType === 'php'
            && $absolutePath !== ''
            && is_file($absolutePath)
            && is_readable($absolutePath)
            && !$this->isExcludedContext($relativePath)
        ) {
            $contentFinding = $this->scanPhpContent(
                $absolutePath,
                $relativePath
            );

            if ($contentFinding !== null) {
                $findings[] = $contentFinding;
            }
        }

        return $findings;
    }

    /**
     * @return array{
     *     path: string,
     *     severity: string,
     *     category: string,
     *     reason: string,
     *     indicators: list<string>
     * }|null
     */
    private function scanPhpContent(
        string $absolutePath,
        string $relativePath
    ): ?array {
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        $indicators = [];
        $score = 0;

        $patterns = [
            [
                '/\beval\s*\(/i',
                35,
                'eval()',
            ],
            [
                '/\bbase64_decode\s*\(/i',
                18,
                'base64_decode()',
            ],
            [
                '/\bgzinflate\s*\(/i',
                25,
                'gzinflate()',
            ],
            [
                '/\b(shell_exec|system|passthru|proc_open|popen)\s*\(/i',
                35,
                'server command execution',
            ],
            [
                '/\bassert\s*\(/i',
                25,
                'assert()',
            ],
            [
                '/\$_(GET|POST|REQUEST|COOKIE).{0,160}(eval|assert|system|exec|shell_exec|passthru|base64_decode)/is',
                70,
                'request data gekoppeld aan uitvoering of decoding',
            ],
            [
                '/HTTP_USER_AGENT.{0,300}(google|bot|spider|crawler)|'
                . '(google|bot|spider|crawler).{0,300}HTTP_USER_AGENT/is',
                55,
                'user-agent cloaking',
            ],
            [
                '/pemudaterang\.site|slogc2/i',
                100,
                'bekende IOC pemudaterang.site/slogc2',
            ],
            [
                '/FilesMan|WSO\b|C99\b|R57\b|Alfa\s*Shell|'
                . 'AnonymousFox|IndoXploit|b374k/i',
                100,
                'bekende webshellnaam',
            ],
            [
                '/https?:\/\/[a-z0-9.-]+\.[a-z]{2,}/i',
                8,
                'externe URL',
            ],
        ];

        foreach ($patterns as [$pattern, $weight, $label]) {
            if (preg_match($pattern, $contents) === 1) {
                $score += $weight;
                $indicators[] = $label;
            }
        }

        if ($score < 35) {
            return null;
        }

        $severity = match (true) {
            $score >= 100 => 'critical',
            $score >= 70 => 'high',
            $score >= 45 => 'medium',
            default => 'low',
        };

        return $this->finding(
            $relativePath,
            $severity,
            'php-patterns',
            'PHP-bestand bevat één of meer verdachte codepatronen.',
            array_values(array_unique($indicators))
        );
    }

    private function isExcludedContext(string $relativePath): bool
    {
        $normalized = strtolower(
            str_replace('\\', '/', $relativePath)
        );

        $excludedSegments = [
            '/tests/',
            '/test/',
            '/phpunit/',
            '/phan/',
            '/vendor/',
            '/maintenance/',
        ];

        foreach ($excludedSegments as $segment) {
            if (str_contains($normalized, $segment)) {
                return true;
            }
        }

        return false;
    }

    private function isExecutableExtension(string $extension): bool
    {
        return in_array(
            $extension,
            [
                'php',
                'php5',
                'php7',
                'php8',
                'phtml',
                'phar',
                'cgi',
                'pl',
                'py',
                'sh',
            ],
            true
        );
    }

    private function hasSuspiciousFilename(string $filename): bool
    {
        return in_array(
            $filename,
            [
                'shell.php',
                'wso.php',
                'c99.php',
                'r57.php',
                'alfa.php',
                'filesman.php',
                'cmd.php',
                'mailer.php',
                'upload.php',
                'uploader.php',
                'backdoor.php',
                'webshell.php',
                'adminer.php',
                'license.php',
                'about.php',
                'cache.php',
                'wp-vcd.php',
                'wp-tmp.php',
            ],
            true
        );
    }

    private function hasDoubleExecutableExtension(
        string $filename
    ): bool {
        return preg_match(
            '/\.(jpg|jpeg|png|gif|svg|txt|log|ico)\.'
            . '(php|php5|php7|php8|phtml|phar)$/i',
            $filename
        ) === 1;
    }

    /**
     * @param list<string> $indicators
     *
     * @return array{
     *     path: string,
     *     severity: string,
     *     category: string,
     *     reason: string,
     *     indicators: list<string>
     * }
     */
    private function finding(
        string $path,
        string $severity,
        string $category,
        string $reason,
        array $indicators
    ): array {
        return [
            'path' => $path,
            'severity' => $severity,
            'category' => $category,
            'reason' => $reason,
            'indicators' => $indicators,
        ];
    }

    /**
     * @param list<array<string, mixed>> $findings
     *
     * @return array<string, int>
     */
    private function buildStatistics(array $findings): array
    {
        $statistics = [
            'total' => count($findings),
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($findings as $finding) {
            $severity = (string) (
                $finding['severity'] ?? 'low'
            );

            if (isset($statistics[$severity])) {
                $statistics[$severity]++;
            }
        }

        return $statistics;
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