@section('page_title')
    <h2>카카오채널 AI 자동답변 설정</h2>
@endsection

@section('page_description')
    <small>카카오 i 오픈빌더 스킬 서버 연동 및 AI 자동답변 설정</small>
@endsection

@if(session('alert'))
    <div class="alert alert-{{ session('alert.type') }}">
        {{ session('alert.message') }}
    </div>
@endif

<div class="panel-group">

    {{-- 웹훅 URL 안내 --}}
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">카카오 i 오픈빌더 스킬 서버 URL</h3>
        </div>
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:0;">
                <strong>아래 URL을 카카오 i 오픈빌더의 스킬 서버 URL로 등록하세요:</strong>
                <div style="margin-top:8px;">
                    <code id="webhook-url">{{ $webhookUrl }}</code>
                    <button type="button" class="btn btn-xs btn-default" style="margin-left:8px;"
                            onclick="copyWebhookUrl()">복사</button>
                </div>
                <small class="text-muted" style="margin-top:6px;display:block;">
                    POST 방식으로 연동됩니다. 카카오 서버에서 직접 호출하는 공개 URL입니다.
                </small>
            </div>
        </div>
    </div>

    {{-- 설정 폼 --}}
    <form action="{{ route('settings.kakao_channel.update') }}" method="POST">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">

        {{-- AI 제공자 설정 --}}
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">AI 제공자 설정</h3>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label class="control-label">AI 제공자 선택</label>
                    <select name="ai_provider" id="ai_provider" class="form-control" style="max-width:250px;">
                        <option value="openai" {{ $settings['ai_provider'] === 'openai' ? 'selected' : '' }}>
                            OpenAI GPT
                        </option>
                        <option value="claude" {{ $settings['ai_provider'] === 'claude' ? 'selected' : '' }}>
                            Anthropic Claude
                        </option>
                    </select>
                </div>

                {{-- OpenAI 설정 --}}
                <div id="openai-settings">
                    <hr>
                    <h4>OpenAI 설정</h4>
                    <div class="form-group">
                        <label class="control-label">OpenAI API Key</label>
                        <input type="password" name="openai_api_key" class="form-control" style="max-width:520px;"
                               placeholder="{{ !empty($settings['openai_api_key']) ? '저장된 API 키가 있습니다. 변경 시에만 입력하세요.' : 'sk-...' }}">
                        <p class="help-block">비워두면 기존에 저장된 키가 유지됩니다.</p>
                    </div>
                    <div class="form-group">
                        <label class="control-label">OpenAI 모델</label>
                        <input type="text" name="openai_model" class="form-control" style="max-width:300px;"
                               value="{{ $settings['openai_model'] }}"
                               placeholder="gpt-4o">
                        <p class="help-block">예: gpt-4o, gpt-4o-mini, gpt-3.5-turbo</p>
                    </div>
                </div>

                {{-- Claude 설정 --}}
                <div id="claude-settings" style="display:none;">
                    <hr>
                    <h4>Anthropic Claude 설정</h4>
                    <div class="form-group">
                        <label class="control-label">Anthropic API Key</label>
                        <input type="password" name="claude_api_key" class="form-control" style="max-width:520px;"
                               placeholder="{{ !empty($settings['claude_api_key']) ? '저장된 API 키가 있습니다. 변경 시에만 입력하세요.' : 'sk-ant-...' }}">
                        <p class="help-block">비워두면 기존에 저장된 키가 유지됩니다.</p>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Claude 모델</label>
                        <input type="text" name="claude_model" class="form-control" style="max-width:300px;"
                               value="{{ $settings['claude_model'] }}"
                               placeholder="claude-3-5-sonnet-20241022">
                        <p class="help-block">예: claude-3-5-sonnet-20241022, claude-3-haiku-20240307</p>
                    </div>
                </div>

            </div>
        </div>

        {{-- AI 동작 설정 --}}
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">AI 동작 설정</h3>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label class="control-label">시스템 프롬프트</label>
                    <textarea name="system_prompt" class="form-control" rows="5" style="max-width:700px;"
                              placeholder="AI의 역할과 응답 방식을 정의합니다.">{{ $settings['system_prompt'] }}</textarea>
                    <p class="help-block">
                        예: "당신은 [회사명]의 친절한 고객 상담 AI입니다. 제품에 관한 질문에 정확하고 간결하게 답변해 주세요."
                    </p>
                </div>

                <div class="form-group">
                    <label class="control-label">최대 대화 이력 수</label>
                    <input type="number" name="max_history" class="form-control" style="max-width:150px;"
                           value="{{ $settings['max_history'] }}" min="1" max="50">
                    <p class="help-block">
                        사용자별로 기억할 최대 대화 쌍(user + assistant) 수입니다.
                        많을수록 문맥 이해가 정확하지만 AI 비용이 증가합니다. (권장: 5~15)
                    </p>
                </div>

            </div>
        </div>

        <div style="margin-bottom:20px;">
            <button type="submit" class="btn btn-primary btn-lg">설정 저장</button>
            <a href="{{ route('settings.kakao_channel.logs') }}" class="btn btn-default btn-lg" style="margin-left:8px;">
                대화 로그 보기
            </a>
        </div>

    </form>
</div>

<script>
(function () {
    var providerSelect = document.getElementById('ai_provider');
    var openaiDiv = document.getElementById('openai-settings');
    var claudeDiv = document.getElementById('claude-settings');

    function toggleProvider() {
        if (providerSelect.value === 'claude') {
            openaiDiv.style.display = 'none';
            claudeDiv.style.display = '';
        } else {
            openaiDiv.style.display = '';
            claudeDiv.style.display = 'none';
        }
    }

    providerSelect.addEventListener('change', toggleProvider);
    toggleProvider();
})();

function copyWebhookUrl() {
    var url = document.getElementById('webhook-url').textContent;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
            alert('URL이 복사되었습니다.');
        });
    } else {
        var el = document.createElement('textarea');
        el.value = url;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        alert('URL이 복사되었습니다.');
    }
}
</script>
