{{-- welcome.blade.php --}}
@extends('layouts.app')

@section('title', '상재의 노래주머니')

@push('styles')
<link rel="stylesheet" href="{{ asset('CSS/music.css?r=4') }}">
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
    <div class="account-links">
        @guest
            <a href="{{ route('login') }}" class="sidebar-account-btn">
                 <i class="fas fa-sign-in-alt"></i> 로그인
            </a>
            <a href="{{ route('register') }}" class="sidebar-account-btn">
                 <i class="fas fa-user-plus"></i> 회원가입
            </a>
        @else
            <span class="logged-in-user">안녕하세요, {{ Auth::user()->name }}님!</span>
            <a href="{{ route('logout') }}" class="sidebar-account-btn"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="fas fa-sign-out-alt"></i> 로그아웃
            </a>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        @endguest
    </div>
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
    window.playlist = initialPlaylist; // 전체 플레이리스트 원본 데이터
    let displayedSongs = [...window.playlist]; // 화면에 현재 표시되는 노래 목록 (검색/찜 등에 따라 변경됨)
    window.currentPlayingSong = null; // 현재 재생 중인 노래 객체 (window.playlist 내의 객체)
    let currentSongOriginalIndex = -1; // 현재 재생 중인 노래의 window.playlist 배열 인덱스

    const mappedChannels = @json($mappedChannels);
    // 찜 목록 Set (song.index, 즉 DB 고유 번호 사용)
    const favoritedIndexes = new Set(@json($favorites ?? []).map(i => Number(i)));

    const audioPlayer = document.getElementById('audioPlayer');
    const songTitle = document.getElementById('songTitle');
    const songList = document.getElementById('songList');
    const searchInput = document.querySelector('#searchInput');
    const coverImage = document.getElementById('coverImage');
    const totalSongCountEl = document.getElementById('totalSongCount');
    const displayedSongCountInfoEl = document.getElementById('displayedSongCountInfo');

    /**
     * 주어진 노래 목록을 화면에 렌더링합니다.
     * @param {Array} songsToRender 화면에 표시할 노래 객체 배열
     */
    function renderSongs(songsToRender) {
        songList.innerHTML = '';

        // 표시되는 곡 수 정보 업데이트 (전체 목록과 다를 경우에만 표시)
        if (displayedSongCountInfoEl) {
            displayedSongCountInfoEl.textContent = (songsToRender.length === window.playlist.length)
                ? ''
                : `검색 결과: ${songsToRender.length} 곡`;
        }

        songsToRender.forEach((song) => {
            // 현재 표시할 곡(song)이 원본 플레이리스트(window.playlist)에서 몇 번째인지 찾음
            const originalIndex = window.playlist.findIndex(s => s.index === song.index);

            if (originalIndex === -1) {
                console.error("renderSongs: 곡에 대한 원본 index를 찾을 수 없습니다:", song.title, song.index);
                return;
            }

            const songDiv = document.createElement('div');
            songDiv.className = 'alert alert-light song-item d-flex justify-content-between align-items-center py-2 px-3 my-1';
            songDiv.style.cursor = 'pointer';
            songDiv.id = `song-${originalIndex}`; // 식별을 위해 원본 인덱스를 ID로 사용
            songDiv.onclick = () => window.playSong(originalIndex); // 재생 함수 호출 시 원본 인덱스 전달

            const leftDiv = document.createElement('div');
            leftDiv.classList.add('d-flex', 'align-items-center', 'flex-grow-1', 'mr-2');

            const favoriteBtn = document.createElement('button');
            favoriteBtn.className = 'favorite-btn btn btn-sm mr-2';
            favoriteBtn.innerHTML = '<i class="far fa-star"></i>';
            // 찜 상태 확인 시 song.index (DB 고유 번호) 사용
            if (favoritedIndexes.has(Number(song.index))) {
                favoriteBtn.classList.add('active');
                favoriteBtn.innerHTML = '<i class="fas fa-star"></i>';
            }
            favoriteBtn.addEventListener('click', function (e) {
                e.stopPropagation(); // 이벤트 버블링 방지 (songDiv의 onclick 실행 안 되게)
                // 찜 토글 함수 호출 시 song.index (DB 고유 번호) 전달
                toggleFavorite(song.index, favoriteBtn);
            });

            const infoDiv = document.createElement('div');
            infoDiv.style.overflow = 'hidden';

            const titleDiv = document.createElement('div');
            titleDiv.style.textOverflow = 'ellipsis';
            titleDiv.style.overflow = 'hidden';
            titleDiv.style.whiteSpace = 'nowrap';
            titleDiv.style.maxWidth = '250px'; // 제목 최대 너비 제한
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
            rightDiv.style.flexShrink = '0'; // 오른쪽 요소 크기 고정

            const thumbnail = document.createElement('img');
            thumbnail.src = `https://i.ytimg.com/vi/${song.videoID}/maxresdefault.jpg`; // 유튜브 썸네일
            thumbnail.alt = `${song.title} 썸네일`;
            thumbnail.style.width = '50px';
            thumbnail.style.height = '50px';
            thumbnail.style.borderRadius = '5px';
            thumbnail.style.objectFit = 'cover';
            thumbnail.style.display = 'block';
            thumbnail.onerror = function() {
                this.src = '/images/default_thumbnail.png'; // 기본 이미지 경로
                console.warn(`renderSongs: 썸네일 로드 실패 (videoID: ${song.videoID})`);
            };

            rightDiv.appendChild(thumbnail);

            songDiv.appendChild(leftDiv);
            songDiv.appendChild(rightDiv);

            // 현재 재생 중인 곡이면 강조 클래스 추가
            if (originalIndex === currentSongOriginalIndex) {
                songDiv.classList.add('current-song');
            }

            songList.appendChild(songDiv);
        });
    }

    /**
     * 지정된 인덱스의 곡을 재생합니다.
     * @param {number} index 재생할 곡의 window.playlist 배열 인덱스 (원본 인덱스)
     */
    window.playSong = function (index) {
        if (index < 0 || index >= window.playlist.length) {
            console.error('⛔ playSong: 잘못된 인덱스:', index);
            window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
            audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
            renderSongs(displayedSongs); // 목록 UI 갱신
            return;
        }

        const song = window.playlist[index];

        console.log('▶️ playSong: 곡 재생 시작', {
            playlistIndex: index, // window.playlist 배열 인덱스
            songDBIndex: song.index, // 곡 DB 고유 번호
            title: song.title
        });

        currentSongOriginalIndex = index; // 현재 재생 곡의 원본 인덱스 저장
        window.currentPlayingSong = song; // 현재 재생 곡 객체 저장

        // 썸네일 URL 설정 (백엔드 생성 URL 우선, 없으면 유튜브 URL 사용)
        const finalThumbnailUrl = song.thumbnail_url || `https://i.ytimg.com/vi/${song.videoID}/maxresdefault.jpg`;
        console.log('ℹ️ playSong: 사용할 최종 썸네일 URL:', finalThumbnailUrl);
        coverImage.src = finalThumbnailUrl;
        coverImage.onerror = function() {
            this.src = '/images/default_thumbnail.png'; // 기본 이미지 경로
            console.warn(`playSong: 썸네일 로드 실패 (videoID: ${song.videoID}, 시도 URL: ${finalThumbnailUrl})`);
        };

        songTitle.innerText = song.title; // 플레이어 제목 업데이트
        document.title = `${song.title} - ${song.channel}`; // 브라우저 탭 제목 업데이트

        const audioSrc = '/music/' + encodeURIComponent(song.title) + '.mp3';
        const fullSrc = location.origin + audioSrc;

        // 오디오 소스가 다를 경우에만 변경 및 로드
        if (audioPlayer.src !== fullSrc) {
            audioPlayer.src = fullSrc;
            audioPlayer.load(); // Safari 등 일부 브라우저 호환성 위해 추가
        }

        // 오디오 재생 시도 및 재생 수 업데이트
        const playPromise = audioPlayer.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                // UI 즉시 업데이트 및 서버 요청
                updatePlayCount_ImmediateUI(song.index, currentSongOriginalIndex);
                // 전체 화면 플레이어 UI 업데이트 (열려있을 경우)
                if (document.getElementById('fullscreenPlayer')?.style.display === 'flex' && window.updateFullscreenUIIfNeeded) {
                    window.updateFullscreenUIIfNeeded(song, finalThumbnailUrl);
                }
            }).catch(err => {
                console.error(`❌ 'playSong: '${song.title}' 재생 실패:`, err);
            });
        } else {
            console.warn("playSong: play() 메서드가 Promise를 반환하지 않습니다.");
            // Promise를 반환하지 않아도 재생이 시작될 수 있으므로, 재생 수 업데이트는 별도로 처리할 수도 있음
        }

        // 기존 강조 표시 제거
        document.querySelectorAll('.song-item.current-song').forEach(item => {
            item.classList.remove('current-song');
        });

        // 현재 재생 곡 항목 강조 및 스크롤
        const currentDiv = document.getElementById(`song-${index}`);
        if (currentDiv) {
            currentDiv.classList.add('current-song'); // 강조 클래스 추가

            // 현재 재생 중인 곡 항목으로 자동 스크롤
            const scrollContainer = songList?.parentElement; // 스크롤 가능한 부모 요소 (div.content)
            if (scrollContainer) {
                currentDiv.scrollIntoView({
                    behavior: 'smooth', // 부드러운 스크롤
                    block: 'center'    // 항목이 화면 중앙에 오도록
                });
                console.log(`스크롤: song-${index} (${song.title}) 항목으로 스크롤합니다.`);
            } else {
                console.warn("스크롤: 노래 목록의 스크롤 가능한 부모 요소(div.content)를 찾을 수 없습니다.");
            }
        } else {
            console.warn(`playSong: song-${index} 에 해당하는 HTML 요소를 찾을 수 없습니다.`);
        }

        // === 미디어 세션 API 설정 ===
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: song.title,
                artist: song.channel,
                album: '상재의 노래주머니',
                artwork: [
                    {
                        src: finalThumbnailUrl,
                        sizes: '512x512', // 적절한 크기 명시
                        type: 'image/jpeg' // 이미지 타입 명시
                    }
                ]
            });

            // 미디어 컨트롤 핸들러 설정
            navigator.mediaSession.setActionHandler('previoustrack', playPrevious);
            navigator.mediaSession.setActionHandler('nexttrack', window.playNext);
            navigator.mediaSession.setActionHandler('play', () => audioPlayer.play().catch(e=>console.error("playSong: 미디어 세션 재생 오류 (Play):", e)));
            navigator.mediaSession.setActionHandler('pause', () => audioPlayer.pause());
        }
        // ==========================
    };

    /**
     * 현재 표시된 목록(displayedSongs) 기준으로 이전 곡을 재생합니다.
     */
    function playPrevious() {
        if (!window.currentPlayingSong || displayedSongs.length === 0) {
            console.log('playPrevious: 재생 중인 곡이 없거나 목록이 비어있습니다.');
            return; // 재생할 곡 없음
        }

        // 현재 재생 중인 곡이 displayedSongs 목록에서 몇 번째인지 찾음 (DB 인덱스 기준)
        const currentDisplayedIndex = displayedSongs.findIndex(song => song.index === window.currentPlayingSong.index);

        if (currentDisplayedIndex === -1) {
            console.warn('playPrevious: 현재 재생 중인 곡이 현재 표시된 목록에 없습니다.');
            // 첫 곡을 재생하거나, 재생 중지 등 정책 결정 필요
            if (displayedSongs.length > 0) {
                const firstOriginalIndex = window.playlist.findIndex(s => s.index === displayedSongs[0].index);
                if (firstOriginalIndex !== -1) window.playSong(firstOriginalIndex);
            }
            return;
        }

        let prevDisplayedIndex = currentDisplayedIndex - 1;
        if (prevDisplayedIndex < 0) {
            prevDisplayedIndex = displayedSongs.length - 1; // 목록 처음으로 순환
        }

        // 이전 곡 객체 (displayedSongs 기준)
        const prevSongObject = displayedSongs[prevDisplayedIndex];
        // 이전 곡의 원본 플레이리스트(window.playlist) 인덱스를 찾음
        const prevOriginalIndex = window.playlist.findIndex(song => song.index === prevSongObject.index);

        if (prevOriginalIndex === -1) {
            console.error('playPrevious: 이전 곡의 원본 index를 찾을 수 없습니다.');
            return;
        }

        // 찾은 원본 인덱스로 재생 함수 호출
        window.playSong(prevOriginalIndex);
    };

    /**
     * 현재 표시된 목록(displayedSongs) 기준으로 다음 곡을 재생합니다.
     */
    window.playNext = function () {
        if (!window.currentPlayingSong || displayedSongs.length === 0) {
             console.log('playNext: 재생 중인 곡이 없거나 목록이 비어있습니다.');
            return; // 재생할 곡 없음
        }

        // 현재 재생 중인 곡이 displayedSongs 목록에서 몇 번째인지 찾음 (DB 인덱스 기준)
        const currentDisplayedIndex = displayedSongs.findIndex(song => song.index === window.currentPlayingSong.index);

        if (currentDisplayedIndex === -1) {
            console.warn('playNext: 현재 재생 중인 곡이 현재 표시된 목록에 없습니다.');
            // 첫 곡을 재생하거나, 재생 중지 등 정책 결정 필요
             if (displayedSongs.length > 0) {
                const firstOriginalIndex = window.playlist.findIndex(s => s.index === displayedSongs[0].index);
                if (firstOriginalIndex !== -1) window.playSong(firstOriginalIndex);
            }
            return;
        }

        let nextDisplayedIndex = currentDisplayedIndex + 1;

        // 목록의 끝에 도달했을 때의 처리
        if (nextDisplayedIndex >= displayedSongs.length) {
            // 1. 검색 결과 목록의 마지막 곡 재생 완료 시: 페이지 새로고침 (또는 다른 동작)
            if (displayedSongs.length !== window.playlist.length) {
                 console.log('playNext: 검색 결과 마지막 곡 재생 완료. 페이지 새로고침.');
                 location.reload();
                 return; // 새로고침하므로 함수 종료
            } else {
                // 2. 전체 목록 순환 재생: 첫 곡으로 이동
                console.log('playNext: 전체 목록 마지막 곡 재생 완료. 첫 곡으로 순환.');
                nextDisplayedIndex = 0;
            }
        }

        // 다음 곡 객체 (displayedSongs 기준)
        const nextSongObject = displayedSongs[nextDisplayedIndex];
        // 다음 곡의 원본 플레이리스트(window.playlist) 인덱스를 찾음
        const nextOriginalIndex = window.playlist.findIndex(song => song.index === nextSongObject.index);

        if (nextOriginalIndex === -1) {
            console.error('playNext: 다음 곡의 원본 index를 찾을 수 없습니다.');
            return;
        }

        // 찾은 원본 인덱스로 재생 함수 호출
        window.playSong(nextOriginalIndex);
    };

    // 오디오 재생 완료 시 다음 곡 자동 재생 이벤트 리스너
    audioPlayer.addEventListener('ended', window.playNext);

    /**
     * 곡의 찜 상태를 토글합니다. (서버와 통신)
     * @param {number|string} songIndex 토글할 곡의 DB 고유 번호 (song.index)
     * @param {HTMLElement} buttonElement 클릭된 찜 버튼 요소 (UI 업데이트용)
     */
    function toggleFavorite(songIndex, buttonElement) {
        if (typeof songIndex === 'undefined') {
            console.error('toggleFavorite: songIndex가 정의되지 않았습니다.');
            alert('찜 상태 변경에 필요한 정보가 부족합니다.');
            return;
        }
        const numSongIndex = Number(songIndex); // Set 및 서버 전송 시 숫자 타입으로 일관성 유지

        fetch('/toggle-favorite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ index: numSongIndex }) // DB 고유 번호 전달
        })
        .then(res => {
            if (!res.ok) {
                 // CSRF 토큰 만료 에러 처리
                 if (res.status === 419) {
                      alert('세션이 만료되었습니다. 페이지를 새로고침 해주세요.');
                      location.reload(); // 페이지 새로고침
                      throw new Error('CSRF token mismatch'); // 에러 발생시켜 .catch로 이동
                 }
                throw new Error(`toggleFavorite: 서버 응답 오류: ${res.status}`);
            }
            // 응답 본문이 있는지, JSON 형식인지 확인
            const contentType = res.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                 return res.json();
            } else {
                 throw new Error('toggleFavorite: 잘못된 서버 응답 형식 (JSON 필요)');
            }
        })
        .then(data => {
            // 서버 응답 형식 검증 강화
            if (data && typeof data.status === 'string') {
                if (data.status === "added") {
                    favoritedIndexes.add(numSongIndex); // 찜 Set에 추가
                    buttonElement.classList.add('active');
                    buttonElement.innerHTML = '<i class="fas fa-star"></i>'; // 아이콘 변경
                    console.log(`toggleFavorite: ${numSongIndex} 찜 추가됨`);
                } else if (data.status === "removed") {
                    favoritedIndexes.delete(numSongIndex); // 찜 Set에서 제거
                    buttonElement.classList.remove('active');
                    buttonElement.innerHTML = '<i class="far fa-star"></i>'; // 아이콘 변경
                     console.log(`toggleFavorite: ${numSongIndex} 찜 제거됨`);
                } else {
                    // 예상치 못한 status 값 처리
                    console.error('toggleFavorite: 알 수 없는 상태값:', data.status);
                    alert('찜 상태 변경 중 알 수 없는 응답을 받았습니다.');
                }
                // 필요하다면 여기서 window.playlist 데이터 모델의 is_favorite 같은 속성도 업데이트 할 수 있음
            } else {
                 // 응답 데이터 형식이 잘못된 경우
                 console.error('toggleFavorite: 서버 응답에 status 문자열 없음 또는 형식 오류:', data);
                 alert('찜 상태 변경 중 서버 응답 형식 오류가 발생했습니다.');
            }
        })
        .catch(error => {
             // CSRF 오류 시 이미 reload 했으므로 추가 alert 방지
             if (error.message !== 'CSRF token mismatch') {
                  console.error(`❌ toggleFavorite (${songIndex}) 처리 실패:`, error);
                  alert('찜 상태 변경에 실패했습니다.');
             }
        });
    }

    /**
     * 곡 재생 시 재생 횟수를 UI에 즉시 반영하고 서버에 업데이트 요청합니다.
     * @param {number|string} songUniqueIndex 업데이트할 곡의 DB 고유 번호 (song.index)
     * @param {number} originalIndex 업데이트할 곡의 window.playlist 배열 인덱스 (UI 업데이트용)
     */
    function updatePlayCount_ImmediateUI(songUniqueIndex, originalIndex) {
        if (typeof songUniqueIndex === 'undefined' || originalIndex < 0 || originalIndex >= window.playlist.length) {
             console.error('updatePlayCount_ImmediateUI: 잘못된 인덱스 또는 정보 부족.', { songUniqueIndex, originalIndex });
             return;
        }

        const songDiv = document.getElementById(`song-${originalIndex}`);
        let localCount = 0;
        let targetSongInData = window.playlist[originalIndex]; // 데이터 모델에서 해당 곡 객체 찾기
        let badgeElement = null;

        // 1. UI 및 데이터 모델 즉시 업데이트
        if (songDiv && targetSongInData) {
            badgeElement = songDiv.querySelector('.badge'); // 재생 횟수 표시 뱃지 요소

            if (badgeElement) {
                // 데이터 모델의 play_count를 기준으로 1 증가
                localCount = (parseInt(targetSongInData.play_count) || 0) + 1;
                badgeElement.innerText = `${localCount}회`; // UI 업데이트
                targetSongInData.play_count = localCount; // 데이터 모델 업데이트 (다음 렌더링 시 반영 위함)
                console.log(`updatePlayCount_ImmediateUI: UI/Data 업데이트 - 곡 Index: ${songUniqueIndex}, 새 재생 수: ${localCount}`);
            } else {
                 console.warn('updatePlayCount_ImmediateUI: 재생 횟수 뱃지 요소를 찾을 수 없습니다.', { songUniqueIndex, originalIndex });
            }
        } else {
             console.warn('updatePlayCount_ImmediateUI: 해당 곡의 UI 요소 또는 데이터 모델을 찾을 수 없습니다.', { songUniqueIndex, originalIndex });
             // UI 요소나 데이터가 없으면 서버 요청도 의미 없을 수 있으므로 여기서 중단할 수도 있음
             // return;
        }

        // 2. 서버에 재생 수 업데이트 비동기 요청
        fetch('/update-play-count', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ index: Number(songUniqueIndex) }) // DB 고유 번호 전달
        })
        .then(res => {
            if (!res.ok) {
                console.error(`updatePlayCount_ImmediateUI: 서버 응답 오류 (${res.status}), 인덱스: ${songUniqueIndex}.`);
                // 서버 업데이트 실패 시 UI 롤백은 복잡하므로 보통 로그만 남김
                throw new Error(`updatePlayCount_ImmediateUI: 서버 응답 오류: ${res.status}`);
            }
            const contentType = res.headers.get("content-type");
             if (contentType && contentType.indexOf("application/json") !== -1) {
                return res.json();
             } else {
                 // JSON 응답이 아니어도 성공으로 간주할 수 있음 (예: 204 No Content)
                 console.warn('updatePlayCount_ImmediateUI: 서버가 JSON이 아닌 성공 응답을 반환했습니다.');
                return null;
             }
        })
        .then(data => {
             // 서버로부터 성공 메시지 등 추가 정보가 있다면 로그 기록
             if (data && data.message) {
                  console.log('updatePlayCount_ImmediateUI: 서버 업데이트 성공 메시지:', data.message);
             } else {
                  console.log(`updatePlayCount_ImmediateUI: 서버 업데이트 성공 (인덱스: ${songUniqueIndex})`);
             }
        })
        .catch(error => {
            // 네트워크 오류 또는 서버 오류 응답 처리
            console.error(`❌ updatePlayCount_ImmediateUI (${songUniqueIndex}) 처리 실패:`, error.message);
             // 사용자에게 알림을 줄 수도 있음 (선택 사항)
             // alert('재생 횟수 업데이트에 실패했습니다.');
        });
    }

    // 검색 입력 이벤트 리스너
    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            const searchQuery = e.target.value.trim().toLowerCase();

            // 검색어가 비어있으면 전체 목록 복원 및 표시
            if (searchQuery.length === 0) {
                displayedSongs = [...window.playlist]; // 전체 목록으로 복원
                renderSongs(displayedSongs);
                return;
            }

            // 서버에 검색 요청 (디바운싱/쓰로틀링 고려 가능)
            fetch(`/search?q=${encodeURIComponent(searchQuery)}`)
                .then(res => {
                    if (!res.ok) throw new Error(`search: 검색 실패: ${res.status}`);
                    return res.json();
                 })
                .then(results => {
                    // 서버 결과(DB 인덱스 목록)를 바탕으로 window.playlist에서 곡 객체를 찾아 새 목록 생성
                    // 서버 응답 형식이 { index_number: xxx, ... } 형태라고 가정
                    displayedSongs = results.map(result => {
                        // result.index_number 대신 실제 백엔드 필드명 사용 (예: result.index)
                        return window.playlist.find(song => song.index == result.index_number);
                    }).filter(Boolean); // find에서 못 찾은 경우(undefined) 제거

                    renderSongs(displayedSongs); // 검색 결과 렌더링
                })
                .catch(error => {
                    console.error('❌ search: 검색 요청 실패:', error);
                    songList.innerHTML = '<li class="text-danger px-3">검색 중 오류가 발생했습니다.</li>';
                    displayedSongs = []; // 오류 시 표시 목록 비우기
                    renderSongs(displayedSongs); // 빈 목록 렌더링 (오류 메시지 표시 위함)
                });
        });
     }

    // === 사이드 메뉴 관련 함수 ===
    window.toggleMenu = function () {
        document.getElementById("sideMenu").classList.toggle("active");
    };

    window.showMain = function () {
        displayedSongs = [...window.playlist]; // 전체 목록으로 설정
        renderSongs(displayedSongs);
        if (searchInput) searchInput.value = ''; // 검색창 초기화
        toggleMenu(); // 메뉴 닫기
     };

    window.showFavorites = function () {
        // 찜 목록 필터링 (favoritedIndexes Set 사용, song.index 기준)
        displayedSongs = window.playlist.filter(song => favoritedIndexes.has(Number(song.index)));
        renderSongs(displayedSongs);
        if (searchInput) searchInput.value = ''; // 검색창 초기화
        toggleMenu(); // 메뉴 닫기
    };

    window.updatePlaylist = function () {
        alert("🔄 재생목록을 업데이트 중입니다...");
        toggleMenu(); // 메뉴 닫기
        fetch("/update-playlist")
            .then(res => {
                if (!res.ok) {
                     if (res.status === 419) { // CSRF 토큰 오류
                          alert('세션이 만료되었습니다. 페이지를 새로고침 해주세요.');
                          location.reload();
                          throw new Error('CSRF token mismatch');
                     }
                    throw new Error(`updatePlaylist: 서버 응답 오류: ${res.status}`);
                }
                // 성공 시 응답 본문이 있다면 텍스트로 읽기 (없을 수도 있음)
                return res.text();
            })
            .then(response => {
                alert(`✅ 업데이트 완료! 페이지를 새로고침합니다.\n${response || ''}`);
                location.reload(); // 페이지 새로고침하여 변경사항 반영
            })
            .catch(error => {
                // CSRF 오류 시 이미 reload 했으므로 추가 alert 방지
                if (error.message !== 'CSRF token mismatch') {
                    console.error('❌ updatePlaylist: 재생목록 업데이트 실패:', error);
                    alert(`재생목록 업데이트 중 오류가 발생했습니다.\n${error.message}`);
                }
            });
    };
    // ==========================

    // --- 페이지 초기화 ---
    renderSongs(displayedSongs); // 초기 노래 목록 렌더링

    // 플레이리스트에 곡이 있을 경우 첫 곡 자동 재생
    if (window.playlist.length > 0) {
       window.playSong(0);
    } else {
       // 곡이 없을 경우 메시지 표시
       songTitle.innerText = "재생 목록이 비어 있습니다.";
       document.title = "상재의 노래주머니";
       coverImage.src = '/images/default_thumbnail.png'; // 기본 이미지 표시
    }

    // 전체 곡 개수 표시 업데이트
    if (totalSongCountEl) {
        totalSongCountEl.textContent = `${window.playlist.length} 곡`;
    }
    // --- 페이지 초기화 끝 ---

}); // DOMContentLoaded 끝

// === 검색창 UI 관련 이벤트 리스너 ===
document.getElementById('searchToggle')?.addEventListener('click', function () {
    const input = document.getElementById('searchInput');
    input?.classList.toggle('active'); // 'active' 클래스 토글로 표시/숨김 제어
    if (input?.classList.contains('active')) input.focus(); // 활성화 시 포커스
});

// 검색창 영역 외부 클릭 시 검색창 숨김 처리
document.addEventListener('click', function (e) {
    const input = document.getElementById('searchInput');
    const toggle = document.getElementById('searchToggle');
    const searchArea = e.target.closest('.search-area'); // 클릭된 요소가 검색 영역 내부인지 확인

    // input, toggle 버튼, 검색 영역(.search-area) 내부가 아닌 곳을 클릭했을 때
    if (input && toggle && !input.contains(e.target) && !toggle.contains(e.target) && !searchArea) {
        input.classList.remove('active'); // 활성화 클래스 제거
    }
});
// =================================

</script>

{{-- 플레이어 UI 제어 스크립트 로드 --}}
<script src="{{ asset('js/player-ui.js') }}"></script>
@endpush