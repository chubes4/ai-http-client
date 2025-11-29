# Anthropic Provider

Anthropic Claude provider implementation extending `AI_HTTP_BaseProvider`. Features native Files API, vision support, streaming, and function calling.

## Configuration

```php
$config = [
    'api_key' => 'your-anthropic-api-key'
];
```

## Supported Features

- **Claude Models**: All Claude model variants
- **Files API**: Native file upload with 500MB limit
- **Vision**: Image processing and analysis
- **Function Calling**: Tool execution support
- **Streaming**: Real-time response streaming
- **System Messages**: Dedicated system message support

## Request Format

Standard unified format with Anthropic-specific parameters:

```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello']
    ],
    'model' => 'claude-3-5-sonnet-20241022',
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'system' => 'You are a helpful assistant.',
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get weather information',
            'input_schema' => [
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

Supports text, images, and documents:

```php
$message = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => 'Analyze this document:'],
        ['type' => 'file', 'file_path' => '/path/to/document.pdf']
    ]
];
```

## Supported File Types

- **Images**: JPEG, PNG, GIF, WebP
- **Documents**: PDF, plain text
- **Maximum Size**: 500MB per file
- **Storage Limit**: 100GB per organization

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
        'model' => 'claude-3-5-sonnet-20241022',
        'finish_reason' => 'end_turn',
        'tool_calls' => [
            [
                'name' => 'get_weather',
                'parameters' => ['location' => 'New York']
            ]
        ]
    ],
    'error' => null,
    'provider' => 'anthropic'
]
```

## File Operations

### Upload File
```php
$file_id = $provider->upload_file('/path/to/file.pdf', 'user_data');
```

### Delete File
```php
$success = $provider->delete_file('file-id');
```

## Error Handling

Specific error messages for common issues:
- **Invalid file type**: Unsupported file format
- **File too large**: Exceeds 500MB limit
- **Storage limit exceeded**: Organization over 100GB limit
- **413 Payload Too Large**: File exceeds size limits

## Streaming Requests

```php
$provider = ai_http_create_provider('anthropic', ['api_key' => 'key']);
$response = $provider->streaming_request($request, function($chunk) {
    echo $chunk;
});
```

## Model Support

Returns normalized model list from Anthropic's `/models` endpoint with display names. Supports all Claude model variants including Opus, Sonnet, and Haiku.