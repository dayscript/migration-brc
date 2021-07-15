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
          case 'field_icono_media':
            if(!empty((string)$value)){
              $media = Media::load((string)$value);
              $fid = $media->getSource()->getSourceFieldValue($media);
              $file = File::load($fid);
              $author = Term::load($media->get('field_media_author')->target_id);
              $author = $author->name->value;
              $rights = Term::load($media->get('field_media_rights')->target_id);
              $rights = $rights->name->value;
              $nodes[$key]['icon'][] = [
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
          case 'field_archivo_adjunto_media':
            if(!empty((string)$value)){
              $media = Media::load((string)$value);
              $fid = $media->getSource()->getSourceFieldValue($media);
              $file = File::load($fid);
              $nodes[$key]['adjunto'] = $file->url();
            }
            break;
          case 'field_archivos_para_period_media':
            if(!empty((string)$value)){
              $media = Media::load((string)$value);
              $fid = $media->getSource()->getSourceFieldValue($media);
              $file = File::load($fid);
              $nodes[$key]['adjunto1'] = $file->url();
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
          case 'body_1':
            $nodes[$key]['summary'] = $value;
            break;
          case 'field_sumario':
            $nodes[$key]['subtitle'] = $value;
            break;
          case 'field_horarios':
            $nodes[$key]['hour'] = $value;
            break;
          case 'field_tome_nota':
            $nodes[$key]['notes'] = $value;
            break;
          case 'field_description':
            $nodes[$key]['tickets'] = $value;
            break;
          case 'field_tags':
            $nodes[$key]['tags'] = $value;
            break;
          case 'field_city':
            $nodes[$key]['city'] = $value;
            break;
          case 'field_plugin':
            $nodes[$key]['plugin'] = $value;
            break;
          case 'field_type_activity':
            $nodes[$key]['type'] = $value;
            break;
          case 'field_categoria_de_noticia':
            $nodes[$key]['category'] = $value;
            break;
          case 'field_type_service':
            $nodes[$key]['type'] = $value;
            break;
          case 'field_area_misional':
            $nodes[$key]['area_misional'] = $value;
            break;
          case 'field_tipo_de_publicacion':
            $nodes[$key]['type'] = $value;
            break;
          case 'field_author':
            $nodes[$key]['author'] = $value;
            break;
          case 'field_editorial':
            $nodes[$key]['editorial'] = $value;
            break;
          case 'field_isbn':
            $nodes[$key]['isbn'] = $value;
            break;
          case 'field_numero_de_paginas':
            $nodes[$key]['pages'] = $value;
            break;
          case 'field_precio_de_venta':
            $nodes[$key]['price'] = $value;
            break;
          case 'field_url_publicacion':
            $nodes[$key]['url'] = $value;
            break;
          case 'field_ano_publicacion_':
            $nodes[$key]['year'] = $value;
            break;
          case 'field_date':
            $nodes[$key]['date'] = $value;
            break;
          case 'field_date_1':
            $nodes[$key]['date_end'] = $value;
            break;
          case 'field_publication_date':
            $nodes[$key]['publication_date'] = $value;
            break;
          case 'field_disponibilidad':
            $nodes[$key]['disponibilidad'] = $value;
            break;
          case 'field_price':
            $nodes[$key]['tarifa'] = $value;
            break;
          case 'field_style':
            $nodes[$key]['tarifa_color'] = $value;
            break;
          case 'field_autor_del_art_culo':
            $nodes[$key]['author'] = $value;
            break;
          case 'description__value':
            $nodes[$key]['description'] = $value;
            break;
          case 'field_resumen':
            $nodes[$key]['summary'] = $value;
            break;
          case 'field_email':
            $nodes[$key]['email'] = $value;
            break;
          case 'field_dir':
            $nodes[$key]['address'] = $value;
            break;
          case 'field_telefono':
            $nodes[$key]['phone'] = $value;
            break;
          case 'field_imagen_principal':
            $nodes[$key]['image'] = $value;
            break;
          case 'field_tipo_de_espacio':
            $nodes[$key]['type'] = $value;
            break;
          case 'field_url_ubicacion':
            $nodes[$key]['url'] = $value;
            break;
          case 'name_1':
            $nodes[$key]['city'] = $value;
            break;
          case 'name_2':
            $nodes[$key]['place'] = $value;
            break;
          case 'field_enlace_a_facebook':
            $nodes[$key]['facebook'] = $value;
            break;
          case 'field_items_del_submenu':
            $s = [];
            foreach (explode(',', $value) as $k => $v) {
              $slug = str_replace(' ', '-', trim($v));
              $slug = strtolower($slug);
              $s[] = ['slug' => $slug, 'name' => trim($v)];
            }
            $nodes[$key]['submenu'] = $s;
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

    $pagination['page'] = !empty($page) ? $page : 0;
    $pagination['count'] = $count;
    $pagination['pages'] = $pages;
    $pagination['limit'] = $limit;

    return $pagination;
  }
}
