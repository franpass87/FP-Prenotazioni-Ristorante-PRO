# FPPR Brand Configuration System

This document explains how to customize the accent color and brand styling for the FP Prenotazioni Ristorante across multiple sites or brands.

## Overview

The brand configuration system provides flexible theming that can be managed through multiple methods, making it ideal for both single restaurants and multi-brand deployments.

## Priority System

The configuration uses the following priority order (highest to lowest):

1. **PHP Filter** - `apply_filters('fppr_brand_config', $config)` (for advanced programmatic control)
2. **PHP Constants** - Defined in `wp-config.php` or theme (for environment-specific overrides)
3. **Admin Interface** - WordPress admin settings page (easiest for users)
4. **JSON File** - Local or global configuration files (for programmatic deployments)
5. **Default Values** - Built-in defaults

## Configuration Methods

### Method 1: Admin Interface (Recommended for Most Users)

The easiest way to customize your restaurant's branding is through the WordPress admin interface:

1. Go to **Prenotazioni > Impostazioni** in your WordPress admin
2. Scroll to the **"Configurazione Brand e Colori"** section
3. Use the color pickers to select your:
   - **Primary Color** - Main brand color for buttons and highlights
   - **Secondary Color** - Accent color for complementary elements  
   - **Border Radius** - How rounded corners appear (0px to 16px)
4. Preview your changes in real-time
5. Click **"Salva modifiche"** to apply

**Benefits:**
- No technical knowledge required
- Real-time preview
- User-friendly interface
- Highest priority for user-selected colors

### Method 2: JSON File Configuration

Create a `fppr-brand.json` file with your brand settings:

```json
{
  "accent_color": "#e74c3c",
  "accent_color_light": "#ec7063", 
  "accent_color_dark": "#c0392b",
  "secondary_color": "#f39c12",
  "border_radius": "10px",
  "logo_url": "https://example.com/logo.png",
  "brand_name": "My Restaurant"
}
```

**Placement Options:**
- **Plugin directory**: `/wp-content/plugins/fp-prenotazioni-ristorante-pro/fppr-brand.json`
- **Global override**: `/wp-content/fppr-brand.json` (takes priority)

**Note:** If colors are set in the Admin Interface, JSON file settings will be ignored for colors, but other settings like `logo_url` and `brand_name` will still be used.

### Method 3: PHP Constants

Add to your `wp-config.php` or theme's `functions.php`:

```php
define('FPPR_ACCENT_COLOR', '#e74c3c');
define('FPPR_ACCENT_COLOR_LIGHT', '#ec7063');
define('FPPR_ACCENT_COLOR_DARK', '#c0392b');
define('FPPR_BORDER_RADIUS', '10px');
```

### Method 4: PHP Filter (Advanced)

```php
add_filter('fppr_brand_config', function($config) {
    $config['accent_color'] = '#e74c3c';
    $config['border_radius'] = '15px';
    return $config;
});
```

## Shortcode Parameters

Override colors for individual form instances:

```html
[ristorante_booking_form accent_color="#e74c3c" border_radius="15px"]
```

## Available Configuration Options

| Parameter | Description | Default |
|-----------|-------------|---------|
| `accent_color` | Primary brand color | `#000000` |
| `accent_color_light` | Lighter variant for hover states | `#333333` |
| `accent_color_dark` | Darker variant for active states | `#000000` |
| `secondary_color` | Secondary accent color | `#f8b500` |
| `border_radius` | CSS border-radius value | `8px` |
| `logo_url` | Brand logo URL (future use) | `""` |
| `brand_name` | Brand name (future use) | `""` |

## CSS Variables Generated

The system automatically generates these CSS custom properties:

- `--fppr-accent` - Primary accent color
- `--fppr-accent-light` - Light variant 
- `--fppr-accent-dark` - Dark variant
- `--fppr-secondary` - Secondary color
- `--fppr-radius` - Border radius

Legacy variables for backward compatibility:
- `--rbf-primary`, `--rbf-primary-light`, `--rbf-primary-dark`

## Usage Examples

### Single Restaurant
Use the default `fppr-brand.json` in the plugin directory.

### Multiple Brands
1. Deploy plugin to all sites
2. Place brand-specific `fppr-brand.json` in each site's `/wp-content/` directory
3. Each site automatically loads its own branding

### Development/Staging
Use PHP constants in `wp-config.php` for environment-specific colors.

### Dynamic Themes
Use the PHP filter to load colors from custom post types or external APIs.

## Testing Your Configuration

1. Update your configuration file/constant
2. Clear any caching plugins
3. Visit a page with the booking form
4. Inspect CSS to verify `--fppr-accent` variables are applied

## Troubleshooting

**Colors not changing?**
- Check file permissions on JSON file
- Verify JSON syntax with online validator
- Clear any page/plugin caches
- Check browser developer tools for CSS variable values

**JSON not loading?**
- Verify file path and permissions
- Check WordPress error logs for JSON parsing errors
- Ensure proper JSON syntax (no trailing commas)

## Migration from Hardcoded Colors

The system maintains backward compatibility. Existing CSS using `--rbf-primary` variables will continue working while you migrate to the new system.

## Future Extensions

The configuration structure supports future enhancements:
- Logo management
- Font selections  
- Advanced color schemes
- Per-service theming
- Multi-language brand assets

---

For technical support or feature requests, please refer to the plugin documentation or contact the plugin author.