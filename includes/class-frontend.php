<?php
class WPAI_Recipe_Generator_Frontend {
    private static $instance;
    private $assets_enqueued = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('WPAI_recipe_generator', [$this, 'recipe_shortcode_handler']);
        add_shortcode('user_saved_recipes', [$this, 'saved_recipes_shortcode_handler']);
        add_shortcode('recipe_user_profile', [$this, 'user_profile_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function recipe_shortcode_handler($atts) {
        $this->set_assets_flag();
        
        // Your existing shortcode content remains exactly the same
        $prompt_manager = WPAI_Recipe_Generator_Prompt_Manager::get_instance();
        $dietary_options = $prompt_manager->get_dietary_options();
        
        ob_start(); ?>
        <div class="wpai-recipe-generator-frontend">
            <form id="wpai-recipe-generator-form">
                <?php wp_nonce_field('wp_ai_recipe_generator_frontend_nonce', '_wpnonce'); ?>
                
                <div class="form-group">
                    <label for="rg-servings"><?php esc_html_e('Servings', 'wpai-recipe-generator'); ?></label>
                    <input type="number" id="rg-servings" name="servings" min="1" max="20" value="2">
                </div>
                
                <div class="form-group">
                    <label for="rg-include"><?php esc_html_e('Must Include Ingredients', 'wpai-recipe-generator'); ?></label>
                    <input type="text" id="rg-include" name="include" 
                        placeholder="<?php esc_attr_e('e.g., chicken, potatoes', 'wpai-recipe-generator'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="rg-exclude"><?php esc_html_e('Must Exclude Ingredients', 'wpai-recipe-generator'); ?></label>
                    <input type="text" id="rg-exclude" name="exclude" 
                        placeholder="<?php esc_attr_e('e.g., nuts, dairy', 'wpai-recipe-generator'); ?>">
                </div>
                
                <?php if (!empty($dietary_options)) : ?>
                    <div class="form-group">
                        <h3><?php esc_html_e('Dietary Requirements', 'wpai-recipe-generator'); ?></h3>
                        <div class="dietary-options">
                            <?php foreach ($dietary_options as $key => $label) : ?>
                                <label class="dietary-option">
                                    <input type="checkbox" name="dietary[]" value="<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="form-buttons">
                   <button type="submit" id="generate-btn" class="rg-submit wp-element-button">
                    <span class="dashicons dashicons-food"></span>
                    <?php esc_html_e('Generate Recipe', 'wpai-recipe-generator'); ?>
                    </button>
                    <button type="button" id="reset-form-btn" class="rg-submit secondary">
                        <span class="dashicons dashicons-update"></span> Reset Form
                    </button>
                </div>
                
                
                <div class="rg-loading" style="display:none;">
                    <div class="rg-spinner"></div>
                    <p class="loading-text">Crafting your perfect recipe...</p>
                </div>
            </form>
            
            <div id="recipe-results" style="display:none;"></div>
            <div id="recipe-actions" style="display:none;">
                <button id="save-recipe-btn" class="rg-submit wp-element-button">Save to Favorites</button>
                <span id="save-status"></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function saved_recipes_shortcode_handler($atts) {
        $this->set_assets_flag();
        
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your saved recipes.</p>';
        }
        
        $saved_recipes = get_user_meta(get_current_user_id(), 'ai_saved_recipes', true) ?: [];
        $recipe_count = count($saved_recipes);
        
        if (empty($saved_recipes)) {
            return '<p>You have no saved recipes yet.</p>';
        }

        ob_start(); ?>
        <div class="user-saved-recipes">
            <h3>Your Saved Recipes <span class="recipe-count">(<?php echo esc_html( $recipe_count ); ?>)</span></h3>
            
            <?php if ($recipe_count > 0) : ?>
                <ul class="saved-recipes-list">
                    <?php foreach ($saved_recipes as $recipe_id => $recipe) : ?>
                        <li class="saved-recipe-item" data-recipe-id="<?php echo esc_attr($recipe_id); ?>">
                            <div class="recipe-summary">
                                <h4><?php echo esc_html($recipe['name']); ?></h4>
                                <a class="view-recipe-btn wp-element-button">Quick-View Recipe</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p>You have no saved recipes yet.</p>
            <?php endif; ?>
        </div>

        <!-- Modal Structure -->
        <div id="recipe-modal" class="recipe-modal" style="display:none;">
            <div class="modal-content">
                
                <div class="modal-actions">
                    <button class="modal-action share-recipe" title="Share Recipe">
                        <span class="dashicons dashicons-share"></span> Share
                    </button>
                    <button class="modal-action print-recipe" title="Print Recipe">
                        <span class="dashicons dashicons-printer"></span> Print
                    </button>
                    <a href="#" class="modal-action view-post-btn" style="display:none;" title="View Full Post">
                        <span class="dashicons dashicons-external"></span> View Post
                    </a>
                    <button class="modal-action delete-recipe" title="Delete Recipe">
                        <span class="dashicons dashicons-trash"></span> Delete
                    </button>
                    <span class="close-modal">&times;</span>
                </div>
                <div id="modal-recipe-content"></div>
            </div>
        </div>
        <div id="share-options-modal" class="share-modal" style="display:none;">
            <div class="share-modal-content">
                <span class="close-share-modal">&times;</span>
                <h4>Share Recipe</h4>
                <p style="font-size: .8rem;">Social sharing is only available when the saved recipe has an associated post - you can still email or copy/paste.</p>
                <div class="share-buttons">
                    <a href="#" class="share-btn modal-action platform-share-btn facebook disabled" data-platform="facebook">
                        <span class="dashicons dashicons-facebook"></span> Facebook
                    </a>
                    <a href="#" class="share-btn modal-action platform-share-btn twitter disabled" data-platform="twitter">
                        <span class="dashicons dashicons-twitter"></span> X (Twitter)
                    </a>
                    <a href="#" class="share-btn modal-action platform-share-btn pinterest disabled" data-platform="pinterest">
                        <span class="dashicons dashicons-pinterest"></span> Pinterest
                    </a>
                    <a href="#" class="share-btn modal-action platform-share-btn reddit disabled" data-platform="reddit">
                        <span class="dashicons dashicons-reddit"></span> Reddit
                    </a>
                    <a href="#" class="share-btn modal-action email-recipe" data-platform="email">
                        <span class="dashicons dashicons-email"></span> Email
                    </a>
                    <button class="share-btn modal-action copy-recipe" title="Copy Recipe">
                        <span class="dashicons dashicons-clipboard"></span> Copy
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function user_profile_shortcode($atts) {
        $this->set_assets_flag();
        
        $atts = shortcode_atts([
            'show_avatar' => true,
            'show_name'   => true,
            'show_login'  => true,
            'avatar_size' => 50
        ], $atts, 'recipe_user_profile');

        ob_start();
        
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            ?>
            <div class="wpai-recipe-generator-user-profile">
                <?php if ($atts['show_avatar']) : ?>
                    <div class="user-avatar">
                        <?php echo get_avatar($current_user->ID, (int)$atts['avatar_size']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_name']) : ?>
                    <div class="user-info">
                        <span class="user-display-name">Welcome <?php echo esc_html($current_user->display_name); ?></span>
                        <?php if ($atts['show_login']) : ?>
                            <a href="<?php echo esc_url(wp_logout_url()); ?>" class="logout-link">
                                <?php esc_html_e('Log Out', 'wpai-recipe-generator'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        } elseif ($atts['show_login']) {
            ?>
            <div class="wpai-recipe-generator-user-profile">
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="login-link">
                    <?php esc_html_e('Log In', 'wpai-recipe-generator'); ?>
                </a>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }

    private function set_assets_flag() {
        $this->assets_enqueued = true;
    }

    public function enqueue_assets() {
        global $post;
        
        // Check if we've processed shortcodes
        if ($this->assets_enqueued) {
            $this->do_enqueue_assets();
            return;
        }
        
        // Fallback check for posts/pages
        if (is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'WPAI_recipe_generator') || 
               has_shortcode($post->post_content, 'user_saved_recipes')) {
                $this->do_enqueue_assets();
            }
        }
    }

    private function do_enqueue_assets() {
        if (did_action('wp_enqueue_scripts') !== 1) return;
        
        wp_enqueue_style(
            'wpai-recipe-generator-frontend',
            WPAI_RECIPE_GENERATOR_URL . 'assets/css/frontend.min.css',
            [],
            WPAI_RECIPE_GENERATOR_VERSION
        );
        
        wp_enqueue_script(
            'wpai-recipe-generator-frontend',
            WPAI_RECIPE_GENERATOR_URL . 'assets/js/frontend.js',
            ['jquery'],
            WPAI_RECIPE_GENERATOR_VERSION,
            true
        );
        
        wp_localize_script(
            'wpai-recipe-generator-frontend',
            'recipeGeneratorFrontendVars',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_ai_recipe_generator_frontend_nonce'),
                'saved_recipes' => is_user_logged_in() ? get_user_meta(get_current_user_id(), 'ai_saved_recipes', true) : []
            ]
        );
    }
}

// Initialize
WPAI_Recipe_Generator_Frontend::get_instance();
