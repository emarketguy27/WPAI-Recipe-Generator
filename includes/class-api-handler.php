<?php
/**
 * Handles all API communication for Recipe Generator
 */
class WPAI_Recipe_Generator_API_Handler {
    private static $instance;
    private $providers;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->providers = WPAI_Recipe_Generator_Providers::get_instance();
    }
    
    /**
     * Shared method to generate and send prompt
     * @param array $args Prompt arguments
     * @param bool $is_test Whether this is a test request
     * @return array|WP_Error 
     */

    public function handle_prompt_request($args, $is_test = false) {
        // Generate the prompt (shared between frontend and admin)
        $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
        $prompt = $prompt_manager->generate_prompt($args);
        
        // Get API configuration
        $selected_provider = get_option('WPAI_recipe_generator_selected_provider', 'OpenAI');
        $api_endpoint = $this->providers->get_endpoint($selected_provider);
        $api_key = get_option('WPAI_recipe_generator_api_key', '');
        
        // Validate configuration
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key is not configured.', 'wpai-recipe-generator'));
        }
        
        if (!$api_endpoint) {
            return new WP_Error('invalid_provider', __('Invalid API provider selected.', 'wpai-recipe-generator'));
        }
        
        // Make the API request
        $response = $this->make_api_request($selected_provider, $api_endpoint, $api_key, $prompt);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $formatted = $this->format_response($selected_provider, $response);
        
        return [
            'prompt' => $prompt,
            'raw_response' => $response,
            'formatted_response' => $formatted,
            'usage' => isset($response['usage']) ? $response['usage'] : null
        ];
    }

    /**
     * Formats the API response for display
     */
    private function format_response($provider, $response) {
        
        try {
            if ($provider === 'Deepseek') {
                
                if (isset($response['choices'][0]['message']['content'])) {
                    $content = $response['choices'][0]['message']['content'];
                    
                    // Try to parse JSON
                    $decoded = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $this->format_json_recipe($decoded);
                    }
                    return $content;
                }
            }
            
            // ... other provider handling
            
        } catch (Exception $e) {
            return __('Error formatting response', 'wpai-recipe-generator');
        }
    }
    
    /**
     * Makes the actual API request
     */
    private function make_api_request($provider, $endpoint, $api_key, $prompt) {
        $request_args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 30,
            'body' => json_encode($this->get_request_body($provider, $prompt))
        ];
        
        // Provider-specific headers
        if ($provider === 'Anthropic') {
            $request_args['headers']['anthropic-version'] = '2023-06-01';
        }
        
        $response = wp_remote_post($endpoint, $request_args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', $this->get_api_error_message($provider, $response_body));
        }
        
        return $response_body;
    }
    
    /**
     * Gets the request body formatted for the specific provider
     */
    private function get_request_body($provider, $prompt) {
        $providers = WPAI_Recipe_Generator_Providers::get_instance();
        $model = $providers->get_model($provider);
        $response_format = $providers->get_response_format($provider);
        
        if (!$model) {
            return new WP_Error('invalid_model', __('Invalid provider model', 'wpai-recipe-generator'));
        }

        $base_params = [
            'model' => $model,
            'temperature' => (float) get_option('WPAI_recipe_generator_temperature', 0.7),
            'max_tokens' => (int) get_option('WPAI_recipe_generator_max_tokens', 1000)            
        ];

        // Add response format if specified
        if ($response_format) {
            $base_params['response_format'] = $response_format;
        }

        switch ($provider) {
            case 'OpenAI':
            case 'Deepseek':
                return array_merge($base_params, [
                    'messages' => [[
                        'role' => 'user',
                        'content' => $prompt
                    ]]
                ]);
                
            case 'Anthropic':
                return array_merge($base_params, [
                    'messages' => [[
                        'role' => 'user',
                        'content' => $prompt
                    ]],
                    'max_tokens' => 1000
                ]);
                
            case 'Google AI':
                return [
                    'contents' => [
                        'parts' => [[
                            'text' => $prompt
                        ]]
                    ]
                ];
                
            default:
                return ['prompt' => $prompt];
        }
    }

    private function format_json_recipe($recipe_data) {
        // Ensure we have an array
        if (!is_array($recipe_data)) {
            return __('Invalid recipe format', 'wpai-recipe-generator');
        }

        ob_start(); ?>
        <div class="recipe-json-output">
            <?php if (!empty($recipe_data['recipe_name'])) : ?>
                <h2><?php echo esc_html($recipe_data['recipe_name']); ?></h2>
            <?php endif; ?>
            
            <?php if (!empty($recipe_data['description'])) : ?>
                <p class="recipe-description"><?php echo esc_html($recipe_data['description']); ?></p>
            <?php endif; ?>
            
            <div class="recipe-meta">
                <?php if (!empty($recipe_data['servings'])) : ?>
                    <div class="meta-group">
                        <span class="dashicons dashicons-groups"></span>
                        <p><strong><?php esc_html_e('Servings:', 'wpai-recipe-generator'); ?></strong> <?php echo esc_html($recipe_data['servings']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recipe_data['preparation_time'])) : ?>
                    <div class="meta-group">
                        <span class="dashicons dashicons-clock"></span>
                        <p><strong><?php esc_html_e('Prep Time:', 'wpai-recipe-generator'); ?></strong> <?php echo esc_html($recipe_data['preparation_time']); ?></p>
                    </div>
                    
                <?php endif; ?>
                
                <?php if (!empty($recipe_data['cooking_time'])) : ?>
                    <div class="meta-group">
                        <span class="dashicons dashicons-food"></span>
                        <p><strong><?php esc_html_e('Cook Time:', 'wpai-recipe-generator'); ?></strong> <?php echo esc_html($recipe_data['cooking_time']); ?></p>
                    </div>
                    
                <?php endif; ?>
            </div>
            
            <?php if (!empty($recipe_data['ingredients'])) : ?>
                <h4><?php esc_html_e('Ingredients', 'wpai-recipe-generator'); ?></h4>
                <ul class="recipe-ingredients">
                    <?php foreach ((array)$recipe_data['ingredients'] as $ingredient) : ?>
                        <li><?php echo esc_html($ingredient); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (!empty($recipe_data['method'])) : ?>
                <h4><?php esc_html_e('Instructions', 'wpai-recipe-generator'); ?></h4>
                <ol class="recipe-instructions">
                    <?php foreach ((array)$recipe_data['method'] as $step) : ?>
                        <li><?php echo esc_html($step); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            
            <?php if (!empty($recipe_data['nutritional_information'])) : ?>
                <h4><?php esc_html_e('Nutritional Information', 'wpai-recipe-generator'); ?></h4>
                <ul class="recipe-nutrition">
                    <?php foreach ((array)$recipe_data['nutritional_information'] as $key => $value) : ?>
                        <li><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> <?php echo esc_html($value); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (!empty($recipe_data['wpai_dietary_tags'])) : ?>
                <div class="recipe-tags">
                    <?php foreach ((array)$recipe_data['wpai_dietary_tags'] as $tag) : ?>
                        <span class="dietary-tag"><span class="dashicons dashicons-tag"></span><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Gets a user-friendly error message from API response
     */
    private function get_api_error_message($provider, $response) {
        if (!is_array($response)) {
            return __('Unknown API error occurred', 'wpai-recipe-generator');
        }
        
        switch ($provider) {
            case 'OpenAI':
                return $response['error']['message'] ?? __('OpenAI API error', 'wpai-recipe-generator');
                
            case 'Anthropic':
                return $response['error']['message'] ?? __('Anthropic API error', 'wpai-recipe-generator');
                
            case 'Google AI':
                return $response['error']['message'] ?? __('Google AI API error', 'wpai-recipe-generator');
                
            case 'Deepseek':
                return $response['error']['message'] ?? __('Deepseek API error', 'wpai-recipe-generator');
                
            default:
                return __('API request failed', 'wpai-recipe-generator');
        }
    }
}