<?php
/**
 * Plugin Name: Pro WP SMTP
 * Plugin URI: https://prowpsmtp.com
 * Description: Plugin SMTP completo para configuración de correos corporativos y servicios externos. Todas las funciones PRO activadas.
 * Version: 1.0.0
 * Author: MiniMax Agent
 * License: GPL v2 or later
 * Text Domain: pro-wp-smtp
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

if (!defined('WPINC')) {
    die;
}

define('PRO_WP_SMTP_VERSION', '1.0.0');
define('PRO_WP_SMTP_PLUGIN_FILE', __FILE__);
define('PRO_WP_SMTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRO_WP_SMTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PRO_WP_SMTP_PLUGIN_BASENAME', plugin_basename(__FILE__));

class ProWPSMTP {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('plugins_loaded', array($this, 'init'), 10);
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('phpmailer_init', array($this, 'phpmailer_init'), 999);
        add_filter('plugin_action_links_' . PRO_WP_SMTP_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }
    
    public function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_logs = $wpdb->prefix . 'prowpsmtp_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subject varchar(255) DEFAULT NULL,
            to_email text DEFAULT NULL,
            from_email varchar(255) DEFAULT NULL,
            mailer varchar(50) DEFAULT NULL,
            status varchar(20) DEFAULT NULL,
            error text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        
        if (!get_option('prowpsmtp_settings')) {
            add_option('prowpsmtp_settings', array(
                'mail_from' => get_option('admin_email'),
                'mail_from_name' => get_option('blogname'),
                'force_from_email' => 0,
                'force_from_name' => 0,
                'mailer' => 'smtp',
                'return_path' => true,
                'smtp_host' => '',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'smtp_auth' => true,
                'smtp_user' => '',
                'smtp_pass' => '',
            ));
        }
        
        if (!get_option('prowpsmtp_pro_features')) {
            add_option('prowpsmtp_pro_features', array(
                'email_logging' => true,
                'email_tracking' => true,
                'reports' => true,
            ));
        }
    }
    
    public function init() {
        load_plugin_textdomain('pro-wp-smtp', false, dirname(PRO_WP_SMTP_PLUGIN_BASENAME) . '/languages');
    }
    
    public function admin_menu() {
        add_menu_page(
            __('Pro WP SMTP', 'pro-wp-smtp'),
            __('Pro WP SMTP', 'pro-wp-smtp'),
            'manage_options',
            'pro-wp-smtp',
            array($this, 'settings_page'),
            'dashicons-email-alt',
            80
        );
        
        add_submenu_page(
            'pro-wp-smtp',
            __('Configuración', 'pro-wp-smtp'),
            __('Configuración', 'pro-wp-smtp'),
            'manage_options',
            'pro-wp-smtp',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'pro-wp-smtp',
            __('Email Test', 'pro-wp-smtp'),
            __('Email Test', 'pro-wp-smtp'),
            'manage_options',
            'pro-wp-smtp-test',
            array($this, 'test_page')
        );
        
        add_submenu_page(
            'pro-wp-smtp',
            __('Email Log', 'pro-wp-smtp'),
            __('Email Log', 'pro-wp-smtp'),
            'manage_options',
            'pro-wp-smtp-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'pro-wp-smtp',
            __('Reportes', 'pro-wp-smtp'),
            __('Reportes', 'pro-wp-smtp'),
            'manage_options',
            'pro-wp-smtp-reports',
            array($this, 'reports_page')
        );
    }
    
    public function settings_page() {
        if (isset($_POST['prowpsmtp_save_settings']) && check_admin_referer('prowpsmtp_settings')) {
            $settings = array(
                'mail_from' => sanitize_email($_POST['prowpsmtp_from_email']),
                'mail_from_name' => sanitize_text_field($_POST['prowpsmtp_from_name']),
                'force_from_email' => isset($_POST['prowpsmtp_force_from_email']) ? 1 : 0,
                'force_from_name' => isset($_POST['prowpsmtp_force_from_name']) ? 1 : 0,
                'mailer' => sanitize_text_field($_POST['prowpsmtp_mailer']),
                'return_path' => isset($_POST['prowpsmtp_return_path']) ? 1 : 0,
            );
            
            if ($_POST['prowpsmtp_mailer'] === 'smtp') {
                $settings['smtp_host'] = sanitize_text_field($_POST['prowpsmtp_smtp_host']);
                $settings['smtp_port'] = intval($_POST['prowpsmtp_smtp_port']);
                $settings['smtp_encryption'] = sanitize_text_field($_POST['prowpsmtp_smtp_encryption']);
                $settings['smtp_auth'] = isset($_POST['prowpsmtp_smtp_auth']) ? 1 : 0;
                $settings['smtp_user'] = sanitize_text_field($_POST['prowpsmtp_smtp_user']);
                if (!empty($_POST['prowpsmtp_smtp_pass'])) {
                    $settings['smtp_pass'] = sanitize_text_field($_POST['prowpsmtp_smtp_pass']);
                } else {
                    $old_settings = get_option('prowpsmtp_settings', array());
                    $settings['smtp_pass'] = isset($old_settings['smtp_pass']) ? $old_settings['smtp_pass'] : '';
                }
            } elseif ($_POST['prowpsmtp_mailer'] === 'gmail') {
                $settings['gmail_client_id'] = sanitize_text_field($_POST['prowpsmtp_gmail_client_id']);
                $settings['gmail_client_secret'] = sanitize_text_field($_POST['prowpsmtp_gmail_client_secret']);
            } elseif ($_POST['prowpsmtp_mailer'] === 'sendgrid') {
                $settings['sendgrid_api_key'] = sanitize_text_field($_POST['prowpsmtp_sendgrid_api_key']);
            } elseif ($_POST['prowpsmtp_mailer'] === 'mailgun') {
                $settings['mailgun_api_key'] = sanitize_text_field($_POST['prowpsmtp_mailgun_api_key']);
                $settings['mailgun_domain'] = sanitize_text_field($_POST['prowpsmtp_mailgun_domain']);
                $settings['mailgun_region'] = sanitize_text_field($_POST['prowpsmtp_mailgun_region']);
            }
            
            update_option('prowpsmtp_settings', $settings);
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Configuración guardada correctamente.', 'pro-wp-smtp') . '</p></div>';
        }
        
        $settings = get_option('prowpsmtp_settings', array());
        include PRO_WP_SMTP_PLUGIN_DIR . 'src/Admin/settings.php';
    }
    
    public function test_page() {
        if (isset($_POST['prowpsmtp_send_test']) && check_admin_referer('prowpsmtp_test_email')) {
            $to = sanitize_email($_POST['prowpsmtp_test_email']);
            $subject = sanitize_text_field($_POST['prowpsmtp_test_subject']);
            $message = wp_kses_post($_POST['prowpsmtp_test_message']);
            
            $result = wp_mail($to, $subject, $message);
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Email de prueba enviado correctamente.', 'pro-wp-smtp') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al enviar el email de prueba.', 'pro-wp-smtp') . '</p></div>';
            }
        }
        
        include PRO_WP_SMTP_PLUGIN_DIR . 'src/Admin/test.php';
    }
    
    public function logs_page() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'prowpsmtp_logs';
        $per_page = 50;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $total_pages = ceil($total / $per_page);
        
        include PRO_WP_SMTP_PLUGIN_DIR . 'src/Admin/logs.php';
    }
    
    public function reports_page() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'prowpsmtp_logs';
        
        $total_sent = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'sent'");
        $total_failed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'");
        $success_rate = $total_sent > 0 ? round(($total_sent / ($total_sent + $total_failed)) * 100, 2) : 0;
        
        include PRO_WP_SMTP_PLUGIN_DIR . 'src/Admin/reports.php';
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'pro-wp-smtp') === false) {
            return;
        }
        
        wp_enqueue_style(
            'prowpsmtp-admin',
            PRO_WP_SMTP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PRO_WP_SMTP_VERSION
        );
        
        wp_enqueue_script(
            'prowpsmtp-admin',
            PRO_WP_SMTP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PRO_WP_SMTP_VERSION,
            true
        );
    }
    
    public function phpmailer_init($phpmailer) {
        $settings = get_option('prowpsmtp_settings', array());
        
        if (!empty($settings['mail_from']) && !empty($settings['force_from_email'])) {
            $phpmailer->From = $settings['mail_from'];
        }
        
        if (!empty($settings['mail_from_name']) && !empty($settings['force_from_name'])) {
            $phpmailer->FromName = $settings['mail_from_name'];
        }
        
        $mailer = isset($settings['mailer']) ? $settings['mailer'] : 'smtp';
        
        if ($mailer === 'smtp') {
            $phpmailer->isSMTP();
            $phpmailer->Host = isset($settings['smtp_host']) ? $settings['smtp_host'] : '';
            $phpmailer->Port = isset($settings['smtp_port']) ? intval($settings['smtp_port']) : 587;
            $phpmailer->SMTPAuth = isset($settings['smtp_auth']) && $settings['smtp_auth'];
            $phpmailer->Username = isset($settings['smtp_user']) ? $settings['smtp_user'] : '';
            $phpmailer->Password = isset($settings['smtp_pass']) ? $settings['smtp_pass'] : '';
            
            if (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] !== 'none') {
                $phpmailer->SMTPSecure = $settings['smtp_encryption'];
            }
            
            $phpmailer->SMTPAutoTLS = true;
        }
        
        $this->log_email($phpmailer);
    }
    
    private function log_email($phpmailer) {
        global $wpdb;
        
        $pro_features = get_option('prowpsmtp_pro_features', array());
        
        if (empty($pro_features['email_logging'])) {
            return;
        }
        
        $table = $wpdb->prefix . 'prowpsmtp_logs';
        
        $to = is_array($phpmailer->getToAddresses()) ? implode(', ', array_column($phpmailer->getToAddresses(), 0)) : '';
        
        $wpdb->insert(
            $table,
            array(
                'subject' => $phpmailer->Subject,
                'to_email' => $to,
                'from_email' => $phpmailer->From,
                'mailer' => $phpmailer->Mailer,
                'status' => 'sent',
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=pro-wp-smtp') . '">' . __('Configuración', 'pro-wp-smtp') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

function prowpsmtp() {
    return ProWPSMTP::instance();
}

prowpsmtp();
