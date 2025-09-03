<?php
// AJAX Handlers
add_action('wp_ajax_wp_ai_recipe_generator_reset_prompt', function() {
    check_ajax_referer('wp_ai_recipe_generator_ajax_nonce', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Permission denied.', 'wpai-recipe-generator'));
    }
    
    $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
    $prompt_manager->reset_prompt_to_default();
    
    wp_send_json_success([
        'default_prompt' => $prompt_manager->get_default_prompt()
    ]);
});

add_action('wp_ajax_wp_ai_recipe_generator_add_dietary_option', function() {
    
    check_ajax_referer('wp_ai_recipe_generator_ajax_nonce', '_wpnonce');
    
    if (!current_user_can('manage_options') || empty($_POST['option'])) {
        wp_send_json_error(esc_html__('Invalid request.', 'wpai-recipe-generator'));
    }
    
    $option = sanitize_text_field(wp_unslash($_POST['option']));
    $key = sanitize_key($option);
    
    $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
    $prompt_manager->add_dietary_option($key, $option);
    
    wp_send_json_success([
        'key' => $key,
        'label' => $option
    ]);
});

add_action('wp_ajax_wp_ai_recipe_generator_remove_dietary_option', function() {
    check_ajax_referer('wp_ai_recipe_generator_ajax_nonce', '_wpnonce');
    
    if (!current_user_can('manage_options') || empty($_POST['key'])) {
        wp_send_json_error(esc_html__('Invalid request.', 'wpai-recipe-generator'));
    }
    
    $key = isset($_POST['key']) ? sanitize_key(wp_unslash($_POST['key'])) : '';
    if (empty($key)) {
        wp_send_json_error(esc_html__('Invalid key.', 'wpai-recipe-generator'));
    }

    $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
    
    // Don't allow removing default options
    // $default_options = $prompt_manager->get_default_dietary_options();
    // if (array_key_exists($key, $default_options)) {
    //     wp_send_json_error(esc_html__('Cannot remove default options.', 'wpai-recipe-generator'));
    // }
    
    $prompt_manager->remove_dietary_option($key);
    wp_send_json_success();
});

add_action('wp_ajax_wp_ai_recipe_generator_reset_dietary_options', function() {
    check_ajax_referer('wp_ai_recipe_generator_ajax_nonce', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Permission denied.', 'wpai-recipe-generator'));
    }
    
    $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
    $prompt_manager->reset_dietary_options_to_default();
    wp_send_json_success();
});

add_action('wp_ajax_wp_ai_recipe_generator_test_connection', function() {
    check_ajax_referer('wp_ai_recipe_generator_test_connection', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Permission denied.', 'wpai-recipe-generator'));
    }

    $providers = WPAI_Recipe_Generator_Providers::get_instance();
    $selected_provider = get_option('WPAI_recipe_generator_selected_provider', '');
    $api_endpoint = $providers->get_endpoint($selected_provider);
    $api_key = get_option('WPAI_recipe_generator_api_key', '');

    if (empty($api_key)) {
        wp_send_json_error(esc_html__('API key is not configured.', 'wpai-recipe-generator'));
    }

    if (empty($api_endpoint)) {
        wp_send_json_error(esc_html__('No API endpoint configured for this provider.', 'wpai-recipe-generator'));
    }

    // Provider-specific test requests
    $test_request = [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'timeout' => 10
    ];

    // Adjust based on provider requirements
    switch ($selected_provider) {
        case 'OpenAI':
            $test_request['method'] = 'POST';
            $test_request['body'] = json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1
            ]);
            break;
        case 'Deepseek':
            $test_request['method'] = 'POST';
            $test_request['body'] = json_encode([
                'model' => 'deepseek-chat',
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1
            ]);
            break;
            
        case 'Anthropic':
            $test_request['method'] = 'POST';
            $test_request['body'] = json_encode([
                'model' => 'claude-instant-1',
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1
            ]);
            break;
        
            case 'Google AI':
            $test_request['method'] = 'POST';
            $test_request['body'] = json_encode([
                'model' => 'gemini-pro',
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1
            ]);
            break;
            
        default:
            // Fallback to GET request
            $test_request['method'] = 'GET';
    }

    $response = wp_remote_request($api_endpoint, $test_request);

    if (is_wp_error($response)) {
        wp_send_json_error(esc_html($response->get_error_message()));
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code === 200) {
        wp_send_json_success(__('API connection successful!', 'wpai-recipe-generator'));
    } else {
        $error_message = sprintf(
            /* translators: 1: HTTP status code, 2: API response body */
            __('API returned status %1$d. Response: %2$s', 'wpai-recipe-generator'),
            absint($response_code),
            esc_html($response_body)
        );
        wp_send_json_error(esc_html($response->get_error_message()));
    }
});

add_action('wp_ajax_wp_ai_recipe_generator_test_prompt', function() {
    check_ajax_referer('wp_ai_recipe_generator_ajax_nonce', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Permission denied.', 'wpai-recipe-generator'));
    }
    
    $args = [
        'servings' => !empty($_POST['servings']) ? absint($_POST['servings']) : 4,
        'include_ingredients' => !empty($_POST['include']) ? sanitize_text_field(wp_unslash($_POST['include'])) : '',
        'exclude_ingredients' => !empty($_POST['exclude']) ? sanitize_text_field(wp_unslash($_POST['exclude'])) : '',
        'dietary' => !empty($_POST['dietary']) ? array_map('sanitize_text_field', wp_unslash($_POST['dietary'])) : []
    ];
    
    $api_handler = WPAI_Recipe_Generator_API_Handler::get_instance();
    $result = $api_handler->handle_prompt_request($args, true);

    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    // Add token usage display if available
    $token_html = '';
    if (!empty($result['usage'])) {
        $token_html = '<div class="token-usage">
            <h4>Token Usage</h4>
            <ul>
                <li><strong>Prompt Tokens:</strong> '.esc_html($result['usage']['prompt_tokens']).'</li>
                <li><strong>Completion Tokens:</strong> '.esc_html($result['usage']['completion_tokens']).'</li>
                <li><strong>Total Tokens:</strong> '.esc_html($result['usage']['total_tokens']).'</li>
            </ul>
        </div>';
    }

    wp_send_json_success([
        'prompt' => $result['prompt'],
        'response' => $result['formatted_response'] . $token_html
    ]);
});

add_action('wp_ajax_wp_ai_recipe_generator_generate_recipe', 'wp_ai_recipe_generator_handle_frontend_request');
add_action('wp_ajax_nopriv_wp_ai_recipe_generator_generate_recipe', 'wp_ai_recipe_generator_handle_frontend_request');
add_action('wp_ajax_save_ai_recipe_to_favorites', 'handle_save_ai_recipe_to_favorites');

add_action('wp_ajax_delete_saved_recipe', function() {
    check_ajax_referer('wp_ai_recipe_generator_frontend_nonce', '_wpnonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(esc_html__('Authentication required', 'wpai-recipe-generator'));
    }
    
    $user_id = get_current_user_id();
    $recipe_id = isset($_POST['recipe_id']) ? sanitize_text_field(wp_unslash($_POST['recipe_id'])) : '';
    $saved_recipes = get_user_meta($user_id, 'ai_saved_recipes', true) ?: [];
    
    if (isset($saved_recipes[$recipe_id])) {
        unset($saved_recipes[$recipe_id]);
        update_user_meta($user_id, 'ai_saved_recipes', $saved_recipes);
        wp_send_json_success();
    }
    
    wp_send_json_error(esc_html__('Recipe not found', 'wpai-recipe-generator'));
});

add_action('admin_action_delete_recipe', function() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions.', 'wpai-recipe-generator'));
    }

    $recipe_id = isset($_GET['recipe_id']) ? sanitize_text_field(wp_unslash($_GET['recipe_id'])) : '';
    $user_id = isset($_GET['user_id']) ? absint(wp_unslash($_GET['user_id'])) : 0;

    check_admin_referer('delete_recipe_' . $recipe_id);

    $saved_recipes = get_user_meta($user_id, 'ai_saved_recipes', true) ?: [];
    
    if (isset($saved_recipes[$recipe_id])) {
        unset($saved_recipes[$recipe_id]);
        update_user_meta($user_id, 'ai_saved_recipes', $saved_recipes);
    }

    wp_redirect(admin_url('admin.php?page=wpai-recipe-generator-saved-recipes'));
    exit;
});

add_action('wp_ajax_wp_ai_recipe_generator_bulk_create_posts', function() {
    check_ajax_referer('wp_ai_recipe_generator_ajax_nonce', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Permission denied.', 'wpai-recipe-generator'));
    }

    // 1. Get the raw input safely
    $input_recipes = filter_input(INPUT_POST, 'recipes', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    
    // 2. Validate the input
    if (!is_array($input_recipes) || empty($input_recipes)) {
        wp_send_json_error(esc_html__('No valid recipes provided.', 'wpai-recipe-generator'));
    }

    // 3. Sanitize the array in one operation
    $recipes = array_map(function($recipe) {
        return [
            'id' => isset($recipe['id']) ? sanitize_text_field(wp_unslash($recipe['id'])) : '',
            'user_id' => isset($recipe['user_id']) ? absint(wp_unslash($recipe['user_id'])) : 0
        ];
    }, $input_recipes);

    $created = 0;
    $created_posts = [];
    
    foreach ($recipes as $recipe_data) {
        $recipe_id = $recipe_data['id'];
        $user_id = $recipe_data['user_id'];
        
        // Get the full recipe data
        $saved_recipes = get_user_meta($user_id, 'ai_saved_recipes', true) ?: [];
        
        $recipe = $saved_recipes[$recipe_id];

        // Parse HTML content
        $dom = new DOMDocument();
        @$dom->loadHTML($recipe['html']);
        $xpath = new DOMXPath($dom);
        
        // Extract all elements
        $servings_node = $xpath->query("//p[contains(., 'Servings:')]")->item(0);
        $servings = $servings_node ? trim(str_replace('Servings:', '', $servings_node->nodeValue)) : '';

        $prep_time_node = $xpath->query("//p[contains(., 'Prep Time:')]")->item(0);
        $prep_time = $prep_time_node ? trim(str_replace('Prep Time:', '', $prep_time_node->nodeValue)) : '';

        $cook_time_node = $xpath->query("//p[contains(., 'Cook Time:')]")->item(0);
        $cook_time = $cook_time_node ? trim(str_replace('Cook Time:', '', $cook_time_node->nodeValue)) : '';

        $prep_mins = (int)preg_replace('/[^0-9]/', '', $prep_time);
        $cook_mins = (int)preg_replace('/[^0-9]/', '', $cook_time);
        $total_time = $prep_mins + $cook_mins;
        
        $ingredients = [];
        $ingredient_nodes = $xpath->query("//ul[@class='recipe-ingredients']/li");
        foreach ($ingredient_nodes as $node) {
            $text = trim($node->nodeValue);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (function_exists('mb_convert_encoding')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
            $text = str_replace(['Â°', 'â„¢'], ['°', '™'], $text);
            $ingredients[] = $text;
        }
        
        $instructions = [];
        $instruction_nodes = $xpath->query("//ol[@class='recipe-instructions']/li");
        foreach ($instruction_nodes as $node) {
            $text = trim($node->nodeValue);
            // Fix double-encoded UTF-8 characters
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Convert to proper UTF-8 if needed
            if (function_exists('mb_convert_encoding')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
            // Clean any remaining encoding artifacts
            $text = str_replace(['Â°', 'â„¢'], ['°', '™'], $text);
            $instructions[] = $text;
        }
        
        $nutrition = [];
        $nutrition_nodes = $xpath->query("//ul[@class='recipe-nutrition']/li");
        foreach ($nutrition_nodes as $node) {
            $nutrition[] = trim($node->nodeValue);
        }

        // Parse nutrition information
        $nutrition_meta = [
            '_recipe_calories' => '',
            '_recipe_fat' => '',               // fatContent
            '_recipe_carbohydrates' => '',     // carbohydrateContent
            '_recipe_fiber' => '',             // fiberContent
            '_recipe_protein' => '',           // proteinContent
            '_recipe_sugar' => '',             // sugarContent
            '_recipe_sodium' => '',            // sodiumContent
            '_recipe_cholesterol' => ''        // cholesterolContent
        ];

        foreach ($nutrition as $nutrition_item) {
            if (preg_match('/(calories|fat|carbs|carbohydrates|protein|sugar|fiber|sodium|cholesterol):?\s*([0-9\.]+)\s*(kcal|g|mg)?/i', 
                strtolower($nutrition_item), $matches)) {
                
                $nutri_type = $matches[1];
                // Standardize field names
                if ($nutri_type === 'carbs') $nutri_type = 'carbohydrates';
                
                $value = $matches[2] . ($matches[3] ?? '');
                $meta_key = '_recipe_' . $nutri_type;
                
                if (array_key_exists($meta_key, $nutrition_meta)) {
                    $nutrition_meta[$meta_key] = $value;
                }
            }
        }

        // Generate block content
        $blocks = [];

        $blocks[] = '<!-- wp:group {"layout":{"type":"constrained","wideSize":"1000px},"style":{"spacing":{"padding":{"top":"20px","bottom":"20px"}}}} -->';
        $blocks[] = '<div class="wp-block-group">';
        
        // 1. Description
        if (!empty($recipe['description'])) {
            $blocks[] = '<!-- wp:paragraph --><p class="recipe-description">' . esc_html($recipe['description']) . '</p><!-- /wp:paragraph -->';
        }

        // 2. Meta Group 1 (Times/Servings)
        $blocks[] = '<!-- wp:group {"className":"recipe-meta-container","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->';
        $blocks[] = '<div class="wp-block-group recipe-meta-container" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">';

        $meta_items = [
            'servings' => [
                'icon' => 'groups',
                'label' => 'Servings',
                'value' => $servings
            ],
            'prep_time' => [
                'icon' => 'clock',
                'label' => 'Prep Time',
                'value' => $prep_time
            ],
            'cook_time' => [
                'icon' => 'food',
                'label' => 'Cook Time',
                'value' => $cook_time
            ]
        ];

        foreach ($meta_items as $key => $item) {
            if (!empty($item['value'])) {
                $blocks[] = sprintf(
                    '<!-- wp:group {"className":"meta-group","layout":{"type":"flex","flexWrap":"nowrap"}} -->
                    <div class="wp-block-group meta-group">
                        <!-- wp:paragraph {"className":"meta-icon"} -->
                        <p class="meta-icon"><span class="dashicons dashicons-%s"></span></p>
                        <!-- /wp:paragraph -->
                        
                        <!-- wp:paragraph {"className":"meta-value"} -->
                        <p class="meta-value">%s: %s</p>
                        <!-- /wp:paragraph -->
                    </div>
                    <!-- /wp:group -->',
                    esc_attr($item['icon']),
                    esc_html($item['label']),
                    esc_html($item['value'])
                );
            }
        }

        $blocks[] = '</div>';
        $blocks[] = '<!-- /wp:group -->';
        
        // 4. Ingredients
        if (!empty($ingredients)) {
            $blocks[] = '<!-- wp:group {"className":"is-style-section-1","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}},"border":{"radius":"8px"}},"layout":{"type":"default"}} -->';
            $blocks[] = '<div class="wp-block-group is-style-section-1 ingredients-wrapper" style="border-radius:8px;margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">';
            $blocks[] = '<!-- wp:heading {"level":3,"className":"is-style-text-subtitle","style":{"border":{"bottom":{"color":"var:preset|color|accent-6","style":"solid","width":"1px"}},"spacing":{"padding":{"bottom":"var:preset|spacing|20"}}}} -->
            <h3 class="wp-block-heading is-style-text-subtitle" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-style:solid;border-bottom-width:1px;padding-bottom:var(--wp--preset--spacing--20)">Ingredients</h3><!-- /wp:heading -->';
            $ingredient_blocks = array_map(function($item) {
                return '<!-- wp:list-item --><li>' . esc_html($item) . '</li><!-- /wp:list-item -->';
            }, $ingredients);
            $blocks[] = '<!-- wp:list --><ul>' . implode('', $ingredient_blocks) . '</ul><!-- /wp:list -->';
            $blocks[] = '</div>';
            $blocks[] = '<!-- /wp:group -->';
        }
        
        // 5. Instructions
        if (!empty($instructions)) {
            $blocks[] = '<!-- wp:group {"className":"is-style-section-1","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}},"border":{"radius":"8px"}},"layout":{"type":"default"}} -->';
            $blocks[] = '<div class="wp-block-group is-style-section-1 instructions-wrapper" style="border-radius:8px;margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)">';
            $blocks[] = '<!-- wp:heading {"level":3,"className":"is-style-text-subtitle","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|20"}},"border":{"bottom":{"color":"var:preset|color|accent-6","style":"solid","width":"1px"},"top":{},"right":{},"left":{}}}} -->
            <h3 class="wp-block-heading is-style-text-subtitle" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-style:solid;border-bottom-width:1px;padding-bottom:var(--wp--preset--spacing--20)">Instructions</h3><!-- /wp:heading -->';
            $instruction_blocks = array_map(function($item) {
                $clean = htmlspecialchars($item, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
                return '<!-- wp:list-item --><li>' . $clean . '</li><!-- /wp:list-item -->';
            }, $instructions);
            $blocks[] = '<!-- wp:list {"ordered":true} --><ol>' . implode('', $instruction_blocks) . '</ol><!-- /wp:list -->';
            $blocks[] = '</div>';
            $blocks[] = '<!-- /wp:group -->';
        }
        
        // 6. Nutrition
        if (!empty($nutrition)) {
            $blocks[] = '<!-- wp:group {"className":"is-style-section-2","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"border":{"radius":"8px"},"shadow":"var:preset|shadow|deep"},"layout":{"type":"default"}} -->';
            $blocks[] = '<div class="wp-block-group is-style-section-2 nutrition-wrapper" style="border-radius:8px;margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30);padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30);box-shadow:var(--wp--preset--shadow--deep)">';
            $blocks[] = '<!-- wp:heading {"textAlign":"left","level":3,"className":"is-style-text-subtitle","style":{"border":{"bottom":{"color":"var:preset|color|accent-6","style":"solid","width":"1px"}},"spacing":{"padding":{"bottom":"var:preset|spacing|20"}}}} -->
            <h3 class="wp-block-heading has-text-align-left is-style-text-subtitle" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-style:solid;border-bottom-width:1px;padding-bottom:var(--wp--preset--spacing--20)">Nutritional Information</h3>
            <!-- /wp:heading -->';
            $nutrition_blocks = array_map(function($item) {
                return '<!-- wp:list-item --><li>' . esc_html($item) . '</li><!-- /wp:list-item -->';
            }, $nutrition);
            $blocks[] = '<!-- wp:list --><ul>' . implode('', $nutrition_blocks) . '</ul><!-- /wp:list -->';
            $blocks[] = '</div>';
            $blocks[] = '<!-- /wp:group -->';
        }
        $blocks[] = '</div>';
        $blocks[] = '<!-- /wp:group -->';

        // Create the post
        $post_id = wp_insert_post([
            'post_title'   => $recipe['name'],
            'post_content' => implode("\n\n", $blocks),
            'post_status'  => 'draft',
            'post_author'  => $user_id,
            'post_type'    => 'ai_recipe',
            'meta_input'   => array_merge([
                '_recipe_servings' => $servings,
                '_recipe_prep_time' => $prep_mins,
                '_recipe_cook_time' => $cook_mins,
                '_recipe_total_time' => $total_time,
                '_wpai_recipe_generator_id' => $recipe_id,
                '_recipe_original_user' => $user_id
            ], $nutrition_meta)
        ]);

        // Set category and tags - FIXED TAXONOMY NAMES
        $default_category = get_term_by('slug', 'main-dishes', 'wpai_recipe_category');
        if ($default_category) {
            wp_set_object_terms($post_id, $default_category->term_id, 'wpai_recipe_category');
        }
        
        // FIXED: Use correct key for dietary tags and correct taxonomy
        if (!empty($recipe['wpai_dietary_tags'])) {
            // Convert tag names to term IDs or create new terms
            $term_ids = array();
            foreach ($recipe['wpai_dietary_tags'] as $tag_name) {
                $term = term_exists($tag_name, 'wpai_dietary_tag');
                if (!$term) {
                    $term = wp_insert_term($tag_name, 'wpai_dietary_tag');
                }
                if (!is_wp_error($term)) {
                    $term_ids[] = (int)$term['term_id'];
                }
            }
            wp_set_object_terms($post_id, $term_ids, 'wpai_dietary_tag');
        }

        // Add custom meta
        update_post_meta($post_id, '_wpai_recipe_generator_id', $recipe_id);
        update_post_meta($post_id, '_recipe_original_user', $user_id);

        $created++;
        $created_posts[] = [
            'recipe_id' => $recipe_id,
            'post_id' => $post_id,
            'edit_link' => get_edit_post_link($post_id, 'raw')
        ];
    }
    
    wp_send_json_success([
        'count' => $created,
        'created_posts' => $created_posts,
        /* translators: %d: Number of posts created */
        'message' => sprintf(_n('Created %d post', 'Created %d posts', $created, 'wpai-recipe-generator'), $created)
    ]);
});

add_action('wp_ajax_check_recipe_post', function() {
    check_ajax_referer('wp_ai_recipe_generator_frontend_nonce', '_wpnonce');
    $recipe_id = isset($_POST['recipe_id']) ? sanitize_text_field(wp_unslash($_POST['recipe_id'])) : '';
    $post_id = wp_ai_recipe_generator_find_recipe_post($recipe_id);
    
    wp_send_json_success([
        'has_post' => (bool)$post_id,
        'post_url' => $post_id ? get_permalink($post_id) : null
    ]);
});

add_action('wp_ajax_wp_ai_recipe_generator_get_dietary_options', function() {
    check_ajax_referer('wp_ai_recipe_generator_ajax_nonce', '_wpnonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Permission denied.', 'wpai-recipe-generator'));
    }
    
    $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
    $options = $prompt_manager->get_dietary_options();
    
    wp_send_json_success([
        'options' => $options
    ]);
});

// Standalone helper functions
function wp_ai_recipe_generator_handle_frontend_request() {
    check_ajax_referer('wp_ai_recipe_generator_frontend_nonce', '_wpnonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(esc_html__('Authentication required.', 'wpai-recipe-generator'));
    }
    
    $args = [
        'servings' => !empty($_POST['servings']) ? absint($_POST['servings']) : 4,
        'include_ingredients' => !empty($_POST['include']) ? sanitize_text_field(wp_unslash($_POST['include'])) : '',
        'exclude_ingredients' => !empty($_POST['exclude']) ? sanitize_text_field(wp_unslash($_POST['exclude'])) : '',
        'dietary' => !empty($_POST['dietary']) ? array_map('sanitize_key', $_POST['dietary']) : []
    ];
    
    $api_handler = WPAI_Recipe_Generator_API_Handler::get_instance();
    $result = $api_handler->handle_prompt_request($args);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success([
        'html' => $result['formatted_response']
    ]);
}

function WPAI_recipe_generator_format_recipe_for_display($recipe_data) {
    if (is_string($recipe_data)) {
        $recipe = json_decode($recipe_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '<div class="error">' . esc_html__('Failed to parse recipe data', 'wpai-recipe-generator') . '</div>';
        }
    } else {
        $recipe = $recipe_data;
    }
    
    // Basic validation
    if (!isset($recipe['recipe_name'])) {
        return '<div class="error">' . esc_html__('Invalid recipe format received', 'wpai-recipe-generator') . '</div>';
    }
    
    ob_start(); ?>
    <div class="recipe-container">
        <h2><?php echo esc_html($recipe['recipe_name']); ?></h2>
        <?php if (!empty($recipe['description'])) : ?>
            <p class="description"><?php echo wp_kses_post($recipe['description']); ?></p>
        <?php endif; ?>
        
        <div class="recipe-meta">
            <?php if (!empty($recipe['servings'])) : ?>
                <span><?php
                    /* translators: %s: Number of servings */
                    printf(esc_html__('Servings: %s', 'wpai-recipe-generator'), esc_html($recipe['servings'])); ?></span>
            <?php endif; ?>
            <?php if (!empty($recipe['preparation_time'])) : ?>
                <span><?php 
                    /* translators: %s: Preparation time */
                    printf(esc_html__('Prep: %s', 'wpai-recipe-generator'), esc_html($recipe['preparation_time'])); ?></span>
            <?php endif; ?>
            <?php if (!empty($recipe['cooking_time'])) : ?>
                <span><?php 
                    /* translators: %s: Cooking time */
                    printf(esc_html__('Cook: %s', 'wpai-recipe-generator'), esc_html($recipe['cooking_time'])); ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($recipe['ingredients'])) : ?>
            <h3><?php esc_html_e('Ingredients', 'wpai-recipe-generator'); ?></h3>
            <ul>
                <?php foreach ((array)$recipe['ingredients'] as $ingredient) : ?>
                    <li><?php echo esc_html($ingredient); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if (!empty($recipe['method'])) : ?>
            <h3><?php esc_html_e('Method', 'wpai-recipe-generator'); ?></h3>
            <ol>
                <?php foreach ((array)$recipe['method'] as $step) : ?>
                    <li><?php echo esc_html($step); ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function handle_save_ai_recipe_to_favorites() {
    check_ajax_referer('wp_ai_recipe_generator_frontend_nonce', '_wpnonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(esc_html__('You must be logged in to save recipes', 'wpai-recipe-generator'));
    }

    $user_id = get_current_user_id();
    $recipe_id = isset($_POST['recipe_id']) ? sanitize_text_field(wp_unslash($_POST['recipe_id'])) : '';

    // Validate ID format
    if (!preg_match('/^recipe_[a-z0-9]+_[a-z0-9]+_\d+$/', $recipe_id)) {
        wp_send_json_error(esc_html__('Invalid recipe ID format', 'wpai-recipe-generator'));
    }
    
    $recipe_html = isset($_POST['recipe_html']) ? wp_kses_post(wp_unslash($_POST['recipe_html'])) : '';
    
    // Extract recipe name from HTML
    $recipe_name = 'AI Recipe';
    if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $recipe_html, $matches)) {
        $recipe_name = sanitize_text_field(wp_strip_all_tags($matches[1]));
    }

    // Extract description from HTML
    $description = '';
    if (preg_match('/<p class="recipe-description">(.*?)<\/p>/i', $recipe_html, $matches)) {
        $description = wp_strip_all_tags($matches[1]);
    }

    $wpai_dietary_tags = [];
    if (isset($_POST['wpai_dietary_tags'])) {
        if (is_array($_POST['wpai_dietary_tags'])) {
            $wpai_dietary_tags = array_map('sanitize_text_field', wp_unslash($_POST['wpai_dietary_tags']));
        } else {
            $tags_string = sanitize_text_field(wp_unslash($_POST['wpai_dietary_tags']));
            $wpai_dietary_tags = array_filter(array_map('trim', explode(',', $tags_string)));
        }
        
        $wpai_dietary_tags = array_map('sanitize_title', $wpai_dietary_tags);
        $wpai_dietary_tags = array_unique(array_filter($wpai_dietary_tags));
    }
    
    // Get existing saved recipes
    $saved_recipes = get_user_meta($user_id, 'ai_saved_recipes', true) ?: [];
    
    // Add new recipe with all metadata
    $saved_recipes[$recipe_id] = [
        'id' => $recipe_id,
        'name' => $recipe_name,
        'description' => $description,
        'html' => $recipe_html,
        'wpai_dietary_tags' => $wpai_dietary_tags,
        'saved_at' => current_time('mysql'),
        'saved_at_gmt' => current_time('mysql', true)
    ];

    update_user_meta($user_id, 'ai_saved_recipes', $saved_recipes);
    
    wp_send_json_success([
        'recipe_name' => $recipe_name,
        'recipe_id' => $recipe_id
    ]);
}

function wp_ai_recipe_generator_find_recipe_post($recipe_id) {
    // First try to get from cache
    $cache_key = 'recipe_post_' . md5($recipe_id);
    $post_id = wp_cache_get($cache_key, 'WPAI_recipe_generator');
    
    if (false === $post_id) {
        global $wpdb;
        
        // Direct SQL query with proper indexing and suppression of warnings
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        // Direct query used for performance reasons - we're looking up by a specific meta key
        // and caching results to minimize database impact
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id 
                 FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_WPAI_recipe_generator_id' 
                 AND meta_value = %s 
                 LIMIT 1",
                $recipe_id
            )
        );
        
        // Cache the result (even if null)
        wp_cache_set($cache_key, $post_id ?: 0, 'WPAI_recipe_generator', DAY_IN_SECONDS);
    }
    
    return $post_id ?: 0;
}
