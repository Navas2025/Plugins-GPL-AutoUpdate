<?php
/**
 * Rank Math SEO PRO GPL Plugin.
 *
 * @package      RANK_MATH
 * @copyright    Copyright (C) 2018-2020, Rank Math - support@rankmath.com
 * @link         https://rankmath.com
 * @since        2.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Rank Math SEO PRO GPL
 * Version:           3.0.106
 * Plugin URI:        https://rankmath.com/wordpress/plugin/seo-suite/
 * Description:       Super-charge your website's SEO with the Rank Math PRO options. Sistema GPL con actualizaciones por API Key.
 * Author:            Rank Math SEO (Modificado con Sistema GPL)
 * Author URI:        https://rankmath.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rank-math-pro
 * Domain Path:       /languages
 */

use RankMath\Helper;

defined('ABSPATH') || exit;

// ========== CONFIGURACIÓN GPL ==========

if (!defined('RANK_MATH_PRO_GPL_UPDATE_SERVER')) {
    define('RANK_MATH_PRO_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/');
}

if (!defined('RANK_MATH_PRO_GPL_API_KEY')) {
    define('RANK_MATH_PRO_GPL_API_KEY', get_option('rank_math_pro_gpl_api_key', ''));
}

// ========== BYPASS DE LICENCIA RANK MATH ==========

add_filter('rank_math/admin/sensitive_data_encryption', '__return_false');

update_option('rank_math_connect_data', [
    'username'  => 'rankmath',
    'email'     => 'rankmath@weadown.com',
    'api_key'   => '*********',
    'plan'      => 'business',
    'connected' => true,
]);
update_option('rank_math_registration_skip', 1);

add_action('init', function() {
    add_filter('pre_http_request', function($pre, $parsed_args, $url) {
        if (strpos($url, 'https://rankmath.com/wp-json/rankmath/v1/') !== false) {
            $basename = basename(parse_url($url, PHP_URL_PATH));
            if ($basename == 'siteSettings') {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'error' => '',
                        'plan'  => 'business',
                        'keywords' => get_option('rank_math_keyword_quota', ['available' => 10000, 'taken' => 0]),
                        'analytics' => 'on',
                    ]),
                ];
            } elseif ($basename == 'keywordsInfo') {
                if (isset($parsed_args['body']['count'])) {
                    return [
                        'response' => ['code' => 200, 'message' => 'OK'],
                        'body'     => json_encode(['available' => 10000, 'taken' => $parsed_args['body']['count']]),
                    ];
                }
            }
            return ['response' => ['code' => 200, 'message' => 'OK']];
        }
        return $pre;
    }, 10, 3);
});

// ========== CARGAR INTERFAZ GPL ==========

if (is_admin()) {
    $includes_dir = __DIR__ . '/includes/';
    if (file_exists($includes_dir . 'admin-license.php')) {
        require_once $includes_dir . 'admin-license.php';
    }
    if (file_exists($includes_dir . 'ajax-license.php')) {
        require_once $includes_dir . 'ajax-license.php';
    }
    if (file_exists($includes_dir . 'class-update-manager.php')) {
        require_once $includes_dir . 'class-update-manager.php';
    }
}

// ========== CLASE PRINCIPAL ==========

final class RankMathPro {

    public $version = '3.0.106';
    public $rank_math_min_version = '1.0.263';
    private $container = [];
    private $messages = [];
    private $free_version_plugin_path = 'seo-by-rank-math/rank-math.php';
    protected static $instance = null;

    public static function get() {
        if (is_null(self::$instance) && !(self::$instance instanceof RankMathPro)) {
            self::$instance = new RankMathPro();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!$this->are_requirements_met()) {
            return;
        }

        $this->define_constants();
        $this->includes();
        new \RankMathPro\Installer();

        add_action('after_setup_theme', [$this, 'localization_setup'], 1);
        add_action('rank_math/loaded', [$this, 'setup']);
        add_filter('rank_math/license/activate_url', [$this, 'add_query_arg']);
    }

    public function setup() {
        if (!$this->is_free_version_compatible()) {
            $this->messages[] = esc_html__('Please update Rank Math Free to the latest version first before using Rank Math PRO.', 'rank-math-pro');
            add_action('admin_notices', [$this, 'activation_error']);
            return false;
        }

        $this->instantiate();
        $this->init_actions();
        do_action('rank_math_pro/loaded');
    }

    private function are_requirements_met() {
        $dont_load = false;
        if ($this->is_free_version_being_deactivated()) {
            $this->messages[] = esc_html__('Rank Math free version is required to run Rank Math PRO. Both plugins are now disabled.', 'rank-math-pro');
        } elseif ($this->is_free_version_being_rolled_back() || $this->is_free_version_being_updated() || $this->is_troubleshooting()) {
            $dont_load = true;
        } else {
            if (!$this->is_free_version_installed()) {
                if (!$this->install_free_version()) {
                    $this->messages[] = esc_html__('Rank Math free version is required to run Rank Math PRO, but it could not be installed automatically. Please install and activate the free version first.', 'rank-math-pro');
                }
            }

            if (!$this->is_free_version_activated()) {
                if (!$this->activate_free_version()) {
                    $this->messages[] = esc_html__('Rank Math free version is required to run Rank Math PRO, but it could not be activated automatically. Please install and activate the free version first.', 'rank-math-pro');
                }
            }
        }

        if ($dont_load) {
            return false;
        }

        if (empty($this->messages)) {
            return true;
        }

        add_action('admin_init', [$this, 'auto_deactivate']);
        add_action('admin_notices', [$this, 'activation_error']);
        return false;
    }

    public function is_troubleshooting() {
        return (bool) get_option('health-check-allowed-plugins') && !$this->is_free_version_activated();
    }

    public function is_free_version_being_rolled_back() {
        $reactivating = isset($_GET['action'])
            && 'activate-plugin' === $_GET['action']
            && isset($_GET['plugin'])
            && 'seo-by-rank-math/rank-math.php' === $_GET['plugin'];

        return $reactivating || (function_exists('rank_math') && rank_math()->version !== get_option('rank_math_version'));
    }

    public function auto_deactivate() {
        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }

    public function activation_error() {
        ?>
        <div class="rank-math-notice notice notice-error">
            <p>
                <?php echo join('<br>', $this->messages); ?>
            </p>
        </div>
        <?php
    }

    private function define_constants() {
        define('RANK_MATH_PRO_VERSION', $this->version);
        define('RANK_MATH_PRO_FILE', __FILE__);
        define('RANK_MATH_PRO_PATH', dirname(RANK_MATH_PRO_FILE) . '/');
        define('RANK_MATH_PRO_URL', plugins_url('', RANK_MATH_PRO_FILE) . '/');
    }

    private function includes() {
        include __DIR__ . '/vendor/autoload.php';

        $licence_file = __DIR__ . '/licence-data.php';
        if (file_exists($licence_file)) {
            include $licence_file;
        }
    }

    private function instantiate() {
        new \RankMathPro\Modules();
        $this->load_3rd_party();
    }

    private function load_3rd_party() {
        if (defined('ELEMENTOR_VERSION')) {
            new \RankMathPro\Elementor\Elementor();
        }

        if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
            new \RankMathPro\ThirdParty\WPML();
        }

        add_action(
            'after_setup_theme',
            function () {
                if (defined('ET_CORE')) {
                    new \RankMathPro\Divi\Divi();
                }
            },
            11
        );

        if (class_exists('\\WPMedia\\PluginFamily\\Controller\\PluginFamily')) {
            new \RankMathPro\ThirdParty\Plugin_Family();
        }
    }

    private function init_actions() {
        if (is_admin()) {
            add_action('rank_math/admin/loaded', [$this, 'init_admin'], 15);
        }

        add_action('rest_api_init', [$this, 'init_rest_api']);
        add_action('after_setup_theme', [$this, 'init'], 11);
        new \RankMathPro\Common();
        new \RankMathPro\Setup_Wizard();
        new \RankMathPro\Register_Vars();
    }

    public function init_admin() {
        new \RankMathPro\Admin\Admin();
    }

    public function init_rest_api() {
        $controllers = [
            new \RankMathPro\Schema\Rest(),
            new \RankMathPro\Analytics\Rest(),
            new \RankMathPro\Rest\Rest(),
        ];

        foreach ($controllers as $controller) {
            $controller->register_routes();
        }
    }

    public function init() {
        if (is_super_admin()) {
            new \RankMathPro\Robots_Txt();
        }

        if (Helper::is_module_active('image-seo')) {
            new \RankMathPro\Image_Seo_Pro();
        }

        if (Helper::is_module_active('bbpress')) {
            new \RankMathPro\BBPress();
        }

        if (Helper::is_module_active('local-seo', false)) {
            new \RankMathPro\Local_Seo\Local_Seo();
        }

        if (Helper::is_module_active('analytics')) {
            new \RankMathPro\Analytics\Analytics();
        }

        if (Helper::is_woocommerce_active() && Helper::is_module_active('woocommerce')) {
            new \RankMathPro\WooCommerce();
        }

        if (Helper::is_module_active('404-monitor')) {
            new \RankMathPro\Monitor_Pro();
        }

        if (Helper::is_module_active('redirections')) {
            new \RankMathPro\Redirections\Redirections();
        }

        if (Helper::is_module_active('seo-analysis')) {
            new \RankMathPro\SEO_Analysis\SEO_Analysis_Pro();
        }

        if (function_exists('acf') && Helper::is_module_active('acf')) {
            new \RankMathPro\ACF\ACF();
        }

        if (Helper::is_module_active('content-ai')) {
            new \RankMathPro\Content_AI();
        }

        new \RankMathPro\Status\System_Status();
        new \RankMathPro\Plugin_Update\Plugin_Update();
        new \RankMathPro\Thumbnail_Overlays();
    }

    public function localization_setup() {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'rank-math-pro');

        unload_textdomain('rank-math-pro');
        if (false === load_textdomain('rank-math-pro', WP_LANG_DIR . '/plugins/seo-by-rank-math-pro-' . $locale . '.mo')) {
            load_textdomain('rank-math-pro', WP_LANG_DIR . '/seo-by-rank-math/seo-by-rank-math-pro-' . $locale . '.mo');
        }

        load_plugin_textdomain('rank-math-pro', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function is_free_version_installed() {
        if ($this->is_free_version_activated()) {
            return true;
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();

        return array_key_exists($this->free_version_plugin_path, $installed_plugins);
    }

    public function install_free_version() {
        include_once ABSPATH . 'wp-includes/pluggable.php';
        include_once ABSPATH . 'wp-admin/includes/misc.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        $skin        = new Automatic_Upgrader_Skin();
        $upgrader    = new Plugin_Upgrader($skin);
        $plugin_file = 'https://downloads.wordpress.org/plugin/seo-by-rank-math.latest-stable.zip';
        $result      = $upgrader->install($plugin_file);

        return $result;
    }

    public function is_free_version_activated() {
        $active_plugins = get_option('active_plugins', []);
        return in_array($this->free_version_plugin_path, $active_plugins, true);
    }

    public function is_free_version_being_updated() {
        $action  = isset($_POST['action']) && $_POST['action'] !== -1 ? sanitize_text_field($_POST['action']) : '';
        $plugins = isset($_POST['plugin']) && is_array($_POST['plugin']) ? array_map('sanitize_text_field', $_POST['plugin']) : '';
        if (empty($plugins)) {
            $plugins = isset($_POST['plugins']) && is_array($_POST['plugins']) ? array_map('sanitize_text_field', $_POST['plugins']) : [];
        }

        $update_plugin   = 'update-plugin';
        $update_selected = 'update-selected';
        $actions         = [$update_plugin, $update_selected];

        if (!in_array($action, $actions, true)) {
            return false;
        }

        return in_array($this->free_version_plugin_path, $plugins, true);
    }

    public function is_free_version_being_deactivated() {
        if (!is_admin()) {
            return false;
        }

        $action = isset($_REQUEST['action']) && $_REQUEST['action'] !== -1 ? sanitize_text_field($_REQUEST['action']) : '';
        if (!$action) {
            $action = isset($_REQUEST['action2']) && $_REQUEST['action2'] !== -1 ? sanitize_text_field($_REQUEST['action2']) : '';
        }
        $plugin  = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
        $checked = isset($_POST['checked']) && is_array($_POST['checked']) ? array_map('sanitize_text_field', $_POST['checked']) : [];

        $deactivate          = 'deactivate';
        $deactivate_selected = 'deactivate-selected';
        $actions             = [$deactivate, $deactivate_selected];

        if (!in_array($action, $actions, true)) {
            return false;
        }

        if ($action === $deactivate && $plugin !== $this->free_version_plugin_path) {
            return false;
        }

        if ($action === $deactivate_selected && !in_array($this->free_version_plugin_path, $checked, true)) {
            return false;
        }

        return true;
    }

    public function activate_free_version() {
        return activate_plugin($this->free_version_plugin_path);
    }

    public function is_free_version_compatible() {
        return defined('RANK_MATH_VERSION') && version_compare(RANK_MATH_VERSION, $this->rank_math_min_version, '>=');
    }

    public function add_query_arg($url) {
        return add_query_arg(['pro' => 1], $url);
    }
}

function rank_math_pro() {
    return RankMathPro::get();
}

rank_math_pro();
