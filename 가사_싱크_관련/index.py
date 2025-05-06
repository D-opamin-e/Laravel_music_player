import os
import sys
import json
import logging
import google.generativeai as genai # Gemini API 라이브러리 임포트

# 로깅 설정
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# --- 설정 ---
# Google Gemini API 키 설정 (필수!)
# 환경 변수로 설정하거나, 여기에 직접 입력합니다.
# 보안을 위해 환경 변수 사용을 권장합니다.
GOOGLE_API_KEY = "GOOGLE_API_KEY" # <-- 여기에 발급받은 실제 API 키를 입력합니다.

# 사용자가 가진 원본 가사 파일들 (.json 형식)이 있는 입력 디렉토리
# 형식: ["가사 라인 1", "가사 라인 2", ...]
ORIGINAL_LYRICS_INPUT_DIR = 'original_lyrics'

# 시간 정보를 가져올 전사 결과 JSON 파일들이 있는 입력 디렉토리
# 형식: [{"time": 시간, "line": "전사된 텍스트"}, ...] 또는 {"segments": [...]}
TRANSCRIPTION_INPUT_DIR = 'whisper'

# 최종적으로 시간 정보가 매칭된 (매칭 성공한 라인만 포함) 가사 JSON 파일들을 저장할 디렉토리
# 형식: [{"time": 시간, "line": "가사 내용"}, ...]
ALIGNED_LYRICS_OUTPUT_DIR = 'aligned_lyrics_output_gemini_filtered' # 폴더 이름 변경 (필터링 결과)

# 매칭에 실패하여 제외된 가사 라인들을 기록할 TXT 파일 경로
NULL_REPORT_FILE = 'unmatched_lyrics_report.txt' # <-- 이 줄의 주석을 제거했습니다.

# 사용할 Gemini 모델 이름
GEMINI_MODEL_NAME = 'gemini-1.5-flash-latest'


# --- Gemini API 설정 및 초기화 ---
if not GOOGLE_API_KEY or GOOGLE_API_KEY == "GOOGLE_API_KEY":
    logging.error("오류: Google Gemini API 키가 설정되지 않았거나 플레이스홀더 값('YOUR_GOOGLE_API_KEY') 그대로입니다. 스크립트 상단에 실제 API 키를 입력하거나 환경 변수를 설정해주세요.")
    sys.exit(1)

try:
    genai.configure(api_key=GOOGLE_API_KEY)
    logging.info(f"Google Gemini API 설정 완료. 사용할 모델: '{GEMINI_MODEL_NAME}'.")
except Exception as e:
    logging.error(f"Google Gemini API 설정 또는 인증 중 오류 발생: {e}")
    logging.error("API 키가 올바른지 (오타 확인), 유효한 키인지 (만료 여부 등), Google Cloud Console에서 해당 API가 프로젝트에 대해 사용 설정되어 있는지 확인하세요.")
    sys.exit(1)

# --- API 호출 함수 정의 ---
def call_gemini_api_for_alignment(original_lyrics_list, transcription_segments_list, model_name=GEMINI_MODEL_NAME):
    """
    Gemini API를 호출하여 가사 정렬을 수행하고, 매칭된 라인만 포함된 리스트를 반환합니다.

    Args:
        original_lyrics_list (list): 빈 줄이 제거된 원본 가사 라인 문자열 리스트.
        transcription_segments_list (list): 텍스트가 비어있지 않은 전사 결과 세그먼트 딕셔너리 리스트 (각 딕셔너리는 최소 'text', 'start' 키 포함).
        model_name (str): 사용할 Gemini 모델 이름.

    Returns:
        list: Gemini API로부터 받은 정렬된 가사 딕셔너리 리스트 [{'time': float, 'line': str}].
              매칭되지 않은 라인은 이 리스트에서 제외됩니다.
              API 호출 또는 응답 처리 중 오류 발생 시 None을 반환합니다.
    """
    try:
        model = genai.GenerativeModel(model_name)
    except Exception as e:
        logging.error(f"Gemini 모델 '{model_name}' 로드 중 오류 발생 (API 키 문제일 수 있음): {e}")
        return None

    # --- 프롬프트 구성 ---
    # 매칭되지 않은 라인은 결과에서 '제외'하도록 지시합니다.
    prompt = f"""
    당신은 오디오 전사 결과와 원본 가사를 비교하여 시간 정보를 매칭하는 도구입니다.

    아래 두 가지 입력이 주어집니다:
    1. 원본 가사 (각 라인이 별도의 항목입니다.)
    2. 오디오 전사 결과 (각 세그먼트가 시간 정보와 전사된 텍스트를 가집니다.)

    **당신의 목표:**
    원본 가사의 각 라인에 대해, 오디오 전사 결과에서 내용이 가장 잘 일치하거나 대응되는 세그먼트의 시간 정보를 매칭하여 정렬된 가사를 생성합니다.

    **입력 형식:**

    <원본 가사>
    {json.dumps(original_lyrics_list, indent=2, ensure_ascii=False)}

    <오디오 전사 결과>
    {json.dumps(transcription_segments_list, indent=2, ensure_ascii=False)}

    **매칭 규칙:**
    - 원본 가사의 각 라인은 시간 순서대로 매칭되어야 합니다.
    - 전사 결과 세그먼트의 시간 정보는 'time' 또는 'start' 키의 값을 사용합니다. (스크립트에서는 'start'로 통일하여 전달)
    - 전사 결과 세그먼트의 텍스트는 'text' 키의 값을 사용합니다.
    - 하나의 원본 가사 라인은 하나 이상의 전사 세그먼트와 대응될 수 있습니다. 이 경우 **가장 먼저 등장하는 대응 세그먼트의 시간**을 사용합니다.
    - 하나의 전사 세그먼트가 하나 이상의 원본 가사 라인과 대응될 수 있습니다. 이 경우 대응되는 원본 가사 라인들에 **모두 동일한 전사 세그먼트의 시간**을 할당합니다.
    - 만약 원본 가사 라인과 일치하거나 대응되는 전사 결과 세그먼트를 찾을 수 없으면, 해당 라인은 **최종 출력 리스트에서 제외합니다.** Null로 표시하지 마세요.

    **출력 형식:**
    최종 결과는 **매칭에 성공한 라인만 포함된** 다음 JSON 형식의 리스트로만 출력해야 합니다. 설명이나 추가 텍스트 없이 JSON 리스트만 출력합니다.

    [
      {{ "time": 시간 (float), "line": "원본 가사 라인 텍스트" }},
      {{ "time": 시간 (float), "line": "원본 가사 라인 텍스트" }},
      ...
    ]

    **주의:**
    - 출력은 오직 **매칭 성공 라인만** 포함된 JSON 리스트 형태여야 합니다. 다른 형식은 허용되지 않습니다.
    - 'time' 값은 대응되는 전사 세그먼트의 'start' 값을 사용합니다.
    - 'line' 값은 원본 가사의 해당 라인 텍스트를 **그대로 사용**합니다. 전사 결과 텍스트로 대체하지 마세요.
    - 전사 결과 텍스트에 오탈자나 오류가 있을 수 있습니다. 당신은 원본 가사 텍스트를 우선하여 매칭을 시도합니다.

    **이제 입력 데이터를 바탕으로 정렬된 가사를 JSON 형식으로 출력하세요. 매칭되지 않은 라인은 제외합니다.**

    <원본 가사>
    {json.dumps(original_lyrics_list, indent=2, ensure_ascii=False)}

    <오디오 전사 결과>
    {json.dumps(transcription_segments_list, indent=2, ensure_ascii=False)}

    <출력 JSON>
    """

    try:
        response = model.generate_content(prompt)
        logging.info("Gemini API 호출 완료.")

        response_text = response.text.strip()

        if response_text.startswith("```json"):
            response_text = response_text[len("```json"):].strip()
            if response_text.endswith("```"):
                response_text = response_text[:-len("```")].strip()

        logging.debug(f"API 응답 텍스트:\n{response_text}")

        aligned_result = json.loads(response_text)

        # 결과 형식이 예상과 맞는지 최종 확인 (매칭 성공 라인만 포함된 리스트인지)
        # time 값이 int나 float인지 확인합니다.
        if isinstance(aligned_result, list) and all(isinstance(item, dict) and "time" in item and isinstance(item["time"], (int, float)) and "line" in item for item in aligned_result):
            logging.info("API 응답이 예상된 JSON 형식 (매칭 성공 라인 리스트)과 일치합니다.")
            return aligned_result
        else:
            logging.warning("API 응답이 예상된 JSON 형식과 다릅니다. 파싱은 되었지만 결과 확인이 필요합니다.")
            logging.warning("API 응답에는 'time': null이 포함되었거나, 형식이 다를 수 있습니다. (제외되어야 함)")
            logging.warning(f"수신된 데이터: {aligned_result}")
            # 형식이 다르면 일단 None 반환하여 메인 로직에서 오류 처리
            return None

    except json.JSONDecodeError as e:
         logging.error(f"Gemini API 응답 파싱 오류: 수신된 텍스트가 올바른 JSON 형식이 아닙니다. 응답 텍스트를 확인하세요. 오류: {e}")
         logging.debug(f"파싱 시도 텍스트:\n{response_text}")
         return None
    except Exception as e:
        logging.error(f"Gemini API 호출 또는 응답 처리 중 예상치 못한 오류 발생: {e}")
        return None


# --- 메인 처리 로직 ---

# 최종 정렬 결과가 저장될 출력 디렉토리 없으면 생성
if not os.path.exists(ALIGNED_LYRICS_OUTPUT_DIR):
    os.makedirs(ALIGNED_LYRICS_OUTPUT_DIR)
    logging.info(f"출력 디렉토리 생성: '{ALIGNED_LYRICS_OUTPUT_DIR}'")

# 제외된 가사 라인들을 기록할 파일 경로 설정 및 해당 파일을 담을 딕셔너리 초기화
# NULL_REPORT_FILE 변수가 여기서 사용됩니다.
unmatched_report_filepath = os.path.join(ALIGNED_LYRICS_OUTPUT_DIR, NULL_REPORT_FILE)
all_unmatched_lyrics = {} # {file_title: [(original_index, line), ...], ...} 형태로 저장

# 원본 가사 파일 목록 가져오기
try:
    logging.info(f"\n원본 가사 디렉토리 '{ORIGINAL_LYRICS_INPUT_DIR}'에서 JSON 파일 검색 중...")
    lyrics_files = [f for f in os.listdir(ORIGINAL_LYRICS_INPUT_DIR)
                    if os.path.isfile(os.path.join(ORIGINAL_LYRICS_INPUT_DIR, f)) and f.lower().endswith('.json')]
except FileNotFoundError:
    logging.error(f"오류: 입력 디렉토리 '{ORIGINAL_LYRICS_INPUT_DIR}'를 찾을 수 없습니다. 디렉토리 경로를 확인해주세요.")
    sys.exit(1)
except Exception as e:
    logging.error(f"디렉토리 목록 읽기 중 오류 발생: {e}")
    sys.exit(1)

if not lyrics_files:
    logging.warning(f"'{ORIGINAL_LYRICS_INPUT_DIR}' 디렉토리에 지원하는 .json 가사 파일이 없습니다. 처리할 파일이 없습니다.")
    sys.exit(0)

logging.info(f"'{ORIGINAL_LYRICS_INPUT_DIR}'에서 총 {len(lyrics_files)}개의 가사 JSON 파일을 찾았습니다.")

processed_count = 0
skipped_count = 0
successful_count = 0

# 각 가사 파일에 대해 반복 처리 실행
for lyrics_filename in lyrics_files:
    base_filename, _ = os.path.splitext(lyrics_filename)
    original_lyrics_filepath = os.path.join(ORIGINAL_LYRICS_INPUT_DIR, lyrics_filename)

    transcription_filepath = os.path.join(TRANSCRIPTION_INPUT_DIR, base_filename + '.json')

    # 최종 정렬 결과 파일 경로 (제외된 라인 제외)
    aligned_output_filepath = os.path.join(ALIGNED_LYRICS_OUTPUT_DIR, lyrics_filename) # 원본 파일 이름 그대로 사용

    logging.info(f"\n--- 처리 시작 (Gemini API): '{lyrics_filename}' ---")

    # 이미 최종 결과 파일이 존재하면 해당 파일 처리를 건너뛰기
    if os.path.exists(aligned_output_filepath):
        logging.info(f"   최종 결과 파일 '{aligned_output_filepath}' 이미 존재합니다. 건너뜁니다.")
        skipped_count += 1
        processed_count += 1
        continue

    # --- 원본 가사 로드 ---
    original_lyrics_list_raw = None
    original_lyrics_list_filtered = [] # 빈 문자열이 제거된 원본 가사 리스트 (Gemini API 입력용)

    try:
        if not os.path.exists(original_lyrics_filepath):
             logging.warning(f"   원본 가사 파일 '{original_lyrics_filepath}'를 찾을 수 없습니다. 해당 파일을 건너뜁니다.")
             skipped_count += 1
             processed_count += 1
             continue

        with open(original_lyrics_filepath, 'r', encoding='utf-8') as f:
            original_lyrics_list_raw = json.load(f)

        # --- 원본 가사 리스트 검증 및 빈 문자열 필터링 ---
        if not (isinstance(original_lyrics_list_raw, list) and all(isinstance(item, str) for item in original_lyrics_list_raw)):
             logging.warning(f"   원본 가사 파일 '{original_lyrics_filepath}' 형식이 올바르지 않습니다. (예상 형식: [\"line1\", \"line2\", ...]). 해당 파일을 건너뜁니다.")
             skipped_count += 1
             processed_count += 1
             continue

        # 빈 문자열("") 또는 공백만 있는 문자열을 원본 가사 리스트에서 제거
        # 필터링 전 원본 인덱스를 추적하기 위해 튜플 리스트로 저장
        original_lyrics_list_with_index = [(i, line.strip()) for i, line in enumerate(original_lyrics_list_raw)]
        original_lyrics_list_filtered = [(i, line) for i, line in original_lyrics_list_with_index if line] # 빈 줄 필터링

        if not original_lyrics_list_filtered:
             logging.warning(f"   원본 가사 파일 '{original_lyrics_filepath}' 로드 후 유효한 (비어있지 않은) 가사 라인이 없습니다. 건너뜁니다.")
             skipped_count += 1
             processed_count += 1
             continue

        logging.info(f"   원본 가사 로드 성공: '{original_lyrics_filepath}' ({len(original_lyrics_list_raw)} 라인). 유효한 라인 {len(original_lyrics_list_filtered)}개.")

    except json.JSONDecodeError as e:
        logging.warning(f"   원본 가사 파일 '{original_lyrics_filepath}' JSON 파싱 오류: {e}. 파일 내용을 확인해주세요. 건너뜁니다.")
        skipped_count += 1
        processed_count += 1
        continue
    except Exception as e:
        logging.warning(f"   원본 가사 파일 로드 중 예상치 못한 오류 발생 ('{original_lyrics_filepath}'): {e}. 건너뜁니다.")
        skipped_count += 1
        processed_count += 1
        continue

    # --- 전사 결과 로드 ---
    transcription_raw_data = None
    transcription_segments_for_matching = [] # {"text": ..., "start": ...} 형태의 전사 세그먼트 리스트 (Gemini API 입력용)

    try:
        if not os.path.exists(transcription_filepath):
             logging.warning(f"   해당 오디오의 전사 결과 파일 '{transcription_filepath}'를 찾을 수 없습니다. 해당 파일을 건너뜁니다.")
             skipped_count += 1
             processed_count += 1
             continue

        with open(transcription_filepath, 'r', encoding='utf-8') as f:
            transcription_raw_data = json.load(f)

        # --- 전사 결과 데이터 형식 판단 및 매칭에 사용할 리스트 구성 ---
        if isinstance(transcription_raw_data, list) and all(isinstance(item, dict) for item in transcription_raw_data) and all("time" in item and "line" in item for item in transcription_raw_data):
            transcription_segments_for_matching = [{"text": item.get("line", "").strip(), "start": item.get("time", 0.0)} for item in transcription_raw_data]
            logging.info(f"   전사 결과 로드 성공 (사용자 제공 형식): '{transcription_filepath}' ({len(transcription_segments_for_matching)} 세그먼트)")

        elif isinstance(transcription_raw_data, dict) and "segments" in transcription_raw_data and isinstance(transcription_raw_data["segments"], list):
            transcription_segments_for_matching = [{"text": seg.get("text", "").strip(), "start": seg.get("start", 0.0)} for seg in transcription_raw_data["segments"]]
            logging.info(f"   전사 결과 로드 성공 (WhisperX/VAD 형식): '{transcription_filepath}' ({len(transcription_segments_for_matching)} 세그먼트)")

        else:
             logging.warning(f"   전사 결과 파일 '{transcription_filepath}' 형식이 올바르지 않습니다. (예상 형식: 목록[사전] 또는 사전['segments']에 목록). 해당 파일을 건너ㅂ니다.")
             skipped_count += 1
             processed_count += 1
             continue

        # 전사 결과 세그먼트 중 텍스트가 비어있는 세그먼트 제거
        transcription_segments_for_matching = [seg for seg in transcription_segments_for_matching if seg.get("text", "").strip()]
        
        if not transcription_segments_for_matching:
             logging.warning(f"   전사 결과 파일 '{transcription_filepath}' 로드 후 유효한 (비어있지 않은) 전사 세그먼트가 없습니다. 건너뜁니다.")
             skipped_count += 1
             processed_count += 1
             continue

         # 전사 결과 세그먼트들을 시간 순서대로 정렬 (안전장치)
        transcription_segments_for_matching.sort(key=lambda x: x.get('start', 0.0))


    except json.JSONDecodeError as e:
        logging.warning(f"   전사 결과 파일 '{transcription_filepath}' JSON 파싱 오류: {e}. 파일 내용을 확인해주세요. 건너ㅂ니다.")
        skipped_count += 1
        processed_count += 1
        continue
    except Exception as e:
        logging.warning(f"   전사 결과 파일 로드 중 예상치 못한 오류 발생 ('{transcription_filepath}'): {e}. 건너니다.")
        skipped_count += 1
        processed_count += 1
        continue


    # --- Gemini API 호출하여 가사 정렬 수행 ---
    logging.info("   Gemini API를 사용하여 가사 정렬 시작...")

    # call_gemini_api_for_alignment 함수 호출
    # API에는 원본 가사 텍스트 리스트와 전사 세그먼트 리스트만 전달
    # original_lyrics_list_filtered는 (인덱스, 라인텍스트) 튜플의 리스트이므로 라인 텍스트만 추출하여 전달
    aligned_data_from_gemini = call_gemini_api_for_alignment(
        [line for index, line in original_lyrics_list_filtered],
        transcription_segments_for_matching
    )

    if aligned_data_from_gemini is None:
        logging.error(f"   '{lyrics_filename}' 파일에 대해 Gemini API 처리 중 오류 발생 또는 결과 형식 오류. 결과 저장을 건너ㅂ니다.")
        skipped_count += 1
        processed_count += 1
        continue # 다음 파일로 이동

    logging.info(f"   Gemini API 가사 정렬 완료. 매칭된 라인 {len(aligned_data_from_gemini)}개 결과 수신.")

    # --- 매칭 성공 라인 결과 저장 ---
    # 매칭 성공 라인이 1개 이상인 경우에만 저장
    if aligned_data_from_gemini:
         try:
             with open(aligned_output_filepath, 'w', encoding='utf-8') as outfile:
                 json.dump(aligned_data_from_gemini, outfile, indent=2, ensure_ascii=False)
             logging.info(f"   매칭 성공 라인 결과 저장 완료: '{aligned_output_filepath}' 생성.")
             successful_count += 1 # 성공 파일 수 증가 (매칭된 라인이 1개 이상인 경우)
         except Exception as e:
             logging.error(f"   최종 결과 JSON 파일 저장 중 오류 발생 ('{aligned_output_filepath}'): {e}")
             skipped_count += 1 # 저장 오류도 실패로 간주
    else:
         logging.warning(f"   '{lyrics_filename}' 파일에 대해 Gemini API로부터 받은 매칭된 라인이 없습니다. 결과 JSON이 저장되지 않습니다.")
         # 매칭된 라인이 하나도 없어도 파일 처리는 된 것으로 간주 (skipped_count 증가 안 함)
         pass


    # --- 매칭 실패 (제외된) 라인 수집 ---
    # 원본 필터링된 라인 리스트와 API가 반환한 매칭 성공 라인 리스트를 비교하여 제외된 라인을 찾습니다.
    # API 응답 결과 리스트에서 'line' 텍스트들만 추출하여 집합으로 만듭니다.
    matched_lines_text = {item.get("line", "") for item in aligned_data_from_gemini}

    unmatched_lyrics_for_this_file = []
    # 원본 가사 리스트 (필터링 전 인덱스 포함)를 순회하며 매칭 성공 라인 집합에 없는 라인을 찾음
    for original_index, original_line_text in original_lyrics_list_filtered:
        # 원본 라인 텍스트가 매칭 성공 집합에 없는 경우 제외된 라인
        if original_line_text not in matched_lines_text:
            unmatched_lyrics_for_this_file.append((original_index, original_line_text))

    # 제외된 라인이 하나라도 있으면 전체 수집 딕셔너리에 추가
    if unmatched_lyrics_for_this_file:
        # 파일 이름 (확장자 제외)을 키로, 제외된 라인 리스트를 값으로 저장
        all_unmatched_lyrics[base_filename] = unmatched_lyrics_for_this_file
        logging.warning(f"   '{lyrics_filename}' 파일에서 매칭 실패/제외된 가사 라인 {len(unmatched_lyrics_for_this_file)}개 발견.")


    processed_count += 1 # 처리 시도한 파일 수 증가
    logging.info(f"--- 처리 완료: '{lyrics_filename}' ---\n")


# --- 전체 처리 완료 후, 제외된 가사 라인 보고서 (null.txt) 저장 ---
# 모든 파일 처리가 끝난 후, 수집된 제외된 라인 정보를 파일로 저장
if all_unmatched_lyrics:
    logging.info(f"\n======= 매칭 실패/제외된 가사 라인 보고서 작성: '{unmatched_report_filepath}' =======")
    try:
        with open(unmatched_report_filepath, 'w', encoding='utf-8') as report_file:
            # 수집된 딕셔너리 순회 (파일 제목별로)
            for file_title, unmatched_lines in all_unmatched_lyrics.items():
                report_file.write(f"--- 파일: {file_title} ---\n")
                for original_index, line_text in unmatched_lines:
                    report_file.write(f"[원본 라인 {original_index + 1}] {line_text}\n")
                report_file.write("\n")
        logging.info(f"매칭 실패/제외된 가사 라인 보고서 작성 완료.")
    except Exception as e:
        logging.error(f"매칭 실패/제외된 가사 라인 보고서 저장 중 오류 발생 ('{unmatched_report_filepath}'): {e}")

# --- 전체 처리 결과 요약 ---
logging.info("\n======= 전체 처리 결과 요약 =======")
logging.info(f"총 {len(lyrics_files)}개의 원본 가사 JSON 파일 발견.")
logging.info(f"처리 시도 파일: {processed_count}개")
logging.info(f"강제 정렬 및 결과 저장 성공 파일: {successful_count}개")
logging.info(f"건너뛰거나 오류 발생 파일: {skipped_count}개")
logging.info(f"매칭 성공 라인 결과 JSON 파일은 '{ALIGNED_LYRICS_OUTPUT_DIR}' 폴더에 저장되었습니다.")
if all_unmatched_lyrics:
    logging.info(f"매칭 실패/제외된 라인 보고서는 '{unmatched_report_filepath}' 파일에 기록되었습니다.")
logging.info("========================================")