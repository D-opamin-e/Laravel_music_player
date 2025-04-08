<?php

namespace App\Http\Controllers; 

use Illuminate\Http\Request;
use App\Models\Favorite;
use App\Http\Controllers\Controller; 

class FavoriteController extends Controller
{
    public function toggle(Request $request)
    {
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        $index = $request->input('index');

        if (!$index) {
            return response()->json(['error' => 'Index is required'], 400);
        }

        $favorite = Favorite::where('ip_address', $ip)->where('song_index', $index)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['favorited' => false]);
        } else {
            Favorite::create([
                'ip_address' => $ip,
                'song_index' => $index,
            ]);
            return response()->json(['favorited' => true]);
        }
    }
}
