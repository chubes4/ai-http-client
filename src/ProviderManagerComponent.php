<?php
/**
 * AI HTTP Client - Provider Manager Component
 * 
 * Single Responsibility: Render complete AI provider configuration interface
 * Self-contained component that handles provider selection, API keys, models, and instructions
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_ProviderManager_Component {

    private static $instance_count = 0;
    private $options_manager;
    private $client;

    public function __construct() {
        $this->options_manager = new AI_HTTP_Options_Manager();
        $this->client = new AI_HTTP_Client();
        
        // Register AJAX handlers on first instantiation
        if (self::$instance_count === 0) {
            $this->register_ajax_handlers();
        }
        self::$instance_count++;
    }

    /**
     * Static render method for easy usage
     *
     * @param array $args Component configuration
     * @return string Rendered HTML
     */
    public static function render($args = array()) {
        $component = new self();
        return $component->render_component($args);
    }

    /**
     * Render the complete provider manager interface
     *
     * @param array $args Component configuration
     * @return string Rendered HTML
     */
    public function render_component($args = array()) {
        $defaults = array(
            'title' => 'AI Provider Configuration',
            'show_instructions' => true,
            'show_test_connection' => true,
            'allowed_providers' => array(), // Empty = all providers
            'default_provider' => 'openai',
            'custom_fields' => array(),
            'instructions_label' => 'System Instructions',
            'wrapper_class' => 'ai-http-provider-manager'
        );

        $args = array_merge($defaults, $args);
        $unique_id = 'ai-provider-manager-' . uniqid();
        
        $current_settings = $this->options_manager->get_all_providers();
        $available_providers = $this->get_available_providers($args['allowed_providers']);
        
        $selected_provider = isset($current_settings['selected_provider']) 
            ? $current_settings['selected_provider'] 
            : $args['default_provider'];

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['wrapper_class']); ?>" id="<?php echo esc_attr($unique_id); ?>">
            
            <?php if ($args['title']): ?>
                <h3><?php echo esc_html($args['title']); ?></h3>
            <?php endif; ?>

            <div class="ai-provider-form">
                
                <!-- Provider Selection -->
                <div class="ai-field-group">
                    <label for="<?php echo esc_attr($unique_id); ?>_provider">AI Provider:</label>
                    <select id="<?php echo esc_attr($unique_id); ?>_provider" 
                            name="ai_provider" 
                            data-component-id="<?php echo esc_attr($unique_id); ?>">
                        <?php foreach ($available_providers as $provider_key => $provider_name): ?>
                            <option value="<?php echo esc_attr($provider_key); ?>" 
                                    <?php selected($selected_provider, $provider_key); ?>>
                                <?php echo esc_html($provider_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ai-provider-status" id="<?php echo esc_attr($unique_id); ?>_status">
                        <?php echo $this->get_provider_status($selected_provider); ?>
                    </span>
                </div>

                <!-- API Key Input -->
                <div class="ai-field-group">
                    <label for="<?php echo esc_attr($unique_id); ?>_api_key">API Key:</label>
                    <input type="password" 
                           id="<?php echo esc_attr($unique_id); ?>_api_key"
                           name="ai_api_key" 
                           value="<?php echo esc_attr($this->get_provider_setting($selected_provider, 'api_key')); ?>"
                           placeholder="Enter your <?php echo esc_attr($selected_provider); ?> API key"
                           data-component-id="<?php echo esc_attr($unique_id); ?>"
                           data-provider="<?php echo esc_attr($selected_provider); ?>" />
                    <button type="button" class="ai-toggle-key-visibility" 
                            onclick="aiHttpToggleKeyVisibility('<?php echo esc_attr($unique_id); ?>_api_key')">👁</button>
                </div>

                <!-- Model Selection -->
                <div class="ai-field-group">
                    <label for="<?php echo esc_attr($unique_id); ?>_model">Model:</label>
                    <select id="<?php echo esc_attr($unique_id); ?>_model" 
                            name="ai_model"
                            data-component-id="<?php echo esc_attr($unique_id); ?>">
                        <?php echo $this->render_model_options($selected_provider); ?>
                    </select>
                    <button type="button" class="ai-refresh-models" 
                            onclick="aiHttpRefreshModels('<?php echo esc_attr($unique_id); ?>', '<?php echo esc_attr($selected_provider); ?>')">🔄</button>
                </div>

                <?php if ($args['show_instructions']): ?>
                    <!-- Instructions/System Prompt -->
                    <div class="ai-field-group">
                        <label for="<?php echo esc_attr($unique_id); ?>_instructions"><?php echo esc_html($args['instructions_label']); ?>:</label>
                        <textarea id="<?php echo esc_attr($unique_id); ?>_instructions" 
                                  name="ai_instructions"
                                  rows="4"
                                  data-component-id="<?php echo esc_attr($unique_id); ?>"
                                  placeholder="Enter system instructions for the AI..."><?php 
                                  echo esc_textarea($this->get_provider_setting($selected_provider, 'instructions')); 
                                  ?></textarea>
                    </div>
                <?php endif; ?>

                <?php if (!empty($args['custom_fields'])): ?>
                    <!-- Custom Fields -->
                    <?php foreach ($args['custom_fields'] as $field_key => $field_label): ?>
                        <div class="ai-field-group">
                            <label for="<?php echo esc_attr($unique_id . '_' . $field_key); ?>"><?php echo esc_html($field_label); ?>:</label>
                            <input type="text" 
                                   id="<?php echo esc_attr($unique_id . '_' . $field_key); ?>"
                                   name="ai_custom_<?php echo esc_attr($field_key); ?>"
                                   value="<?php echo esc_attr($this->get_provider_setting($selected_provider, 'custom_' . $field_key)); ?>"
                                   data-component-id="<?php echo esc_attr($unique_id); ?>"
                                   data-custom-field="<?php echo esc_attr($field_key); ?>" />
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($args['show_test_connection']): ?>
                    <!-- Test Connection -->
                    <div class="ai-field-group">
                        <button type="button" class="ai-test-connection" 
                                onclick="aiHttpTestConnection('<?php echo esc_attr($unique_id); ?>')">
                            Test Connection
                        </button>
                        <span class="ai-test-result" id="<?php echo esc_attr($unique_id); ?>_test_result"></span>
                    </div>
                <?php endif; ?>

                <!-- Auto-save Status -->
                <div class="ai-save-status" id="<?php echo esc_attr($unique_id); ?>_save_status" style="display: none;">
                    Settings saved ✓
                </div>

            </div>
        </div>

        <script>
        <?php echo $this->render_javascript($unique_id); ?>
        </script>

        <style>
        <?php echo $this->render_basic_styles(); ?>
        </style>
        <?php

        return ob_get_clean();
    }

    /**
     * Get available providers
     */
    private function get_available_providers($allowed_providers) {
        $all_providers = array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            'grok' => 'Grok',
            'openrouter' => 'OpenRouter'
        );

        if (empty($allowed_providers)) {
            return $all_providers;
        }

        $filtered = array();
        foreach ($allowed_providers as $provider) {
            if (isset($all_providers[$provider])) {
                $filtered[$provider] = $all_providers[$provider];
            }
        }

        return $filtered;
    }

    /**
     * Get provider setting value
     */
    private function get_provider_setting($provider, $key, $default = '') {
        $settings = $this->options_manager->get_provider_settings($provider);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Get provider status
     */
    private function get_provider_status($provider) {
        $api_key = $this->get_provider_setting($provider, 'api_key');
        
        if (empty($api_key)) {
            return '<span style="color: #d63638;">⚠ Not configured</span>';
        }

        return '<span style="color: #00a32a;">✓ Configured</span>';
    }

    /**
     * Render model options for provider
     */
    private function render_model_options($provider) {
        $current_model = $this->get_provider_setting($provider, 'model');
        
        try {
            $models = $this->client->get_models($provider);
            $html = '';
            
            foreach ($models as $model_id => $model_name) {
                $selected = ($current_model === $model_id) ? 'selected' : '';
                $html .= sprintf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($model_id),
                    $selected,
                    esc_html($model_name)
                );
            }
            
            return $html;
            
        } catch (Exception $e) {
            return '<option value="">Error loading models</option>';
        }
    }

    /**
     * Render minimal JavaScript for functionality
     */
    private function render_javascript($unique_id) {
        return "
        // Auto-save on input change
        document.querySelectorAll('#$unique_id input, #$unique_id select, #$unique_id textarea').forEach(function(input) {
            input.addEventListener('change', function() {
                aiHttpAutoSave('$unique_id');
            });
        });

        // Provider change handler
        document.getElementById('{$unique_id}_provider').addEventListener('change', function() {
            aiHttpProviderChanged('$unique_id', this.value);
        });
        ";
    }

    /**
     * Render basic styles (minimal, unstyled approach)
     */
    private function render_basic_styles() {
        return "
        .ai-provider-form .ai-field-group {
            margin-bottom: 15px;
        }
        .ai-provider-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .ai-provider-form input, .ai-provider-form select, .ai-provider-form textarea {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .ai-provider-status {
            margin-left: 10px;
            font-size: 14px;
        }
        .ai-save-status {
            color: #00a32a;
            font-size: 14px;
            margin-top: 10px;
        }
        ";
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_ai_http_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_ai_http_get_models', array($this, 'ajax_get_models'));
        add_action('wp_ajax_ai_http_test_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('ai_http_nonce', 'nonce');
        
        $provider = sanitize_text_field($_POST['provider']);
        $settings = array(
            'api_key' => sanitize_text_field($_POST['api_key']),
            'model' => sanitize_text_field($_POST['model']),
            'instructions' => sanitize_textarea_field($_POST['instructions'])
        );

        // Handle custom fields
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'custom_') === 0) {
                $settings[$key] = sanitize_text_field($value);
            }
        }

        $this->options_manager->save_provider_settings($provider, $settings);
        $this->options_manager->set_selected_provider($provider);

        wp_send_json_success('Settings saved');
    }

    /**
     * AJAX: Get models for provider
     */
    public function ajax_get_models() {
        check_ajax_referer('ai_http_nonce', 'nonce');
        
        $provider = sanitize_text_field($_POST['provider']);
        $models = $this->client->get_models($provider);
        
        wp_send_json_success($models);
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('ai_http_nonce', 'nonce');
        
        $provider = sanitize_text_field($_POST['provider']);
        $result = $this->client->test_connection($provider);
        
        wp_send_json($result);
    }
}

// Global JavaScript functions for component functionality
if (!function_exists('ai_http_render_global_js')):
function ai_http_render_global_js() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    ?>
    <script>
    function aiHttpAutoSave(componentId) {
        // Auto-save functionality
        const component = document.getElementById(componentId);
        const formData = new FormData();
        
        formData.append('action', 'ai_http_save_settings');
        formData.append('nonce', '<?php echo wp_create_nonce('ai_http_nonce'); ?>');
        
        component.querySelectorAll('input, select, textarea').forEach(function(input) {
            formData.append(input.name, input.value);
        });

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        }).then(response => response.json()).then(data => {
            if (data.success) {
                const status = document.getElementById(componentId + '_save_status');
                status.style.display = 'block';
                setTimeout(() => status.style.display = 'none', 2000);
            }
        });
    }

    function aiHttpProviderChanged(componentId, provider) {
        // Handle provider change
        location.reload(); // Simple approach - reload to update UI
    }

    function aiHttpToggleKeyVisibility(inputId) {
        const input = document.getElementById(inputId);
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    function aiHttpRefreshModels(componentId, provider) {
        // Refresh models for provider
        const modelSelect = document.getElementById(componentId + '_model');
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=ai_http_get_models&provider=' + provider + '&nonce=<?php echo wp_create_nonce('ai_http_nonce'); ?>'
        }).then(response => response.json()).then(data => {
            if (data.success) {
                modelSelect.innerHTML = '';
                Object.entries(data.data).forEach(([key, value]) => {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = value;
                    modelSelect.appendChild(option);
                });
            }
        });
    }

    function aiHttpTestConnection(componentId) {
        const provider = document.getElementById(componentId + '_provider').value;
        const resultSpan = document.getElementById(componentId + '_test_result');
        
        resultSpan.textContent = 'Testing...';
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=ai_http_test_connection&provider=' + provider + '&nonce=<?php echo wp_create_nonce('ai_http_nonce'); ?>'
        }).then(response => response.json()).then(data => {
            resultSpan.textContent = data.success ? '✓ Connected' : '✗ ' + data.message;
            resultSpan.style.color = data.success ? '#00a32a' : '#d63638';
        });
    }
    </script>
    <?php
}
add_action('admin_footer', 'ai_http_render_global_js');
endif;