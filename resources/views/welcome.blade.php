@extends('layouts.app')

@section('title', '상재의 노래주머니')

@push('styles')
    <link rel="stylesheet" href="{{ asset('CSS/music.css?r=2') }}">
    <link rel="stylesheet" href="{{ asset('CSS/bootstrap.css?r=2') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
          integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
          crossorigin="anonymous">
    <style>
        .content {
            padding-top: 70px; /* header 가림 방지 */
        }
    </style>
@endpush

@section('content')
    <div id="playlistContainer">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <input type="text" id="searchInput" placeholder="노래 제목을 검색하세요!">
                <button class="btn btn-outline-dark" id="updateButton">재생목록 업데이트</button>
            </div>
            <div id="totalSongs">
                <small>전체 곡 개수: 
                    @isset($playlist)
                        {{ is_countable($playlist) ? count($playlist) : 0 }}
                    @else
                        0
                    @endisset
                    곡
                </small>
            </div>
        </div>

        <div class="content">
            <ul id="songList">
                @isset($playlist)
                @foreach ($playlist as $index => $song)
    <div class="alert {{ $index === 0 ? 'alert-primary' : 'alert-light' }} song-item d-flex justify-content-between align-items-center py-2 px-3 my-1"
         onclick="playSong({{ $index }})" style="cursor: pointer;" id="song-{{ $index }}">
        <div>
            <strong>{{ $song->title }}</strong><br>
            <small>{{ $song->channel }}</small>
        </div>
        <span class="badge bg-secondary">{{ $song->play_count }}회</span>
    </div>
@endforeach
                @endisset
            </ul>
        </div>
    </div>

    <div id="audioPlayerContainer">
        <div id="audioInfo">
            <p id="songTitle"></p>
        </div>
        <audio id="audioPlayer" controls preload="metadata">
            Your browser does not support the audio element.
        </audio>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('CSS/jquery-3.6.4.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const playlist = @json(collect($playlist ?? [])->map(fn($s) => (array) $s)->toArray());
            if (playlist.length === 0) return;

            let currentSongIndex = 0;
            const audioPlayer = document.getElementById('audioPlayer');
            const songSpans = document.querySelectorAll('ul li span');
            const songTitle = document.getElementById('songTitle');

            if (!audioPlayer) return;

            // 노래 재생 함수
            window.playSong = function (index) {
    if (!playlist[index]) return;

    let song = playlist[index];
    let audioSrc = '/music/' + encodeURIComponent(song.title) + '.mp3';
    let fullAudioSrc = location.origin + audioSrc;

    if (audioPlayer.src !== fullAudioSrc) {
        audioPlayer.src = audioSrc;
    }

    audioPlayer.play()
        .then(() => console.log("✅ 재생됨:", song.title))
        .catch(e => console.error("❌ 재생 오류:", e));

    // 모든 song-item에서 alert-primary 제거, alert-light 추가
    document.querySelectorAll('.song-item').forEach(el => {
        el.classList.remove('alert-primary');
        el.classList.add('alert-light');
    });

    // 현재 곡만 alert-primary로
    const currentItem = document.getElementById(`song-${index}`);
    if (currentItem) {
        currentItem.classList.remove('alert-light');
        currentItem.classList.add('alert-primary');
    }

    // 제목 표시
    songTitle.innerText = song.title;
    currentSongIndex = index;
    document.title = `${song.title} - ${song.channel}`;
};


            // 다음 곡 재생 함수
            window.playNext = function () {
                currentSongIndex = (currentSongIndex + 1) % playlist.length;
                window.playSong(currentSongIndex);
            };

            // 노래가 끝나면 다음 곡 재생
            audioPlayer.addEventListener('ended', function () {
                window.playNext();
            });

            // 클릭 시, 재생
            songSpans.forEach((span, index) => {
                span.addEventListener('click', function () {
                    window.playSong(index);
                });
            });

            // 접속 시 첫 곡 재생
            window.playSong(0);

            // 재생목록 업데이트 버튼 기능
            document.getElementById("updateButton").addEventListener("click", function () {
                var xhr = new XMLHttpRequest();
                xhr.open("GET", "/update-playlist", true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        var response = xhr.responseText;
                        alert(response);
                        console.log("🔁 서버 응답:", response);
                        location.reload(); // 페이지 새로고침으로 갱신 반영
                    }
                };
                xhr.send();
            });
        });
    </script>
@endpush
