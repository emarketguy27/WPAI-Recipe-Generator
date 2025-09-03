<?php
/**
 * Handles registration of block patterns for the Recipe Generator plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Recipe_Generator_Patterns_Handler {
    /**
     * Initialize pattern registration
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_patterns_category']);
        add_action('init', [__CLASS__, 'register_patterns']);
    }

    /**
     * Register custom pattern category
     */
    public static function register_patterns_category() {
        register_block_pattern_category(
            'ai-recipes',
            ['label' => __('AI Recipes', 'wpai-recipe-generator')]
        );
    }

    /**
     * Register plugin patterns
     */
    public static function register_patterns() {
        $patterns = [
            'user-recipes-sidebar' => [
                'title' => __('User Recipes Sidebar', 'wpai-recipe-generator'),
                'description' => __('A sidebar section for displaying user-generated recipes', 'wpai-recipe-generator'),
                'categories' => ['ai-recipes'],
                'content' => self::get_pattern_content('user-recipes-sidebar'),
            ],
            'ai-recipe-form-with-sidebar' => [
                'title' => __('AI Recipe Form with Sidebar', 'wpai-recipe-generator'),
                'description' => __('A complete recipe generation form with accompanying sidebar', 'wpai-recipe-generator'),
                'categories' => ['ai-recipes'],
                'content' => self::get_pattern_content('ai-recipe-form-with-sidebar'),
            ],
            'ai-recipe-hero-header' => [
                'title' => __('AI Recipe Hero Header with Sidebar', 'wpai-recipe-generator'),
                'description' => __('A hero header section for single recipe posts with sidebar integration', 'wpai-recipe-generator'),
                'categories' => ['ai-recipes'],
                'content' => self::get_pattern_content('ai-recipe-hero-header'),
            ],
        ];

        foreach ($patterns as $slug => $pattern) {
            register_block_pattern(
                "wpai-recipe-generator/{$slug}",
                $pattern
            );
        }
    }

    /**
     * Get pattern content from file
     */
    private static function get_pattern_content($pattern_name) {
        $pattern_path = WPAI_RECIPE_GENERATOR_PATH . "patterns/{$pattern_name}.html";
        
        if (!file_exists($pattern_path) || !is_readable($pattern_path)) {
            return '';
        }

        $content = @file_get_contents($pattern_path);
        if (false === $content) {
            return '';
        }

        return $content;
    }
}