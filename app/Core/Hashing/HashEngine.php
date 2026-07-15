<?php

declare(strict_types=1);

namespace HKL\Forensics\Core\Hashing;

use RuntimeException;

final class HashEngine
{
    /**
     * Berekent meerdere hashes van één bestand.
     *
     * @return array{
     *     path: string,
     *     size: int,
     *     sha256: string,
     *     sha1: string,
     *     md5: string
     * }
     */
    public function analyze(string $filename): array
    {
        if (!is_file($filename)) {
            throw new RuntimeException(
                'Bestand bestaat niet: ' . $filename
            );
        }

        if (!is_readable($filename)) {
            throw new RuntimeException(
                'Bestand is niet leesbaar: ' . $filename
            );
        }

        $realPath = realpath($filename);

        if ($realPath === false) {
            throw new RuntimeException(
                'Het absolute bestandspad kon niet worden bepaald.'
            );
        }

        $size = filesize($realPath);

        if ($size === false) {
            throw new RuntimeException(
                'De bestandsgrootte kon niet worden gelezen.'
            );
        }

        return [
            'path' => $realPath,
            'size' => $size,
            'sha256' => $this->hashFile('sha256', $realPath),
            'sha1' => $this->hashFile('sha1', $realPath),
            'md5' => $this->hashFile('md5', $realPath),
        ];
    }

    public function sha256(string $filename): string
    {
        return $this->hashFile('sha256', $filename);
    }

    public function sha1(string $filename): string
    {
        return $this->hashFile('sha1', $filename);
    }

    public function md5(string $filename): string
    {
        return $this->hashFile('md5', $filename);
    }

    public function matchesSha256(
        string $filename,
        string $expectedHash
    ): bool {
        return hash_equals(
            strtolower(trim($expectedHash)),
            strtolower($this->sha256($filename))
        );
    }

    private function hashFile(
        string $algorithm,
        string $filename
    ): string {
        if (!in_array($algorithm, hash_algos(), true)) {
            throw new RuntimeException(
                'Hash-algoritme wordt niet ondersteund: '
                . $algorithm
            );
        }

        if (!is_file($filename)) {
            throw new RuntimeException(
                'Bestand bestaat niet: ' . $filename
            );
        }

        if (!is_readable($filename)) {
            throw new RuntimeException(
                'Bestand is niet leesbaar: ' . $filename
            );
        }

        $hash = hash_file($algorithm, $filename);

        if ($hash === false) {
            throw new RuntimeException(
                sprintf(
                    'De %s-hash kon niet worden berekend voor %s.',
                    $algorithm,
                    $filename
                )
            );
        }

        return strtolower($hash);
    }
}