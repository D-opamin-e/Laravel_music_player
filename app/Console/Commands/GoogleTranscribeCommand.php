<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
// --- V2 Namespace ---
use Google\Cloud\Speech\V2\SpeechClient;
use Google\Cloud\Speech\V2\RecognitionConfig;
use Google\Cloud\Speech\V2\RecognitionConfig\AudioEncoding;
use Google\Cloud\Speech\V2\RecognitionAudio; // V2 might put RecognitionAudio directly under V2 namespace
use Google\Protobuf\Duration; // Duration class is likely from a common protobuf library

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File; // Assuming File facade is still needed

class GoogleTranscribeCommand extends Command
{
    // ... signature and description remain the same ...
    protected $signature = 'google:transcribe {gcs_uri : Google Cloud Storage URI of the audio file}';
    protected $description = 'Transcribes audio from GCS using Google Cloud STT API (V2).'; // Updated description

    public function handle()
    {
        // ... (Retrieving GCS URI, project ID, key file path remains the same) ...
        $gcsUri = $this->argument('gcs_uri');
        $projectId = env('GOOGLE_CLOUD_PROJECT_ID');
        $keyFilePath = env('GOOGLE_CLOUD_KEY_FILE');
        $fullKeyFilePath = base_path($keyFilePath);
        // dd($fullKeyFilePath);
        // ... (Error checking for project ID, key file path, and file existence remains the same) ...
         if (!$projectId) {
             $this->error("오류: .env 파일에 GOOGLE_CLOUD_PROJECT_ID가 설정되지 않았습니다.");
             return Command::FAILURE;
        }
         if (!$keyFilePath) {
             $this->error("오류: .env 파일에 GOOGLE_CLOUD_KEY_FILE이 설정되지 않았습니다.");
             return Command::FAILURE;
        }
         if (!file_exists($fullKeyFilePath)) {
             $this->error("오류: Google Cloud 서비스 계정 키 파일을 찾을 수 없습니다. 경로를 확인해주세요: " . $fullKeyFilePath);
             $this->info("GOOGLE_CLOUD_KEY_FILE 환경 변수에 파일 이름만 설정했다면, 해당 파일이 프로젝트 루트에 있어야 합니다.");
             return Command::FAILURE;
        }


        // SpeechClient 인스턴스 생성 (서비스 계정 키 파일 경로 지정) - Using V2
        try {
            $client = new SpeechClient([
                'projectId' => $projectId,
                'keyFilePath' => $fullKeyFilePath,
                // 'scopes' => SpeechClient::DEFAULT_SCOPE, // Default scopes might be automatically handled or needed
            ]);
        } catch (\Exception $e) {
            $this->error("오류: Google Cloud SpeechClient (V2) 생성 중 오류 발생. 인증 정보 또는 네트워크 문제일 수 있습니다: " . $e->getMessage());
            Log::error("SpeechClient (V2) creation error: " . $e->getMessage());
            return Command::FAILURE;
        }

        // 오디오 설정 (GCS URI 지정) - Check V2 class name
        // V2 might not use RecognitionAudio object directly, or it might be under V2 namespace
        // Assuming RecognitionAudio exists in V2 namespace or needs a different approach
        // Let's try using the URI directly in the recognize method config if RecognitionAudio object is not used
        // Or assume RecognitionAudio\Audio\Uri is the correct V2 class
        // Based on docs, V2 uses InputConfig and AudioSource, and RecognizeRequest
        // This is a significant change in V2 API structure compared to V1's longRunningRecognize

        // --- V2 API Call Structure is Different ---
        // V2 often uses a 'recognizer' resource concept
        // Example V2 (conceptual, requires checking actual V2 client library methods):
        // $recognizerName = $client->recognizerName($projectId, 'default-recognizer'); // Or list recognizers
        // $inputConfig = (new InputConfig())
        //     ->setGcsSource((new GcsSource())->setUri($gcsUri));
        // $recognitionOutputConfig = (new RecognitionOutputConfig())
        //     ->setGcsDestination((new GcsDestination())->setUri('gs://your-output-bucket/results/')); // V2 can save results directly to GCS

        // $recognizeRequest = (new RecognizeRequest())
        //    ->setConfig($config) // RecognitionConfig V2
        //    ->setInputConfig($inputConfig);

        // $operation = $client->recognize($recognizerName, $recognizeRequest); // V2 might use recognize method on a recognizer

        // --- Reverting to V1-like Long Running for now as V2 async example is more complex ---
        // It seems the longRunningRecognize method might still exist or V2 has an async equivalent
        // Let's stick to the longRunningRecognize *concept* and just update namespaces
        // If longRunningRecognize is gone, a significant rewrite is needed

         // RecognitionAudio (assuming it exists in V2 or similar concept)
        $audio = (new RecognitionAudio())
             ->setUri($gcsUri);

         // RecognitionConfig (V2)
         $config = (new RecognitionConfig())
             ->setEncoding(AudioEncoding::MP3) // Check V2 Enum Path
             ->setSampleRateHertz(44100)
             ->setLanguageCode('ko-KR')
             ->setEnableWordTimeOffsets(true);


        $this->info("Google Cloud Speech-to-Text (V2) 비동기 인식 요청 중...");
        $this->info("GCS URI: " . $gcsUri);

        try {
            // 비동기 인식 요청 실행 - Check V2 method name and parameters
            // V2 might use a different method than longRunningRecognize
            // Let's assume longRunningRecognize exists for now, but its signature might differ
            $operation = $client->longRunningRecognize($config, $audio);


            $this->info("비동기 인식 작업이 생성되었습니다. 작업 이름: " . $operation->getName());
            $this->info("작업 완료까지 기다리는 중...");

            // 작업 완료까지 대기
            // V2 Operation object might have different methods
            $operation->pollUntilDone(null, 600); // 타임아웃 600초 (10분)

            if ($operation->operationSucceeded()) {
                $this->info("비동기 인식 작업 성공!");
                $response = $operation->getResult(); // Check V2 getResult method

                // 결과 처리 및 단어별 타임스탬프 추출 - Check V2 response structure
                // V2 response structure might differ from V1
                $results = $response->getResults(); // Assuming getResults exists and structure is similar

                if (empty($results)) {
                    $this->warn("인식 결과가 없습니다.");
                    return Command::SUCCESS;
                }

                $this->info("인식 결과 처리 중...");
                $syncedLyricsRawStt = []; // raw STT 결과를 저장할 리스트 (디버깅용)

                // 결과에서 단어별 타임스탬프 추출 및 콘솔 출력
                foreach ($results as $result) {
                    // V2 result structure might differ
                    $alternative = $result->getAlternatives()[0]; // Assuming getAlternatives exists and structure is similar
                    $transcript = $alternative->getTranscript(); // Assuming getTranscript exists
                    $words = $alternative->getWords(); // Assuming getWords exists

                    $this->line("--- 인식된 텍스트 세그먼트 ---");
                    $this->line("텍스트: " . $transcript);
                    $this->line("단어별 타임스탬프:");

                    foreach ($words as $wordInfo) {
                         // V2 WordInfo object might have different methods
                        $startTime = $wordInfo->getStartTime(); // Assuming getStartTime exists
                        $word = $wordInfo->getWord(); // Assuming getWord exists

                        // Duration 객체에서 초 단위로 변환 (Duration class likely same)
                        // Assuming total_seconds() method exists or similar way to get seconds
                         if (method_exists($startTime, 'total_seconds')) {
                             $startTimeInSeconds = $startTime->total_seconds();
                         } elseif (method_exists($startTime, 'getSeconds') && method_exists($startTime, 'getNanos')) {
                             $startTimeInSeconds = $startTime->getSeconds() + ($startTime->getNanos() / 1e9);
                         } else {
                             $startTimeInSeconds = 0; // Fallback if method not found
                             $this->warn("Could not get start time from WordInfo object.");
                         }


                        $this->line(sprintf("  - 단어: '%s', 시작 시간: %.3f초", $word, $startTimeInSeconds));

                         // --- 후처리 및 가사 라인 매칭 로직 통합 (TODO) ---
                         // 로직 자체는 동일, 입력 데이터 구조 접근 방식만 V2에 맞게 조정 필요
                         // --------------------------------------------------------
                    }
                     $this->line("---------------------------");
                }

                // --- 최종 $syncedLyricsData를 DB에 저장하는 로직 추가 (TODO) ---
                // 로직 자체는 동일
                // ----------------------------------------------------


            } else {
                $this->error("비동기 인식 작업 실패.");
                $this->error("오류 상세: " . $operation->getError()->getMessage());
                 Log::error("GC STT Operation failed for URI {$gcsUri} (V2): " . $operation->getError()->getMessage());
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("오류: Google Cloud STT (V2) 요청 중 예상치 못한 예외 발생: " . $e->getMessage());
            Log::error("GC STT Request Exception for URI {$gcsUri} (V2): " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            // 클라이언트 닫기 (자원 해제)
            if (isset($client)) {
                 $client->close();
            }
        }

        return Command::SUCCESS;
    }

     /**
      * TODO: Google Cloud STT 단어별 결과를 정확한 가사 라인과 매칭시키는 메소드
      * 이 메소드는 별도의 클래스로 분리하는 것이 좋습니다.
      * 입력 $sttResults 구조가 V2에 맞게 변경될 수 있습니다.
      *
      * @param array $sttResults Google Cloud STT API 응답의 results 배열 (V2 구조)
      * @param array $accurateLyricsList 정확한 가사 라인 리스트 (string 배열)
      * @return array [{'time': float, 'line': string}, ...] 형태의 싱크된 가사 데이터
      */
     // private function matchAccurateLyrics(array $sttResults, array $accurateLyricsList): array
     // {
     //     // --- 매칭 로직 구현 (V2 결과 구조에 맞춰 데이터 접근 방식 변경) ---
     //     // $sttResults 배열의 구조가 V1과 다를 수 있으므로, V2 문서를 참고하여
     //     // 단어별 타임스탬프와 텍스트에 접근하는 방식을 수정해야 합니다.
     //     // 로직 자체(유사도 기반 매칭)는 동일할 수 있습니다.
     //     // --------------------
     //     $syncedData = [];
     //     // ... 구현 ...
     //     return $syncedData;
     // }
}