<?php

namespace KakaoChannel\Services;

use KakaoChannel\Models\KakaoConversation;

/**
 * 대화 이력 관리 서비스
 *
 * 사용자별 대화 이력을 DB에 저장하고 조회합니다.
 * AI 메시지 형식(messages 배열)으로 변환하여 제공합니다.
 */
class ConversationService
{
    /** @var int 최대 유지 대화 쌍 수 */
    protected $maxHistory;

    public function __construct(int $maxHistory = 10)
    {
        $this->maxHistory = $maxHistory;
    }

    /**
     * 사용자/봇 조합의 대화 이력을 AI messages 형식으로 반환
     *
     * @param string $userKey  카카오 사용자 키
     * @param string $botId    봇 ID
     * @return array           [['role' => 'user'|'assistant', 'content' => '...'], ...]
     */
    public function getHistory(string $userKey, string $botId): array
    {
        // 최대 이력의 2배(user + assistant 쌍)만큼 조회
        $limit = $this->maxHistory * 2;

        $records = KakaoConversation::getHistory($userKey, $botId, $limit);

        return $records->map(function ($record) {
            return [
                'role'    => $record->role,
                'content' => $record->message,
            ];
        })->toArray();
    }

    /**
     * 사용자 메시지와 AI 답변을 DB에 저장
     *
     * @param string $userKey       카카오 사용자 키
     * @param string $botId         봇 ID
     * @param string $userMessage   사용자 메시지
     * @param string $assistantReply AI 답변
     */
    public function addMessages(string $userKey, string $botId, string $userMessage, string $assistantReply): void
    {
        // 사용자 메시지 저장
        KakaoConversation::create([
            'user_key' => $userKey,
            'bot_id'   => $botId,
            'role'     => 'user',
            'message'  => $userMessage,
        ]);

        // AI 답변 저장
        KakaoConversation::create([
            'user_key' => $userKey,
            'bot_id'   => $botId,
            'role'     => 'assistant',
            'message'  => $assistantReply,
        ]);

        // 오래된 이력 정리 (maxHistory * 2 개 초과 시)
        KakaoConversation::pruneHistory($userKey, $botId, $this->maxHistory * 2);
    }

    /**
     * 특정 사용자의 대화 이력 전체 삭제 (초기화)
     *
     * @param string $userKey
     * @param string $botId
     */
    public function clearHistory(string $userKey, string $botId): void
    {
        KakaoConversation::where('user_key', $userKey)
            ->where('bot_id', $botId)
            ->delete();
    }
}
