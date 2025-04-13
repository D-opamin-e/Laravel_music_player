// WebRTC 연결 설정
let peerConnection = new RTCPeerConnection();
let dataChannel = peerConnection.createDataChannel("deviceInfo");

// 기기 상태를 서버로 보내기
function sendDeviceInfo(deviceId, song) {
    fetch('/register-device', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ device_id: deviceId, song: song })
    })
    .then(response => response.json())
    .then(data => {
        console.log(data.message);
    });
}

// 서버로부터 기기 목록 받기
function getDevices() {
    fetch('/get-devices')
        .then(response => response.json())
        .then(devices => {
            updateDeviceList(devices);
        });
}

// 기기 목록 업데이트
function updateDeviceList(devices) {
    const deviceList = document.getElementById("deviceList");
    deviceList.innerHTML = '';
    devices.forEach(device => {
        const li = document.createElement("li");
        li.textContent = `Device: ${device.device_id}, Song: ${device.song}`;
        li.addEventListener('click', () => controlDevice(device.device_id));
        deviceList.appendChild(li);
    });
}

// 다른 기기 제어하기
function controlDevice(deviceId) {
    fetch(`/control-device/${deviceId}`)
        .then(response => response.json())
        .then(data => {
            console.log("기기 제어 성공:", data);
        });
}
