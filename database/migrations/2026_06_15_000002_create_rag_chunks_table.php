<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // FTS5 virtual table — not creatable via Blueprint, use raw SQL
        DB::statement('
            CREATE VIRTUAL TABLE IF NOT EXISTS rag_chunks
            USING fts5(
                source_file,
                chunk_index,
                content,
                tokenize = "porter ascii"
            )
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS rag_chunks');
    }
};
