# DB에 있는 가사를 JSON 파일로 내보내는 스크립트
import mysql.connector # MariaDB/MySQL 연결을 위한 라이브러리 임포트
import json
import os
import re

# --- 설정 (MariaDB 용) ---
# !!! 아래 정보를 사용하시는 MariaDB 접속 정보로 반드시 수정하세요 !!!
DB_CONFIG = {
    'host': 'kkk234454.duckdns.org', # 데이터베이스 서버 주소 (예: 'localhost', '192.168.1.100')
    'user': 'root', # MariaDB 사용자 이름
    'password': 'password', # MariaDB 비밀번호
    'database': 'music_player', # 연결할 데이터베이스 이름
    'charset': 'utf8mb4', # 문자셋 설정 (가사 등에 한글/이모지 등 다양한 문자가 포함될 경우 중요)
    'collation': 'utf8mb4_general_ci' # <-- 이 줄을 추가하여 지원되는 콜레이션 명시
}
TABLE_NAME = 'songs' # 데이터를 가져올 테이블 이름
TITLE_COLUMN = 'title' # 파일 이름으로 사용할 컬럼 이름 (JSON 파일 이름에만 사용)
LYRICS_COLUMN = 'lyrics' # JSON 파일에 저장할 가사 컬럼 이름
OUTPUT_DIRECTORY = 'DB_lyrics' # JSON 파일을 저장할 디렉토리 이름

# --- 파일 이름 정제를 위한 헬퍼 함수 ---
def sanitize_filename(title):
    """
    문자열을 파일 이름으로 사용하기 안전하게 정제합니다.
    일반적인 파일 시스템에서 허용되지 않는 문자를 제거합니다.
    """
    if not title:
        return "untitled" # 제목이 없는 경우 기본 이름

    # 파일 이름에 사용할 수 없는 문자들: < > : " / \ | ? * 그리고 제어 문자 제거
    # 이 문자들을 밑줄(_)로 대체합니다.
    # 유니코드 공백 문자(예: U+00A0)도 포함하여 제거하거나 대체합니다.
    # 여기서는 파일 시스템에서 안전한 문자만 남기고 나머지를 _로 대체하는 방식을 사용합니다.
    # 더 엄격하게 하려면 허용할 문자 범위(a-z, A-Z, 0-9, -, _)만 남기고 제거할 수 있습니다.
    sanitized = re.sub(r'[^\w\s.-]', '_', str(title)) # 알파벳, 숫자, 공백, 마침표, 하이픈 외 모두 _로 대체
    sanitized = re.sub(r'[\s]+', ' ', sanitized) # 여러 공백을 하나의 공백으로 줄임
    # 앞뒤 공백 제거
    sanitized = sanitized.strip()
    # 파일 이름 시작/끝에 올 수 없는 문자 처리 (예: 마침표, 하이픈 - OS마다 다를 수 있음)
    sanitized = re.sub(r'^[.-]+', '_', sanitized)
    sanitized = re.sub(r'[.-]+$', '_', sanitized)

    # 정제 후 파일 이름이 비어 있지 않도록 확인
    if not sanitized:
        return "untitled_fallback" # 정제 후 비어있다면 대체 이름 제공

    # 선택 사항: OS 문제 방지를 위해 길이를 제한할 수 있습니다. (예: 확장자 제외 최대 200자)
    # sanitized = sanitized[:200] # 파일 이름 길이를 200자로 제한 (확장자 .json 포함하면 204자)
    # 파일 시스템 제한에 따라 더 짧게 필요할 수도 있습니다.

    return sanitized

# --- 메인 스크립트 (MariaDB 연결 및 배열 형식 저장) ---
def export_lyrics_to_json_mariadb(db_config, table_name, title_col, lyrics_col, output_dir):
    conn = None # 연결 객체 초기화
    cursor = None # 커서 객체 초기화
    try:
        # MariaDB 데이터베이스에 연결
        print(f"MariaDB 데이터베이스 '{db_config['database']}'에 연결 중...")
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        print("데이터베이스 연결 성공.")

        # 출력 디렉토리가 없으면 생성
        if not os.path.exists(output_dir):
            os.makedirs(output_dir)
            print(f"출력 디렉토리 생성: {output_dir}")

        # 테이블에서 title과 lyrics 선택
        query = f"SELECT `{title_col}`, `{lyrics_col}` FROM `{table_name}`"
        print(f"쿼리 실행 중: {query}")
        cursor.execute(query)

        # 모든 행 가져오기
        rows = cursor.fetchall()

        if not rows:
            print(f"테이블 '{table_name}'에서 노래를 찾을 수 없습니다.")
            return

        print(f"총 {len(rows)}개의 노래를 찾았습니다. 가사 내보내기 중 (배열 형식)...")

        processed_count = 0 # 처리 시도한 노래 수
        skipped_count = 0 # 파일 이미 존재하거나 데이터 누락 등으로 건너뛴 노래 수
        error_count = 0 # 처리 중 오류 발생한 노래 수
        success_count = 0 # 성공적으로 내보낸 노래 수


        # 각 행을 반복하며 가사를 JSON 배열 파일로 저장
        for row_index, row in enumerate(rows): # 행 인덱스도 함께 가져옴
            processed_count += 1 # 새 노래 처리 시도

            title, lyrics = row

            # MariaDB Connector에서 가져온 데이터 디코딩 (Bytes -> String)
            current_charset = db_config.get('charset', 'utf-8')
            try:
                if isinstance(title, bytes):
                    title = title.decode(current_charset)
                if isinstance(lyrics, bytes):
                    lyrics = lyrics.decode(current_charset)
            except Exception as e:
                 print(f"[경고] 인코딩 오류 발생, 이 행을 건너뜁니다 (인덱스 {row_index}, 데이터: {row}): {e}")
                 skipped_count += 1
                 continue


            # 제목이나 가사가 누락된 행은 건너뛰기
            if not title or not lyrics:
                print(f"[경고] 제목 또는 가사가 누락된 행을 건너뜁니다 (인덱스 {row_index}): {row}")
                skipped_count += 1 # 누락된 데이터도 건너뛴 것으로 카운트
                continue


            # 파일 이름으로 사용할 제목 정제
            safe_title = sanitize_filename(title)
            # 정제 후에도 파일 이름이 너무 길면 잘라냅니다.
            if len(safe_title) > 200:
                 safe_title = safe_title[:200]
            # sanitize_filename 함수에서 이미 비어있지 않도록 보장하지만, 혹시 모를 상황 대비
            if not safe_title:
                 safe_title = f"untitled_row_{row_index}" # 대체 이름 (행 인덱스 사용)

            filename = f"{safe_title}.json"
            file_path = os.path.join(output_dir, filename)

            # --- 파일이 이미 존재하는지 확인하고 건너뛰는 로직 추가 ---
            if os.path.exists(file_path):
                # print(f"[정보] 파일이 이미 존재합니다. 건너뛰기: {file_path}") # 너무 많은 출력을 방지하기 위해 주석 처리 또는 레벨 조정 가능
                skipped_count += 1 # 이미 존재하는 파일 건너뛰기 카운트
                continue # 이 노래 처리를 중단하고 다음 노래로 넘어갑니다.

            # --- 가사 문자열을 줄 단위 리스트로 변환 ---
            # .splitlines()는 다양한 줄 바꿈 문자(\n, \r\n, \r)를 인식하고, 빈 줄도 포함합니다.
            lyrics_lines_list = lyrics.splitlines()

            # JSON 파일에 저장할 데이터는 가사 줄들의 리스트입니다.
            data_to_save = lyrics_lines_list

            # 데이터를 JSON 파일로 저장 (배열 형식)
            try:
                # 파일 저장 시 'utf-8' 인코딩 및 들여쓰기 설정 유지
                with open(file_path, 'w', encoding='utf-8') as f:
                    json.dump(data_to_save, f, ensure_ascii=False, indent=4)
                print(f"[정보] 성공적으로 저장: {file_path}")
                success_count += 1 # 성공 카운트 증가
            except IOError as e:
                print(f"[오류] 파일 저장 오류 '{file_path}': {e}")
                error_count += 1 # 파일 저장 오류 카운트
            except Exception as e:
                print(f"[오류] '{title}' ({file_path}) 가사 처리 중 예상치 못한 오류 발생: {e}")
                error_count += 1 # 기타 오류 카운트


        print("\n--- 내보내기 결과 요약 ---")
        print(f"총 {len(rows)}개 노래 중:")
        print(f"  처리 시도: {processed_count}개")
        print(f"  파일 이미 존재하거나 데이터 처리 오류로 건너뛴 노래: {skipped_count}개") # 건너뛴 항목 설명 업데이트
        print(f"  성공적으로 JSON 파일로 내보낸 노래: {success_count}개")
        print(f"  파일 저장 오류 발생 노래: {error_count}개")


    except mysql.connector.Error as e:
        print(f"[치명적 오류] 데이터베이스 오류 발생: {e}")
        # 이전 오류 코드에 대한 추가 정보는 이전 답변 참고
    except FileExistsError: # 이 예외는 os.makedirs에서 발생할 수 있지만, 위에서 디렉토리 존재 여부를 먼저 확인하므로 발생 가능성 낮음
        print(f"[치명적 오류] 출력 디렉토리 '{output_dir}' 경로 생성 중 문제가 발생했습니다.")
    except Exception as e:
        print(f"[치명적 오류] 스크립트 실행 중 예상치 못한 오류 발생: {e}")
    finally:
        # 데이터베이스 연결 및 커서 닫기
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()
            print("데이터베이스 연결이 닫혔습니다.")

# --- 스크립트 실행 ---
if __name__ == "__main__":
    export_lyrics_to_json_mariadb(DB_CONFIG, TABLE_NAME, TITLE_COLUMN, LYRICS_COLUMN, OUTPUT_DIRECTORY)