<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/*
Plugin Name: GitHub Cards Carousel
Description: Muestra múltiples tarjetas de GitHub en un carrusel usando Swiper.js
Version: 1.2
Author: Sharlye Navarro 
*/

// Registrar scripts y estilos
function github_cards_log_error($message) {
    $log_file = WP_CONTENT_DIR . '/github_cards_error.log';
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $log_file);
}

// Usa esta función en puntos críticos de tu plugin, por ejemplo:
github_cards_log_error('Plugin inicializado');

function github_cards_activation() {
    add_option('github_cards_activated', true);
}
register_activation_hook(__FILE__, 'github_cards_activation');

function github_cards_admin_notice() {
    if (get_option('github_cards_activated', false)) {
        echo '<div class="notice notice-success is-dismissible"><p>GitHub Cards Carousel se ha activado correctamente.</p></div>';
        delete_option('github_cards_activated');
    }
}
add_action('admin_notices', 'github_cards_admin_notice');

function github_cards_enqueue_scripts() {
    wp_enqueue_style('swiper-style', 'https://unpkg.com/swiper/swiper-bundle.min.css');
    wp_enqueue_style('github-card-style', plugins_url('github-card-style.css', __FILE__));
    wp_enqueue_script('swiper-script', 'https://unpkg.com/swiper/swiper-bundle.min.js', array(), null, true);
    wp_enqueue_script('github-card-script', plugins_url('github-card-script.js', __FILE__), array('jquery', 'swiper-script'), '2.0', true);
    wp_localize_script('github-card-script', 'github_card_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('github_card_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'github_cards_enqueue_scripts');

// Función para manejar la solicitud AJAX
function github_cards_fetch_repo_info() {
    check_ajax_referer('github_card_nonce', 'nonce');
    
    $username = sanitize_text_field($_POST['username']);
    $repo = sanitize_text_field($_POST['repo']);
    $api_key = get_option('github_cards_api_key', '');
    
    $api_url = "https://api.github.com/repos/{$username}/{$repo}";
    $args = array(
        'headers' => array(
            'Authorization' => 'token ' . $api_key,
            'User-Agent' => 'WordPress/GitHub-Cards-Plugin'
        )
    );
    
    $response = wp_remote_get($api_url, $args);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Error al obtener la información del repositorio');
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    wp_send_json_success($data);
}
add_action('wp_ajax_github_cards_fetch_repo_info', 'github_cards_fetch_repo_info');
add_action('wp_ajax_nopriv_github_cards_fetch_repo_info', 'github_cards_fetch_repo_info');

// Función para el shortcode
function github_cards_shortcode($atts) {
    $atts = shortcode_atts(array(
        'repos' => '',
    ), $atts, 'github_cards');

    $repos = explode(',', $atts['repos']);
    $slides = '';

    foreach ($repos as $repo) {
        $repo_data = explode('/', trim($repo));
        if (count($repo_data) === 2) {
            $username = $repo_data[0];
            $repo_name = $repo_data[1];
            $slides .= "
            <div class='swiper-slide'>
                <div class='github-card' data-username='{$username}' data-repo='{$repo_name}'>
                    <h4 class='repo-name'>Cargando...</h4>
                    <p class='repo-description'></p>
                    <div class='repo-languages'></div>
                    <p class='repo-updated'></p>
                    <a href='#' class='view-on-github' target='_blank'>Ver en GitHub</a>
                </div>
            </div>";
        }
    }

    $output = "
    <div class='github-cards-carousel swiper-container'>
        <div class='swiper-wrapper'>
            {$slides}
        </div>
        <div class='swiper-pagination'></div>
        <div class='swiper-button-next'></div>
        <div class='swiper-button-prev'></div>
    </div>";

    return $output;
}
add_shortcode('github_cards', 'github_cards_shortcode');

// Agregar página de opciones en el panel de administración
function github_cards_add_admin_menu() {
    add_options_page('GitHub Cards Settings', 'GitHub Cards', 'manage_options', 'github_cards', 'github_cards_options_page');
}
add_action('admin_menu', 'github_cards_add_admin_menu');

function github_cards_options_page() {
    ?>
    <div class="wrap">
        <h1>GitHub Cards Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('github_cards_options');
            do_settings_sections('github_cards_options');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function github_cards_settings_init() {
    register_setting('github_cards_options', 'github_cards_api_key');
    add_settings_section('github_cards_section', 'API Settings', 'github_cards_section_callback', 'github_cards_options');
    add_settings_field('github_cards_api_key', 'GitHub API Key', 'github_cards_api_key_render', 'github_cards_options', 'github_cards_section');
}
add_action('admin_init', 'github_cards_settings_init');

function github_cards_section_callback() {
    echo 'Enter your GitHub API key below:';
}

function github_cards_api_key_render() {
    $api_key = get_option('github_cards_api_key');
    echo "<input type='text' name='github_cards_api_key' value='{$api_key}' />";
}

function github_cards_check_for_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'github-cards-carousel/github-cards-carousel.php';
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_slug);
    $current_version = $plugin_data['Version'];
    
    // Obtén la información de la versión más reciente de tu servidor
    $remote_version_info = wp_remote_get('https://sharnnabis.com/wp-content/plugins/github-cards-carousel/version-info.json');
    
    if (is_wp_error($remote_version_info)) {
        return $transient; // Si hay un error, no hacemos nada
    }

    $remote_version_data = json_decode(wp_remote_retrieve_body($remote_version_info), true);
    
    if ($remote_version_data && isset($remote_version_data['version'])) {
        $remote_version = $remote_version_data['version'];
        
        if (version_compare($current_version, $remote_version, '<')) {
            $obj = new stdClass();
            $obj->slug = 'github-cards-carousel';
            $obj->new_version = $remote_version;
            $obj->url = 'https://sharnnabis.com/plugin-info/';
            $obj->package = 'https://sharnnabis.com/downloads/github-cards-carousel.zip';
            $transient->response[$plugin_slug] = $obj;
        }
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'github_cards_check_for_updates');