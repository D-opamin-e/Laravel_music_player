<?php

// 라라벨 Artisan Command 또는 서비스 클래스 등 적절한 파일 안에 작성
// 예: app/Console/Commands/TranscribeAudio.php (Artisan Command)

use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Protobuf\Duration; // Duration 클래스 사용 (단어 타임스탬프 시간에 사용)
use Illuminate\Console\Command; // Artisan Command에 사용하는 경우

// Artisan Command 클래스라고 가정
class TranscribeAudio extends Command
{
    protected $signature = 'audio:transcribe {gcs_uri : Google Cloud Storage URI of the audio file}';
    protected $description = 'Transcribes audio from GCS using Google Cloud STT API.';

    public function handle()
    {
        $gcsUri = $this->argument('gcs_uri'); // 명령줄 인자로 GCS URI 받기
        $projectId = env('GOOGLE_CLOUD_PROJECT_ID'); // .env 파일 등에서 Google Cloud Project ID 설정
        $keyFilePath = env('GOOGLE_CLOUD_KEY_FILE'); // .env 파일 등에서 서비스 계정 JSON 키 파일 경로 설정

        if (!$projectId || !$keyFilePath) {
            $this->error("Google Cloud Project ID 또는 서비스 계정 키 파일 경로가 설정되지 않았습니다.");
            $this->info("GOOGLE_CLOUD_PROJECT_ID, GOOGLE_CLOUD_KEY_FILE 환경 변수를 .env 파일에 설정해주세요.");
            return Command::FAILURE;
        }

        // SpeechClient 인스턴스 생성 (서비스 계정 키 파일 경로 지정)
        $client = new SpeechClient([
            'projectId' => $projectId,
            'keyFilePath' => base_path($keyFilePath), // 프로젝트 루트 기준 서비스 계정 키 파일 경로
        ]);

        // 오디오 설정 (GCS URI 지정)
        $audio = (new RecognitionAudio())
            ->setUri($gcsUri); // Google Cloud Storage URI (예: gs://your-bucket-name/your-audio-file.mp3)

        // 인식 설정
        $config = (new RecognitionConfig())
            ->setEncoding(AudioEncoding::MP3) // 오디오 파일 인코딩 (MP3, LINEAR16 등) - 파일 형식에 맞게 설정
            ->setSampleRateHertz(44100) // 샘플링 속도 (Hz) - 오디오 파일 속성에 맞게 설정
            ->setLanguageCode('ko-KR') // 언어 코드 (한국어)
            ->setEnableWordTimeOffsets(true); // !!! 단어별 타임스탬프 활성화 !!!

        $this->info("Google Cloud Speech-to-Text 비동기 인식 요청 중...");

        try {
            // 비동기 인식 요청 실행
            $operation = $client->longRunningRecognize($config, $audio);

            $this->info("비동기 인식 작업이 생성되었습니다. 작업 이름: " . $operation->getName());
            $this->info("작업 완료까지 기다리는 중...");

            // 작업 완료까지 대기 (실제 애플리케이션에서는 웹훅 등으로 비동기 처리하는 것이 더 효율적)
            $operation->pollUntilDone();

            if ($operation->operationSucceeded()) {
                $this->info("비동기 인식 작업 성공!");
                $response = $operation->getResult();

                // 결과 처리 및 단어별 타임스탬프 추출
                $results = $response->getResults();

                if (empty($results)) {
                    $this->warn("인식 결과가 없습니다.");
                    return Command::SUCCESS;
                }

                $this->info("인식 결과 처리 중...");
                $syncedLyricsData = [];

                // 결과에서 단어별 타임스탬프 추출
                foreach ($results as $result) {
                    // 보통 가장 가능성 높은 첫 번째 대안(alternative)을 사용
                    $alternative = $result->getAlternatives()[0];
                    $transcript = $alternative->getTranscript();
                    $words = $alternative->getWords(); // 단어별 타임스탬프 리스트

                    // 여기서는 예시로 단어별 타임스탬프를 모두 출력합니다.
                    // 실제 가사 싱크 로직에서는 이 단어들을 미리 가진 정확한 가사 라인과 매칭시켜야 합니다.
                    $this->line("인식된 텍스트: " . $transcript);

                    foreach ($words as $wordInfo) {
                        $startTime = $wordInfo->getStartTime(); // Duration 객체
                        $word = $wordInfo->getWord();

                        // Duration 객체에서 초 단위로 변환
                        // Duration 객체는 초와 나노초로 구성됩니다.
                        $seconds = $startTime->getSeconds();
                        $nanos = $startTime->getNanos();
                        $startTimeInSeconds = $seconds + ($nanos / 1e9);

                        $this->line(sprintf("  단어: %s, 시작 시간: %.3f초", $word, $startTimeInSeconds));

                         // --- 가사 라인 매칭 및 데이터 구조 생성 (추가 로직 필요) ---
                         // 여기서는 단어별 타임스탬프만 가져오는 예시이며,
                         // 이 단어들을 미리 가진 정확한 가사 라인과 매칭시켜
                         // [{time: 라인시작시간, line: 해당 라인 텍스트}] 형태로 만드는 로직은 추가해야 합니다.
                         // 이 매칭 로직은 이전에 논의했던 정확도 개선 스크립트의 새로운 버전이 될 것입니다.
                         // 예를 들어, 정확한 가사 라인을 순회하며, 해당 라인에 해당하는 STT 단어들의
                         // 첫 단어 타임스탬프를 라인 시작 시간으로 사용하는 방식입니다.
                         // $syncedLyricsData.append({"time": line_start_time, "line": accurate_line_text});
                         // --------------------------------------------------------
                    }
                }

                // --- 최종 syncedLyricsData를 DB에 저장하는 로직 추가 ---
                // $syncedLyricsData 배열을 JSON 문자열로 변환하여 DB에 저장합니다.
                // 라라벨 Artisan Command로 Google Cloud STT 결과를 가져오고,
                // 여기서 생성된 $syncedLyricsData를 해당 곡의 DB 레코드에 업데이트하면 됩니다.
                // 이 부분은 이전에 논의했던 라라벨 DB 임포트 로직과 결합될 수 있습니다.
                // $song = App\Models\Song::where('some_identifier', '...')->first();
                // $song->lyrics = json_encode($syncedLyricsData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                // $song->save();
                // ----------------------------------------------------


            } else {
                $this->error("비동기 인식 작업 실패.");
                $this->error("오류 상세: " . $operation->getError()->getMessage());
                return Command::FAILURE;
            }

        } finally {
            // 클라이언트 닫기 (자원 해제)
            $client->close();
        }

        return Command::SUCCESS;
    }
}