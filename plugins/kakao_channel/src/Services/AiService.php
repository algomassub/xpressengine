<?php

namespace KakaoChannel\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * AI API 통신 서비스
 *
 * OpenAI GPT 및 Anthropic Claude API를 지원합니다.
 * 관리자 설정에서 선택한 AI 제공자에 따라 자동으로 API를 선택합니다.
 */
class AiService
{
    /** @var array 플러그인 설정 */
    protected $config;

    /** @var Client GuzzleHttp 클라이언트 */
    protected $httpClient;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->httpClient = new Client([
            'timeout'         => 25.0,  // 카카오 5초 제한보다 여유있게
            'connect_timeout' => 5.0,
        ]);
    }

    /**
     * 사용자 발화에 대한 AI 답변 생성
     *
     * @param string $utterance  사용자 입력 텍스트
     * @param array  $history    이전 대화 이력 (OpenAI messages 형식)
     * @return string            AI 생성 답변
     */
    public function generateReply(string $utterance, array $history = []): string
    {
        $provider = $this->config['ai_provider'] ?? 'openai';

        // API 키 미설정 시 안내 메시지 반환
        if ($provider === 'claude') {
            $apiKey = $this->config['claude_api_key'] ?? '';
        } else {
            $apiKey = $this->config['openai_api_key'] ?? '';
        }

        if (empty(trim($apiKey))) {
            \Log::warning('KakaoChannel AiService: API 키가 설정되지 않았습니다.', ['provider' => $provider]);
            return '관리자 설정에서 AI API 키를 입력해 주세요. (설정 > 카카오채널 AI)';
        }

        try {
            if ($provider === 'claude') {
                return $this->callClaude($utterance, $history);
            }
            return $this->callOpenAi($utterance, $history);
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            \Log::error('KakaoChannel AiService 오류', [
                'provider'   => $provider,
                'status'     => $statusCode,
                'message'    => $e->getMessage(),
            ]);
            return '죄송합니다. 일시적인 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.';
        } catch (\Exception $e) {
            \Log::error('KakaoChannel AiService 예외', [
                'provider' => $provider,
                'message'  => $e->getMessage(),
            ]);
            return '죄송합니다. 답변을 생성하는 중 문제가 발생했습니다.';
        }
    }

    /**
     * OpenAI Chat Completions API 호출
     *
     * @param string $utterance
     * @param array  $history   [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @return string
     */
    protected function callOpenAi(string $utterance, array $history): string
    {
        $apiKey       = $this->config['openai_api_key'] ?? '';
        $model        = $this->config['openai_model'] ?? 'gpt-4o';
        $systemPrompt = $this->config['system_prompt'] ?? '';

        $messages = [];

        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        // 대화 이력 추가
        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // 현재 사용자 발화 추가
        $messages[] = ['role' => 'user', 'content' => $utterance];

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => 1000,
                'temperature' => 0.7,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Anthropic Claude Messages API 호출
     *
     * @param string $utterance
     * @param array  $history   [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @return string
     */
    protected function callClaude(string $utterance, array $history): string
    {
        $apiKey       = $this->config['claude_api_key'] ?? '';
        $model        = $this->config['claude_model'] ?? 'claude-3-5-sonnet-20241022';
        $systemPrompt = $this->config['system_prompt'] ?? '';

        $messages = [];

        // 대화 이력 추가 (Claude는 user/assistant 교대 필수)
        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // 현재 사용자 발화 추가
        $messages[] = ['role' => 'user', 'content' => $utterance];

        $body = [
            'model'      => $model,
            'max_tokens' => 1000,
            'messages'   => $messages,
        ];

        if (!empty($systemPrompt)) {
            $body['system'] = $systemPrompt;
        }

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'json' => $body,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return trim($data['content'][0]['text'] ?? '');
    }
}
