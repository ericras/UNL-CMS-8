<?php

/**
 * @file
 * User page callbacks for the Aggregator module.
 */

use Drupal\aggregator\Entity\Feed;
use Drupal\Core\Entity\EntityInterface;

/**
 * Loads and optionally filters feed items.
 *
 * @param string $type
 *   The type of filter for the items. Possible values are:
 *   - sum: No filtering.
 *   - source: Filter the feed items, limiting the result to items from a
 *     single source.
 * @param mixed $data
 *   Feed data used for filtering. The type and value of $data depends on $type:
 *   - source: $data is an object with $data->fid identifying the feed used to
 *     as filter.
 *   The $data parameter is not used when $type is 'sum'.
 * @param int $limit
 *   (optional) The number of records to return. Defaults to 20.
 *
 * @deprecated Use \Drupal\aggregator\ItemStorageController::loadAll() for
 *   loading all feed items, \Drupal\aggregator\ItemStorageController::loadByFeed()
 *
 * @return \Drupal\aggregator\ItemInterface[]
 *   An array of the feed items.
 */
function aggregator_load_feed_items($type, $data = NULL, $limit = 20) {
  $storage_controller = \Drupal::entityManager()->getStorageController('aggregator_item');
  switch ($type) {
    case 'sum':
      return $storage_controller->loadAll($limit);

    case 'source':
      return $storage_controller->loadByFeed($data->id(), $limit);
  }
}

/**
 * Prepares variables for aggregator item templates.
 *
 * Default template: aggregator-item.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - aggregator_item: An individual feed item for display on the aggregator
 *     page.
 */
function template_preprocess_aggregator_item(&$variables) {
  $item = $variables['aggregator_item'];

  $variables['feed_url'] = check_url($item->getLink());
  $variables['feed_title'] = check_plain($item->getTitle());
  $variables['content'] = aggregator_filter_xss($item->getDescription());

  $variables['source_url'] = '';
  $variables['source_title'] = '';
  $fid = $item->getFeedId();
  if (isset($item->ftitle) && $fid !== NULL) {
    $variables['source_url'] = url('aggregator/sources/' . $fid);
    $variables['source_title'] = check_plain($item->ftitle);
  }
  if (date('Ymd', $item->getPostedTime()) == date('Ymd')) {
    $variables['source_date'] = t('%ago ago', array('%ago' => format_interval(REQUEST_TIME - $item->getPostedTime())));
  }
  else {
    $variables['source_date'] = format_date($item->getPostedTime(), 'medium');
  }

  $variables['attributes']['class'][] = 'feed-item';
}

/**
 * Prints the OPML page for the feed.
 *
 * @param array $variables
 *   An associative array containing:
 *   - feeds: An array of the feeds to theme.
 *
 * @return string
 *   An OPML formatted string.
 *
 * @ingroup themeable
 */
function theme_aggregator_page_opml($variables) {
  $feeds = $variables['feeds'];

  $output  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
  $output .= "<opml version=\"1.1\">\n";
  $output .= "<head>\n";
  $output .= '<title>' . check_plain(\Drupal::config('system.site')->get('name')) . "</title>\n";
  $output .= '<dateModified>' . gmdate(DATE_RFC2822, REQUEST_TIME) . "</dateModified>\n";
  $output .= "</head>\n";
  $output .= "<body>\n";
  foreach ($feeds as $feed) {
    $output .= '<outline text="' . check_plain($feed->title) . '" xmlUrl="' . check_url($feed->url) . "\" />\n";
  }
  $output .= "</body>\n";
  $output .= "</opml>\n";

  return $output;
}

/**
 * Prepares variables for aggregator summary templates.
 *
 * Default template: aggregator-summary-items.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - source: A Drupal\aggregator\Entity\Feed instance representing
 *     the feed source.
 *   - summary_items: An array of feed items.
 */
function template_preprocess_aggregator_summary_items(&$variables) {
  $variables['title'] = check_plain($variables['source'] instanceof EntityInterface ? $variables['source']->label() : $variables['source']->title);
  $summary_items = array();
  foreach (element_children($variables['summary_items']) as $key) {
    $summary_items[] = $variables['summary_items'][$key];
  }
  $variables['summary_list'] = array(
    '#theme' => 'item_list',
    '#items' => $summary_items,
  );
  $variables['source_url'] = $variables['source'] instanceof EntityInterface ? $variables['source']->url->value : $variables['source']->url;
}

/**
 * Processes variables for aggregator summary item templates.
 *
 * Default template: aggregator-summary-item.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - aggregator_item: The feed item.
 *   - view_mode: How the item is being displayed.
 */
function template_preprocess_aggregator_summary_item(&$variables) {
  $item = $variables['aggregator_item'];

  $variables['url'] = l(check_plain($item->label()), check_url(url($item->getLink(), array('absolute' => TRUE))), array(
    'attributes' => array(
      'class' => array('feed-item-url'),
    ),
  ));
  $variables['age'] = array(
    '#theme' => 'datetime',
    '#attributes' => array(
      'datetime' => format_date($item->getPostedTime(), 'html_datetime', '', 'UTC'),
      'class' => array('feed-item-age',),
    ),
    '#text' => t('%age old', array('%age' => format_interval(REQUEST_TIME - $item->getPostedTime()))),
    '#html' => TRUE,
  );
}

/**
 * Prepares variables for aggregator feed source templates.
 *
 * Default template: aggregator-feed-source.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - aggregator_feed: A Drupal\aggregator\Entity\Feed instance
 *     representing the feed source.
 */
function template_preprocess_aggregator_feed_source(&$variables) {
  $feed = $variables['aggregator_feed'];

  $feed_icon = array(
    '#theme' => 'feed_icon',
    '#url' => $feed->url->value,
    '#title' => t('!title feed', array('!title' => $feed->label())),
  );
  $variables['source_icon'] = drupal_render($feed_icon);

  if (!empty($feed->image->value) && $feed->label() && !empty($feed->link->value)) {
    $image = array(
      '#theme' => 'image',
      '#path' => $feed->image->value,
      '#alt' => $feed->title->value,
    );
    $variables['source_image'] = l($image, $feed->link->value, array('html' => TRUE, 'attributes' => array('class' => 'feed-image')));
  }
  else {
    $variables['source_image'] = '';
  }
  $variables['source_description'] = aggregator_filter_xss($feed->description->value);
  $variables['source_url'] = check_url(url($feed->link->value, array('absolute' => TRUE)));

  if ($feed->checked) {
    $variables['last_checked'] = t('@time ago', array('@time' => format_interval(REQUEST_TIME - $feed->checked->value)));
  }
  else {
    $variables['last_checked'] = t('never');
  }

  if (\Drupal::currentUser()->hasPermission('administer news feeds')) {
    $variables['last_checked'] = l($variables['last_checked'], 'admin/config/services/aggregator');
  }

  $variables['attributes']['class'][] = 'feed-source';
}
