# Gemini Provider

Google Gemini provider implementation extending `AI_HTTP_BaseProvider`. Features native Files API, multi-modal support, streaming, and function calling.

## Configuration

```php
$config = [
    'api_key' => 'your-gemini-api-key'
];
```

## Supported Features

- **Gemini Models**: All Gemini model variants
- **Files API**: Native file upload with extensive format support
- **Multi-modal**: Images, audio, video, and documents
- **Function Calling**: Tool execution support
- **Streaming**: Real-time response streaming
- **Unique URL Structure**: Model name in API path

## Request Format

Standard unified format with Gemini-specific parameters:

```php
$request = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => 'Hello']
            ]
        ]
    ],
    'model' => 'gemini-1.5-pro',
    'generationConfig' => [
        'maxOutputTokens' => 1000,
        'temperature' => 0.7
    ],
    'tools' => [
        [
            'functionDeclarations' => [
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
        ]
    ]
];
```

## Multimodal Content

Extensive file type support:

```php
$content = [
    'type' => 'file',
    'file_uri' => 'https://generativelanguage.googleapis.com/v1beta/files/file-id'
];
```

## Supported File Types

- **Images**: JPEG, JPG, PNG, GIF, WebP, BMP
- **Audio**: WAV, MP3, MPEG, AIFF, AAC, OGG, FLAC
- **Video**: MP4, MPEG, MOV, AVI, FLV, MPG, WebM, WMV, 3GPP
- **Documents**: PDF, plain text
- **Maximum Size**: Varies by type (images up to 20MB, video up to 2GB)

## Response Format

Standard unified response:

```php
[
    'success' => true,
    'data' => [
        'content' => 'Response text',
        'usage' => [
            'prompt_tokens' => 12,
            'completion_tokens' => 18,
            'total_tokens' => 30
        ],
        'model' => 'gemini-1.5-pro',
        'finish_reason' => 'STOP',
        'tool_calls' => [
            [
                'name' => 'get_weather',
                'parameters' => ['location' => 'New York']
            ]
        ]
    ],
    'error' => null,
    'provider' => 'gemini'
]
```

## File Operations

### Upload File
```php
$file_uri = $provider->upload_file('/path/to/file.mp4', 'user_data');
```

### Delete File
```php
$success = $provider->delete_file('file-uri');
```

## URL Structure

Gemini uses model names in the API path:
- Generate: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- Stream: `https://generativelanguage.googleapis.com/v1beta/models/{model}:streamGenerateContent`

## Streaming Requests

```php
$provider = ai_http_create_provider('gemini', ['api_key' => 'key']);
$response = $provider->streaming_request($request, function($chunk) {
    echo $chunk;
});
```

## Model Support

Returns normalized model list from Gemini's `/models` endpoint. Supports all Gemini variants including Pro, Pro Vision, Flash, and experimental models.