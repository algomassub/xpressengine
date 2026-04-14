<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Xpressengine\Support\Migration;

/**
 * 카카오채널 AI 자동답변 플러그인 DB 마이그레이션
 *
 * 테이블: kakao_channel_conversations
 * - 사용자별 대화 이력을 저장하여 문맥 유지 AI 답변을 지원합니다.
 */
class CreateKakaoChannelTables extends Migration
{
    /**
     * 테이블 생성
     */
    public function install()
    {
        if (!Schema::hasTable('kakao_channel_conversations')) {
            Schema::create('kakao_channel_conversations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_key', 255)->comment('카카오 사용자 키');
                $table->string('bot_id', 255)->comment('봇 ID');
                $table->enum('role', ['user', 'assistant'])->comment('메시지 역할');
                $table->text('message')->comment('메시지 내용');
                $table->timestamps();

                $table->index(['user_key', 'bot_id', 'created_at'], 'idx_kakao_user_bot_created');
            });
        }
    }

    /**
     * 설치 완료 후 초기 config 데이터 삽입
     */
    public function installed($site_key = 'default')
    {
        // 기본 설정값 등록
        if (!DB::table('config')->where('name', 'kakao_channel')->where('site_key', $site_key)->exists()) {
            DB::table('config')->insert([
                'name'     => 'kakao_channel',
                'vars'     => json_encode([
                    'ai_provider'    => 'openai',
                    'openai_api_key' => '',
                    'openai_model'   => 'gpt-4o',
                    'claude_api_key' => '',
                    'claude_model'   => 'claude-3-5-sonnet-20241022',
                    'system_prompt'  => '당신은 친절하고 전문적인 고객 상담 AI입니다. 간결하고 명확하게 답변해 주세요.',
                    'max_history'    => 10,
                ]),
                'site_key' => $site_key,
            ]);
        }
    }

    /**
     * 테이블 존재 여부로 설치 확인
     */
    public function checkInstalled()
    {
        return Schema::hasTable('kakao_channel_conversations');
    }

    /**
     * 업데이트 필요 여부 확인
     */
    public function checkUpdated($installedVersion = null)
    {
        return true;
    }

    /**
     * 테이블 삭제
     */
    public function uninstall()
    {
        Schema::dropIfExists('kakao_channel_conversations');

        DB::table('config')
            ->where('name', 'kakao_channel')
            ->delete();
    }
}
