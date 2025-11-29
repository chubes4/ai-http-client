# OpenAI Provider

OpenAI provider implementation extending `AI_HTTP_BaseProvider`. Supports Responses API, native Files API, function calling, and streaming.

## Configuration

```php
$config = [
    'api_key' => 'your-openai-api-key',
    'organization' => 'org-id' // Optional
];
```

## Supported Features

- **Responses API**: Latest OpenAI API format
- **Files API**: Native file upload and management
- **Function Calling**: Tool execution support
- **Streaming**: Real-time response streaming
- **Vision**: Image processing via Files API

## Request Format

Standard unified format with OpenAI-specific extensions:

```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ],
    'model' => 'gpt-4o',
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'tools' => [
        [
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
];
```

## Multimodal Content

Supports text, images, and files:

```php
$message = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => 'Describe this image:'],
        ['type' => 'file', 'file_path' => '/path/to/image.jpg']
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
        'model' => 'gpt-4o',
        'finish_reason' => 'stop',
        'tool_calls' => [
            [
                'name' => 'get_weather',
                'parameters' => ['location' => 'New York']
            ]
        ]
    ],
    'error' => null,
    'provider' => 'openai'
]
```

## File Operations

### Upload File
```php
$file_id = $provider->upload_file('/path/to/file.pdf', 'assistants');
```

### Delete File
```php
$success = $provider->delete_file('file-id');
```

## Streaming Requests

```php
$provider = ai_http_create_provider('openai', ['api_key' => 'key']);
$response = $provider->streaming_request($request, function($chunk) {
    echo $chunk;
});
```

## Model Support

Returns normalized model list from OpenAI's `/models` endpoint. All available models are supported through the Responses API.