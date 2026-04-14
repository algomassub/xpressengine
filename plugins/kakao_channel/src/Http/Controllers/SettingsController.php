<?php

namespace KakaoChannel\Http\Controllers;

use Illuminate\Routing\Controller;
use KakaoChannel\Models\KakaoConversation;
use Xpressengine\Http\Request;

/**
 * 카카오채널 AI 자동답변 플러그인 관리자 설정 컨트롤러
 */
class SettingsController extends Controller
{
    /**
     * 설정 페이지 표시
     *
     * GET /settings/kakao_channel
     *
     * @return \Xpressengine\Presenter\Presentable
     */
    public function index()
    {
        $config = $this->getConfig();

        $settings = [
            'ai_provider'    => $config['ai_provider'] ?? 'openai',
            'openai_api_key' => $config['openai_api_key'] ?? '',
            'openai_model'   => $config['openai_model'] ?? 'gpt-4o',
            'claude_api_key' => $config['claude_api_key'] ?? '',
            'claude_model'   => $config['claude_model'] ?? 'claude-3-5-sonnet-20241022',
            'system_prompt'  => $config['system_prompt'] ?? '',
            'max_history'    => $config['max_history'] ?? 10,
        ];

        $webhookUrl = url('/kakao/webhook');

        return \XePresenter::make('kakao_channel::settings.index', compact('settings', 'webhookUrl'));
    }

    /**
     * 설정 저장
     *
     * POST /settings/kakao_channel
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $configObj = app('xe.config')->get('kakao_channel');
        $existing  = $configObj ? $configObj->getPureAll() : [];

        $newValues = [
            'ai_provider'  => $request->get('ai_provider', 'openai'),
            'openai_model' => $request->get('openai_model', 'gpt-4o'),
            'claude_model' => $request->get('claude_model', 'claude-3-5-sonnet-20241022'),
            'system_prompt'=> $request->get('system_prompt', ''),
            'max_history'  => (int) $request->get('max_history', 10),
        ];

        // API 키: 빈 값으로 제출 시 기존 키 유지
        $openaiKey = $request->get('openai_api_key', '');
        $newValues['openai_api_key'] = !empty($openaiKey)
            ? $openaiKey
            : ($existing['openai_api_key'] ?? '');

        $claudeKey = $request->get('claude_api_key', '');
        $newValues['claude_api_key'] = !empty($claudeKey)
            ? $claudeKey
            : ($existing['claude_api_key'] ?? '');

        if ($configObj === null) {
            app('xe.config')->add('kakao_channel', $newValues);
        } else {
            foreach ($newValues as $key => $value) {
                $configObj->set($key, $value);
            }
            app('xe.config')->modify($configObj);
        }

        return redirect()
            ->route('settings.kakao_channel.index')
            ->with('alert', ['type' => 'success', 'message' => '설정이 저장되었습니다.']);
    }

    /**
     * 대화 이력 로그 조회
     *
     * GET /settings/kakao_channel/logs
     *
     * @param Request $request
     * @return \Xpressengine\Presenter\Presentable
     */
    public function logs(Request $request)
    {
        $query = KakaoConversation::orderBy('created_at', 'desc');

        // 사용자 키 필터
        if ($userKey = $request->get('user_key')) {
            $query->where('user_key', $userKey);
        }

        // 역할 필터 (user / assistant)
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        // 날짜 범위 필터
        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $conversations = $query->paginate(30)->appends($request->query());

        return \XePresenter::make('kakao_channel::settings.logs', compact('conversations'));
    }

    /**
     * 특정 사용자 대화 이력 삭제
     *
     * DELETE /settings/kakao_channel/logs/{user_key}
     *
     * @param Request $request
     * @param string  $userKey
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearUserHistory(Request $request, $userKey)
    {
        KakaoConversation::where('user_key', $userKey)->delete();

        return redirect()
            ->route('settings.kakao_channel.logs')
            ->with('alert', ['type' => 'success', 'message' => "사용자 [{$userKey}]의 대화 이력이 삭제되었습니다."]);
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
}
