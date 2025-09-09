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

    public function copyPublicBook($bookId)
    {
        if (!Auth::check()) abort(401);

        $src = \App\Models\Book::where('id', $bookId)->where('is_public', true)->first();
        if (!$src) abort(404, 'Book not found or not public.');

        $user = Auth::user();
        $dst = new \App\Models\Book();
        $dst->user_id = $user->id;
        $dst->name = $src->name;
        $dst->language = $src->language;
        $dst->cover_image = null; // will fill below
        $dst->is_public = false;
        $dst->tags = $src->tags; // copy tags
        $dst->save();

        // Duplicate cover image if exists
        if (!empty($src->cover_image)) {
            $timestamp = str_replace([' ', ':'], '_', now()->toDateTimeString());
            $newName = $dst->id . '_' . $timestamp . '.' . pathinfo($src->cover_image, PATHINFO_EXTENSION);
            try {
                \Illuminate\Support\Facades\Storage::copy('/images/book_images/' . $src->cover_image, '/images/book_images/' . $newName);
                $dst->cover_image = $newName;
                $dst->save();
            } catch (\Throwable $e) {}
        }

        // Duplicate chapters as unprocessed
        $chapters = \App\Models\Chapter::where('user_id', $src->user_id)->where('book_id', $src->id)->get();
        foreach ($chapters as $c) {
            $nc = new \App\Models\Chapter();
            $nc->user_id = $user->id;
            $nc->book_id = $dst->id;
            $nc->name = $c->name;
            $nc->read_count = 0;
            $nc->word_count = 0;
            $nc->language = $src->language;
            $nc->raw_text = $c->raw_text;
            $nc->processing_status = \App\Enums\ChapterProcessingStatusEnum::UNPROCESSED->value;
            $nc->save();
        }

        return response()->json(['bookId' => $dst->id], 200);
    }
}
