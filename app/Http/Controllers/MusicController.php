<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Song;
use App\Models\Favorite;
use App\Models\UserFavorite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\MappingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Jobs\GenerateSquareThumbnail;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessBpmUpdate;

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
        $userIPSanitized = str_replace(':', '_', $userIP);
        $userPlaylistFile = storage_path('app/playlist/' . $userIPSanitized . '.json');

        Log::info("MusicController@index: Current IP: {$userIP}");

        $favorites = [];

        if (Auth::check()) {
            try {
                $favorites = DB::table('user_favorites')
                               ->where('user_id', Auth::id())
                               ->pluck('song_index')
                               ->toArray();
                Log::info("MusicController@index: Logged-in User ID: " . Auth::id() . " Favorites loaded: " . json_encode($favorites));
            } catch (\Exception $e) {
                Log::error("MusicController@index: Error fetching logged-in user favorites for ID " . Auth::id() . ": " . $e->getMessage());
            }
        } else {
            try {
                $favorites = Favorite::where('ip_address', $userIP)
                               ->pluck('song_index')
                               ->toArray();
                Log::info("MusicController@index: Guest User IP: {$userIP} Favorites loaded: " . json_encode($favorites));
            } catch (\Exception $e) {
                Log::error("MusicController@index: Error fetching guest favorites for IP {$userIP}: " . $e.getMessage());
            }
        }

        if (File::exists($userPlaylistFile)) {
        }

        $total_songs = DB::table('songs')->count();
        if ($total_songs === 0) {
            abort(500, '곡 목록이 없습니다.');
        }

        $random_offset = rand(0, $total_songs - 1);
        $first_song = DB::table('songs')->offset($random_offset)->limit(1)->first();

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
                    Log::warning("MusicController@index: Failed to build full playlist after {$max_attempts} attempts.");
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
             $videoID = $song->videoID;

             $squareThumbnailPath = "thumbnails/square/{$videoID}.jpg";

             if (Storage::disk('public')->exists($squareThumbnailPath)) {
                 $thumbnailUrl = Storage::url($squareThumbnailPath);
             } else {
                 $thumbnailUrl = "https://i.ytimg.com/vi/{$videoID}/maxresdefault.jpg";
             }


             $shuffleplaylist[] = [
                 'play_num' => $playNum++,
                 'id' => $song->id,
                 'title' => $song->title,
                 'index' => $song->index_number,
                 'play_count' => $song->play_count,
                 'bpm' => $song->BPM,
                 'channel' => $channelNormalized,
                 'videoID' => $videoID,
                 'thumbnail_url' => $thumbnailUrl,
             ];
        }


        try {
             File::put($userPlaylistFile, json_encode($shuffleplaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
             Log::info("MusicController@index: Playlist data saved to {$userPlaylistFile}");
           } catch (\Exception $e) {
             Log::error("MusicController@index: Error saving playlist file {$userPlaylistFile}: " . $e.getMessage());
           }

        $playlist = [];
        if (File::exists($userPlaylistFile)) {
             try {
                $playlist = json_decode(File::get($userPlaylistFile));
             } catch (\Exception $e) {
                 Log::error("MusicController@index: Error reading playlist file {$userPlaylistFile}: " + $e.getMessage());
                 $playlist = [];
             }
        } else {
             Log::warning("MusicController@index: Playlist file not found: {$userPlaylistFile}");
        }

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
                    while (!feof($fp) && (ftell($fp) + $bufferSize) <= ($end + 1)) {
                        if ((ftell($fp) + $bufferSize) > $end + 1) {
                             $bufferSize = ($end + 1) - ftell($fp);
                        }
                        if ($bufferSize <= 0) break;
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
        Log::info("updatePlaylist 메소드 시작 (Node.js 실행 및 BPM Job 디스패치)");
        $userIP = $request->ip();
        $scriptPath = storage_path('../nodejs/now_playlist_update.js');
        $command = "node {$scriptPath} {$userIP}";
        Log::info("Node.js 스크립트 실행 명령: {$command}");
        $output = shell_exec($command . ' 2>&1');
        Log::info("Node.js 스크립트 실행 완료.");
        Log::info("--- Node.js 스크립트 원본 출력 (shell_exec 캡처 내용) ---");
        Log::info($output);
        Log::info("--- 원본 출력 끝 ---");

        $resultArray = null;
        $jsonString = trim($output);
        $resultArray = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("Node.js 스크립트 출력 전체 JSON 파싱 실패: " . json_last_error_msg() . ". JSON 블록 추출 시도.");

            $jsonStart = strpos($output, '{');
            $jsonArrayStart = strpos($output, '[');
            if ($jsonStart === false && $jsonArrayStart === false) {
                Log::error("Node.js 스크립트 출력에서 JSON 시작 문자 ({ 또는 [)를 찾지 못했습니다.");
                $resultArray = null;
            } else {
                if ($jsonStart === false) $jsonStart = $jsonArrayStart;
                if ($jsonArrayStart === false) $jsonArrayStart = $jsonStart;
                $actualJsonStart = min($jsonStart, $jsonArrayStart);

                $jsonEnd = strrpos($output, '}');
                $jsonArrayEnd = strrpos($output, ']');
                if ($jsonEnd === false && $jsonArrayEnd === false) {
                    Log::error("Node.js 스크립트 출력에서 JSON 끝 문자 (} 또는 ])를 찾지 못했습니다.");
                    $resultArray = null;
                } else {
                    if ($jsonEnd === false) $jsonEnd = $jsonArrayEnd;
                    if ($jsonArrayEnd === false) $jsonArrayEnd = $jsonEnd;
                    $actualJsonEnd = max($jsonEnd, $jsonArrayEnd);
                    if ($actualJsonStart !== false && $actualJsonEnd !== false && $actualJsonEnd > $actualJsonStart) {
                        $jsonString = substr($output, $actualJsonStart, $actualJsonEnd - $actualJsonStart + 1);
                        Log::info("JSON 블록 추출 성공:\n" . $jsonString);
                        $resultArray = json_decode($jsonString, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Log::error("추출된 JSON 블록 파싱 실패: " . json_last_error_msg());
                            $resultArray = null;
                        } else {
                            Log::info("추출된 JSON 블록 파싱 성공.");
                        }
                    } else {
                        Log::error("JSON 시작/끝 위치 관계가 올바르지 않습니다. 추출 실패.");
                        $resultArray = null;
                    }
                }
            }
        } else {
            Log::info("Node.js 스크립트 출력 전체 JSON 파싱 성공 (trim 후).");
        }


        Log::info("썸네일 생성 Job 디스패치 로직 시작");
        $processedVideoIDs = [];
        if ($resultArray !== null && isset($resultArray['log']) && is_array($resultArray['log'])) {
            $videoIdsFromScript = $resultArray['log'];
            Log::info("스크립트 결과 videoID 목록 (썸네일 Job 대상): " . json_encode($videoIdsFromScript));
             if (!empty($videoIdsFromScript)) {
                 $songsToProcess = Song::whereIn('videoID', $videoIdsFromScript)->get();
                 if ($songsToProcess->isEmpty()) {
                        Log::warning("스크립트 결과 videoID로 DB에서 일치하는 곡을 찾지 못했습니다. 썸네일 Job을 디스패치하지 않습니다.");
                 } else {
                        Log::info("찾은 곡들에 대해 썸네일 Job 디스패치를 시도합니다.");
                 }
                 foreach ($songsToProcess as $song) {
                     $videoID = $song->videoID;
                     $squareThumbnailFileName = "thumbnails/square/{$videoID}.jpg";
                     if (!Storage::disk('public')->exists($squareThumbnailFileName)) {
                         Log::info("썸네일 파일이 없습니다. Job 디스패치 예정: {$squareThumbnailFileName} (videoID: {$videoID})");
                         GenerateSquareThumbnail::dispatch($song);
                         $processedVideoIDs[] = $videoID;
                         Log::info("썸네일 생성 Job 디스패치됨 (videoID: {$videoID}, 제목: {$song->title})");
                     }
                 }
             } else {
                 Log::info("스크립트 결과 'log' 배열이 비어 있습니다. 썸네일 Job 디스패치 대상 없음.");
             }
        } else {
             Log::warning("스크립트 결과에 예상된 'log' 배열이 없거나 배열 형식이 아닙니다 (파싱 실패 또는 'log' 키 없음). 썸네일 Job 디스패치 로직 건너tdm.");
        }
        Log::info("썸네일 생성 Job 디스패치 로직 종료. 이번 실행에서 디스패치된 Job 수: " . count($processedVideoIDs));

        Log::info("BPM 업데이트 Job 디스패치 시도");
        ProcessBpmUpdate::dispatch();
        Log::info("BPM 업데이트 Job 디스패치 완료");

        $responseMessage = '재생목록 관련 백그라운드 작업들이 큐에 추가되었습니다.';
        if ($resultArray !== null && isset($resultArray['message']) && is_string($resultArray['message'])) {
             $responseMessage = 'Node.js 결과: ' . $resultArray['message'] . ' | ' . $responseMessage;
        } elseif ($resultArray !== null && isset($resultArray['status']) && is_string($resultArray['status'])) {
             $responseMessage = 'Node.js 결과: ' . $resultArray['status'] . ' | ' . $responseMessage;
        } else {
             $outputLines = explode("\n", trim($output));
             $responseMessage = 'Node.js RAW 출력: ' . ($outputLines[0] ?? '출력 없음') . ' | ' . $responseMessage;
             Log::warning("Node.js JSON 파싱 실패로 RAW 출력 첫 줄 및 Job 디스패치 메시지 사용.");
        }


        Log::info("updatePlaylist 메소드 종료");
        return response($responseMessage)->header('Content-Type', 'text/plain; charset=utf-8');
    }


    public function updatePlayCount(Request $request)
    {
        $index = $request->input('index');

        $song = Song::where('index_number', $index)->first();

        if ($song) {
            $song->increment('play_count');
            Log::info("MusicController@updatePlayCount: Song index {$index} play count updated.");
            return response()->json(['message' => '재생 수 업데이트 완료']);
        }

        Log::warning("MusicController@updatePlayCount: Song with index {$index} not found.");
        return response()->json(['message' => '곡을 찾을 수 없습니다'], 404);
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

        Log::info("MusicController@search: Search query '{$query}' resulted in " . $songs->count() . " songs.");

        return response()->json($songs);
    }

    public function toggleFavorite(Request $request)
    {
        $songIndex = $request->input('index');

        if (is_null($songIndex)) {
            Log::warning("MusicController@toggleFavorite: Missing song index in request.");
            return response()->json(['success' => false, 'message' => '곡 정보가 누락되었습니다.'], 400);
        }

        $status = 'error';
        $message = '찜 상태 변경 중 오류 발생';

        if (Auth::check()) {
            $userId = Auth::id();
            $existingFavorite = DB::table('user_favorites')
                ->where('user_id', $userId)
                ->where('song_index', $songIndex)
                ->first();

            if ($existingFavorite) {
                DB::table('user_favorites')
                    ->where('user_id', $userId)
                    ->where('song_index', $songIndex)
                    ->delete();
                $message = '찜 목록에서 삭제되었습니다.';
                $status = 'removed';
                Log::info("MusicController@toggleFavorite: User ID {$userId} removed favorite for song index {$songIndex}.");
            } else {
                DB::table('user_favorites')->insert([
                    'user_id' => $userId,
                    'song_index' => $songIndex,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $message = '찜 목록에 추가되었습니다.';
                $status = 'added';
                Log::info("MusicController@toggleFavorite: User ID {$userId} added favorite for song index {$songIndex}.");
            }
        } else {
            $userIP = $request->ip();
            $existingFavorite = DB::table('favorites')
                ->where('ip_address', $userIP)
                ->where('song_index', $songIndex)
                ->first();

            if ($existingFavorite) {
                DB::table('favorites')
                    ->where('ip_address', $userIP)
                    ->where('song_index', $songIndex)
                    ->delete();
                $message = '찜 목록에서 삭제되었습니다. (비로그인)';
                $status = 'removed';
                Log::info("MusicController@toggleFavorite: Guest IP {$userIP} removed favorite for song index {$songIndex}.");
            } else {
                DB::table('favorites')->insert([
                    'ip_address' => $userIP,
                    'song_index' => $songIndex,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $message = '찜 목록에 추가되었습니다. (비로그인)';
                $status = 'added';
                Log::info("MusicController@toggleFavorite: Guest IP {$userIP} added favorite for song index {$songIndex}.");
            }
        }

        return response()->json(['success' => true, 'message' => $message, 'status' => $status]);
    }

    public function listFavorites(Request $request)
    {
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        $favorites = Favorite::where('ip_address', $ip)->pluck('song_index')->toArray();
        Log::info("MusicController@listFavorites: IP {$ip} favorites requested.");
        return response()->json($favorites);
    }
}