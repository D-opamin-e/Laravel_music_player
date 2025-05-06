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
let currentLyricsData = []; // 불러온 가사 데이터를 저장할 배열
let currentHighlightedLyricIndex = -1; // 현재 강조된 가사 라인 중 첫 번째 라인의 인덱스 (스크롤 대상 등) - 단일 인덱스 추적 (스크롤 기준)
let currentlyHighlightedIndices = []; // 현재 강조된 모든 가사 라인의 인덱스 배열 (동일 시간대 가사 처리용) <--- 새로 추가된 변수

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
            lyricsContainer.style.top = '110px'; // 필요에 따라 위치 조정
            lyricsContainer.style.left = '20px';
            lyricsContainer.style.right = '20px'; // 오른쪽 여백 추가
            lyricsContainer.style.maxHeight = '400px'; // 세로 최대 크기
            lyricsContainer.style.overflowY = 'auto'; // 스크롤 가능하게
            lyricsContainer.style.padding = '10px';
            lyricsContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.8)'; // 반투명 검정 배경
            lyricsContainer.style.color = 'rgba(255, 255, 255, 0.6)'; // 글자색 약간 투명하게 (기본)
            lyricsContainer.style.transition = 'opacity 0.3s ease-in-out, transform 0.3s ease-in-out'; // 나타나는 애니메이션 추가
            lyricsContainer.style.opacity = '0'; // 초기 투명 상태
            lyricsContainer.style.transform = 'translateY(20px)'; // 초기 위치 (아래로 살짝 내려놓기)
            lyricsContainer.style.pointerEvents = 'none'; // 숨김 상태일 때 클릭 방지
            fullscreenPlayer.appendChild(lyricsContainer);
        }
        // 초기에는 숨김 상태를 유지 (CSS 클래스로 제어할 수도 있음)
        lyricsContainer.classList.add('hidden'); // 숨김 상태를 위한 CSS 클래스 추가
        lyricsContainer.style.display = 'none'; // 일단 display none으로 완전히 숨김
        lyricsContainer.dataset.songId = ''; // 초기 songId 비움
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
                     // 가사 표시 후 현재 시간에 맞춰 강조 업데이트
                     if (currentLyricsData.length > 0) {
                         updateLyricHighlight(audioPlayer.currentTime);
                     }
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

    // --- 가사 데이터 로딩 및 파싱 수정 (백엔드가 JSON 배열 직접 반환하는 경우에 맞춤) ---
    function fetchLyrics(songId) {
        // dataset.songId는 문자열, songId는 window.currentPlayingSong.id (타입 확인 필요, 일단 문자열로 변환 비교)
        const currentSongIdStr = String(songId);
        const containerSongId = lyricsContainer.dataset.songId;

        // 가사 컨테이너에 저장된 곡 ID와 요청된 곡 ID가 다르거나, 현재 가사 데이터가 비어있을 경우에만 fetch
        if (containerSongId !== currentSongIdStr || currentLyricsData.length === 0) {
             console.log(`가사 불러오는 중: ${currentSongIdStr}`); // 로깅 추가

             // 로딩 중 메시지 표시
             lyricsContainer.innerHTML = '<p style="text-align: center;">가사 불러오는 중...</p>';
             // dataset.songId는 fetch 완료(성공/실패) 시 설정

             currentLyricsData = []; // 새로운 가사를 불러오기 전에 기존 데이터 초기화
             currentHighlightedLyricIndex = -1; // 강조 인덱스 초기화
             currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가

             fetch(`/lyrics/${currentSongIdStr}`)
                 .then(response => {
                     if (response.status === 404) {
                         console.warn(`가사 찾을 수 없음 (404): ${currentSongIdStr}`);
                         return response.json().then(data => {
                             showLyrics([{ time: 0, line: data.message || '가사를 찾을 수 없습니다.' }]);
                             lyricsContainer.dataset.songId = currentSongIdStr; // ID 저장
                             currentLyricsData = []; // 데이터 초기화
                             currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
                             throw new Error('Lyrics not found');
                         }).catch(() => {
                             showLyrics([{ time: 0, line: '가사를 찾을 수 없습니다.' }]);
                             lyricsContainer.dataset.songId = currentSongIdStr; // ID 저장
                             currentLyricsData = []; // 데이터 초기화
                             currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
                             throw new Error('Lyrics not found');
                         });
                     }
                     if (!response.ok) {
                          return response.json().then(data => {
                             console.error(`HTTP 오류 ${response.status}: ${data.message || response.statusText}`);
                             showLyrics([{ time: 0, line: data.message || `가사 불러오기 오류: ${response.status}` }]);
                             lyricsContainer.dataset.songId = currentSongIdStr; // ID 저장
                             currentLyricsData = []; // 데이터 초기화
                             currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
                             throw new Error(`HTTP error ${response.status}`);
                          }).catch(() => {
                                console.error(`HTTP 오류 ${response.status}: ${response.statusText}`);
                                showLyrics([{ time: 0, line: `가사 불러오기 오류: ${response.status}` }]);
                                lyricsContainer.dataset.songId = currentSongIdStr; // ID 저장
                                currentLyricsData = []; // 데이터 초기화
                                currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
                                throw new Error(`HTTP error ${response.status}`);
                           });
                     }
                     return response.json();
                 })
                 .then(parsedLyrics => {
                      // 받은 데이터가 예상 JSON 배열 형식인지 검증
                      if (Array.isArray(parsedLyrics) && parsedLyrics.every(line => typeof line.time === 'number' && typeof line.line === 'string')) {
                          currentLyricsData = parsedLyrics; // 파싱 성공 시 전역 변수에 저장
                          showLyrics(currentLyricsData); // 파싱된 데이터로 가사 표시 (여기서 DOM 요소 생성)
                          lyricsContainer.dataset.songId = currentSongIdStr; // 성공 시 ID 저장 확정
                          console.log(`가사 불러옴 및 파싱 완료: ${currentSongIdStr}, ${currentLyricsData.length} 줄.`);
                      } else {
                           console.error('불러온 가사 데이터 형식이 올바르지 않습니다 (JSON 배열 아님).', parsedLyrics);
                           showLyrics([{ time: 0, line: '가사 데이터 형식이 올바르지 않습니다.' }]);
                           lyricsContainer.dataset.songId = currentSongIdStr; // 데이터 오류 시에도 ID 저장
                           currentLyricsData = []; // 데이터 초기화
                           currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
                         }
                 })
                 .catch(error => {
                      if (error.message !== 'Lyrics not found' && !error.message.startsWith('HTTP error')) {
                            console.error('가사 불러오기 네트워크 오류:', error);
                            showLyrics([{ time: 0, line: '가사 불러오기 중 오류가 발생했습니다.' }]);
                         }
                         lyricsContainer.dataset.songId = currentSongIdStr; // 네트워크 오류 시에도 ID 저장
                         currentLyricsData = []; // 데이터 초기화
                         currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
                     });
        } else {
             console.log(`이미 불러온 가사: ${currentSongIdStr}`); // 이미 가사가 있는 경우 로깅
             // 이미 가사가 있으므로 fetch하지 않음. 저장된 데이터로 다시 표시만 함 (토글 후 내용 보이게)
             // showLyrics(currentLyricsData); // 필요시 주석 해제하여 다시 표시
             // 가사 컨테이너가 보일 때만 현재 시간에 맞춰 바로 강조 업데이트만 수행
             if (lyricsContainer && lyricsContainer.classList.contains('visible') && currentLyricsData.length > 0) {
                  updateLyricHighlight(audioPlayer.currentTime); // <--- 이미 불러온 가사도 강조 업데이트
             } else if (lyricsContainer && lyricsContainer.classList.contains('visible') && currentLyricsData.length === 0) {
                   // dataset.songId는 일치하지만 데이터 배열이 비어있는 경우 (예: 이전에 가사 없음 또는 오류)
                    console.warn(`이미 불러온 곡 (${currentSongIdStr}) 가사 데이터가 비어있습니다. 메시지 표시.`);
                    showLyrics([{ time: 0, line: '불러왔으나 표시할 가사가 없습니다.' }]);
             }
         }
    }

    // --- 가사 표시 방식 수정 (HTML 요소 생성) ---
    function showLyrics(lyricsArray) {
        if (!lyricsContainer) {
            console.error("showLyrics: 가사 컨테이너 요소를 찾을 수 없습니다.");
            return;
        }

        lyricsContainer.innerHTML = ''; // 기존 내용 모두 삭제
        currentHighlightedLyricIndex = -1; // 가사 새로 로드 시 강조 인덱스 초기화
        currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가

        // 가사 데이터가 배열이 아니거나 비어있거나 오류 메시지만 있는 경우를 포함
        if (!Array.isArray(lyricsArray) || lyricsArray.length === 0 || (lyricsArray.length === 1 && (lyricsArray[0].line === '가사를 찾을 수 없습니다.' || lyricsArray[0].line.startsWith('가사 불러오기 오류') || lyricsArray[0].line.startsWith('불러왔으나 표시할 가사가 없습니다')))) {
              console.warn("showLyrics: 표시할 유효한 가사 데이터가 없습니다.", lyricsArray);
             const messageElement = document.createElement('p');
             messageElement.style.textAlign = 'center';
             messageElement.style.color = 'rgba(255, 255, 255, 0.8)';
             messageElement.innerText = (lyricsArray && lyricsArray.length > 0) ? lyricsArray[0].line : '가사를 불러올 수 없습니다.'; // 받은 메시지 사용 또는 기본 메시지
             lyricsContainer.appendChild(messageElement);
             // currentHighlightedLyricIndex = -1; // 이미 초기화됨
             // currentlyHighlightedIndices = []; // 이미 초기화됨
             // currentLyricsData = []; // 데이터가 없거나 오류 메시지면 배열 비움 (fetchLyrics에서 처리)
             return;
         }

         // 유효한 가사 데이터가 있을 경우
         console.log("showLyrics: 가사 데이터 표시 시작", lyricsArray.length, "줄");

        lyricsArray.forEach((lyricLine, index) => {
            const lineElement = document.createElement('p');
            lineElement.innerText = lyricLine.line;
            lineElement.dataset.time = lyricLine.time; // 시간 정보를 data 속성에 저장
            lineElement.dataset.index = index; // 라인 인덱스도 data 속성에 저장 <--- 추가 (디버깅/참조용)
            lineElement.classList.add('lyric-line'); // 가사 라인임을 나타내는 클래스 추가
             // 클릭 시 해당 시간으로 이동하는 기능 추가
             lineElement.addEventListener('click', () => {
                 if (audioPlayer && !isNaN(lyricLine.time)) {
                      audioPlayer.currentTime = lyricLine.time;
                      console.log(`➡️ 가사 클릭: ${lyricLine.time}초로 이동`);
                      // 가사 클릭 후 재생 위치 변경 시, timeupdate 이벤트가 발생하여
                      // updateLyricHighlight가 자동으로 호출되어 강조가 업데이트됩니다.
                  }
              });
             lyricsContainer.appendChild(lineElement);
         });

         // currentHighlightedLyricIndex = -1; // 이미 초기화됨
          // 가사 로드 후 현재 재생 시간에 맞는 라인을 찾아서 강조
          if (currentLyricsData.length > 0) {
               updateLyricHighlight(audioPlayer.currentTime); // 현재 시간에 맞춰 가사 강조 업데이트
          }
     }

    // --- 시간 업데이트 시 가사 강조 로직 추가 ---
    audioPlayer.addEventListener('timeupdate', function () {
        const currentTime = audioPlayer.currentTime;

        if (fullscreenPlayer.style.display === 'flex' && !isNaN(audioPlayer.duration) && audioPlayer.duration > 0) {
            // UI 업데이트 (시간, 탐색 바) - 탐색바를 드래그 중이 아닐 때만 업데이트
             if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(currentTime);
             if (seekBar && !seekBar.matches(':active')) {
                 seekBar.value = (currentTime / audioPlayer.duration) * 100;
             }

            // 가사 영역이 보일 때만 강조 로직 실행
            if (lyricsContainer && lyricsContainer.classList.contains('visible') && currentLyricsData.length > 0) {
                 updateLyricHighlight(currentTime);
            }
        }
    });

    // --- 현재 시간에 맞는 가사 라인을 찾아 강조하는 함수 (동일 시간대 가사 처리 기능 포함) --- <--- 수정된 함수
    function updateLyricHighlight(currentTime) {
         // 가사 데이터 없거나 컨테이너 없으면 모든 강조 해제 및 상태 초기화
         if (currentLyricsData.length === 0 || !lyricsContainer) {
             if (lyricsContainer) {
                  Array.from(lyricsContainer.children).forEach(child => {
                      child.classList.remove('highlighted-lyric');
                      child.style.color = 'rgba(255, 255, 255, 0.6)'; // 기본 색상으로 되돌림
                  });
             }
              currentHighlightedLyricIndex = -1;
              currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화
             return;
         }

         let activeTime = -1; // 현재 시간대에 해당하는 가사의 시작 시간

         // 1. 현재 시간(currentTime)을 포함하는 가장 최신의 가사 시작 시간(activeTime) 찾기
         // 즉, currentLyricsData[i].time <= currentTime < currentLyricsData[i+1].time 인 i의 time
         // 또는 마지막 라인인 경우 currentLyricsData[마지막].time <= currentTime
         let foundIndex = -1; // currentTime을 넘어서는 첫 번째 가사 라인의 인덱스

         for (let i = 0; i < currentLyricsData.length; i++) {
             if (currentLyricsData[i].time > currentTime) {
                 foundIndex = i;
                 break; // currentTime을 넘어서는 첫 라인을 찾으면 바로 반복 종료
             }
         }

         // activeTime 결정
         if (foundIndex === 0) {
             // 현재 시간이 첫 가사 라인의 시작 시간보다 이전인 경우 (-무한대 시간대의 가사)
             activeTime = -1; // 강조할 시간대 없음
         } else if (foundIndex === -1) {
             // 현재 시간이 모든 가사 라인의 시작 시간보다 같거나 큰 경우 (마지막 시간대의 가사)
             activeTime = currentLyricsData[currentLyricsData.length - 1].time; // 마지막 가사 라인의 시간
         } else {
             // 현재 시간이 중간 어딘가에 있는 경우
             // currentTime을 넘어서는 첫 라인 (foundIndex) 이전의 라인 (foundIndex - 1)의 시간
             activeTime = currentLyricsData[foundIndex - 1].time;
         }


         // 2. activeTime과 동일한 시작 시간을 가진 모든 가사 라인의 인덱스 찾기
         let indicesToHighlight = [];
         if (activeTime !== -1) {
             for (let i = 0; i < currentLyricsData.length; i++) {
                 // 현재 라인의 시간이 activeTime과 정확히 일치하는 경우
                 // 소수점 비교 오류를 줄이기 위해 약간의 오차 허용 (옵션)
                 // const timeDiff = Math.abs(currentLyricsData[i].time - activeTime);
                 // if (timeDiff < 0.01) { // 0.01초 오차 허용
                 //     indicesToHighlight.push(i);
                 // }
                 // 일단 정확히 일치하는 경우로 처리 (API 결과는 보통 정확하므로)
                 if (currentLyricsData[i].time === activeTime) {
                      indicesToHighlight.push(i);
                 }
                 // activeTime 이후의 다른 시간대 라인이 나타나면 더 이상 동일 시간대 라인이 아님
                 // (가사 데이터가 시간 순서대로 정렬되어 있다는 가정 하에 유효한 최적화)
                 // activeTime보다 0.01초 이상 큰 시간이 나타나면 종료
                 if (currentLyricsData[i].time > activeTime + 0.01 && indicesToHighlight.length > 0) {
                      break;
                 }
             }
         }

         // 3. 이전 강조 상태와 새 강조 상태 비교하여 DOM 업데이트 (하이라이트 클래스 추가/제거)
         const prevHighlightedIndices = currentlyHighlightedIndices; // 이전 강조 인덱스 배열 저장
         currentlyHighlightedIndices = indicesToHighlight; // 현재 강조 인덱스 배열 업데이트

         const lyricsElements = lyricsContainer.children; // 가사 DOM 요소들 (HTMLCollection)

         // 이전에는 강조되었지만 현재는 강조되지 않아야 할 라인들의 강조 해제
         // 이전 강조 인덱스 배열을 순회
         prevHighlightedIndices.forEach(prevIndex => {
             // 현재 강조 인덱스 배열에 포함되지 않는 이전 인덱스이고, 해당 DOM 요소가 존재할 경우 (prevIndex < lyricsElements.length)
             if (!currentlyHighlightedIndices.includes(prevIndex) && prevIndex < lyricsElements.length) {
                 lyricsElements[prevIndex].classList.remove('highlighted-lyric');
                 lyricsElements[prevIndex].style.color = 'rgba(255, 255, 255, 0.6)'; // 기본 색상으로 되돌림
             }
         });

         // 현재 강조되어야 하지만 이전에는 강조되지 않았던 라인들의 강조 설정
         // 현재 강조 인덱스 배열을 순회
         currentlyHighlightedIndices.forEach(currentIndex => {
              // 이전 강조 인덱스 배열에 포함되지 않는 현재 인덱스이고, 해당 DOM 요소가 존재할 경우 (currentIndex < lyricsElements.length)
              // 여기서 lyricsElements.elements.length 오타 수정 -> lyricsElements.length
              if (!prevHighlightedIndices.includes(currentIndex) && currentIndex < lyricsElements.length) {
                  lyricsElements[currentIndex].classList.add('highlighted-lyric');
                  lyricsElements[currentIndex].style.color = 'rgba(255, 255, 255, 1)'; // 강조 색상 (불투명 흰색)
              }
         });


         // 4. 자동 스크롤 기능
         // 강조된 라인이 하나라도 있으면, 그 중 첫 번째 라인으로 스크롤
         if (currentlyHighlightedIndices.length > 0) {
             const firstHighlightIndex = currentlyHighlightedIndices[0]; // 동일 시간대 라인 중 첫 번째 인덱스
             const currentHighlightedElement = lyricsElements[firstHighlightIndex]; // 해당 DOM 요소

             if (currentHighlightedElement) {
                 const containerHeight = lyricsContainer.clientHeight;
                 const elementTop = currentHighlightedElement.offsetTop;
                 // 요소의 상단에서 컨테이너 뷰포트 상단으로부터 일정 마진을 두고 스크롤
                 const scrollMargin = containerHeight / 3; // 뷰포트 상단에서 1/3 지점에 오도록
                 const targetScrollTop = elementTop - scrollMargin;

                 // 부드러운 스크롤 적용
                 lyricsContainer.scrollTo({
                     top: targetScrollTop,
                     behavior: 'smooth'
                 });
             }
         }

         // 현재 강조된 라인 중 첫 번째 라인의 인덱스를 업데이트 (스크롤 기준 등)
         currentHighlightedLyricIndex = currentlyHighlightedIndices.length > 0 ? currentlyHighlightedIndices[0] : -1;
     }
    // --- 가사 강조 로직 끝 ---


    if (!audioPlayer || !fullscreenPlayer) {
        console.error("❌ 필수 플레이어 요소를 찾을 수 없습니다!");
        return;
    }

    // 재생/일시정지 버튼 아이콘만 업데이트하는 함수
    function updatePlayPauseButtonUI() {
         if (playPauseBtn && audioPlayer) {
              playPauseBtn.innerHTML = audioPlayer.paused ? '<i class="fas fa-play"></i>' : '<i class="fas fa-pause"></i>';
          }
    }


    // UI 업데이트 함수: 가사 데이터 초기화 로직 조건 수정 및 타입 비교 개선
    // 이 함수는 주로 곡이 변경되거나 플레이어가 열릴 때 전체 UI를 업데이트하는 데 사용
    function updateFullscreenUI(song) {
         const prevSongIdStr = lyricsContainer ? String(lyricsContainer.dataset.songId) : ''; // 이전 곡 ID 문자열
         const currentSongIdStr = song ? String(song.id) : ''; // 현재 곡 ID 문자열

         // 이전 곡 ID 문자열과 현재 곡 ID 문자열이 다르면 새로운 곡으로 간주
         const isNewSong = prevSongIdStr !== currentSongIdStr;

         console.log(`[UI Update] 호출됨. 현재 곡 ID: ${currentSongIdStr}, 이전 곡 ID: ${prevSongIdStr}, 새로운 곡? ${isNewSong}`);

         if (song) {
             const thumbnailUrl = `https://i.ytimg.com/vi/${song.videoID}/maxresdefault.jpg`;
             if (fullscreenCover) {
                 fullscreenCover.src = thumbnailUrl;
                 fullscreenCover.onerror = function() { this.src = '/images/maxresdefault.png'; }; // 기본 이미지 경로
             }
             if (fullscreenTitle) fullscreenTitle.innerText = song.title;
             if (fullscreenArtist) fullscreenArtist.innerText = song.channel;

             const duration = audioPlayer.duration;
             // 총 시간, 현재 시간, 탐색 바 업데이트
             if (!isNaN(duration) && duration > 0) {
                 if (durationDisplay) durationDisplay.textContent = formatTime(duration);
                 if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
                 if (seekBar) {
                      seekBar.value = (audioPlayer.currentTime / duration) * 100; // 로드 시점 현재 위치 반영
                      seekBar.disabled = false;
                  }
             } else {
                 if (durationDisplay) durationDisplay.textContent = formatTime(0);
                 if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(0);
                 if (seekBar) {
                     seekBar.value = 0;
                     seekBar.disabled = true;
                 }
             }

             // --- 가사 관련 상태 초기화 (새로운 곡이 로드될 때만) ---
             if (isNewSong && lyricsContainer) {
                 console.log(`[UI Update] 새로운 곡 감지 (${prevSongIdStr} -> ${currentSongIdStr}). 가사 데이터/UI 초기화.`);

                 lyricsContainer.innerHTML = ''; // 이전 가사 내용 지우기
                 // 가사 영역 숨김 상태로 되돌림 (CSS 클래스 및 스타일 사용)
                 lyricsContainer.classList.remove('visible');
                 lyricsContainer.classList.add('hidden');
                 lyricsContainer.style.opacity = '0';
                 lyricsContainer.style.transform = 'translateY(20px)';
                 lyricsContainer.style.pointerEvents = 'none';
                 // Transitionend 리스너는 window.updateFullscreenUIIfNeeded나 closeFullscreenPlayer에서 담당 (display: none 처리)

                 lyricsContainer.dataset.songId = currentSongIdStr; // 새로운 곡 ID 문자열 저장

                 currentLyricsData = []; // 가사 데이터 배열 초기화
                 currentHighlightedLyricIndex = -1; // 강조 인덱스 초기화
                 currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가

             }
         } else { // 곡 정보가 없을 때 (플레이어가 비워질 때 호출되는 경우, song === null)
              console.log(`[UI Update] song 객체 null. UI 및 가사 초기화.`); // 디버깅용 로그
              if (fullscreenCover) fullscreenCover.src = '';
              if (fullscreenTitle) fullscreenTitle.innerText = '선택된 곡 없음';
              if (fullscreenArtist) fullscreenArtist.innerText = '';
              if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(0);
              if (durationDisplay) durationDisplay.textContent = formatTime(0);
              if (seekBar) {
                  seekBar.value = 0;
                  seekBar.disabled = true;
              }
               // 곡이 없으면 가사 컨테이너 숨김 및 상태 초기화 (이 부분은 항상 실행)
               if (lyricsContainer) {
                   lyricsContainer.innerHTML = ''; // 이전 가사 내용 지우기
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
                    }, { once: true }); // 리스너 한 번만 실행되도록 변경
               }
               currentLyricsData = []; // 가사 데이터 배열 초기화
               currentHighlightedLyricIndex = -1; // 강조 인덱스 초기화
               currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
          }
     }

    // 풀스크린이 열려있는 경우에만 UI를 업데이트하는 함수
    // 이 함수는 window.currentPlayingSong이 변경될 때 외부 (welcome.blade.php 등)에서 호출될 것으로 예상됨.
    // 또는 풀스크린을 열 때 호출됨.
    window.updateFullscreenUIIfNeeded = function(song) {
         console.log(`[UI If Needed] 호출됨. 풀스크린 열림 상태: ${fullscreenPlayer.style.display === 'flex'}`);
         if (fullscreenPlayer.style.display === 'flex') {
             // 풀스크린이 열려있으면 updateFullscreenUI 호출 (새 곡이면 가사 초기화 포함)
             updateFullscreenUI(song);
             // updateFullscreenUI 내부에서 새 곡이 아니면 가사 초기화를 건너뛰므로,
             // 가사 영역이 보이고 데이터가 있다면 현재 시간에 맞춰 강조를 업데이트합니다.
             if (song && lyricsContainer && lyricsContainer.classList.contains('visible') && currentLyricsData.length > 0) {
                  console.log("[UI If Needed] 풀스크린 열림 & 가사 보임. 현재 시간으로 강조 업데이트.");
                  updateLyricHighlight(audioPlayer.currentTime);
             } else if (song && lyricsContainer && lyricsContainer.classList.contains('visible') && currentLyricsData.length === 0) {
                    // 풀스크린 열려있고 가사 컨테이너는 visible 상태인데 데이터가 비어있는 경우
                    // 이전에 가사 불러오기 실패했거나 가사가 없는 곡일 수 있음.
                    // 필요하다면 여기서 가사 불러오기(fetchLyrics)를 다시 시도할 수 있습니다.
                    console.warn("[UI If Needed] 가사 컨테이너 보임 상태이나 가사 데이터가 비어있습니다. (가사 없음?)");
                    // fetchLyrics(song.id); // 필요시 주석 해제하여 다시 시도
              }
         } else {
             // 풀스크린이 닫혔을 때 (즉, 이 함수가 풀스크린이 닫힌 상태에서 호출되었을 때)
             // 가사 영역도 확실히 숨김 처리 및 상태 초기화
              console.log("[UI If Needed] 풀스크린 닫힘. 가사 초기화 및 숨김 처리.");
             if (lyricsContainer) {
                  lyricsContainer.innerHTML = ''; // 내용만 지움
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
                    }, { once: true }); // 리스너 한 번만 실행되도록 변경
               }
               currentLyricsData = []; // 가사 데이터 배열 초기화
               currentHighlightedLyricIndex = -1; // 강조 인덱스 초기화
               currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가
          }
     };

    window.openFullscreenPlayer = function () {
        const currentSong = window.currentPlayingSong;

        if (!currentSong) {
            console.warn("풀스크린 열 수 없음: 현재 재생 중인 곡이 없습니다.");
            return;
        }

        console.log("🚀 풀스크린 플레이어 여는 중 - 곡:", currentSong.title);
        fullscreenPlayer.style.display = 'flex';
        requestAnimationFrame(() => {
            fullscreenPlayer.classList.add('active');
        });
        // 풀스크린 열 때 UI 업데이트 (새 곡이면 가사 컨테이너 초기화/숨김 처리 포함)
        updateFullscreenUI(currentSong);

        // ⭐ 새로 추가: 풀스크린 열릴 때 현재 재생 상태에 맞춰 재생/일시정지 버튼 업데이트
        updatePlayPauseButtonUI(); // 이 줄을 추가합니다.

        // 풀스크린 열릴 때 현재 시간에 맞춰 가사 강조 업데이트 (가사 영역이 보인다면)
         if (lyricsContainer && lyricsContainer.classList.contains('visible') && currentLyricsData.length > 0) {
               updateLyricHighlight(audioPlayer.currentTime);
          }
    };

    window.closeFullscreenPlayer = function () {
        console.log("🚪 풀스크린 플레이어 닫는 중."); // 필요시 주석 해제
        fullscreenPlayer.classList.remove('active');
        // 풀스크린 닫을 때 가사 영역도 숨김 및 상태 초기화 (이전 코드 유지)
         if (lyricsContainer) {
              lyricsContainer.innerHTML = ''; // 내용만 지움
              lyricsContainer.classList.remove('visible');
              lyricsContainer.classList.add('hidden'); // CSS transition 트리거
              lyricsContainer.style.opacity = '0';
              lyricsContainer.style.transform = 'translateY(20px)';
              lyricsContainer.style.pointerEvents = 'none';

               // 트랜지션 완료 후 완전히 숨김
               lyricsContainer.addEventListener('transitionend', function handler() {
                    if (lyricsContainer.classList.contains('hidden')) {
                        lyricsContainer.style.display = 'none'; // 트랜지션 완료 후 display none
                        lyricsContainer.removeEventListener('transitionend', handler);
                    }
                }, { once: true }); // 리스너 한 번만 실행되도록 변경
           }
           currentLyricsData = []; // 가사 데이터 배열 초기화
           currentHighlightedLyricIndex = -1; // 강조 인덱스 초기화
           currentlyHighlightedIndices = []; // 강조 인덱스 배열 초기화 <--- 추가


        setTimeout(() => {
            fullscreenPlayer.style.display = 'none';
        }, 300); // CSS transition 시간과 일치
    };

    const audioPlayerContainer = document.getElementById('audioPlayerContainer');
    if (audioPlayerContainer) {
        // 미니 플레이어 클릭 시 풀스크린 열기
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
        // 풀스크린 배경 클릭 시 닫히도록 (가사 영역 제외)
        // e.target이 가사 컨테이너 자체가 아니고, 가사 컨테이너 안에 포함된 요소도 아닌 경우
        fullscreenPlayer.addEventListener('click', function (e) {
             if (lyricsContainer && !lyricsContainer.contains(e.target) && e.target !== lyricsContainer) {
                 window.closeFullscreenPlayer();
             }
         });
    }

    // --- 시간 포맷팅 헬퍼 함수 ---
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        const paddedSeconds = remainingSeconds < 10 ? '0' + remainingSeconds : remainingSeconds;
        return `${minutes}:${paddedSeconds}`;
    }

    // 윈도우 전역에 노출하여 welcome.blade.php에서 호출 가능하도록 함
    window.updatePlayPauseButtonUI = updatePlayPauseButtonUI; // 재생/일시정지 아이콘 업데이트 함수 노출
    window.updateFullscreenUI = updateFullscreenUI; // 풀스크린 UI 업데이트 함수 노출 (새 곡 로드 시)
    // window.updateFullscreenUIIfNeeded = updateFullscreenUIIfNeeded; // 이미 위에서 정의됨
});