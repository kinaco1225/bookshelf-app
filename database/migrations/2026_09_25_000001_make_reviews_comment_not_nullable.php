<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * comment は当初 nullable だったが、コーチフィードバックによりアプリのバリデーション
     * （ReviewRequest）側は既に必須化済み。DBの列定義も一致させ、必須であることを保証する。
     */
    public function up(): void
    {
        DB::statement("UPDATE reviews SET comment = '' WHERE comment IS NULL");
        DB::statement('ALTER TABLE reviews MODIFY comment TEXT NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE reviews MODIFY comment TEXT NULL');
    }
};
