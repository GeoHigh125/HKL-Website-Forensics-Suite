<?php
declare(strict_types=1);

namespace HKL\Forensics\Contracts;

interface ScannerModule
{
    public function name(): string;
    public function platform(): string;
    public function supports(string $targetPath): bool;

    /** @return array<string,mixed> */
    public function scan(string $targetPath): array;
}
