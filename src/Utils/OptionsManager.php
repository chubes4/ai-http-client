<?php
/**
 * AI HTTP Client - Options Manager
 * 
 * Single Responsibility: Handle WordPress options storage for AI provider settings
 * Manages the nested array structure in wp_options table
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Options_Manager {

    /**
     * Option name in wp_options table
     */
    const OPTION_NAME = 'ai_http_client_providers';

    /**
     * Option name for selected provider
     */
    const SELECTED_PROVIDER_OPTION = 'ai_http_client_selected_provider';

    /**
     * Get all provider settings
     *
     * @return array All provider settings
     */
    public function get_all_providers() {
        $settings = get_option(self::OPTION_NAME, array());
        $selected_provider = get_option(self::SELECTED_PROVIDER_OPTION, 'openai');
        
        // Add selected provider to the settings array
        $settings['selected_provider'] = $selected_provider;
        
        return $settings;
    }

    /**
     * Get settings for a specific provider
     *
     * @param string $provider_name Provider name
     * @return array Provider settings
     */
    public function get_provider_settings($provider_name) {
        $all_settings = get_option(self::OPTION_NAME, array());
        return isset($all_settings[$provider_name]) ? $all_settings[$provider_name] : array();
    }

    /**
     * Save settings for a specific provider
     *
     * @param string $provider_name Provider name
     * @param array $settings Provider settings
     * @return bool True on success
     */
    public function save_provider_settings($provider_name, $settings) {
        $all_settings = get_option(self::OPTION_NAME, array());
        
        // Sanitize and merge settings
        $all_settings[$provider_name] = array_merge(
            isset($all_settings[$provider_name]) ? $all_settings[$provider_name] : array(),
            $this->sanitize_provider_settings($settings)
        );
        
        return update_option(self::OPTION_NAME, $all_settings);
    }

    /**
     * Get specific setting for a provider
     *
     * @param string $provider_name Provider name
     * @param string $setting_key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_provider_setting($provider_name, $setting_key, $default = null) {
        $provider_settings = $this->get_provider_settings($provider_name);
        return isset($provider_settings[$setting_key]) ? $provider_settings[$setting_key] : $default;
    }

    /**
     * Set specific setting for a provider
     *
     * @param string $provider_name Provider name
     * @param string $setting_key Setting key
     * @param mixed $value Setting value
     * @return bool True on success
     */
    public function set_provider_setting($provider_name, $setting_key, $value) {
        $provider_settings = $this->get_provider_settings($provider_name);
        $provider_settings[$setting_key] = $value;
        
        return $this->save_provider_settings($provider_name, $provider_settings);
    }

    /**
     * Check if provider is configured (has API key)
     *
     * @param string $provider_name Provider name
     * @return bool True if configured
     */
    public function is_provider_configured($provider_name) {
        $api_key = $this->get_provider_setting($provider_name, 'api_key');
        return !empty($api_key);
    }

    /**
     * Get selected provider
     *
     * @return string Selected provider name
     */
    public function get_selected_provider() {
        return get_option(self::SELECTED_PROVIDER_OPTION, 'openai');
    }

    /**
     * Set selected provider
     *
     * @param string $provider_name Provider name
     * @return bool True on success
     */
    public function set_selected_provider($provider_name) {
        return update_option(self::SELECTED_PROVIDER_OPTION, sanitize_text_field($provider_name));
    }

    /**
     * Delete provider settings
     *
     * @param string $provider_name Provider name
     * @return bool True on success
     */
    public function delete_provider_settings($provider_name) {
        $all_settings = get_option(self::OPTION_NAME, array());
        
        if (isset($all_settings[$provider_name])) {
            unset($all_settings[$provider_name]);
            return update_option(self::OPTION_NAME, $all_settings);
        }
        
        return true;
    }

    /**
     * Reset all settings
     *
     * @return bool True on success
     */
    public function reset_all_settings() {
        $deleted_main = delete_option(self::OPTION_NAME);
        $deleted_selected = delete_option(self::SELECTED_PROVIDER_OPTION);
        
        return $deleted_main && $deleted_selected;
    }

    /**
     * Get API key for provider (with encryption support)
     *
     * @param string $provider_name Provider name
     * @return string API key (decrypted if needed)
     */
    public function get_api_key($provider_name) {
        $api_key = $this->get_provider_setting($provider_name, 'api_key');
        
        // If encryption is available, decrypt the key
        if (!empty($api_key) && $this->is_encryption_available()) {
            return $this->decrypt_api_key($api_key);
        }
        
        return $api_key;
    }

    /**
     * Set API key for provider (with encryption support)
     *
     * @param string $provider_name Provider name
     * @param string $api_key API key
     * @return bool True on success
     */
    public function set_api_key($provider_name, $api_key) {
        // If encryption is available, encrypt the key
        if ($this->is_encryption_available()) {
            $api_key = $this->encrypt_api_key($api_key);
        }
        
        return $this->set_provider_setting($provider_name, 'api_key', $api_key);
    }

    /**
     * Export all settings (for backup/migration)
     *
     * @return array All settings
     */
    public function export_settings() {
        return array(
            'providers' => get_option(self::OPTION_NAME, array()),
            'selected_provider' => get_option(self::SELECTED_PROVIDER_OPTION, 'openai'),
            'export_date' => current_time('mysql'),
            'version' => AI_HTTP_CLIENT_VERSION
        );
    }

    /**
     * Import settings (for backup/migration)
     *
     * @param array $settings Settings to import
     * @return bool True on success
     */
    public function import_settings($settings) {
        if (!is_array($settings) || !isset($settings['providers'])) {
            return false;
        }
        
        $success_main = update_option(self::OPTION_NAME, $settings['providers']);
        $success_selected = true;
        
        if (isset($settings['selected_provider'])) {
            $success_selected = update_option(self::SELECTED_PROVIDER_OPTION, $settings['selected_provider']);
        }
        
        return $success_main && $success_selected;
    }

    /**
     * Get configuration array for AI_HTTP_Client
     *
     * @return array Configuration ready for AI_HTTP_Client
     */
    public function get_client_config() {
        $selected_provider = $this->get_selected_provider();
        $provider_settings = $this->get_provider_settings($selected_provider);
        
        return array(
            'default_provider' => $selected_provider,
            'providers' => array(
                $selected_provider => $provider_settings
            )
        );
    }

    /**
     * Sanitize provider settings
     *
     * @param array $settings Raw settings
     * @return array Sanitized settings
     */
    private function sanitize_provider_settings($settings) {
        $sanitized = array();
        
        // Sanitize common fields
        if (isset($settings['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($settings['api_key']);
        }
        
        if (isset($settings['model'])) {
            $sanitized['model'] = sanitize_text_field($settings['model']);
        }
        
        if (isset($settings['instructions'])) {
            $sanitized['instructions'] = sanitize_textarea_field($settings['instructions']);
        }
        
        if (isset($settings['base_url'])) {
            $sanitized['base_url'] = esc_url_raw($settings['base_url']);
        }
        
        if (isset($settings['organization'])) {
            $sanitized['organization'] = sanitize_text_field($settings['organization']);
        }
        
        // Sanitize custom fields
        foreach ($settings as $key => $value) {
            if (strpos($key, 'custom_') === 0) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        // Sanitize numeric fields
        if (isset($settings['temperature'])) {
            $sanitized['temperature'] = floatval($settings['temperature']);
        }
        
        if (isset($settings['max_tokens'])) {
            $sanitized['max_tokens'] = intval($settings['max_tokens']);
        }
        
        return $sanitized;
    }

    /**
     * Check if encryption is available
     *
     * @return bool True if encryption is available
     */
    private function is_encryption_available() {
        // Check if WordPress has encryption keys defined
        return defined('AUTH_KEY') && defined('SECURE_AUTH_KEY');
    }

    /**
     * Encrypt API key
     *
     * @param string $api_key Plain API key
     * @return string Encrypted API key
     */
    private function encrypt_api_key($api_key) {
        if (!$this->is_encryption_available()) {
            return $api_key;
        }
        
        // Simple encryption using WordPress constants
        $key = AUTH_KEY . SECURE_AUTH_KEY;
        return base64_encode($api_key ^ str_repeat($key, ceil(strlen($api_key) / strlen($key))));
    }

    /**
     * Decrypt API key
     *
     * @param string $encrypted_key Encrypted API key
     * @return string Plain API key
     */
    private function decrypt_api_key($encrypted_key) {
        if (!$this->is_encryption_available()) {
            return $encrypted_key;
        }
        
        // Simple decryption using WordPress constants
        $key = AUTH_KEY . SECURE_AUTH_KEY;
        $decoded = base64_decode($encrypted_key);
        return $decoded ^ str_repeat($key, ceil(strlen($decoded) / strlen($key)));
    }
    
    /**
     * Initialize AJAX handlers for settings management
     */
    public static function init_ajax_handlers() {
        add_action('wp_ajax_ai_http_save_settings', [__CLASS__, 'ajax_save_settings']);
    }
    
    /**
     * AJAX handler for saving settings
     */
    public static function ajax_save_settings() {
        check_ajax_referer('ai_http_nonce', 'nonce');
        
        try {
            $provider = sanitize_text_field($_POST['ai_provider']);
            $settings = array(
                'api_key' => sanitize_text_field($_POST['ai_api_key']),
                'model' => sanitize_text_field($_POST['ai_model']),
                'temperature' => isset($_POST['ai_temperature']) ? floatval($_POST['ai_temperature']) : null,
                'system_prompt' => isset($_POST['ai_system_prompt']) ? sanitize_textarea_field($_POST['ai_system_prompt']) : '',
                'instructions' => isset($_POST['instructions']) ? sanitize_textarea_field($_POST['instructions']) : ''
            );

            // Handle custom fields
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'custom_') === 0) {
                    $settings[$key] = sanitize_text_field($value);
                }
            }

            $options_manager = new self();
            $options_manager->save_provider_settings($provider, $settings);
            $options_manager->set_selected_provider($provider);

            wp_send_json_success('Settings saved');
            
        } catch (Exception $e) {
            error_log('AI HTTP Client: Save settings AJAX failed: ' . $e->getMessage());
            wp_send_json_error('Save failed: ' . $e->getMessage());
        }
    }
}

// Initialize AJAX handlers
add_action('init', ['AI_HTTP_Options_Manager', 'init_ajax_handlers']);