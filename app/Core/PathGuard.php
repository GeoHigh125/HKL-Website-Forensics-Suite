<?php
declare(strict_types=1);

namespace HKL\Forensics\Core;

use InvalidArgumentException;

final class PathGuard
{
    public static function requireReadableDirectory(string $path): string
    {
        $resolved = realpath($path);

        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('De opgegeven scanmap bestaat niet.');
        }

        if (!is_readable($resolved)) {
            throw new InvalidArgumentException('De opgegeven scanmap is niet leesbaar.');
        }

        return $resolved;
    }
}
