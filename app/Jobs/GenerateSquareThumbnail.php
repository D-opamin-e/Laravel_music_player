<?php

namespace App\Jobs;

use App\Models\Song;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // Log 파사드 사용
use Illuminate\Support\Facades\Storage; // Storage 파사드 사용
use Intervention\Image\ImageManagerStatic as Image; // Intervention Image 사용

class GenerateSquareThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $song;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Song  $song
     * @return void
     */
    public function __construct(Song $song)
    {
        $this->song = $song;
        // --- 디버깅 로그 ---
        Log::info("GenerateSquareThumbnail Job 생성됨 for videoID: {$this->song->videoID}");
        // --- 디버깅 로그 끝 ---
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // --- 디버깅 로그 ---
        Log::info("GenerateSquareThumbnail Job 처리 시작 for videoID: {$this->song->videoID}");
        // --- 디버깅 로그 끝 ---

        $videoID = $this->song->videoID;
        $squareThumbnailFileName = "thumbnails/square/{$videoID}.jpg";
        $originalThumbnailUrl = "https://i.ytimg.com/vi/{$videoID}/maxresdefault.jpg"; // 원본 유튜브 썸네일 URL

        // 썸네일이 이미 존재하는지 다시 한번 확인 (Job 중복 실행 방지)
        if (Storage::disk('public')->exists($squareThumbnailFileName)) {
            // --- 디버깅 로그 ---
            Log::info("GenerateSquareThumbnail Job: 썸네일 파일이 이미 존재합니다. Job 종료. (videoID: {$videoID})");
            // --- 디버깅 로그 끝 ---
            return; // 이미 파일이 있으면 Job 처리 중단
        }

        try {
            // --- 디버깅 로그 ---
            Log::info("GenerateSquareThumbnail Job: 썸네일 파일 없음. 다운로드 및 처리 시도. (videoID: {$videoID}), URL: {$originalThumbnailUrl}");
            // --- 디버깅 로그 끝 ---

            // 1. 원본 이미지 다운로드 및 로드
            $image = Image::make($originalThumbnailUrl);

            // --- 디버깅 로그 ---
            Log::info("GenerateSquareThumbnail Job: 원본 이미지 로드 성공. (videoID: {$videoID})");
            // --- 디버깅 로그 끝 ---

            // 2. 이미지를 정사각형으로 자르고 원하는 크기로 리사이즈
            $targetSize = 512; // 원하는 정사각형 최종 크기 (픽셀)
            $image->fit($targetSize, $targetSize, function ($constraint) {
                 $constraint->upsize(); // 작은 이미지를 확대하지 않음
                 // $constraint->aspectRatio(); // 비율 유지 (fit 사용 시 필요 없을 수 있음)
            });

            // --- 디버깅 로그 ---
            Log::info("GenerateSquareThumbnail Job: 이미지 처리 (자르기/리사이즈) 성공. (videoID: {$videoID})");
            // --- 디버깅 로그 끝 ---

            // 3. 처리된 이미지 저장 (public 디스크 사용)
            Storage::disk('public')->put($squareThumbnailFileName, $image->stream('jpg', 90)); // 품질 90으로 JPEG 저장

            // --- 디버깅 로그 ---
            Log::info("GenerateSquareThumbnail Job: 정사각형 썸네일 저장 성공: {$squareThumbnailFileName} (videoID: {$videoID})");
            // --- 디버깅 로그 끝 ---

            // (선택 사항) DB에 썸네일 URL 저장 필드가 있다면 업데이트
            // 예: $this->song->square_thumbnail_url = Storage::url($squareThumbnailFileName);
            // $this->song->save();

        } catch (\Exception $e) {
            // 이미지 처리 또는 저장 중 오류 발생 시
            // --- 디버깅 로그 ---
            Log::error("GenerateSquareThumbnail Job: 썸네일 생성/저장 중 오류 발생 (videoID: {$videoID}): " . $e->getMessage());
            Log::error("GenerateSquareThumbnail Job: 오류 trace: " . $e->getTraceAsString()); // 오류 발생 위치 및 경로 로깅
            // --- 디버깅 로그 끝 ---

            // Job 실패 시 재시도 또는 failed_jobs 테이블 기록은 큐 설정에 따라 자동 처리
        }

        // --- 디버깅 로그 ---
        Log::info("GenerateSquareThumbnail Job 처리 완료 for videoID: {$this->song->videoID}");
        // --- 디버깅 로그 끝 ---
    }

    // Job 실패 시 호출될 메소드 (선택 사항)
    // public function failed(\Throwable $exception)
    // {
    //     Log::error("GenerateSquareThumbnail Job 실패 for videoID: {$this->song->videoID} with error: " . $exception->getMessage());
    // }
}