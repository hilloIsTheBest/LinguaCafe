<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PublicLibraryController extends Controller
{
    public function listPublicBooks()
    {
        // Authenticated users can browse public library
        if (!Auth::check()) abort(401);

        $books = Book::select(['id','user_id','name','cover_image','language','tags','updated_at'])
            ->where('is_public', true)
            ->orderBy('updated_at','desc')
            ->limit(500)
            ->get();

        $userIds = $books->pluck('user_id')->unique()->values();
        $users = User::whereIn('id', $userIds)->get(['id','name'])->keyBy('id');

        $result = $books->map(function($b) use ($users) {
            $tags = [];
            if (!empty($b->tags)) {
                $decoded = json_decode($b->tags, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $tags = $decoded;
            }
            return [
                'id' => $b->id,
                'name' => $b->name,
                'cover_image' => $b->cover_image,
                'language' => $b->language,
                'tags' => $tags,
                'author' => optional($users->get($b->user_id))->name,
                'updated_at' => $b->updated_at,
            ];
        });

        return response()->json($result, 200);
    }
}

