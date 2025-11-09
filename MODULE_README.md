# Facebook Autopost Module for Drupal 11

This module automatically posts article nodes to configured Facebook Pages and Groups when they are created.

## Features

- Automatically posts new article nodes to Facebook Pages and Groups
- Support for multiple Facebook Pages and Groups
- Domain module integration - restrict posting to specific domains
- Configurable post options (include image, body excerpt)
- Posts article title, body excerpt, featured image, and link
- Tracks posting status with a boolean field
- Admin UI for managing Facebook Page and Group configurations

## Requirements

- Drupal 11
- Domain module (https://www.drupal.org/project/domain)
- Article content type with:
  - `title` (default field)
  - `body` (default field)
  - `field_image` (image field)
- Facebook Page Access Token(s) for Pages
- Facebook User Access Token with `publish_to_groups` permission for Groups

## Installation

1. Copy this module to your Drupal modules directory (e.g., `modules/custom/facebook_autopost`)
2. Enable the module: `drush en facebook_autopost`
3. Configure the module at: `/admin/config/services/facebook-autopost`

## Configuration

### Getting Facebook Access Tokens

**For Facebook Pages:**

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app or use an existing one
3. Add the "Pages" product to your app
4. Go to Tools & Support > Access Token Tool
5. Generate a Page Access Token for your page
6. Copy the token (you'll need it for configuration)

**For Facebook Groups:**

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app or use an existing one
3. Request `publish_to_groups` permission for your app
4. Generate a User Access Token with `publish_to_groups` permission
5. Find your Group ID from the group's About section or URL
6. Copy both the token and Group ID (you'll need them for configuration)

### Module Configuration

1. Navigate to **Configuration > Services > Facebook Autopost Settings** (`/admin/config/services/facebook-autopost`)
2. Check "Enable Facebook Autoposting"
3. **Domain Settings** (optional):
   - Select which domains should trigger Facebook posting
   - Only articles from selected domains will be posted
   - Leave all unchecked to post from all domains
   - Works with both Domain Access and Domain Source modules
4. Add your Facebook Page(s) or Group(s):
   - **Type**: Select "Facebook Page" or "Facebook Group"
   - **Name**: A friendly name for identification
   - **Page/Group ID**: Your Facebook Page ID or Group ID
   - **Access Token**: Page Access Token (for Pages) or User Access Token (for Groups)
   - **Enabled**: Check to enable posting to this destination
5. Configure post options:
   - **Include featured image**: Posts the `field_image` with the article
   - **Include body excerpt**: Includes a portion of the body text
   - **Body excerpt length**: Maximum characters from body (50-1000)
6. Save configuration

## How It Works

1. When an article node is created **or updated**, the module:
   - Checks if `field_facebook_posted` is unchecked (allows reposting if manually unchecked)
   - Checks if Facebook autoposting is enabled
   - Checks if the node's domain matches the configured enabled domains (if any)
   - Builds a message with the article title, body excerpt, and URL
   - Posts to all enabled Facebook Pages and Groups
   - If the article has a featured image (`field_image`), it posts as a photo
   - Otherwise, it posts as a status update
   - Marks the node as posted using the `field_facebook_posted` field
   - Logs detailed information to watchdog for debugging and auditing

2. Domain filtering:
   - If you have Domain Access module: checks `field_domain_access` for matching domains
   - If you have Domain Source module: checks `field_domain_source` for matching domain
   - If specific domains are configured, only articles from those domains will be posted
   - If no domains are configured, all articles will be posted

3. The `field_facebook_posted` field:
   - Automatically created on the article content type during installation
   - Prevents duplicate posts if the node is saved multiple times
   - Can be manually unchecked to allow reposting on next save/update
   - Visible in the node edit/view forms

4. Comprehensive logging:
   - All posting activity is logged to Drupal watchdog (Reports > Recent log messages)
   - Logs include: node operations, domain filtering, API requests/responses, errors
   - Full API response bodies for debugging
   - Stack traces for exceptions
   - Success/failure counts for each posting operation

## Permissions

- **Administer Facebook Autopost**: Required to access and configure the settings page

Grant this permission to roles that should manage Facebook integration.

## Troubleshooting

### Posts are not appearing on Facebook

- Check that the module is enabled at the settings page
- Verify your Access Token is valid and has not expired
- For Groups: Ensure you're using a User Access Token with `publish_to_groups` permission
- For Pages: Ensure you're using a Page Access Token
- Check the Drupal logs (Reports > Recent log messages) for errors
- Ensure your Facebook app has the necessary permissions

### Images are not posting

- Verify that the article has an image in the `field_image` field
- Check that "Include featured image" is enabled in settings
- Ensure the image is publicly accessible (absolute URL required)

### Multiple posts for the same article

- The module checks the `field_facebook_posted` field to prevent duplicates
- If you're seeing duplicates, verify this field exists and is functioning

### Articles not posting from specific domains

- Check the Domain Settings in the configuration page
- Verify the article is assigned to one of the enabled domains
- Check the Drupal logs for domain-related messages
- Ensure Domain Access or Domain Source module is installed and configured

### How to view detailed logs

1. Navigate to **Reports > Recent log messages** (`/admin/reports/dblog`)
2. Filter by type: `facebook_autopost`
3. Review detailed logs including:
   - Node processing operations (insert/update)
   - Domain filtering decisions
   - Message building details
   - API requests and responses
   - Success/failure status for each destination
   - Error messages and stack traces

### How to repost an article to Facebook

1. Edit the article node
2. Uncheck the "Posted to Facebook" field (`field_facebook_posted`)
3. Save the node
4. The module will automatically post to Facebook again
5. Check watchdog logs for posting status

## API Usage

You can also programmatically post nodes to Facebook:

```php
$facebook_api = \Drupal::service('facebook_autopost.api');
$results = $facebook_api->postNode($node);

foreach ($results as $result) {
  if ($result['success']) {
    \Drupal::logger('my_module')->info('Posted to ' . $result['page_name']);
  } else {
    \Drupal::logger('my_module')->error('Failed: ' . $result['error']);
  }
}
```

## Uninstallation

When uninstalling the module:
- Configuration will be deleted
- The `field_facebook_posted` field will be removed from article nodes
- Historical data about which articles were posted will be lost

## Support

For issues and feature requests, please use the project's issue queue.

## License

GPL-2.0+
