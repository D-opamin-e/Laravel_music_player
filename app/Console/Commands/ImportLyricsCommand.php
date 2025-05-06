<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Song; // Song 모델 사용
use Illuminate\Support\Facades\Storage; // 파일 시스템 관리를 위해 Storage 파사드 사용 (필요에 따라 사용)
use Illuminate\Support\Facades\File; // 파일 처리를 위해 File 파사드 사용
use Illuminate\Support\Facades\Log; // 로깅을 위해 Log 파사드 사용

class ImportLyricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-lyrics {--dir=whisper : Directory containing the lyric JSON files relative to the project root}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports lyrics from JSON files into the database based on song title.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 명령어 옵션으로 가사 JSON 파일 디렉토리 경로 가져오기 (프로젝트 루트 기준)
        $lyricsDirectory = $this->option('dir');

        $this->info("가사 JSON 파일 디렉토리: {$lyricsDirectory}");

        // 지정된 디렉토리가 있는지 확인
        // File::isDirectory는 절대 경로 또는 스토리지(storage_path) 기준 경로를 잘 처리합니다.
        // 여기서는 프로젝트 루트 기준 경로를 옵션으로 받으므로 File::isDirectory가 바로 동작합니다.
        if (!File::isDirectory($lyricsDirectory)) {
            $this->error("오류: 지정된 디렉토리를 찾을 수 없습니다. 경로를 확인해주세요. ({$lyricsDirectory})");
            $this->error("예: php artisan app:import-lyrics --dir=storage/app/whisper");
            return Command::FAILURE;
        }

        // 해당 디렉토리의 모든 JSON 파일 목록 가져오기
        $jsonFiles = File::files($lyricsDirectory);

        if (empty($jsonFiles)) {
            $this->info("지정된 디렉토리에 JSON 파일이 없습니다.");
            return Command::SUCCESS;
        }

        $this->info("총 " . count($jsonFiles) . "개의 JSON 파일을 찾았습니다. 데이터베이스 임포트를 시작합니다...");

        $processedCount = 0;
        $importedCount = 0;
        $skippedCount = 0; // 곡을 찾지 못해 건너뛴 파일
        $errorCount = 0;   // 파일 읽기 또는 DB 저장 중 오류 발생

        foreach ($jsonFiles as $file) {
            $fileName = $file->getFilename(); // 파일 이름 (예: 곡제목.json)
            // 파일 이름에서 확장자를 제외한 부분, 즉 곡 제목을 추출합니다.
            $songTitle = pathinfo($fileName, PATHINFO_FILENAME);
            $filePath = $file->getRealPath(); // 파일의 실제 경로

            $this->comment("\n--- 처리 중: {$fileName} (곡 제목: {$songTitle}) ---");

            // 데이터베이스에서 파일 이름(확장자 제외)과 일치하는 곡 찾기
            // 파일 이름과 데이터베이스 'title' 컬럼의 값이 정확히 일치해야 합니다.
            $song = Song::where('title', $songTitle)->first();

            if (!$song) {
                $this->warn("경고: 데이터베이스에서 제목이 '{$songTitle}'인 곡을 찾을 수 없습니다. 이 파일을 건너뜁니다.");
                $skippedCount++;
                continue;
            }

            // JSON 파일 내용 읽기
            try {
                $jsonStringFromFile = File::get($filePath);
            } catch (\Exception $e) {
                $this->error("오류: 파일 '{$fileName}'을(를) 읽는 중 오류 발생: " . $e->getMessage());
                $errorCount++;
                continue;
            }


            // JSON 문자열 디코딩
            $lyricsArray = json_decode($jsonStringFromFile, true);

            // JSON 디코딩 오류 확인
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($lyricsArray)) {
                $this->error("오류: 파일 '{$fileName}'의 JSON 형식이 유효하지 않습니다. 오류: " . json_last_error_msg());
                // 파일 내용 일부를 로깅하여 문제 파악에 도움
                Log::error("JSON decode error for file {$fileName}. Error: " . json_last_error_msg() . ". File content begins with: " . substr($jsonStringFromFile, 0, 100) . "...");
                $errorCount++;
                continue;
            }

            // 데이터베이스 저장을 위한 JSON 문자열로 인코딩
            // 데이터베이스 컬럼이 TEXT/LONGTEXT 타입이라면 json_encode 결과 문자열을 저장합니다.
            // JSON 타입 컬럼이라면 배열($lyricsArray)을 바로 할당해도 라라벨이 자동으로 변환합니다.
            // 안전하게 문자열로 인코딩하여 저장하는 방식을 사용합니다.
            $jsonStringForDb = json_encode($lyricsArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); // 한글, 슬래시 깨짐 방지 및 예쁘게 출력

            // Song 모델 업데이트
            try {
                $song->lyrics = $jsonStringForDb;
                $song->save();
                $this->info("성공: ID {$song->id} ('{$songTitle}') 곡의 가사를 업데이트했습니다.");
                $importedCount++;
            } catch (\Exception $e) {
                $this->error("오류: ID {$song->id} ('{$songTitle}') 곡 가사 DB 저장 중 오류 발생: " . $e->getMessage());
                // 저장 실패 시 로깅
                Log::error("Database save error for song ID {$song->id}, title '{$songTitle}': " . $e->getMessage());
                $errorCount++;
            }

            $processedCount++;
        }

        // 최종 결과 요약
        $this->info("\n======= 가사 임포트 결과 요약 =======");
        $this->info("총 JSON 파일: " . count($jsonFiles) . "개");
        $this->info("처리 시도 파일: {$processedCount}개");
        $this->info("데이터베이스 임포트 성공: {$importedCount}개");
        $this->warn("데이터베이스에서 곡을 찾지 못함 (건너뛰기): {$skippedCount}개");
        $this->error("JSON 파일 읽기/형식 오류 또는 DB 저장 오류: {$errorCount}개");
        $this->info("===================================");

        // 실패한 항목이 있다면 실패 코드를 반환
        if ($skippedCount > 0 || $errorCount > 0) {
             return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}