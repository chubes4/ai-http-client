<?php
/**
 * AI HTTP Client - Model Selector Component
 * 
 * Single Responsibility: Render dynamic model selection dropdown
 * Core component that handles model selection with dynamic loading
 *
 * @package AIHttpClient\Components\Core
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Core_ModelSelector implements AI_HTTP_Component_Interface {
    
    /**
     * Render the model selector component
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []) {
        $config = array_merge(self::get_defaults(), $config);
        
        $provider = $current_values['provider'] ?? 'openai';
        $selected_model = $current_values['model'] ?? '';
        
        $html = '<div class="ai-field-group ai-model-selector">';
        $html .= '<label for="' . esc_attr($unique_id) . '_model">' . esc_html($config['label']) . ':</label>';
        $html .= '<div class="ai-select-with-button">';
        $html .= '<select id="' . esc_attr($unique_id) . '_model" ';
        $html .= 'name="ai_model" ';
        $html .= 'data-component-id="' . esc_attr($unique_id) . '" ';
        $html .= 'data-component-type="model_selector" ';
        $html .= 'data-provider="' . esc_attr($provider) . '" ';
        $html .= 'class="ai-model-field">';
        
        $html .= self::render_model_options($provider, $selected_model);
        
        $html .= '</select>';
        
        if ($config['show_refresh']) {
            $html .= '<button type="button" class="ai-refresh-models" ';
            $html .= 'onclick="aiHttpRefreshModels(\'' . esc_attr($unique_id) . '\', \'' . esc_attr($provider) . '\')" ';
            $html .= 'title="Refresh available models">';
            $html .= $config['refresh_icon'];
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        if ($config['show_help']) {
            $html .= '<p class="ai-field-help">' . esc_html($config['help_text']) . '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get component configuration schema
     *
     * @return array Configuration schema
     */
    public static function get_config_schema() {
        return [
            'label' => [
                'type' => 'string',
                'default' => 'Model',
                'description' => 'Label for the model selector'
            ],
            'show_refresh' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show refresh models button'
            ],
            'refresh_icon' => [
                'type' => 'string',
                'default' => '🔄',
                'description' => 'Icon for refresh button'
            ],
            'show_help' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show help text'
            ],
            'help_text' => [
                'type' => 'string',
                'default' => 'Select the AI model to use for requests.',
                'description' => 'Help text displayed below selector'
            ],
            'loading_text' => [
                'type' => 'string',
                'default' => 'Loading models...',
                'description' => 'Text shown while loading models'
            ],
            'error_text' => [
                'type' => 'string',
                'default' => 'Error loading models',
                'description' => 'Text shown when model loading fails'
            ]
        ];
    }
    
    /**
     * Get component default values
     *
     * @return array Default values
     */
    public static function get_defaults() {
        return [
            'label' => 'Model',
            'show_refresh' => true,
            'refresh_icon' => '🔄',
            'show_help' => true,
            'help_text' => 'Select the AI model to use for requests.',
            'loading_text' => 'Loading models...',
            'error_text' => 'Error loading models'
        ];
    }
    
    /**
     * Validate component configuration
     *
     * @param array $config Configuration to validate
     * @return bool True if valid
     */
    public static function validate_config($config) {
        $schema = self::get_config_schema();
        
        foreach ($config as $key => $value) {
            if (!isset($schema[$key])) {
                return false;
            }
            
            $expected_type = $schema[$key]['type'];
            
            if ($expected_type === 'string' && !is_string($value)) {
                return false;
            }
            
            if ($expected_type === 'boolean' && !is_bool($value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Render model options for provider
     *
     * @param string $provider Provider key
     * @param string $selected_model Currently selected model
     * @return string HTML options
     */
    private static function render_model_options($provider, $selected_model) {
        try {
            $client = new AI_HTTP_Client();
            $models = $client->get_models($provider);
            
            $html = '';
            
            if (empty($models)) {
                $html .= '<option value="">No models available</option>';
                return $html;
            }
            
            foreach ($models as $model_id => $model_name) {
                $selected = ($selected_model === $model_id) ? 'selected' : '';
                $html .= '<option value="' . esc_attr($model_id) . '" ' . $selected . '>';
                $html .= esc_html($model_name);
                $html .= '</option>';
            }
            
            return $html;
            
        } catch (Exception $e) {
            return '<option value="">Error loading models</option>';
        }
    }
    
    /**
     * Initialize AJAX handlers for model fetching
     */
    public static function init_ajax_handlers() {
        add_action('wp_ajax_ai_http_get_models', [__CLASS__, 'ajax_get_models']);
    }
    
    /**
     * AJAX handler for getting models
     */
    public static function ajax_get_models() {
        check_ajax_referer('ai_http_nonce', 'nonce');
        
        $provider = sanitize_text_field($_POST['provider']);
        
        try {
            // Get provider settings from WordPress options
            $options_manager = new AI_HTTP_Options_Manager();
            $provider_settings = $options_manager->get_provider_settings($provider);
            
            // Configure client with provider settings
            $config = array(
                'default_provider' => $provider,
                'providers' => array(
                    $provider => $provider_settings
                )
            );
            
            $client = new AI_HTTP_Client($config);
            $models = $client->get_models($provider);
            
            if (empty($models)) {
                wp_send_json_error('No models available for ' . $provider . '. Check API key configuration.');
                return;
            }
            
            wp_send_json_success($models);
            
        } catch (Exception $e) {
            error_log('AI HTTP Client: Model fetch AJAX failed: ' . $e->getMessage());
            wp_send_json_error('Failed to fetch models: ' . $e->getMessage());
        }
    }
}

// Initialize AJAX handlers
add_action('init', ['AI_HTTP_Core_ModelSelector', 'init_ajax_handlers']);