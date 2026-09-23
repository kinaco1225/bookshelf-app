<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    // 応用フェーズの Blade（books/_form・books/show）が published_date を
    // ->format('Y-m-d') で扱う前提のため Carbon にキャストする。
    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * この書籍の登録者。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この書籍に付けられたジャンル（多対多）。
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * この書籍へのレビュー。
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍をお気に入り登録しているユーザー（多対多）。
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * タイトル・著者の部分一致でキーワード検索する。$keyword が空なら絞り込まない。
     */
    public function scopeSearchKeyword(Builder $query, ?string $keyword): Builder
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where(
            fn (Builder $query) => $query->where('title', 'like', "%{$keyword}%")
                ->orWhere('author', 'like', "%{$keyword}%")
        );
    }

    /**
     * 指定ジャンルに紐づく書籍のみに絞り込む。$genreId が空なら絞り込まない。
     */
    public function scopeInGenre(Builder $query, int|string|null $genreId): Builder
    {
        if (! $genreId) {
            return $query;
        }

        return $query->whereHas('genres', fn (Builder $query) => $query->where('genres.id', $genreId));
    }

    /**
     * 並び順を適用する（newest（デフォルト）/oldest/title/rating）。
     * rating を使う場合は事前に withAvg('reviews', 'rating') が必要。
     */
    public function scopeSortBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
