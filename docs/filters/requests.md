# Request Processing Filter

Core filter handling all AI request processing, HTTP communication, provider management, and response formatting in a unified pipeline.

## Primary Filter: chubes_ai_request

Main entry point for all AI requests. Handles provider selection, format conversion, and unified response processing.

```php
$response = apply_filters('chubes_ai_request', $request, $provider_name, $streaming_callback, $tools, $conversation_data);
```

### Parameters

- **$request** (array): Unified request format
- **$provider_name** (string): Provider identifier (openai, anthropic, gemini, grok, openrouter)
- **$streaming_callback** (callable, optional): Callback function for streaming responses
- **$tools** (array, optional): Tools/functions to make available
- **$conversation_data** (array, optional): Conversation continuation data

### Request Format

```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello world']
    ],
    'model' => 'gpt-4o',
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'tools' => [...], // Optional
    'system' => 'System message' // Optional
];
```

### Response Format

```php
[
    'success' => true,
    'data' => [
        'content' => 'AI response text',
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30
        ],
        'model' => 'gpt-4o',
        'finish_reason' => 'stop',
        'tool_calls' => null
    ],
    'error' => null,
    'provider' => 'openai',
    'raw_response' => [...] // Original provider response
]
```

## HTTP Communication Filter: chubes_ai_http

Internal filter handling all HTTP requests to AI provider APIs.

```php
$result = apply_filters('chubes_ai_http', [], $method, $url, $args, $context, $streaming, $callback);
```

### Parameters

- **$method** (string): HTTP method (GET, POST, PUT, DELETE, PATCH)
- **$url** (string): Request URL
- **$args** (array): Request arguments (headers, body, timeout)
- **$context** (string): Context description for error reporting
- **$streaming** (bool): Whether this is a streaming request
- **$callback** (callable): Streaming callback function

### Response Format

```php
[
    'success' => true,
    'data' => 'response body',
    'status_code' => 200,
    'headers' => [...],
    'error' => ''
]
```

## File Conversion Filter: chubes_ai_file_to_base64

Universal file-to-base64 conversion for image uploads.

```php
$base64_url = apply_filters('chubes_ai_file_to_base64', '', $file_path, $options);
```

### Parameters

- **$file_path** (string): Path to file to convert
- **$options** (array): Conversion options

### Options

```php
$options = [
    'max_size' => 10485760, // 10MB default
    'supported_types' => ['image/jpeg', 'image/png', ...]
];
```

### Response

Returns data URL format: `"data:image/jpeg;base64,/9j/4AAQ..."`

## Provider Creation

Internal function to create provider instances:

```php
$provider = ai_http_create_provider($provider_name, $config);
```

## File Upload Helper

Uploads files to provider Files APIs:

```php
$file_id = ai_http_upload_file_to_provider($file_path, $purpose, $provider_name, $config);
```

## Error Handling

All errors trigger `chubes_ai_library_error` action with structured error data for monitoring and debugging.