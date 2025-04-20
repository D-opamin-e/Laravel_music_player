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
{{-- 사이드바 메뉴 --}}
<div id="sideMenu">
    {{-- 주요 메뉴 목록 --}}
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
    window.playlist = initialPlaylist;
    let displayedSongs = [...window.playlist];
    window.currentPlayingSong = null;
    let currentSongOriginalIndex = -1;

    const mappedChannels = @json($mappedChannels); // 이 변수가 사용되는 부분은 현재 코드에 없지만, 유지합니다.
    // favoritedIndexes는 DB 인덱스(song.index)를 저장하도록 수정
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
            // renderSongs 내에서는 displayedSongs의 인덱스를 사용하지 않고,
            // 실제 playlist 내의 원본 인덱스를 찾아서 사용합니다.
            // 이 원본 인덱스는 'song-${index}' ID와 window.playSong(index) 호출에 사용됩니다.
            const originalIndex = window.playlist.findIndex(s => s.index === song.index);

            if (originalIndex === -1) {
                console.error("renderSongs: 곡에 대한 원본 index를 찾을 수 없습니다:", song.title, song.index);
                return;
            }

            const songDiv = document.createElement('div');
            songDiv.className = 'alert alert-light song-item d-flex justify-content-between align-items-center py-2 px-3 my-1';
            songDiv.style.cursor = 'pointer';
            songDiv.id = `song-${originalIndex}`; // 원본 인덱스를 사용하여 ID 생성
            songDiv.onclick = () => window.playSong(originalIndex); // 원본 인덱스로 playSong 호출

            const leftDiv = document.createElement('div');
            leftDiv.classList.add('d-flex', 'align-items-center', 'flex-grow-1', 'mr-2');

            const favoriteBtn = document.createElement('button');
            favoriteBtn.className = 'favorite-btn btn btn-sm mr-2';
            favoriteBtn.innerHTML = '<i class="far fa-star"></i>';
            // 찜 상태 확인 시 song.index 사용
            if (favoritedIndexes.has(Number(song.index))) {
                favoriteBtn.classList.add('active');
                favoriteBtn.innerHTML = '<i class="fas fa-star"></i>';
            }
            favoriteBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                // toggleFavorite 호출 시 song.index 전달
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
            thumbnail.src = `https://i.ytimg.com/vi/${song.videoID}/maxresdefault.jpg`;
            thumbnail.alt = `${song.title} 썸네일`;
            thumbnail.style.width = '50px';
            thumbnail.style.height = '50px';
            thumbnail.style.borderRadius = '5px';
            thumbnail.style.objectFit = 'cover';
            thumbnail.style.display = 'block';
            thumbnail.onerror = function() {
                this.src = '/images/default_thumbnail.png'; // 실제 기본 이미지 경로로 변경하세요
                console.warn(`renderSongs: 썸네일 로드 실패 (videoID: ${song.videoID})`);
            };

            rightDiv.appendChild(thumbnail);

            songDiv.appendChild(leftDiv);
            songDiv.appendChild(rightDiv);

            // 현재 재생 중인 곡 표시
            if (originalIndex === currentSongOriginalIndex) {
                songDiv.classList.add('current-song');
            }

            songList.appendChild(songDiv);
        });
    }

    window.playSong = function (index) {
        // index는 window.playlist 배열에서의 순서 인덱스입니다.
        if (index < 0 || index >= window.playlist.length) {
            console.error('⛔ playSong: 잘못된 인덱스:', index); // 함수 이름 포함
            return;
        }

        const song = window.playlist[index];

        // 현재 재생하려는 곡 정보를 콘솔에 출력 (song.index 사용)
        console.log('▶️ playSong: 곡 재생 시작', {
            playlistIndex: index, // window.playlist 배열에서의 인덱스
            songDBIndex: song.index, // 곡의 고유 번호 (DB index)
            title: song.title,
            channel: song.channel,
            videoID: song.videoID
        });

        currentSongOriginalIndex = index; // 현재 재생 중인 곡의 window.playlist 인덱스 저장
        window.currentPlayingSong = song; // 현재 재생 중인 곡 객체 저장

        const thumbnailUrl = `https://i.ytimg.com/vi/${song.videoID}/maxresdefault.jpg`;
        coverImage.src = thumbnailUrl;
        coverImage.onerror = function() { this.src = '/images/default_thumbnail.png'; }; // 실제 기본 이미지 경로
        songTitle.innerText = song.title;
        document.title = `${song.title} - ${song.channel}`;

        const audioSrc = '/music/' + encodeURIComponent(song.title) + '.mp3';
        const fullSrc = location.origin + audioSrc;

        if (audioPlayer.src !== fullSrc) {
            audioPlayer.src = fullSrc;
            // 오디오 소스 변경 시 메타데이터 로드 후 재생
            audioPlayer.load(); // Safari 등에서 필요할 수 있음
        }


        const playPromise = audioPlayer.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                // 재생 수 업데이트 시 song.index 전달
                updatePlayCount_ImmediateUI(song.index, currentSongOriginalIndex);

                // 전체 화면 플레이어 UI 업데이트
                if (document.getElementById('fullscreenPlayer').style.display === 'flex' && window.updateFullscreenUIIfNeeded) {
                    window.updateFullscreenUIIfNeeded(song);
                }
            }).catch(err => {
                // 재생 실패 시 처리
                console.error(`❌ 'playSong: '${song.title}' 재생 실패:`, err);
                // 다음 곡 자동 재생 또는 오류 메시지 표시 등
                // window.playNext(); // 실패 시 다음 곡 자동 재생 원하면 활성화
            });
        }

        // 현재 재생 중인 곡 UI 표시 업데이트
        document.querySelectorAll('.song-item.current-song').forEach(item => {
            item.classList.remove('current-song');
        });
        const currentDiv = document.getElementById(`song-${index}`); // 원본 인덱스 사용
        if (currentDiv) {
            currentDiv.classList.add('current-song');
        }

        // 미디어 세션 API 업데이트
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: song.title,
                artist: song.channel,
                album: '상재의 노래주머니', // 앨범 정보 필요시 추가
                artwork: [{ src: thumbnailUrl, sizes: '512x512', type: 'image/jpeg' }] // 썸네일 사용
            });

            navigator.mediaSession.setActionHandler('previoustrack', playPrevious);
            navigator.mediaSession.setActionHandler('nexttrack', window.playNext);
            navigator.mediaSession.setActionHandler('play', () => audioPlayer.play().catch(e=>console.error("playSong: 미디어 세션 재생 오류 (Play):", e)));
            navigator.mediaSession.setActionHandler('pause', () => audioPlayer.pause());
            // seekbackward/forward, stop 등 추가 가능
        }
    };

    function playPrevious() {
        if (!window.currentPlayingSong || displayedSongs.length === 0) {
             // 재생 중지 및 UI 초기화 (필요에 따라)
             window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
             audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
             renderSongs(displayedSongs); // 목록 새로고침 (선택 사항)
             return;
        }

        // 현재 재생 중인 곡이 displayedSongs 목록에서 몇 번째인지 찾습니다.
        const currentDisplayedIndex = displayedSongs.findIndex(song => song.index === window.currentPlayingSong.index);

        if (currentDisplayedIndex === -1) {
            console.warn('playPrevious: 현재 재생 중인 곡이 표시된 목록에 없습니다.');
             // 재생 중지 및 UI 초기화 (필요에 따라)
             window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
             audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
             renderSongs(displayedSongs); // 목록 새로고침 (선택 사항)
             return;
        }

        let prevDisplayedIndex = currentDisplayedIndex - 1;
        if (prevDisplayedIndex < 0) {
            prevDisplayedIndex = displayedSongs.length - 1; // 목록 순환
        }

        // 이전 곡 객체를 displayedSongs에서 가져옵니다.
        const prevSongObject = displayedSongs[prevDisplayedIndex];
        // 이 곡 객체의 고유 ID(song.index)를 사용하여 window.playlist에서의 원본 인덱스를 찾습니다.
        const prevOriginalIndex = window.playlist.findIndex(song => song.index === prevSongObject.index);

        if (prevOriginalIndex === -1) {
            console.error('playPrevious: 이전 곡의 원본 index를 찾을 수 없습니다.');
             // 재생 중지 및 UI 초기화 (필요에 따라)
             window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
             audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
             renderSongs(displayedSongs); // 목록 새로고침 (선택 사항)
             return;
        }

        // 찾은 원본 인덱스로 playSong 함수 호출
        window.playSong(prevOriginalIndex);
    };

    window.playNext = function () {
        if (!window.currentPlayingSong || displayedSongs.length === 0) {
            // 재생 중지 및 UI 초기화
            window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
            audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
            renderSongs(displayedSongs); // 목록 새로고침 (선택 사항)
            return;
        }

        // 현재 재생 중인 곡이 displayedSongs 목록에서 몇 번째인지 찾습니다.
        const currentDisplayedIndex = displayedSongs.findIndex(song => song.index === window.currentPlayingSong.index);
        if (currentDisplayedIndex === -1) {
             console.warn('playNext: 현재 재생 중인 곡이 표시된 목록에 없습니다.');
             // 재생 중지 및 UI 초기화
            window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
            audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
            renderSongs(displayedSongs); // 목록 새로고침 (선택 사항)
            return;
        }

        let nextDisplayedIndex = currentDisplayedIndex + 1;

        if (nextDisplayedIndex >= displayedSongs.length) {
            // ✅ 검색 곡의 마지막 재생 완료시 페이지 새로고침 실행 또는 처음으로 돌아가기
            console.log('playNext: 마지막 곡 재생 완료. 페이지 새로고침.');
            location.reload();
            // 또는 목록 처음부터 다시 시작하려면: nextDisplayedIndex = 0;
            return; // 새로고침을 선택한 경우 함수 종료
        }

        // 다음 곡 객체를 displayedSongs에서 가져옵니다.
        const nextSongObject = displayedSongs[nextDisplayedIndex];
        // 이 곡 객체의 고유 ID(song.index)를 사용하여 window.playlist에서의 원본 인덱스를 찾습니다.
        const nextOriginalIndex = window.playlist.findIndex(song => song.index === nextSongObject.index);

        if (nextOriginalIndex === -1) {
            console.error('playNext: 다음 곡의 원본 index를 찾을 수 없습니다.');
             // 재생 중지 및 UI 초기화
            window.currentPlayingSong = null; currentSongOriginalIndex = -1; audioPlayer.pause();
            audioPlayer.src = ""; coverImage.src = ""; songTitle.innerText = "재생 중인 곡 없음"; document.title = "상재의 노래주머니";
            renderSongs(displayedSongs); // 목록 새로고침 (선택 사항)
            return;
        }

        // 찾은 원본 인덱스로 playSong 함수 호출
        window.playSong(nextOriginalIndex);
    };


    // 오디오 재생 완료 시 다음 곡 자동 재생
    audioPlayer.addEventListener('ended', window.playNext);

    // songIndex 매개변수는 이제 song.index (DB 고유 번호) 입니다.
    function toggleFavorite(songIndex, buttonElement) {
        if (typeof songIndex === 'undefined') {
            console.error('toggleFavorite: songIndex가 정의되지 않았습니다.');
            alert('찜 상태 변경에 필요한 정보가 부족합니다.');
            return;
        }
        const numSongIndex = Number(songIndex); // 숫자로 변환하여 사용 (Set에 저장할 때 일관성을 위해)

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
                 // 419 CSRF 오류 등 특정 상태 코드에 대한 처리 추가 가능
                 if (res.status === 419) {
                      alert('세션이 만료되었습니다. 페이지를 새로고침 해주세요.');
                      location.reload();
                      return; // 이후 처리 중단
                 }
                throw new Error(`toggleFavorite: 서버 응답 오류: ${res.status}`);
            }
            const contentType = res.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                 return res.json();
            } else {
                 // JSON 응답이 아닌 경우 오류 처리
                 throw new Error('toggleFavorite: 잘못된 서버 응답 형식 (JSON 필요)');
            }
        })
        .then(data => {
            if (data && typeof data.status === 'string') {
                if (data.status === "added") {
                    favoritedIndexes.add(numSongIndex); // Set에 추가
                    buttonElement.classList.add('active');
                    buttonElement.innerHTML = '<i class="fas fa-star"></i>';
                    console.log(`toggleFavorite: ${numSongIndex} 찜 추가됨`);
                } else if (data.status === "removed") {
                    favoritedIndexes.delete(numSongIndex); // Set에서 제거
                    buttonElement.classList.remove('active');
                    buttonElement.innerHTML = '<i class="far fa-star"></i>';
                     console.log(`toggleFavorite: ${numSongIndex} 찜 제거됨`);
                } else {
                    console.error('toggleFavorite: 알 수 없는 상태값:', data.status);
                    alert('찜 상태 변경 중 알 수 없는 응답을 받았습니다.');
                }
                 // UI 업데이트 후 playlist 데이터 모델의 찜 상태도 업데이트할 수 있으나, 현재는 Set으로 관리.
            } else {
                 console.error('toggleFavorite: 서버 응답에 status 문자열 없음 또는 형식 오류:', data);
                 alert('찜 상태 변경 중 서버 응답 형식 오류가 발생했습니다.');
            }
        })
        .catch(error => {
            console.error(`❌ toggleFavorite (${songIndex}) 처리 실패:`, error);
            alert('찜 상태 변경에 실패했습니다.');
        });
    }

    // songUniqueIndex 매개변수는 이제 song.index (DB 고유 번호) 입니다.
    function updatePlayCount_ImmediateUI(songUniqueIndex, originalIndex) {
        if (typeof songUniqueIndex === 'undefined' || originalIndex < 0 || originalIndex >= window.playlist.length) {
             console.error('updatePlayCount_ImmediateUI: 잘못된 인덱스 또는 정보 부족.', { songUniqueIndex, originalIndex });
             return;
        }

        const songDiv = document.getElementById(`song-${originalIndex}`);
        let localCount = 0;
        let targetSongInData = null;
        let badgeElement = null; // 뱃지 요소 저장용

        // UI에서 뱃지 업데이트 및 데이터 모델 업데이트
        if (songDiv) {
            badgeElement = songDiv.querySelector('.badge'); // 뱃지 요소 찾기
            targetSongInData = window.playlist[originalIndex]; // window.playlist에서 해당 곡 객체 찾기

            if (badgeElement && targetSongInData) {
                localCount = (parseInt(targetSongInData.play_count) || 0) + 1; // 데이터 모델의 play_count 사용
                badgeElement.innerText = `${localCount}회`; // UI 즉시 업데이트
                targetSongInData.play_count = localCount; // 데이터 모델 업데이트
                console.log(`updatePlayCount_ImmediateUI: UI 및 데이터 모델 업데이트 - 곡 Index: ${songUniqueIndex}, 새 재생 수: ${localCount}`);
            } else {
                 console.warn('updatePlayCount_ImmediateUI: 해당 곡의 UI 요소 또는 데이터 모델을 찾을 수 없습니다.', { songUniqueIndex, originalIndex });
            }
        } else {
             console.warn('updatePlayCount_ImmediateUI: 해당 originalIndex에 대한 songDiv를 찾을 수 없습니다.', originalIndex);
        }


        // 서버에 재생 수 업데이트 요청
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
                // 서버 오류 시 UI 및 데이터 모델 롤백 (선택 사항이나, 정합성을 위해 고려할 수 있음)
                // 하지만 즉시 UI 업데이트 방식에서는 롤백이 복잡할 수 있어 로그만 남기는 경우가 많음
                 if (songDiv && badgeElement && targetSongInData) {
                      console.warn(`updatePlayCount_ImmediateUI: 서버 업데이트 실패로 UI/데이터 모델 롤백 시도 안함 (즉시 업데이트 방식)`);
                 }
                throw new Error(`updatePlayCount_ImmediateUI: 서버 응답 오류: ${res.status}`);
            }
            const contentType = res.headers.get("content-type");
             if (contentType && contentType.indexOf("application/json") !== -1) {
                return res.json(); // 서버에서 응답하는 JSON 데이터 (예: 성공 메시지)
             } else {
                 console.warn('updatePlayCount_ImmediateUI: 서버가 JSON이 아닌 응답을 반환했습니다.');
                return null; // JSON 아니면 null 반환
             }
        })
        .then(data => {
             // 성공 시 추가 작업 없음 (UI는 이미 업데이트됨)
             if (data && data.message) {
                  console.log('updatePlayCount_ImmediateUI: 서버 업데이트 성공 메시지:', data.message);
             }
        })
        .catch(error => {
            // fetch 자체 실패 또는 서버 오류 응답 처리
            console.error(`❌ updatePlayCount_ImmediateUI (${songUniqueIndex}) 처리 실패:`, error.message);
        });
    }


    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            const searchQuery = e.target.value.trim().toLowerCase();

            // 검색어가 비어있으면 전체 목록 표시
            if (searchQuery.length === 0) {
                displayedSongs = [...window.playlist];
                renderSongs(displayedSongs);
                return;
            }

            // 검색어가 있을 경우 서버에 검색 요청
            fetch(`/search?q=${encodeURIComponent(searchQuery)}`)
                .then(res => {
                    if (!res.ok) throw new Error(`search: 검색 실패: ${res.status}`);
                    return res.json();
                 })
                .then(results => {
                    // 검색 결과는 DB 인덱스(index_number로 오는지 확인 필요, 여기서는 result.index_number로 가정) 목록
                    // 이 목록을 사용하여 window.playlist에서 해당 곡 객체를 찾습니다.
                    displayedSongs = results.map(result => {
                        // result.index_number가 서버 검색 결과에서 DB index 필드라고 가정
                        // 만약 서버 검색 결과가 song 객체 형태 그대로 온다면 result.index를 사용해야 합니다.
                        // 현재 백엔드 검색 로직에 맞춰 result.index_number를 사용합니다.
                        return window.playlist.find(song => song.index == result.index_number);
                    }).filter(Boolean); // 찾지 못한 곡은 제외

                    renderSongs(displayedSongs);
                })
                .catch(error => {
                    console.error('❌ search: 검색 요청 실패:', error);
                    songList.innerHTML = '<li class="text-danger px-3">검색 중 오류가 발생했습니다.</li>';
                });
        });
     }

    // 사이드 메뉴 토글 함수
    window.toggleMenu = function () {
        document.getElementById("sideMenu").classList.toggle("active");
    };

    // 메인 목록 표시 함수
    window.showMain = function () {
        displayedSongs = [...window.playlist]; // 전체 목록
        renderSongs(displayedSongs);
        if (searchInput) searchInput.value = ''; // 검색 입력창 초기화
        toggleMenu(); // 메뉴 닫기
     };

    // 찜 목록 표시 함수
    window.showFavorites = function () {
        // favoritedIndexes Set을 사용하여 찜 목록 필터링 (song.index 사용)
        displayedSongs = window.playlist.filter(song => favoritedIndexes.has(Number(song.index)));
        renderSongs(displayedSongs);
        if (searchInput) searchInput.value = ''; // 검색 입력창 초기화
        toggleMenu(); // 메뉴 닫기
    };

    // 재생목록 업데이트 함수
    window.updatePlaylist = function () {
        alert("🔄 재생목록을 업데이트 중입니다...");
        toggleMenu(); // 메뉴 닫기
        fetch("/update-playlist")
            .then(res => {
                if (!res.ok) {
                     if (res.status === 419) {
                          alert('세션이 만료되었습니다. 페이지를 새로고침 해주세요.');
                          location.reload();
                          return; // 이후 처리 중단
                     }
                    throw new Error(`updatePlaylist: 서버 응답 오류: ${res.status}`);
                }
                 // 서버 응답이 텍스트일 경우
                return res.text();
            })
            .then(response => {
                alert("✅ 업데이트 완료! 페이지를 새로고침합니다.\n" + (response || ''));
                location.reload(); // 페이지 새로고침
            })
            .catch(error => {
                console.error('❌ updatePlaylist: 재생목록 업데이트 실패:', error);
                alert('재생목록 업데이트 중 오류가 발생했습니다.\n' + error.message);
            });
    };

    // 페이지 로드 시 초기 렌더링 및 첫 곡 재생
    renderSongs(displayedSongs);
    if (window.playlist.length > 0) {
       window.playSong(0); // 페이지 로드 시 첫 곡 자동 재생
    } else {
       songTitle.innerText = "재생 목록이 비어 있습니다.";
        document.title = "상재의 노래주머니";
    }

    // 전체 곡 개수 표시 업데이트
    if (totalSongCountEl) {
        totalSongCountEl.textContent = `${window.playlist.length} 곡`;
    }

}); // DOMContentLoaded 끝

// 검색창 토글 버튼 이벤트 리스너
document.getElementById('searchToggle')?.addEventListener('click', function () {
    const input = document.getElementById('searchInput');
    input?.classList.toggle('active');
    if (input?.classList.contains('active')) input.focus();
});

// 검색창 외부 클릭 시 숨김 처리
document.addEventListener('click', function (e) {
    const input = document.getElementById('searchInput');
    const toggle = document.getElementById('searchToggle');
    // input, toggle, search-area 영역이 아닌 곳을 클릭했을 때 숨김
    if (input && toggle && !input.contains(e.target) && !toggle.contains(e.target) && !e.target.closest('.search-area')) {
        input.classList.remove('active');
    }
});

// 플레이어 UI 관련 스크립트 (js/player-ui.js 파일에 있다고 가정)
</script>

<script src="{{ asset('js/player-ui.js') }}"></script>
@endpush