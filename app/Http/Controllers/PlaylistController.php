<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function listPlaylists()
    {
        $userId = Auth::id();
        $pls = Playlist::where('user_id', $userId)->orderBy('updated_at', 'desc')->get();
        return response()->json($pls, 200);
    }

    public function createPlaylist(Request $request)
    {
        $userId = Auth::id();
        $name = trim((string) $request->post('name'));
        $isPublic = (bool) $request->post('isPublic', false);
        if ($name === '') {
            abort(422, 'Playlist name is required.');
        }
        $pl = Playlist::create(['user_id' => $userId, 'name' => $name, 'is_public' => $isPublic]);
        return response()->json($pl, 200);
    }

    public function deletePlaylist($playlistId)
    {
        $userId = Auth::id();
        $pl = Playlist::where('user_id', $userId)->where('id', $playlistId)->first();
        if (!$pl) abort(404);
        PlaylistItem::where('user_id', $userId)->where('playlist_id', $playlistId)->delete();
        $pl->delete();
        return response()->json('Deleted', 200);
    }

    public function listItems($playlistId)
    {
        $userId = Auth::id();
        $items = PlaylistItem::where('user_id', $userId)->where('playlist_id', $playlistId)->orderBy('position')->get();
        return response()->json($items, 200);
    }

    public function addItem(Request $request)
    {
        $userId = Auth::id();
        $playlistId = (int) $request->post('playlistId');
        $itemType = (string) $request->post('itemType'); // 'book' or 'chapter'
        $itemId = (int) $request->post('itemId');
        $pos = $request->post('position');

        $pl = Playlist::where('user_id', $userId)->where('id', $playlistId)->first();
        if (!$pl) abort(404, 'Playlist not found.');
        if (!in_array($itemType, ['book','chapter'], true)) abort(422, 'Invalid item type.');

        if ($pos === null) {
            $max = (int) PlaylistItem::where('user_id', $userId)->where('playlist_id', $playlistId)->max('position');
            $pos = $max + 1;
        } else {
            $pos = (int) $pos;
        }

        $rec = PlaylistItem::create([
            'user_id' => $userId,
            'playlist_id' => $playlistId,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'position' => $pos,
        ]);

        return response()->json($rec, 200);
    }

    public function removeItem(Request $request)
    {
        $userId = Auth::id();
        $playlistItemId = (int) $request->post('playlistItemId');
        $item = PlaylistItem::where('user_id', $userId)->where('id', $playlistItemId)->first();
        if (!$item) abort(404);
        $item->delete();
        return response()->json('Deleted', 200);
    }

    public function moveItem(Request $request)
    {
        $userId = Auth::id();
        $playlistItemId = (int) $request->post('playlistItemId');
        $direction = (string) $request->post('direction'); // 'up' | 'down'
        $item = PlaylistItem::where('user_id', $userId)->where('id', $playlistItemId)->first();
        if (!$item) abort(404);
        $op = $direction === 'up' ? '<' : '>';
        $sort = $direction === 'up' ? 'desc' : 'asc';
        $neighbor = PlaylistItem::where('user_id', $userId)
            ->where('playlist_id', $item->playlist_id)
            ->where('position', $op, $item->position)
            ->orderBy('position', $sort)
            ->first();
        if (!$neighbor) return response()->json('noop', 200);
        $tmp = $item->position;
        $item->position = $neighbor->position;
        $neighbor->position = $tmp;
        $item->save();
        $neighbor->save();
        return response()->json('ok', 200);
    }
}

