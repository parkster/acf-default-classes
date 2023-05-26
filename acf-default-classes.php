<?php

/**
 * Plugin Name: ACF Default Classes
 * Plugin URI: https://gutensmith.com/
 * Description: This plugin adds a "Default Class List" setting to ACF fields.
 * Version: 1.0
 * Author: Gutensmith
 * Author URI: https://gutensmith.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-default-classes
 */

// Make sure this file is called from WordPress, not directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if ACF is active
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('advanced-custom-fields/acf.php') || is_plugin_active('advanced-custom-fields-pro/acf.php')) {


    function acf_default_classes_enqueue_block_editor_assets()
    {
        wp_enqueue_script(
            'acf-default-classes-editor',
            plugins_url('build/index.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-editor', 'wp-compose', 'wp-hooks', 'wp-i18n'),
            filemtime(plugin_dir_path(__FILE__) . 'build/index.js')
        );
    }
    add_action('enqueue_block_editor_assets', 'acf_default_classes_enqueue_block_editor_assets');

    function acf_default_classes_acf_render_field_group_settings($field_group)
    {
        $default_class_list = get_post_meta($field_group['ID'], 'default_class_list', true);
?>
        <div class="field-group-settings field-group-settings-tab" style="padding-top: 0;">
            <div class="acf-field acf-field-text" data-name="default_class_list" data-type="text" style="max-width: 600px;">
                <div class="acf-label">
                    <label for="default_class_list">Default Class List</label>
                </div>
                <div class="acf-input">
                    <div class="acf-input-wrap">
                        <input type="text" id="default_class_list" name="default_class_list" value="<?php echo esc_attr($default_class_list); ?>" />
                    </div>
                    <p class="description">Enter the default classes to be applied to each block on insert.</p>
                </div>
            </div>
        </div>
    <?php
    }
    add_action('acf/render_field_group_settings', 'acf_default_classes_acf_render_field_group_settings');

    add_action('rest_api_init', function () {
        register_rest_route('acf-default-classes/v1', '/default-class-list/', array(
            'methods' => 'GET',
            'callback' => 'acf_default_classes_get_default_class_list',
            'permission_callback' => function () {
                // Only allow access if user is logged in
                return is_user_logged_in();
            },
        ));
    });

    function acf_default_classes_get_default_class_list($data)
    {
        // return "works.";
        $name = $data['name'];

        // Here you can fetch the default class list based on the block name. 
        // Replace this with your actual logic.
        $default_class_list = get_default_class_list_based_on_name($name);

        // Create a new response object
        $response = new WP_REST_Response(array(
            'success'           => true,
            'defaultClassList'  => $default_class_list,
        ));

        // Set the status code for the response - 200 is OK
        $response->set_status(200);

        return $response;
    }

    function get_default_class_list_based_on_name($block_value)
    {
        // Set up a query to find the post with the specified block_value
        $args = array(
            'post_type'  => 'acf-field-group', // assuming the post type is 'acf-field-group'
            'meta_query' => array(
                array(
                    'key'     => 'block_value',
                    'value'   => $block_value,
                    'compare' => '=',
                ),
            ),
        );
        $query = new WP_Query($args);

        // return $query;

        // If a post was found, return the default_class_list from that post
        if ($query->have_posts()) {
            $query->the_post();
            $default_class_list = get_post_meta(get_the_ID(), 'default_class_list', true);
            return $default_class_list;
        } else {
            // If no post was found, return some default value
            return null;
        }
    }
    function is_valid_html_class($input)
    {
        // Class names can contain letters, numbers, hyphens, or underscores
        // They cannot start with a digit, two hyphens, or a hyphen followed by a digit
        $pattern = '/^[_a-zA-Z]+[_a-zA-Z0-9-]*$/';
        $classes = explode(" ", $input);

        foreach ($classes as $class) {
            if (!preg_match($pattern, $class)) {
                return false;
            }
        }

        return true;
    }


    function acf_default_classes_acf_update_field_group($field_group)
    {
        $block_value = '';

        foreach ($field_group['location'] as $rules) {
            foreach ($rules as $rule) {
                if ($rule['param'] === 'block') {
                    $block_value = $rule['value'];
                    break 2;
                }
            }
        }

        // Save block_value to postmeta
        update_post_meta($field_group['ID'], 'block_value', $block_value);

        // First check if $_POST contains 'default_class_list' and save it to postmeta
        if (isset($_POST['default_class_list'])) {
            $default_class_list = sanitize_text_field($_POST['default_class_list']);
            // Validate input before saving
            if (is_valid_html_class($default_class_list)) {
                // Manually save to postmeta
                update_post_meta($field_group['ID'], 'default_class_list', $default_class_list);
            } else {
                // Handle invalid input
                error_log('default_class_list contains invalid characters');
                // Set the error message
                set_transient('acf_default_classes_validation_error', 'Invalid class list', 45);
            }
        } else {
            error_log('default_class_list not found in $_POST');
        }



        return $field_group;
    }
    add_filter('acf/update_field_group', 'acf_default_classes_acf_update_field_group', 20);  // Note the added priority of 20 to ensure this runs after ACF's internal save function

    function acf_default_classes_admin_notice()
    {
        if ($error = get_transient('acf_default_classes_validation_error')) {
            echo "<div class='notice notice-error is-dismissible'><p>$error</p></div>";
            delete_transient('acf_default_classes_validation_error');
        }
    }
    add_action('admin_notices', 'acf_default_classes_admin_notice');

    function acf_default_classes_acf_render_field_settings($field) {
        if($field['type'] === 'radio') {
            acf_render_field_setting($field, array(
                'label'        => 'Add value to class names',
                'instructions' => 'When the user selects one of the options in the radio button group, the value is added to the class name list.',
                'type'         => 'true_false',
                'name'         => 'add_value_to_class_names',
            ));
        }
    }
    add_filter('acf/render_field_settings', 'acf_default_classes_acf_render_field_settings');
    
    function acf_default_classes_acf_update_field($field) {
        if(isset($_POST['acf_fields'][$field['key']]['add_value_to_class_names'])) {
            $add_value_to_class_names = sanitize_text_field($_POST['acf_fields'][$field['key']]['add_value_to_class_names']);
            update_post_meta($field['ID'], 'add_value_to_class_names', $add_value_to_class_names);
        }
    
        return $field;
    }
    add_filter('acf/update_field', 'acf_default_classes_acf_update_field', 20);
    
    add_action('init', 'slug_register_meta');
    function slug_register_meta()
    {
        register_meta('post', 'default_class_list', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ));
        register_meta('post', 'add_value_to_class_names', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
        ));
    }
} else {
    // Display an admin notice if ACF is not active
    function acf_default_classes_admin_notice()
    {
    ?>
        <div class="notice notice-error">
            <p><?php _e('ACF Default Classes requires Advanced Custom Fields plugin to be installed and activated.', 'acf-default-classes'); ?></p>
        </div>
<?php
    }
    add_action('admin_notices', 'acf_default_classes_admin_notice');
}
