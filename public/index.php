<?php

declare(strict_types=1);

use HKL\Forensics\Core\Application;
use HKL\Forensics\Modules\MediaWiki\MediaWikiScanner;
use HKL\Forensics\Services\ScanService;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);

    exit('Voer eerst composer install uit.');
}

require $autoloadPath;

$config = require dirname(__DIR__) . '/config/app.php';

$app = new Application($config);
$app->registerModule(new MediaWikiScanner());

$defaultScanPath = 'E:\back_HKL_Public_2026-07-07\historischekringlosser.nl\public_html\wiki1';

$scanPath = trim(
    (string) ($_POST['scan_path'] ?? $defaultScanPath)
);

$scanResult = null;
$scanError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $scanService = new ScanService();

        $scanResult = $scanService->scan($scanPath);
    } catch (Throwable $exception) {
        $scanError = $exception->getMessage();
    }
}

/**
 * HTML veilig weergeven.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * @param array<string, int> $statistics
 */
function totalDirectories(array $statistics): int
{
    return array_sum($statistics);
}

/**
 * @param array<int, array<string, mixed>> $directories
 *
 * @return array<int, array<string, mixed>>
 */
function unknownDirectories(array $directories): array
{
    return array_values(
        array_filter(
            $directories,
            static fn (array $directory): bool =>
                ($directory['category'] ?? null) === 'unknown'
        )
    );
}

header('Content-Type: text/html; charset=UTF-8');

$statistics = [];

$unknownDirectories = [];

$totalDirectoryCount = 0;

$knownDirectoryCount = 0;

$unknownDirectoryCount = 0;

if (
    is_array($scanResult)
    && ($scanResult['success'] ?? false) === true
) {
    $statistics = $scanResult['statistics'] ?? [];

    $unknownDirectories = unknownDirectories(
        $scanResult['directories'] ?? []
    );

    $totalDirectoryCount = totalDirectories($statistics);

    $unknownDirectoryCount = count($unknownDirectories);

    $knownDirectoryCount = max(
        0,
        $totalDirectoryCount - $unknownDirectoryCount
    );
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= e($config['name'] ?? 'HKL Website Forensics Suite') ?>
    </title>

    <style>
        :root {
            --hkl-blue-900: #243b53;
            --hkl-blue-700: #34618c;
            --hkl-blue-100: #dbeafe;
            --hkl-green-700: #287a4b;
            --hkl-green-100: #dcfce7;
            --hkl-orange-700: #a65300;
            --hkl-orange-100: #ffedd5;
            --hkl-red-700: #b42318;
            --hkl-red-100: #fee2e2;
            --hkl-gray-050: #f5f7fb;
            --hkl-gray-100: #eef2f7;
            --hkl-gray-300: #d5dde8;
            --hkl-gray-600: #566273;
            --hkl-gray-900: #172033;
            --hkl-white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--hkl-gray-050);
            color: var(--hkl-gray-900);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.5;
        }

        header {
            padding: 24px;
            background: var(--hkl-blue-900);
            color: var(--hkl-white);
        }

        header h1 {
            margin: 0 0 4px;
        }

        header p {
            margin: 0;
            opacity: 0.9;
        }

        main {
            max-width: 1180px;
            margin: 28px auto;
            padding: 0 20px 40px;
        }

        .card {
            margin-bottom: 18px;
            padding: 20px;
            background: var(--hkl-white);
            border: 1px solid var(--hkl-gray-300);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(23 32 51 / 5%);
        }

        .card h2,
        .card h3 {
            margin-top: 0;
        }

        .scan-form label {
            display: block;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .scan-form input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #aeb8c5;
            border-radius: 7px;
            font: inherit;
        }

        .scan-form button {
            margin-top: 14px;
            padding: 10px 18px;
            border: 0;
            border-radius: 7px;
            background: var(--hkl-blue-700);
            color: var(--hkl-white);
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .scan-form button:hover {
            filter: brightness(0.92);
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }

        .summary-card {
            padding: 17px;
            border-radius: 10px;
            background: var(--hkl-gray-100);
            border: 1px solid var(--hkl-gray-300);
        }

        .summary-card strong {
            display: block;
            margin-bottom: 6px;
            color: var(--hkl-gray-600);
            font-size: 0.87rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .summary-card .value {
            font-size: 1.45rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .summary-card.success {
            background: var(--hkl-green-100);
            border-color: #86cda5;
        }

        .summary-card.warning {
            background: var(--hkl-orange-100);
            border-color: #efbd84;
        }

        .summary-card.danger {
            background: var(--hkl-red-100);
            border-color: #ef9991;
        }

        .category-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }

        .category {
            padding: 14px;
            border: 1px solid var(--hkl-gray-300);
            border-radius: 9px;
            background: var(--hkl-gray-050);
        }

        .category-name {
            color: var(--hkl-gray-600);
            text-transform: capitalize;
        }

        .category-count {
            margin-top: 4px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid var(--hkl-gray-300);
            text-align: left;
            vertical-align: top;
        }

        th {
            background: var(--hkl-gray-100);
        }

        code {
            padding: 2px 5px;
            background: var(--hkl-gray-100);
            border-radius: 4px;
            overflow-wrap: anywhere;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
        }

        .message.error {
            background: var(--hkl-red-100);
            color: var(--hkl-red-700);
        }

        .message.warning {
            background: var(--hkl-orange-100);
            color: var(--hkl-orange-700);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--hkl-green-100);
            color: var(--hkl-green-700);
            font-size: 0.88rem;
            font-weight: 700;
        }

        .muted {
            color: var(--hkl-gray-600);
        }
    </style>
</head>

<body>
<header>
    <h1><?= e($config['name'] ?? '') ?></h1>

    <p>
        Versie <?= e($config['version'] ?? '') ?>
        — alleen-lezen forensische analyse
    </p>
</header>

<main>
    <section class="card">
        <h2>MediaWiki-scan</h2>

        <p class="muted">
            Selecteer de hoofdmap van een lokale kopie van de
            MediaWiki-installatie.
        </p>

        <form
            method="post"
            class="scan-form"
        >
            <label for="scan_path">
                Scanpad
            </label>

            <input
                id="scan_path"
                name="scan_path"
                type="text"
                value="<?= e($scanPath) ?>"
                required
            >

            <button type="submit">
                Start scan
            </button>
        </form>
    </section>

    <?php if ($scanError !== null): ?>
        <section class="card">
            <div class="message error">
                <strong>De scan kon niet worden uitgevoerd.</strong>

                <br>

                <?= e($scanError) ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (
        is_array($scanResult)
        && ($scanResult['success'] ?? false) === false
    ): ?>
        <section class="card">
            <div class="message warning">
                <strong>
                    Geen geldige MediaWiki-installatie gevonden.
                </strong>

                <br>

                <?= e($scanResult['message'] ?? '') ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (
        is_array($scanResult)
        && ($scanResult['success'] ?? false) === true
    ): ?>
        <section class="card">
            <h2>Scanoverzicht</h2>

            <span class="status-badge">
                MediaWiki herkend
            </span>

            <div class="summary-grid">
                <div class="summary-card">
                    <strong>Scan-ID</strong>

                    <div class="value">
                        <?= e($scanResult['scan_id'] ?? '') ?>
                    </div>
                </div>

                <div class="summary-card">
                    <strong>Platform</strong>

                    <div class="value">
                        <?= e($scanResult['platform'] ?? '') ?>
                    </div>
                </div>

                <div class="summary-card">
                    <strong>Versie</strong>

                    <div class="value">
                        <?= e(
                            $scanResult['version']
                            ?: 'Niet herkend'
                        ) ?>
                    </div>
                </div>

                <div class="summary-card">
                    <strong>Scantype</strong>

                    <div class="value">
                        <?= e($scanResult['scan_type'] ?? '') ?>
                    </div>
                </div>

                <div class="summary-card">
                    <strong>Totaal mappen</strong>

                    <div class="value">
                        <?= e($totalDirectoryCount) ?>
                    </div>
                </div>

                <div class="summary-card success">
                    <strong>Bekende mappen</strong>

                    <div class="value">
                        <?= e($knownDirectoryCount) ?>
                    </div>
                </div>

                <div class="summary-card <?= $unknownDirectoryCount > 0
                    ? 'warning'
                    : 'success' ?>"
                >
                    <strong>Onbekende mappen</strong>

                    <div class="value">
                        <?= e($unknownDirectoryCount) ?>
                    </div>
                </div>
            </div>

            <p>
                <strong>Rootpad:</strong>

                <code>
                    <?= e($scanResult['root_path'] ?? '') ?>
                </code>
            </p>

            <p>
                <strong>Gegenereerd:</strong>

                <?= e($scanResult['generated_at'] ?? '') ?>
            </p>
        </section>

        <section class="card">
            <h2>Classificatie</h2>

            <div class="category-grid">
                <?php foreach ($statistics as $category => $count): ?>
                    <div class="category">
                        <div class="category-name">
                            <?= e($category) ?>
                        </div>

                        <div class="category-count">
                            <?= e($count) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <h2>
                Onbekende mappen
                (<?= e($unknownDirectoryCount) ?>)
            </h2>

            <?php if ($unknownDirectories === []): ?>
                <p>
                    Alle mappen vallen onder een bekende
                    MediaWiki-categorie.
                </p>
            <?php else: ?>
                <p class="muted">
                    Deze mappen zijn nog niet automatisch geclassificeerd.
                    Dat betekent nog niet dat ze kwaadaardig zijn.
                </p>

                <table>
                    <thead>
                    <tr>
                        <th>Relatief pad</th>
                        <th>Reden</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($unknownDirectories as $directory): ?>
                        <tr>
                            <td>
                                <code>
                                    <?= e(
                                        $directory['relative_path']
                                        ?? ''
                                    ) ?>
                                </code>
                            </td>

                            <td>
                                <?= e($directory['reason'] ?? '') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>