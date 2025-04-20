<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Song;
use App\Models\Favorite; // 비로그인 찜
use App\Models\UserFavorite; // 로그인 찜
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\MappingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Jobs\GenerateSquareThumbnail;
use Illuminate\Support\Facades\Storage;

class MusicController extends Controller
{
    protected $mappingService;

    public function __construct(MappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * 메인 페이지 로드 및 사용자별 플레이리스트 생성/로드
     */
    public function index(Request $request)
    {
        $userIP = $request->header('X-Forwarded-For') ?? $request->ip();
        $userIPSanitized = str_replace(':', '_', $userIP);
        $userPlaylistFile = storage_path('app/playlist/' . $userIPSanitized . '.json');

        Log::info("MusicController@index: Access from IP: {$userIP}");

        $favorites = [];

        // 로그인 상태에 따라 찜 목록 로드
        if (Auth::check()) {
            try {
                $favorites = DB::table('user_favorites')
                                ->where('user_id', Auth::id())
                                ->pluck('song_index')
                                ->toArray();
                 Log::info("MusicController@index: Logged-in User ID: " . Auth::id() . " Favorites loaded: " . count($favorites));
            } catch (\Exception $e) {
                 Log::error("MusicController@index: Error fetching logged-in user favorites for ID " . Auth::id() . ": " . $e->getMessage());
            }
        } else {
            try {
                $favorites = Favorite::where('ip_address', $userIP)
                                ->pluck('song_index')
                                ->toArray();
                 Log::info("MusicController@index: Guest User IP: {$userIP} Favorites loaded: " . count($favorites));
            } catch (\Exception $e) {
                 Log::error("MusicController@index: Error fetching guest favorites for IP {$userIP}: " . $e->getMessage());
            }
        }

        // 필요시 기존 플레이리스트 파일 삭제 (주석 처리됨)
        // if (File::exists($userPlaylistFile)) {
        //     File::delete($userPlaylistFile);
        // }

        $total_songs = DB::table('songs')->count();
        if ($total_songs === 0) {
            abort(500, '곡 목록이 없습니다.');
        }

        // --- BPM 기반 플레이리스트 생성 로직 ---
        $random_offset = rand(0, $total_songs - 1);
        $first_song = DB::table('songs')->offset($random_offset)->limit(1)->first();

        if (!$first_song) {
            abort(500, '첫 곡을 가져오지 못했습니다.');
        }

        $current_bpm = $first_song->BPM;
        $exclude_ids = [$first_song->index_number];
        $song_list = [$first_song];

        $max_bpm_diff = 10; // 초기 BPM 허용 범위
        $added_songs = 1;
        $failed_attempts = 0;
        $max_attempts = 50; // 무한 루프 방지용 최대 시도 횟수

        while ($added_songs < $total_songs) {
            $next_song = DB::table('songs')
                ->whereNotIn('index_number', $exclude_ids) // 이미 추가된 곡 제외
                ->where('BPM', '!=', $current_bpm) // 이전 곡과 BPM이 다른 곡 선호 (믹싱 효과)
                ->whereRaw('ABS(BPM - ?) <= ?', [$current_bpm, $max_bpm_diff]) // 현재 BPM과 일정 범위 내
                ->inRandomOrder()
                ->first();

            if ($next_song) {
                $song_list[] = $next_song;
                $exclude_ids[] = $next_song->index_number;
                $current_bpm = $next_song->BPM;
                $added_songs++;
                $failed_attempts = 0; // 성공 시 실패 횟수 초기화
            } else {
                // 조건에 맞는 곡 못 찾으면 BPM 허용 범위 넓힘
                $max_bpm_diff += 5;
                $failed_attempts++;
                if ($failed_attempts >= $max_attempts) {
                    Log::warning("MusicController@index: Failed to build full playlist after {$max_attempts} attempts. Found {$added_songs}/{$total_songs} songs.");
                    break; // 최대 시도 도달 시 중단
                }
            }
        }
        // --- 플레이리스트 생성 로직 끝 ---

        // 채널 이름 매핑
        $mappedChannels = [];
        foreach ($song_list as $song) {
            $mappedChannels[$song->channel] = $this->mappingService->getMappedValue($song->channel);
        }

        // 플레이리스트 데이터 구조 생성 (썸네일 URL 포함)
        $shuffleplaylist = [];
        $playNum = 1;
        foreach ($song_list as $song) {
            $channelNormalized = $this->mappingService->normalizeQuery($song->channel);
            $videoID = $song->videoID;

            // --- 썸네일 URL 결정 로직 ---
            // Public storage disk에 저장된 정사각형 썸네일 파일 경로
            $squareThumbnailPath = "thumbnails/square/{$videoID}.jpg";

            // 1. 정사각형 썸네일 파일 존재 확인
            if (Storage::disk('public')->exists($squareThumbnailPath)) {
                // 2. 존재하면 Public URL 사용
                $thumbnailUrl = Storage::url($squareThumbnailPath);
                // Log::info("index: Found square thumbnail: {$thumbnailUrl} (videoID: {$videoID})"); // 필요 시 로그 활성화
            } else {
                // 3. 없으면 원본 유튜브 썸네일 URL 사용 (또는 기본 이미지)
                $thumbnailUrl = "https://i.ytimg.com/vi/{$videoID}/maxresdefault.jpg";
                // $thumbnailUrl = asset('images/default_square_thumbnail.png'); // 기본 이미지 사용 시
                Log::warning("index: Square thumbnail not found for videoID: {$videoID}. Using fallback: {$thumbnailUrl}");
            }
            // --- 썸네일 URL 결정 로직 끝 ---

            $shuffleplaylist[] = [
                'play_num' => $playNum++,
                'id' => $song->id, // DB id
                'title' => $song->title,
                'index' => $song->index_number, // 곡 고유 번호
                'play_count' => $song->play_count,
                'bpm' => $song->BPM,
                'channel' => $channelNormalized,
                'videoID' => $videoID,
                'thumbnail_url' => $thumbnailUrl, // 최종 결정된 썸네일 URL
            ];
        }

        // 생성된 플레이리스트 JSON 파일로 저장
        try {
            // 주의: 'php artisan storage:link' 필요
            File::put($userPlaylistFile, json_encode($shuffleplaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Log::info("MusicController@index: Playlist data saved to {$userPlaylistFile}");
        } catch (\Exception $e) {
            Log::error("MusicController@index: Error saving playlist file {$userPlaylistFile}: " . $e->getMessage());
        }

        // 저장된 플레이리스트 JSON 파일 읽기
        $playlist = [];
        if (File::exists($userPlaylistFile)) {
             try {
                 $playlist = json_decode(File::get($userPlaylistFile));
             } catch (\Exception $e) {
                  Log::error("MusicController@index: Error reading playlist file {$userPlaylistFile}: " . $e->getMessage());
                  $playlist = []; // 오류 시 빈 배열
             }
        } else {
             Log::warning("MusicController@index: Playlist file not found: {$userPlaylistFile}");
        }

        // 뷰로 데이터 전달
        return view('welcome', [
            'playlist' => $playlist,
            'mappedChannels' => $mappedChannels,
            'favorites' => $favorites, // 찜 목록 배열 (song_index)
            // 'favorited' => $favorites, // 'favorites' 와 동일하므로 하나만 사용해도 됨
        ]);
    }

    /**
     * 오디오 파일 스트리밍 (Range Request 지원)
     */
    public function stream(Request $request, $filename)
    {
        $filePath = public_path("music/{$filename}");

        if (!file_exists($filePath)) {
            abort(404, '파일을 찾을 수 없습니다.');
        }

        $fileSize = filesize($filePath);
        $mimeType = 'audio/mpeg'; // MP3 가정

        $start = 0;
        $end = $fileSize - 1;

        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $fileSize,
        ];

        // Range 헤더 처리 (탐색 지원)
        if ($request->headers->has('Range')) {
            $range = $request->header('Range');

            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                // 끝 값이 없으면 파일 끝까지
                $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;

                if ($start > $end || $end >= $fileSize) {
                    return response('Requested range not satisfiable', 416);
                }

                $length = $end - $start + 1;

                $headers['Content-Range'] = "bytes $start-$end/$fileSize";
                $headers['Content-Length'] = $length;

                // 206 Partial Content 응답
                return response()->stream(function () use ($filePath, $start, $end) {
                    $fp = fopen($filePath, 'rb');
                    fseek($fp, $start);

                    $bufferSize = 8192; // 8KB 버퍼
                    $bytesToSend = $end - $start + 1;

                    while ($bytesToSend > 0 && !feof($fp)) {
                        $readSize = min($bufferSize, $bytesToSend);
                        echo fread($fp, $readSize);
                        $bytesToSend -= $readSize;
                        flush(); // 버퍼 비우기
                    }
                    fclose($fp);
                }, 206, $headers);
            }
        }

        // Range 헤더 없을 시 전체 파일 응답 (200 OK)
        return response()->stream(function () use ($filePath) {
            readfile($filePath);
        }, 200, $headers);
    }

   /**
    * 외부 스크립트(Node.js, Python)를 실행하여 플레이리스트 관련 데이터를 업데이트하고,
    * 필요한 썸네일 생성 Job을 디스패치합니다.
    */
   public function updatePlaylist(Request $request)
   {
       Log::info("updatePlaylist: Start (Executing Node.js/Python and dispatching Thumbnail Jobs)");
       $userIP = $request->ip();

       // --- 1. Node.js 스크립트 실행 및 JSON 파싱 ---
       $scriptPath = storage_path('../nodejs/now_playlist_update.js');
       $command = "node {$scriptPath} {$userIP}";
       Log::info("Executing Node.js script: {$command}");
       $output = shell_exec($command . ' 2>&1'); // stderr 포함 출력 캡처
       Log::info("Node.js script finished.");
       Log::info("--- Node.js Raw Output ---");
       Log::info($output);
       Log::info("--- End Node.js Raw Output ---");

       $resultArray = null;
       $jsonString = trim($output);
       $resultArray = json_decode($jsonString, true);

       if (json_last_error() !== JSON_ERROR_NONE) {
           Log::warning("Failed to parse full Node.js output as JSON: " . json_last_error_msg() . ". Attempting to extract JSON block.");
           // JSON 블록 추출 시도 (로그 메시지 등이 섞여 있을 경우 대비)
           $jsonStart = strpos($output, '{');
           $jsonArrayStart = strpos($output, '[');
           $actualJsonStart = false;
           if ($jsonStart !== false && $jsonArrayStart !== false) $actualJsonStart = min($jsonStart, $jsonArrayStart);
           elseif ($jsonStart !== false) $actualJsonStart = $jsonStart;
           elseif ($jsonArrayStart !== false) $actualJsonStart = $jsonArrayStart;

           $jsonEnd = strrpos($output, '}');
           $jsonArrayEnd = strrpos($output, ']');
           $actualJsonEnd = false;
           if ($jsonEnd !== false && $jsonArrayEnd !== false) $actualJsonEnd = max($jsonEnd, $jsonArrayEnd);
           elseif ($jsonEnd !== false) $actualJsonEnd = $jsonEnd;
           elseif ($jsonArrayEnd !== false) $actualJsonEnd = $jsonArrayEnd;


           if ($actualJsonStart !== false && $actualJsonEnd !== false && $actualJsonEnd > $actualJsonStart) {
               $jsonString = substr($output, $actualJsonStart, $actualJsonEnd - $actualJsonStart + 1);
               Log::info("Extracted JSON block:\n" . $jsonString);
               $resultArray = json_decode($jsonString, true);
               if (json_last_error() !== JSON_ERROR_NONE) {
                   Log::error("Failed to parse extracted JSON block: " . json_last_error_msg());
                   $resultArray = null; // 최종 파싱 실패
               } else {
                   Log::info("Successfully parsed extracted JSON block.");
               }
           } else {
               Log::error("Could not find valid JSON start/end markers in Node.js output.");
               $resultArray = null; // 최종 파싱 실패
           }
       } else {
            Log::info("Successfully parsed trimmed Node.js output as JSON.");
       }
       // --- Node.js 처리 끝 ---

       // --- 2. 썸네일 생성 Job 디스패치 ---
       Log::info("Dispatching Thumbnail Generation Jobs...");
       $processedVideoIDs = []; // 이번 실행에서 Job 디스패치한 videoID 목록

       // Node.js 결과에서 videoID 목록 가져오기 (결과 구조에 따라 키 이름 확인 필요, 'log'로 가정)
       if ($resultArray !== null && isset($resultArray['log']) && is_array($resultArray['log'])) {
           $videoIdsFromScript = $resultArray['log'];
           Log::info("Found 'log' array in script result. Count: " . count($videoIdsFromScript));
           // Log::info("VideoIDs from script: " . json_encode($videoIdsFromScript)); // 필요 시 활성화

           if (!empty($videoIdsFromScript)) {
               // DB에서 해당 videoID를 가진 곡들 조회
               $songsToProcess = Song::whereIn('videoID', $videoIdsFromScript)->get(['id', 'videoID', 'title']); // 필요한 컬럼만 선택
               Log::info("Found " . $songsToProcess->count() . " songs in DB matching script videoIDs.");

               // 각 곡에 대해 썸네일 존재 여부 확인 후 Job 디스패치
               foreach ($songsToProcess as $song) {
                   $videoID = $song->videoID;
                   $squareThumbnailFileName = "thumbnails/square/{$videoID}.jpg"; // 저장될 파일 경로

                   // 썸네일 파일이 아직 없으면 Job 디스패치
                   if (!Storage::disk('public')->exists($squareThumbnailFileName)) {
                       Log::info("Dispatching GenerateSquareThumbnail job for videoID: {$videoID} (Title: {$song->title})");
                       GenerateSquareThumbnail::dispatch($song);
                       $processedVideoIDs[] = $videoID;
                   } else {
                       // Log::info("Thumbnail already exists for videoID: {$videoID}. Skipping job dispatch."); // 필요 시 활성화
                   }
               }
           } else {
               Log::info("Script 'log' array is empty. No thumbnail jobs to dispatch.");
           }
       } else {
           Log::warning("Expected 'log' array not found or not an array in script result. Skipping thumbnail job dispatch.");
       }
       Log::info("Finished dispatching thumbnail jobs. Dispatched count: " . count($processedVideoIDs));
       // --- 썸네일 처리 끝 ---


       // --- 3. BPM 업데이트 파이썬 스크립트 실행 ---
       $pythonPath = 'E:\\Laravel\\music\\nodejs\\python.exe'; // 파이썬 실행 파일 경로 (환경에 맞게 설정)
       $pythonScriptPath = storage_path('../nodejs/bpm.py');
       $bpmCommand = "\"{$pythonPath}\" \"{$pythonScriptPath}\"";
       Log::info("Executing BPM update Python script: {$bpmCommand}");
       $bpmOutputRaw = shell_exec($bpmCommand . ' 2>&1');
       Log::info("BPM update Python script finished.");

       // 파이썬 스크립트 출력 인코딩 처리 (EUC-KR -> UTF-8)
       $bpmOutput = @iconv("EUC-KR", "UTF-8//IGNORE", $bpmOutputRaw);

       // BPM 스크립트 실행 결과 로그 파일에 저장 (append 모드)
       file_put_contents(
           storage_path('logs/bpm_exec_log.txt'),
           "[" . now()->toDateTimeString() . "] Executed BPM.py\nOutput:\n{$bpmOutput}\n---------------------\n",
           FILE_APPEND
       );
       // --- BPM 처리 끝 ---

       // --- 4. 응답 반환 ---
       // Node.js 결과 JSON에서 메시지 추출 시도
       $responseMessage = '재생목록 업데이트 완료 (메시지 없음)'; // 기본값
       if ($resultArray !== null) {
            if (isset($resultArray['message']) && is_string($resultArray['message'])) {
                $responseMessage = $resultArray['message'];
            } elseif (isset($resultArray['status']) && is_string($resultArray['status'])) {
                $responseMessage = $resultArray['status']; // message 없으면 status 사용
            }
       } else {
           // JSON 파싱 실패 시, 원본 출력의 첫 줄 사용 (주의: 안전하지 않을 수 있음)
           $outputLines = explode("\n", trim($output));
           $responseMessage = $outputLines[0] ?? '재생목록 업데이트 완료 (RAW 메시지 사용)';
           Log::warning("Using raw first line as response message due to JSON parse failure: " . $responseMessage);
       }

       Log::info("Final response message: {$responseMessage}");
       Log::info("updatePlaylist: End");

       // 추출/결정된 메시지를 text/plain으로 반환
       return response($responseMessage)->header('Content-Type', 'text/plain; charset=utf-8');
       // --- 응답 반환 끝 ---
   }


    /**
     * 특정 곡의 재생 횟수를 1 증가시킵니다.
     */
    public function updatePlayCount(Request $request)
    {
        $index = $request->input('index'); // 곡의 index_number

        if (is_null($index)) {
             Log::warning("MusicController@updatePlayCount: Missing song index.");
             return response()->json(['message' => '곡 정보가 누락되었습니다.'], 400);
        }

        $song = Song::where('index_number', $index)->first();

        if ($song) {
            $song->increment('play_count');
            Log::info("MusicController@updatePlayCount: Song index {$index} play count updated to {$song->play_count}.");
            return response()->json(['message' => '재생 수 업데이트 완료']);
        }

        Log::warning("MusicController@updatePlayCount: Song with index {$index} not found.");
        return response()->json(['message' => '곡을 찾을 수 없습니다'], 404);
    }

    /**
     * 곡 검색 (제목 또는 채널명 기준)
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (empty(trim($query))) {
            // 검색어 없으면 빈 결과 반환 (또는 전체 목록 - 정책에 따라 결정)
            // return response()->json(Song::all()); // 전체 반환 시
            Log::info("MusicController@search: Empty search query.");
            return response()->json([]); // 빈 결과 반환
        }

        // 검색어 및 관련 매핑/별칭 가져오기
        $mapped = $this->mappingService->map($query);
        $reverseMapped = $this->mappingService->reverseMap($query);
        $aliases = $this->mappingService->getAliasesForValue($query);

        // 검색에 사용할 모든 용어 목록 생성 (중복 제거)
        $searchTerms = array_filter([
            $query,
            $mapped,
            $reverseMapped,
            ...$aliases // 배열 펼치기
        ]);
        $searchTerms = array_unique($searchTerms);

        Log::info("MusicController@search: Searching for terms: " . implode(', ', $searchTerms));

        // title 또는 channel 컬럼에서 검색 용어와 부분 일치하는 곡 검색
        $songs = Song::where(function($q) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $q->orWhere('channel', 'like', "%$term%")
                  ->orWhere('title', 'like', "%$term%");
            }
        })->get();

        Log::info("MusicController@search: Query '{$query}' resulted in " . $songs->count() . " songs.");
        return response()->json($songs);
    }

    /**
     * 곡 찜 상태 토글 (로그인/비로그인 사용자 구분 처리)
     */
    public function toggleFavorite(Request $request)
    {
        $songIndex = $request->input('index'); // 곡의 index_number

        if (is_null($songIndex)) {
            Log::warning("MusicController@toggleFavorite: Missing song index.");
            return response()->json(['success' => false, 'message' => '곡 정보가 누락되었습니다.'], 400);
        }

        $status = 'error';
        $message = '찜 상태 변경 중 오류 발생';

        try {
            if (Auth::check()) {
                // --- 로그인 사용자 처리 ---
                $userId = Auth::id();
                $existingFavorite = DB::table('user_favorites')
                    ->where('user_id', $userId)
                    ->where('song_index', $songIndex)
                    ->first();

                if ($existingFavorite) {
                    // 찜 제거 (로그인)
                    DB::table('user_favorites')
                        ->where('user_id', $userId)
                        ->where('song_index', $songIndex)
                        ->delete();
                    $message = '찜 목록에서 삭제되었습니다.';
                    $status = 'removed';
                    Log::info("MusicController@toggleFavorite: User ID {$userId} removed favorite for song index {$songIndex}.");
                } else {
                    // 찜 추가 (로그인)
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
                // --- 비로그인 사용자 처리 ---
                $userIP = $request->ip();
                $existingFavorite = DB::table('favorites')
                    ->where('ip_address', $userIP)
                    ->where('song_index', $songIndex)
                    ->first();

                if ($existingFavorite) {
                    // 찜 제거 (비로그인)
                    DB::table('favorites')
                        ->where('ip_address', $userIP)
                        ->where('song_index', $songIndex)
                        ->delete();
                    $message = '찜 목록에서 삭제되었습니다.';
                    $status = 'removed';
                    Log::info("MusicController@toggleFavorite: Guest IP {$userIP} removed favorite for song index {$songIndex}.");
                } else {
                    // 찜 추가 (비로그인)
                    DB::table('favorites')->insert([
                        'ip_address' => $userIP,
                        'song_index' => $songIndex,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $message = '찜 목록에 추가되었습니다.';
                    $status = 'added';
                    Log::info("MusicController@toggleFavorite: Guest IP {$userIP} added favorite for song index {$songIndex}.");
                }
            }
            return response()->json(['success' => true, 'message' => $message, 'status' => $status]);

        } catch (\Exception $e) {
            Log::error("MusicController@toggleFavorite: Error toggling favorite for index {$songIndex}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => '찜 처리 중 오류가 발생했습니다.'], 500);
        }
    }

    /**
     * 비로그인 사용자의 IP 기반 찜 목록 반환 (참고용)
     * Note: 이 메소드는 현재 IP 기반 찜 목록만 반환합니다.
     * 로그인 사용자의 찜 목록은 index() 메소드에서 처리됩니다.
     */
    public function listFavorites(Request $request)
    {
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        try {
            $favorites = Favorite::where('ip_address', $ip)->pluck('song_index')->toArray();
            Log::info("MusicController@listFavorites: Guest IP {$ip} favorites requested, found " . count($favorites));
            return response()->json($favorites);
        } catch (\Exception $e) {
             Log::error("MusicController@listFavorites: Error fetching guest favorites for IP {$ip}: " . $e->getMessage());
             return response()->json([], 500); // 오류 시 빈 배열과 500 상태 코드 반환
        }
    }
}