<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * このジャンルに紐づく書籍（多対多）。
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
