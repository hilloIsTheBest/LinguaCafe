<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

// request classes
use App\Http\Requests\Books\GetBookWordCountsRequest;
use App\Http\Requests\Books\CreateBookRequest;
use App\Http\Requests\Books\UpdateBookRequest;
use App\Http\Requests\Books\DeleteBookRequest;

// services
use App\Services\BookService;

class BookController extends Controller {
    private $bookService;

    public function __construct(BookService $bookService) {
        $this->bookService = $bookService;
    }

    public function getBooks() {
        $userId = Auth::user()->id;
        $language = Auth::user()->selected_language;
        
        $books = $this->bookService->getBooks($userId, $language);

        return response()->json($books, 200);
    }

    public function getBookWordCounts($bookId, GetBookWordCountsRequest $request) {
        $userId = Auth::user()->id;

        try {
            $wordCounts = $this->bookService->getBookWordCounts($userId, $bookId);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json($wordCounts, 200);
    }

    public function getBookDetails($bookId)
    {
        $userId = Auth::user()->id;
        $book = \App\Models\Book::where('user_id', $userId)->where('id', $bookId)->first();
        if (!$book) {
            abort(404, 'Book not found.');
        }
        // Normalize tags to array
        $tags = [];
        if (!empty($book->tags)) {
            $decoded = json_decode($book->tags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $tags = $decoded;
            }
        }
        return response()->json([
            'id' => $book->id,
            'name' => $book->name,
            'cover_image' => $book->cover_image,
            'language' => $book->language,
            'is_public' => (bool) $book->is_public,
            'tags' => $tags,
        ], 200);
    }

    public function createBook(CreateBookRequest $request) {
        $userId = Auth::user()->id;
        $language = Auth::user()->selected_language;
        $bookName = $request->post('bookName');
        $bookCoverFile = $request->file('bookCover');
        $isPublic = (bool) $request->post('isPublic', false);
        $tags = $request->post('tags');
        if (is_string($tags)) {
            // Allow JSON string or comma-separated list
            $decoded = json_decode($tags, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $tags = $decoded;
            } else {
                $tags = array_values(array_filter(array_map('trim', explode(',', $tags)), fn($s) => $s !== ''));
            }
        }
        
        try {
            $this->bookService->createBook($userId, $language, $bookName, $bookCoverFile, $isPublic, $tags ?? []);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }
        
        return response()->json('Book has been successfully created.', 200);
    }

    public function updateBook(UpdateBookRequest $request) {
        $userId = Auth::user()->id;
        $bookId = $request->post('bookId');
        $bookName = $request->post('bookName');
        $bookCoverFile = $request->file('bookCover');
        $isPublic = (bool) $request->post('isPublic', false);
        $tags = $request->post('tags');
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $tags = $decoded;
            } else {
                $tags = array_values(array_filter(array_map('trim', explode(',', $tags)), fn($s) => $s !== ''));
            }
        }
        
        try {
            $this->bookService->updateBook($userId, $bookId, $bookName, $bookCoverFile, $isPublic, $tags ?? []);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }
        
        return response()->json('Book has been successfully updated.', 200);
    }

    public function deleteBook(DeleteBookRequest $request) {
        $bookId = $request->post('bookId');
        $userId = Auth::user()->id;

        try {
            $this->bookService->deleteBook($userId, $bookId);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }
        
        return response()->json('Book has been successfully deleted.', 200);
    }
}
