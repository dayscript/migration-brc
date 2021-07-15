<?php
/**
 * A drush command file.
 * @package Drupal\migrate_media_fields_custom\Commands.
 */

namespace Drupal\migrate_media_fields_custom\Commands;

use Drush\Commands\DrushCommands;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\Client;

class MigrateMediaFieldsCommands extends DrushCommands {
  /**
   * Custom drush command to migrate fields of the media content type.
   *
   * @param string $text
   * 
   * @command migrate:media-fields-custom
   * @aliases migrate-media-fields-custom mmfc
   * @option type
   *   Media Type.
   * @option page
   *   Page for view.
   * @usage migrate:media-fields-custom --type --page
   */
  public function media($text = '', $options = ['type' => 'image', 'page' => 0]) {
    $url = 'http://admin.banrepcultural.local/files-brc-migrate/' . $options['type'] . '?page=' . $options['page'] ;
    $client = new Client();
    $res = $client->get($url);
    $medias = [];
    $count = 0;
    $idmedia = '';

    if ($res->getStatusCode() == 200) {
      $response = json_decode($res->getBody(), TRUE);
     
      switch ($options['type']) {
        case 'image':
          try {
            $nids = \Drupal::entityQuery('media')->condition('bundle', 'image')->execute();
            foreach ($nids as $nid) {
              $media = Media::load($nid);
              $medias[$nid] = $media->get('field_media_image')->target_id;
            }

            foreach ($response['nodes'] as $item) {
              $idmedia = intval($item['id']);
              if(array_search($idmedia, $medias)){
                $media = Media::load(array_search($idmedia, $medias));
                if(isset($item['alt']) && !empty($item['alt'])){
                  $media->field_media_image->alt = $item['alt'];
                }
                if(isset($item['title']) && !empty($item['title'])){
                  $media->field_media_image->title = $item['title'];
                }
                if(isset($item['author']) && !empty($item['author'])){
                  foreach (explode(',', $item['author']) as $value) {
                    if (trim($value) == '') {
                      continue;
                    }
                    $query = \Drupal::entityQuery('taxonomy_term');
                    $query->condition('name', $value);
                    $query->condition('vid', 'autor_de_imagen');
                    $entity_ids = $query->execute();

                    if( gettype(array_search(array_shift(array_values($entity_ids)),  array_column($media->get('field_media_author')->getValue(), 'target_id'))) === 'boolean' ){
                      $term = Term::load(array_pop($entity_ids));
                      $media->field_media_author[] = $term;
                    }
                  }
                }
                if(isset($item['rights']) && !empty($item['rights'])){
                  foreach (explode(',', $item['rights']) as $value) {
                    if (trim($value) == '') {
                      continue;
                    }
                    $query = \Drupal::entityQuery('taxonomy_term');
                    $query->condition('name', $value);
                    $query->condition('vid', 'derechos_de_uso');
                    $entity_ids = $query->execute();

                    if( gettype(array_search(array_shift(array_values($entity_ids)),  array_column($media->get('field_media_rights')->getValue(), 'target_id'))) === 'boolean' ){
                      $term = Term::load(array_pop($entity_ids));
                      $media->field_media_rights[] = $term;
                    }
                  }
                }
                if(isset($item['longdesc']) && !empty($item['longdesc'])){
                 $media->field_media_longdesc  = [
                    'value'  => $item['longdesc'],
                    'format' => 'full_html',
                  ];
                }
                if(isset($item['tags']) && !empty($item['tags'])){
                  foreach (explode(',', $item['tags']) as $value) {
                    if (trim($value) == '') {
                      continue;
                    }
                    $query = \Drupal::entityQuery('taxonomy_term');
                    $query->condition('name', $value);
                    $query->condition('vid', 'tags');
                    $entity_ids = $query->execute();

                    if( gettype(array_search(array_shift(array_values($entity_ids)),  array_column($media->get('field_media_tags')->getValue(), 'target_id'))) === 'boolean' ){
                      $term = Term::load(array_pop($entity_ids));
                      $media->field_media_tags[] = $term;
                    }
                  }
                }
                if(isset($item['source']) && !empty($item['source'])){
                  foreach (explode(',', $item['source']) as $value) {
                    if (trim($value) == '') {
                      continue;
                    }
                    $query = \Drupal::entityQuery('taxonomy_term');
                    $query->condition('name', $value);
                    $query->condition('vid', 'fuente_de_la_imagen');
                    $entity_ids = $query->execute();

                    if( gettype(array_search(array_shift(array_values($entity_ids)),  array_column($media->get('field_media_source')->getValue(), 'target_id'))) === 'boolean' ){
                      $term = Term::load(array_pop($entity_ids));
                      $media->field_media_source[] = $term;
                    }
                  }
                }
                if(isset($item['use']) && !empty($item['use'])){
                  if ($item['use'] === '1') {
                    $media->field_media_divulgation = 1;
                  }
                }
                $media->save();

                $count++;
              }else {
                $this->output()->writeln('Media ID not found:' . $idmedia);
              }
            }
          } catch (Exception $e) {
            $this->output()->writeln('error updated media fields ID:' . $idmedia);
          }
          break;
        case 'document':
          $nids = \Drupal::entityQuery('media')->condition('bundle', 'document')->execute();
          foreach ($nids as $nid) {
            $media = Media::load($nid);
            $medias[$nid] = $media->get('field_media_document')->target_id;
          }

          foreach ($response['nodes'] as $item) {
            $media = Media::load(array_search(intval($item['id']), $medias));
            if(isset($item['description']) && !empty($item['description'])){
              $media->field_media_document->description = $item['description'];
            }
            $media->save();
            $count++;
          }
          break;
        case 'audio':
          $nids = \Drupal::entityQuery('media')->condition('bundle', 'audio')->execute();
          foreach ($nids as $nid) {
            $media = Media::load($nid);
            $medias[$nid] = $media->get('field_media_audio_file')->target_id;
          }

          foreach ($response['nodes'] as $item) {
            $media = Media::load(array_search(intval($item['id']), $medias));
            if(isset($item['description']) && !empty($item['description'])){
              $media->field_media_audio_file->description = $item['description'];
            }
            $media->save();
            $count++;
          }
          break;
      }
    }
    $this->output()->writeln($count.' updated media fields.');
  }
}