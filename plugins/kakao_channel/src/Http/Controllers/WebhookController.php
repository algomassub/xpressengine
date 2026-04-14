<?php

namespace KakaoChannel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use KakaoChannel\Services\AiService;
use KakaoChannel\Services\ConversationService;

/**
 * 카카오 i 오픈빌더 스킬 서버 Webhook 컨트롤러
 *
 * 카카오 서버에서 POST로 전달되는 사용자 메시지를 수신하여
 * AI 답변을 생성하고 카카오 응답 포맷으로 반환합니다.
 *
 * 카카오 i 오픈빌더 스킬 서버 응답 제한 시간: 5초
 * GuzzleHttp 타임아웃은 4초로 설정하여 안전 마진 확보.
 */
class WebhookController
{
    /**
     * 카카오 i 오픈빌더 스킬 서버 Webhook 처리
     *
     * POST /kakao/webhook
     *
     * 요청 형식 (카카오 i 오픈빌더 v2):
     * {
     *   "intent": { "id": "...", "name": "..." },
     *   "userRequest": {
     *     "utterance": "사용자 입력 텍스트",
     *     "user": { "id": "botUserKey", "type": "botUserKey" },
     *     "lang": "ko"
     *   },
     *   "bot": { "id": "botId", "name": "봇 이름" },
     *   "action": { "name": "...", "params": {} }
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // 카카오 요청 파싱 (JSON body 또는 form data 모두 지원)
            $body      = $request->isJson() ? $request->json()->all() : $request->all();
            $userKey   = data_get($body, 'userRequest.user.id', '');
            $botId     = data_get($body, 'bot.id', 'default');
            $utterance = trim((string) data_get($body, 'userRequest.utterance', ''));

            // 빈 메시지 처리
            if (empty($utterance)) {
                return $this->makeKakaoResponse('안녕하세요! 무엇을 도와드릴까요?');
            }

            // 대화 초기화 명령어 처리
            if (in_array($utterance, ['대화초기화', '처음부터', '/reset'], true)) {
                if ($userKey && $botId) {
                    try {
                        (new ConversationService($this->getMaxHistory()))->clearHistory($userKey, $botId);
                    } catch (\Exception $e) {
                        \Log::warning('KakaoChannel clearHistory 오류: ' . $e->getMessage());
                    }
                }
                return $this->makeKakaoResponse('대화 이력이 초기화되었습니다. 새로운 대화를 시작해 주세요!');
            }

            $config = $this->getConfig();

            // 1. 대화 이력 조회 (DB 오류 시 빈 배열로 처리)
            $conversationService = new ConversationService(
                (int) ($config['max_history'] ?? 10)
            );
            $history = [];
            if ($userKey && $botId) {
                try {
                    $history = $conversationService->getHistory($userKey, $botId);
                } catch (\Exception $e) {
                    \Log::warning('KakaoChannel getHistory 오류: ' . $e->getMessage());
                }
            }

            // 2. AI 답변 생성
            $aiReply = (new AiService($config))->generateReply($utterance, $history);

            // 3. 대화 이력 저장 (DB 오류 시 무시)
            if ($userKey && $botId) {
                try {
                    $conversationService->addMessages($userKey, $botId, $utterance, $aiReply);
                } catch (\Exception $e) {
                    \Log::warning('KakaoChannel addMessages 오류: ' . $e->getMessage());
                }
            }

            // 4. 카카오 응답 포맷으로 반환
            return $this->makeKakaoResponse($aiReply);

        } catch (\Exception $e) {
            \Log::error('KakaoChannel Webhook 치명적 오류: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->makeKakaoResponse('죄송합니다. 서비스에 일시적인 문제가 발생했습니다. 잠시 후 다시 시도해 주세요.');
        }
    }

    /**
     * 카카오 i 오픈빌더 v2 응답 포맷 생성
     *
     * simpleText 최대 1000자 제한 적용.
     *
     * @param string $text 응답 텍스트
     * @return JsonResponse
     */
    protected function makeKakaoResponse(string $text): JsonResponse
    {
        // 카카오 simpleText 최대 1000자
        $text = mb_substr($text, 0, 1000);

        return response()->json([
            'version'  => '2.0',
            'template' => [
                'outputs' => [
                    [
                        'simpleText' => [
                            'text' => $text,
                        ],
                    ],
                ],
                'quickReplies' => [],
            ],
        ]);
    }

    /**
     * 플러그인 설정 로드
     *
     * @return array
     */
    protected function getConfig(): array
    {
        $configObj = app('xe.config')->get('kakao_channel');
        return $configObj ? ($configObj->getPureAll() ?: []) : [];
    }

    /**
     * max_history 설정 값 반환
     *
     * @return int
     */
    protected function getMaxHistory(): int
    {
        $config = $this->getConfig();
        return (int) ($config['max_history'] ?? 10);
    }
}
