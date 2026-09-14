<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author');
            // 応用フェーズ: ISBN検索での自動入力に失敗した場合でも登録できるよう nullable。
            // MySQL の UNIQUE 制約は NULL 同士を重複とみなさないため、複数書籍で isbn が
            // NULL でも問題ない。
            $table->string('isbn', 13)->nullable()->unique();
            // 応用フェーズ: Google Books API が出版日を返さない場合があるため nullable。
            $table->date('published_date')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
