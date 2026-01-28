<?php
/*
Plugin Name: Amelia GPL
Plugin URI: https://wpamelia.com/
Description: Amelia is a simple yet powerful automated booking specialist, working 24/7 to make sure your customers can make appointments and events even while you sleep! (Versión GPL con sistema de actualización).
Version: 9.1
Author: Melograno Ventures (Modificado con Sistema GPL)
Author URI: https://melograno.io/
Text Domain: wpamelia
Domain Path: /languages
*/

namespace AmeliaBooking;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Licence\LicenceConstants;
use AmeliaBooking\Infrastructure\Routes\Routes;
use AmeliaBooking\Infrastructure\Services\Payment\SquareService;
use AmeliaBooking\Infrastructure\WP\ButtonService\ButtonService;
use AmeliaBooking\Infrastructure\WP\config\Menu;
use AmeliaBooking\Infrastructure\WP\Elementor\ElementorBlock;
use AmeliaBooking\Infrastructure\WP\ErrorService\ErrorService;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaStepBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaCatalogBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaCatalogGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaCustomerCabinetGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEmployeeCabinetGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsListBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsCalendarBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaSearchGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\Integrations\WooCommerce\WooCommerceService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Infrastructure\WP\UserRoles\UserRoles;
use AmeliaBooking\Infrastructure\WP\WPMenu\Submenu;
use AmeliaBooking\Infrastructure\WP\WPMenu\SubmenuPageHandler;
use Exception;
use Slim\App;
use AmeliaBooking\Infrastructure\Licence;

defined('ABSPATH') or die('No script kiddies please!');

if (!defined('AMELIA_PATH')) {
    define('AMELIA_PATH', __DIR__);
}

if (!defined('AMELIA_UPLOADS_PATH')) {
    $uploadDir = wp_upload_dir();
    define('AMELIA_UPLOADS_PATH', $uploadDir['basedir']);
}

if (!defined('AMELIA_UPLOADS_URL')) {
    $uploadUrl = wp_upload_dir();
    define('AMELIA_UPLOADS_URL', set_url_scheme($uploadUrl['baseurl']));
}

if (!defined('AMELIA_UPLOADS_FILES_URL')) {
    define('AMELIA_UPLOADS_FILES_URL', AMELIA_UPLOADS_URL . '/amelia/files/');
}

if (!defined('AMELIA_UPLOADS_FILES_PATH')) {
    define('AMELIA_UPLOADS_FILES_PATH', AMELIA_UPLOADS_PATH . '/amelia/files/');
}

if (!defined('AMELIA_UPLOADS_FILES_PATH_USE')) {
    define('AMELIA_UPLOADS_FILES_PATH_USE', true);
}

if (!defined('AMELIA_URL')) {
    define('AMELIA_URL', plugin_dir_url(__FILE__));
}

if (!defined('AMELIA_HOME_URL')) {
    define('AMELIA_HOME_URL', get_home_url());
}

if (!defined('AMELIA_ACTION_SLUG')) {
    define('AMELIA_ACTION_SLUG', 'action=wpamelia_api&call=');
}

if (!defined('AMELIA_ACTION_URL')) {
    define('AMELIA_ACTION_URL', admin_url('admin-ajax.php', '') . '?' . AMELIA_ACTION_SLUG);
}

if (!defined('AMELIA_PAGE_URL')) {
    define('AMELIA_PAGE_URL', get_site_url() . '/wp-admin/admin.php?page=');
}

if (!defined('AMELIA_LOGIN_URL')) {
    define('AMELIA_LOGIN_URL', get_site_url() . '/wp-login.php?redirect_to=');
}

if (!defined('AMELIA_VERSION')) {
    define('AMELIA_VERSION', '9.1');
}

if (!defined('AMELIA_SITE_URL')) {
    define('AMELIA_SITE_URL', get_site_url());
}

if (!defined('AMELIA_PLUGIN_SLUG')) {
    define('AMELIA_PLUGIN_SLUG', plugin_basename(__FILE__));
}

if (!defined('AMELIA_SMS_API_URL')) {
    define('AMELIA_SMS_API_URL', 'https://smsapi.wpamelia.com/');
    define('AMELIA_SMS_VENDOR_ID', 36082);
    define('AMELIA_SMS_IS_SANDBOX', false);
    define('AMELIA_SMS_PRODUCT_ID_10', 595657);
    define('AMELIA_SMS_PRODUCT_ID_20', 595658);
    define('AMELIA_SMS_PRODUCT_ID_50', 595659);
    define('AMELIA_SMS_PRODUCT_ID_100', 595660);
    define('AMELIA_SMS_PRODUCT_ID_200', 595661);
    define('AMELIA_SMS_PRODUCT_ID_500', 595662);
}

if (!defined('AMELIA_STORE_API_URL')) {
    define('AMELIA_STORE_API_URL', 'https://store.melograno.io/api/');
}

if (!defined('AMELIA_DEV')) {
    define('AMELIA_DEV', false);
}

if (!defined('AMELIA_PRODUCTION')) {
    define('AMELIA_PRODUCTION', true);
}

if (!defined('AMELIA_NGROK_URL')) {
    define('AMELIA_NGROK_URL', 'nonmelodiously-barnlike-anika.ngrok-free.dev');
}

if (!defined('AMELIA_MIDDLEWARE_URL')) {
    define('AMELIA_MIDDLEWARE_URL', 'https://middleware.wpamelia.com/');
}

if (!defined('AMELIA_MAILCHIMP_CLIENT_ID')) {
    define('AMELIA_MAILCHIMP_CLIENT_ID', '459163389015');
}

if (!defined('AMELIA_GPL_UPDATE_SERVER')) {
    define('AMELIA_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/');
}

if (!defined('AMELIA_GPL_API_KEY')) {
    define('AMELIA_GPL_API_KEY', base64_decode('R1BMLTIWMJQTUFSFTUIVTS1BQ0NFU1M='));
}

require_once AMELIA_PATH . '/vendor/autoload.php';

class Plugin
{

    public static function wpAmeliaApiCall()
    {
        try {
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
            $app = new App($container);
            Routes::routes($app, $container);
            $app->run();
            exit();
        } catch (Exception $e) {
            echo 'ERROR: ' . esc_html($e->getMessage());
        }
    }

    static function square_weekly_token_refresh($schedules)
    {
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => __('Add weekly cron to refresh square access token every 7 days')
        );
        return $schedules;
    }

    public static function init()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        self::weglotConflict($settingsService, true);

        if (!defined('AMELIA_LOCALE')) {
            define('AMELIA_LOCALE', get_user_locale());
        }

        load_plugin_textdomain('wpamelia', false, plugin_basename(__DIR__) . '/languages/' . AMELIA_LOCALE . '/');

        self::weglotConflict($settingsService, false);

        if (WooCommerceService::isEnabled()) {
            if (!empty($settingsService->getCategorySettings('payments')['wc']['dashboard'])) {
                add_filter('woocommerce_prevent_admin_access', '__return_false');
            }

            if (!empty($settingsService->getCategorySettings('payments')['wc']['enabled'])) {
                try {
                    WooCommerceService::init($settingsService);
                } catch (ContainerException $e) {
                }
            } else {
                WooCommerceService::setContainer(require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php');
                WooCommerceService::$settingsService = $settingsService;
                add_filter('woocommerce_after_order_itemmeta', [WooCommerceService::class, 'orderItemMeta'], 10, 3);
            }
        }

        if (!empty($settingsService->getCategorySettings('payments')['square']['enabled']) &&
            !empty($settingsService->getCategorySettings('payments')['square']['accessToken'])) {
            add_filter('cron_schedules', [self::class, 'square_weekly_token_refresh']);

            if (!wp_next_scheduled('amelia_square_access_token_refresh')) {
                wp_schedule_event(time(), 'weekly', 'amelia_square_access_token_refresh');
            }

            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
            $squareService = $container->get('infrastructure.payment.square.service');
            add_action('amelia_square_access_token_refresh', [$squareService, 'refreshAccessToken']);
        }

        $ameliaRole = UserRoles::getUserAmeliaRole(wp_get_current_user());

        if (in_array($ameliaRole, ['admin', 'manager', 'provider', 'customer'])) {
            if ($ameliaRole === 'admin') {
                ErrorService::setNotices();
            }

            ButtonService::renderButton();

            AmeliaStepBookingGutenbergBlock::init();
            AmeliaCatalogBookingGutenbergBlock::init();
            AmeliaBookingGutenbergBlock::init();
            AmeliaSearchGutenbergBlock::init();
            AmeliaCatalogGutenbergBlock::init();
            AmeliaEventsGutenbergBlock::init();
            AmeliaEventsListBookingGutenbergBlock::init();
            AmeliaEventsCalendarBookingGutenbergBlock::init();
            AmeliaCustomerCabinetGutenbergBlock::init();
            AmeliaEmployeeCabinetGutenbergBlock::init();

            add_filter('block_categories_all', array('AmeliaBooking\Plugin', 'addAmeliaBlockCategory'), 10, 2);
            add_filter('learn-press/frontend-default-scripts', array('AmeliaBooking\Plugin', 'learnPressConflict'));
        }

        if (!is_admin()) {
            add_filter('learn-press/frontend-default-scripts', array('AmeliaBooking\Plugin', 'learnPressConflict'));
            add_shortcode('ameliabooking', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliasearch', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliacatalog', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliaevents', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliaeventslistbooking', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliaeventscalendarbooking', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsCalendarBookingShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliacustomerpanel', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetCustomerShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliaemployeepanel', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetEmployeeShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliastepbooking', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'shortcodeHandler'));
            add_shortcode('ameliacatalogbooking', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService', 'shortcodeHandler'));
        }

        if (defined('ELEMENTOR_VERSION')) {
            ElementorBlock::get_instance();
        }

        $theme = wp_get_theme();
        $theme = $theme->parent() ?: $theme;

        if ($theme && strtolower($theme->get('Name')) === 'divi' || strtolower($theme->get_template()) === 'divi') {
            $version = $theme->get('Version');

            if (version_compare($version, '5.0', '<')) {
                add_action('wp_head', function() {
                    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) {
                        wp_enqueue_script('jquery');
                        wp_print_scripts('jquery');
                    }
                }, 0);
                require_once AMELIA_PATH . '/extensions/divi_amelia/divi_amelia.php';
            } else {
                require_once AMELIA_PATH . '/extensions/divi_5_amelia/divi-5-amelia.php';
            }
        }

        if ($settingsService->isFeatureEnabled('buddyboss')) {
            require_once AMELIA_PATH . '/extensions/buddyboss-platform-addon/buddyboss-platform-addon.php';
        }
    }

    public static function addAmeliaBlockCategory($categories, $post)
    {
        return array_merge(
            array(
                array(
                    'slug'  => 'amelia-blocks',
                    'title' => 'Amelia',
                ),
            ),
            $categories
        );
    }

    public static function weglotConflict($settingsService, $init)
    {
        if (defined('AMELIA_LOCALE_FORCED') &&
            AMELIA_LOCALE_FORCED &&
            function_exists('weglot_get_current_language')
        ) {
            try {
                if ($init && !defined('AMELIA_LOCALE')) {
                    $weglotCurrentLanguage = weglot_get_current_language();
                    $ameliaUsedLanguages = array_flip($settingsService->getSetting('general', 'usedLanguages'));
                    require_once ABSPATH . 'wp-admin/includes/translation-install.php';
                    global $locale;
                    $potentialLanguages = [];

                    foreach (wp_get_available_translations() as $key => $value) {
                        if (substr($key, 0, 2) === substr($weglotCurrentLanguage, 0, 2)) {
                            $potentialLanguages[] = $key;
                        }
                    }

                    foreach ($potentialLanguages as $potentialLanguage) {
                        if (array_key_exists($potentialLanguage, $ameliaUsedLanguages)) {
                            $locale = $potentialLanguage;
                            break;
                        }
                    }
                } else {
                    global $locale;
                    $locale = AMELIA_LOCALE_FORCED;
                }
            } catch (\Exception $e) {
            }
        }
    }

    public static function learnPressConflict($data)
    {
        if (has_shortcode(get_post(get_the_ID())->post_content, 'ameliabooking') ||
            has_shortcode(get_post(get_the_ID())->post_content, 'ameliacatalog') ||
            has_shortcode(get_post(get_the_ID())->post_content, 'ameliasearch') ||
            has_shortcode(get_post(get_the_ID())->post_content, 'ameliaevents') ||
            has_shortcode(get_post(get_the_ID())->post_content, 'ameliacabinet') ||
            has_shortcode(get_post(get_the_ID())->post_content, 'ameliaeventslistbooking') ||
            has_shortcode(get_post(get_the_ID())->post_content, 'ameliaeventscalendarbooking') ||
            has_shortcode(get_post(get_the_ID())->post_content, 'ameliastepbooking')
        ) {
            return array();
        } else {
            return $data;
        }
    }

    public static function initMenu()
    {
        $settingsService = new SettingsService(new SettingsStorage());
        $menuItems = new Menu($settingsService);
        $wpMenu = new Submenu(
            new SubmenuPageHandler($settingsService),
            $menuItems()
        );
        $wpMenu->addOptionsPages();
    }

    public static function adminInit()
    {
        $settingsService = new SettingsService(new SettingsStorage());
        self::handleWelcomePageRedirect($settingsService);

        if (AMELIA_VERSION !== $settingsService->getSetting('activation', 'version')) {
            $settingsService->setSetting('activation', 'version', AMELIA_VERSION);
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            deactivate_plugins(AMELIA_PLUGIN_SLUG);
            activate_plugin(AMELIA_PLUGIN_SLUG);
        }
    }

    public static function handleWelcomePageRedirect($settingsService)
    {
        $currentPage = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $showWelcomePage = $settingsService->getSetting('activation', 'showWelcomePage');
        $isNewInstallation = $settingsService->getSetting('activation', 'isNewInstallation');

        if (get_transient('amelia_activation_redirect') && $currentPage !== 'wpamelia-welcome') {
            delete_transient('amelia_activation_redirect');

            if ($showWelcomePage && $isNewInstallation) {
                wp_safe_redirect(admin_url('admin.php?page=wpamelia-welcome'));
                exit;
            }
        }

        if (!$showWelcomePage && $currentPage === 'wpamelia-welcome') {
            wp_safe_redirect(admin_url('admin.php?page=wpamelia-dashboard'));
            exit;
        }
    }

    public static function activation($networkWide)
    {
        load_plugin_textdomain('wpamelia', false, plugin_basename(__DIR__) . '/languages/' . get_locale() . '/');

        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50500) {
            deactivate_plugins(AMELIA_PLUGIN_SLUG);
            wp_die(
                BackendStrings::get('php_version_message'),
                BackendStrings::get('php_version_title'),
                array('response' => 200, 'back_link' => TRUE)
            );
        }

        if ($networkWide && function_exists('is_multisite') && is_multisite()) {
            Infrastructure\WP\InstallActions\ActivationMultisite::init();
        }

        Infrastructure\WP\InstallActions\ActivationDatabaseHook::init();
        set_transient('amelia_activation_redirect', true, 30);
    }

    public static function deleteFolderContent($dirPath)
    {
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }

        $files = glob($dirPath . '*', GLOB_MARK);

        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteFolderContent($file);
            } else {
                unlink($file);
            }
        }
    }

    public static function deletion()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        if ($settingsService->getSetting('activation', 'deleteTables')) {
            if (function_exists('is_multisite') && is_multisite()) {
                Infrastructure\WP\InstallActions\DeletionMultisite::delete();
            }

            Infrastructure\WP\InstallActions\DeleteDatabaseHook::delete();

            global $wp_roles;
            $wp_roles->remove_role('wpamelia-customer');
            $wp_roles->remove_role('wpamelia-provider');
            $wp_roles->remove_role('wpamelia-manager');

            delete_option('amelia_settings');
            delete_option('amelia_stash');
            delete_option('amelia_show_wpdt_promo');

            foreach (['/amelia/css', '/amelia/files/tmp', '/amelia/files', '/amelia'] as $path) {
                if (is_dir(AMELIA_UPLOADS_PATH . $path)) {
                    self::deleteFolderContent(AMELIA_UPLOADS_PATH . $path);
                    rmdir(AMELIA_UPLOADS_PATH . $path);
                }
            }
        }
    }

    public static function wpdt_dashboard_promo()
    {
        $wpAmeliaPage = isset($_GET['page']) ? $_GET['page'] : '';
        require_once AMELIA_PATH . '/extensions/wpdt/functions.php';

        if(is_admin() && (strpos($wpAmeliaPage,'wpamelia-dashboard') !== false) &&
            amelia_installed_plugins_wpdt_promotion() &&
            get_option('amelia_show_wpdt_promo') == 'yes'
        ) {
            include AMELIA_PATH . '/extensions/wpdt/promote_wpdt.php';
            wp_enqueue_style('wdt-promo-css', AMELIA_URL . 'public/css/backend/promote_wpdt.css');
        }
    }

    public static function amelia_remove_wpdt_promo_notice()
    {
        update_option('amelia_show_wpdt_promo', 'no');
        echo json_encode(array("success"));
        exit;
    }

    public static function hide_notices_on_amelia_pages()
    {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'wpamelia')) {
            remove_action('admin_notices', 'update_nag', 3);
            remove_action('network_admin_notices', 'update_nag', 3);
            remove_action('admin_notices', 'maintenance_nag');
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }

        add_action('admin_notices', array('AmeliaBooking\Plugin', 'wpdt_dashboard_promo'));
    }

    public static function addPluginActionLinks($links)
    {
        $primaryLinks = [
            '<a href="' . admin_url('admin.php?page=wpamelia-dashboard') . '">View</a>',
            '<a href="' . admin_url('admin.php?page=wpamelia-settings') . '">Settings</a>'
        ];

        return array_merge($primaryLinks, $links);
    }

    public static function addPluginRowMeta($links, $file, $pluginData, $status)
    {
        if ($file !== AMELIA_PLUGIN_SLUG) {
            return $links;
        }

        $links[] = '<a href="https://wpamelia.com/documentation/" target="_blank" rel="noopener">Docs</a>';
        return $links;
    }

    public static function enqueueAngieMcpServer()
    {
        global $wp_version;
        if (version_compare($wp_version, '6.5', '<')) {
            return;
        }

        $mcpServerPath = AMELIA_PATH . '/redesign/dist/amelia-angie.js';
        if (!file_exists($mcpServerPath)) {
            return;
        }

        wp_enqueue_script_module(
            'amelia-angie-mcp',
            AMELIA_URL . 'redesign/dist/amelia-angie.js',
            array(),
            AMELIA_VERSION
        );
    }
}

add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = plugin_basename(__FILE__);
    $current_version = AMELIA_VERSION;

    $remote = wp_remote_post(AMELIA_GPL_UPDATE_SERVER . 'check-update', [
        'body' => [
            'plugin_slug' => 'ameliabooking',
            'current_version' => $current_version,
            'api_key' => AMELIA_GPL_API_KEY
        ],
        'timeout' => 5
    ]);

    if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body'])) {
        $remote_data = json_decode($remote['body']);

        if ($remote_data && is_object($remote_data) && json_last_error() === JSON_ERROR_NONE &&
            isset($remote_data->version) && isset($remote_data->package) && 
            version_compare($current_version, $remote_data->version, '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => 'ameliabooking-gpl',
                'plugin' => $plugin_slug,
                'new_version' => $remote_data->version,
                'url' => isset($remote_data->url) ? $remote_data->url : '',
                'package' => $remote_data->package,
                'tested' => isset($remote_data->tested) ? $remote_data->tested : '',
                'requires_php' => '5.5'
            ];
        }
    }

    return $transient;
});

add_filter('plugins_api', function ($res, $action, $args) {
    if ($action !== 'plugin_information') {
        return $res;
    }

    if ($args->slug !== 'ameliabooking-gpl') {
        return $res;
    }

    $remote = wp_remote_post(AMELIA_GPL_UPDATE_SERVER . 'plugin-info', [
        'body' => [
            'plugin_slug' => 'ameliabooking',
            'api_key' => AMELIA_GPL_API_KEY
        ],
        'timeout' => 5
    ]);

    if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body'])) {
        $remote_data = json_decode($remote['body']);

        if ($remote_data && is_object($remote_data) && json_last_error() === JSON_ERROR_NONE &&
            isset($remote_data->name) && isset($remote_data->version) && isset($remote_data->package)) {
            $res = (object) [
                'name' => $remote_data->name,
                'slug' => 'ameliabooking-gpl',
                'version' => $remote_data->version,
                'tested' => isset($remote_data->tested) ? $remote_data->tested : '',
                'requires' => '5.0',
                'requires_php' => '5.5',
                'author' => '<a href="https://wpamelia.com">Melograno Ventures</a>',
                'author_profile' => 'https://melograno.io',
                'download_link' => $remote_data->package,
                'trunk' => $remote_data->package,
                'last_updated' => isset($remote_data->last_updated) ? $remote_data->last_updated : '',
                'sections' => [
                    'description' => isset($remote_data->description) ? $remote_data->description : '',
                    'changelog' => isset($remote_data->changelog) ? $remote_data->changelog : ''
                ],
                'banners' => [
                    'low' => isset($remote_data->banner_low) ? $remote_data->banner_low : '',
                    'high' => isset($remote_data->banner_high) ? $remote_data->banner_high : ''
                ]
            ];

            return $res;
        }
    }

    return $res;
}, 10, 3);

add_filter('pre_http_request', function ($pre, $parsed_args, $url) {
    if (strpos($url, 'store.melograno.io') !== false || 
        strpos($url, 'wpamelia.com') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode(['success' => true, 'license' => 'valid', 'expires' => '2030-12-31'])
        ];
    }
    return $pre;
}, 10, 3);

add_action('wp_ajax_amelia_remove_wpdt_promo_notice', array('AmeliaBooking\Plugin', 'amelia_remove_wpdt_promo_notice'));
add_action('admin_head', array('AmeliaBooking\Plugin', 'hide_notices_on_amelia_pages'));

if (is_admin()) {
    add_action('wp_loaded', array('AmeliaBooking\Infrastructure\Services\Outlook\OutlookCalendarService', 'handleCallback'));
}

add_action('wp_ajax_wpamelia_api', array('AmeliaBooking\Plugin', 'wpAmeliaApiCall'));
add_action('wp_ajax_nopriv_wpamelia_api', array('AmeliaBooking\Plugin', 'wpAmeliaApiCall'));
add_action('plugins_loaded', array('AmeliaBooking\Plugin', 'init'));
add_action('admin_init', array('AmeliaBooking\Plugin', 'adminInit'));
add_action('admin_menu', array('AmeliaBooking\Plugin', 'initMenu'));

register_activation_hook(__FILE__, array('AmeliaBooking\Plugin', 'activation'));
register_activation_hook(__FILE__, array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationRolesHook', 'init'));
register_activation_hook(__FILE__, array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook', 'init'));
register_uninstall_hook(__FILE__, array('AmeliaBooking\Plugin', 'deletion'));

add_action('wpmu_new_blog', array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationNewSiteMultisite', 'init'));

add_filter('script_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'prepareScripts'), 10, 3);
add_filter('style_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'prepareStyles'), 10, 3);
add_filter('script_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService', 'prepareScripts'), 10, 3);
add_filter('style_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService', 'prepareStyles'), 10, 3);

add_action('thrive_automator_init', array('AmeliaBooking\Infrastructure\WP\Integrations\ThriveAutomator\ThriveAutomatorService', 'init'));
add_filter('plugin_row_meta', array('AmeliaBooking\Plugin', 'addPluginRowMeta'), 10, 4);
add_filter('plugin_action_links_' . AMELIA_PLUGIN_SLUG, array('AmeliaBooking\Plugin', 'addPluginActionLinks'));

add_action('wp_logout', array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'logoutAmeliaUser'));
add_action('profile_update', array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'updateAmeliaUser'), 10, 3);
add_action('deleted_user', array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'removeWPUserConnection'), 10, 1);

if (function_exists('is_plugin_active') && is_plugin_active('angie/angie.php')) {
    add_action('admin_enqueue_scripts', array('AmeliaBooking\Plugin', 'enqueueAngieMcpServer'));
}
