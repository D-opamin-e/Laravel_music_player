<?php
use Illuminate\Support\Facades\DB;

class LyricsController extends Controller
{
    public function show($id)
    {
        // DB에서 가사 가져오기 (예시)
        $song = DB::table('songs')->where('id', $id)->first();

        if ($song && $song->lyrics) {
            return response()->json(['lyrics' => $song->lyrics]);
        } else {
            return response()->json(['lyrics' => '가사를 찾을 수 없습니다.'], 404);
        }
    }
}
