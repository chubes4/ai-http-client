# AI Tools System

The AI Tools system provides a centralized mechanism for registering and discovering AI tools across WordPress plugins. Tools enable AI models to perform specific functions like data processing, API calls, or custom operations.

## Overview

The tools system is built on WordPress filters, allowing any plugin to register tools that other plugins can discover and use. This enables a plugin ecosystem where AI capabilities can be extended through modular tool registration.

## Tool Registration

Tools are registered via the `chubes_ai_tools` filter. Each tool defines its interface, parameters, and capabilities:

```php
add_filter('chubes_ai_tools', function($tools) {
    $tools['web_search'] = [
        'type' => 'web_search_preview',
        'search_context_size' => 'medium',
        'category' => 'search',
        'description' => 'Search the web for current information'
    ];

    $tools['file_processor'] = [
        'class' => 'FileProcessor_Tool',
        'category' => 'file_handling',
        'description' => 'Process files and extract content',
        'parameters' => [
            'file_path' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Path to file to process'
            ],
            'operation' => [
                'type' => 'string',
                'enum' => ['extract_text', 'analyze', 'summarize'],
                'required' => false,
                'description' => 'Operation to perform on the file'
            ]
        ]
    ];

    return $tools;
});
```

## Tool Discovery

### Get All Tools

```php
// Get all registered tools
$all_tools = apply_filters('chubes_ai_tools', []);

// Returns array of tool definitions
```

### Filter by Category

```php
// Get tools by category
$search_tools = ai_http_get_tools('search');
$file_tools = ai_http_get_tools('file_handling');
```

## Tool Definition Format

Each tool definition supports the following properties:

### Basic Properties
- **`type`** (string): Tool type identifier
- **`class`** (string): PHP class name for tool implementation
- **`category`** (string): Categorization for organization
- **`description`** (string): Human-readable description

### Parameter Definition
- **`parameters`** (array): JSON Schema-style parameter definitions

```php
'parameters' => [
    'parameter_name' => [
        'type' => 'string|number|boolean|array|object',
        'required' => true|false,
        'description' => 'Parameter description',
        'enum' => ['option1', 'option2'], // Optional: allowed values
        'items' => ['type' => 'string'], // For array types
        'default' => 'default_value' // Optional: default value
    ]
]
```

## Built-in Helper Functions

### Tool Name to Definition Conversion

```php
// Convert common tool names to definitions
$definition = ai_http_convert_tool_name_to_definition('web_search_preview');
// Returns: ['type' => 'web_search_preview', 'search_context_size' => 'low']
```

### Tool Retrieval with Filtering

```php
// Get all tools (optionally filtered by category)
$tools = ai_http_get_tools(); // All tools
$search_tools = ai_http_get_tools('search'); // Category filter
```

## Integration with AI Requests

Tools are passed to AI requests to enable function calling:

```php
// Include tools in AI request
$request = [
    'messages' => [['role' => 'user', 'content' => 'Search for WordPress news']],
    'model' => 'gpt-4o'
];

$tools = apply_filters('chubes_ai_tools', []);
$response = apply_filters('chubes_ai_request', $request, 'openai', null, $tools);
```

## Tool Execution

**Important**: Tool execution is handled by the consuming plugin, not the AI HTTP Client library. The library only facilitates tool discovery and parameter passing.

When an AI model decides to use a tool, the response includes tool call information:

```php
$response = [
    'success' => true,
    'data' => [
        'content' => 'I found some WordPress news...',
        'tool_calls' => [
            [
                'name' => 'web_search',
                'parameters' => [
                    'query' => 'WordPress news',
                    'context_size' => 'medium'
                ]
            ]
        ]
    ]
];
```

## Best Practices

### Tool Naming
- Use descriptive, unique names
- Follow WordPress naming conventions (lowercase, underscores)
- Avoid conflicts with existing tool names

### Parameter Validation
- Always validate tool parameters before execution
- Use WordPress sanitization functions
- Provide clear error messages for invalid parameters

### Error Handling
- Implement robust error handling in tool execution
- Return meaningful error messages
- Log errors appropriately

### Documentation
- Provide comprehensive descriptions
- Document all parameters clearly
- Include usage examples

## Example Tool Implementation

```php
class WebSearch_Tool {
    public function execute($parameters) {
        // Validate parameters
        if (empty($parameters['query'])) {
            return ['error' => 'Query parameter is required'];
        }

        // Sanitize input
        $query = sanitize_text_field($parameters['query']);
        $context_size = sanitize_text_field($parameters['context_size'] ?? 'medium');

        // Perform search operation
        $results = $this->perform_web_search($query, $context_size);

        return [
            'success' => true,
            'results' => $results
        ];
    }

    private function perform_web_search($query, $context_size) {
        // Implementation details...
        return $results;
    }
}

// Register the tool
add_filter('chubes_ai_tools', function($tools) {
    $tools['web_search'] = [
        'class' => 'WebSearch_Tool',
        'category' => 'search',
        'description' => 'Search the web for current information',
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search query'
            ],
            'context_size' => [
                'type' => 'string',
                'enum' => ['low', 'medium', 'high'],
                'required' => false,
                'description' => 'Amount of context to return',
                'default' => 'medium'
            ]
        ]
    ];
    return $tools;
});
```

## Multi-Plugin Support

The tools system is designed for multi-plugin environments:

- **Plugin Isolation**: Tools are registered per-plugin
- **No Conflicts**: Tool names are namespaced by registering plugin
- **Shared Discovery**: All plugins can discover tools from other plugins
- **Independent Execution**: Each plugin handles its own tool execution

This enables a rich ecosystem where plugins can extend each other's AI capabilities through tool sharing.</content>
<parameter name="filePath">docs/filters/tools.md