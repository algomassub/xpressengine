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
        // 카카오 요청 파싱
        $userKey   = $request->input('userRequest.user.id', '');
        $botId     = $request->input('bot.id', 'default');
        $utterance = trim((string) $request->input('userRequest.utterance', ''));

        // 빈 메시지 처리
        if (empty($utterance)) {
            return $this->makeKakaoResponse('안녕하세요! 무엇을 도와드릴까요?');
        }

        // 대화 초기화 명령어 처리
        if (in_array($utterance, ['대화초기화', '처음부터', '/reset'], true)) {
            if ($userKey && $botId) {
                (new ConversationService($this->getMaxHistory()))->clearHistory($userKey, $botId);
            }
            return $this->makeKakaoResponse('대화 이력이 초기화되었습니다. 새로운 대화를 시작해 주세요!');
        }

        $config = $this->getConfig();

        // 1. 대화 이력 조회
        $conversationService = new ConversationService(
            (int) ($config['max_history'] ?? 10)
        );
        $history = ($userKey && $botId)
            ? $conversationService->getHistory($userKey, $botId)
            : [];

        // 2. AI 답변 생성
        $aiReply = (new AiService($config))->generateReply($utterance, $history);

        // 3. 대화 이력 저장
        if ($userKey && $botId) {
            $conversationService->addMessages($userKey, $botId, $utterance, $aiReply);
        }

        // 4. 카카오 응답 포맷으로 반환
        return $this->makeKakaoResponse($aiReply);
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
