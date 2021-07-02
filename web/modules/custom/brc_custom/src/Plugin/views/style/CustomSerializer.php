<?php
/**
 * @file
 * Contains \Drupal\brc_custom\Plugin\views\style\CustomSerializer.
 */

namespace Drupal\brc_custom\Plugin\views\style;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\taxonomy\Entity\Term;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

/**
 * Custom serializer
 *
 * @ViewsStyle(
 *   id = "custom_serializer",
 *   title = @Translation("Custom Serializer"),
 *   help = @Translation("Serializes views row data using the Serializer component."),
 *   display_types = {"data"}
 * )
 */
class CustomSerializer extends Serializer implements CacheableDependencyInterface{

  const PAGER_NONE = 'Drupal\views\Plugin\views\pager\None';

  const PAGER_SOME = 'Drupal\views\Plugin\views\pager\Some';

  public function render() {
    $rows = [];

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    unset($this->view->row_index);

    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }


    $nodes = [];
    foreach ($rows as $key => $row) {
      foreach ($row as $index => $value) {
        switch ($index) {
          case 'field_image_media':
            if(!empty((string)$value)){
              $media = Media::load((string)$value);
              $fid = $media->getSource()->getSourceFieldValue($media);
              $file = File::load($fid);
              $author = Term::load($media->get('field_media_author')->target_id);
              $author = $author->name->value;
              $rights = Term::load($media->get('field_media_rights')->target_id);
              $rights = $rights->name->value;
              $nodes[$key]['image'][] = [
                'src' => $file->url(),
                'alt' => $media->get('field_media_image')->alt,
                'title' => $media->get('field_media_image')->title,
                'width' => $media->get('field_media_image')->width,
                'height' => $media->get('field_media_image')->height,
                'author' => $author,
                'rights' => $rights,
                'longdesc' => $media->get('field_media_longdesc')->value,
              ];
            }
            break;
          case 'view_node':
            $path = (string)$value;
            $nodes[$key]['path'] = $path;
            $nodes[$key]['target'] = '_self';
            if(strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/http://') === 0 || strpos($path, '/https://') === 0){
              $nodes[$key]['target'] = '_blank';
            }
            break;
          default:
            $nodes[$key][$index] = $value;
            break;
        }
      }
    }

    $pagination = $this->pagination($rows);
    $result = [
      'nodes' => $nodes,
      'pager' => $pagination,
    ];

    return $this->serializer->serialize($result, $content_type, ['views_style_plugin' => $this]);
  }

  protected function pagination($rows) {
    $pagination = [];
    $page = 0;
    $limit = 0;
    $count = 0;
    $pages = 1;
    $class = NULL;

    $pager = $this->view->pager;

    if ($pager) {
      $limit = $pager->getItemsPerPage();
      $count = $pager->getTotalItems();
      $class = get_class($pager);
    }

    if (method_exists($pager, 'getPagerTotal')) {
      $pages = $pager->getPagerTotal();
    }
    if (method_exists($pager, 'getCurrentPage')) {
      $page = $pager->getCurrentPage();
    }
    if ($class == static::PAGER_NONE) {
      $limit = $count;
    }
    elseif ($class == static::PAGER_SOME) {
      $count = count($rows);
    }

    $pagination['page'] = $page;
    $pagination['count'] = $count;
    $pagination['pages'] = $pages;
    $pagination['limit'] = $limit;

    return $pagination;
  }
}
