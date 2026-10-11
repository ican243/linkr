#!/bin/bash
# Linkr(king) DB 매일 자동 백업
# - 회원·링크·클릭 기록·결제 기록이 든 DB 전체를 파일 하나(.sql.gz)로 저장
# - KEEP_DAYS 일이 지난 자동 백업은 삭제 (작업 전에 손으로 만든 king_db_*.sql 백업은 건드리지 않음)
#
# 저장 위치: /var/www/backups/king_auto/king_db_YYYYMMDD_HHMMSS.sql.gz
#   웹 폴더 바깥이라 인터넷에서는 열 수 없고, 폴더 700 / 파일 600 (서버 관리자만 읽기)
#
# 서버 설치: sudo install -o root -g root -m 755 scripts/backup-db.sh /usr/local/sbin/king-backup
#   (웹 서버 계정이 고칠 수 있는 프로젝트 폴더의 파일을 root가 바로 실행하지 않도록 복사본을 실행함)
# 실행 예약: /etc/cron.d/king (매일 03:40 한국 시간)
#
# 되돌리는 법(복원):
#   gunzip < /var/www/backups/king_auto/king_db_날짜.sql.gz | mysql king_db
set -euo pipefail

DB_NAME="${DB_NAME:-king_db}"
DEST="${DEST:-/var/www/backups/king_auto}"
KEEP_DAYS="${KEEP_DAYS:-7}"
TS="$(date +%Y%m%d_%H%M%S)"
OUT="$DEST/king_db_$TS.sql.gz"

umask 077
mkdir -p "$DEST"
chmod 700 "$DEST"

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*"; }

# 1) 덤프 → 압축 (root는 비밀번호 없이 서버 내부 연결로 접속 — 비밀번호가 명령어나 파일에 남지 않음)
#    --single-transaction: 사이트를 멈추지 않고 "한 순간"의 모습을 그대로 저장
if ! mysqldump --single-transaction --quick --routines --triggers --no-tablespaces \
        --default-character-set=utf8mb4 "$DB_NAME" | gzip -c > "$OUT"; then
    rm -f "$OUT"
    log "❌ 백업 실패"
    exit 1
fi

# 2) 압축 파일이 온전하고, 비어 있지 않은지 확인
if ! gzip -t "$OUT" || [ "$(stat -c %s "$OUT")" -lt 1024 ]; then
    rm -f "$OUT"
    log "❌ 백업 파일 이상 (손상되었거나 비어 있음)"
    exit 1
fi

# 3) 오래된 자동 백업 정리
REMOVED="$(find "$DEST" -maxdepth 1 -type f -name 'king_db_*.sql.gz' -mtime +"$KEEP_DAYS" -print -delete | wc -l)"

log "✅ 백업 완료: $(basename "$OUT") ($(du -h "$OUT" | cut -f1)) · 지난 백업 ${REMOVED}개 정리"
