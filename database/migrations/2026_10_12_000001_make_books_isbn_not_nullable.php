<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * isbn は応用フェーズで nullable にしていたが、データ要件（DR01）で nullable に変更するのは
     * published_date のみのため、アプリのバリデーション（BookRequest）とあわせて必須に戻す。
     * UNIQUE 制約はそのまま維持される。
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE books MODIFY isbn VARCHAR(13) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE books MODIFY isbn VARCHAR(13) NULL');
    }
};
