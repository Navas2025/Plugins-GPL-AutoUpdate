# WP Rocket Version Information

## Current Version
**3.16.4** (December 2024)

## Version Requirements

### WordPress Requirements
- **Minimum WordPress Version:** 6.3
- **Tested up to:** 6.4
- **Recommended:** Latest stable WordPress version

### PHP Requirements
- **Minimum PHP Version:** 7.4
- **Recommended PHP Version:** 8.0 or higher
- **Maximum tested PHP Version:** 8.3

### Server Requirements
- **Web Server:** Apache or Nginx
- **mod_rewrite:** Required for Apache
- **File Permissions:** Write permissions for wp-content directory
- **PHP Extensions:**
  - mbstring
  - gd or imagick (for image optimization)
  - zlib (for GZIP compression)

## Version History

### 3.16.4 (Current)
- Release Date: December 2024
- Status: Stable
- Major improvements and bug fixes

### Previous Versions
- 3.16.x series: Performance optimizations
- 3.15.x series: Feature enhancements
- 3.14.x series: Bug fixes and stability improvements

## Update Policy

This repository tracks GPL versions of WP Rocket. Updates are applied when:
1. New stable versions are released
2. Security updates are available
3. Critical bug fixes are issued
4. Compatibility updates for WordPress core are needed

## Compatibility Matrix

| WP Rocket Version | WordPress Version | PHP Version | Status |
|-------------------|-------------------|-------------|---------|
| 3.16.4            | 6.3 - 6.4        | 7.4 - 8.3   | Current |
| 3.16.x            | 6.2 - 6.4        | 7.4 - 8.2   | Supported |
| 3.15.x            | 6.1 - 6.3        | 7.4 - 8.1   | Legacy |

## Breaking Changes

### Version 3.16.x
- Minimum WordPress version increased to 6.3
- Deprecated functions from 3.14.x removed
- Updated minification engine

### Version 3.15.x
- Minimum PHP version increased to 7.4
- Removed support for legacy cache methods

## Upgrade Notes

When upgrading from previous versions:
1. Clear all caches before upgrade
2. Backup wp-content/wp-rocket-config folder
3. Deactivate and reactivate the plugin after upgrade
4. Review and update settings if necessary
5. Test critical functionality after upgrade
