# Usage Examples

Complete code examples showing common AI HTTP Client usage patterns for WordPress integration.

## Basic AI Request

Simple text completion request using OpenAI:

```php
// Set API key first
$api_keys = ['openai' => 'your-openai-api-key'];
apply_filters('chubes_ai_provider_api_keys', $api_keys);

// Make AI request
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, how are you?']
    ],
    'model' => 'gpt-4o',
    'max_tokens' => 100,
    'temperature' => 0.7
];

$response = apply_filters('chubes_ai_request', $request, 'openai');

if ($response['success']) {
    echo $response['data']['content'];
} else {
    echo 'Error: ' . $response['error'];
}
```

## Streaming Response

Real-time streaming response handling:

```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Write a short story']
    ],
    'model' => 'claude-3-5-sonnet-20241022'
];

// Streaming callback function
$streaming_callback = function($chunk) {
    echo $chunk;
    flush(); // Send chunk immediately to browser
};

$response = apply_filters('chubes_ai_request', $request, 'anthropic', $streaming_callback);
```

## Function Calling

Using tools/functions with AI models:

```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'What\'s the weather like in New York?']
    ],
    'model' => 'gpt-4o',
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get current weather for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name'
                    ]
                ],
                'required' => ['location']
            ]
        ]
    ]
];

$response = apply_filters('chubes_ai_request', $request, 'openai');

if ($response['success'] && $response['data']['tool_calls']) {
    foreach ($response['data']['tool_calls'] as $tool_call) {
        if ($tool_call['name'] === 'get_weather') {
            $location = $tool_call['parameters']['location'];
            // Execute weather API call here
            $weather = get_weather_data($location);
            echo "Weather in {$location}: {$weather}";
        }
    }
}
```

## Multimodal Content

Using images and files with vision-enabled models:

```php
// Convert image to base64
$base64_image = apply_filters('chubes_ai_file_to_base64', '', '/path/to/image.jpg');

$request = [
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'Describe this image:'],
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => $base64_image]
                ]
            ]
        ]
    ],
    'model' => 'claude-3-5-sonnet-20241022'
];

$response = apply_filters('chubes_ai_request', $request, 'anthropic');
```

## File Upload

Uploading files to provider Files APIs:

```php
// Upload file to OpenAI
$provider = ai_http_create_provider('openai', ['api_key' => 'your-key']);
$file_id = $provider->upload_file('/path/to/document.pdf', 'assistants');

// Use file in request
$request = [
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'file', 'file_path' => '/path/to/document.pdf']
            ]
        ]
    ],
    'model' => 'gpt-4o'
];

$response = apply_filters('chubes_ai_request', $request, 'openai');
```

## Model Management

Getting available models and caching:

```php
// Get cached models (24-hour cache)
$models = apply_filters('chubes_ai_models', 'openai', ['api_key' => 'your-key']);

// Clear model cache
do_action('chubes_ai_clear_model_cache', 'openai');

// Clear all caches
do_action('chubes_ai_clear_all_cache');
```

## REST API Integration

Using REST API endpoints for configuration:

```php
// Set API key via REST API
wp_remote_post('/wp-json/ai-http-client/v1/api-keys/openai', [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ],
    'body' => wp_json_encode([
        'api_key' => 'your-new-api-key'
    ])
]);

// Get available models
$models_response = wp_remote_get('/wp-json/ai-http-client/v1/models/openai', [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);
```

## Error Handling

Comprehensive error monitoring:

```php
add_action('chubes_ai_library_error', function($error_data) {
    $component = $error_data['component'];
    $message = $error_data['message'];
    $context = $error_data['context'];
    
    // Log errors
    error_log("AI Error [{$component}]: {$message}");
    
    // Handle specific error types
    if (isset($context['error_type'])) {
        switch ($context['error_type']) {
            case 'api_error':
                // Provider API error
                handle_api_error($context);
                break;
            case 'connection_error':
                // Network connectivity issue
                handle_connection_error($context);
                break;
        }
    }
});
```

## Provider Switching

Easy switching between AI providers:

```php
$providers = ['openai', 'anthropic', 'gemini', 'grok', 'openrouter'];
$user_preference = 'anthropic'; // Could come from user settings

$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Explain quantum computing']
    ],
    'model' => get_default_model_for_provider($user_preference)
];

$response = apply_filters('chubes_ai_request', $request, $user_preference);
```

## Conversation Continuation

Maintaining conversation context:

```php
$conversation_data = [
    'conversation_history' => [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!']
    ]
];

$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'How are you?']
    ],
    'model' => 'gpt-4o'
];

$response = apply_filters('chubes_ai_request', $request, 'openai', null, null, $conversation_data);
```

## Batch Processing

Processing multiple requests:

```php
$requests = [
    ['content' => 'Summarize this article...', 'model' => 'gpt-4o-mini'],
    ['content' => 'Translate to French...', 'model' => 'claude-3-haiku-20240307'],
    ['content' => 'Analyze this code...', 'model' => 'gemini-pro']
];

$results = [];
foreach ($requests as $req_data) {
    $request = [
        'messages' => [['role' => 'user', 'content' => $req_data['content']]],
        'model' => $req_data['model']
    ];
    
    $provider = get_provider_for_model($req_data['model']);
    $results[] = apply_filters('chubes_ai_request', $request, $provider);
}
```