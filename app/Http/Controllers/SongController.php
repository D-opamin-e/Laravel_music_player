<?php
public function updatePlayCount(Request $request)
{
    $index = $request->input('index');
    
    $song = Song::where('index', $index)->first();

    if ($song) {
        $song->increment('play_count');
        return response()->json(['message' => 'SongController.php 재생 수 증가 완료']);
    } else {
        return response()->json(['message' => 'SongController.php 곡을 찾을 수 없습니다.'], 404);
    }
}
