<?php

namespace Drupal\facebook_autopost\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for interacting with the Facebook Graph API.
 */
class FacebookApiService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a FacebookApiService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface $file_system,
    FileUrlGeneratorInterface $file_url_generator
  ) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('facebook_autopost');
    $this->fileSystem = $file_system;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Check if detailed logging is enabled.
   *
   * @return bool
   *   TRUE if detailed logging is enabled.
   */
  protected function isDetailedLoggingEnabled() {
    $config = $this->configFactory->get('facebook_autopost.settings');
    return $config->get('detailed_logging') ?? TRUE;
  }

  /**
   * Posts a node to all configured Facebook pages and groups.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to post.
   *
   * @return array
   *   An array of results with page_id and success status.
   */
  public function postNode(NodeInterface $node) {
    $config = $this->configFactory->get('facebook_autopost.settings');
    $results = [];

    if (!$config->get('enabled')) {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Facebook autoposting is disabled in configuration.');
      }
      return $results;
    }

    $pages = $config->get('pages') ?? [];

    if (empty($pages)) {
      $this->logger->warning('No Facebook destinations configured. Skipping posting for node @nid.', [
        '@nid' => $node->id(),
      ]);
      return $results;
    }

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Starting Facebook posting for node @nid to @count destination(s).', [
        '@nid' => $node->id(),
        '@count' => count($pages),
      ]);
    }

    foreach ($pages as $index => $page) {
      if (empty($page['enabled'])) {
        if ($this->isDetailedLoggingEnabled()) {
          $this->logger->info('Destination @index (@name) is disabled. Skipping.', [
            '@index' => $index + 1,
            '@name' => $page['page_name'] ?? 'Unknown',
          ]);
        }
        continue;
      }

      $type = $page['type'] ?? 'page';
      $destination_type = ($type === 'group') ? 'group' : 'page';

      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Processing destination @index: @name (Type: @type, ID: @id)', [
          '@index' => $index + 1,
          '@name' => $page['page_name'],
          '@type' => $destination_type,
          '@id' => $page['page_id'],
        ]);
      }

      try {
        $result = $this->postToDestination($node, $page);
        $results[] = [
          'page_id' => $page['page_id'],
          'page_name' => $page['page_name'],
          'type' => $destination_type,
          'success' => $result['success'],
          'post_id' => $result['post_id'] ?? NULL,
          'error' => $result['error'] ?? NULL,
        ];

        if ($result['success']) {
          if ($this->isDetailedLoggingEnabled()) {
            $this->logger->info('✓ Successfully posted node @nid to Facebook @type "@name" (ID: @id). Post ID: @post_id', [
              '@nid' => $node->id(),
              '@type' => $destination_type,
              '@name' => $page['page_name'],
              '@id' => $page['page_id'],
              '@post_id' => $result['post_id'],
            ]);
          }
        }
        else {
          $this->logger->error('✗ Failed to post node @nid to Facebook @type "@name" (ID: @id). Error: @error', [
            '@nid' => $node->id(),
            '@type' => $destination_type,
            '@name' => $page['page_name'],
            '@id' => $page['page_id'],
            '@error' => $result['error'],
          ]);
        }
      }
      catch (\Exception $e) {
        $this->logger->error('✗ Exception when posting node @nid to Facebook @type "@name" (ID: @id): @message. Stack trace: @trace', [
          '@nid' => $node->id(),
          '@type' => $destination_type,
          '@name' => $page['page_name'],
          '@id' => $page['page_id'],
          '@message' => $e->getMessage(),
          '@trace' => $e->getTraceAsString(),
        ]);
        $results[] = [
          'page_id' => $page['page_id'],
          'page_name' => $page['page_name'],
          'type' => $destination_type,
          'success' => FALSE,
          'error' => $e->getMessage(),
        ];
      }
    }

    return $results;
  }

  /**
   * Posts a node to a specific Facebook page or group.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to post.
   * @param array $destination
   *   The destination configuration (page or group).
   *
   * @return array
   *   Result array with success status and optional post_id or error.
   */
  protected function postToDestination(NodeInterface $node, array $destination) {
    $type = $destination['type'] ?? 'page';

    if ($type === 'group') {
      return $this->postToGroup($node, $destination);
    }
    else {
      return $this->postToPage($node, $destination);
    }
  }

  /**
   * Posts a node to a specific Facebook page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to post.
   * @param array $page
   *   The page configuration.
   *
   * @return array
   *   Result array with success status and optional post_id or error.
   */
  protected function postToPage(NodeInterface $node, array $page) {
    $config = $this->configFactory->get('facebook_autopost.settings');
    $post_options = $config->get('post_options') ?? [];

    // Build the message.
    $message = $this->buildMessage($node, $post_options);

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Built message for Page @id. Message length: @length characters.', [
        '@id' => $page['page_id'],
        '@length' => mb_strlen($message),
      ]);
    }

    // Check if we need to include an image.
    $include_image = $post_options['include_image'] ?? TRUE;
    $image_url = NULL;

    if ($include_image && $node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
      $image_entity = $node->get('field_image')->entity;
      if ($image_entity) {
        $image_url = $this->fileUrlGenerator->generateAbsoluteString($image_entity->getFileUri());
        if ($this->isDetailedLoggingEnabled()) {
          $this->logger->info('Found image for Page @id: @url', [
            '@id' => $page['page_id'],
            '@url' => $image_url,
          ]);
        }
      }
    }
    else {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('No image to include for Page @id (include_image: @include, has_field: @has, is_empty: @empty)', [
          '@id' => $page['page_id'],
          '@include' => $include_image ? 'TRUE' : 'FALSE',
          '@has' => $node->hasField('field_image') ? 'TRUE' : 'FALSE',
          '@empty' => ($node->hasField('field_image') && $node->get('field_image')->isEmpty()) ? 'TRUE' : 'FALSE',
        ]);
      }
    }

    // Post to Facebook.
    if ($image_url) {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Posting to Page @id with photo.', [
          '@id' => $page['page_id'],
        ]);
      }
      return $this->postPhoto($page['page_id'], $page['access_token'], $message, $image_url);
    }
    else {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Posting to Page @id as status update.', [
          '@id' => $page['page_id'],
        ]);
      }
      return $this->postStatus($page['page_id'], $page['access_token'], $message);
    }
  }

  /**
   * Builds the message to post to Facebook.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $post_options
   *   The post options.
   *
   * @return string
   *   The message.
   */
  protected function buildMessage(NodeInterface $node, array $post_options) {
    $message = $node->getTitle();

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Building message for node @nid. Title: "@title"', [
        '@nid' => $node->id(),
        '@title' => $node->getTitle(),
      ]);
    }

    $include_body = $post_options['include_body'] ?? TRUE;
    if ($include_body && $node->hasField('body') && !$node->get('body')->isEmpty()) {
      $body = $node->get('body')->value;
      // Strip HTML tags.
      $body = strip_tags($body);
      // Limit length.
      $max_length = $post_options['body_length'] ?? 200;
      $original_length = mb_strlen($body);
      if (mb_strlen($body) > $max_length) {
        $body = mb_substr($body, 0, $max_length) . '...';
      }
      $message .= "\n\n" . $body;

      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Added body excerpt (original: @original chars, truncated to: @truncated chars)', [
          '@original' => $original_length,
          '@truncated' => $max_length,
        ]);
      }
    }
    else {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Body excerpt not included (include_body: @include)', [
          '@include' => $include_body ? 'TRUE' : 'FALSE',
        ]);
      }
    }

    // Add link to the node.
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
    $message .= "\n\n" . $url;

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Added node URL: @url', [
        '@url' => $url,
      ]);
    }

    return $message;
  }

  /**
   * Posts a status message to a Facebook page.
   *
   * @param string $page_id
   *   The page ID.
   * @param string $access_token
   *   The page access token.
   * @param string $message
   *   The message to post.
   *
   * @return array
   *   Result array.
   */
  protected function postStatus($page_id, $access_token, $message) {
    $url = "https://graph.facebook.com/v18.0/{$page_id}/feed";

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Making API request to Facebook Page @page_id: POST @url', [
        '@page_id' => $page_id,
        '@url' => $url,
      ]);
    }

    try {
      $response = $this->httpClient->post($url, [
        'form_params' => [
          'message' => $message,
          'access_token' => $access_token,
        ],
      ]);

      $status_code = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Facebook API response (Page @page_id): Status @status, Body: @body', [
          '@page_id' => $page_id,
          '@status' => $status_code,
          '@body' => $body,
        ]);
      }

      $data = json_decode($body, TRUE);

      if (isset($data['id'])) {
        if ($this->isDetailedLoggingEnabled()) {
          $this->logger->info('Facebook Page post successful. Post ID: @post_id', [
            '@post_id' => $data['id'],
          ]);
        }
        return [
          'success' => TRUE,
          'post_id' => $data['id'],
        ];
      }
      else {
        $this->logger->error('No post ID returned from Facebook Page @page_id. Response: @response', [
          '@page_id' => $page_id,
          '@response' => $body,
        ]);
        return [
          'success' => FALSE,
          'error' => 'No post ID returned from Facebook',
        ];
      }
    }
    catch (GuzzleException $e) {
      $this->logger->error('Facebook API error (Page @page_id): @error', [
        '@page_id' => $page_id,
        '@error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Posts a photo to a Facebook page.
   *
   * @param string $page_id
   *   The page ID.
   * @param string $access_token
   *   The page access token.
   * @param string $message
   *   The message to post.
   * @param string $image_url
   *   The image URL.
   *
   * @return array
   *   Result array.
   */
  protected function postPhoto($page_id, $access_token, $message, $image_url) {
    $url = "https://graph.facebook.com/v18.0/{$page_id}/photos";

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Making API request to Facebook Page @page_id with photo: POST @url (image: @image)', [
        '@page_id' => $page_id,
        '@url' => $url,
        '@image' => $image_url,
      ]);
    }

    try {
      $response = $this->httpClient->post($url, [
        'form_params' => [
          'url' => $image_url,
          'caption' => $message,
          'access_token' => $access_token,
        ],
      ]);

      $status_code = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Facebook API response (Page @page_id photo): Status @status, Body: @body', [
          '@page_id' => $page_id,
          '@status' => $status_code,
          '@body' => $body,
        ]);
      }

      $data = json_decode($body, TRUE);

      if (isset($data['id'])) {
        if ($this->isDetailedLoggingEnabled()) {
          $this->logger->info('Facebook Page photo post successful. Post ID: @post_id', [
            '@post_id' => $data['id'],
          ]);
        }
        return [
          'success' => TRUE,
          'post_id' => $data['id'],
        ];
      }
      else {
        $this->logger->error('No post ID returned from Facebook Page @page_id photo post. Response: @response', [
          '@page_id' => $page_id,
          '@response' => $body,
        ]);
        return [
          'success' => FALSE,
          'error' => 'No post ID returned from Facebook',
        ];
      }
    }
    catch (GuzzleException $e) {
      $this->logger->error('Facebook API error (Page @page_id photo): @error', [
        '@page_id' => $page_id,
        '@error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Posts a node to a specific Facebook group.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to post.
   * @param array $group
   *   The group configuration.
   *
   * @return array
   *   Result array with success status and optional post_id or error.
   */
  protected function postToGroup(NodeInterface $node, array $group) {
    $config = $this->configFactory->get('facebook_autopost.settings');
    $post_options = $config->get('post_options') ?? [];

    // Build the message.
    $message = $this->buildMessage($node, $post_options);

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Built message for Group @id. Message length: @length characters.', [
        '@id' => $group['page_id'],
        '@length' => mb_strlen($message),
      ]);
    }

    // Check if we need to include an image.
    $include_image = $post_options['include_image'] ?? TRUE;
    $image_url = NULL;

    if ($include_image && $node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
      $image_entity = $node->get('field_image')->entity;
      if ($image_entity) {
        $image_url = $this->fileUrlGenerator->generateAbsoluteString($image_entity->getFileUri());
        if ($this->isDetailedLoggingEnabled()) {
          $this->logger->info('Found image for Group @id: @url', [
            '@id' => $group['page_id'],
            '@url' => $image_url,
          ]);
        }
      }
    }
    else {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('No image to include for Group @id (include_image: @include, has_field: @has, is_empty: @empty)', [
          '@id' => $group['page_id'],
          '@include' => $include_image ? 'TRUE' : 'FALSE',
          '@has' => $node->hasField('field_image') ? 'TRUE' : 'FALSE',
          '@empty' => ($node->hasField('field_image') && $node->get('field_image')->isEmpty()) ? 'TRUE' : 'FALSE',
        ]);
      }
    }

    // Post to Facebook Group.
    if ($image_url) {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Posting to Group @id with photo.', [
          '@id' => $group['page_id'],
        ]);
      }
      return $this->postGroupPhoto($group['page_id'], $group['access_token'], $message, $image_url);
    }
    else {
      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Posting to Group @id as status update.', [
          '@id' => $group['page_id'],
        ]);
      }
      return $this->postGroupStatus($group['page_id'], $group['access_token'], $message);
    }
  }

  /**
   * Posts a status message to a Facebook group.
   *
   * @param string $group_id
   *   The group ID.
   * @param string $access_token
   *   The user access token with publish_to_groups permission.
   * @param string $message
   *   The message to post.
   *
   * @return array
   *   Result array.
   */
  protected function postGroupStatus($group_id, $access_token, $message) {
    $url = "https://graph.facebook.com/v18.0/{$group_id}/feed";

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Making API request to Facebook Group @group_id: POST @url', [
        '@group_id' => $group_id,
        '@url' => $url,
      ]);
    }

    try {
      $response = $this->httpClient->post($url, [
        'form_params' => [
          'message' => $message,
          'access_token' => $access_token,
        ],
      ]);

      $status_code = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Facebook API response (Group @group_id): Status @status, Body: @body', [
          '@group_id' => $group_id,
          '@status' => $status_code,
          '@body' => $body,
        ]);
      }

      $data = json_decode($body, TRUE);

      if (isset($data['id'])) {
        if ($this->isDetailedLoggingEnabled()) {
          $this->logger->info('Facebook Group post successful. Post ID: @post_id', [
            '@post_id' => $data['id'],
          ]);
        }
        return [
          'success' => TRUE,
          'post_id' => $data['id'],
        ];
      }
      else {
        $this->logger->error('No post ID returned from Facebook Group @group_id. Response: @response', [
          '@group_id' => $group_id,
          '@response' => $body,
        ]);
        return [
          'success' => FALSE,
          'error' => 'No post ID returned from Facebook',
        ];
      }
    }
    catch (GuzzleException $e) {
      $this->logger->error('Facebook API error (Group @group_id): @error', [
        '@group_id' => $group_id,
        '@error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Posts a photo to a Facebook group.
   *
   * @param string $group_id
   *   The group ID.
   * @param string $access_token
   *   The user access token with publish_to_groups permission.
   * @param string $message
   *   The message to post.
   * @param string $image_url
   *   The image URL.
   *
   * @return array
   *   Result array.
   */
  protected function postGroupPhoto($group_id, $access_token, $message, $image_url) {
    $url = "https://graph.facebook.com/v18.0/{$group_id}/photos";

    if ($this->isDetailedLoggingEnabled()) {
      $this->logger->info('Making API request to Facebook Group @group_id with photo: POST @url (image: @image)', [
        '@group_id' => $group_id,
        '@url' => $url,
        '@image' => $image_url,
      ]);
    }

    try {
      $response = $this->httpClient->post($url, [
        'form_params' => [
          'url' => $image_url,
          'caption' => $message,
          'access_token' => $access_token,
        ],
      ]);

      $status_code = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($this->isDetailedLoggingEnabled()) {
        $this->logger->info('Facebook API response (Group @group_id photo): Status @status, Body: @body', [
          '@group_id' => $group_id,
          '@status' => $status_code,
          '@body' => $body,
        ]);
      }

      $data = json_decode($body, TRUE);

      if (isset($data['id'])) {
        if ($this->isDetailedLoggingEnabled()) {
          $this->logger->info('Facebook Group photo post successful. Post ID: @post_id', [
            '@post_id' => $data['id'],
          ]);
        }
        return [
          'success' => TRUE,
          'post_id' => $data['id'],
        ];
      }
      else {
        $this->logger->error('No post ID returned from Facebook Group @group_id photo post. Response: @response', [
          '@group_id' => $group_id,
          '@response' => $body,
        ]);
        return [
          'success' => FALSE,
          'error' => 'No post ID returned from Facebook',
        ];
      }
    }
    catch (GuzzleException $e) {
      $this->logger->error('Facebook API error (Group @group_id photo): @error', [
        '@group_id' => $group_id,
        '@error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

}
