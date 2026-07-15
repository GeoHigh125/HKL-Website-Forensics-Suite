<?php
declare(strict_types=1);

namespace HKL\Forensics\Core;

use HKL\Forensics\Contracts\ScannerModule;

final class Application
{
    /** @var list<ScannerModule> */
    private array $modules = [];

    public function __construct(private readonly array $config) {}

    public function registerModule(ScannerModule $module): void
    {
        $this->modules[] = $module;
    }

    /** @return list<ScannerModule> */
    public function modules(): array
    {
        return $this->modules;
    }

    public function config(): array
    {
        return $this->config;
    }
}
