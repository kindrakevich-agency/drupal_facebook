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
   * Posts a node to all configured Facebook pages.
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
      $this->logger->info('Facebook autoposting is disabled.');
      return $results;
    }

    $pages = $config->get('pages') ?? [];

    foreach ($pages as $page) {
      if (empty($page['enabled'])) {
        continue;
      }

      try {
        $result = $this->postToPage($node, $page);
        $results[] = [
          'page_id' => $page['page_id'],
          'page_name' => $page['page_name'],
          'success' => $result['success'],
          'post_id' => $result['post_id'] ?? NULL,
          'error' => $result['error'] ?? NULL,
        ];

        if ($result['success']) {
          $this->logger->info('Successfully posted node @nid to Facebook page @page', [
            '@nid' => $node->id(),
            '@page' => $page['page_name'],
          ]);
        }
        else {
          $this->logger->error('Failed to post node @nid to Facebook page @page: @error', [
            '@nid' => $node->id(),
            '@page' => $page['page_name'],
            '@error' => $result['error'],
          ]);
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Exception when posting node @nid to Facebook page @page: @message', [
          '@nid' => $node->id(),
          '@page' => $page['page_name'],
          '@message' => $e->getMessage(),
        ]);
        $results[] = [
          'page_id' => $page['page_id'],
          'page_name' => $page['page_name'],
          'success' => FALSE,
          'error' => $e->getMessage(),
        ];
      }
    }

    return $results;
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

    // Check if we need to include an image.
    $include_image = $post_options['include_image'] ?? TRUE;
    $image_url = NULL;

    if ($include_image && $node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
      $image_entity = $node->get('field_image')->entity;
      if ($image_entity) {
        $image_url = $this->fileUrlGenerator->generateAbsoluteString($image_entity->getFileUri());
      }
    }

    // Post to Facebook.
    if ($image_url) {
      return $this->postPhoto($page['page_id'], $page['access_token'], $message, $image_url);
    }
    else {
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

    $include_body = $post_options['include_body'] ?? TRUE;
    if ($include_body && $node->hasField('body') && !$node->get('body')->isEmpty()) {
      $body = $node->get('body')->value;
      // Strip HTML tags.
      $body = strip_tags($body);
      // Limit length.
      $max_length = $post_options['body_length'] ?? 200;
      if (mb_strlen($body) > $max_length) {
        $body = mb_substr($body, 0, $max_length) . '...';
      }
      $message .= "\n\n" . $body;
    }

    // Add link to the node.
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
    $message .= "\n\n" . $url;

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

    try {
      $response = $this->httpClient->post($url, [
        'form_params' => [
          'message' => $message,
          'access_token' => $access_token,
        ],
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (isset($data['id'])) {
        return [
          'success' => TRUE,
          'post_id' => $data['id'],
        ];
      }
      else {
        return [
          'success' => FALSE,
          'error' => 'No post ID returned from Facebook',
        ];
      }
    }
    catch (GuzzleException $e) {
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

    try {
      $response = $this->httpClient->post($url, [
        'form_params' => [
          'url' => $image_url,
          'caption' => $message,
          'access_token' => $access_token,
        ],
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (isset($data['id'])) {
        return [
          'success' => TRUE,
          'post_id' => $data['id'],
        ];
      }
      else {
        return [
          'success' => FALSE,
          'error' => 'No post ID returned from Facebook',
        ];
      }
    }
    catch (GuzzleException $e) {
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

}
