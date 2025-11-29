# Error Management Action

Centralized error handling using WordPress action hooks for developer-friendly error events across all library components.

## Primary Action Hook: chubes_ai_library_error

Unified error event triggered by all library components for monitoring and debugging.

```php
add_action('chubes_ai_library_error', function($error_data) {
    // Handle all library errors
    $component = $error_data['component'];
    $message = $error_data['message'];
    $context = $error_data['context'];
    $timestamp = $error_data['timestamp'];
    
    // Log or handle error
    error_log("AI Library Error [{$component}]: {$message}");
});
```

## Error Data Structure

```php
$error_data = [
    'component' => 'Requests|RestApi|OpenAI|Anthropic|Gemini|Grok|OpenRouter',
    'message' => 'Human-readable error description',
    'context' => [
        'provider' => 'provider_name',
        'endpoint' => 'api_endpoint',
        'http_code' => 500,
        'error_type' => 'connection_error|api_error|validation_error',
        // Additional context-specific data
    ],
    'timestamp' => 1234567890
];
```

## Error Components

### Requests
HTTP communication and request processing errors.

### RestApi
REST API endpoint errors.

### Provider Names
- **OpenAI**: OpenAI API errors
- **Anthropic**: Anthropic Claude API errors
- **Gemini**: Google Gemini API errors
- **Grok**: Grok/X.AI API errors
- **OpenRouter**: OpenRouter API errors

## Error Types

### connection_error
Network connectivity issues, DNS failures, timeouts.

### api_error
Provider API returned error status codes (4xx, 5xx).

### validation_error
Input validation failures, malformed requests.

### curl_error
cURL-specific errors during HTTP requests.

### http_error
HTTP protocol errors, status codes outside 200-299 range.

## Usage Examples

### Basic Error Logging
```php
add_action('chubes_ai_library_error', function($error_data) {
    $log_message = sprintf(
        '[%s] AI Library Error in %s: %s',
        date('Y-m-d H:i:s', $error_data['timestamp']),
        $error_data['component'],
        $error_data['message']
    );
    
    error_log($log_message);
    
    // Log additional context
    if (!empty($error_data['context'])) {
        error_log('Context: ' . json_encode($error_data['context']));
    }
});
```

### Provider-Specific Handling
```php
add_action('chubes_ai_library_error', function($error_data) {
    $context = $error_data['context'];
    
    // Handle API errors differently from connection errors
    if (isset($context['error_type'])) {
        switch ($context['error_type']) {
            case 'api_error':
                // Provider API returned an error
                if (isset($context['http_code'])) {
                    handle_api_error($context['http_code'], $error_data);
                }
                break;
                
            case 'connection_error':
                // Network connectivity issue
                handle_connection_error($error_data);
                break;
        }
    }
    
    // Provider-specific handling
    if (isset($context['provider'])) {
        switch ($context['provider']) {
            case 'openai':
                handle_openai_error($error_data);
                break;
            case 'anthropic':
                handle_anthropic_error($error_data);
                break;
        }
    }
});
```

### Error Monitoring Dashboard
```php
class AI_Error_Monitor {
    private $errors = [];
    
    public function __construct() {
        add_action('chubes_ai_library_error', [$this, 'collect_error']);
    }
    
    public function collect_error($error_data) {
        $this->errors[] = $error_data;
        
        // Keep only last 100 errors
        if (count($this->errors) > 100) {
            array_shift($this->errors);
        }
    }
    
    public function get_error_stats() {
        $stats = [
            'total_errors' => count($this->errors),
            'by_component' => [],
            'by_provider' => [],
            'by_error_type' => []
        ];
        
        foreach ($this->errors as $error) {
            $component = $error['component'];
            $context = $error['context'];
            
            $stats['by_component'][$component] = ($stats['by_component'][$component] ?? 0) + 1;
            
            if (isset($context['provider'])) {
                $stats['by_provider'][$context['provider']] = ($stats['by_provider'][$context['provider']] ?? 0) + 1;
            }
            
            if (isset($context['error_type'])) {
                $stats['by_error_type'][$context['error_type']] = ($stats['by_error_type'][$context['error_type']] ?? 0) + 1;
            }
        }
        
        return $stats;
    }
}
```

## Error Triggering

Errors are automatically triggered by the library. Manual triggering is not needed for normal usage.

## Integration Benefits

- **Centralized Monitoring**: Single hook for all library errors
- **Rich Context**: Detailed error information for debugging
- **Plugin Integration**: Easy integration with existing error monitoring systems
- **Performance Tracking**: Monitor error rates and patterns