<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // 로그 사용
use Illuminate\Support\Facades\Storage; // Storage 파사드 사용 (로그 파일 저장 등에 필요시)
use Symfony\Component\Process\Process; // Process 컴포넌트 사용
use Symfony\Component\Process\Exception\ProcessFailedException; // 예외 처리

class ProcessBpmUpdate implements ShouldQueue // ShouldQueue 인터페이스를 구현해야 큐로 실행됩니다.
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Job 생성 시 필요한 데이터가 있다면 여기에 인자로 받아 저장합니다.
        // 현재 BPM 스크립트가 전체를 업데이트하는 것 같으므로 인자는 없을 수 있습니다.
        // 만약 특정 파일이나 특정 정보가 필요하다면 여기에 추가합니다.
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("ProcessBpmUpdate Job 시작");

        // 파이썬 실행 경로 및 스크립트 경로 (Job 안에서 정의하거나 생성자 인자로 받기)
        // 여기서는 MusicController에 있던 경로를 그대로 사용하겠습니다.
        $pythonPath = 'E:\\Laravel\\music\\nodejs\\python.exe';
        $pythonScriptPath = storage_path('../nodejs/bpm.py'); // storage_path는 Job에서도 사용 가능

        $bpmCommand = "\"{$pythonPath}\" \"{$pythonScriptPath}\""; // 명령어 변수

        Log::info("BPM 업데이트 파이썬 스크립트 실행 명령 (Job): {$bpmCommand}");

        // Symfony Process 컴포넌트를 사용하여 실행
        // 쉘 명령 실행 시 timeout 설정 가능
        $process = Process::fromShellCommandline(
            $bpmCommand,
            null, /* cwd - 현재 작업 디렉토리, null이면 PHP의 현재 디렉토리 상속 */
            ['PYTHONIOENCODING' => 'utf-8'], /* env - 환경 변수 배열 */ // <--- 이 부분을 이렇게 수정했는지 다시 확인해주세요.
            null, /* input - 표준 입력 */
            3600  /* timeout - 타임아웃 (초) */
        );
        try {
            $process->run(); // 스크립트 실행 및 완료까지 대기

            // 실행 후 표준 출력 가져오기
            $bpmOutputRaw = $process->getOutput();
            // 실행 후 표준 에러 가져오기
            $bpmErrorOutput = $process->getErrorOutput();

            Log::info("BPM 업데이트 파이썬 스크립트 실행 완료 (Job)");
            Log::info("--- BPM 스크립트 표준 출력 (Job) ---");
            Log::info($bpmOutputRaw); // 표준 출력 로깅
            Log::info("--- BPM 스크립트 표준 에러 (Job) ---");
            Log::info($bpmErrorOutput); // 표준 에러 로깅
            Log::info("--- 출력 끝 ---");


            // 파이썬 스크립트의 종료 코드를 확인하여 성공 여부 판단
            if (!$process->isSuccessful()) {
                 // 에러가 발생했거나 종료 코드가 0이 아닌 경우
                 Log::error("BPM 스크립트 실행 실패 (Job). 종료 코드: " . $process->getExitCode());
                 Log::error("에러 메시지: " . $bpmErrorOutput);
                 // 여기서 에러 알림 등의 추가 작업 수행 가능
                 throw new ProcessFailedException($process); // 실패 시 Job이 재시도되도록 예외 발생 (기본 설정 시)
            }

            // iconv 처리가 필요한 경우 여기에 추가
             $bpmOutput = @iconv("EUC-KR", "UTF-8//IGNORE", $bpmOutputRaw);

            // 로그 파일 기록 (기존 로직 옮김)
            file_put_contents(
                storage_path('logs/bpm_exec_log.txt'), // storage_path 사용
                "Executed BPM.py (via Job)\nOutput:\n{$bpmOutput}\nError:\n{$bpmErrorOutput}\n---------------------\n",
                FILE_APPEND
            );

            // TODO: 만약 python 스크립트가 DB 업데이트를 직접 하지 않고 결과를 JSON 등으로 출력한다면,
            //       여기서 $bpmOutput을 파싱하여 DB 업데이트 로직을 추가해야 합니다.
            //       현재는 스크립트가 DB를 직접 업데이트한다고 가정합니다.

        } catch (ProcessFailedException $exception) {
            Log::error("ProcessBpmUpdate Job 실패: " . $exception->getMessage());
            // 예외 발생 시 Job은 기본적으로 재시도됩니다.
        } catch (\Exception $e) {
             Log::error("ProcessBpmUpdate Job 실행 중 예상치 못한 오류 발생: " . $e->getMessage());
             // 다른 종류의 예외 처리
        }

        Log::info("ProcessBpmUpdate Job 종료");
    }

    /**
     * Job이 실패했을 때 처리할 메소드 (선택 사항)
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        // 실패 알림 (예: 슬랙, 이메일 등)을 보내거나 로깅합니다.
        Log::critical("ProcessBpmUpdate Job 최종 실패: " . $exception->getMessage());
    }
}