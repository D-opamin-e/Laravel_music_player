{{-- welcome.blade.php --}}
@extends('layouts.app')

@section('title', '상재의 노래주머니')

@push('styles')
<link rel="stylesheet" href="{{ asset('CSS/music.css') }}">
<link rel="stylesheet" href="{{ asset('CSS/bootstrap.css?r=2') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.0/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
@endpush

@section('content')
<div id="sideMenu">
    <ul>
        <li onclick="window.showMain()">메인</li>
        <li onclick="window.showFavorites()">찜 곡</li>
        <li onclick="window.updatePlaylist()">재생목록 업데이트</li>
    </ul>
</div>

<div id="playlistContainer">
    <div class="header mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="menu-toggle" onclick="window.toggleMenu()">
                <i class="fas fa-bars"></i>
            </div>
            <div class="search-area ml-auto d-flex align-items-center">
                <input type="text" id="searchInput" class="form-control" placeholder="노래 제목을 검색하세요!" autocomplete="off">
                <button id="searchToggle" class="btn search-icon"><i class="fas fa-search"></i></button>
            </div>
        </div>
        <div class="mt-2 text-muted">
            전체 곡 개수: <strong id="totalSongCount">{{ isset($playlist) && is_countable($playlist) ? count($playlist) : 0 }} 곡</strong>
            <span id="displayedSongCountInfo" style="margin-left: 10px;"></span>
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

<div id="fullscreenPlayer" style="display: none;">
  <div class="fullscreen-wrapper">
    <img id="fullscreenCover" src="" alt="Cover" />
    <div class="song-details">
      <h2 id="fullscreenTitle">제목</h2>
      <p id="fullscreenArtist">아티스트</p>
    </div>
    <div class="time-bar">
      <span id="currentTime">0:00</span>
      <input type="range" id="seekBar" min="0" max="100" value="0" />
      <span id="duration">0:00</span>
    </div>
    <div class="controls new-style">
      <button class="control-btn fullscreen-prev"><i class="fas fa-step-backward"></i></button>
      <button class="control-btn main-btn fullscreen-playpause"><i class="fas fa-play"></i></button>
      <button class="control-btn fullscreen-next"><i class="fas fa-step-forward"></i></button>
      <button class="close-fullscreen-btn" id="closeFullscreenBtn"><i class="fas fa-times"></i></button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('CSS/jquery-3.6.4.js') }}"></script>
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialPlaylist = @json(collect($playlist ?? [])->map(fn($s) => (array) $s)->toArray());
    window.playlist = initialPlaylist;
    let displayedSongs = [...window.playlist];
    window.currentPlayingSong = null;
    let currentSongOriginalIndex = -1;

    const mappedChannels = @json($mappedChannels);
    const favoritedIndexes = new Set(@json($favorites ?? []).map(i => Number(i)));

    const audioPlayer = document.getElementById('audioPlayer');
    const songTitle = document.getElementById('songTitle');
    const songList = document.getElementById('songList');
    const searchInput = document.querySelector('#searchInput');
    const coverImage = document.getElementById('coverImage');
    const totalSongCountEl = document.getElementById('totalSongCount');
    const displayedSongCountInfoEl = document.getElementById('displayedSongCountInfo');

    function renderSongs(songsToRender) {
        songList.innerHTML = '';

        if (displayedSongCountInfoEl) {
            displayedSongCountInfoEl.textContent = (songsToRender.length === window.playlist.length)
                ? ''
                : `(검색 결과: ${songsToRender.length} 곡)`;
        }

        songsToRender.forEach((song) => {
            const originalIndex = window.playlist.findIndex(s => s.index === song.index); // 고유 ID로 song.index 사용 가정

            if (originalIndex === -1) {
                console.error("곡에 대한 원본 index를 찾을 수 없습니다:", song.title, song.index);
                return;
            }

            const songDiv = document.createElement('div');
            songDiv.className = 'alert alert-light song-item d-flex justify-content-between align-items-center py-2 px-3 my-1';
            songDiv.style.cursor = 'pointer';
            songDiv.id = `song-${originalIndex}`;
            songDiv.onclick = () => window.playSong(originalIndex);

            const leftDiv = document.createElement('div');
            leftDiv.classList.add('d-flex', 'align-items-center', 'flex-grow-1', 'mr-2');

            const favoriteBtn = document.createElement('button');
            favoriteBtn.className = 'favorite-btn btn btn-sm mr-2';
            favoriteBtn.innerHTML = '<i class="far fa-star"></i>';
            if (favoritedIndexes.has(Number(song.index))) {
                favoriteBtn.classList.add('active');
                favoriteBtn.innerHTML = '<i class="fas fa-star"></i>';
            }
            favoriteBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleFavorite(song.index, favoriteBtn);
            });

            const infoDiv = document.createElement('div');
            infoDiv.style.overflow = 'hidden';

            const titleDiv = document.createElement('div');
            titleDiv.style.textOverflow = 'ellipsis';
            titleDiv.style.overflow = 'hidden';
            titleDiv.style.whiteSpace = 'nowrap';
            titleDiv.style.maxWidth = '250px';
            const title = document.createElement('strong');
            title.textContent = song.title;
            titleDiv.appendChild(title);

            const metaDiv = document.createElement('div');
            metaDiv.style.fontSize = '0.8em';
            metaDiv.style.color = '#6c757d';

            const badge = document.createElement('span');
            badge.className = 'badge badge-secondary mr-2';
            badge.innerText = `${song.play_count || 0}회`;
            badge.style.fontSize = '0.9em';
            metaDiv.appendChild(badge);

            const channel = document.createElement('small');
            channel.textContent = song.channel;
            metaDiv.appendChild(channel);

            infoDiv.appendChild(titleDiv);
            infoDiv.appendChild(metaDiv);

            leftDiv.appendChild(favoriteBtn);
            leftDiv.appendChild(infoDiv);

            const rightDiv = document.createElement('div');
            rightDiv.style.flexShrink = '0';

            const thumbnail = document.createElement('img');
            thumbnail.src = `https://i.ytimg.com/vi/${song.videoID}/hqdefault.jpg`;
            thumbnail.alt = `${song.title} 썸네일`;
            thumbnail.style.width = '50px';
            thumbnail.style.height = '50px';
            thumbnail.style.borderRadius = '5px';
            thumbnail.style.objectFit = 'cover';
            thumbnail.style.display = 'block';
            thumbnail.onerror = function() {
                this.src = '/images/default_thumbnail.png'; // 실제 기본 이미지 경로로 변경하세요
                console.warn(`썸네일 로드 실패 (videoID: ${song.videoID})`);
            };

            rightDiv.appendChild(thumbnail);

            songDiv.appendChild(leftDiv);
            songDiv.appendChild(rightDiv);

            if (originalIndex === currentSongOriginalIndex) {
                songDiv.classList.add('current-song');
            }

            songList.appendChild(songDiv);
        });
    }

    window.playSong = function (index) {
        if (index < 0 || index >= window.playlist.length) {
            console.error('⛔ 잘못된 인덱스:', index);
            return;
        }

        const song = window.playlist[index];

        currentSongOriginalIndex = index;
        window.currentPlayingSong = song;

        const thumbnailUrl = `https://i.ytimg.com/vi/${song.videoID}/hqdefault.jpg`;
        coverImage.src = thumbnailUrl;
        coverImage.onerror = function() { this.src = '/images/default_thumbnail.png'; }; // 실제 기본 이미지 경로
        songTitle.innerText = song.title;
        document.title = `${song.title} - ${song.channel}`;

        const audioSrc = '/music/' + encodeURIComponent(song.title) + '.mp3';
        const fullSrc = location.origin + audioSrc;

        if (audioPlayer.src !== fullSrc) {
            audioPlayer.src = fullSrc;
        }

        const playPromise = audioPlayer.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                updatePlayCount_ImmediateUI(song.index, currentSongOriginalIndex);

                if (document.getElementById('fullscreenPlayer').style.display === 'flex' && window.updateFullscreenUIIfNeeded) {
                    window.updateFullscreenUIIfNeeded(song);
                }
            }).catch(err => {
                console.error(`❌ '${song.title}' 재생 실패:`, err);
            });
        }

        document.querySelectorAll('.song-item.current-song').forEach(item => {
            item.classList.remove('current-song');
        });
        const currentDiv = document.getElementById(`song-${index}`);
        if (currentDiv) {
            currentDiv.classList.add('current-song');
        }

        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: song.title,
                artist: song.channel,
                album: '상재의 노래주머니',
                artwork: [{ src: thumbnailUrl, sizes: '512x512', type: 'image/jpeg' }]
            });

            navigator.mediaSession.setActionHandler('previoustrack', playPrevious); // 함수명 직접 사용
            navigator.mediaSession.setActionHandler('nexttrack', window.playNext); // 함수명 직접 사용
            navigator.mediaSession.setActionHandler('play', () => audioPlayer.play().catch(e=>console.error("미디어 세션 재생 오류:", e)));
            navigator.mediaSession.setActionHandler('pause', () => audioPlayer.pause());
        }
    };

    function playPrevious() {
        if (!window.currentPlayingSong || displayedSongs.length === 0) return;

        const currentDisplayedIndex = displayedSongs.findIndex(song => song.index === window.currentPlayingSong.index);
        if (currentDisplayedIndex === -1) return;

        let prevDisplayedIndex = currentDisplayedIndex - 1;
        if (prevDisplayedIndex < 0) {
            prevDisplayedIndex = displayedSongs.length - 1; // 목록 순환
        }

        const prevSongObject = displayedSongs[prevDisplayedIndex];
        const prevOriginalIndex = window.playlist.findIndex(song => song.index === prevSongObject.index);
        if (prevOriginalIndex === -1) return;

        window.playSong(prevOriginalIndex);
    };

    window.playNext = function () {
        if (!window.currentPlayingSong || displayedSongs.length === 0) {
            // 재생 중지 및 UI 초기화
            window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
            audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
            renderSongs(displayedSongs);
            return;
        }

        const currentDisplayedIndex = displayedSongs.findIndex(song => song.index === window.currentPlayingSong.index);
        if (currentDisplayedIndex === -1) {
             // 재생 중지 및 UI 초기화
            window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
            audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
            renderSongs(displayedSongs);
            return;
        }

        let nextDisplayedIndex = currentDisplayedIndex + 1;

        if (nextDisplayedIndex >= displayedSongs.length) {
            // ✅ 검색 곡의 마지막 재생 완료시 페이지 새로고침 실행
            location.reload();
            return;
        }

        const nextSongObject = displayedSongs[nextDisplayedIndex];
        const nextOriginalIndex = window.playlist.findIndex(song => song.index === nextSongObject.index);

        if (nextOriginalIndex === -1) {
             // 재생 중지 및 UI 초기화
            window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
            audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
            renderSongs(displayedSongs);
            return;
        }

        window.playSong(nextOriginalIndex);
    };

    // 오디오 재생 완료 시 다음 곡 자동 재생
    audioPlayer.addEventListener('ended', window.playNext);

    function toggleFavorite(songIndex, buttonElement) {
        fetch('/toggle-favorite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ index: songIndex })
        })
        .then(res => {
            if (!res.ok) throw new Error(`서버 응답 오류: ${res.status}`);
            const contentType = res.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                 return res.json();
            } else {
                 throw new Error('잘못된 서버 응답 형식 (JSON 필요)');
            }
        })
        .then(data => {
            if (data && typeof data.status === 'string') {
                const numSongIndex = Number(songIndex);
                if (data.status === "added") {
                    favoritedIndexes.add(numSongIndex);
                    buttonElement.classList.add('active');
                    buttonElement.innerHTML = '<i class="fas fa-star"></i>';
                } else if (data.status === "removed") {
                    favoritedIndexes.delete(numSongIndex);
                    buttonElement.classList.remove('active');
                    buttonElement.innerHTML = '<i class="far fa-star"></i>';
                } else {
                    console.error('알 수 없는 상태값:', data.status);
                    alert('찜 상태 변경 중 알 수 없는 응답을 받았습니다.');
                }
            } else {
                 console.error('서버 응답에 status 문자열 없음:', data);
                 alert('찜 상태 변경 중 서버 응답 형식 오류가 발생했습니다.');
            }
        })
        .catch(error => {
            console.error('❌ 찜 토글 실패:', error);
            alert('찜 상태 변경에 실패했습니다.');
        });
    }

    function updatePlayCount_ImmediateUI(songUniqueIndex, originalIndex) {
        if (typeof songUniqueIndex === 'undefined' || originalIndex < 0) return;

        const songDiv = document.getElementById(`song-${originalIndex}`);
        let localCount = 0;
        let targetSongInData = null;
        let badgeElement = null; // 뱃지 요소 저장용

        if (songDiv) {
            badgeElement = songDiv.querySelector('.badge'); // 뱃지 요소 찾기
            if (badgeElement) {
                const currentCountText = badgeElement.innerText || '0회';
                localCount = (parseInt(currentCountText.replace(/\D/g, '')) || 0) + 1;
                badgeElement.innerText = `${localCount}회`; // 즉시 업데이트

                targetSongInData = window.playlist.find(s => s.index == songUniqueIndex);
                 if (targetSongInData) targetSongInData.play_count = localCount; // 데이터 모델 업데이트
            }
        }

        fetch('/update-play-count', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ index: songUniqueIndex })
        })
        .then(res => {
            if (!res.ok) {
                console.error(`재생 수 업데이트 서버 오류 (${res.status}), 인덱스: ${songUniqueIndex}.`);
                // 서버 오류 시 UI 롤백
                if (badgeElement && badgeElement.innerText === `${localCount}회`) {
                    badgeElement.innerText = `${localCount - 1}회`;
                }
                if (targetSongInData && targetSongInData.play_count === localCount) {
                     targetSongInData.play_count = localCount - 1;
                }
                throw new Error(`서버 응답 오류: ${res.status}`);
            }
            const contentType = res.headers.get("content-type");
             if (contentType && contentType.indexOf("application/json") !== -1) {
                return res.json();
             } else {
                return null; // JSON 아니면 null 반환
             }
        })
        .then(data => {
             // 성공 시 추가 작업 없음 
        })
        .catch(error => {
            // fetch 실패 또는 서버 오류 시 로그
            console.error(`❌ 재생 수 업데이트 처리 실패:`, error.message);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            const searchQuery = e.target.value.trim().toLowerCase();

            if (searchQuery.length === 0) {
                displayedSongs = [...window.playlist];
                renderSongs(displayedSongs);
                return;
            }

            fetch(`/search?q=${encodeURIComponent(searchQuery)}`)
                .then(res => {
                    if (!res.ok) throw new Error(`검색 실패: ${res.status}`);
                    return res.json();
                 })
                .then(results => {
                    displayedSongs = results.map(result => {
                        return window.playlist.find(song => song.index == result.index_number);
                    }).filter(Boolean);
                    renderSongs(displayedSongs);
                })
                .catch(error => {
                    console.error('❌ 검색 요청 실패:', error);
                    songList.innerHTML = '<li class="text-danger">검색 중 오류가 발생했습니다.</li>';
                });
        });
     }

    window.toggleMenu = function () {
        document.getElementById("sideMenu").classList.toggle("active");
    };

    window.showMain = function () {
        displayedSongs = [...window.playlist];
        renderSongs(displayedSongs);
        searchInput.value = '';
        toggleMenu();
     };

    window.showFavorites = function () {
        displayedSongs = window.playlist.filter(song => favoritedIndexes.has(Number(song.index)));
        renderSongs(displayedSongs);
        searchInput.value = '';
        toggleMenu();
    };

    window.updatePlaylist = function () {
        alert("🔄 재생목록을 업데이트 중입니다...");
        toggleMenu();
        fetch("/update-playlist")
            .then(res => {
                if (!res.ok) throw new Error(`서버 응답 오류: ${res.status}`);
                return res.text();
            })
            .then(response => {
                alert("✅ 업데이트 완료! 페이지를 새로고침합니다.\n" + (response || ''));
                location.reload();
            })
            .catch(error => {
                console.error('❌ 재생목록 업데이트 실패:', error);
                alert('재생목록 업데이트 중 오류가 발생했습니다.\n' + error.message);
            });
    };

    renderSongs(displayedSongs);
    window.playSong(0); // 페이지 로드 시 첫 곡 자동 재생
    if (totalSongCountEl) {
        totalSongCountEl.textContent = `${window.playlist.length} 곡`;
    }

}); // DOMContentLoaded 끝

document.getElementById('searchToggle')?.addEventListener('click', function () {
    const input = document.getElementById('searchInput');
    input?.classList.toggle('active');
    if (input?.classList.contains('active')) input.focus();
});

document.addEventListener('click', function (e) {
    const input = document.getElementById('searchInput');
    const toggle = document.getElementById('searchToggle');
    if (input && toggle && !input.contains(e.target) && !toggle.contains(e.target) && !e.target.closest('.search-area')) {
        input.classList.remove('active');
    }
});
</script>

<script src="{{ asset('js/player-ui.js') }}"></script>
@endpush