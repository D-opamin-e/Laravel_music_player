/**
 * player-ui.js
 * 풀스크린 오디오 플레이어 UI 및 상호작용 관리
 * 상태 관리는 welcome.blade.php의 전역 변수/함수에 의존합니다.
 */

// 전역 상태 변수 (이 파일에서는 직접 관리하지 않음)
// - window.playlist: 전체 곡 목록 배열
// - window.currentPlayingSong: 현재 재생 중인 곡 객체
// - window.currentSongOriginalIndex: 현재 재생 곡의 원래 인덱스

let userClicked = false; // (사용 여부 확인 필요)

window.addEventListener('DOMContentLoaded', function () {
    const audioPlayer = document.getElementById('audioPlayer');
    const fullscreenPlayer = document.getElementById('fullscreenPlayer');
    const fullscreenCover = document.getElementById('fullscreenCover');
    const fullscreenTitle = document.getElementById('fullscreenTitle');
    const fullscreenArtist = document.getElementById('fullscreenArtist');
    const lyricsButton = document.querySelector('.fullscreen-tabs button');
    const seekBar = document.getElementById('seekBar');
    const currentTimeDisplay = document.getElementById('currentTime');
    const durationDisplay = document.getElementById('duration');
    const playPauseBtn = document.querySelector('.fullscreen-playpause');
    const prevBtn = document.querySelector('.fullscreen-prev');
    const nextBtn = document.querySelector('.fullscreen-next');
    const closeBtn = document.getElementById('closeFullscreenBtn');
    let lyricsContainer = null; // 가사 컨테이너 요소를 저장할 변수

    console.log('✅ player-ui.js 로드됨');

    // 가사 컨테이너를 생성하거나 찾고 초기 상태 설정
    function initializeLyricsContainer() {
         lyricsContainer = document.getElementById('lyricsContainer');
         if (!lyricsContainer) {
             lyricsContainer = document.createElement('div');
             lyricsContainer.id = 'lyricsContainer';
             // 초기 스타일 (숨김 상태) - CSS에서 관리하는 것이 더 좋음
             // 여기서는 JS로 생성하므로 기본적인 틀만 잡고, 보이기/숨김은 클래스로 제어
             lyricsContainer.style.position = 'absolute'; // 필요에 따라 변경 (absolute 또는 flex item)
             lyricsContainer.style.top = '150px'; // 필요에 따라 위치 조정
             lyricsContainer.style.left = '20px';
             lyricsContainer.style.right = '20px'; // 오른쪽 여백 추가
             lyricsContainer.style.maxHeight = '400px'; // 세로 최대 크기
             lyricsContainer.style.overflowY = 'auto';
             lyricsContainer.style.padding = '10px';
             lyricsContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.8)'; // 반투명 검정 배경
             lyricsContainer.style.color = 'rgba(255, 255, 255, 0.8)'; // 글자색 하얗게
             lyricsContainer.style.transition = 'opacity 0.3s ease-in-out, transform 0.3s ease-in-out'; // 나타나는 애니메이션 추가
             lyricsContainer.style.opacity = '0'; // 초기 투명 상태
             lyricsContainer.style.transform = 'translateY(20px)'; // 초기 위치 (아래로 살짝 내려놓기)
             lyricsContainer.style.pointerEvents = 'none'; // 숨김 상태일 때 클릭 방지
             fullscreenPlayer.appendChild(lyricsContainer);
         }
         // 초기에는 숨김 상태를 유지 (CSS 클래스로 제어할 수도 있음)
         lyricsContainer.classList.add('hidden'); // 숨김 상태를 위한 CSS 클래스 추가
         lyricsContainer.style.display = 'none'; // 일단 display none으로 완전히 숨김
    }

    // 페이지 로드 시 가사 컨테이너 초기화
    initializeLyricsContainer();


    if (lyricsButton) {
        lyricsButton.addEventListener('click', function (e) {
            e.stopPropagation();
            // 가사 영역 토글 함수 호출
            toggleLyricsDisplay();
        });
    }

    function toggleLyricsDisplay() {
        if (!window.currentPlayingSong) {
            console.warn("가사 토글: 현재 재생 중인 곡이 없습니다.");
            return;
        }

        // 가사 컨테이너 요소를 다시 가져옴 (initializeLyricsContainer에서 이미 설정됨)
        if (!lyricsContainer) {
             console.error("가사 컨테이너 요소를 찾거나 생성할 수 없습니다.");
             return;
        }

        const isHidden = lyricsContainer.classList.contains('hidden');

        if (isHidden) {
            // 숨겨져 있으면 보여주기
            fetchLyrics(window.currentPlayingSong.id); // 가사 불러오기
            // fetchLyrics 성공 후 showLyrics에서 컨텐츠를 채우고,
            // 여기서 display와 클래스를 조작하여 보이게 함
            lyricsContainer.style.display = 'block'; // 일단 보이게
            // 애니메이션 효과를 위해 잠시 기다렸다가 클래스 제거
            requestAnimationFrame(() => {
                 requestAnimationFrame(() => {
                    lyricsContainer.classList.remove('hidden');
                    lyricsContainer.classList.add('visible'); // 보이는 상태 CSS 클래스 추가
                    lyricsContainer.style.opacity = '1'; // 투명도 1로 만들어서 보이게 함
                    lyricsContainer.style.transform = 'translateY(0)'; // 원래 위치로 이동
                    lyricsContainer.style.pointerEvents = 'auto'; // 보일 때 클릭 가능하게
                 });
            });

        } else {
            // 보이고 있으면 숨기기
            lyricsContainer.classList.remove('visible');
            lyricsContainer.classList.add('hidden'); // 숨김 상태 CSS 클래스 추가
            lyricsContainer.style.opacity = '0'; // 투명도 0으로 만들어서 숨김 효과
            lyricsContainer.style.transform = 'translateY(20px)'; // 다시 살짝 아래로 이동 (숨김 효과)
            lyricsContainer.style.pointerEvents = 'none'; // 숨김 상태일 때 클릭 방지

            // 트랜지션 완료 후 완전히 숨김
            lyricsContainer.addEventListener('transitionend', function handler() {
                if (lyricsContainer.classList.contains('hidden')) {
                     lyricsContainer.style.display = 'none'; // 트랜지션 완료 후 display none
                     lyricsContainer.removeEventListener('transitionend', handler);
                }
            });
        }
    }


    function fetchLyrics(songId) {
        // AJAX 요청을 통해 가사 불러오기
        // 가사 영역이 아직 비어있거나 다른 곡의 가사일 경우에만 fetch
        if (!lyricsContainer || lyricsContainer.dataset.songId !== songId || lyricsContainer.innerHTML.trim() === '') {
             console.log(`가사 불러오는 중: ${songId}`); // 로깅 추가
             // 이전 가사를 지우고 로딩 상태 표시 등
             lyricsContainer.innerHTML = '<p style="text-align: center;">가사 불러오는 중...</p>';
             lyricsContainer.dataset.songId = songId; // 어떤 곡의 가사인지 저장

             fetch(`/lyrics/${songId}`)
                 .then(response => {
                     if (!response.ok) {
                         throw new Error(`HTTP error! status: ${response.status}`);
                     }
                     return response.json();
                  })
                 .then(data => {
                     if (data.lyrics) {
                         showLyrics(data.lyrics); // 가사 표시 함수 호출 (내용만 채움)
                     } else {
                         showLyrics('가사를 찾을 수 없습니다.'); // 가사 없을 때 메시지 표시
                         console.warn(`가사 없음: ${songId}`);
                     }
                 })
                 .catch(error => {
                     console.error('가사 불러오기 오류:', error);
                     showLyrics('가사를 불러오는 데 실패했습니다.'); // 오류 메시지 표시
                 });
        } else {
             console.log(`이미 불러온 가사: ${songId}`); // 이미 가사가 있는 경우 로깅
             // 이미 가사가 있으므로 fetch하지 않음. 토글만 수행
        }
    }

    function showLyrics(lyrics) {
        // 가사를 화면에 표시할 div 생성/선택은 initializeLyricsContainer에서 이미 함
        // 여기서는 받은 가사 텍스트를 채우는 역할만 함.
        if (!lyricsContainer) {
             console.error("showLyrics: 가사 컨테이너 요소를 찾을 수 없습니다.");
             return;
        }
        // <pre> 태그를 사용하여 포맷팅 유지, CSS로 색상/배경은 이미 설정됨
        lyricsContainer.innerHTML = `<pre>${lyrics}</pre>`;
        // 가사 내용이 업데이트된 후에는 dataset.songId가 최신 상태인지 확인
        // (fetchLyrics에서 이미 설정했으므로 여기서 또 할 필요는 없음)
    }


    if (!audioPlayer || !fullscreenPlayer) {
        console.error("❌ 필수 플레이어 요소를 찾을 수 없습니다!");
        return;
    }

    function updateFullscreenUI(song) {
        if (song) {
            const thumbnailUrl = `https://i.ytimg.com/vi/${song.videoID}/maxresdefault.jpg`;
            if (fullscreenCover) {
                fullscreenCover.src = thumbnailUrl;
                fullscreenCover.onerror = function() { this.src = '/images/maxresdefault.png'; }; // 기본 이미지 경로
            }
            if (fullscreenTitle) fullscreenTitle.innerText = song.title;
            if (fullscreenArtist) fullscreenArtist.innerText = song.channel;

            if (playPauseBtn) {
                playPauseBtn.innerHTML = audioPlayer.paused ? '<i class="fas fa-play"></i>' : '<i class="fas fa-pause"></i>';
            }

            const duration = audioPlayer.duration;
            if (!isNaN(duration) && duration > 0) {
                if (durationDisplay) durationDisplay.textContent = formatTime(duration);
                if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
                if (seekBar) seekBar.value = (audioPlayer.currentTime / duration) * 100;
                if (seekBar) seekBar.disabled = false;
            } else {
                if (durationDisplay) durationDisplay.textContent = formatTime(0);
                if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(0);
                if (seekBar) seekBar.value = 0;
                if (seekBar) seekBar.disabled = true;
            }

            // 새로운 곡이 로드되면 가사 컨테이너 내용 초기화 및 숨김
            if (lyricsContainer) {
                 lyricsContainer.innerHTML = ''; // 이전 가사 내용 지우기
                 lyricsContainer.classList.remove('visible');
                 lyricsContainer.classList.add('hidden');
                 lyricsContainer.style.opacity = '0';
                 lyricsContainer.style.transform = 'translateY(20px)';
                 lyricsContainer.style.pointerEvents = 'none';
                 lyricsContainer.style.display = 'none'; // 완전히 숨김
                 lyricsContainer.dataset.songId = ''; // 곡 ID 초기화
            }

        } else {
            if (fullscreenCover) fullscreenCover.src = '';
            if (fullscreenTitle) fullscreenTitle.innerText = '선택된 곡 없음';
            if (fullscreenArtist) fullscreenArtist.innerText = '';
            if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(0);
            if (durationDisplay) durationDisplay.textContent = formatTime(0);
            if (seekBar) {
                seekBar.value = 0;
                seekBar.disabled = true;
            }
             // 곡이 없으면 가사 컨테이너 숨김
             if (lyricsContainer) {
                 lyricsContainer.innerHTML = ''; // 이전 가사 내용 지우기
                 lyricsContainer.classList.remove('visible');
                 lyricsContainer.classList.add('hidden');
                 lyricsContainer.style.opacity = '0';
                 lyricsContainer.style.transform = 'translateY(20px)';
                 lyricsContainer.style.pointerEvents = 'none';
                 lyricsContainer.style.display = 'none'; // 완전히 숨김
                 lyricsContainer.dataset.songId = ''; // 곡 ID 초기화
             }
        }
    }

    window.updateFullscreenUIIfNeeded = function(song) {
        if (fullscreenPlayer.style.display === 'flex') {
            updateFullscreenUI(song);
        } else {
             // 풀스크린이 닫혔을 때 가사 영역도 숨김 처리
             if (lyricsContainer) {
                 lyricsContainer.classList.remove('visible');
                 lyricsContainer.classList.add('hidden');
                 lyricsContainer.style.opacity = '0';
                 lyricsContainer.style.transform = 'translateY(20px)';
                 lyricsContainer.style.pointerEvents = 'none';
                 lyricsContainer.style.display = 'none'; // 완전히 숨김
             }
        }
    }

    window.openFullscreenPlayer = function () {
        const currentSong = window.currentPlayingSong;

        if (!currentSong) {
            console.warn("풀스크린 열 수 없음: 현재 재생 중인 곡이 없습니다.");
            return;
        }

        console.log("풀스크린 플레이어 여는 중 - 곡:", currentSong.title); // 필요시 주석 해제
        fullscreenPlayer.style.display = 'flex';
        requestAnimationFrame(() => {
            fullscreenPlayer.classList.add('active');
        });
        updateFullscreenUI(currentSong); // 풀스크린 열 때 UI 업데이트 및 가사 컨테이너 초기화/숨김
    };

    window.closeFullscreenPlayer = function () {
        // console.log("풀스크린 플레이어 닫는 중."); // 필요시 주석 해제
        fullscreenPlayer.classList.remove('active');
        // 풀스크린 닫을 때 가사 영역도 숨김
         if (lyricsContainer) {
             lyricsContainer.classList.remove('visible');
             lyricsContainer.classList.add('hidden');
             lyricsContainer.style.opacity = '0';
             lyricsContainer.style.transform = 'translateY(20px)';
             lyricsContainer.style.pointerEvents = 'none';
             // 트랜지션 완료 후 완전히 숨김
             lyricsContainer.addEventListener('transitionend', function handler() {
                 if (lyricsContainer.classList.contains('hidden')) {
                     lyricsContainer.style.display = 'none'; // 트랜지션 완료 후 display none
                     lyricsContainer.removeEventListener('transitionend', handler);
                 }
             });
         }

        setTimeout(() => {
            fullscreenPlayer.style.display = 'none';
        }, 300); // CSS transition 시간과 일치
    };

    const audioPlayerContainer = document.getElementById('audioPlayerContainer');
    if (audioPlayerContainer) {
        audioPlayerContainer.addEventListener('click', () => {
            window.openFullscreenPlayer();
        });
    } else {
        console.warn("#audioPlayerContainer 요소를 찾을 수 없음.");
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            window.closeFullscreenPlayer();
        });
    }

    if (fullscreenPlayer) {
        fullscreenPlayer.addEventListener('click', function (e) {
            // 가사 영역을 클릭했을 때는 닫히지 않도록 예외 처리
            if (e.target === fullscreenPlayer) {
                 window.closeFullscreenPlayer();
            }
        });
    }

    if (fullscreenCover) {
        fullscreenCover.addEventListener('click', function (e) {
            e.stopPropagation();
            togglePlay();
        });
    }

    if (playPauseBtn) {
        playPauseBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            togglePlay();
        });
    }

    function togglePlay() {
        if (!window.currentPlayingSong) {
            console.warn("재생/일시정지 토글: 로드된 곡이 없습니다.");
            return;
        }
        if (audioPlayer.paused) {
            audioPlayer.play().catch(e => console.error("재생 오류:", e));
        } else {
            audioPlayer.pause();
        }
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (typeof window.playPrevious === 'function') {
                window.playPrevious();
            } else {
                // Media Session API는 UI 로직과 분리하는 것이 좋지만, 여기서는 기존 코드를 유지
                navigator.mediaSession?.playbackState === 'playing' && navigator.mediaSession?.setActionHandler('previoustrack', () => {})()
                console.warn("window.playPrevious 함수를 찾을 수 없습니다.");
            }
             // 이전/다음 곡 이동 시 가사 영역 숨김 및 초기화
             if (lyricsContainer) {
                 lyricsContainer.innerHTML = '';
                 lyricsContainer.classList.remove('visible');
                 lyricsContainer.classList.add('hidden');
                 lyricsContainer.style.opacity = '0';
                 lyricsContainer.style.transform = 'translateY(20px)';
                 lyricsContainer.style.pointerEvents = 'none';
                 lyricsContainer.style.display = 'none';
                 lyricsContainer.dataset.songId = '';
             }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (typeof window.playNext === 'function') {
                window.playNext();
            } else {
                // Media Session API는 UI 로직과 분리하는 것이 좋지만, 여기서는 기존 코드를 유지
                navigator.mediaSession?.playbackState === 'playing' && navigator.mediaSession?.setActionHandler('nexttrack', () => {})()
                console.warn("window.playNext 함수를 찾을 수 없습니다.");
            }
             // 이전/다음 곡 이동 시 가사 영역 숨김 및 초기화
             if (lyricsContainer) {
                 lyricsContainer.innerHTML = '';
                 lyricsContainer.classList.remove('visible');
                 lyricsContainer.classList.add('hidden');
                 lyricsContainer.style.opacity = '0';
                 lyricsContainer.style.transform = 'translateY(20px)';
                 lyricsContainer.style.pointerEvents = 'none';
                 lyricsContainer.style.display = 'none';
                 lyricsContainer.dataset.songId = '';
             }
        });
    }

    audioPlayer.addEventListener('play', () => {
        if (fullscreenPlayer.style.display === 'flex') {
            updateFullscreenUI(window.currentPlayingSong);
        }
    });

    audioPlayer.addEventListener('pause', () => {
        if (fullscreenPlayer.style.display === 'flex') {
            updateFullscreenUI(window.currentPlayingSong);
        }
    });

    audioPlayer.addEventListener('timeupdate', function () {
        if (fullscreenPlayer.style.display === 'flex' && !isNaN(audioPlayer.duration) && audioPlayer.duration > 0) {
            // 가사 영역이 보이는 상태라면 스크롤 위치 업데이트 로직 추가 가능
            // (현재 코드에는 없으므로 필요시 추가)

            if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
            if (seekBar && !seekBar.matches(':active')) {
                seekBar.value = (audioPlayer.currentTime / audioPlayer.duration) * 100;
            }
        }
    });

    audioPlayer.addEventListener('loadedmetadata', function () {
        console.log("메타데이터 로드됨"); // 필요시 주석 해제
        if (fullscreenPlayer.style.display === 'flex') {
            updateFullscreenUI(window.currentPlayingSong); // 메타데이터 로드 시 UI 업데이트 (총 시간 등)
        }
         // 새 곡 메타데이터 로드 완료 시 가사 영역 초기화/숨김
         if (lyricsContainer) {
             lyricsContainer.innerHTML = '';
             lyricsContainer.classList.remove('visible');
             lyricsContainer.classList.add('hidden');
             lyricsContainer.style.opacity = '0';
             lyricsContainer.style.transform = 'translateY(20px)';
             lyricsContainer.style.pointerEvents = 'none';
             lyricsContainer.style.display = 'none';
             lyricsContainer.dataset.songId = '';
         }
    });

    audioPlayer.addEventListener('loadstart', () => {
        console.log("로딩 시작"); // 필요시 주석 해제
          if (fullscreenPlayer.style.display === 'flex') {
            // 로딩 중 UI 업데이트 가능 (예: 로딩 스피너)
          }
         // 로딩 시작 시 가사 영역 초기화/숨김
         if (lyricsContainer) {
             lyricsContainer.innerHTML = '';
             lyricsContainer.classList.remove('visible');
             lyricsContainer.classList.add('hidden');
             lyricsContainer.style.opacity = '0';
             lyricsContainer.style.transform = 'translateY(20px)';
             lyricsContainer.style.pointerEvents = 'none';
             lyricsContainer.style.display = 'none';
             lyricsContainer.dataset.songId = '';
         }
    });

     audioPlayer.addEventListener('canplay', () => {
        console.log("재생 가능"); // 필요시 주석 해제
        if (fullscreenPlayer.style.display === 'flex') {
           updateFullscreenUI(window.currentPlayingSong); // 재생 가능 시 UI 업데이트
        }
     });

    if (seekBar) {
        seekBar.addEventListener('input', function () {
            if (window.currentPlayingSong && !isNaN(audioPlayer.duration)) {
                const newTime = (seekBar.value / 100) * audioPlayer.duration;
                if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(newTime);
            }
        });

        seekBar.addEventListener('change', function () {
             if (window.currentPlayingSong && !isNaN(audioPlayer.duration)) {
                 const newTime = (seekBar.value / 100) * audioPlayer.duration;
                 audioPlayer.currentTime = newTime;
             }
        });
    }

    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) return "0:00";
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

}); // DOMContentLoaded 끝