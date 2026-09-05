# Linkr

긴 URL을 짧게 줄이고, 클릭 통계까지 관리할 수 있는 URL 단축 서비스입니다.

## 주요 기능

- URL 단축 + 커스텀 별칭/제목, QR 코드 생성
- 비밀번호 보호, 만료일 설정, 커스텀 도메인 연결
- 소셜 로그인 (구글 / 네이버 / 카카오)
- 요금제 3단계(무료 / 프로 / 엔터프라이즈) + 토스페이먼츠 결제 연동(테스트 모드)
- 관리자 패널 — 회원/링크 관리, 요금제 값 실시간 변경
- 외부 프로그램용 REST API (링크 생성 / 조회 / 삭제)
- 엔터프라이즈 전용 화이트라벨링

## 기술 스택

- **백엔드**: PHP 8.2+ (CodeIgniter 4)
- **DB**: MySQL
- **웹서버**: Nginx
- **테스트**: PHPUnit
- **외부 연동**: 토스페이먼츠 API, 구글/네이버/카카오 OAuth
- **프런트**: Bootstrap 5 + 커스텀 CSS

## 설치 방법

### 1. 의존성 설치

```bash
composer install
```

### 2. 환경 설정

`env` 파일을 `.env`로 복사한 뒤, 아래 값들을 채워주세요.

```bash
cp env .env
```

`.env`에서 채워야 하는 주요 항목:

- `database.default.*` — DB 접속 정보
- `google.*` / `naver.*` / `kakao.*` — 각 소셜 로그인 클라이언트 ID/시크릿, 리다이렉트 URI
- `tosspayments.*` — 토스페이먼츠 클라이언트 키/시크릿 키
- `privacy.contactEmail` — 개인정보처리방침 문의 이메일

### 3. 데이터베이스 마이그레이션

```bash
php spark migrate
```

### 4. 로컬 서버 실행

```bash
php spark serve
```

기본적으로 `http://localhost:8080`에서 접속할 수 있습니다.

### 5. (선택) 관리자 계정 생성

관리자 계정은 회원가입 화면이 따로 없어, 아래처럼 직접 비밀번호 해시를 만들어 DB에 등록합니다.

```bash
php -r "echo password_hash('원하는비밀번호', PASSWORD_DEFAULT);"
```

나온 해시값을 `admins` 테이블에 직접 INSERT 하면 `/admin/login`으로 로그인할 수 있습니다.

## 테스트

```bash
vendor/bin/phpunit
```

## 라이선스

[MIT License](LICENSE)
