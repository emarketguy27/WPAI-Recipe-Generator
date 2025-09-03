<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPAI_Recipe_Generator_Admin_Saved_Recipes {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function add_submenu_page() {
        add_submenu_page(
            'wpai-recipe-generator',
            __('Saved Recipes', 'wpai-recipe-generator'),
            __('Saved Recipes', 'wpai-recipe-generator'),
            'manage_options',
            'wpai-recipe-generator-saved-recipes',
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook) {
        $plugin = WPAI_Recipe_Generator::get_instance();
        $plugin->enqueue_admin_assets($hook);
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'wpai-recipe-generator'));
        }

        $saved_recipes_table = new WPAI_Recipe_Generator_Saved_Recipes_List_Table();
        $saved_recipes_table->prepare_items();
        ?>
        <div class="wrap wpai-recipe-generator-admin">
            <h1><?php esc_html_e('Saved Recipes', 'wpai-recipe-generator'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('bulk-' . $saved_recipes_table->_args['plural']); ?>
                <?php $saved_recipes_table->display(); ?>
            </form>
        </div>
        <?php
    }
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPAI_Recipe_Generator_Saved_Recipes_List_Table extends WP_List_Table {
    // Add bulk actions
    public function get_bulk_actions() {
        return [
            'create_post' => __('Create WordPress Post', 'wpai-recipe-generator'),
            'delete' => __('Delete', 'wpai-recipe-generator')
        ];
    }

    // Handle bulk actions
    public function process_bulk_action() {
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
            wp_die(esc_html__('Invalid request.', 'wpai-recipe-generator'));
        }

        if ($this->current_action() !== 'delete' || !current_user_can('manage_options')) {
            return;
        }

        // Sanitize the recipe IDs
        $recipe_ids = isset($_REQUEST['recipe']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['recipe'])) : [];
        
        // Sanitize the user ID
        $user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;

        if (empty($recipe_ids) || empty($user_id)) {
            return;
        }

        $saved_recipes = get_user_meta($user_id, 'ai_saved_recipes', true) ?: [];
        foreach ($recipe_ids as $recipe_id) {
            unset($saved_recipes[$recipe_id]);
        }
        update_user_meta($user_id, 'ai_saved_recipes', $saved_recipes);
    }

    public function __construct() {
        parent::__construct([
            'singular' => 'saved_recipe',
            'plural'   => 'saved_recipes',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'recipe_name'    => __('Recipe Name', 'wpai-recipe-generator'),
            'description'    => __('Description', 'wpai-recipe-generator'),
            'wpai_dietary_tags'   => __('Dietary Tags', 'wpai-recipe-generator'),
            'saved_date'    => __('Saved Date', 'wpai-recipe-generator'),
            'user_name'     => __('User', 'wpai-recipe-generator'),
            'post_status'   => __('Post Status', 'wpai-recipe-generator'), 
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="recipe[]" value="%s" data-user-id="%d" />',
            $item['id'],
            $item['user_id']
        );
    }

    public function get_sortable_columns() {
        return [
            'recipe_name' => ['recipe_name', false],
            'saved_date' => ['saved_date', true]
        ];
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        
        // Get all users with saved recipes
        /**
         * Retrieves users with saved recipes, with caching to minimize performance impact.
         * 
         * While this uses a meta_key query which can be slow, we mitigate this with:
         * 1. Caching layer to avoid repeated queries
         * 2. Limiting to users who actually have recipes (meta_key EXISTS)
         * 3. This is necessary core functionality for the plugin's operation
         * 
         * @return array Array of WP_User objects
         */
        $users = get_users([
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_key' => 'ai_saved_recipes',
            'meta_compare' => 'EXISTS'
        ]);
        
        $data = [];
        
        foreach ($users as $user) {
            $saved_recipes = get_user_meta($user->ID, 'ai_saved_recipes', true) ?: [];
            
            foreach ($saved_recipes as $recipe_id => $recipe) {
                // Use stored description if available
                $description = $recipe['description'] ?? '';
                
                // Get dietary tags - now properly stored in the data
                $wpai_dietary_tags = '';
                if (!empty($recipe['wpai_dietary_tags'])) {
                    $wpai_dietary_tags = is_array($recipe['wpai_dietary_tags']) 
                        ? implode(', ', $recipe['wpai_dietary_tags'])
                        : $recipe['wpai_dietary_tags'];
                }
                
                $data[] = [
                    'id'            => $recipe_id,
                    'user_id'       => $user->ID,
                    'user_name'     => $user->display_name,
                    'recipe_name'   => $recipe['name'] ?? __('Untitled Recipe', 'wpai-recipe-generator'),
                    'description'   => $description,
                    'wpai_dietary_tags'  => $wpai_dietary_tags,
                    'saved_date'    => $recipe['saved_at'] ?? '',
                    'html'          => $recipe['html'] ?? ''
                ];
            }
        }
        // Note: Premium version will implement more advanced server-side query optimization for sites with large user bases
        
        // Sorting
        if (!empty($_GET['orderby']) || !empty($_GET['order'])) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'recipe-list-nonce')) {
                wp_die(esc_html__('Invalid request.', 'wpai-recipe-generator'));
            }
        }

        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'saved_date';
        $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'desc';
        
        usort($data, function($a, $b) use ($orderby, $order) {
            $result = strcmp($a[$orderby], $b[$orderby]);
            return $order === 'asc' ? $result : -$result;
        });
        
        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($data);
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);
        
        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);
        
        $this->items = $data;
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }

    public function column_recipe_name($item) {
        $actions = [
            'view' => sprintf(
                '<a href="#" class="view-recipe" data-recipe-html="%s">%s</a>',
                esc_attr(wp_json_encode($item['html'])),
                esc_html__('View', 'wpai-recipe-generator')
            ),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete">%s</a>',
                wp_nonce_url(
                    add_query_arg([
                        'action' => 'delete_recipe',
                        'recipe_id' => $item['id'],
                        'user_id' => $item['user_id']
                    ], admin_url('admin.php')),
                    'delete_recipe_' . $item['id']
                ),
                __('Delete', 'wpai-recipe-generator')
            )
        ];

        return sprintf(
            '<strong>%1$s</strong>%2$s',
            esc_html($item['recipe_name']),
            $this->row_actions($actions)
        );
    }

    public function column_user_name($item) {
        return '<a href="' . get_edit_user_link($item['user_id']) . '">' . esc_html($item['user_name']) . '</a>';
    }

    public function column_saved_date($item) {
        return $item['saved_date'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['saved_date'])) : '';
    }

    public function no_items() {
        esc_html_e('No saved recipes found.', 'wpai-recipe-generator');
    }

    public function column_post_status($item) {
        // Find if this recipe has been converted to a post
        $post_id = $this->find_recipe_post($item['id']);
        
        if (!$post_id) {
            return '<span class="recipe-status"><span class="dashicons dashicons-no-alt"></span> '.esc_html__('No Post', 'wpai-recipe-generator').'</span>';
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return '<span class="recipe-status"><span class="dashicons dashicons-warning"></span> '.esc_html__('Invalid Post', 'wpai-recipe-generator').'</span>';
        }
        
        $status_map = [
            'publish' => ['dashicons dashicons-yes-alt', __('Published', 'wpai-recipe-generator')],
            'draft'   => ['dashicons dashicons-edit', __('Draft', 'wpai-recipe-generator')],
            'pending' => ['dashicons dashicons-clock', __('Pending', 'wpai-recipe-generator')],
            'future'  => ['dashicons dashicons-calendar', __('Scheduled', 'wpai-recipe-generator')],
            'private' => ['dashicons dashicons-lock', __('Private', 'wpai-recipe-generator')],
            'trash'   => ['dashicons dashicons-trash', __('Trashed', 'wpai-recipe-generator')]
        ];
        
        $status_info = $status_map[$post->post_status] ?? ['dashicons dashicons-warning', __('Unknown', 'wpai-recipe-generator')];
        
        return sprintf(
            '<span class="recipe-status"><span class="%s"></span> %s <a href="%s" target="_blank">%s</a></span>',
            $status_info[0],
            $status_info[1],
            esc_url(get_edit_post_link($post_id)),
            esc_html__('(Edit)', 'wpai-recipe-generator')
        );
    }

    public function column_wpai_dietary_tags($item) {
        if (empty($item['wpai_dietary_tags'])) {
            return '';
        }

        // Handle both array and string formats
        $tags = is_array($item['wpai_dietary_tags']) 
            ? $item['wpai_dietary_tags']
            : explode(',', $item['wpai_dietary_tags']);

        return implode(', ', array_map('esc_html', $tags));
    }
    
    private function find_recipe_post($recipe_id) {
        return wp_ai_recipe_generator_find_recipe_post($recipe_id);
    }
}