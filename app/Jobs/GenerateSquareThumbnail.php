<?php

namespace App\Jobs;

use App\Models\Song;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class GenerateSquareThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $song;

    public function __construct(Song $song)
    {
        $this->song = $song;
    }

    public function handle()
    {
        $videoID = $this->song->videoID;
        $squareThumbnailFileName = "thumbnails/square/{$videoID}.jpg";
        $originalThumbnailUrl = "https://i.ytimg.com/vi/{$videoID}/maxresdefault.jpg";

        if (Storage::disk('public')->exists($squareThumbnailFileName)) {
            return;
        }

        try {
            $image = Image::make($originalThumbnailUrl);

            $targetSize = 512;
            $image->fit($targetSize, $targetSize, function ($constraint) {
                $constraint->upsize();
            });

            Storage::disk('public')->put($squareThumbnailFileName, $image->stream('jpg', 90));


        } catch (\Exception $e) {
            Log::error("GenerateSquareThumbnail Job: 썸네일 생성/저장 중 오류 발생 (videoID: {$videoID}): " . $e->getMessage());
            Log::error("GenerateSquareThumbnail Job: 오류 trace: " . $e->getTraceAsString());
        }
    }
}