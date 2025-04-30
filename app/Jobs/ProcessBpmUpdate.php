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

class ProcessBpmUpdate implements ShouldQueue // ShouldQueue 인터페이스를 구현해야 큐로 실행
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("ProcessBpmUpdate Job 시작");
        $pythonPath = 'E:\\Laravel\\music\\nodejs\\python.exe';
        $pythonScriptPath = storage_path('../nodejs/bpm.py'); 

        $bpmCommand = "\"{$pythonPath}\" \"{$pythonScriptPath}\""; // 명령어 변수

        Log::info("BPM 업데이트 파이썬 스크립트 실행 명령 (Job): {$bpmCommand}");

        // Symfony Process 컴포넌트를 사용하여 실행
        // 쉘 명령 실행 시 timeout 설정 가능
        $process = Process::fromShellCommandline(
            $bpmCommand,
            null, /* cwd - 현재 작업 디렉토리, null이면 PHP의 현재 디렉토리 상속 */
            ['PYTHONIOENCODING' => 'utf-8'], /* env - 환경 변수 배열 */ 
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

        } catch (ProcessFailedException $exception) {
            Log::error("ProcessBpmUpdate Job 실패: " . $exception->getMessage());
            // 예외 발생 시 Job은 기본적으로 재시도
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
        // 실패 알림 (예: 슬랙, 이메일 등)을 보내거나 로깅
        Log::critical("ProcessBpmUpdate Job 최종 실패: " . $exception->getMessage());
    }
}