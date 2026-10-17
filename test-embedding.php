<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$response = \Laravel\Ai\Embeddings::for([
    'Test embedding for a quiz question'
])->generate();

echo 'Embedding dimensions: ' . count($response->embeddings[0]) . PHP_EOL;
