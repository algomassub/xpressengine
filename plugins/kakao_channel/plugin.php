<?php
/**
 * 카카오채널 AI 자동답변 플러그인
 *
 * 카카오 i 오픈빌더 스킬 서버로 동작하며,
 * 사용자 메시지를 받아 OpenAI 또는 Anthropic Claude API로
 * AI 자동답변을 생성하고 대화 이력을 DB에 유지합니다.
 *
 * PHP version 7
 *
 * @license MIT
 */

namespace KakaoChannel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Xpressengine\Plugin\AbstractPlugin;
use App\Http\Middleware\ExceptAppendableVerifyCsrfToken;

/**
 * 카카오채널 AI 자동답변 플러그인 메인 클래스
 */
class Plugin extends AbstractPlugin
{
    /**
     * 플러그인 부트: 라우트 및 설정 메뉴를 등록합니다.
     * 활성화된 플러그인이 매 요청마다 실행됩니다.
     *
     * @return void
     */
    public function boot()
    {
        // 지원 파일 로드 (composer autoload 없는 환경에서의 fallback)
        $this->loadSupportFiles();

        // 카카오 webhook 라우트 등록 (CSRF 제외)
        $this->registerWebhookRoute();

        // 관리자 설정 라우트 등록
        $this->registerSettingsRoute();

        // 관리자 사이드바 메뉴 등록
        $this->registerSettingsMenu();
    }

    /**
     * 플러그인 설치: DB 테이블 생성 및 초기 설정값 등록
     *
     * @return void
     */
    public function install()
    {
        // 대화 이력 테이블 생성
        if (!Schema::hasTable('kakao_channel_conversations')) {
            Schema::create('kakao_channel_conversations', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->string('user_key', 255)->comment('카카오 사용자 키 (botUserKey)');
                $table->string('bot_id', 255)->comment('봇 ID');
                $table->enum('role', ['user', 'assistant'])->comment('메시지 역할');
                $table->text('message')->comment('메시지 내용');
                $table->timestamps();

                $table->index(['user_key', 'bot_id', 'created_at'], 'idx_kakao_user_bot_created');
            });
        }

        // 초기 설정값 등록
        $existingConfig = app('xe.config')->get('kakao_channel');
        if ($existingConfig === null) {
            app('xe.config')->add('kakao_channel', [
                'ai_provider'    => 'openai',
                'openai_api_key' => '',
                'openai_model'   => 'gpt-4o',
                'claude_api_key' => '',
                'claude_model'   => 'claude-3-5-sonnet-20241022',
                'system_prompt'  => '당신은 친절하고 전문적인 고객 상담 AI입니다. 간결하고 명확하게 답변해 주세요.',
                'max_history'    => 10,
            ]);
        }
    }

    /**
     * 설치 여부 확인
     *
     * @return bool
     */
    public function checkInstalled()
    {
        return Schema::hasTable('kakao_channel_conversations')
            && app('xe.config')->get('kakao_channel') !== null;
    }

    /**
     * 플러그인 삭제: 테이블 및 설정 제거
     *
     * @return void
     */
    public function uninstall()
    {
        Schema::dropIfExists('kakao_channel_conversations');

        $config = app('xe.config')->get('kakao_channel');
        if ($config !== null) {
            app('xe.config')->remove($config);
        }
    }

    /**
     * 관리자 설정 페이지 URL 반환
     * 플러그인 목록의 '관리' 버튼에 연결됩니다.
     *
     * @return string
     */
    public function getSettingsURI()
    {
        return route('settings.kakao_channel.index');
    }

    /**
     * 카카오 webhook POST 라우트를 등록합니다.
     * 카카오 서버에서 CSRF 토큰 없이 POST 요청을 보내므로 CSRF 검증에서 제외합니다.
     *
     * @return void
     */
    protected function registerWebhookRoute()
    {
        ExceptAppendableVerifyCsrfToken::setExcept('kakao/webhook');

        \Route::group(['middleware' => ['web']], function () {
            \Route::post('kakao/webhook', [
                'as'   => 'kakao_channel.webhook',
                'uses' => 'KakaoChannel\Http\Controllers\WebhookController@handle',
            ]);
        });
    }

    /**
     * 관리자 설정 라우트를 등록합니다.
     *
     * @return void
     */
    protected function registerSettingsRoute()
    {
        \Route::settings('kakao_channel', function () {
            \Route::get('/', [
                'as'            => 'settings.kakao_channel.index',
                'uses'          => 'KakaoChannel\Http\Controllers\SettingsController@index',
                'settings_menu' => 'kakao_channel.settings',
                'permission'    => 'kakao_channel',
            ]);

            \Route::post('/', [
                'as'         => 'settings.kakao_channel.update',
                'uses'       => 'KakaoChannel\Http\Controllers\SettingsController@update',
                'permission' => 'kakao_channel',
            ]);

            \Route::get('/logs', [
                'as'            => 'settings.kakao_channel.logs',
                'uses'          => 'KakaoChannel\Http\Controllers\SettingsController@logs',
                'settings_menu' => 'kakao_channel.logs',
                'permission'    => 'kakao_channel',
            ]);

            \Route::delete('/logs/{userKey}', [
                'as'         => 'settings.kakao_channel.logs.clear',
                'uses'       => 'KakaoChannel\Http\Controllers\SettingsController@clearUserHistory',
                'permission' => 'kakao_channel',
            ]);
        });
    }

    /**
     * 관리자 사이드바 설정 메뉴를 등록합니다.
     *
     * @return void
     */
    protected function registerSettingsMenu()
    {
        $register = app('xe.register');

        // 최상위 메뉴 항목
        $register->push('settings/menu', 'kakao_channel', [
            'title'       => '카카오채널 AI',
            'description' => '카카오채널 AI 자동답변 관리',
            'display'     => true,
            'ordering'    => 7000,
            'icon'        => 'xi-chat-o',
        ]);

        // 설정 서브메뉴
        $register->push('settings/menu', 'kakao_channel.settings', [
            'title'       => '설정',
            'description' => 'AI 및 카카오채널 설정',
            'display'     => true,
            'ordering'    => 100,
        ]);

        // 대화 로그 서브메뉴
        $register->push('settings/menu', 'kakao_channel.logs', [
            'title'       => '대화 로그',
            'description' => '사용자 대화 이력 조회',
            'display'     => true,
            'ordering'    => 200,
        ]);
    }

    /**
     * 지원 클래스 파일을 로드합니다.
     * vendor/autoload.php가 없는 개발 환경에서의 fallback입니다.
     *
     * @return void
     */
    protected function loadSupportFiles()
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            return;
        }

        // Fallback: 각 클래스 파일 직접 로드
        $files = [
            __DIR__ . '/src/Models/KakaoConversation.php',
            __DIR__ . '/src/Services/AiService.php',
            __DIR__ . '/src/Services/ConversationService.php',
            __DIR__ . '/src/Http/Controllers/WebhookController.php',
            __DIR__ . '/src/Http/Controllers/SettingsController.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}
