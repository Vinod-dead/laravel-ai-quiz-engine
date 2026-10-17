<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['questions', 'attempts'] as $table) {
    $result = DB::select("
        SELECT
            COUNT(*) AS total_rows,
            COUNT(embedding) AS rows_with_embeddings
        FROM {$table}
    ");

    echo $table . ': ';
    echo 'total rows=' . $result[0]->total_rows;
    echo ', rows with embeddings=' . $result[0]->rows_with_embeddings;
    echo PHP_EOL;
}
