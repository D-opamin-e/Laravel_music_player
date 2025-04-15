<?php
// DeviceController.php

public function updateDeviceStatus(Request $request)
{
    $deviceId = $request->input('device_id');
    $status = $request->input('status'); // 재생 중, 일시정지 등

    $device = Device::where('device_id', $deviceId)->first();
    if ($device) {
        $device->status = $status;
        $device->save();
    }

    return response()->json(['message' => '기기 상태 업데이트 완료']);
}

