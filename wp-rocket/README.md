# WP Rocket - GPL Version

## Plugin Information

- **Plugin Name:** WP Rocket
- **Current Version:** 3.16.4
- **Requires WordPress:** 6.3 or higher
- **Requires PHP:** 7.4 or higher
- **License:** GPL-2.0+
- **License URI:** http://www.gnu.org/licenses/gpl-2.0.txt

## Description

WP Rocket is a powerful WordPress caching plugin that helps improve website performance and page load times. This is the GPL-licensed version distributed according to the terms of the GNU General Public License.

## Features

- Page Caching
- Cache Preloading
- Sitemap-based Cache Preloading
- GZIP Compression
- Browser Caching
- Database Optimization
- Lazy Loading for images and iframes
- Minification (CSS, JavaScript, HTML)
- Concatenation of CSS and JavaScript files
- CDN Integration
- DNS Prefetching
- Heartbeat Control
- WebP Compatibility

## Installation

1. Upload the `wp-rocket` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings through Settings > WP Rocket

## Main Includes and Dependencies

### Core Files
- `wp-rocket.php` - Main plugin file
- `inc/main.php` - Core functionality initialization
- `inc/functions/` - Helper functions
  - `options.php` - Option handling functions
  - `i18n.php` - Internationalization functions
  - `formatting.php` - Data formatting functions
  - `files.php` - File system operations
  - `admin.php` - Admin interface functions
  - `caching.php` - Caching functionality
  - `minify.php` - Minification functions
  - `varnish.php` - Varnish integration
- `inc/classes/` - Object-oriented components
  - `class-wp-rocket-requirements-check.php` - System requirements validation
  - `class-wp-rocket-cache.php` - Cache management
  - `class-wp-rocket-options.php` - Options management
  - `class-wp-rocket-admin-bar.php` - Admin bar integration
  - `class-wp-rocket-settings.php` - Settings page
  - `class-wp-rocket-preload.php` - Cache preload functionality
  - `class-wp-rocket-minify.php` - Minification engine
  - `class-wp-rocket-lazyload.php` - Lazy loading implementation

### Assets
- `assets/css/` - Stylesheet files
- `assets/js/` - JavaScript files
- `assets/img/` - Image assets

### Vendor Dependencies
The plugin may include third-party libraries in the `inc/vendor/` directory:
- Minification libraries
- Browser detection libraries
- Utility libraries

## Version History

### Version 3.16.4
- Latest stable release
- Performance improvements
- Bug fixes and security updates

## GPL License Notice

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
MA 02110-1301, USA.

## Copyright

Copyright 2013-2024 WP Media

## Support

For support and documentation, please refer to the official WP Rocket documentation.

## Changelog

### 3.16.4
- Current version
- See official changelog for detailed update information
