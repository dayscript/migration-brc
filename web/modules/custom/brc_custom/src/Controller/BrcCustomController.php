<?php
/**
 * @file
 * Contains \Drupal\brc_custom\Controller\BrcCustomController.
 */

namespace Drupal\brc_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactory;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

class BrcCustomController extends ControllerBase {

  public $alias;

  public $page;

  public $config;

  public function __construct(ConfigFactory $config_factory) {
    //$this->config = $config_factory->get('brc_custom.settings');

    if (isset($_REQUEST['alias'])) {
      $this->alias = $_REQUEST['alias'];
    }else {
      $this->alias = null;
    }
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'));
  }

  public function fullNode() {
    $path = \Drupal::service('path.alias_manager')->getPathByAlias($this->alias);
    if(preg_match('/node\/(\d+)/', $path, $matches)) {
      $node = Node::load($matches[1]);
    }
    foreach ($node->toArray() as $keys => $values) {
      foreach ($values as $key => $value) {
        switch ($keys) {
          case 'type':
            $reponse[$keys] = $value['target_id'];
            break;
          case 'status':
            $reponse[$keys] = 200;
            break;
          case 'created':
          case 'changed':
            $reponse[$keys] = date('Y/m/d H:i:s', $value['value']);
            break;
          case 'path':
            $reponse[$keys] = $value['alias'];
            $reponse['target'] = '_self';
            if(strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/http://') === 0 || strpos($path, '/https://') === 0){
              $reponse['target'] = '_blank';
            }
            break;
          case 'field_plugin':
           $plugin = Node::load($value['target_id']);
           $reponse[$keys] = $plugin->title->value;
            break;
          case 'field_image_media':
          case 'field_gallery_media':
          case 'field_imagen_para_slider_media':
            $media = Media::load($value['target_id']);

            $fid = $media->getSource()->getSourceFieldValue($media);
            $file = File::load($fid);

            $term_author = Term::load($media->get('field_media_author')->target_id);
            $term_author = $term_author->name->value;

            $term_rights = Term::load($media->get('field_media_rights')->target_id);
            $term_rights = $term_rights->name->value;

            $reponse[$keys][] = [
              'src' => $file->url(),
              'alt' => $media->get('field_media_image')->alt,
              'title' => $media->get('field_media_image')->title,
              'width' => $media->get('field_media_image')->width,
              'height' => $media->get('field_media_image')->height,
              'author' => $term_author,
              'rights' => $term_rights,
              'longdesc' => $media->get('field_media_longdesc')->value,
            ];
            break;
          default:
            $exclude = ['uuid','vid','langcode','revision_timestamp','revision_uid','revision_log','uid','promote','sticky','default_langcode','revision_default','revision_translation_affected','metatag','field_metatag','field_image','comment_node_activity','comment_node_multimedia'];
            if(!in_array($keys, $exclude)) {
              switch (key($value)) {
                case 'value':
                  $reponse[$keys] = $value['value'];
                  break;
                case 'target_id':
                  $term = Term::load($value['target_id']);
                  $reponse[$keys][] = '<a href="'.$term->path->alias.'">'.$term->name->value.'</a>';
                  break;
                default:
                  $reponse[$keys] = $value;
                  break;
              }
            }
            break;
        }

      }
    }
    return new JsonResponse($reponse);
  }
}