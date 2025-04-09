<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Song;
use App\Models\Favorite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\MappingService;

class MusicController extends Controller
{
    protected $mappingService;

    public function __construct(MappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    public function index(Request $request)
    {
        $userIP = $request->header('X-Forwarded-For') ?? $request->ip();
        $favorites = Favorite::where('ip_address', $userIP)->pluck('song_index')->toArray();
        $userIPSanitized = str_replace(':', '_', $userIP);
        $userPlaylistFile = storage_path('app/playlist/' . $userIPSanitized . '.json');


        logger("Current IP: $userIP");
        logger("Favorites loaded: " . json_encode($favorites));


        if (File::exists($userPlaylistFile)) {
            File::delete($userPlaylistFile);
        }

        $total_songs = DB::table('songs')->count();
        if ($total_songs === 0) {
            abort(500, '곡 목록이 없습니다.');
        }

        $random_offset = rand(0, $total_songs - 1);
        $first_song = DB::table('songs')
            ->offset($random_offset)
            ->limit(1)
            ->first();

        if (!$first_song) {
            abort(500, '첫 곡을 가져오지 못했습니다.');
        }

        $current_bpm = $first_song->BPM;
        $exclude_ids = [$first_song->index_number];
        $song_list = [$first_song];

        $max_bpm_diff = 10;
        $added_songs = 1;
        $failed_attempts = 0;
        $max_attempts = 50;

        while ($added_songs < $total_songs) {
            $next_song = DB::table('songs')
                ->whereNotIn('index_number', $exclude_ids)
                ->where('BPM', '!=', $current_bpm)
                ->whereRaw('ABS(BPM - ?) <= ?', [$current_bpm, $max_bpm_diff])
                ->inRandomOrder()
                ->first();

            if ($next_song) {
                $song_list[] = $next_song;
                $exclude_ids[] = $next_song->index_number;
                $current_bpm = $next_song->BPM;
                $added_songs++;
                $failed_attempts = 0;
            } else {
                $max_bpm_diff += 5;
                $failed_attempts++;
                if ($failed_attempts >= $max_attempts) {
                    break;
                }
            }
        }

        $mappedChannels = [];
        foreach ($song_list as $song) {
            $mappedChannels[$song->channel] = $this->mappingService->getMappedValue($song->channel);
        }

        $shuffleplaylist = [];
        $playNum = 1;
        foreach ($song_list as $song) {
            $channelNormalized = $this->mappingService->normalizeQuery($song->channel);

            $shuffleplaylist[] = [
                'play_num' => $playNum++,
                'id' => $song->id,
                'title' => $song->title,
                'index' => $song->index_number,
                'play_count' => $song->play_count,
                'bpm' => $song->BPM,
                'channel' => $channelNormalized,
                'videoID' => $song->videoID,
            ];
        }

        File::put($userPlaylistFile, json_encode($shuffleplaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $playlist = json_decode(File::get($userPlaylistFile));

        return view('welcome', [
            'playlist' => $playlist,
            'mappedChannels' => $mappedChannels,
            'favorites' => $favorites,
            'favorited' => $favorites,
        ]);
    }

    public function stream(Request $request, $filename)
    {
        $filePath = public_path("music/{$filename}");

        if (!file_exists($filePath)) {
            abort(404, '파일을 찾을 수 없습니다.');
        }

        $fileSize = filesize($filePath);
        $mimeType = 'audio/mpeg';

        $start = 0;
        $end = $fileSize - 1;
        $length = $fileSize;

        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $fileSize,
        ];

        if ($request->headers->has('Range')) {
            $range = $request->header('Range');

            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;

                if ($start > $end || $end >= $fileSize) {
                    return response('Requested range not satisfiable', 416);
                }

                $length = $end - $start + 1;

                $headers['Content-Range'] = "bytes $start-$end/$fileSize";
                $headers['Content-Length'] = $length;

                return response()->stream(function () use ($filePath, $start, $end) {
                    $fp = fopen($filePath, 'rb');
                    fseek($fp, $start);

                    $bufferSize = 8192;
                    while (!feof($fp) && ftell($fp) <= $end) {
                        if ((ftell($fp) + $bufferSize) > $end) {
                            $bufferSize = $end - ftell($fp) + 1;
                        }
                        echo fread($fp, $bufferSize);
                        flush();
                    }
                    fclose($fp);
                }, 206, $headers);
            }
        }

        return response()->stream(function () use ($filePath) {
            readfile($filePath);
        }, 200, $headers);
    }

    public function updatePlaylist(Request $request)
    {
        $userIP = $request->ip();
        $filename = storage_path("app/playlist/{$userIP}.json");

        $scriptPath = base_path('now_playlist_update.js');
        $command = "node {$scriptPath} {$userIP}";
        $output = shell_exec($command);

        file_put_contents(storage_path('logs/shell_exec_log.txt'), "Command: $command\nOutput: $output\n", FILE_APPEND);

        $jsonStart = strpos($output, '{"');
        $jsonEnd = strrpos($output, '}');
        $jsonString = $jsonStart !== false && $jsonEnd !== false
            ? substr($output, $jsonStart, $jsonEnd - $jsonStart + 1)
            : '';

        $resultArray = json_decode($jsonString, true);

        $time = now()->format('m\uc6d4 d\uc77c H\uc2dc i\ubd84 s\ucd08');
        $outputLines = explode("\n", $output);
        $firstLine = $outputLines[0] ?? 'No output';

        if (isset($resultArray['log'])) {
            $logArray = $resultArray['log'];
            $outputString = '';

            foreach ($logArray as $log) {
                $outputString .= "\uc2e0\uaddc \ucd94\uac00\ub41c \uace1: $log\n";
            }

            if (!empty($outputString)) {
                return response($outputString)->header('Content-Type', 'text/plain; charset=utf-8');
            }
        }

        return response("$time / $firstLine")->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function updatePlayCount(Request $request)
    {
        $index = $request->input('index');

        $song = Song::where('index_number', $index)->first();

        if ($song) {
            $song->increment('play_count');
            return response()->json(['message' => 'MusicController.php 재생 수 업데이트 완료']);
        }

        return response()->json(['message' => 'MusicController.php 곡을 찾을 수 없습니다'], 404);
    }

    public function search(Request $request)
    {
        $query = $request->input('q');

        if (empty(trim($query))) {
            return response()->json(Song::all());
        }

        $mapped = $this->mappingService->map($query);
        $reverseMapped = $this->mappingService->reverseMap($query);
        $aliases = $this->mappingService->getAliasesForValue($query);

        $searchTerms = array_filter([
            $query,
            $mapped,
            $reverseMapped,
            ...$aliases
        ]);

        $searchTerms = array_unique($searchTerms);

        $songs = Song::where(function($q) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $q->orWhere('channel', 'like', "%$term%")
                  ->orWhere('title', 'like', "%$term%")
                  ;
            }
        })->get();

        return response()->json($songs);
    }

    public function toggleFavorite(Request $request)
    {
        $index = $request->input('index');
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();

        $favorite = Favorite::where('ip_address', $ip)->where('song_index', $index)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['status' => 'removed']);
        } else {
            Favorite::create([
                'ip_address' => $ip,
                'song_index' => $index,
            ]);
            return response()->json(['status' => 'added']);
        }
    }

    public function listFavorites(Request $request)
    {
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        $favorites = Favorite::where('ip_address', $ip)->pluck('song_index')->toArray();
        return response()->json($favorites);
    }
}
