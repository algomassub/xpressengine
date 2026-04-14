<?php

namespace KakaoChannel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 카카오채널 대화 이력 모델
 *
 * @property int    $id
 * @property string $user_key   카카오 사용자 키 (botUserKey)
 * @property string $bot_id     봇 ID
 * @property string $role       메시지 역할 ('user' | 'assistant')
 * @property string $message    메시지 내용
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class KakaoConversation extends Model
{
    protected $table = 'kakao_channel_conversations';

    protected $fillable = [
        'user_key',
        'bot_id',
        'role',
        'message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 특정 사용자/봇의 최근 대화 이력 조회
     *
     * @param string $userKey
     * @param string $botId
     * @param int    $limit  메시지 개수 제한
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getHistory(string $userKey, string $botId, int $limit = 20)
    {
        return static::where('user_key', $userKey)
            ->where('bot_id', $botId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * 오래된 대화 이력 정리
     *
     * @param string $userKey
     * @param string $botId
     * @param int    $keepCount 유지할 메시지 수
     */
    public static function pruneHistory(string $userKey, string $botId, int $keepCount = 20)
    {
        $ids = static::where('user_key', $userKey)
            ->where('bot_id', $botId)
            ->orderBy('created_at', 'desc')
            ->limit($keepCount)
            ->pluck('id');

        static::where('user_key', $userKey)
            ->where('bot_id', $botId)
            ->whereNotIn('id', $ids)
            ->delete();
    }
}
