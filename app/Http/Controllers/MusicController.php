<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MusicController extends Controller
{
    public function index(Request $request)
    {
        $userIP = $request->ip();
        $userIPSanitized = str_replace(':', '_', $userIP);
        $userPlaylistFile = storage_path('app/playlist/' . $userIPSanitized . '.json');

        // ✅ 항상 새 재생목록 생성: 기존 파일 삭제
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

        $shuffleplaylist = [];
        $playNum = 1;
        foreach ($song_list as $song) {
            $shuffleplaylist[] = [
                'play_num' => $playNum++,
                'id' => $song->id,
                'title' => $song->title,
                'index' => $song->index_number,
                'play_count' => $song->play_count,
                'bpm' => $song->BPM,
                'channel' => $song->channel,
            ];
        }

        File::put($userPlaylistFile, json_encode($shuffleplaylist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $playlist = json_decode(File::get($userPlaylistFile));

        return view('welcome', ['playlist' => $playlist]);
    }

    // ✅ 오디오 스트리밍 with Range 지원
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
                }, 206, $headers); // Partial Content
            }
        }

        // Range 헤더가 없을 경우 전체 파일 제공
        return response()->stream(function () use ($filePath) {
            readfile($filePath);
        }, 200, $headers);
    }
    public function updatePlaylist(Request $request)
    {
        $userIP = $request->ip(); // 사용자 IP 가져오기
        $filename = storage_path("app/playlist/{$userIP}.json");
    
        // Node.js 스크립트 실행
        $scriptPath = base_path('now_playlist_update.js'); // 또는 'node_scripts/now_playlist_update.js'
        $command = "node {$scriptPath} {$userIP}";
        $output = shell_exec($command);
    
        // 로그 저장
        file_put_contents(storage_path('logs/shell_exec_log.txt'), "Command: $command\nOutput: $output\n", FILE_APPEND);
    
        // 출력에서 JSON 추출
        $jsonStart = strpos($output, '{"');
        $jsonEnd = strrpos($output, '}');
        $jsonString = $jsonStart !== false && $jsonEnd !== false
            ? substr($output, $jsonStart, $jsonEnd - $jsonStart + 1)
            : '';
    
        $resultArray = json_decode($jsonString, true);
    
        $time = now()->format('m월 d일 H시 i분 s초');
        $outputLines = explode("\n", $output);
        $firstLine = $outputLines[0] ?? 'No output';
    
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
    
        return response("$time / $firstLine")->header('Content-Type', 'text/plain; charset=utf-8');
    }
    
}
