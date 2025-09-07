<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlaylistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'playlist_id', 'item_type', 'item_id', 'position',
    ];
}

