<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['questions', 'attempts'] as $table) {
    $result = DB::select("
        SELECT format_type(a.atttypid, a.atttypmod) AS column_type
        FROM pg_attribute a
        JOIN pg_class t ON t.oid = a.attrelid
        WHERE t.relname = ?
          AND a.attname = 'embedding'
          AND a.attnum > 0
          AND NOT a.attisdropped
    ", [$table]);

    echo $table . ': ' . ($result[0]->column_type ?? 'column not found') . PHP_EOL;
}
