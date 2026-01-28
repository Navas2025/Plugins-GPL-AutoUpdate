<?php
/*
Plugin Name: Amelia
Plugin URI: https://wpamelia.com/
Description: Amelia is a simple yet powerful automated booking specialist, working 24/7 to make sure your customers can make appointments and events even while you sleep!
Version: 9.1
Author: Melograno Ventures
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

// No direct access
defined('ABSPATH') or die('No script kiddies please!');

// Const for path root
if (!defined('AMELIA_PATH')) {
    define('AMELIA_PATH', __DIR__);
}

// Const for uploads path
if (!defined('AMELIA_UPLOADS_PATH')) {
    $uploadDir = wp_upload_dir();
    define('AMELIA_UPLOADS_PATH', $uploadDir['basedir']);
}

// Const for uploads url
if (!defined('AMELIA_UPLOADS_URL')) {
    $uploadUrl = wp_upload_dir();
    define('AMELIA_UPLOADS_URL', set_url_scheme($uploadUrl['baseurl']));
}

// Const for uploads url
if (!defined('AMELIA_UPLOADS_FILES_URL')) {
    define('AMELIA_UPLOADS_FILES_URL', AMELIA_UPLOADS_URL . '/amelia/files/');
}

// Const for uploads files path
if (!defined('AMELIA_UPLOADS_FILES_PATH')) {
    define('AMELIA_UPLOADS_FILES_PATH', AMELIA_UPLOADS_PATH . '/amelia/files/');
}

// Const for uploads files path
if (!defined('AMELIA_UPLOADS_FILES_PATH_USE')) {
    define('AMELIA_UPLOADS_FILES_PATH_USE', true);
}

// Const for URL root
if (!defined('AMELIA_URL')) {
    define('AMELIA_URL', plugin_dir_url(__FILE__));
}

if (!defined('AMELIA_HOME_URL')) {
    define('AMELIA_HOME_URL', get_home_url());
}

// Const for URL Actions identifier
if (!defined('AMELIA_ACTION_SLUG')) {
    define('AMELIA_ACTION_SLUG', 'action=wpamelia_api&call=');
}

// Const for URL Actions identifier
if (!defined('AMELIA_ACTION_URL')) {
    define('AMELIA_ACTION_URL', admin_url('admin-ajax.php', '') . '?' . AMELIA_ACTION_SLUG);
}

// Const for URL Actions identifier
if (!defined('AMELIA_PAGE_URL')) {
    define('AMELIA_PAGE_URL', get_site_url() . '/wp-admin/admin.php?page=');
}

// Const for URL Actions identifier
if (!defined('AMELIA_LOGIN_URL')) {
    define('AMELIA_LOGIN_URL', get_site_url() . '/wp-login.php?redirect_to=');
}

// Const for Amelia version
if (!defined('AMELIA_VERSION')) {
    define('AMELIA_VERSION', '9.1');
}

// Const for site URL
if (!defined('AMELIA_SITE_URL')) {
    define('AMELIA_SITE_URL', get_site_url());
}

// Const for plugin basename
if (!defined('AMELIA_PLUGIN_SLUG')) {
    define('AMELIA_PLUGIN_SLUG', plugin_basename(__FILE__));
}

// Const for Amelia SMS API
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

require_once AMELIA_PATH . '/vendor/autoload.php';


/**
 * @noinspection AutoloadingIssuesInspection
 *
 * Class Plugin
 *
 * @package AmeliaBooking
 *
 * @phpcs:ignoreFile
 * @SuppressWarnings(PHPMD)
 */
class Plugin
{

    /**
     * API Call
     *
     * @throws \InvalidArgumentException
     */
    public static function wpAmeliaApiCall()
    {
        try {
            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

            $app = new App($container);

            // Initialize all API routes
            Routes::routes($app, $container);

            $app->run();

            exit();
        } catch (Exception $e) {
            echo 'ERROR: ' . esc_html($e->getMessage());
        }
    }

    static function square_weekly_token_refresh( $schedules ) {
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => __('Add weekly cron to refresh square access token every 7 days')
        );
        return $schedules;
    }

    /**
     * Initialize the plugin
     */
    public static function init()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        self::weglotConflict($settingsService, true);

        // Const for path root
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
            add_filter( 'cron_schedules', [self::class, 'square_weekly_token_refresh'] );

            if ( ! wp_next_scheduled( 'amelia_square_access_token_refresh' ) ) {
                wp_schedule_event( time(), 'weekly', 'amelia_square_access_token_refresh' );
            }

            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

            /** @var SquareService $squareService */
            $squareService = $container->get('infrastructure.payment.square.service');

            add_action( 'amelia_square_access_token_refresh', [$squareService, 'refreshAccessToken'] );
        }

        $ameliaRole = UserRoles::getUserAmeliaRole(wp_get_current_user());

        // Init menu if user is logged in with amelia role
        if (in_array($ameliaRole, ['admin', 'manager', 'provider', 'customer'])) {
            if ($ameliaRole === 'admin') {
                ErrorService::setNotices();
            }

            // Add TinyMCE button for shortcode generator
            ButtonService::renderButton();

            // Add Gutenberg Block for shortcode generator
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
                // Only enqueue jQuery early in Divi builder to avoid frontend conflicts
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

        // Load BuddyBoss integration only if feature is enabled
        if ($settingsService->isFeatureEnabled('buddyboss')) {
            require_once AMELIA_PATH . '/extensions/buddyboss-platform-addon/buddyboss-platform-addon.php';
        }
    }

    /**
     * Creating Amelia block category in Gutenberg
     */
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

    /**
     * Fix for conflict with Weglot plugin
     * @param $settingsService
     * @param $init
     */
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

    /**
     * Fix for conflict with LearnPress plugin
     */
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

        // Init admin menu
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

    /**
     * Handle welcome page redirect and access control
     *
     * @param SettingsService $settingsService
     */
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

    /**
     * @param $networkWide
     */
    public static function activation($networkWide)
    {
        load_plugin_textdomain('wpamelia', false, plugin_basename(__DIR__) . '/languages/' . get_locale() . '/');

        // Check PHP version
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50500) {
            deactivate_plugins(AMELIA_PLUGIN_SLUG);
            wp_die(
                BackendStrings::get('php_version_message'),
                BackendStrings::get('php_version_title'),
                array('response' => 200, 'back_link' => TRUE)
            );
        }
        //Network activation
        if ($networkWide && function_exists('is_multisite') && is_multisite()) {
            Infrastructure\WP\InstallActions\ActivationMultisite::init();
        }

        Infrastructure\WP\InstallActions\ActivationDatabaseHook::init();

        set_transient('amelia_activation_redirect', true, 30);
    }

    /**
     * @param $dirPath
     */
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

    /**
     * @throws Domain\Common\Exceptions\InvalidArgumentException
     */
    public static function deletion()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        if ($settingsService->getSetting('activation', 'deleteTables')) {
            //Network deletion
            if (function_exists('is_multisite') &&
                is_multisite()
            ) {
                Infrastructure\WP\InstallActions\DeletionMultisite::delete();
            }

            Infrastructure\WP\InstallActions\DeleteDatabaseHook::delete();


            // Delete Roles
            global $wp_roles;

            $wp_roles->remove_role('wpamelia-customer');
            $wp_roles->remove_role('wpamelia-provider');
            $wp_roles->remove_role('wpamelia-manager');


            // Delete Settings
            delete_option('amelia_settings');
            delete_option('amelia_stash');
            delete_option('amelia_show_wpdt_promo');

            // Delete Files
            foreach (['/amelia/css', '/amelia/files/tmp', '/amelia/files', '/amelia'] as $path) {
                if (is_dir(AMELIA_UPLOADS_PATH . $path)) {
                    self::deleteFolderContent(AMELIA_UPLOADS_PATH . $path);
                    rmdir(AMELIA_UPLOADS_PATH . $path);
                }
            }
        }
    }

    /**
     * Show WPDT promo notice
     **/
    public static function wpdt_dashboard_promo()
    {
        $wpAmeliaPage = isset($_GET['page']) ? $_GET['page'] : '';

        require_once AMELIA_PATH . '/extensions/wpdt/functions.php';

        if( is_admin() && (strpos($wpAmeliaPage,'wpamelia-dashboard') !== false) &&
            amelia_installed_plugins_wpdt_promotion() &&
            get_option( 'amelia_show_wpdt_promo' ) == 'yes'
        ) {
            include AMELIA_PATH . '/extensions/wpdt/promote_wpdt.php';
            wp_enqueue_style('wdt-promo-css', AMELIA_URL . 'public/css/backend/promote_wpdt.css');
        }
    }

    /**
     * Remove WPDT promo notice
     **/
    public static function amelia_remove_wpdt_promo_notice()
    {
        update_option( 'amelia_show_wpdt_promo', 'no' );
        echo json_encode( array("success") );
        exit;
    }

    /**
     * Hide admin notices on Amelia pages
     **/
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

    /**
     * @param array $links
     *
     * @return array
     */
    public static function addPluginActionLinks($links)
    {
        $primaryLinks = [
            '<a href="' . admin_url('admin.php?page=wpamelia-dashboard') . '">View</a>',
            '<a href="' . admin_url('admin.php?page=wpamelia-settings') . '">Settings</a>'
        ];

        if (Licence\Licence::getLicence() === LicenceConstants::LITE) {
            $links[] = '<a href="https://wpamelia.com/pricing/?utm_source=wp_org&utm_medium=wp_org&utm_content=plugin_row&utm_campaign=wp_org" style="color: #5951F6; font-weight: bold;" target="_blank">Get Amelia Pro</a>';
        }

        return array_merge($primaryLinks, $links);
    }

    /**
     * @param array  $links
     * @param string $file
     * @param array  $pluginData
     * @param string $status
     *
     * @return array
     */
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

add_action('wp_ajax_amelia_remove_wpdt_promo_notice', array('AmeliaBooking\Plugin', 'amelia_remove_wpdt_promo_notice'));

add_action('admin_head', array('AmeliaBooking\Plugin', 'hide_notices_on_amelia_pages'));

/** Redirect For Outlook Calendar */
if (is_admin()) {
    add_action('wp_loaded', array('AmeliaBooking\Infrastructure\Services\Outlook\OutlookCalendarService', 'handleCallback'));
}

/** Isolate API calls */
add_action('wp_ajax_wpamelia_api', array('AmeliaBooking\Plugin', 'wpAmeliaApiCall'));
add_action('wp_ajax_nopriv_wpamelia_api', array('AmeliaBooking\Plugin', 'wpAmeliaApiCall'));

/** Init the plugin */
add_action('plugins_loaded', array('AmeliaBooking\Plugin', 'init'));

add_action('admin_init', array('AmeliaBooking\Plugin', 'adminInit'));

add_action('admin_menu', array('AmeliaBooking\Plugin', 'initMenu'));

/** Activation hooks */
register_activation_hook(__FILE__, array('AmeliaBooking\Plugin', 'activation'));
register_activation_hook(__FILE__, array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationRolesHook', 'init'));
register_activation_hook(__FILE__, array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook', 'init'));
register_uninstall_hook(__FILE__, array('AmeliaBooking\Plugin', 'deletion'));

/** Activation hook for new site on multisite setup */
add_action('wpmu_new_blog', array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationNewSiteMultisite', 'init'));

/** Define the API for updating checking */
add_filter('pre_set_site_transient_update_plugins', array('AmeliaBooking\Infrastructure\WP\InstallActions\AutoUpdateHook', 'checkUpdate'), 21, 1);

/** Define the alternative response for information checking */
add_filter('plugins_api', array('AmeliaBooking\Infrastructure\WP\InstallActions\AutoUpdateHook', 'checkInfo'), 20, 3);

/** Add a message for unavailable auto update if plugin is not activated */
add_action('in_plugin_update_message-' . AMELIA_PLUGIN_SLUG, array('AmeliaBooking\Infrastructure\WP\InstallActions\AutoUpdateHook', 'addMessageOnPluginsPage'));

/** Add error message on plugin update if plugin is not activated */
add_filter('upgrader_pre_download', array('AmeliaBooking\Infrastructure\WP\InstallActions\AutoUpdateHook', 'addMessageOnUpdate'), 10, 4);

add_filter('script_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'prepareScripts') , 10, 3);
add_filter('style_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'prepareStyles') , 10, 3);

add_filter('script_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService', 'prepareScripts') , 10, 3);
add_filter('style_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService', 'prepareStyles') , 10, 3);

add_action('thrive_automator_init', array('AmeliaBooking\Infrastructure\WP\Integrations\ThriveAutomator\ThriveAutomatorService', 'init'));
add_filter('plugin_row_meta', array('AmeliaBooking\Plugin', 'addPluginRowMeta'), 10, 4);
add_filter('plugin_action_links_' . AMELIA_PLUGIN_SLUG, array('AmeliaBooking\Plugin', 'addPluginActionLinks'));

add_action( 'wp_logout',  array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'logoutAmeliaUser'));
add_action( 'profile_update',  array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'updateAmeliaUser'), 10, 3);
add_action( 'deleted_user', array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'removeWPUserConnection'), 10, 1);

if (function_exists('is_plugin_active') && is_plugin_active('angie/angie.php')) {
    add_action('admin_enqueue_scripts', array('AmeliaBooking\Plugin', 'enqueueAngieMcpServer'));
}
