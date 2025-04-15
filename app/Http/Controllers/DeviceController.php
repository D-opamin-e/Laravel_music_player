<?php
// DeviceController.php
public function registerDevice(Request $request)
{
    $deviceId = $request->input('device_id');
    $song = $request->input('song');
    
    // 기기 등록 또는 상태 업데이트
    $device = Device::updateOrCreate(
        ['device_id' => $deviceId],
        ['song' => $song]
    );

    // 등록된 기기 상태를 실시간으로 전파
    broadcast(new DeviceStatusUpdated($device));

    return response()->json(['message' => '기기 등록 성공']);
}
