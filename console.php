<?php

declare(strict_types = 1);

use App\Commands\BuildComposerJson;
use App\Http\ProjectReleaseFetcher;
use App\Http\UpdateFetcher;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$httpClient = new CachingHttpClient(
    HttpClient::create(),
    new Store(getenv('DSA_CACHE_DIR') ?: '/tmp/symfony-cache')
);
$buildDir = getenv('DSA_BUILD_DIR') ?: __DIR__ . '/build';

if (!is_dir($buildDir)) {
    mkdir($buildDir, 0755, true);
}
$app = new Application();
$app->add(new BuildComposerJson(
    $buildDir,
    new ProjectReleaseFetcher($httpClient),
    new UpdateFetcher($httpClient)
));
$app->run();
