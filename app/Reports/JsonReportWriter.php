<?php
declare(strict_types=1);

namespace HKL\Forensics\Reports;

use RuntimeException;

final class JsonReportWriter
{
    public function write(array $report, string $outputPath): void
    {
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false || file_put_contents($outputPath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Rapport kon niet worden opgeslagen.');
        }
    }
}
