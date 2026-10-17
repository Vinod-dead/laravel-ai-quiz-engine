<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE questions ALTER COLUMN embedding TYPE vector(768)'
        );

        DB::statement(
            'ALTER TABLE attempts ALTER COLUMN embedding TYPE vector(768)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE questions ALTER COLUMN embedding TYPE vector(1536)'
        );

        DB::statement(
            'ALTER TABLE attempts ALTER COLUMN embedding TYPE vector(1536)'
        );
    }
};