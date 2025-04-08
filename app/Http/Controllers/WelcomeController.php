<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Favorite;
use App\Models\Song;
use App\Services\MappingService;

class WelcomeController extends Controller
{
    protected $mappingService;

    public function __construct(MappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    public function index(Request $request)
    {
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        $favorites = Favorite::where('ip_address', $ip)->pluck('song_index')->toArray();
        $songs = Song::all(); // 노래 목록 가져오기
        \Log::info('클라이언트 IP: ' . $ip);
        \Log::info('즐겨찾기 목록: ' . json_encode($favorites));

        // 채널 이름 매핑
        $mappedChannels = [];
        foreach ($songs as $song) {
            $mappedChannels[$song->channel] = $this->mappingService->getMappedValue($song->channel);
        }

        return view('welcome', [
            'favorites' => $favorites,
            'favorited' => $favorites,
            'playlist' => $songs,
            'mappedChannels' => $mappedChannels,
        ]);
    }
}
