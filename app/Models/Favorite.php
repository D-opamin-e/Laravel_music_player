<?php

// app/Models/Favorite.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $fillable = ['ip_address', 'song_index'];
}
