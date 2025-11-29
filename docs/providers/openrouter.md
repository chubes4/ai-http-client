# OpenRouter Provider

OpenRouter provider implementation extending `AI_HTTP_BaseProvider`. Provides access to 200+ AI models through a unified gateway with OpenAI-compatible format.

## Configuration

```php
$config = [
    'api_key' => 'your-openrouter-api-key',
    'http_referer' => 'https://your-site.com', // Optional
    'app_title' => 'Your App Name' // Optional
];
```

## Supported Features

- **200+ Models**: Access to models from multiple providers
- **OpenAI Compatibility**: Uses OpenAI-compatible API format
- **File Support**: Base64-encoded images via unified file filter
- **Function Calling**: Tool execution support
- **Credits System**: Pay-per-use pricing across providers
- **Model Routing**: Automatic routing to appropriate endpoints

## Request Format

Standard unified format with OpenRouter-specific parameters:

```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ],
    'model' => 'anthropic/claude-3.5-sonnet',
    'max_tokens' => 1000,
    'temperature' => 0.7,
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

## Multimodal Content

Images supported via base64 encoding:

```php
$message = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => 'Describe this image:'],
        [
            'type' => 'image_url',
            'image_url' => ['url' => 'data:image/jpeg;base64,/9j/4AAQ...']
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
            'prompt_tokens' => 15,
            'completion_tokens' => 25,
            'total_tokens' => 40
        ],
        'model' => 'anthropic/claude-3.5-sonnet',
        'finish_reason' => 'stop',
        'tool_calls' => [
            [
                'name' => 'get_weather',
                'parameters' => ['location' => 'New York']
            ]
        ]
    ],
    'error' => null,
    'provider' => 'openrouter'
]
```

## Model Format

Models use provider/model format:
- `openai/gpt-4o`
- `anthropic/claude-3.5-sonnet`
- `google/gemini-pro`
- `meta/llama-3.1-405b-instruct`

## File Support

Images are converted to base64 data URLs using the unified file filter. No native Files API - uses OpenAI-compatible image format.

## Streaming Requests

```php
$provider = ai_http_create_provider('openrouter', ['api_key' => 'key']);
$response = $provider->streaming_request($request, function($chunk) {
    echo $chunk;
});
```

## Model Support

Returns comprehensive model list from OpenRouter's `/models` endpoint with display names. Supports all available models across all integrated providers.