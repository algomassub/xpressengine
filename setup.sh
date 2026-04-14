#!/bin/bash
# XpressEngine + 카카오채널 AI 플러그인 자동 설치 스크립트
set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}=====================================${NC}"
echo -e "${GREEN}  XE3 + 카카오채널 AI 자동 설치${NC}"
echo -e "${GREEN}=====================================${NC}"

# 1. Docker 실행 확인
echo -e "\n${YELLOW}[1/6] Docker 상태 확인...${NC}"
if ! docker info > /dev/null 2>&1; then
  echo -e "${RED}Docker가 실행되지 않았습니다. Docker Desktop을 시작해주세요.${NC}"
  exit 1
fi
echo "Docker 정상"

# 2. .env 파일 확인
echo -e "\n${YELLOW}[2/6] 환경 설정 확인...${NC}"
if [ ! -f .env ]; then
  echo -e "${RED}.env 파일이 없습니다!${NC}"
  exit 1
fi
echo ".env 파일 확인 완료"

# 3. Docker 컨테이너 빌드 & 실행
echo -e "\n${YELLOW}[3/6] Docker 컨테이너 빌드 및 실행 중... (첫 실행 시 5~10분 소요)${NC}"
docker-compose down -v 2>/dev/null || true
docker-compose up -d --build

echo "DB 준비 대기 중..."
sleep 15

# 4. Composer 패키지 설치
echo -e "\n${YELLOW}[4/6] Composer 패키지 설치 중...${NC}"
docker exec xe_php composer install \
  --no-interaction \
  --no-scripts \
  --ignore-platform-reqs \
  --optimize-autoloader

# 5. APP_KEY 생성
echo -e "\n${YELLOW}[5/6] APP_KEY 생성 중...${NC}"
docker exec xe_php php artisan key:generate --no-interaction

# 6. XE 웹 설치
echo -e "\n${YELLOW}[6/6] XpressEngine 설치 중...${NC}"

# config/local 디렉토리 생성
docker exec xe_php mkdir -p /var/www/html/config/local

# 설치 요청
INSTALL_RESULT=$(docker exec xe_php curl -s \
  -X POST http://localhost/install/post \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "database_driver=mysql&database_host=db&database_port=3306&database_name=xpressengine&database_user_name=xe&database_password=xe_password&database_prefix=xe_&database_charset=utf8mb4&locale=ko&web_url=http://localhost:8080&web_timezone=Asia/Seoul&admin_login_id=admin&admin_password=admin1234&admin_password_confirmation=admin1234&admin_email=admin@example.com&admin_display_name=Admin" 2>&1)

if echo "$INSTALL_RESULT" | grep -q "Exception\|Error"; then
  echo -e "${RED}설치 중 오류가 발생했습니다. 아래 내용을 확인하세요:${NC}"
  echo "$INSTALL_RESULT" | grep -oP "Exception[^\n<]+" | head -3
  echo -e "\n${YELLOW}수동 설치: http://localhost:8080/install 에 접속하세요${NC}"
else
  echo "XE 설치 완료"
fi

# 플러그인 활성화
echo "카카오채널 플러그인 활성화 중..."
docker exec xe_php php artisan plugin:activate kakao_channel 2>/dev/null || \
  echo "플러그인 활성화 - 수동 활성화 필요 (관리자 패널에서 진행)"

# 완료
echo -e "\n${GREEN}=====================================${NC}"
echo -e "${GREEN}       설치 완료!${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo -e "사이트:         ${GREEN}http://localhost:8080${NC}"
echo -e "관리자 패널:    ${GREEN}http://localhost:8080/settings${NC}"
echo -e "카카오채널 설정: ${GREEN}http://localhost:8080/settings/kakao_channel${NC}"
echo -e "웹훅 URL:       ${GREEN}http://localhost:8080/kakao/webhook${NC}"
echo ""
echo -e "관리자 계정: admin@example.com / admin1234"
echo ""
