<?php
class WPAI_Recipe_Generator_Providers {
    private static $instance;
    private $option_name = 'WPAI_recipe_generator_custom_providers';
    private $providers;
    private $endpoints;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $models = [
        'OpenAI' => 'gpt-4',
        'Anthropic' => 'claude-2',
        'Google AI' => 'gemini-pro',
        'Deepseek' => 'deepseek-chat'
    ];

    public function get_model($provider_name) {
        return $this->models[$provider_name] ?? null;
    }

    private $response_formats = [
        'OpenAI' => null, // Default
        'Deepseek' => ['type' => 'json_object'],
        'Anthropic' => null,
        'Google AI' => null
    ];

    public function get_response_format($provider_name) {
        return $this->response_formats[$provider_name] ?? null;
    }
    
    private function __construct() {
        $this->providers = array(
            'OpenAI' => 'OpenAI (GPT-4)',
            'Anthropic' => 'Anthropic (Claude-2)',
            'Google AI' => 'Google AI (Gemini-pro)',
            'Deepseek' => 'DeepSeek (DeepSeek Chat)'
        );
        
        // Define endpoints for each provider
        $this->endpoints = array(
            'OpenAI' => 'https://api.openai.com/v1/chat/completions',
            'Anthropic' => 'https://api.anthropic.com/v1/messages',
            'Google AI' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent',
            'Deepseek' => 'https://api.deepseek.com/v1/chat/completions'
        );
        
        $this->load_custom_providers();
    }


    private function load_custom_providers() {
        $custom_providers = get_option($this->option_name, array());
        $this->providers = array_merge($this->providers, $custom_providers);
    }

    public function get_providers() {
        return $this->providers;
    }

    public function get_endpoint($provider_name) {
        // Check built-in endpoints first
        if (isset($this->endpoints[$provider_name])) {
            return $this->endpoints[$provider_name];
        }
        
        // Check custom endpoints
        $custom_endpoints = get_option('WPAI_recipe_generator_custom_endpoints', array());
        if (isset($custom_endpoints[$provider_name])) {
            return $custom_endpoints[$provider_name];
        }
        
        return false;
    }

    public function add_provider($provider_name, $endpoint = '') {
        
        if (empty(trim($provider_name))) {
            return new WP_Error('empty', __('Provider name cannot be empty', 'wpai-recipe-generator'));
        }

        $custom_providers = get_option($this->option_name, array());
        $custom_endpoints = get_option('WPAI_recipe_generator_custom_endpoints', array());
        
        $sanitized = sanitize_text_field($provider_name);
        $custom_providers[$sanitized] = $sanitized;
        
        if (!empty($endpoint)) {
            $custom_endpoints[$sanitized] = esc_url_raw($endpoint);
            update_option('WPAI_recipe_generator_custom_endpoints', $custom_endpoints);
        }
        
        if (!update_option($this->option_name, $custom_providers)) {
            return new WP_Error('db_error', __('Failed to save provider', 'wpai-recipe-generator'));
        }
        
        $this->providers[$sanitized] = $sanitized;
        return true;
    }
}