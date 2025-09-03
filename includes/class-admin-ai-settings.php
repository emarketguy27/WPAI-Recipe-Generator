<?php
class WPAI_Recipe_Generator_Admin_AI_Settings {
    private $providers;
    private $api_key_option;
    private $technical_params;

    public function __construct() {
        $this->api_key_option = 'WPAI_recipe_generator_api_key';
        $this->providers = WPAI_Recipe_Generator_Providers::get_instance();

        // Initialize technical parameters
        $this->technical_params = [
            'temperature' => [
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'default' => 0.7
            ],
            'max_tokens' => [
                'min' => 1,
                'max' => 4000,
                'step' => 1,
                'default' => 1000
            ],
            'top_p' => [
                'min' => 0,
                'max' => 1,
                'step' => 0.1,
                'default' => 1
            ],
            'frequency_penalty' => [
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'default' => 0
            ],
            'presence_penalty' => [
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'default' => 0
            ]
        ];
        
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_init', array($this, 'handle_submissions'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'register_technical_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets($hook) {
        // if ('toplevel_page_wpai-recipe-generator' !== $hook && 'wpai-recipe-generator_page_wpai-recipe-generator-ai-settings' !== $hook) {
        //     return;
        // }

        // Get the main plugin instance to access its enqueue method
        $plugin = WPAI_Recipe_Generator::get_instance();
        $plugin->enqueue_admin_assets($hook);
    }

    public function add_submenu_page() {
        add_submenu_page(
            'wpai-recipe-generator',
            __('AI Settings', 'wpai-recipe-generator'),
            __('AI Settings', 'wpai-recipe-generator'),
            'manage_options',
            'wpai-recipe-generator-ai-settings',
            array($this, 'render_page')
        );
    }

    public function register_settings() {
        register_setting(
            'WPAI_recipe_generator_ai_settings',
            'WPAI_recipe_generator_api_key',
            ['sanitize_callback' => [$this, 'sanitize_api_key']]
        );
    }

    public function register_technical_settings() {
        foreach ($this->technical_params as $param => $attributes) {
            register_setting(
                'WPAI_recipe_generator_ai_settings',
                "WPAI_recipe_generator_{$param}",
                [
                    'type' => 'number',
                    'sanitize_callback' => function($value) use ($param) {
                        return $this->sanitize_technical_param_single($value, $param);
                    },
                    'default' => $attributes['default']
                ]
            );
        }
    }

    private function sanitize_technical_param_single($value, $param) {
        if (!isset($this->technical_params[$param])) {
            return $value;
        }

        $value = (float) $value;
        $min = $this->technical_params[$param]['min'];
        $max = $this->technical_params[$param]['max'];

        return max($min, min($max, round($value, 2)));
    }

    public function sanitize_technical_param($value) {
        // Get the option name from the current filter
        $option_name = str_replace('sanitize_option_', '', current_filter());
        $param = str_replace('WPAI_recipe_generator_', '', $option_name);
        
        if (!isset($this->technical_params[$param])) {
            return $value;
        }

        $value = (float) $value;
        $min = $this->technical_params[$param]['min'];
        $max = $this->technical_params[$param]['max'];

        // Clamp the value between min and max
        return max($min, min($max, round($value, 2)));
    }
    
    // public function sanitize_api_key($input) {
    //     $input = trim($input);
    //     if (!empty($input)) {
    //         if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $input)) {
    //             add_settings_error(
    //                 $this->api_key_option,
    //                 'invalid_api_key',
    //                 __('Invalid API key format', 'wpai-recipe-generator'),
    //                 'error'
    //             );
    //             return '';
    //         }
    //     }
    //     return $input;
    // }

    public function sanitize_api_key($input) {
        $input = trim($input);
        
        // If input is empty, return the existing value (don't overwrite with empty)
        if (empty($input)) {
            return get_option('WPAI_recipe_generator_api_key', '');
        }
        
        // Validate the key format if it's not empty
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $input)) {
            add_settings_error(
                'WPAI_recipe_generator_api_key',
                'invalid_api_key',
                __('Invalid API key format', 'wpai-recipe-generator'),
                'error'
            );
            return get_option('WPAI_recipe_generator_api_key', ''); // Return existing key on error
        }
        
        return $input;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'wpai-recipe-generator'));
        }
        
        $selected_provider = get_option('WPAI_recipe_generator_selected_provider', '');
        $api_key = get_option($this->api_key_option, '');
        $providers = $this->providers->get_providers();
        ?>
        
        <div class="wrap wpai-recipe-generator-settings">
            <h1><?php esc_html_e('AI Settings', 'wpai-recipe-generator'); ?></h1>
            <p><strong>Set up your API, Technical Parameters, Prompt, User Options, and Testing...</strong></p>
            
            <?php settings_errors('WPAI_recipe_generator_messages'); ?>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields('WPAI_recipe_generator_ai_settings');
                ?>                
                <div class="settings-section">
                    <h2 class="title"><?php esc_html_e('API Configuration', 'wpai-recipe-generator'); ?></h2>
                    
                    <table class="form-table">
                        <tbody>
                            <!-- API Key Field -->
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($this->api_key_option); ?>">
                                        <?php esc_html_e('API Key', 'wpai-recipe-generator'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="password" 
                                        name="<?php echo esc_attr($this->api_key_option); ?>" 
                                        id="<?php echo esc_attr($this->api_key_option); ?>" 
                                        value="<?php echo esc_attr($this->get_display_api_key($api_key)); ?>" 
                                        class="regular-text" 
                                        autocomplete="off" 
                                        placeholder="<?php esc_attr_e('Enter your API key', 'wpai-recipe-generator'); ?>">
                                    <p class="description">
                                        <?php esc_html_e('Your secure API key for the selected provider', 'wpai-recipe-generator'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Provider Selection -->
                            <tr>
                                <th scope="row">
                                    <label for="api_provider">
                                        <?php esc_html_e('API Provider', 'wpai-recipe-generator'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select name="api_provider" id="api_provider" class="regular-text">
                                        <option value=""><?php esc_html_e('Select a provider', 'wpai-recipe-generator'); ?></option>
                                        <?php foreach ($providers as $key => $value) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_provider, $key); ?>>
                                                <?php echo esc_html($value); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            
                            <!-- Add New Provider -->
                            <tr>
                            <th scope="row">
                                <label for="new_provider">
                                    <?php esc_html_e('Add New Provider', 'wpai-recipe-generator'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                    name="new_provider" 
                                    id="new_provider" 
                                    class="regular-text" 
                                    placeholder="<?php esc_attr_e('Enter new provider name', 'wpai-recipe-generator'); ?>">
                                <p class="description">
                                    <?php esc_html_e('Add a new LLM API provider', 'wpai-recipe-generator'); ?>
                                </p>
                            </td>
                            </tr>

                            <!-- Add New Provider URL Endpoint-->
                            <tr>
                                <th scope="row">
                                    <label for="new_provider_endpoint">
                                        <?php esc_html_e('API Endpoint URL:', 'wpai-recipe-generator'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="url" 
                                        id="new_provider_endpoint" 
                                        name="new_provider_endpoint" 
                                        class="regular-text" 
                                        placeholder="<?php esc_attr_e('https://api.example.com/v1/endpoint', 'wpai-recipe-generator'); ?>"
                                        pattern="https?://.+">
                                    <p class="description">
                                        <?php esc_html_e('The full API endpoint URL for this provider', 'wpai-recipe-generator'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr class="connection-test">
                                <th scope="row">
                                    <h4><?php esc_html_e('Connection Test', 'wpai-recipe-generator'); ?></h4>
                                </th>
                                <td>
                                    <button type="button" 
                                            id="test-api-connection" 
                                            class="button button-secondary"
                                            data-nonce="<?php echo esc_attr(wp_create_nonce('wp_ai_recipe_generator_test_connection')); ?>">
                                        <?php esc_html_e('Test API Connection', 'wpai-recipe-generator'); ?>
                                    </button>
                                    <span id="test-connection-result" style="margin-left:10px;"></span>
                                    <p class="description">
                                        <?php esc_html_e('Verify your API key and endpoint are working.', 'wpai-recipe-generator'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="settings-section">
                    <h2 class="title"><?php esc_html_e('Technical Parameters', 'wpai-recipe-generator'); ?></h2>
                    <table class="form-table">
                        <tbody>
                            <?php foreach ($this->technical_params as $param => $attributes) : 
                                $current_value = get_option("WPAI_recipe_generator_{$param}", $attributes['default']);
                                ?>
                                <tr>
                                    <th scope="row">
                                        <label for="WPAI_recipe_generator_<?php echo esc_attr($param); ?>">
                                            <?php echo esc_html(ucwords(str_replace('_', ' ', $param))); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input type="range" 
                                            name="WPAI_recipe_generator_<?php echo esc_attr($param); ?>" 
                                            id="WPAI_recipe_generator_<?php echo esc_attr($param); ?>" 
                                            min="<?php echo esc_attr($attributes['min']); ?>" 
                                            max="<?php echo esc_attr($attributes['max']); ?>" 
                                            step="<?php echo esc_attr($attributes['step']); ?>" 
                                            value="<?php echo esc_attr($current_value); ?>" 
                                            class="technical-param-slider" 
                                            oninput="document.getElementById('WPAI_recipe_generator_<?php echo esc_attr($param); ?>_value').value=this.value">
                                        <output id="WPAI_recipe_generator_<?php echo esc_attr($param); ?>_value">
                                            <?php echo esc_html($current_value); ?>
                                        </output>
                                        <p class="description">
                                            <?php echo esc_html($this->get_param_description($param)); ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php 
                $this->render_prompt_editor();
                $this->render_dietary_options_editor();
                $this->render_test_interface();
                ?>
                
                <div class="submit-section">
                    <?php submit_button(__('Save All Settings', 'wpai-recipe-generator')); ?>
                    <p><strong>ANY</strong> changes made to API or Technical Parameters <strong>MUST</strong> be saved using this <strong>"Save All Settings"</strong> button.</p>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_prompt_editor() {
        $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
        $current_prompt = $prompt_manager->get_prompt_template();
        ?>
        <div class="settings-section">
            <h2 class="title"><?php esc_html_e('Prompt Editor', 'wpai-recipe-generator'); ?></h2>
            <p><strong>This prompt has been fully tested as providing the most efficient response whilst adhering to all set paramaters and user selections.</strong></p>
            <table class="form-table">
                <tbody class="prompt-section">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Current Prompt Template', 'wpai-recipe-generator'); ?></label>
                        </th>
                        <td>
                            <textarea name="WPAI_recipe_generator_prompt" rows="10" class="large-text code">
                                <?php echo esc_textarea($current_prompt); ?>
                            </textarea>
                            <p class="description">
                                <?php esc_html_e('Available placeholders: {cuisine}, {dietary}, {include_ingredients}, {exclude_ingredients}, {servings}, {skill_level}, {creativity_level}', 'wpai-recipe-generator'); ?>
                                <p><strong>Editing the prompt will directly affect the response - The provided {placeholders} MUST be included to align with frontend user selections.</strong></p>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td class="btn-grp">
                            <button type="button" id="reset-prompt" class="button button-secondary">
                                <?php esc_html_e('Reset to Default', 'wpai-recipe-generator'); ?>
                            </button>
                            <p>Reset back to original prompt (this will persist regardless of your prompt alterations)</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_dietary_options_editor() {
        $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
        $dietary_options = $prompt_manager->get_dietary_options();
        
        ?>
        <div class="settings-section">
            <h2 class="title"><?php esc_html_e('Dietary Options', 'wpai-recipe-generator'); ?></h2>
            <p>These options are rendered on the frontend form as "User Selections". <i>(Adding to, or removing these will update the user selections automatically.)</i></p>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Available Options', 'wpai-recipe-generator'); ?></label></th>
                        <td>
                            <ul id="current-dietary-options">
                                <?php foreach ($dietary_options as $key => $label) : ?>
                                    <li>
                                        <?php echo esc_html($label); ?>
                                        <a href="#" data-key="<?php echo esc_attr($key); ?>" class="remove-dietary-option">
                                            (<?php esc_html_e('Remove', 'wpai-recipe-generator'); ?>)
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="new_dietary_option"><?php esc_html_e('Add New Option', 'wpai-recipe-generator'); ?></label>
                        </th>
                        <td class="new-diet-options">
                            <input type="text" 
                                id="new_dietary_option" 
                                name="new_dietary_option" 
                                class="regular-text" 
                                placeholder="<?php esc_attr_e('e.g., Paleo', 'wpai-recipe-generator'); ?>"
                            >
                            <div class="btn-grp">
                                <button type="button" id="add-dietary-option" class="button button-primary">
                                <?php esc_html_e('Add', 'wpai-recipe-generator'); ?>
                                </button>
                                <button type="button" id="reset-dietary-options" class="button button-secondary">
                                    <?php esc_html_e('Reset to Default', 'wpai-recipe-generator'); ?>
                                </button>
                            </div>
                                
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php
    }

    private function render_test_interface() {
        $dietary_options = WPAI_Recipe_Generator_Prompt_Manager::get_instance()->get_dietary_options();
        ?>
        <div class="settings-section">
            <h2 class="title"><?php esc_html_e('Test Prompt & Response', 'wpai-recipe-generator'); ?></h2>
            <div id="prompt-test-interface">
                <table class="form-table">
                    <tbody class="form-test-prompt">
                        <tr>
                            <th scope="row">
                                <label for="test_servings"><?php esc_html_e('Servings', 'wpai-recipe-generator'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                    id="test_servings" 
                                    name="test_servings" 
                                    min="1" 
                                    max="20" 
                                    value="4">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="test_include"><?php esc_html_e('Must Include', 'wpai-recipe-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                    id="test_include" 
                                    name="test_include" 
                                    class="regular-text" 
                                    placeholder="<?php esc_attr_e('Comma separated list', 'wpai-recipe-generator'); ?>">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="test_exclude"><?php esc_html_e('Must Exclude', 'wpai-recipe-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                    id="test_exclude" 
                                    name="test_exclude" 
                                    class="regular-text" 
                                    placeholder="<?php esc_attr_e('Comma separated list', 'wpai-recipe-generator'); ?>">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Dietary Requirements', 'wpai-recipe-generator'); ?></label>
                            </th>
                            <td class="diet-options-wrapper">
                                <?php foreach ($dietary_options as $key => $label) : ?>
                                    <label>
                                        <input type="checkbox" 
                                            name="test_dietary[]" 
                                            value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div id="test-prompt-container">
                    <button type="button" 
                            id="test-prompt" 
                            class="button button-primary">
                        <?php esc_html_e('Test Prompt', 'wpai-recipe-generator'); ?>
                    </button>
                    <div class="loading-bar"></div>
                </div>
                
                <div id="test-results" style="margin-top:20px; display:none;">
                    <h3><?php esc_html_e('Generated Prompt:', 'wpai-recipe-generator'); ?></h3>
                    <div id="generated-prompt" 
                        class="code" 
                        style="background:#f5f5f5; padding:10px; border:1px solid #ddd;"></div>
                    
                    <h3 style="margin-top:15px;"><?php esc_html_e('API Response:', 'wpai-recipe-generator'); ?></h3>
                    <div id="api-response" 
                        style="background:#f5f5f5; padding:10px; border:1px solid #ddd;"></div>
                </div>
            </div>
        </div>
        
        <?php
    }

    private function get_display_api_key($key) {
        return empty($key) ? '' : '••••••••••••••••';
    }

    public function handle_submissions() {
        
        if (!isset($_POST['option_page']) || sanitize_text_field(wp_unslash($_POST['option_page'])) !== 'WPAI_recipe_generator_ai_settings') {
            return;
        }

        if (!isset($_POST['_wpnonce'])) {
            wp_die(esc_html__('Security check failed', 'wpai-recipe-generator'));
        }
        
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'WPAI_recipe_generator_ai_settings-options')) {
            wp_die(esc_html__('Security check failed', 'wpai-recipe-generator'));
        }

        // Handle provider selection
        if (isset($_POST['api_provider'])) {
            $provider = isset($_POST['api_provider']) ? sanitize_text_field(wp_unslash($_POST['api_provider'])) : '';
            update_option('WPAI_recipe_generator_selected_provider', $provider);
        }

        // Handle technical parameters
        foreach ($this->technical_params as $param => $attributes) {
            $option_name = "WPAI_recipe_generator_{$param}";
            if (isset($_POST[$option_name])) {
                // First sanitize as text input, then unslash, then custom sanitize
                $raw_value = sanitize_text_field(wp_unslash($_POST[$option_name]));
                $unsanitized_value = wp_unslash($raw_value);
                $value = $this->sanitize_technical_param_single($unsanitized_value, $param);
                update_option($option_name, $value);
            }
        }

        // Handle new provider addition
        $new_provider = isset($_POST['new_provider']) ? sanitize_text_field(wp_unslash($_POST['new_provider'])) : '';
        if (!empty($new_provider)) {
            $endpoint = isset($_POST['new_provider_endpoint']) ? esc_url_raw(wp_unslash($_POST['new_provider_endpoint'])) : '';
            
            if (empty($endpoint)) {
                add_settings_error(
                    'WPAI_recipe_generator_messages',
                    'WPAI_recipe_generator_message',
                    __('API endpoint URL is required when adding a new provider', 'wpai-recipe-generator'),
                    'error'
                );
                return;
            }

            $result = $this->providers->add_provider($new_provider, $endpoint);
            
            if (is_wp_error($result)) {
                add_settings_error(
                    'WPAI_recipe_generator_messages', 
                    'WPAI_recipe_generator_message', 
                    esc_html($result->get_error_message()), 
                    'error'
                );
            } else {
                add_settings_error(
                    'WPAI_recipe_generator_messages',
                    'WPAI_recipe_generator_message',
                    __('Provider added successfully!', 'wpai-recipe-generator'),
                    'success'
                );
            }
        }

        // Handle prompt update
        if (isset($_POST['WPAI_recipe_generator_prompt'])) {
            $new_prompt = wp_kses_post(wp_unslash($_POST['WPAI_recipe_generator_prompt']));
            $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
            $prompt_manager->update_prompt_template($new_prompt);
            
            add_settings_error(
                'WPAI_recipe_generator_messages',
                'WPAI_recipe_generator_message',
                __('Settings updated successfully!', 'wpai-recipe-generator'),
                'success'
            );
        }
    }

    private function get_param_description($param) {
        $descriptions = [
            'temperature' => __('0 = predictable, 2 = creative', 'wpai-recipe-generator'),
            'max_tokens' => __('Maximum token usage', 'wpai-recipe-generator'),
            'top_p' => __('Controls diversity via nucleus sampling', 'wpai-recipe-generator'),
            'frequency_penalty' => __('Penalizes frequently used tokens', 'wpai-recipe-generator'),
            'presence_penalty' => __('Penalizes new tokens appearing in text', 'wpai-recipe-generator')
        ];
        
        return $descriptions[$param] ?? '';
    }
}