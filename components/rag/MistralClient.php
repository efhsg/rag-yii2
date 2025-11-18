<?php

namespace app\components\rag;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Mistral API client for both embeddings and chat completion
 * Handles both embedding generation and chat responses
 */
class MistralClient
{
    private Client $httpClient;
    private string $apiKey;
    private string $baseUrl;
    private array $config;
    private float $lastRequestTime = 0;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->apiKey = $config['api_key'];
        $this->baseUrl = $config['base_url'];

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $config['timeout'],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    /**
     * Rate limiting - ensure minimum time between requests
     */
    private function respectRateLimit(): void
    {
        $timeSinceLastRequest = microtime(true) - $this->lastRequestTime;
        $minInterval = $this->config['rate_limit_seconds'] ?? 2.0; // Use config value or default to 2 seconds

        if ($timeSinceLastRequest < $minInterval) {
            $sleepTime = $minInterval - $timeSinceLastRequest;
            // Don't echo rate limiting messages as they break JSON responses
            // echo "Rate limiting: waiting " . number_format($sleepTime, 1) . " seconds...\n";
            usleep((int)($sleepTime * 1000000)); // Convert to microseconds
        }

        $this->lastRequestTime = microtime(true);
    }

    /**
     * Generate embeddings for text using Mistral's embedding model
     * @throws GuzzleException
     * @throws Exception
     */
    public function generateEmbedding(string $text): array
    {
        $this->respectRateLimit();

        try {
            // The correct endpoint is /v1/embeddings, not /embeddings
            $response = $this->httpClient->post('/v1/embeddings', [
                'json' => [
                    'model' => $this->config['embedding_model'],
                    'input' => [$text]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['data'][0]['embedding'])) {
                throw new Exception('Invalid embedding response from Mistral API: ' . json_encode($data));
            }

            return $data['data'][0]['embedding'];
        } catch (RequestException $e) {
            $responseBody = '';
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
            }
            throw new Exception('Mistral embedding API error: ' . $e->getMessage() . ' Response: ' . $responseBody);
        }
    }

    /**
     * Generate chat completion using Mistral
     * @throws GuzzleException
     * @throws Exception
     */
    public function generateChatResponse(string $userMessage, array $context = [], array $chatConfig = []): string
    {
        $this->respectRateLimit();

        try {
            // Use config values with override option
            $maxTokens = $chatConfig['max_tokens'] ?? $this->config['max_tokens'] ?? 500;
            $temperature = $chatConfig['temperature'] ?? $this->config['temperature'] ?? 0.7;
            $model = $chatConfig['model'] ?? $this->config['chat_model'];

            // Build system prompt with context
            $systemPrompt = "You are a helpful assistant. ";
            if (!empty($context)) {
                $systemPrompt .= "Use the following context to answer the user's question:\n\n";
                foreach ($context as $ctx) {
                    $systemPrompt .= "- " . $ctx['content'] . "\n";
                }
                $systemPrompt .= "\nAnswer based on this context when relevant.";
            }

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ];

            // The correct endpoint is /v1/chat/completions
            $response = $this->httpClient->post('/v1/chat/completions', [
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['choices'][0]['message']['content'])) {
                throw new Exception('Invalid chat response from Mistral API: ' . json_encode($data));
            }

            return $data['choices'][0]['message']['content'];
        } catch (RequestException $e) {
            $responseBody = '';
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
            }
            throw new Exception('Mistral chat API error: ' . $e->getMessage() . ' Response: ' . $responseBody);
        }
    }

    /**
     * Test API connection
     * @throws GuzzleException
     */
    public function testConnection(): bool
    {
        try {
            // Test with a simple embedding request
            $this->generateEmbedding("test connection");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
