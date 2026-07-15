<?php

declare(strict_types=1);

/** @var array<string,mixed> $inventory */

$statistics = $inventory['statistics'] ?? [];

$files = $inventory['files'] ?? [];

?>

<div class="card">

    <h2>Bestandsinventarisatie</h2>

    <div class="summary-grid">

        <?php foreach ($statistics as $name => $value): ?>

            <div class="summary-card">

                <strong><?= htmlspecialchars(ucfirst($name)) ?></strong>

                <div class="value">

                    <?= htmlspecialchars((string)$value) ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<div class="card">

    <h2>Eerste 25 bestanden</h2>

    <table>

        <thead>

        <tr>

            <th>Pad</th>
            <th>Categorie</th>
            <th>Type</th>
            <th>Grootte</th>

        </tr>

        </thead>

        <tbody>

        <?php foreach (array_slice($files,0,25) as $file): ?>

            <tr>

                <td>

                    <code>

                        <?= htmlspecialchars($file['path']) ?>

                    </code>

                </td>

                <td>

                    <?= htmlspecialchars($file['directory']['category']) ?>

                </td>

                <td>

                    <?= htmlspecialchars($file['file']['type']) ?>

                </td>

                <td>

                    <?= htmlspecialchars($file['metadata']['size_human']) ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>