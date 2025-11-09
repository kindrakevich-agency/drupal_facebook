# Drupal 11 Facebook Autopost Module

A Drupal 11 module that automatically posts article nodes to Facebook pages when they are created.

## Features

✅ Automatic posting of article nodes to Facebook pages
✅ Support for article nodes with: title, body, field_image
✅ Admin settings page for managing Facebook pages
✅ Posts when new nodes are created
✅ Tracks posting status to prevent duplicates
✅ Support for multiple Facebook pages
✅ Configurable post options (image inclusion, body excerpt length)

## Quick Start

1. **Install the module:**
   ```bash
   # Copy to your Drupal modules directory
   drush en facebook_autopost
   ```

2. **Configure Facebook pages:**
   - Navigate to: `/admin/config/services/facebook-autopost`
   - Enable autoposting
   - Add your Facebook Page ID and Access Token
   - Configure post options

3. **Create an article:**
   - When you create a new article node, it will automatically post to configured Facebook pages
   - The node will be marked as posted via the `field_facebook_posted` field

## Documentation

See [MODULE_README.md](MODULE_README.md) for complete documentation including:
- Detailed installation instructions
- How to get Facebook Page Access Tokens
- Configuration options
- Troubleshooting guide
- API usage examples

## Requirements

- Drupal 11
- Article content type with `field_image` field
- Facebook Page Access Token

## Module Structure

```
facebook_autopost/
├── facebook_autopost.info.yml          # Module definition
├── facebook_autopost.module            # Hooks for node posting
├── facebook_autopost.install           # Install/uninstall hooks
├── facebook_autopost.routing.yml       # Routes definition
├── facebook_autopost.permissions.yml   # Permissions
├── facebook_autopost.services.yml      # Service definitions
└── src/
    ├── Form/
    │   └── FacebookAutopostSettingsForm.php  # Admin settings form
    └── Service/
        └── FacebookApiService.php            # Facebook API integration
```

## License

GPL-2.0+
