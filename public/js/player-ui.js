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
    const seekBar = document.getElementById('seekBar');
    const currentTimeDisplay = document.getElementById('currentTime');
    const durationDisplay = document.getElementById('duration');
    const playPauseBtn = document.querySelector('.fullscreen-playpause');
    const prevBtn = document.querySelector('.fullscreen-prev');
    const nextBtn = document.querySelector('.fullscreen-next');
    const closeBtn = document.getElementById('closeFullscreenBtn');

    console.log('✅ player-ui.js 로드됨');

    if (!audioPlayer || !fullscreenPlayer) {
        console.error("❌ 필수 플레이어 요소를 찾을 수 없습니다!");
        return;
    }

    function updateFullscreenUI(song) {
        if (song) {
            const thumbnailUrl = `https://i.ytimg.com/vi/${song.videoID}/hqdefault.jpg`;
            if (fullscreenCover) {
                fullscreenCover.src = thumbnailUrl;
                fullscreenCover.onerror = function() { this.src = '/images/default_thumbnail.png'; }; // 기본 이미지 경로
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
        }
    }

    window.updateFullscreenUIIfNeeded = function(song) {
        if (fullscreenPlayer.style.display === 'flex') {
            updateFullscreenUI(song);
        } else {
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
        updateFullscreenUI(currentSong);
    };

    window.closeFullscreenPlayer = function () {
        // console.log("풀스크린 플레이어 닫는 중."); // 필요시 주석 해제
        fullscreenPlayer.classList.remove('active');
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
                navigator.mediaSession?.playbackState === 'playing' && navigator.mediaSession?.setActionHandler('previoustrack', () => {})()
                console.warn("window.playPrevious 함수를 찾을 수 없습니다.");
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (typeof window.playNext === 'function') {
                window.playNext();
            } else {
                 navigator.mediaSession?.playbackState === 'playing' && navigator.mediaSession?.setActionHandler('nexttrack', () => {})()
                console.warn("window.playNext 함수를 찾을 수 없습니다.");
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
            if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
            if (seekBar && !seekBar.matches(':active')) {
                seekBar.value = (audioPlayer.currentTime / audioPlayer.duration) * 100;
            }
        }
    });

    audioPlayer.addEventListener('loadedmetadata', function () {
        console.log("메타데이터 로드됨"); // 필요시 주석 해제
        if (fullscreenPlayer.style.display === 'flex') {
            updateFullscreenUI(window.currentPlayingSong);
        }
    });

    audioPlayer.addEventListener('loadstart', () => {
        console.log("로딩 시작"); // 필요시 주석 해제
         if (fullscreenPlayer.style.display === 'flex') {
            // 로딩 중 UI 업데이트 가능
         }
    });

     audioPlayer.addEventListener('canplay', () => {
        console.log("재생 가능"); // 필요시 주석 해제
        if (fullscreenPlayer.style.display === 'flex') {
           updateFullscreenUI(window.currentPlayingSong);
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