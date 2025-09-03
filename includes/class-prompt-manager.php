<?php
class WPAI_Recipe_Generator_Prompt_Manager {
    private static $instance;
    private $default_prompt;
    private $dietary_options_option = 'wp_ai_recipe_generator_dietary_options';
    private $prompt_option = 'wp_ai_recipe_generator_prompt_template';
    private $default_dietary_options;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->default_prompt = $this->get_default_prompt();
        $this->default_dietary_options = $this->get_default_dietary_options();
    }

    public function get_default_prompt() {
        return "Create a recipe that strictly follows all requirements below. Respond ONLY with valid JSON following these EXACT rules:\n" .
            "1. All property names and string values MUST use double quotes\n" .
            "2. The wpai_dietary_tags array MUST be properly formatted like this: \n" .
            "\"wpai_dietary_tags\": [\"value1\", \"value2\"]\n" .
            "3. No trailing commas in arrays or objects\n" .
            "4. No comments or markdown formatting\n\n" .
            "- Cuisine type: {cuisine}\n" .
            "- Dietary requirements: {dietary}\n" .
            "- Must include: {include_ingredients}\n" .
            "- Must exclude: {exclude_ingredients}\n" .
            "- Servings: {servings}\n" .
            "- Skill level: {skill_level}\n\n" .
            "The recipe should include:\n" .
            "1. A descriptive title\n" .
            "2. Complete ingredient list with measurements\n" .
            "3. Detailed step-by-step instructions\n" .
            "4. Cooking time and preparation time\n" .
            "5. Nutritional information\n\n" .
            "Make the recipe {creativity_level} and suitable for {servings} people. If Dietary requirements are requested, the recipe must adhere to these choices.\n\n" .
            "EXAMPLE VALID RESPONSE:\n" .
            "{\n" .
            "    \"recipe_name\": \"Name\",\n" .
            "    \"description\": \"Description\",\n" .
            "    \"servings\": 1,\n" .
            "    \"preparation_time\": \"10 mins\",\n" .
            "    \"cooking_time\": \"20 mins\",\n" .
            "    \"ingredients\": [\"item1\", \"item2\"],\n" .
            "    \"method\": [\"step1\", \"step2\"],\n" .
            "    \"nutritional_information\": {\n" .
            "        \"calories\": 300 kCal,\n" .
            "        \"carbs\": 20g,\n" .
            "        \"net carbs\": 10g,\n" .
            "        \"protein\": 20g,\n" .
            "        \"fat\": 30g\n" .
            "    },\n" .
            "    \"wpai_dietary_tags\": [\"keto\", \"low-carb\"]\n" .
            "}";
    }

    private function get_default_dietary_options() {
        return [
            'vegetarian' => 'Vegetarian',
            'vegan' => 'Vegan',
            'gluten-free' => 'Gluten-Free',
            'keto' => 'Keto Diet',
            'diabetic' => 'Diabetic-Friendly',
            'nut-free' => 'Nut-Free',
            'atkins' => 'Atkins Diet',
        ];
    }

    public function get_prompt_template() {
        return get_option($this->prompt_option, $this->default_prompt);
    }

    public function get_dietary_options() {
        $custom_options = get_option($this->dietary_options_option, array());
        return array_merge($this->default_dietary_options, $custom_options);
    }

    public function add_dietary_option($key, $label) {
        $options = get_option($this->dietary_options_option, array());
        $sanitized_key = sanitize_key($key);
        $options[$sanitized_key] = sanitize_text_field($label);
        update_option($this->dietary_options_option, $options);
    }

    public function remove_dietary_option($key) {
        $options = get_option($this->dietary_options_option, array());
        if (isset($options[$key])) {
            unset($options[$key]);
            update_option($this->dietary_options_option, $options);
        }
    }

    public function update_prompt_template($new_prompt) {
        update_option($this->prompt_option, wp_kses_post($new_prompt));
    }

    public function reset_prompt_to_default() {
        delete_option($this->prompt_option);
    }

    public function reset_dietary_options_to_default() {
        delete_option($this->dietary_options_option);
    }

    public function generate_prompt($args) {
        $defaults = [
            'servings' => 2,
            'include_ingredients' => '',
            'exclude_ingredients' => '',
            'dietary' => [],
            'cuisine' => '',
            'skill_level' => 'beginner'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Convert dietary array to comma-separated string
        $dietary_string = !empty($args['dietary']) ? implode(', ', (array)$args['dietary']) : '';
        
        $placeholders = [
            '{servings}' => $args['servings'],
            '{include_ingredients}' => $args['include_ingredients'],
            '{exclude_ingredients}' => $args['exclude_ingredients'],
            '{dietary}' => $dietary_string, // Now a string
            '{cuisine}' => $args['cuisine'],
            '{skill_level}' => $args['skill_level']
        ];
        
        $prompt = $this->get_prompt_template();
        
        foreach ($placeholders as $placeholder => $replacement) {
            $prompt = str_replace($placeholder, $replacement, $prompt);
        }
        
        return $prompt;
    }
}