# Grok Provider

Grok/X.AI provider implementation extending `AI_HTTP_BaseProvider`. Uses OpenAI-compatible format with streaming support and function calling.

## Configuration

```php
$config = [
    'api_key' => 'your-grok-api-key'
];
```

## Supported Features

- **Grok Models**: All Grok model variants
- **OpenAI Compatibility**: Uses OpenAI-compatible API format
- **Function Calling**: Tool execution support
- **Streaming**: Real-time response streaming
- **Reasoning Effort**: Configurable reasoning levels

## Request Format

Standard unified format with Grok-specific parameters:

```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ],
    'model' => 'grok-beta',
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'reasoning_effort' => 'medium',
    'tools' => [
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'description' => 'Get weather information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => ['type' => 'string']
                    ],
                    'required' => ['location']
                ]
            ]
        ]
    ]
];
```

## Response Format

Standard unified response:

```php
[
    'success' => true,
    'data' => [
        'content' => 'Response text',
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30
        ],
        'model' => 'grok-beta',
        'finish_reason' => 'stop',
        'tool_calls' => [
            [
                'name' => 'get_weather',
                'parameters' => ['location' => 'New York']
            ]
        ]
    ],
    'error' => null,
    'provider' => 'grok'
]
```

## Streaming Requests

```php
$provider = ai_http_create_provider('grok', ['api_key' => 'key']);
$response = $provider->streaming_request($request, function($chunk) {
    echo $chunk;
});
```

## Model Support

Returns normalized model list from Grok's `/models` endpoint. Supports all Grok model variants including beta and experimental models.