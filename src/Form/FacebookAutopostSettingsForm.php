<?php

namespace Drupal\facebook_autopost\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Facebook Autopost settings.
 */
class FacebookAutopostSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['facebook_autopost.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facebook_autopost_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('facebook_autopost.settings');

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('
        <div style="padding: 20px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
          <h2 style="margin-top: 0;">Facebook Autopost Configuration Guide</h2>
          <p>This module automatically posts article nodes to Facebook Pages and Groups when they are created or updated.</p>
        </div>

        <details open style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
          <summary style="cursor: pointer; font-size: 16px; font-weight: bold; margin-bottom: 10px;">📘 Step 1: Create Facebook App (for Pages)</summary>
          <div style="padding-left: 20px; margin-top: 10px;">
            <ol>
              <li><strong>Go to Facebook Developers:</strong> Visit <a href="https://developers.facebook.com/apps" target="_blank">https://developers.facebook.com/apps</a></li>
              <li><strong>Create New App:</strong> Click "Create App" button</li>
              <li><strong>Select App Type:</strong> Choose "Business" or "Other" as the app type</li>
              <li><strong>Fill in App Details:</strong>
                <ul>
                  <li>App Name: e.g., "My Website Autopost"</li>
                  <li>App Contact Email: Your email address</li>
                  <li>App Purpose: Select appropriate purpose (e.g., "Yourself or your own business")</li>
                </ul>
              </li>
              <li><strong>Create App:</strong> Click "Create App" and complete security check</li>
            </ol>
          </div>
        </details>

        <details open style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
          <summary style="cursor: pointer; font-size: 16px; font-weight: bold; margin-bottom: 10px;">🔑 Step 2: Get Page Access Token (for Pages)</summary>
          <div style="padding-left: 20px; margin-top: 10px;">
            <ol>
              <li><strong>Add Facebook Login Product:</strong> In your app dashboard, click "Add Product" and select "Facebook Login"</li>
              <li><strong>Go to Tools:</strong> Navigate to "Tools" in the left sidebar</li>
              <li><strong>Access Token Tool:</strong> Find and click on "Access Token Tool" or go to <a href="https://developers.facebook.com/tools/accesstoken/" target="_blank">https://developers.facebook.com/tools/accesstoken/</a></li>
              <li><strong>Get Page Access Token:</strong>
                <ul>
                  <li>Find your Facebook Page in the list</li>
                  <li>Click "Generate Token" next to your page</li>
                  <li>Review and accept the permissions requested</li>
                  <li>Copy the generated Page Access Token (starts with "EAAA...")</li>
                </ul>
              </li>
              <li><strong>Get Page ID:</strong>
                <ul>
                  <li>Go to your Facebook Page</li>
                  <li>Click "About" in the left menu</li>
                  <li>Scroll down to find "Page ID" (numeric ID)</li>
                  <li>Or find it in the URL when viewing your page</li>
                </ul>
              </li>
              <li><strong>Important:</strong> Page Access Tokens can expire. For long-term use, you may need to exchange for a long-lived token or use a System User token</li>
            </ol>
          </div>
        </details>

        <details style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
          <summary style="cursor: pointer; font-size: 16px; font-weight: bold; margin-bottom: 10px;">👥 Step 3: Configure for Groups (Optional)</summary>
          <div style="padding-left: 20px; margin-top: 10px;">
            <ol>
              <li><strong>Request publish_to_groups Permission:</strong>
                <ul>
                  <li>In your app dashboard, go to "App Review" > "Permissions and Features"</li>
                  <li>Find "publish_to_groups" and click "Request"</li>
                  <li>Provide required information and submit for review</li>
                  <li>Note: This permission requires Facebook review and approval</li>
                </ul>
              </li>
              <li><strong>Generate User Access Token:</strong>
                <ul>
                  <li>Go to <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
                  <li>Select your app from the dropdown</li>
                  <li>Click "Generate Access Token"</li>
                  <li>Check the "publish_to_groups" permission</li>
                  <li>Generate and copy the User Access Token</li>
                </ul>
              </li>
              <li><strong>Get Group ID:</strong>
                <ul>
                  <li>Go to your Facebook Group</li>
                  <li>Look in the URL: facebook.com/groups/GROUP_ID</li>
                  <li>Or go to Group Settings > Group Info to find the Group ID</li>
                </ul>
              </li>
              <li><strong>Important:</strong> User Access Tokens expire. Consider using a long-lived token for production use</li>
            </ol>
          </div>
        </details>

        <details style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
          <summary style="cursor: pointer; font-size: 16px; font-weight: bold; margin-bottom: 10px;">🔒 Step 4: App Permissions & Settings</summary>
          <div style="padding-left: 20px; margin-top: 10px;">
            <h4>Required Permissions for Pages:</h4>
            <ul>
              <li><code>pages_manage_posts</code> - Manage and publish Page posts</li>
              <li><code>pages_read_engagement</code> - Read Page engagement data</li>
              <li><code>pages_show_list</code> - Show list of Pages</li>
            </ul>
            <h4>Required Permissions for Groups:</h4>
            <ul>
              <li><code>publish_to_groups</code> - Publish posts to groups (requires Facebook review)</li>
            </ul>
            <h4>App Settings:</h4>
            <ul>
              <li>Make sure your app is in "Live" mode (not Development mode) for production use</li>
              <li>Add your domain to "App Domains" in Settings > Basic</li>
              <li>Configure "Privacy Policy URL" and "Terms of Service URL" if required</li>
            </ul>
          </div>
        </details>

        <details style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
          <summary style="cursor: pointer; font-size: 16px; font-weight: bold; margin-bottom: 10px;">⚠️ Troubleshooting & Best Practices</summary>
          <div style="padding-left: 20px; margin-top: 10px;">
            <h4>Token Expiration:</h4>
            <ul>
              <li>Short-lived tokens expire in 1-2 hours</li>
              <li>Long-lived User tokens expire in 60 days</li>
              <li>Page tokens can be made permanent by using System User</li>
              <li>Check token expiration at <a href="https://developers.facebook.com/tools/debug/accesstoken/" target="_blank">Access Token Debugger</a></li>
            </ul>
            <h4>Common Issues:</h4>
            <ul>
              <li><strong>Token expired:</strong> Regenerate token using the steps above</li>
              <li><strong>Permission denied:</strong> Ensure all required permissions are granted</li>
              <li><strong>App not live:</strong> Switch app to Live mode in App Settings</li>
              <li><strong>Invalid Page ID:</strong> Verify Page ID is correct (numeric only)</li>
            </ul>
            <h4>Testing:</h4>
            <ul>
              <li>Use <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a> to test API calls</li>
              <li>Check Drupal logs at Reports > Recent log messages for detailed error info</li>
              <li>Create a test article to verify posting works correctly</li>
            </ul>
          </div>
        </details>

        <div style="background: #fffbea; padding: 15px; border-left: 4px solid #f0ad4e; margin-top: 20px;">
          <strong>💡 Quick Links:</strong>
          <ul style="margin: 10px 0 0 0;">
            <li><a href="https://developers.facebook.com/apps" target="_blank">Facebook App Dashboard</a></li>
            <li><a href="https://developers.facebook.com/tools/accesstoken/" target="_blank">Access Token Tool</a></li>
            <li><a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
            <li><a href="https://developers.facebook.com/tools/debug/accesstoken/" target="_blank">Access Token Debugger</a></li>
            <li><a href="https://developers.facebook.com/docs/graph-api" target="_blank">Graph API Documentation</a></li>
          </ul>
        </div>
      '),
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Facebook Autoposting'),
      '#default_value' => $config->get('enabled') ?? FALSE,
      '#description' => $this->t('Enable or disable automatic posting to Facebook when articles are created.'),
    ];

    $form['detailed_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Detailed Logging'),
      '#default_value' => $config->get('detailed_logging') ?? TRUE,
      '#description' => $this->t('Enable detailed logging to Drupal watchdog for debugging. Errors will always be logged. Disable this for production to reduce log volume.'),
    ];

    // Domain selection.
    $form['domains'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Domain Settings'),
      '#description' => $this->t('Select which domains should trigger Facebook posting when articles are created.'),
    ];

    // Load all domains.
    $domain_storage = \Drupal::entityTypeManager()->getStorage('domain');
    $domains = $domain_storage->loadMultiple();

    if (!empty($domains)) {
      $domain_options = [];
      foreach ($domains as $domain_id => $domain) {
        $domain_options[$domain_id] = $domain->label() . ' (' . $domain->getHostname() . ')';
      }

      $form['domains']['enabled_domains'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Enable posting for these domains'),
        '#options' => $domain_options,
        '#default_value' => $config->get('enabled_domains') ?? [],
        '#description' => $this->t('Only articles from the selected domains will be posted to Facebook. Leave all unchecked to post from all domains.'),
      ];
    }
    else {
      $form['domains']['no_domains'] = [
        '#type' => 'markup',
        '#markup' => $this->t('<p><em>No domains found. Please create domains using the Domain module first.</em></p>'),
      ];
    }

    $form['pages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Facebook Pages & Groups'),
      '#tree' => TRUE,
      '#prefix' => '<div id="facebook-pages-wrapper">',
      '#suffix' => '</div>',
    ];

    $pages = $config->get('pages') ?? [];
    $num_pages = $form_state->get('num_pages');

    if ($num_pages === NULL) {
      $num_pages = !empty($pages) ? count($pages) : 1;
      $form_state->set('num_pages', $num_pages);
    }

    for ($i = 0; $i < $num_pages; $i++) {
      $form['pages'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Destination @num', ['@num' => $i + 1]),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['pages'][$i]['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#options' => [
          'page' => $this->t('Facebook Page'),
          'group' => $this->t('Facebook Group'),
        ],
        '#default_value' => $pages[$i]['type'] ?? 'page',
        '#description' => $this->t('Select whether this is a Page or Group.'),
      ];

      $form['pages'][$i]['page_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $pages[$i]['page_name'] ?? '',
        '#description' => $this->t('A friendly name to identify this page or group.'),
      ];

      $form['pages'][$i]['page_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Page/Group ID'),
        '#default_value' => $pages[$i]['page_id'] ?? '',
        '#description' => $this->t('The Facebook Page ID or Group ID.'),
      ];

      $form['pages'][$i]['access_token'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Access Token'),
        '#default_value' => $pages[$i]['access_token'] ?? '',
        '#description' => $this->t('Page Access Token (for Pages) or User Access Token with publish_to_groups permission (for Groups).'),
        '#rows' => 3,
      ];

      $form['pages'][$i]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $pages[$i]['enabled'] ?? TRUE,
      ];
    }

    $form['pages']['add_page'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Another Destination'),
      '#submit' => ['::addPage'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'facebook-pages-wrapper',
      ],
    ];

    if ($num_pages > 1) {
      $form['pages']['remove_page'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove Last Destination'),
        '#submit' => ['::removePage'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'facebook-pages-wrapper',
        ],
      ];
    }

    $form['post_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Post Options'),
    ];

    $form['post_options']['include_image'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include featured image'),
      '#default_value' => $config->get('post_options.include_image') ?? TRUE,
      '#description' => $this->t('Include the field_image in the Facebook post.'),
    ];

    $form['post_options']['include_body'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include body excerpt'),
      '#default_value' => $config->get('post_options.include_body') ?? TRUE,
      '#description' => $this->t('Include a portion of the body text in the post.'),
    ];

    $form['post_options']['body_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Body excerpt length'),
      '#default_value' => $config->get('post_options.body_length') ?? 200,
      '#description' => $this->t('Maximum number of characters from the body to include.'),
      '#min' => 50,
      '#max' => 1000,
      '#states' => [
        'visible' => [
          ':input[name="post_options[include_body]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback for add/remove page buttons.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['pages'];
  }

  /**
   * Submit handler for "Add Page" button.
   */
  public function addPage(array &$form, FormStateInterface $form_state) {
    $num_pages = $form_state->get('num_pages');
    $form_state->set('num_pages', $num_pages + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for "Remove Page" button.
   */
  public function removePage(array &$form, FormStateInterface $form_state) {
    $num_pages = $form_state->get('num_pages');
    if ($num_pages > 1) {
      $form_state->set('num_pages', $num_pages - 1);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pages = $form_state->getValue('pages');

    // Remove the button elements.
    unset($pages['add_page']);
    unset($pages['remove_page']);

    // Filter out empty pages.
    $pages = array_filter($pages, function ($page) {
      return !empty($page['page_id']) && !empty($page['access_token']);
    });

    // Re-index the array.
    $pages = array_values($pages);

    // Get enabled domains and filter out unchecked ones.
    $enabled_domains = $form_state->getValue('enabled_domains') ?? [];
    $enabled_domains = array_filter($enabled_domains);

    $this->config('facebook_autopost.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('detailed_logging', $form_state->getValue('detailed_logging'))
      ->set('enabled_domains', array_keys($enabled_domains))
      ->set('pages', $pages)
      ->set('post_options', $form_state->getValue('post_options'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
