<?php
declare(strict_types=1);

use HKL\Forensics\Core\Application;
use HKL\Forensics\Modules\MediaWiki\MediaWikiScanner;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('Voer eerst composer install uit.');
}

require $autoload;

$config = require dirname(__DIR__) . '/config/app.php';
$app = new Application($config);
$app->registerModule(new MediaWikiScanner());

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($config['name']) ?></title>
<style>
body{font-family:Arial,sans-serif;background:#f5f7fb;color:#1f2937;margin:0}
header{background:#243b53;color:white;padding:24px}
main{max-width:960px;margin:28px auto;padding:0 18px}
.card{background:white;border:1px solid #d8dee8;border-radius:10px;padding:18px;margin-bottom:16px}
.badge{display:inline-block;background:#dbeafe;color:#1e40af;border-radius:999px;padding:4px 10px}
</style>
</head>
<body>
<header>
<h1><?= htmlspecialchars($config['name']) ?></h1>
<div>Versie <?= htmlspecialchars($config['version']) ?> — alleen-lezen</div>
</header>
<main>
<div class="card">
<h2>Commit 0001 gereed</h2>
<p>Projectstructuur, autoloading, module-loader en eerste MediaWiki-module zijn aanwezig.</p>
</div>
<?php foreach ($app->modules() as $module): ?>
<div class="card">
<h2><?= htmlspecialchars($module->name()) ?></h2>
<span class="badge"><?= htmlspecialchars($module->platform()) ?></span>
<p>In Commit 0002 volgt scanpad-invoer, structuurherkenning en bestandsclassificatie.</p>
</div>
<?php endforeach; ?>
</main>
</body>
</html>
