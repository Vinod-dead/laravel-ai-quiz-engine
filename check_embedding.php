
<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = Illuminate\Support\Facades\DB::select("
    SELECT c.relname AS table_name,
           format_type(a.atttypid, a.atttypmod) AS data_type
    FROM pg_attribute a
    JOIN pg_class c ON c.oid = a.attrelid
    JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public'
      AND c.relname IN ('questions', 'attempts')
      AND a.attname = 'embedding'
      AND NOT a.attisdropped
");

foreach ($rows as $row) {
    echo $row->table_name . ': ' . $row->data_type . PHP_EOL;
}
