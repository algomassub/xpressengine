@section('page_title')
    <h2>카카오채널 AI 대화 로그</h2>
@endsection

@section('page_description')
    <small>사용자별 대화 이력 조회 및 관리</small>
@endsection

@if(session('alert'))
    <div class="alert alert-{{ session('alert.type') }}">
        {{ session('alert.message') }}
    </div>
@endif

<div class="panel panel-default">
    <div class="panel-heading">
        <div class="pull-right">
            <a href="{{ route('settings.kakao_channel.index') }}" class="btn btn-default btn-sm">← 설정으로 돌아가기</a>
        </div>
        <h3 class="panel-title">대화 이력</h3>
    </div>

    {{-- 필터 폼 --}}
    <div class="panel-body" style="border-bottom:1px solid #ddd;">
        <form method="GET" action="{{ route('settings.kakao_channel.logs') }}" class="form-inline">
            <div class="form-group" style="margin-right:10px;">
                <label>사용자 키</label>
                <input type="text" name="user_key" class="form-control input-sm"
                       value="{{ request('user_key') }}" placeholder="카카오 사용자 키">
            </div>
            <div class="form-group" style="margin-right:10px;">
                <label>역할</label>
                <select name="role" class="form-control input-sm">
                    <option value="">전체</option>
                    <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>사용자</option>
                    <option value="assistant" {{ request('role') === 'assistant' ? 'selected' : '' }}>AI</option>
                </select>
            </div>
            <div class="form-group" style="margin-right:10px;">
                <label>시작일</label>
                <input type="date" name="from" class="form-control input-sm" value="{{ request('from') }}">
            </div>
            <div class="form-group" style="margin-right:10px;">
                <label>종료일</label>
                <input type="date" name="to" class="form-control input-sm" value="{{ request('to') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">검색</button>
            <a href="{{ route('settings.kakao_channel.logs') }}" class="btn btn-default btn-sm" style="margin-left:5px;">초기화</a>
        </form>
    </div>

    {{-- 로그 테이블 --}}
    <div class="table-responsive">
        <table class="table table-striped table-hover" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:160px;">사용자 키</th>
                    <th style="width:70px;">역할</th>
                    <th>메시지</th>
                    <th style="width:150px;">일시</th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversations as $conv)
                    <tr class="{{ $conv->role === 'assistant' ? 'info' : '' }}">
                        <td>{{ $conv->id }}</td>
                        <td>
                            <small style="word-break:break-all;">{{ $conv->user_key }}</small>
                        </td>
                        <td>
                            @if($conv->role === 'user')
                                <span class="label label-default">사용자</span>
                            @else
                                <span class="label label-primary">AI</span>
                            @endif
                        </td>
                        <td>
                            <div style="max-width:500px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                 title="{{ $conv->message }}">
                                {{ $conv->message }}
                            </div>
                        </td>
                        <td>
                            <small>{{ $conv->created_at->format('Y-m-d H:i:s') }}</small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding:30px;">
                            대화 이력이 없습니다.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($conversations->hasPages())
        <div class="panel-footer">
            {!! $conversations->render() !!}
        </div>
    @endif
</div>
