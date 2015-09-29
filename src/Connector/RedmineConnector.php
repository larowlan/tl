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
   * @param string version
   */
  public function __construct(ClientInterface $httpClient, Cache $cache, array $config, $version) {
    $this->httpClient = $httpClient;
    $this->cache = $cache;
    $this->url = $config['url'];
    $this->apiKey = $config['api_key'];
    $this->version = $version;
  }

  /**
   * {@inheritdoc}
   */
  public function ticketDetails($id) {
    if (($details = $this->cache->fetch($this->version . ':' . $id))) {
      return $details;
    }
    // We need to fetch it.
    $url = $this->url . '/issues/' . $id . '.xml';
    if ($xml = $this->fetch($url, $this->apiKey)) {
      $entry = array(
        'title' => $xml->subject . ' (' . $xml->project['name'] . ')',
        'project' => (string) $xml->project['id'],
      );
      $this->cache->save($this->version . ':' . $id, $entry, static::LIFETIME);
      return $entry;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCategories() {
    $cid = 'redmine-categories';
    if (($details = $this->cache->fetch($this->version . ':' . $cid))) {
      return $details;
    }
    $url = $this->url . '/enumerations/time_entry_activities.xml';
    if ($xml = $this->fetch($url, $this->apiKey)) {
      $categories = array();
      $i = 1;
      foreach ($xml->time_entry_activity as $node) {
        $categories[(string) str_pad($node->id, 2, 0, STR_PAD_LEFT)] = $node->name . ':' . $node->id;
        $i++;
      }
      $this->cache->save($this->version . ':' . $cid, $categories, static::LIFETIME);
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
    $url = $this->url . '/time_entries.xml';
    $details = $this->ticketDetails($entry->tid);
    $data = [
      'issue_id'    => $entry->tid,
      'project_id'  => $details['project'],
      'spent_on'    => date('Y-m-d', $entry->start),
      'hours'       => $entry->duration,
      'activity_id' => $entry->category,
      'comments'    => $entry->comment,
    ];
    $xml = new \SimpleXMLElement('<?xml version="1.0"?><time_entry></time_entry>');
    foreach ($data as $key => $value) {
      $xml->addChild($key, $value);
    }
    $response = $this->httpClient->request('POST', $url, [
      'headers' => [
        'Content-Type' => 'application/xml',
        'X-Redmine-API-Key' => $this->apiKey,
      ],
      'body' => $xml->asXml(),
    ]);
    if (in_array(substr($response->getStatusCode(), 0, 1), [2, 3])) {
      $return = new \SimpleXMLElement((string) $response->getBody());
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
