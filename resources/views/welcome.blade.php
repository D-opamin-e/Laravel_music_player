@extends('layouts.app')

@section('title', '상재의 노래주머니')

@push('styles')
    <link rel="stylesheet" href="{{ asset('CSS/music.css?r=2') }}">
    <link rel="stylesheet" href="{{ asset('CSS/bootstrap.css?r=2') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
          integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.0/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
          crossorigin="anonymous">
    <style>
        .content {
            padding-top: 70px;
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
                    {{ isset($playlist) && is_countable($playlist) ? count($playlist) : 0 }} 곡
                </small>
            </div>
        </div>

        <div class="content">
            <ul id="songList"></ul>
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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const playlist = @json(collect($playlist ?? [])->map(fn($s) => (array) $s)->toArray());
            const mappedChannels = @json($mappedChannels);
            if (!playlist.length) return;

            let currentSongIndex = 0;
            const audioPlayer = document.getElementById('audioPlayer');
            const songTitle = document.getElementById('songTitle');
            const songList = document.getElementById('songList');
            const searchInput = document.querySelector('#searchInput');

            if (!audioPlayer) return;

            window.playSong = function (index) {
                if (!playlist[index]) return;

                const song = playlist[index];
                const audioSrc = '/music/' + encodeURIComponent(song.title) + '.mp3';
                const fullAudioSrc = location.origin + audioSrc;

                if (audioPlayer.src !== fullAudioSrc) {
                    audioPlayer.src = audioSrc;
                }

                audioPlayer.play()
                    .then(() => console.log("✅ 재생됨:", song.title))
                    .catch(e => console.error("❌ 재생 오류:", e));

                document.querySelectorAll('.song-item').forEach(el => {
                    el.classList.remove('alert-primary');
                    el.classList.add('alert-light');
                });

                const currentItem = document.getElementById(`song-${index}`);
                if (currentItem) {
                    currentItem.classList.remove('alert-light');
                    currentItem.classList.add('alert-primary');
                }

                songTitle.innerText = song.title;
                currentSongIndex = index;
                document.title = `${song.title} - ${song.channel}`;

                fetch('/update-play-count', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ index: song.index })
                })
                .then(res => {
                    if (!res.ok) throw new Error("서버 응답 오류");
                    return res.json();
                })
                .then(data => {
                    console.log(data.message);
                })
                .catch(err => {
                    console.error('❌ 재생 수 업데이트 실패:', err);
                });
            };

            window.playNext = function () {
                currentSongIndex = (currentSongIndex + 1) % playlist.length;
                window.playSong(currentSongIndex);
            };

            audioPlayer.addEventListener('ended', window.playNext);

            function renderSongs(filteredPlaylist) {
                songList.innerHTML = '';

                filteredPlaylist.forEach((song) => {
                    const originalIndex = playlist.findIndex(s =>
                        s.title === song.title && s.channel === song.channel
                    );

                    const songDiv = document.createElement('div');
                    songDiv.className = `alert alert-light song-item d-flex justify-content-between align-items-center py-2 px-3 my-1`;
                    songDiv.style.cursor = 'pointer';
                    songDiv.id = `song-${originalIndex}`;
                    songDiv.onclick = () => playSong(originalIndex);

                    const infoDiv = document.createElement('div');
                    infoDiv.innerHTML = `<strong>${song.title}</strong><br><small>${song.channel}</small>`;

                    const badgeSpan = document.createElement('span');
                    badgeSpan.className = 'badge bg-secondary';
                    badgeSpan.innerText = `${song.play_count}회`;

                    songDiv.appendChild(infoDiv);
                    songDiv.appendChild(badgeSpan);
                    songList.appendChild(songDiv);
                });
            }

            // ✅ 검색 이벤트 핸들링 추가
            if (searchInput) {
                searchInput.addEventListener('input', function (e) {
                    const searchQuery = e.target.value.trim();

                    if (searchQuery.length === 0) {
                        renderSongs(playlist);
                        return;
                    }

                    fetch(`/search?q=${encodeURIComponent(searchQuery)}`)
                        .then(res => res.json())
                        .then(results => {
                            const filtered = results.map(result => {
                                return playlist.find(song => song.index === result.index_number);
                            }).filter(Boolean);

                            renderSongs(filtered);
                        })
                        .catch(err => {
                            console.error("검색 요청 실패:", err);
                        });
                });
            }

            document.getElementById("updateButton").addEventListener("click", function () {
                fetch("/update-playlist")
                    .then(res => res.text())
                    .then(response => {
                        alert(response);
                        console.log("🔁 서버 응답:", response);
                        location.reload();
                    });
            });

            renderSongs(playlist);
            window.playSong(0);
        });
    </script>
@endpush
