<?php
/**
 * Handles generating Schema.org markup for recipes
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Recipe_Generator_Schema {
    /**
     * Get schema for a recipe post
     */
    public static function get_recipe_schema($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'ai_recipe') {
            return false;
        }

        // Basic recipe information
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => get_the_title($post_id),
            'description' => wp_strip_all_tags(get_the_excerpt($post_id)),
            'datePublished' => get_the_date('c', $post_id),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author)
            ]
        ];

        // Featured image
        if (has_post_thumbnail($post_id)) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => get_the_post_thumbnail_url($post_id, 'full'),
                'width' => 1200,
                'height' => 800
            ];
        }

        // Extract metadata from post content
        $content = $post->post_content;

        // 1. SERVINGS
        if ($servings = get_post_meta($post_id, '_recipe_servings', true)) {
            $schema['recipeYield'] = $servings;
        }
        
        // 2. TIMES
        // $prep_time = get_post_meta($post_id, '_recipe_prep_time', true);
        // $cook_time = get_post_meta($post_id, '_recipe_cook_time', true);
        $prep_mins = get_post_meta($post_id, '_recipe_prep_time', true);
        $cook_mins = get_post_meta($post_id, '_recipe_cook_time', true);
        
        // if ($prep_time) {
        //     $schema['prepTime'] = self::format_duration($prep_time);
        // }

        // if ($cook_time) {
        //     $schema['cookTime'] = self::format_duration($cook_time);
        // }

        if ($prep_mins) {
            $schema['prepTime'] = 'PT' . $prep_mins . 'M';
        }
        if ($cook_mins) {
            $schema['cookTime'] = 'PT' . $cook_mins . 'M';
        }
        if ($prep_mins && $cook_mins) {
            $schema['totalTime'] = 'PT' . ($prep_mins + $cook_mins) . 'M';
        }

        // 3. INGREDIENTS - Update the parsing logic
        $content = get_post_field('post_content', $post_id);
        $ingredients = self::parse_ingredients_from_content($content);
        if (!empty($ingredients)) {
            $schema['recipeIngredient'] = $ingredients;
        }
        
        // 4. Parse instructions from content
        $instructions = self::parse_instructions_from_content($content);
        if (!empty($instructions)) {
            $schema['recipeInstructions'] = array_map(function($step, $index) {
                return [
                    '@type' => 'HowToStep',
                    'text' => html_entity_decode(wp_strip_all_tags($step)),
                    'position' => $index + 1
                ];
            }, $instructions, array_keys($instructions));
        }

        // 5. Add nutrition information
        $nutrition = [];
        $valid_fields = [
            'calories' => 'calories',
            'fat' => 'fatContent',
            'carbohydrates' => 'carbohydrateContent',
            'protein' => 'proteinContent',
            'sugar' => 'sugarContent',
            'fiber' => 'fiberContent',
            'sodium' => 'sodiumContent',
            'cholesterol' => 'cholesterolContent'
        ];
        
        foreach ($valid_fields as $meta => $schema_field) {
            if ($value = get_post_meta($post_id, '_recipe_' . $meta, true)) {
                $nutrition[$schema_field] = $value;
            }
        }
        
        if (!empty($nutrition)) {
            $schema['nutrition'] = array_merge(
                ['@type' => 'NutritionInformation'],
                $nutrition
            );
        }

        // Aggregate rating if comments are enabled
        if (comments_open($post_id)) {
            $rating = self::get_aggregate_rating($post_id);
            if ($rating) {
                $schema['aggregateRating'] = $rating;
            }
        }

        // FIXED: Keywords from dietary tags - corrected taxonomy name
        $tags = wp_get_post_terms($post_id, 'wpai_dietary_tag', ['fields' => 'names']);
        if (!is_wp_error($tags) && !empty($tags)) {
            $schema['keywords'] = implode(', ', $tags);
        } else {
            $schema['keywords'] = '';
        }

        return $schema;
    }

    // ========= Helper Methods ============= //
    private static function parse_ingredients_from_content($content) {
        // Parse from unordered list with class 'recipe-ingredients'
        if (preg_match('/<ul[^>]*class=[\'"][^\'"]*recipe-ingredients[^\'"]*[\'"][^>]*>(.*?)<\/ul>/s', $content, $matches)) {
            preg_match_all('/<li[^>]*>(.*?)<\/li>/', $matches[1], $ingredients);
            return array_map('wp_strip_all_tags', $ingredients[1]);
        }
        return [];
    }

    private static function parse_instructions_from_content($content) {
        // Parse from ordered list (instructions)
        if (preg_match('/<ol[^>]*>(.*?)<\/ol>/s', $content, $matches)) {
            preg_match_all('/<li[^>]*>(.*?)<\/li>/', $matches[1], $steps);
            return array_map('wp_strip_all_tags', $steps[1]);
        }
        return [];
    }

    /**
     * Get aggregate rating from comments
     */
    private static function get_aggregate_rating($post_id) {
        $comments = get_comments([
            'post_id' => $post_id,
            'status' => 'approve'
        ]);
        
        if (empty($comments)) {
            return false;
        }
        
        $total = 0;
        $count = 0;
        
        foreach ($comments as $comment) {
            $rating = (int)get_comment_meta($comment->comment_ID, 'recipe_rating', true);
            if ($rating > 0) {
                $total += $rating;
                $count++;
            }
        }
        
        if ($count === 0) {
            return false;
        }
        
        return [
            '@type' => 'AggregateRating',
            'ratingValue' => round($total / $count, 1),
            'reviewCount' => $count,
            'bestRating' => 5,
            'worstRating' => 1
        ];
    }

    /**
     * Output JSON-LD script for a recipe
     */
    public static function output_recipe_schema($post_id) {
        $schema = self::get_recipe_schema($post_id);
        if (!$schema) {
            return;
        }
        
        echo '<script type="application/ld+json">';
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo '</script>';
    }

}