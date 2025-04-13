{{-- welcome.blade.php --}}
@extends('layouts.app')

@section('title', '상재의 노래주머니')

@push('styles')
<link rel="stylesheet" href="{{ asset('CSS/music.css?r=3') }}">
<link rel="stylesheet" href="{{ asset('CSS/bootstrap.css?r=2') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
      integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA=="
      crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.0/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
      crossorigin="anonymous">
@endpush

@section('content')
<!-- 사이드 메뉴 -->
<div id="sideMenu">
    <ul>
        <li onclick="showMain()">메인</li>
        <li onclick="showFavorites()">찜 곡</li>
        <li onclick="updatePlaylist()">재생목록 업데이트</li>
    </ul>
</div>

<div id="playlistContainer">
    <div class="header mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="menu-toggle" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </div>

            <div class="search-area ml-auto d-flex align-items-center">
                <input type="text" id="searchInput" class="form-control" placeholder="노래 제목을 검색하세요!" autocomplete="off">
                <button id="searchToggle" class="btn search-icon">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="mt-2 text-muted">
            전체 곡 개수: <strong>{{ isset($playlist) && is_countable($playlist) ? count($playlist) : 0 }} 곡</strong>
        </div>
    </div>

    <div class="content">
        <ul id="songList" class="list-unstyled"></ul>
    </div>
</div>

<div id="audioPlayerContainer">
  <div id="audioInfo" class="d-flex align-items-center">
    <img id="coverImage" src="" alt="커버 이미지">
    <div id="songDetails">
      <p id="songTitle" class="mb-0">재생 중인 곡 없음</p>
    </div>
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
    let playlist = @json(collect($playlist ?? [])->map(fn($s) => (array) $s)->toArray());
    const fullPlaylist = [...playlist];
    const mappedChannels = @json($mappedChannels);
    const favoritedIndexes = @json($favorites ?? []).map(i => Number(i));

    let searchResults = null;
    let currentSongIndex = 0;

    const audioPlayer = document.getElementById('audioPlayer');
    const songTitle = document.getElementById('songTitle');
    const songList = document.getElementById('songList');
    const searchInput = document.querySelector('#searchInput');
    const coverImage = document.getElementById('coverImage');

    function renderSongs(filteredPlaylist) {
    songList.innerHTML = '';

    filteredPlaylist.forEach((song) => {
        const originalIndex = playlist.findIndex(s =>
            s.title === song.title && s.channel === song.channel
        );

        const songDiv = document.createElement('div');
        songDiv.className = 'alert alert-light song-item d-flex justify-content-between align-items-center py-2 px-3 my-1';
        songDiv.style.cursor = 'pointer';
        songDiv.id = `song-${originalIndex}`;
        songDiv.onclick = () => playSong(originalIndex);

        // 왼쪽: 텍스트 + 버튼
        const leftDiv = document.createElement('div');
        leftDiv.classList.add('d-flex', 'align-items-center', 'flex-grow-1');

        const favoriteBtn = document.createElement('button');
        favoriteBtn.className = 'favorite-btn';
        favoriteBtn.innerHTML = '<i class="fas fa-star"></i>';
        favoriteBtn.classList.toggle('active', favoritedIndexes.includes(+song.index));
        favoriteBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            fetch('/toggle-favorite', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ index: song.index })
            }).then(res => res.json())
              .then(data => {
                  favoriteBtn.classList.toggle('active', data.favorited);
              });
        });

        const infoDiv = document.createElement('div');

        const titleDiv = document.createElement('div');
        titleDiv.style.width = '300px';
        titleDiv.style.textOverflow = 'ellipsis';
        titleDiv.style.overflow = 'hidden';
        titleDiv.style.whiteSpace = 'nowrap';

        const title = document.createElement('strong');
        title.textContent = song.title;
        titleDiv.appendChild(title);

        const badge = document.createElement('span');
        badge.className = 'badge badge-secondary';
        badge.innerText = `${song.play_count}회`;
        badge.style.fontSize = '0.65rem';
        badge.style.borderRadius = '5px';
        badge.style.marginRight = '10px';
        badge.style.backgroundColor = '#6c757d';
        badge.style.color = 'white';

        const channel = document.createElement('small');
        channel.textContent = song.channel;
        channel.style.verticalAlign = 'middle';

        infoDiv.appendChild(titleDiv);
        infoDiv.appendChild(badge);
        infoDiv.appendChild(channel);

        leftDiv.appendChild(favoriteBtn);
        leftDiv.appendChild(infoDiv);

        // 오른쪽: 썸네일
        const rightDiv = document.createElement('div');

        const thumbnail = document.createElement('img');
        thumbnail.src = `https://img.youtube.com/vi/${song.videoID}/hqdefault.jpg`;
        thumbnail.alt = `${song.title} 썸네일`;
        thumbnail.style.width = '50px';
        thumbnail.style.height = '50px';
        thumbnail.style.borderRadius = '5px';
        thumbnail.style.objectFit = 'cover';
        thumbnail.style.display = 'block';
        thumbnail.style.marginLeft = '10px';

        rightDiv.appendChild(thumbnail);

        // songDiv 조립
        songDiv.appendChild(leftDiv);
        songDiv.appendChild(rightDiv);
        songList.appendChild(songDiv);
    });
}


    window.playSong = function (index) {
        if (!playlist[index]) return;
        const song = playlist[index];
        currentSongIndex = index;

        const thumbnailUrl = `https://img.youtube.com/vi/${song.videoID}/hqdefault.jpg`;
        coverImage.src = thumbnailUrl;

        const audioSrc = '/music/' + encodeURIComponent(song.title) + '.mp3';
        if (audioPlayer.src !== location.origin + audioSrc) {
            audioPlayer.src = audioSrc;
        }
        audioPlayer.play().then(() => console.log("🎵 재생:", song.title));
        songTitle.innerText = song.title;
        document.title = `${song.title} - ${song.channel}`;

        document.querySelectorAll('.song-item').forEach(item => {
            item.classList.remove('current-song');
        });
        const currentDiv = document.getElementById(`song-${index}`);
        if (currentDiv) {
            currentDiv.classList.add('current-song');
        }

        // ✅ Media Session API 설정
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: song.title,
                artist: song.channel,
                album: '상재의 노래주머니',
                artwork: [
                    { src: thumbnailUrl, sizes: '512x512', type: 'image/jpeg' }
                ]
            });

            navigator.mediaSession.setActionHandler('previoustrack', () => {
                if (currentSongIndex > 0) playSong(currentSongIndex - 1);
            });
            navigator.mediaSession.setActionHandler('nexttrack', () => {
                playNext();
            });
        }

        fetch('/update-play-count', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ index: song.index })
        })
        .then(res => res.ok ? res.json() : Promise.reject("서버 응답 오류"))
        .then(data => {
            const badge = document.querySelector(`#song-${index} .badge`);
            if (badge) {
                const currentCount = parseInt(badge.innerText.replace(/\D/g, '')) || 0;
                badge.innerText = `${currentCount + 1}회`;
            }
        })
        .catch(err => console.error('❌ 재생 수 업데이트 실패:', err));
    };

    window.playNext = function () {
        currentSongIndex++;
        if (currentSongIndex >= playlist.length) {
            location.reload();
            return;
        }
        window.playSong(currentSongIndex);
    };

    audioPlayer.addEventListener('ended', window.playNext);

    window.toggleMenu = function () {
        const menu = document.getElementById("sideMenu");
        menu.classList.toggle("active");
        document.body.classList.toggle("menu-open", menu.classList.contains("active"));
    };

    window.showMain = function () {
        playlist = [...fullPlaylist];
        renderSongs(playlist);
        toggleMenu();
    };

    window.showFavorites = function () {
        fetch('/favorites')
            .then(res => res.json())
            .then(latestFavorites => {
                playlist = fullPlaylist.filter(song => latestFavorites.includes(Number(song.index)));
                favoritedIndexes.length = 0;
                latestFavorites.forEach(i => favoritedIndexes.push(i));
                renderSongs(playlist);
                toggleMenu();
            });
    };

    window.updatePlaylist = function () {
        alert("🔄 재생목록을 업데이트 중입니다...");
        fetch("/update-playlist")
            .then(res => res.text())
            .then(response => {
                alert("✅ 업데이트 완료!\n\n" + response);
            });
        toggleMenu();
    };

    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            const searchQuery = e.target.value.trim();
            if (searchQuery.length === 0) {
                searchResults = null;
                playlist = [...fullPlaylist];
                renderSongs(playlist);
                return;
            }

            fetch(`/search?q=${encodeURIComponent(searchQuery)}`)
                .then(res => res.json())
                .then(results => {
                    searchResults = results.map(result => {
                        return fullPlaylist.find(song => song.index == result.index_number);
                    }).filter(Boolean);
                    playlist = searchResults;
                    renderSongs(playlist);
                });
        });
    }

    renderSongs(playlist);
    window.playSong(0);
});

document.getElementById('searchToggle').addEventListener('click', function () {
    const input = document.getElementById('searchInput');
    input.classList.toggle('active');
    if (input.classList.contains('active')) {
        input.focus();
    }
});

document.addEventListener('click', function (e) {
    const input = document.getElementById('searchInput');
    const toggle = document.getElementById('searchToggle');
    if (!input.contains(e.target) && !toggle.contains(e.target)) {
        input.classList.remove('active');
    }
});
</script>
@endpush
