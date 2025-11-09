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
      '#markup' => $this->t('<p>Configure Facebook Pages and Groups for automatic posting.</p>
        <p><strong>For Pages - Get Page Access Token:</strong></p>
        <ol>
          <li>Go to <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a></li>
          <li>Create an app or use an existing one</li>
          <li>Go to Tools & Support > Access Token Tool</li>
          <li>Generate a Page Access Token for your page</li>
          <li>Copy the token and paste it below</li>
        </ol>
        <p><strong>For Groups - Get User Access Token:</strong></p>
        <ol>
          <li>Go to <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a></li>
          <li>Create an app with "publish_to_groups" permission</li>
          <li>Generate a User Access Token with "publish_to_groups" permission</li>
          <li>Get your Group ID from the group\'s About section</li>
          <li>Copy the token and Group ID below</li>
        </ol>'),
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Facebook Autoposting'),
      '#default_value' => $config->get('enabled') ?? FALSE,
      '#description' => $this->t('Enable or disable automatic posting to Facebook when articles are created.'),
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
      ->set('enabled_domains', array_keys($enabled_domains))
      ->set('pages', $pages)
      ->set('post_options', $form_state->getValue('post_options'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
