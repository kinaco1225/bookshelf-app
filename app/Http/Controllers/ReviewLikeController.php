<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * レビューへの「いいね」をトグルする（追加⇔解除）。
     */
    public function toggle(Request $request, Review $review): RedirectResponse
    {
        $request->user()->likedReviews()->toggle($review->id);

        return back();
    }
}
