<?php
use App\Events\DeviceStatusUpdated;
use App\Listeners\UpdateDeviceStatus;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DeviceStatusUpdated::class => [
            UpdateDeviceStatus::class,  // 이벤트를 듣고 실행할 리스너
        ],
    ];
}
