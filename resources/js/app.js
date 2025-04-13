// 클라이언트에서 실시간으로 상태 수신
Echo.channel('device-status')
    .listen('DeviceStatusUpdated', (event) => {
        console.log('기기 상태 업데이트:', event.device);
        // 여기에서 B 기기가 A 기기의 상태를 반영하도록 UI 갱신
        updateDeviceState(event.device);
    });

// B 기기에서 A 기기 제어하기 (재생/일시정지 등)
function togglePlayback() {
    // 서버로 재생/일시정지 명령 전송
    fetch('/toggle-playback', {
        method: 'POST',
        body: JSON.stringify({ device_id: 'A의 기기ID', action: 'play/pause' })
    })
    .then(response => response.json())
    .then(data => console.log(data));
}
