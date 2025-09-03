<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!defined('WPAI_RECIPE_GENERATOR_REMOVE_DATA_ON_UNINSTALL')) {
    define('WPAI_RECIPE_GENERATOR_REMOVE_DATA_ON_UNINSTALL', true);
}

if (defined('WPAI_RECIPE_GENERATOR_REMOVE_DATA_ON_UNINSTALL') && WPAI_RECIPE_GENERATOR_REMOVE_DATA_ON_UNINSTALL) {
    // ======================
    // POSTS & ATTACHMENTS
    // ======================
    $posts = get_posts([
        'post_type'      => 'ai_recipe',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ]);

    foreach ($posts as $post_id) {
        // Delete attachments first if any
        $attachments = get_children([
            'post_parent' => $post_id,
            'post_type'   => 'attachment',
            'fields'      => 'ids'
        ]);

        foreach ($attachments as $attachment_id) {
            wp_delete_attachment($attachment_id, true);
        }

        wp_delete_post($post_id, true);
    }

    // ======================
    // TAXONOMIES & TERMS
    // ======================
    // First register the taxonomies - Required to ensure explicit registration during clean environment of Uninstall.
    register_taxonomy('WPAI__recipe_category', 'ai_recipe');
    register_taxonomy('WPAI_recipe_tag', 'ai_recipe');

    $taxonomies = ['WPAI_recipe_category', 'WPAI_recipe_tag'];
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids'
        ]);

        foreach ($terms as $term_id) {
            $result = wp_delete_term($term_id, $taxonomy);
        }
        
        // Clean taxonomy cache
        clean_taxonomy_cache($taxonomy);
    }

    // Unregister taxonomies
    unregister_taxonomy('WPAI_recipe_category');
    unregister_taxonomy('WPAI_recipe_tag');

    // ======================
    // USER SAVED RECIPES
    // ======================
    delete_metadata('user', 0, 'ai_saved_recipes', '', true);

    $batch_size = 100;
    $offset = 0;

    do {
        $users = get_users([
            'fields' => 'ids',
            'number' => $batch_size,
            'offset' => $offset
        ]);

        foreach ($users as $user_id) {
            // Get only the meta keys we care about
            $all_meta = get_user_meta($user_id);
            foreach ($all_meta as $meta_key => $value) {
                if (strpos($meta_key, 'wpai_recipe_generator') === 0) {
                    delete_user_meta($user_id, $meta_key);
                }
            }
        }

        $offset += $batch_size;
    } while (!empty($users));

    // ======================
    // PLUGIN OPTIONS
    // ======================
    $all_options = wp_load_alloptions();
    foreach ($all_options as $name => $value) {
        if (strpos($name, 'wpai_recipe_generator') === 0) {
            delete_option($name);
        }
    }

    // ======================
    // TRANSIENTS & CACHE
    // ======================
    // Get all transients from cache
    $transient_keys = [];
    $alloptions = wp_load_alloptions();
    foreach ($alloptions as $name => $value) {
        if (strpos($name, '_transient_') === 0 && stripos($name, 'recipe') !== false) {
            $transient_keys[] = str_replace('_transient_', '', $name);
        }
        if (strpos($name, '_transient_timeout_') === 0 && stripos($name, 'recipe') !== false) {
            $transient_keys[] = str_replace('_transient_timeout_', '', $name);
        }
    }

    // Delete unique transients
    $transient_keys = array_unique($transient_keys);
    foreach ($transient_keys as $transient) {
        delete_transient($transient);
    }

    // ======================
    // CLEANUP
    // ======================
    flush_rewrite_rules();
    wp_cache_flush();
}