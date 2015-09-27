<?php

/**
 * @file
 * Contains \Larowlan\Tl\Connector\RedmineConnector.
 */

namespace Larowlan\Tl\Connector;

use Doctrine\Common\Cache\Cache;
use GuzzleHttp\ClientInterface;

class RedmineConnector implements Connector {

  protected $httpClient;
  protected $cache;
  protected $url;
  protected $apiKey;
  const LIFETIME = 86400;

  /**
   * Constructs a new RedmineConnector object.
   *
   * @param $httpClient
   * @param $cache
   * @param array $config
   */
  public function __construct(ClientInterface $httpClient, Cache $cache, array $config) {
    $this->httpClient = $httpClient;
    $this->cache = $cache;
    $this->url = $config['url'];
    $this->apiKey = $config['api_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function ticketDetails($id) {
    if (($details = $this->cache->fetch($id))) {
      return $details;
    }
    // We need to fetch it.
    $url = $this->url . '/issues/' . $id . '.xml';
    if ($xml = $this->fetch($url, $this->apiKey)) {
      $entry = array(
        'title' => $xml->subject . ' (' . $xml->project['name'] . ')',
        'project' => (string) $xml->project['id'],
      );
      $this->cache->save($id, $entry, static::LIFETIME);
      return $entry;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCategories() {
    $cid = 'redmine-categories';
    if (($details = $this->cacheGet($cid))) {
      return $details->data;
    }
    $url = variable_get('bot_tl_redmine_url', BOT_TL_DEFAULT_URL) . '/enumerations/time_entry_activities.xml';
    if ($xml = $this->fetch($url, $account->bot_tl->api_key)) {
      $categories = array();
      foreach ($xml->time_entry_activity as $node) {
        $categories[(string) $node->id] = $node->id . ':' . $node->name;
      }
      $this->cacheSet($cid, $categories);
      return $categories;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function sendEntry($entry) {
    if ((float) $entry->duration == 0) {
      // Zero time after rounding.
      // Return 0 to ensure doesn't send again.
      return 0;
    }
    $url = variable_get('bot_tl_redmine_url', BOT_TL_DEFAULT_URL) . '/time_entries.xml';
    $details = $this->ticketDetails($entry->tid, $account);
    $data = array(
      'issue_id'    => $entry->tid,
      'project_id'  => $details['project'],
      'spent_on'    => date('Y-m-d', $entry->start),
      'hours'       => $entry->duration,
      'activity_id' => $entry->category,
      'comments'    => $entry->comment,
    );
    $xml = new \SimpleXMLElement('<?xml version="1.0"?><time_entry></time_entry>');
    foreach ($data as $key => $value) {
      $xml->addChild($key, $value);
    }
    $response = drupal_http_request($url, array(
      'method' => 'POST',
      'headers' => array(
        'Content-Type' => 'application/xml',
        'X-Redmine-API-Key' => $account->bot_tl->api_key,
      ),
      'data' => $xml->asXml(),
    ));
    if (in_array(substr($response->code, 0, 1), array(2, 3))) {
      $return = new \SimpleXMLElement($response->data);
      return (string) $return->id;
    }
    // Try again.
    return NULL;
  }

  /**
   * Fetches an item from redmine.
   *
   * @param string $url
   *   The url to fetch.
   * @param string $redmine_key
   *   Redmine key for request authentication.
   *
   * @return string
   *   The returned object or FALSE if not found.
   */
  protected function fetch($url, $redmine_key) {
    $result = $this->httpClient->request('GET', $url, [
      'headers' => [
        'X-Redmine-API-Key' => $redmine_key,
      ],
    ]);
    if ($result->getStatusCode() != 200) {
      return FALSE;
    }
    $xml = simplexml_load_string((string) $result->getBody());
    if (!$xml) {
      return FALSE;
    }
    return $xml;
  }

}
