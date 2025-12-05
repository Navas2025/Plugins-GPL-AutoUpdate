# WP Rocket - Includes and Dependencies

## Core Plugin Structure

```
wp-rocket/
├── wp-rocket.php           # Main plugin file
├── uninstall.php          # Uninstall handler
├── inc/                   # Core includes
│   ├── main.php          # Main initialization
│   ├── functions/        # Function libraries
│   ├── classes/          # PHP classes
│   ├── vendor/           # Third-party libraries
│   └── Engine/           # Core engine components
├── assets/               # Frontend assets
│   ├── css/
│   ├── js/
│   └── img/
├── languages/            # Translation files
└── views/               # Admin view templates
```

## Core Includes

### Main File (wp-rocket.php)
The main plugin file that defines:
- Plugin header information
- Constants definition
- PHP version checks
- Plugin activation/deactivation hooks
- Core file includes

### Initialization (inc/main.php)
Handles plugin initialization:
- Load text domain for translations
- Register hooks and filters
- Initialize plugin components
- Set up admin interface

## Function Libraries (inc/functions/)

### options.php
- `get_rocket_option()` - Retrieve plugin options
- `get_rocket_options()` - Get all options
- `set_rocket_options()` - Update options
- `rocket_sanitize_options()` - Sanitize option values

### i18n.php
- `rocket_load_textdomain()` - Load translations
- `rocket_get_locale()` - Get current locale
- `rocket__()` - Translation wrapper functions

### formatting.php
- `rocket_format_bytes()` - Format byte sizes
- `rocket_format_date()` - Date formatting
- `rocket_sanitize_key()` - Sanitize array keys
- `rocket_clean_exclude()` - Clean exclusion patterns

### files.php
- `rocket_direct_filesystem()` - Direct filesystem access
- `rocket_put_content()` - Write file content
- `rocket_get_content()` - Read file content
- `rocket_mkdir_p()` - Create directories recursively
- `rocket_rmdir()` - Remove directories
- `rocket_clean_dir()` - Clean directory contents

### admin.php
- `rocket_admin_bar_menu()` - Admin bar menu items
- `rocket_admin_notices()` - Display admin notices
- `rocket_settings_link()` - Plugin action links

### caching.php
- `rocket_generate_caching_files()` - Generate cache files
- `rocket_clean_domain()` - Clean domain cache
- `rocket_clean_post()` - Clean post cache
- `rocket_clean_user()` - Clean user cache
- `rocket_preload_cache()` - Preload cache functionality

### minify.php
- `rocket_minify_css()` - Minify CSS files
- `rocket_minify_js()` - Minify JavaScript files
- `rocket_minify_html()` - Minify HTML output
- `rocket_combine_css()` - Combine CSS files
- `rocket_combine_js()` - Combine JavaScript files

## Class Files (inc/classes/)

### class-wp-rocket-requirements-check.php
Validates system requirements:
- PHP version checking
- WordPress version validation
- Server capability detection
- Extension availability check

### class-wp-rocket-cache.php
Cache management:
- Cache generation
- Cache cleaning
- Cache status checking
- Cache file management

### class-wp-rocket-options.php
Options management:
- Option registration
- Default value handling
- Option validation
- Settings API integration

### class-wp-rocket-admin-bar.php
Admin bar integration:
- Clear cache menu items
- Preload cache actions
- Status indicators
- Quick access links

### class-wp-rocket-settings.php
Settings page:
- Settings page rendering
- Form handling
- Settings sections
- Field registration

### class-wp-rocket-preload.php
Cache preload functionality:
- Sitemap parsing
- URL discovery
- Preload queue management
- Background processing

### class-wp-rocket-minify.php
Minification engine:
- CSS minification
- JavaScript minification
- HTML minification
- File combination

### class-wp-rocket-lazyload.php
Lazy loading:
- Image lazy loading
- Iframe lazy loading
- YouTube video lazy loading
- Intersection observer integration

## Third-Party Dependencies (inc/vendor/)

### Minification Libraries
- CSS minifier (e.g., CSSTidy or similar)
- JavaScript minifier (e.g., JSMin or similar)
- HTML minifier

### Utility Libraries
- Mobile detection library
- Browser detection
- URL parsing utilities
- File system helpers

### Caching Libraries
- Cache adapter interfaces
- Object cache integration
- Transient API helpers

## WordPress Hooks Used

### Actions
- `plugins_loaded` - Initialize plugin
- `init` - Register post types and taxonomies
- `admin_init` - Admin initialization
- `admin_menu` - Add admin menu pages
- `admin_bar_menu` - Add admin bar items
- `wp_enqueue_scripts` - Enqueue frontend scripts
- `admin_enqueue_scripts` - Enqueue admin scripts
- `save_post` - Clean cache on post save
- `deleted_post` - Clean cache on post deletion
- `switch_theme` - Clean cache on theme switch
- `upgrader_process_complete` - Clean cache after updates

### Filters
- `rocket_settings` - Modify plugin settings
- `rocket_cache_query_strings` - Filter query strings for caching
- `rocket_cache_reject_uri` - Reject URIs from caching
- `rocket_cache_reject_cookies` - Reject cookies from caching
- `rocket_cache_mandatory_cookies` - Set mandatory cookies
- `rocket_minify_excluded_external_js` - Exclude external JS from minification
- `rocket_minify_excluded_inline_js` - Exclude inline JS from minification
- `rocket_lazyload_excluded_attributes` - Exclude attributes from lazy loading
- `rocket_buffer` - Filter entire HTML buffer

## External API Integration

### CDN Integration
- CloudFlare API integration
- Amazon CloudFront support
- Generic CDN configuration
- CNAME configuration

### Image Optimization
- WebP detection and serving
- Imagify integration (optional)
- Responsive image support

### Database Optimization
- Cleanup query optimization
- Transient cleaning
- Post revision management
- Spam comment removal

## Configuration Files

### .htaccess Rules
Generated automatically for Apache servers:
- Browser caching rules
- GZIP compression
- Expires headers
- Cache control headers

### nginx.conf
Configuration snippets for Nginx:
- FastCGI cache configuration
- Browser cache rules
- GZIP settings

### wp-rocket-config/
Configuration directory:
- Per-domain configuration files
- Advanced cache dropin
- Cache rules

## Dependencies on WordPress Core

### Required WordPress Functions
- `add_action()` / `add_filter()` - Hook system
- `wp_enqueue_script()` / `wp_enqueue_style()` - Asset management
- `get_option()` / `update_option()` - Options API
- `wp_cache_*()` - Object cache API
- `wp_mkdir_p()` - Filesystem operations
- `wp_upload_dir()` - Upload directory paths
- `wp_remote_*()` - HTTP API

### WordPress Constants Used
- `ABSPATH` - WordPress root directory
- `WP_CONTENT_DIR` - Content directory
- `WP_PLUGIN_DIR` - Plugins directory
- `WP_CACHE` - Cache flag
- `WP_DEBUG` - Debug mode

## Performance Considerations

### File Loading Strategy
- Conditional loading based on context
- Autoloading for classes
- Lazy loading of admin-only files
- Minimal frontend footprint

### Memory Optimization
- Efficient data structures
- Streaming file operations
- Chunked processing for large operations
- Resource cleanup

### Caching Strategy
- Object cache integration
- Transient API usage
- Database query caching
- File-based cache fallback
