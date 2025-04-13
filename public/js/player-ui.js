let userClicked = false;
let currentSongIndex = 0;

window.addEventListener('DOMContentLoaded', function () {
  const audioPlayer = document.getElementById('audioPlayer');
  const seekBar = document.getElementById('seekBar');
  const currentTimeDisplay = document.getElementById('currentTime');
  const durationDisplay = document.getElementById('duration');
  const fullscreenPlayer = document.getElementById('fullscreenPlayer');
  const fullscreenCover = document.getElementById('fullscreenCover');
  const fullscreenTitle = document.getElementById('fullscreenTitle');
  const fullscreenArtist = document.getElementById('fullscreenArtist');
  const playPauseBtn = document.querySelector('.fullscreen-playpause');
  const prevBtn = document.querySelector('.fullscreen-prev');
  const nextBtn = document.querySelector('.fullscreen-next');
  const closeBtn = document.getElementById('closeFullscreenBtn');

  function updateFullscreenInfo() {
    const song = window.playlist?.[currentSongIndex];
    if (!song) return;

    fullscreenTitle.innerText = song.title;
    fullscreenArtist.innerText = song.channel;
    fullscreenCover.src = `https://img.youtube.com/vi/${song.videoID}/hqdefault.jpg`;
  }

  window.openFullscreenPlayer = function () {
    if (!userClicked) return;

    fullscreenPlayer.style.display = 'flex';
    requestAnimationFrame(() => {
      fullscreenPlayer.classList.add('active');
    });
    updateFullscreenInfo();
  };

  window.closeFullscreenPlayer = function () {
    fullscreenPlayer.classList.remove('active');
    setTimeout(() => {
      fullscreenPlayer.style.display = 'none';
    }, 300);
  };

  document.getElementById('audioPlayerContainer')?.addEventListener('click', () => {
    userClicked = true;
    openFullscreenPlayer();
  });

  closeBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    closeFullscreenPlayer();
  });

  fullscreenPlayer?.addEventListener('click', function (e) {
    if (e.target === fullscreenPlayer) {
      closeFullscreenPlayer();
    }
  });

  fullscreenCover?.addEventListener('click', function (e) {
    e.stopPropagation();
    togglePlay();
  });

  playPauseBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    togglePlay();
  });

  function togglePlay() {
    if (audioPlayer.paused) {
      audioPlayer.play();
    } else {
      audioPlayer.pause();
    }
  }

  audioPlayer.addEventListener('play', () => {
    playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
  });

  audioPlayer.addEventListener('pause', () => {
    playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
  });

  prevBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    playPrevious();
  });

  nextBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    playNext();
  });

  window.playPrevious = function () {
    if (currentSongIndex > 0) {
      window.playSong(currentSongIndex - 1);
    }
  };

  window.playNext = function () {
    currentSongIndex++;
    if (currentSongIndex >= window.playlist.length) {
      location.reload();
      return;
    }
    window.playSong(currentSongIndex);
  };

  audioPlayer.addEventListener('ended', window.playNext);

  // Seek bar updates
  audioPlayer.addEventListener('timeupdate', function () {
    if (!audioPlayer.duration) return;
    seekBar.value = (audioPlayer.currentTime / audioPlayer.duration) * 100;
    currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
  });

  audioPlayer.addEventListener('loadedmetadata', function () {
    seekBar.value = 0;
    durationDisplay.textContent = formatTime(audioPlayer.duration);
  });

  seekBar.addEventListener('input', function () {
    if (audioPlayer.duration) {
      audioPlayer.currentTime = (seekBar.value / 100) * audioPlayer.duration;
    }
  });

  function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
  }

  // 현재 곡 인덱스 변경 감지용
  const originalPlaySong = window.playSong;
  window.playSong = function (index) {
    currentSongIndex = index;
    originalPlaySong(index);
    updateFullscreenInfo();
  };
});
