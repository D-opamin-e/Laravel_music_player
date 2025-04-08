
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>
<h2>🎵 Laravel 기반으로 <a href="https://github.com/D-opamin-e/music_player" target="_blank">music_player</a> 리빌드 및 디자인 리뉴얼</h2>
<p>기존 프로젝트를 <strong>Laravel 프레임워크</strong>로 재구성하고, 전체 UI를 새롭게 디자인하였습니다.</p>

<hr>

<h2>📌 수정 내역</h2>

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
    - <code>2025-04-09</code>일 추가 완료 | <code>찜 노래 필터링 기능 추가</code> 목표 
  <li>매핑 기능 고도화<br>
    - 기존: <code>.txt</code> 기반<br>
    - 개선: <strong>DB 테이블 기반</strong> 관리
  </li>
</ol>

<hr>

<p>
  <img 
    src="https://media.discordapp.net/attachments/895639765402132490/1358328768153784372/A8BBB9F9-B703-4445-BA09-807CEDD224B8.jpg?ex=67f371be&is=67f2203e&hm=ece68c11863bb3a64483b65861b1d6701fbb890ffa96df3655730aa1ca4e77b9&" 
    alt="좌측 Laravel | 우측 구형"
    style="max-width: 100%; border-radius: 10px;"
  >
</p>
<p><em>좌측: Laravel 기반 리뉴얼 버전 | 우측: 기존 프로젝트</em></p>