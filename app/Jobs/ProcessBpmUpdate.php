<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ProcessBpmUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    public function handle()
    {
        Log::info("ProcessBpmUpdate Job 시작");

        $pythonPath = 'E:\\Laravel\\music\\nodejs\\python.exe';
        $pythonScriptPath = storage_path('../nodejs/bpm.py');

        $bpmCommand = "\"{$pythonPath}\" \"{$pythonScriptPath}\"";

        Log::info("BPM 업데이트 파이썬 스크립트 실행 명령 (Job): {$bpmCommand}");

        $process = Process::fromShellCommandline(
            $bpmCommand,
            null,
            ['PYTHONIOENCODING' => 'utf-8'],
            null,
            3600
        );
        try {
            $process->run();

            $bpmOutputRaw = $process->getOutput();
            $bpmErrorOutput = $process->getErrorOutput();

            Log::info("BPM 업데이트 파이썬 스크립트 실행 완료 (Job)");
            Log::info("--- BPM 스크립트 표준 출력 (Job) ---");
            Log::info($bpmOutputRaw);
            Log::info("--- BPM 스크립트 표준 에러 (Job) ---");
            Log::info($bpmErrorOutput);
            Log::info("--- 출력 끝 ---");


            if (!$process->isSuccessful()) {
                Log::error("BPM 스크립트 실행 실패 (Job). 종료 코드: " . $process->getExitCode());
                Log::error("에러 메시지: " . $bpmErrorOutput);
                throw new ProcessFailedException($process);
            }

            $bpmOutput = @iconv("EUC-KR", "UTF-8//IGNORE", $bpmOutputRaw);

            file_put_contents(
                storage_path('logs/bpm_exec_log.txt'),
                "Executed BPM.py (via Job)\nOutput:\n{$bpmOutput}\nError:\n{$bpmErrorOutput}\n---------------------\n",
                FILE_APPEND
            );


        } catch (ProcessFailedException $exception) {
            Log::error("ProcessBpmUpdate Job 실패: " . $exception->getMessage());
        } catch (\Exception $e) {
            Log::error("ProcessBpmUpdate Job 실행 중 예상치 못한 오류 발생: " . $e->getMessage());
        }

        Log::info("ProcessBpmUpdate Job 종료");
    }

    public function failed(\Throwable $exception)
    {
        Log::critical("ProcessBpmUpdate Job 최종 실패: " . $exception->getMessage());
    }
}