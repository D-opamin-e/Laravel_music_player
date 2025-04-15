<?php
namespace App\Listeners;

use App\Events\DeviceStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateDeviceStatus
{
    public function __construct()
    {
        // 리스너 초기화
    }

    public function handle(DeviceStatusUpdated $event)
    {
        // 이벤트 발생 시 실행될 코드
        // 예: 기기의 상태를 데이터베이스에 업데이트하는 코드
    }
}
