<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobs = Illuminate\Support\Facades\DB::table('failed_jobs')
    ->orderByDesc('id')
    ->limit(3)
    ->get(['id', 'payload', 'exception']);

foreach ($jobs as $job) {
    $payload = json_decode($job->payload);

    echo "\n--- Failed job ID: {$job->id} ---\n";
    echo "Job: " . ($payload->displayName ?? 'unknown') . "\n";
    echo substr($job->exception, 0, 2500) . "\n";
}
