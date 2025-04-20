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

class MusicController extends Controller
{
    protected $mappingService;

    // MappingService 인스턴스를 주입받아 사용합니다.
    public function __construct(MappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    // 메인 페이지 로드 및 초기 데이터 준비
    public function index(Request $request)
    {
        // 사용자의 IP 주소 및 플레이리스트 파일 경로를 설정합니다.
        $userIP = $request->header('X-Forwarded-For') ?? $request->ip();
        $userIPSanitized = str_replace(':', '_', $userIP);
        $userPlaylistFile = storage_path('app/playlist/' . $userIPSanitized . '.json');

        Log::info("MusicController@index: Current IP: {$userIP}");

        // 로그인 상태에 따라 사용자의 찜 목록을 가져옵니다.
        $favorites = [];
        if (Auth::check()) {
            // 로그인 사용자: user_favorites 테이블에서 해당 user_id의 song_index 목록 조회
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
            // 비로그인 사용자: favorites 테이블에서 해당 IP 주소의 song_index 목록 조회
            try {
                $favorites = Favorite::where('ip_address', $userIP)
                               ->pluck('song_index')
                               ->toArray();
                 Log::info("MusicController@index: Guest User IP: {$userIP} Favorites loaded: " . json_encode($favorites));
            } catch (\Exception $e) {
                 Log::error("MusicController@index: Error fetching guest favorites for IP {$userIP}: " . $e->getMessage());
            }
        }

        // 플레이리스트 파일이 존재하는 경우 삭제 (재생목록 갱신 로직의 일부일 수 있음)
        if (File::exists($userPlaylistFile)) {
            // File::delete($userPlaylistFile); // 주석 처리된 부분은 복원하지 않습니다.
        }

        // 전체 곡 수를 가져와 플레이리스트 생성 준비
        $total_songs = DB::table('songs')->count();
        if ($total_songs === 0) {
            abort(500, '곡 목록이 없습니다.');
        }

        // BPM 기반으로 임의의 플레이리스트를 생성합니다.
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

        // 채널 매핑 정보를 준비합니다.
        $mappedChannels = [];
        foreach ($song_list as $song) {
            $mappedChannels[$song->channel] = $this->mappingService->getMappedValue($song->channel);
        }

        // 프론트엔드 사용을 위한 플레이리스트 데이터 구조를 생성합니다.
        $shuffleplaylist = [];
        $playNum = 1;
        foreach ($song_list as $song) {
             $channelNormalized = $this->mappingService->normalizeQuery($song->channel);

            $shuffleplaylist[] = [
                'play_num' => $playNum++,
                'id' => $song->id,
                'title' => $song->title,
                'index' => $song->index_number, // 프론트에서 사용할 고유 ID
                'play_count' => $song->play_count,
                'bpm' => $song->BPM,
                'channel' => $channelNormalized,
                'videoID' => $song->videoID,
            ];
        }

        // 생성된 플레이리스트를 JSON 파일로 저장합니다 (IP 기반 파일명 사용)
        try {
             File::put($userPlaylistFile, json_encode($shuffleplaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
             Log::info("MusicController@index: Playlist saved to {$userPlaylistFile}");
         } catch (\Exception $e) {
             Log::error("MusicController@index: Error saving playlist file {$userPlaylistFile}: " . $e->getMessage());
         }

        // 뷰로 전달할 플레이리스트 데이터를 파일에서 다시 읽어옵니다.
        $playlist = [];
        if (File::exists($userPlaylistFile)) {
             try {
                $playlist = json_decode(File::get($userPlaylistFile));
             } catch (\Exception $e) {
                 Log::error("MusicController@index: Error reading playlist file {$userPlaylistFile}: " . $e->getMessage());
                 $playlist = [];
             }
        } else {
             Log::warning("MusicController@index: Playlist file not found: {$userPlaylistFile}");
        }

        // welcome 뷰에 필요한 데이터를 전달합니다.
        return view('welcome', [
            'playlist' => $playlist,
            'mappedChannels' => $mappedChannels,
            'favorites' => $favorites, // 로그인 상태에 맞는 찜 목록 ID 배열
            'favorited' => $favorites, // 동일 데이터 (프론트에서 사용한다면 필요)
        ]);
    }

    // 오디오 스트리밍 처리 (Range Request 지원)
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

        // Range 요청 헤더 처리 (오디오 탐색 기능 지원)
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

                // 부분 내용 응답 (206 Partial Content)
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

        // 전체 내용 응답 (200 OK)
        return response()->stream(function () use ($filePath) {
            readfile($filePath);
        }, 200, $headers);
    }

    // 외부 스크립트 실행을 통한 재생목록 업데이트
    public function updatePlaylist(Request $request)
    {
        $userIP = $request->ip();
        $filename = storage_path("app/playlist/{$userIP}.json");

        // Node.js 스크립트 실행
        $scriptPath = storage_path('../nodejs/now_playlist_update.js');
        $command = "node {$scriptPath} {$userIP}";
        $output = shell_exec($command);

        // 셸 실행 결과 로그 기록
        file_put_contents(
            storage_path('logs/shell_exec_log.txt'),
            "Command: $command\nOutput:\n$output\n---------------------\n",
            FILE_APPEND
        );

        // 스크립트 출력에서 JSON 파싱 (신규 추가 곡 정보 등)
        $jsonStart = strpos($output, '{"');
        $jsonEnd = strrpos($output, '}');
        $jsonString = $jsonStart !== false && $jsonEnd !== false
            ? substr($output, $jsonStart, $jsonEnd - $jsonStart + 1)
            : '';

        $resultArray = json_decode($jsonString, true);

        // 신규 추가된 곡 목록 응답
        if (isset($resultArray['log'])) {
            $logArray = $resultArray['log'];
            $outputString = '';
            foreach ($logArray as $log) {
                $outputString .= "신규 추가된 곡: $log\n";
            }
            if (!empty($outputString)) {
                return response($outputString)->header('Content-Type', 'text/plain; charset=utf-8');
            }
        }

        // BPM 업데이트 파이썬 스크립트 실행
        $pythonPath = 'E:\\Laravel\\music\\nodejs\\python.exe';
        $pythonScriptPath = storage_path('../nodejs/bpm.py');
        $bpmOutputRaw = shell_exec("\"{$pythonPath}\" \"{$pythonScriptPath}\" 2>&1");
        $bpmOutput = @iconv("EUC-KR", "UTF-8//IGNORE", $bpmOutputRaw);

        // BPM 스크립트 실행 결과 로그 기록
        file_put_contents(
            storage_path('logs/bpm_exec_log.txt'),
            "Executed BPM.py\nOutput:\n{$bpmOutput}\n---------------------\n",
            FILE_APPEND
        );

        // 기본 응답 (스크립트 첫 줄 메시지 또는 기본 메시지)
        $outputLines = explode("\n", $output);
        $firstLine = $outputLines[0] ?? '재생목록 업데이트 완료 (메시지 없음)';
        return response($firstLine)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    // 특정 곡의 재생 횟수 증가
    public function updatePlayCount(Request $request)
    {
        // 요청에서 곡의 고유 식별자(index)를 가져옵니다.
        $index = $request->input('index');

        // index_number 컬럼으로 Song 모델을 찾아 play_count를 1 증가시킵니다.
        $song = Song::where('index_number', $index)->first();

        if ($song) {
            $song->increment('play_count');
            Log::info("MusicController@updatePlayCount: Song index {$index} play count updated.");
            return response()->json(['message' => '재생 수 업데이트 완료']);
        }

        Log::warning("MusicController@updatePlayCount: Song with index {$index} not found.");
        return response()->json(['message' => '곡을 찾을 수 없습니다'], 404);
    }

    // 곡 검색 처리
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (empty(trim($query))) {
            // 검색어가 비어 있으면 전체 곡 목록 반환
            return response()->json(Song::all());
        }

        // MappingService를 사용하여 검색어 및 관련 별칭을 가져옵니다.
        $mapped = $this->mappingService->map($query);
        $reverseMapped = $this->mappingService->reverseMap($query);
        $aliases = $this->mappingService->getAliasesForValue($query);

        // 검색에 사용할 용어 목록을 구성합니다.
        $searchTerms = array_filter([
            $query,
            $mapped,
            $reverseMapped,
            ...$aliases
        ]);

        $searchTerms = array_unique($searchTerms);

        // 검색 용어를 사용하여 'channel' 또는 'title' 컬럼에서 곡을 검색합니다.
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

    // 곡 찜 상태 토글 (추가/삭제)
    public function toggleFavorite(Request $request)
    {
        // 요청에서 곡의 고유 식별자(index)를 가져옵니다.
        $songIndex = $request->input('index');

        if (is_null($songIndex)) {
            Log::warning("MusicController@toggleFavorite: Missing song index in request.");
            return response()->json(['success' => false, 'message' => '곡 정보가 누락되었습니다.'], 400);
        }

        $status = 'error';
        $message = '찜 상태 변경 중 오류 발생';

        // 사용자의 로그인 상태에 따라 찜 정보를 처리합니다.
        if (Auth::check()) {
            $userId = Auth::id();
            // 로그인 사용자: user_favorites 테이블에서 찜 상태 확인 및 토글
            $existingFavorite = DB::table('user_favorites')
                ->where('user_id', $userId)
                ->where('song_index', $songIndex)
                ->first();

            if ($existingFavorite) {
                // 찜 삭제
                DB::table('user_favorites')
                    ->where('user_id', $userId)
                    ->where('song_index', $songIndex)
                    ->delete();
                $message = '찜 목록에서 삭제되었습니다.';
                $status = 'removed';
                Log::info("MusicController@toggleFavorite: User ID {$userId} removed favorite for song index {$songIndex}.");
            } else {
                // 찜 추가
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
            // 비로그인 사용자: favorites 테이블에서 IP 기반으로 찜 상태 확인 및 토글
            $userIP = $request->ip();
            $existingFavorite = DB::table('favorites')
                ->where('ip_address', $userIP)
                ->where('song_index', $songIndex)
                ->first();

            if ($existingFavorite) {
                // 찜 삭제 (비로그인)
                DB::table('favorites')
                    ->where('ip_address', $userIP)
                    ->where('song_index', $songIndex)
                    ->delete();
                $message = '찜 목록에서 삭제되었습니다. (비로그인)';
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
                $message = '찜 목록에 추가되었습니다. (비로그인)';
                $status = 'added';
                Log::info("MusicController@toggleFavorite: Guest IP {$userIP} added favorite for song index {$songIndex}.");
            }
        }

        // 찜 상태 변경 결과 반환
        return response()->json(['success' => true, 'message' => $message, 'status' => $status]);
    }

    // 비로그인 사용자의 IP 기반 찜 목록 반환 (다른 용도로 사용될 수 있음)
    public function listFavorites(Request $request)
    {
        // Note: 이 메소드는 현재 IP 기반 찜 목록만 반환합니다.
        // 로그인 사용자의 찜 목록을 가져오려면 별도의 메소드를 만들거나 이 메소드에 로그인 분기 로직을 추가해야 합니다.
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        $favorites = Favorite::where('ip_address', $ip)->pluck('song_index')->toArray();
        Log::info("MusicController@listFavorites: IP {$ip} favorites requested.");
        return response()->json($favorites);
    }
}