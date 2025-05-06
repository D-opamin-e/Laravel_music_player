<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;

class LyricsController extends Controller
{
    public function show($id)
    {
        // Song 모델을 통해 가사 조회
        $song = Song::find($id);

        // 노래가 존재하고 가사 필드에 데이터가 있을 경우
        if ($song && $song->lyrics) {
            // !!! 중요: 여기서 $song->lyrics는 데이터베이스에 저장된 값입니다.
            // 이 값은 반드시 [ {"time": 0.0,"line": "..."}, {"time": 6.5,"line": "..."}, ... ]
            // 형태의 JSON **문자열**이어야 합니다.
            // 만약 데이터베이스에 일반 텍스트 가사만 저장되어 있다면,
            // 가사 데이터를 DB에 저장하는 코드를 먼저 수정하여 JSON 문자열로 저장되게 해야 합니다.

            // song->lyrics 필드에 저장된 JSON 문자열을 PHP 배열로 디코딩합니다.
            $lyricsData = json_decode($song->lyrics, true); // true를 넣어 연관 배열로 변환

            // JSON 디코딩 성공 여부 확인 및 디코딩된 데이터가 배열인지 확인합니다.
            if (json_last_error() === JSON_ERROR_NONE && is_array($lyricsData)) {
                 // 디코딩 성공! PHP 배열을 JSON 응답으로 반환합니다.
                 // 프론트엔드의 fetch API는 이 응답을 받으면 자동으로 JSON 배열로 파싱해줍니다.
                return response()->json($lyricsData);
            } else {
                // JSON 형식이 아니거나 디코딩 실패한 경우
                // 이는 데이터베이스에 저장된 song->lyrics 값이 유효한 JSON 문자열이 아니거나,
                // 혹은 다른 이유로 디코딩할 수 없음을 의미합니다. (예: 일반 텍스트 저장)
                \Log::error("Song ID {$id}의 가사 데이터 JSON 디코딩 오류 또는 형식이 올바르지 않음: " . json_last_error_msg() . " Data begins with: " . substr($song->lyrics, 0, 100) . "..."); // 로깅 강화
                return response()->json(['message' => '저장된 가사 데이터 형식이 올바르지 않습니다.'], 500); // 500 Internal Server Error 응답
            }

        } else {
            // 노래가 없거나 가사 필드에 데이터가 없는 경우
            // 404 응답과 함께 가사가 없다는 메시지를 보냅니다.
            // 프론트엔드는 이 메시지를 사용자에게 표시할 수 있습니다.
            return response()->json(['message' => '가사를 찾을 수 없습니다.'], 404);
        }
    }
}