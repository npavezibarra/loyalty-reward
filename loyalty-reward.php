<?php
/*
Plugin Name: Loyalty Reward Manager
Description: Manage loyalty discounts and user search functionality.
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue scripts and styles
function loyalty_reward_enqueue_scripts() {
    wp_enqueue_script('jquery-ui-autocomplete');
    wp_enqueue_script('loyalty-search-script', plugin_dir_url(__FILE__) . 'assets/js/patreon-search.js', array('jquery', 'jquery-ui-autocomplete'), null, true);
    wp_localize_script('loyalty-search-script', 'patreonSearch', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
    wp_enqueue_style('jquery-ui-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
}
add_action('wp_enqueue_scripts', 'loyalty_reward_enqueue_scripts');

// Shortcode: Add user form
function loyalty_user_form_shortcode() {
    if (!current_user_can('manage_options')) {
        return '<div id="loyalty-user-form-wrapper" style="color: red;">No tienes permiso para acceder a este formulario.</div>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'patreon_users';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loyalty_user_submit'])) {
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $label = isset($_POST['label']) ? intval($_POST['label']) : 3;
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : 'P';

        if (is_email($email) && in_array($label, [1, 2, 3, 4]) && in_array($platform, ['P', 'F'])) {
            $result = $wpdb->replace(
                $table_name,
                [
                    'email' => $email,
                    'label' => $label,
                    'platform' => $platform,
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%d', '%s', '%s']
            );

            if ($result) {
                echo '<div style="color: green;">Usuario añadido o actualizado correctamente.</div>';
            } else {
                echo '<div style="color: red;">Error al añadir el usuario.</div>';
            }
        } else {
            echo '<div style="color: red;">Datos inválidos.</div>';
        }
    }

    ob_start();
    ?>
    <div id="loyalty-user-form-wrapper">
        <form method="POST">
            <label for="email">Correo Electrónico:</label><br>
            <input type="email" name="email" id="email" required><br><br>

            <label for="label">Label ID (1-4):</label><br>
            <select name="label" id="label" required>
                <option value="1">1 - 5%</option>
                <option value="2">2 - 10%</option>
                <option value="3" selected>3 - 20%</option>
                <option value="4">4 - 30%</option>
            </select><br><br>

            <label for="platform">Plataforma:</label><br>
            <select name="platform" id="platform" required>
                <option value="P" selected>Patreon (P)</option>
                <option value="F">Flow (F)</option>
            </select><br><br>

            <button type="submit" name="loyalty_user_submit">Añadir Usuario</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('loyalty_user_form', 'loyalty_user_form_shortcode');

// Shortcode: User search
function loyalty_user_search_shortcode() {
    ob_start();
    ?>
    <div id="patreon-search-container">
        <h2>Buscar usuario</h2>
        <form id="patreon-search-form">
            <label for="patreon-search-input">Buscar correo:</label>
            <input type="text" id="patreon-search-input" name="patreon-search-input" autocomplete="off" />
        </form>
        <div id="patreon-user-details"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('loyalty_user_search', 'loyalty_user_search_shortcode');

// AJAX: Autocomplete emails
function loyalty_user_search_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'patreon_users';

    $search_term = sanitize_text_field($_POST['term']);
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT email FROM $table_name WHERE email LIKE %s LIMIT 10",
            '%' . $wpdb->esc_like($search_term) . '%'
        )
    );

    $emails = array();
    foreach ($results as $result) {
        $emails[] = $result->email;
    }

    wp_send_json($emails);
}
add_action('wp_ajax_autocomplete_emails', 'loyalty_user_search_handler');
add_action('wp_ajax_nopriv_autocomplete_emails', 'loyalty_user_search_handler');

// AJAX: Get user info
function get_user_details_callback() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'patreon_users';

    $email = sanitize_email($_POST['email']);
    $user_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT email, label, platform, created_at FROM $table_name WHERE email = %s",
            $email
        )
    );

    if ($user_data) {
        $discount = match (intval($user_data->label)) {
            1 => '5%',
            2 => '10%',
            3 => '20%',
            4 => '30%',
            default => 'N/A',
        };

        $response = array(
            'email' => $user_data->email,
            'label' => $user_data->label,
            'discount' => $discount,
            'platform' => $user_data->platform,
            'created_at' => $user_data->created_at,
        );
    } else {
        $response = array('error' => 'Usuario no encontrado.');
    }

    wp_send_json($response);
}
add_action('wp_ajax_get_user_details', 'get_user_details_callback');
add_action('wp_ajax_nopriv_get_user_details', 'get_user_details_callback');

// Hook: Check or create table on plugin activation
function loyalty_reward_check_or_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'patreon_users';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            label TINYINT(4) NOT NULL DEFAULT 3,
            platform ENUM('P', 'F') NOT NULL DEFAULT 'P',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'loyalty_reward_check_or_create_table');
