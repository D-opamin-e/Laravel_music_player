
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>
<h2>🎵 Laravel 기반으로 <a href="https://github.com/D-opamin-e/music_player" target="_blank">music_player</a> 리빌드 및 디자인 리뉴얼</h2>
<p>기존 프로젝트를 <strong>Laravel 프레임워크</strong>로 재구성하고, 전체 UI를 새롭게 디자인하였습니다.</p>

<hr>

<h2>📌 수정 내역</h2>

<details>
  <summary><strong>2025-08-07</strong></summary>
  <ul>
      <li>➕ <strong>ADD</strong>: 재생 횟수(오름차순) 정렬 기능 추가</li>
       <li>🛠️ <strong>Fixed</strong>: 음악 재생 관련, 404 혹은 부가 에러에 관련 대응 코드 추가</li>
  </ul>
</details>

<details>
  <summary><strong>2025-05-06</strong></summary>
  <ul>
      <li>➕ <strong>ADD</strong>: python의 whisper와 gemeni를 활용한 가사 싱크 관련 코드</li>
       <li>🛠️ <strong>Fixed</strong>: player-ui에 동일한 time에 대한 대응 방안 수정</li>
  </ul>
</details>


<details>
  <summary><strong>2025-05-03</strong></summary>
  <ul>
      <li>➕ <strong>ADD</strong>: 가사 기능에 scroll 기능과 강조 기능 추가</li>
       <li>➕ <strong>ADD</strong>: Whisper를 활용한 1차적인 가사 및 싱크 추출 및 저장</li>
       <li>🛠️ <strong>Fixed</strong>: 가사 레이아웃 제작 및 가사 특정 부분 클릭 시, 해당 부분 이동</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-30</strong></summary>
  <ul>
      <li>➕ <strong>ADD</strong>: fullscreenplayer에 가사 기능 추가</li>
       <li>🛠️ <strong>Fixed</strong>: 더 정확한 BPM 측정을 위해 Madmom 모듈 도입</li>
       <li>🛠️ <strong>Fixed</strong>: BPM 측정 작업을 큐로 처리</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-21</strong></summary>
  <ul>
      <li>➕ <strong>ADD</strong>: 로그인/회원가입 기능 추가</li>
          <li>➕ <strong>ADD</strong>: 현재 재생 중인 곡 항목으로 자동 스크롤</li>
       <li>🛠️ <strong>Fixed</strong>: 유저별 곡 찜 기능 구현</li>
       <li>🛠️ <strong>Fixed</strong>: Media Session API 썸네일 비율 수정 16:9 ➡️ 1:1</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-15</strong></summary>
  <ul>
        <li>🛠️ <strong>Fixed</strong>: 곡 검색 후, fullscreenplayer로 재생시, 커버이미지, 타이틀, 가수 미일치 버그 수정</li>
         <li>🛠️ <strong>Fixed</strong>: 전역변수 관련 수정</li>
                  <li>🛠️ <strong>Fixed</strong>: updatePlaylist시, BPM.py 미작동 버그 수정</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-14</strong></summary>
  <ul>
      <li>➕ <strong>ADD</strong>: 심플UI(fullscreenPlayer) 추가</li>
      <img src="http://kkk234454.duckdns.org/full_simple.png" width="430px" heigh="932px">
        <li>🛠️ <strong>Fixed</strong>: audioPlayerContainer의 coverImage 위치 이동</li>
         <li>🛠️ <strong>Fixed</strong>: yt-dlp 관련 재생목록 업데이트 미작동 코드 수정</li>
         <li>🛠️ <strong>Fixed</strong>: fullscreenPlayer 및 html, body 의 주요 CSS 수정</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-12</strong></summary>
  <ul>
    <li>➕ <strong>ADD</strong>: media Session API 도입</li>
    <li>➕ <strong>ADD</strong>: 메뉴(햄버거바) 추가 - 찜 목록, 재생목록 업데이트 항목을 메뉴로 이동</li>
        <li>🛠️ <strong>Fixed</strong>: 음원 썸네일 우측 구성</li>
         <li>🛠️ <strong>Fixed</strong>: 상단 UI 개선(검색 아이콘 관련 | 클릭시 애니메이션 효과 추가)</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-10</strong></summary>
  <ul>
    <li>🛠️ <strong>Fixed</strong>: `UI'개선</li>
        <li>➕ <strong>ADD</strong>:기기 별로(찜) 기능 필터링</li>
            <li>➕ <strong>ADD</strong>: 재생중인 곡의 Youtube videoid를 활용한 Storage Cover(썸네일)  추가</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-09</strong></summary>
  <ul>
    <li>➕ <strong>ADD</strong>: 기기별로 특정 노래 찜(좋아요)기능</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-08</strong></summary>
  <ul>
    <li>➕ <strong>ADD</strong>: 실시간 재생 횟수 업데이트 기능 구현</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-07</strong></summary>
  <ul>
    <li>🛠️ <strong>Fixed</strong>: `playNext` 함수에 전체 리스트 이어서 재생되도록 로직 추가</li>
    <li>🛠️ <strong>Fixed</strong>: 검색된 곡들을 모두 들은 후, 전체 플레이리스트에서 이어서 다음 곡을 재생하도록 해결</li>
    <li>🛠️ <strong>Fixed</strong>: 제목 검색 시 결과가 출력되지 않던 문제 해결</li>
    <li>🛠️ <strong>Fixed</strong>: 검색창에 입력된 내용을 모두 지울 경우, 플레이리스트가 사라지던 문제 수정<br>
    → 검색어가 비어있을 때는 전체 곡을 다시 불러오도록 개선</li>
  </ul>
</details>

<details>
  <summary><strong>2025-04-06</strong></summary>
  <ul>
    <li>🔍 검색 관련 매핑 기능 추가 <em>(추후 추가 수정 예정)</em></li>
  </ul>
  <ul>
    <li>🔍 검색 기능 구현</li>
    <li>📄 재생목록 업데이트 기능 구현</li>
    <li>🔁 재생 횟수 업데이트 기능 구현</li>
  </ul>
  <ul>
    <li>🔧 전체 업데이트 기능 구현</li>
    <li>🎨 디자인 리뉴얼</li>
    <li>🎧 재생 횟수 UI 우측에 표기</li>
  </ul>
</details>

<hr>

<h2>✨ 개선 및 구현 예정 기능</h2>
<ol>
  <li><strong>가사 검색 기능 개선</strong><br>
    - 1차: <code>replace</code> 기반 처리<br>
    - 2차: NoSQL 도입 고려
  </li>
  <li><s>실시간 재생 횟수 업데이트 기능</s></li>
  - <code>2025-04-08</code>일 개선 완료 
  <li>상/하단 UI 개선</li>
<li><s>검색된 노래 재생 후 후처리 기능</s></li>
    - <code>2025-04-07</code>일 개선 완료 
  <li><s>특정 노래 <code>찜, 좋아요</code> 기능</s></li>
        - <s><code>2025-04-09</code>일 추가 완료 | <code>찜 노래 필터링 기능 추가</code> 목표 </s>
  <li>매핑 기능 고도화<br>
    - 기존: <code>.txt</code> 기반<br>
    - 개선: <strong>DB 테이블 기반</strong> 관리
  </li>
    <li>곡 드래그시 곡 순서 이동 기능</li>
</ol>
<hr>