<?php

declare(strict_types=1);

namespace HKL\Forensics\Core\Classification;

use HKL\Forensics\Core\PathGuard;

final class DirectoryClassifier
{
    public const CATEGORY_ROOT = 'root';
    public const CATEGORY_CONFIGURATION = 'configuration';
    public const CATEGORY_CORE = 'core';
    public const CATEGORY_EXTENSION = 'extension';
    public const CATEGORY_SKIN = 'skin';
    public const CATEGORY_VENDOR = 'vendor';
    public const CATEGORY_TESTS = 'tests';
    public const CATEGORY_IMAGES = 'images';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_RESOURCES = 'resources';
    public const CATEGORY_LANGUAGES = 'languages';
    public const CATEGORY_CACHE = 'cache';
    public const CATEGORY_DOCUMENTATION = 'documentation';
    public const CATEGORY_UNKNOWN = 'unknown';
    public const CATEGORY_INSTALLER = 'installer';
    public const CATEGORY_DATABASE = 'database';


    /**
     * Classificeert één map ten opzichte van de MediaWiki-hoofdmap.
     *
     * @return array{
     *     absolute_path: string,
     *     relative_path: string,
     *     category: string,
     *     component: string|null,
     *     known: bool,
     *     reason: string
     * }
     */
    public function classify(string $rootPath, string $directoryPath): array
    {
        $rootPath = PathGuard::requireReadableDirectory($rootPath);
        $directoryPath = PathGuard::requireReadableDirectory($directoryPath);

        $relativePath = $this->relativePath($rootPath, $directoryPath);

        if ($relativePath === '') {
            return $this->result(
                absolutePath: $directoryPath,
                relativePath: '',
                category: self::CATEGORY_ROOT,
                component: null,
                known: true,
                reason: 'Hoofdmap van de onderzochte MediaWiki-installatie.'
            );
        }

        $segments = $this->segments($relativePath);
        $firstSegment = strtolower($segments[0] ?? '');

        return match ($firstSegment) {
            'includes' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_CORE,
                null,
                true,
                'Onderdeel van de MediaWiki-core.'
            ),

            'extensions' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_EXTENSION,
                $segments[1] ?? null,
                true,
                isset($segments[1])
                    ? 'Onderdeel van de MediaWiki-extensie ' . $segments[1] . '.'
                    : 'Hoofdmap voor MediaWiki-extensies.'
            ),

            'skins' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_SKIN,
                $segments[1] ?? null,
                true,
                isset($segments[1])
                    ? 'Onderdeel van de MediaWiki-skin ' . $segments[1] . '.'
                    : 'Hoofdmap voor MediaWiki-skins.'
            ),

            'vendor' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_VENDOR,
                $this->vendorComponent($segments),
                true,
                'Composer- of bibliotheekcode in de vendor-map.'
            ),

            'tests', 'test', 'phpunit', 'phan' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_TESTS,
                null,
                true,
                'Bekende test- of statische-analysemap.'
            ),

            'images', 'uploads' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_IMAGES,
                null,
                true,
                'MediaWiki-upload- of afbeeldingenmap.'
            ),

            'maintenance' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_MAINTENANCE,
                null,
                true,
                'MediaWiki-onderhoudsscripts.'
            ),

            'mw-config' => $this->result(
                $directoryPath,
                $relativePath,
                'installer',
                null,
                true,
                'MediaWiki installatie- en configuratiemodule.'
            ),

            'sql' => $this->result(
                $directoryPath,
                $relativePath,
                'database',
                null,
                true,
                'MediaWiki databasescripts en schema-updates.'
            ),

            'resources' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_RESOURCES,
                null,
                true,
                'MediaWiki frontend-resources.'
            ),

            'languages' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_LANGUAGES,
                null,
                true,
                'MediaWiki taalbestanden.'
            ),

            'cache', 'tmp', 'temp' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_CACHE,
                null,
                true,
                'Cache- of tijdelijke map.'
            ),

            'docs', 'documentation' => $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_DOCUMENTATION,
                null,
                true,
                'Documentatiemap.'
            ),

            default => $this->classifyNestedKnownDirectory(
                directoryPath: $directoryPath,
                relativePath: $relativePath,
                segments: $segments
            ),
        };
    }

    /**
     * Classificeert alle mappen onder een MediaWiki-hoofdmap.
     *
     * @return list<array{
     *     absolute_path: string,
     *     relative_path: string,
     *     category: string,
     *     component: string|null,
     *     known: bool,
     *     reason: string
     * }>
     */
    public function classifyTree(string $rootPath): array
    {
        $rootPath = PathGuard::requireReadableDirectory($rootPath);

        $results = [
            $this->classify($rootPath, $rootPath),
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $rootPath,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isDir()) {
                continue;
            }

            $results[] = $this->classify(
                $rootPath,
                $item->getPathname()
            );
        }

        return $results;
    }

    /**
     * @param list<string> $segments
     *
     * @return array{
     *     absolute_path: string,
     *     relative_path: string,
     *     category: string,
     *     component: string|null,
     *     known: bool,
     *     reason: string
     * }
     */
    private function classifyNestedKnownDirectory(
        string $directoryPath,
        string $relativePath,
        array $segments
    ): array {
        $normalizedSegments = array_map(
            static fn (string $segment): string => strtolower($segment),
            $segments
        );

        if ($this->containsAny(
            $normalizedSegments,
            ['tests', 'test', 'phpunit', 'phan', 'integration', 'unit', 'benchmark', 'benchmarks']
        )) {
            return $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_TESTS,
                null,
                true,
                'Geneste test-, benchmark- of statische-analysemap.'
            );
        }

        if ($this->containsAny(
            $normalizedSegments,
            ['cache', 'caches', 'tmp', 'temp']
        )) {
            return $this->result(
                $directoryPath,
                $relativePath,
                self::CATEGORY_CACHE,
                null,
                true,
                'Geneste cache- of tijdelijke map.'
            );
        }

        return $this->result(
            $directoryPath,
            $relativePath,
            self::CATEGORY_UNKNOWN,
            null,
            false,
            'Map valt niet onder een bekende MediaWiki-categorie.'
        );
    }

    /**
     * @param list<string> $segments
     */
    private function vendorComponent(array $segments): ?string
    {
        if (!isset($segments[1])) {
            return null;
        }

        if (!isset($segments[2])) {
            return $segments[1];
        }

        return $segments[1] . '/' . $segments[2];
    }

    private function relativePath(string $rootPath, string $directoryPath): string
    {
        $normalizedRoot = rtrim($this->normalizePath($rootPath), '/');
        $normalizedDirectory = rtrim($this->normalizePath($directoryPath), '/');

        if ($normalizedDirectory === $normalizedRoot) {
            return '';
        }

        if (!str_starts_with($normalizedDirectory, $normalizedRoot . '/')) {
            throw new \InvalidArgumentException(
                'De te classificeren map ligt niet binnen de MediaWiki-hoofdmap.'
            );
        }

        return ltrim(
            substr($normalizedDirectory, strlen($normalizedRoot)),
            '/'
        );
    }

    /**
     * @return list<string>
     */
    private function segments(string $relativePath): array
    {
        if ($relativePath === '') {
            return [];
        }

        return array_values(
            array_filter(
                explode('/', $this->normalizePath($relativePath)),
                static fn (string $segment): bool => $segment !== ''
            )
        );
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * @param list<string> $haystack
     * @param list<string> $needles
     */
    private function containsAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $haystack, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     absolute_path: string,
     *     relative_path: string,
     *     category: string,
     *     component: string|null,
     *     known: bool,
     *     reason: string
     * }
     */
    private function result(
        string $absolutePath,
        string $relativePath,
        string $category,
        ?string $component,
        bool $known,
        string $reason
    ): array {
        return [
            'absolute_path' => $absolutePath,
            'relative_path' => $relativePath,
            'category' => $category,
            'component' => $component,
            'known' => $known,
            'reason' => $reason,
        ];
    }
}