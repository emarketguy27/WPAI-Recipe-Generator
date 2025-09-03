<?php
/**
 * Plugin Name: WPAI Recipe Generator
 * Plugin URI: https://jamesdennis.org/wpai-recipe-generator.html
 * Description: ✨ AI-Powered Recipe Generation - Transform your food blog — SEO-optimized recipes in seconds! Perfect for bloggers, chefs, and content creators.
 * Version: 1.2.0
 * Author: James Dennis
 * Author URI: https://jamesdennis.org
 * License: GPL v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wpai-recipe-generator
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WPAI_RECIPE_GENERATOR_VERSION', '1.2.0');
define('WPAI_RECIPE_GENERATOR_PATH', plugin_dir_path(__FILE__));
define('WPAI_RECIPE_GENERATOR_URL', plugin_dir_url(__FILE__));
define('WPAI_RECIPE_GENERATOR_TEMPLATES_PATH', WPAI_RECIPE_GENERATOR_PATH . 'templates/');

class WPAI_Recipe_Generator {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Include required files
        $this->includes();

        // Initialize components
        add_action('plugins_loaded', array($this, 'init'));

        // Register Custom Taxonomies
        add_action('init', [$this, 'init_recipe_taxonomies'], 20);

        add_action('wp_head', [$this, 'output_recipe_schema']);
    }

    private function includes() {
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-prompt-manager.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-providers.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-api-handler.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/ajax-handlers.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-admin-ai-settings.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-admin-main.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-frontend.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-admin-saved-recipes.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-patterns-handler.php'; // Patterns Handler
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-template-loader.php';
        require_once WPAI_RECIPE_GENERATOR_PATH . 'includes/class-schema-generator.php';
    }

    public function output_recipe_schema() {
        if (is_singular('ai_recipe')) {
            WPAI_Recipe_Generator_Schema::output_recipe_schema(get_the_ID());
        }
    }
    
    public function init() {
    }

    // Reister Custom Post Type
    public function register_post_types() {
        if (!post_type_exists('ai_recipe')) {
            $args = [
                'labels' => [
                    'name' => __('AI Recipes', 'wpai-recipe-generator'),
                    'singular_name' => __('AI Recipe', 'wpai-recipe-generator'),
                    'add_new' => __('Add New', 'wpai-recipe-generator'),
                    'add_new_item' => __('Add New Recipe', 'wpai-recipe-generator'),
                    'edit_item' => __('Edit Recipe', 'wpai-recipe-generator'),
                    'new_item' => __('New Recipe', 'wpai-recipe-generator'),
                    'view_item' => __('View Recipe', 'wpai-recipe-generator'),
                    'view_items' => __('View Recipes', 'wpai-recipe-generator'),
                    'taxonomies' => [],
                    'search_items' => __('Search Recipes', 'wpai-recipe-generator'),
                    'not_found' => __('No recipes found', 'wpai-recipe-generator'),
                    'not_found_in_trash' => __('No recipes found in Trash', 'wpai-recipe-generator'),
                    'all_items' => __('All Recipes', 'wpai-recipe-generator'),
                    'archives' => __('Recipe Archives', 'wpai-recipe-generator'),
                    'attributes' => __('Recipe Attributes', 'wpai-recipe-generator'),
                    'insert_into_item' => __('Insert into recipe', 'wpai-recipe-generator')
                ],
                'public' => true,
                'publicly_queryable' => true,
                'exclude_from_search' => false,
                'hierarchical' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_in_nav_menus' => true,
                'show_in_rest' => true,
                'rest_base' => 'ai-recipes',
                'has_archive' => true,
                'rewrite' => [
                    'slug' => 'ai-recipes',
                    'with_front' => false
                ],
                'supports' => [
                    'title', 'editor', 'author', 'thumbnail',
                    'excerpt', 'comments', 'custom-fields', 'page-attributes'
                ],
                // 'taxonomies' => ['wpai_recipe_category', 'wpai_dietary_tag'],
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'menu_icon' => 'dashicons-food',
                'menu_position' => 5,
                'can_export' => true
            ];

            register_post_type('ai_recipe', $args);            
        }
    }

    // register & init Custom Post Type Taxonimies
    private function register_recipe_taxonomies() {
        // WPAI Recipe Categories (hierarchical)
        register_taxonomy('wpai_recipe_category', 'ai_recipe', [
            'hierarchical' => true,
            'labels' => [
                'name' => __('WPAI Categories', 'wpai-recipe-generator'),
                'singular_name' => __('WPAI Category', 'wpai-recipe-generator'),
                'search_items' => __('Search WPAI Categories', 'wpai-recipe-generator'),
                'all_items' => __('All WPAI Categories', 'wpai-recipe-generator'),
                'parent_item' => __('Parent Category', 'wpai-recipe-generator'),
                'parent_item_colon' => __('Parent Category:', 'wpai-recipe-generator'),
                'edit_item' => __('Edit Category', 'wpai-recipe-generator'),
                'update_item' => __('Update Category', 'wpai-recipe-generator'),
                'add_new_item' => __('Add New Category', 'wpai-recipe-generator'),
                'new_item_name' => __('New Category Name', 'wpai-recipe-generator'),
                'menu_name' => __('WPAI Categories', 'wpai-recipe-generator'),
            ],
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => [
                'slug' => 'wpai-category',
                'with_front' => false
            ],
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'public' => true,
            'query_var' => true
        ]);

        // WPAI Dietary Tags (non-hierarchical)
        register_taxonomy('wpai_dietary_tag', 'ai_recipe', [
            'hierarchical' => false,
            'labels' => [
                'name' => __('WPAI Dietary Tags', 'wpai-recipe-generator'),
                'singular_name' => __('WPAI Dietary Tag', 'wpai-recipe-generator'),
                'search_items' => __('Search Dietary Tags', 'wpai-recipe-generator'),
                'popular_items' => __('Popular Dietary Tags', 'wpai-recipe-generator'),
                'all_items' => __('All Dietary Tags', 'wpai-recipe-generator'),
                'edit_item' => __('Edit Dietary Tag', 'wpai-recipe-generator'),
                'update_item' => __('Update Dietary Tag', 'wpai-recipe-generator'),
                'add_new_item' => __('Add New Dietary Tag', 'wpai-recipe-generator'),
                'new_item_name' => __('New Dietary Tag Name', 'wpai-recipe-generator'),
                'separate_items_with_commas' => __('Separate dietary tags with commas', 'wpai-recipe-generator'),
                'add_or_remove_items' => __('Add or remove dietary tags', 'wpai-recipe-generator'),
                'choose_from_most_used' => __('Choose from most used dietary tags', 'wpai-recipe-generator'),
                'menu_name' => __('WPAI Dietary Tags', 'wpai-recipe-generator'),
            ],
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => [
                'slug' => 'wpai-dietary-tag',
                'with_front' => false
            ],
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'public' => true,
            'query_var' => true
        ]);
    }
    public function init_recipe_taxonomies() { // To keep recipe taxonomies registration private
        $this->register_recipe_taxonomies();
    }

    /*** Enqueue Admin assets ***/
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wpai-recipe-generator') === false) {
            return;
        }
        // Enqueue admin CSS
        wp_enqueue_style(
            'wpai-recipe-generator-admin',
            WPAI_RECIPE_GENERATOR_URL . 'assets/css/admin.min.css',
            [],
            WPAI_RECIPE_GENERATOR_VERSION
        );
        
        // Enqueue admin JS
        wp_enqueue_script(
            'wpai-recipe-generator-admin',
            WPAI_RECIPE_GENERATOR_URL . 'assets/js/admin.js',
            array('jquery'),
            WPAI_RECIPE_GENERATOR_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script(
            'wpai-recipe-generator-admin',
            'recipeGeneratorVars',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'adminurl' => admin_url('admin.php'),
                'nonce' => wp_create_nonce('wp_ai_recipe_generator_ajax_nonce'),
                'confirmResetPrompt' => __('Are you sure you want to reset the prompt to default?', 'wpai-recipe-generator'),
                'promptResetSuccess' => __('Prompt reset to default.', 'wpai-recipe-generator'),
                'confirmRemoveOption' => __('Are you sure you want to remove this option?', 'wpai-recipe-generator'),
                'confirmResetOptions' => __('Are you sure you want to reset all dietary options to default?', 'wpai-recipe-generator'),
                'remove' => __('Remove', 'wpai-recipe-generator'),
                'testing' => __('Testing...', 'wpai-recipe-generator'),
                'testPrompt' => __('Test Prompt', 'wpai-recipe-generator'),
                'errorOccurred' => __('An error occurred. Please try again.', 'wpai-recipe-generator')
            )
        );

    }
}

// Initialize the plugin
WPAI_Recipe_Generator::get_instance();

register_activation_hook(__FILE__, function() {
    WPAI_Recipe_Generator::get_instance()->register_post_types();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {

    flush_rewrite_rules();

    wp_cache_flush();
});

add_action('plugins_loaded', function() {
    // Initialize provider system
    WPAI_Recipe_Generator_Providers::get_instance();
    
    // Initialize admin interfaces
    if (is_admin()) {
        new WPAI_Recipe_Generator_Admin_Main();
        new WPAI_Recipe_Generator_Admin_AI_Settings();
        new WPAI_Recipe_Generator_Admin_Saved_Recipes();
    }

    WPAI_Recipe_Generator_Patterns_Handler::init();
    // Future: Initialize frontend components here if needed
});

add_action('init', [WPAI_Recipe_Generator::get_instance(), 'register_post_types']);

/*** Register Custom Block Templates ***/
add_action('init', function() {
    // Only register templates if block themes are supported
    if (function_exists('register_block_template')) {
        WPAI_Recipe_Generator_Template_Loader::register_templates();
    }
}, 20);

add_filter('archive_template_hierarchy', function($templates) {
    if (is_post_type_archive('ai_recipe') || 
        is_tax('wpai_dietary_tag') || 
        is_tax('wpai_recipe_category')) {
        array_unshift($templates, 'archive-recipe.php');
    }
    return $templates;
});

add_filter('render_block', function($block_content, $block) {
    if (is_singular('ai_recipe')) {
        // Add schema markup to core/paragraph blocks with specific classes
        if ($block['blockName'] === 'core/paragraph') {
            if (strpos($block['attrs']['className'] ?? '', 'recipe-servings') !== false) {
                $block_content = str_replace(
                    '<p class="',
                    '<p itemprop="recipeYield' . '" class="',
                    $block_content
                );
            }
            // Repeat for other meta items
            elseif (strpos($block['attrs']['className'] ?? '', 'recipe-prep-time') !== false) {
                $block_content = str_replace(
                    '<p class="',
                    '<p itemprop="prepTime' . '" class="',
                    $block_content
                );
            }
        }
    }
    return $block_content;
}, 10, 2);

add_filter('dashboard_glance_items', function($items) {
    $post_type = 'ai_recipe';
    $count = wp_count_posts($post_type);
    
    if ($count && $count->publish) {
        $text = sprintf(
            /* translators: 1: Number of recipes. %d will be replaced with the actual count. */
            _n('%d AI Recipe', '%d AI Recipes', $count->publish, 'wpai-recipe-generator'),
            $count->publish
        );
        
        $items[] = sprintf(
            '<a href="%s" class="ai-recipe-count">%s</a>',
            admin_url('edit.php?post_type=' . $post_type),
            $text
        ) . 
        '<style>.ai-recipe-count:before { content: "\f485"!important; font-family: dashicons; vertical-align: middle; margin-right: 5px; }</style>';
    }
    
    return $items;
});

add_action('wp_enqueue_scripts', function() {
    // Always load Dashicons - they're part of WordPress core
    wp_enqueue_style('dashicons');
    
    // Load minimal base styles for all recipe content
    wp_enqueue_style(
        'wpai-recipe-generator-base',
        WPAI_RECIPE_GENERATOR_URL . 'assets/css/base.min.css',
        [],
        WPAI_RECIPE_GENERATOR_VERSION
    );
});