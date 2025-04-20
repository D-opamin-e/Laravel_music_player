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
        Log::info("GenerateSquareThumbnail Job 생성됨 for videoID: {$this->song->videoID}");
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("GenerateSquareThumbnail Job 처리 시작 for videoID: {$this->song->videoID}");

        $videoID = $this->song->videoID;
        $squareThumbnailFileName = "thumbnails/square/{$videoID}.jpg";
        $originalThumbnailUrl = "https://i.ytimg.com/vi/{$videoID}/maxresdefault.jpg"; // 원본 유튜브 썸네일 URL

        if (Storage::disk('public')->exists($squareThumbnailFileName)) {
            Log::info("GenerateSquareThumbnail Job: 썸네일 파일이 이미 존재합니다. Job 종료. (videoID: {$videoID})");
            return; 
        }

        try {
            Log::info("GenerateSquareThumbnail Job: 썸네일 파일 없음. 다운로드 및 처리 시도. (videoID: {$videoID}), URL: {$originalThumbnailUrl}");
            $image = Image::make($originalThumbnailUrl);
            Log::info("GenerateSquareThumbnail Job: 원본 이미지 로드 성공. (videoID: {$videoID})");
 
            $targetSize = 512; // 정사각형 크기 (픽셀)
            $image->fit($targetSize, $targetSize, function ($constraint) {
                 $constraint->upsize(); // 작은 이미지를 확대하지 않음
                 // $constraint->aspectRatio(); // 비율 유지 (fit 사용 시 필요 없을 수 있음)
            });

            Log::info("GenerateSquareThumbnail Job: 이미지 처리 (자르기/리사이즈) 성공. (videoID: {$videoID})");

            Storage::disk('public')->put($squareThumbnailFileName, $image->stream('jpg', 90)); // 품질 90으로 JPEG 저장
            Log::info("GenerateSquareThumbnail Job: 정사각형 썸네일 저장 성공: {$squareThumbnailFileName} (videoID: {$videoID})");
        } catch (\Exception $e) {
            Log::error("GenerateSquareThumbnail Job: 썸네일 생성/저장 중 오류 발생 (videoID: {$videoID}): " . $e->getMessage());
            Log::error("GenerateSquareThumbnail Job: 오류 trace: " . $e->getTraceAsString()); 
        }
        Log::info("GenerateSquareThumbnail Job 처리 완료 for videoID: {$this->song->videoID}");
    }

    // Job 실패 시 호출될 메소드 (선택 사항)
    // public function failed(\Throwable $exception)
    // {
    //     Log::error("GenerateSquareThumbnail Job 실패 for videoID: {$this->song->videoID} with error: " . $exception->getMessage());
    // }
}