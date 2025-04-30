<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Song; // Song 모델 사용
use App\Models\Favorite; // 비로그인 찜 모델
use App\Models\UserFavorite; // 로그인 찜 모델
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\MappingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // 로그 사용
use App\Jobs\GenerateSquareThumbnail; // 우리가 만든 썸네일 생성 Job 사용
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
                               Log::info("MusicController@index: Guest User IP: {$userIP} Favorites loaded: " . json_encode($favorites)); // '+' 연산자 대신 '.' 사용 권장
            } catch (\Exception $e) {
                 Log::error("MusicController@index: Error fetching guest favorites for IP {$userIP}: " . $e.getMessage()); // '+' 연산자 대신 '.' 사용 권장
            }
        }

        if (File::exists($userPlaylistFile)) {
            // File::delete($userPlaylistFile); // 필요하다면 기존 파일 삭제
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
        $song_list = [$first_song]; // 첫 곡으로 시작

        $max_bpm_diff = 10; // 초기 BPM 허용 범위
        $added_songs = 1;
        $failed_attempts = 0;
        $max_attempts = 50; // 무한 루프 방지

        // BPM 기반으로 다음 곡들을 찾아 플레이리스트에 추가
        while ($added_songs < $total_songs) {
            $next_song = DB::table('songs')
                ->whereNotIn('index_number', $exclude_ids) // 이미 추가된 곡 제외
                ->where('BPM', '!=', $current_bpm) // 이전 곡과 BPM이 다른 곡 선호 (믹싱 느낌)
                ->whereRaw('ABS(BPM - ?) <= ?', [$current_bpm, $max_bpm_diff]) // 현재 BPM과 일정 범위 내의 곡
                ->inRandomOrder() // 무작위 순서
                ->first(); // 하나만 가져옴

            if ($next_song) {
                $song_list[] = $next_song; // 플레이리스트에 추가
                $exclude_ids[] = $next_song->index_number; // 제외 목록에 추가
                $current_bpm = $next_song->BPM; // 현재 BPM 업데이트
                $added_songs++; // 추가된 곡 수 증가
                $failed_attempts = 0; // 실패 시도 횟수 초기화
            } else {
                // 조건에 맞는 다음 곡을 찾지 못하면 BPM 허용 범위 확대
                $max_bpm_diff += 5;
                $failed_attempts++;
                if ($failed_attempts >= $max_attempts) {
                    Log::warning("MusicController@index: Failed to build full playlist after {$max_attempts} attempts.");
                    break; // 최대 시도 횟수 도달 시 중단
                }
            }
        }

        // 채널 이름 매핑 (기존 로직)
        $mappedChannels = [];
        foreach ($song_list as $song) {
            $mappedChannels[$song->channel] = $this->mappingService->getMappedValue($song->channel);
        }


        // 플레이리스트 데이터 구조 생성 및 **썸네일 URL 포함 (수정된 부분)**
         $shuffleplaylist = [];
         $playNum = 1;
         foreach ($song_list as $song) {
              $channelNormalized = $this->mappingService->normalizeQuery($song->channel);
              $videoID = $song->videoID;

              // --- 썸네일 URL 결정 로직 (수정 시작) ---
              // Public storage disk에 저장된 정사각형 썸네일 파일 경로
              $squareThumbnailPath = "thumbnails/square/{$videoID}.jpg"; // storage/app/public/thumbnails/square/videoID.jpg

              // 1. 정사각형 썸네일 파일이 실제로 존재하는지 확인
              if (Storage::disk('public')->exists($squareThumbnailPath)) {
                  // 2. 파일이 존재하면, 해당 파일의 Public URL을 생성
                  $thumbnailUrl = Storage::url($squareThumbnailPath); // 예: /storage/thumbnails/square/videoID.jpg
                //   Log::info("index 메소드: Generating Public URL for existing square thumbnail: {$thumbnailUrl} (videoID: {$videoID})");
              } else {
                  // 3. 파일이 존재하지 않으면, 원본 유튜브 썸네일 URL 또는 기본 이미지 URL을 사용
                  // 아직 썸네일이 생성되지 않았거나 생성에 실패한 경우입니다.
                  $thumbnailUrl = "https://i.ytimg.com/vi/{$videoID}/maxresdefault.jpg"; // 원본 유튜브 URL
                  // 또는 $thumbnailUrl = asset('images/default_square_thumbnail.png'); // 기본 이미지
                //   Log::warning("index 메소드: Square thumbnail not found for videoID: {$videoID}. Using fallback URL: {$thumbnailUrl}");
              }
              // --- 썸네일 URL 결정 로직 (수정 끝) ---


             $shuffleplaylist[] = [
                 'play_num' => $playNum++,
                 'id' => $song->id, // 데이터베이스 ID
                 'title' => $song->title, // 곡 제목
                 'index' => $song->index_number, // 곡 인덱스 번호 (DB에 있다면)
                 'play_count' => $song->play_count, // 재생 횟수
                 'bpm' => $song->BPM, // BPM
                 'channel' => $channelNormalized, // 채널 (매핑된 이름)
                 'videoID' => $videoID, // YouTube 영상 ID
                 'thumbnail_url' => $thumbnailUrl, 
             ];
         }


        // 생성된 플레이리스트 데이터를 JSON 파일로 저장 (기존 로직)
        try {
             // 'php artisan storage:link'가 실행되어야 public/storage가 접근 가능
             File::put($userPlaylistFile, json_encode($shuffleplaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
             Log::info("MusicController@index: Playlist data saved to {$userPlaylistFile}");
         } catch (\Exception $e) {
             Log::error("MusicController@index: Error saving playlist file {$userPlaylistFile}: " . $e.getMessage()); // '+' 연산자 대신 '.' 사용 권장
         }

        // 저장된 플레이리스트 JSON 파일 읽어오기 (프론트엔드에 전달)
        $playlist = [];
        if (File::exists($userPlaylistFile)) {
             try {
                $playlist = json_decode(File::get($userPlaylistFile));
             } catch (\Exception $e) {
                 Log::error("MusicController@index: Error reading playlist file {$userPlaylistFile}: " + $e.getMessage()); // '+' 연산자 대신 '.' 사용 권장
                 $playlist = [];
             }
        } else {
             Log::warning("MusicController@index: Playlist file not found: {$userPlaylistFile}");
        }

        // welcome 뷰로 데이터 전달 (기존 로직)
        return view('welcome', [
            'playlist' => $playlist, // 프론트엔드로 전달되는 플레이리스트 데이터
            'mappedChannels' => $mappedChannels,
            'favorites' => $favorites,
            'favorited' => $favorites, 
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

    public function updatePlaylist(Request $request)
    {
        Log::info("updatePlaylist 메소드 시작 (Node.js 실행 및 BPM Job 디스패치)");
        $userIP = $request->ip();
        $scriptPath = storage_path('../nodejs/now_playlist_update.js');
        $command = "node {$scriptPath} {$userIP}";
        Log::info("Node.js 스크립트 실행 명령: {$command}");
        $output = shell_exec($command . ' 2>&1'); // 오류 포함 출력 캡처
        Log::info("Node.js 스크립트 실행 완료.");
        Log::info("--- Node.js 스크립트 원본 출력 (shell_exec 캡처 내용) ---");
        Log::info($output); // 캡처된 출력 로깅
        Log::info("--- 원본 출력 끝 ---");

        $resultArray = null;
        $jsonString = trim($output);
        $resultArray = json_decode($jsonString, true);

         // JSON 파싱 및 추출 로직 (기존 코드 유지)
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("Node.js 스크립트 출력 전체 JSON 파싱 실패: " . json_last_error_msg() . ". JSON 블록 추출 시도.");

            $jsonStart = strpos($output, '{');
            $jsonArrayStart = strpos($output, '[');
            if ($jsonStart === false && $jsonArrayStart === false) {
                // JSON 시작 문자를 찾지 못함
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
                            // Log::info("Parsed resultArray (extracted): " . json_encode($resultArray)); // 파싱 결과 확인 (필요시 활성화)
                        }
                    } else {
                        Log::error("JSON 시작/끝 위치 관계가 올바르지 않습니다. 추출 실패.");
                        $resultArray = null;
                    }
                }
            }
        } else {
            // trim된 출력 전체 파싱 성공 시
            Log::info("Node.js 스크립트 출력 전체 JSON 파싱 성공 (trim 후).");
        }
         // --- Node.js 스크립트 실행 부분 끝 ---

        // --- 썸네일 생성 Job 디스패치 로직 (유지) ---
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
                          GenerateSquareThumbnail::dispatch($song); // 썸네일 Job 디스패치
                          $processedVideoIDs[] = $videoID;
                          Log::info("썸네일 생성 Job 디스패치됨 (videoID: {$videoID}, 제목: {$song->title})");
                      } // else { Log::info("썸네일 파일이 이미 존재합니다. 건너뜀 (videoID: {$videoID})"); }
                  }
              } else {
                  Log::info("스크립트 결과 'log' 배열이 비어 있습니다. 썸네일 Job 디스패치 대상 없음.");
              }
         } else {
             Log::warning("스크립트 결과에 예상된 'log' 배열이 없거나 배열 형식이 아닙니다 (파싱 실패 또는 'log' 키 없음). 썸네일 Job 디스패치 로직 건너tdm.");
         }
         Log::info("썸네일 생성 Job 디스패치 로직 종료. 이번 실행에서 디스패치된 Job 수: " . count($processedVideoIDs));
        // --- 썸네일 생성 Job 디스패치 로직 끝 ---

        // --- BPM 업데이트 Job 디스패치 (새로 추가) ---
        Log::info("BPM 업데이트 Job 디스패치 시도");
        ProcessBpmUpdate::dispatch(); // BPM 업데이트 Job을 큐에 추가
        Log::info("BPM 업데이트 Job 디스패치 완료");
        // --- BPM 업데이트 Job 디스패치 끝 ---

        // --- 응답 메시지 부분 (수정) ---
        // BPM 계산 결과는 이제 비동기적으로 처리되므로 즉시 응답 메시지를 반환
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


    // 특정 곡의 재생 횟수 증가
    public function updatePlayCount(Request $request)
    {
        $index = $request->input('index');

        // index_number 컬럼으로 Song 모델을 찾아 play_count를 1 증가
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

        // MappingService를 사용하여 검색어 및 관련 별칭을 가져옴옴
        $mapped = $this->mappingService->map($query);
        $reverseMapped = $this->mappingService->reverseMap($query);
        $aliases = $this->mappingService->getAliasesForValue($query);

        // 검색에 사용할 용어 목록을 구성
        $searchTerms = array_filter([
            $query,
            $mapped,
            $reverseMapped,
            ...$aliases
        ]);

        $searchTerms = array_unique($searchTerms);

        // 검색 용어를 사용하여 'channel' 또는 'title' 컬럼에서 곡을 검색
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
        // 요청에서 곡의 고유 식별자(index)를 가져옴
        $songIndex = $request->input('index');

        if (is_null($songIndex)) {
            Log::warning("MusicController@toggleFavorite: Missing song index in request.");
            return response()->json(['success' => false, 'message' => '곡 정보가 누락되었습니다.'], 400);
        }

        $status = 'error';
        $message = '찜 상태 변경 중 오류 발생';

        // 사용자의 로그인 상태에 따라 찜 정보를 처리
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
        $ip = $request->header('X-Forwarded-For') ?? $request->ip();
        $favorites = Favorite::where('ip_address', $ip)->pluck('song_index')->toArray();
        Log::info("MusicController@listFavorites: IP {$ip} favorites requested.");
        return response()->json($favorites);
    }
}