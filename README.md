SQL Explain Extract, Compare

# Role: 너는 Front/BackEnd의 최적화된 개발자.

---

## 1. DB 구조

- DB 는 ORACLE, 
- 접속 URL 은 LOCALHOST, 포트 : 1521
- 접속 사용자는 sqler , 패스워드는 xxxx
- SERVICE_NAME 은 XE
- 아래 테이블 구조를 바탕으로 조회 페이지를 제작

| 테이블명 | 컬럼 리스트 |
| :--- | :--- | 
| `FROM_SQL_LIST` | `FROM_LIST_SEQ`, `STATEMENT_ID`, `REG_DT`, `SQL`
| `FROM_SQL_EXPLAIN` | `FROM_SQL_SEQ`, `STATEMENT_ID`, `ID`, `OPERATION`, `NAME`, `REG_DT`
| `TO_SQL_EXPLAIN` | `TO_SQL_SEQ`, `STATEMENT_ID`, `ID`, `OPERATION`, `NAME`, `REG_DT`

---

## 2. 웹 페이지 구조
- **웹 페이지의 분할**
- 4개의 영역으로 웹 페이지 분할

## 3. 요구 사항
- **웹 페이지 뷰**
- 왼쪽 상단의 페이지(LT)에 REG_DT의 조회 조건 추가.
- 왼쪽 상단의 페이지(LT) 출력 결과에 대해서 로우마다 체크 박스 추가
- 왼쪽 상단의 페이지(LT) 뷰에서 출력 순서는 REG_DT, STATEMENT_ID, CHECK_YN 과 같은 순서
- 체크 박스 선택 시, FROM_SQL_LIST 테이블의 CHECK_YN 컬럼을 'Y' 로 업데이트
- 체크 박스 해제 시, FROM_SQL_LIST 테이블의 CHECK_YN 컬럼을 'N' 로 업데이트
- 조회되는 CHECK_YN 값이 'Y' 이면 체크 박스 선택된 것으로 출력
- 왼쪽 상단 페이지(LT) 뷰를 위한 조회 쿼리
SELECT 
REG_DT, STATEMENT_ID, CHECK_YN 
FROM_SQL_LIST
WHERE REG_DT > ? AND REG_DT < ?
- 오른쪽 상단 페이지(RT) 뷰를 위한 조회 쿼리 
SELECT 
SQL 
FROM_SQL_LIST
WHERE STATEMENT_ID = ?
- 왼쪽 하단 페이지(LB) 뷰를 위한 조회 쿼리
SELECT 
A.STATEMENT_ID, 
B.ID,
B.OPERATION,
B.NAME
FROM FROM_SQL_LIST A, FROM_SQL_EXPLAIN B
WHERE A.STATEMENT_ID = B.STATEMENT_ID
AND A.STATEMENT_ID = ?
- 오른쪽 하단 페이지(RB) 뷰를 위한 조회 쿼리
SELECT 
A.STATEMENT_ID, 
C.ID,
C.OPERATION,
C.NAME
FROM FROM_SQL_LIST A, TO_SQL_EXPLAIN C
WHERE A.STATEMENT_ID = C.STATEMENT_ID
AND A.STATEMENT_ID = ?
- 웹 페이지는 한 개의 파일로 구현.
- 왼쪽 상단 페이지(LT) 뷰에서 STATEMENT_ID 클릭 시, 오른쪽 상단 페이지(RT)와 왼쪽 하단 페이지(LB), 오른쪽 하단 페이지(RB) 모두 동시 적용. 
- **언어**: php 언어로 작성
- **출력 방식**: HTML Table 형식으로 출력


OCI_RETURN_LOBS: oci_fetch_array 함수에서 이 플래그를 추가하면 PHP가 내부적으로 LOB->load()를 자동으로 수행하여 문자열 결과값을 반환해 줍니다.
Type Casting: htmlspecialchars((string)$sql_text)와 같이 명시적 캐스팅을 추가하여 데이터 타입 불일치로 인한 중단 현상을 원천 방지했습니다.


1. 페이지 캡처해서 붙여넣기 후, 이것만 나오는데?
2. 이 번에는 왼쪽 상단만 나오는데?
3. 좋아..위 소스를 기준으로 앞으로 개선해줘
4. "2.SQL 상세문"에서 출력되는 SQL 데이터 포맷을 맞출 수 있을까? Beautify 같은 거 있잖아
5. 그런데 "2.SQL 상세문" 여기 프레임이 너무 넓다. 좀 줄여줘
6. 아니다 6:4 말고 5:5로 해줘
7. "3. FROM_SQL_EXPLAIN (변경 전)" 페이지와 "4. TO_SQL_EXPLAIN (변경 후)" 페이지의 데이터 비교를 스크롤하면서 같이 가능할까?
   "3. FROM_SQL_EXPLAIN (변경 전)" 페이지와 "4. TO_SQL_EXPLAIN (변경 후)" 페이지의 데이터가 모두 같으면 연한 녹색, 다르면 연한 주황색으로
8. "3. FROM_SQL_EXPLAIN (변경 전)"  페이지와 "4. TO_SQL_EXPLAIN (변경 후)" 페이지 안의 ID, Operation, Name 의 컬럼 길이를 해당 페이지의 사이즈에 맞게 맞춰줘. 너무 짧다.
9. (Gemini 제안) 오퍼레이션 트리 구조 가시화? 해봐
9-1 답. 시각적 계층: OPERATION 텍스트 앞에 │ └─ 기호가 붙어 실행 순서와 부모-자식 관계가 명확해집니다. (데이터에 공백이 포함되어 있어야 효과가 극대화됩니다.)
위험 감지: FULL SCAN이 빨간색으로 강조되어, 리소스를 많이 잡아먹는 구간을 즉시 찾을 수 있습니다.
10. 아니다 오퍼레이션 트리 구조 가시화 이거 말고, 내가 테이블에 저장할 때 각 데이터 앞에 들여쓰기 해놓은 스페이스를 그대로 출력해줘
11. 그리고 index full scan, full scan, table access full 같은 구문이 나올 경우 진한 적색으로 표시해줘


📊 SQL Explain Plan 분석 및 비교 도구 개발 요구사항
1. 프로젝트 개요
Oracle DB에 저장된 변경 전/후 SQL 실행 계획을 한 화면에서 시각적으로 비교하고, 특정 성능 저하 요소를 즉시 파악할 수 있는 단일 PHP 웹 대시보드 개발.
2. 시스템 환경
언어: PHP (Single File)
DB: Oracle XE (Localhost:1521, SID: XE)
계정: sqler / sqler
방식: No AJAX (표준 GET/POST 통신 방식 사용)
폰트: 고정폭 폰트 (Consolas, Monaco) 적용
3. 데이터베이스 구조 (Table)
테이블명	컬럼 리스트	용도
FROM_SQL_LIST : REG_DT, STATEMENT_ID, CHECK_YN, SQL(CLOB)	메인 SQL 리스트 및 원문
FROM_SQL_EXPLAIN : STATEMENT_ID, ID, OPERATION, NAME	변경 전 실행 계획 상세
TO_SQL_EXPLAIN : STATEMENT_ID, ID, OPERATION, NAME	변경 후 실행 계획 상세
4. 화면 구성 및 주요 기능 (4분할 뷰)
① 왼쪽 상단 (LT): SQL LIST
기능: 등록일자(REG_DT) 범위 조회 필터 제공 (시작 일자와 종료 일자 포함).
출력: REG_DT, STATEMENT_ID, CHECK_YN 순서로 테이블 출력.
상태 관리:
체크박스 클릭 시 즉시 DB 업데이트 (CHECK_YN = 'Y'/'N').
STATEMENT_ID 클릭 시 나머지 3개 뷰가 해당 ID 데이터로 동시 갱신.
UI: 현재 선택된 행(Active Row) 강조 표시.
② 오른쪽 상단 (RT): SQL STATEMENT
기능: 선택된 SQL의 원문 출력 (Oracle CLOB 데이터 완벽 대응).
Beautify: 주요 SQL 키워드(SELECT, FROM, WHERE 등) 기준 자동 줄바꿈 처리.
③ 왼쪽/오른쪽 하단 (LB, RB): FROM/TO SQL EXPLAIN
공백 유지: DB에서 가져온 OPERATION, NAME 필드의 스페이스(들여쓰기)를 100% 유지하여 트리 구조 보존.
비교 분석 (Diff):
양쪽 데이터(ID, Operation, Name)가 일치하면 연한 녹색 배경.
데이터가 불일치하면 연한 주황색 배경.
키워드 강조: INDEX FULL SCAN, FULL SCAN, TABLE ACCESS FULL 구문 포함 시 굵고 진한 적색으로 강조.
스크롤 동기화: 좌측 혹은 우측 실행 계획을 스크롤하면 양쪽 화면이 동시에 스크롤되어 라인별 비교 가능.
5. UI/UX 디자인 요구사항
타이틀 스타일: 각 분할 뷰의 제목(SQL LIST 등)은 일반 본문 글꼴보다 1.5배 크게(18px) 설정.
Resizable 레이아웃: 마우스 드래그를 통해 4개 분할 창의 너비와 높이를 자유롭게 조절 가능.
테이블 디자인: Sticky Header를 적용하여 스크롤 시에도 컬럼 제목이 상단에 고정됨.
왼쪽 상단 (LT) 페이지는 10개 단위로 페이징 처리될 수 있도록 처리. 
6. 핵심 코드 로직 요약
CLOB 처리: OCI_RETURN_LOBS 옵션을 사용하여 데이터 누락 방지.
데이터 비교: 배열 키를 ID값으로 매핑하여 반대편 테이블과 실시간 교차 검증.
스크롤 제어: JavaScript onmouseenter와 onscroll 이벤트를 조합하여 무한 루프 없는 스크롤 동기화 구현.
