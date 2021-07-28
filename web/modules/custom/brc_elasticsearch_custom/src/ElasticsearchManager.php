<?php

namespace Drupal\brc_elasticsearch_custom;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use GuzzleHttp\Client;
use Elasticsearch\ClientBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;

class ElasticsearchManager implements ElasticsearchManagerInterface {

  public $config;

  public function __construct() {
    $this->config = \Drupal::config('brc_elasticsearch_custom.settings');
  }

  public function mappingCategory($category, $type = null) {
    $fields = [];
    switch ($category) {
      case 'node':
        $response = \Drupal\node\Entity\NodeType::loadMultiple();
        foreach ($response as $key => $value) {
          $fields[$key] = $value->get('name');
        }
        break;
      case 'taxonomy':
        $response = \Drupal\taxonomy\Entity\Vocabulary::loadMultiple();
        foreach ($response as $key => $value) {
          $fields[$key] = $value->get('name');
        }
        break;
      case 'mediawiki':
        $fields = ['enciclopedia' => 'Enciclopedia'];
        break;
      case 'makemake':
        $fields = ['makemake' => 'Make make'];
        break;
      case 'babel':
      case 'ojs':
        $parameters = $this->config->get('categories')[$category]['endpoint'];
        if(isset($parameters)){
          foreach ($parameters as $key => $end) {
            foreach ($this->clientOaipmh('sets', $end['endpoint']) as $key => $set) {
              $fields[$this->clearType($set->setSpec)] = $set->setName;
            }
          }
        }
        break;
      case 'fields':
        if($type == 'mediawiki'){ $type = 'other';}
        if($type == 'babel' || $type == 'ojs'){ $type = 'oai_dc';}
        $types = ($type == 'general') ? ['node', 'taxonomy', 'other', 'oai_dc'] : [$type];
        $response = [];
        foreach ($types as $key => $value) {
          $response += $this->mappingFields($value);
        }
        foreach ($response as $key => $value) {
         $fields[$key] = $key;
        }
        break;
    }
    return $fields;
  }

  public function mappingFields($type) {
    $types = [
      'node' => [
        'abstract' => '',
        'area' => '',
        'author' => '',
        'available' => '',
        'body' => '',
        'book' => '',
        'category' => '',
        'changed' => '',
        'city' => '',
        'city_place' => '',
        'created' => '',
        'date' => '',
        'date_collection' => '',
        'date_end' => '',
        'date_full' => '',
        'display' => '',
        'editorial' => '',
        'featured' => '',
        'function' => '',
        'hour' => '',
        'image' => [
          'src' => '',
          'alt' => '',
          'title' => '',
          'author' => '',
          'rights' => '',
          'longdesc' => '',
        ],
        'isbn' => '',
        'material' => '',
        'modality' => '',
        'nid' => '',
        'path' => '',
        'path_external' => '',
        'period' => '',
        'prefix' => '',
        'price' => '',
        'public' => '',
        'region' => '',
        'registry_number' => '',
        'score_order' => '',
        'status' => '',
        'style' => '',
        'subcategory' => '',
        'subtitle' => '',
        'summary' => '',
        'tags' => '',
        'technique' => '',
        'title' => '',
        'type' => '',
      ], 
      'taxonomy' => [
        'body'=> '',
        'category'=> '',
        'nid'=> '',
        'path'=> '',      
        'status'=> '',
        'summary'=> '',
        'title'=> '',
        'type'=> '',
      ],
      'other' => [
        'text' => '',
        'title' => '',
      ],
      'oai_dc' => [
        'author' => '',
        'body' => '',
        'category' => '',
        'changed' => '',
        'city_place' => '',
        'created' => '',
        'date' => '',
        'nid' => '',
        'path' => '',
        'subcategory' => '',
        'tags' => '',
        'title' => '',
        'type' => '',
        'type_name' => '',
      ],
      'makemake' => [
        'author' => '',
        'body' => '',
        'category' => '',
        'changed' => '',
        'created' => '',
        'date' => '',
        'image' => [
          'src' => '',
          'alt' => '',
          'title' => '',
          'author' => '',
          'rights' => '',
          'longdesc' => '',
        ],
        'nid' => '',
        'path' => '',
        'subcategory' => '',
        'tags' => '',
        'title' => '',
        'type' => '',
        'type_name' => '',
      ]
    ];
    return $types[$type];
  }

  public function mappingFieldsTaxonomy($term, $type, $filter, $component){
    $nids = \Drupal::entityQuery('taxonomy_term')->condition('vid',  $type)->condition('status', 1)->execute();
    $terms = [];
    foreach ($nids as $key => $nid) {
      $t = Term::load($nid);
      $t = $t->toArray();
      switch ($term) {
        case 'city':
          switch ($component) {
            case 'radio':
              array_push($terms, ['id' => $filter.'-'.$term.'-'.$key, 'name' => $filter.'-'.$type, 'value' => $t['name'][0]['value'], 'label' => $t['name'][0]['value'], 'active' => false]);
              break;
            case 'checkbox':
              array_push($terms, ['id' => $filter.'-'.$term.'-'.$t['name'][0]['value'], 'name' => $filter.'-'.$term.'-'. $t['name'][0]['value'], 'value' => $t['name'][0]['value'], 'active' => false]);
              break;
          }
          break;
        default:
            switch ($component) {
              case 'radio':
                array_push($terms, ['id' => $filter.'-'.$term.'-'.$key, 'name' => $filter.'-'.$type, 'value' => $t['name'][0]['value'], 'label' => $t['name'][0]['value'], 'active' => false]);
                break;
              case 'checkbox':
                array_push($terms, ['id' => $filter.'-'.$term.'-'.$t['name'][0]['value'], 'name' => $filter.'-'.$term.'-'.$t['name'][0]['value'], 'value' => $t['name'][0]['value'], 'active' => false]);
                break;
            }
          break;
      }
    }
    return $terms;
  }

  public function bootingFields($field) {
    $fields = [
        'abstract' => 7,
        'area' => 6,
        'author' => 6,
        'available' => 5,
        'body' => 9,
        'book' => 5,
        'category' => 5,
        'city' => 7,
        'city_place' => 7,
        'editorial' => 5,
        'function' => 5,
        'material' => 5,
        'modality' => 5,
        'period' => 5,
        'public' => 6,
        'region' => 5,
        'registry_number' => 5,
        'style' => 5,
        'subcategory' => 5,
        'subtitle' => 8,
        'summary' => 5,
        'tags' => 8,
        'technique' => 5,
        'title' => 10,
        'type' => 8,
        'text' => 9,
        'type_name' => 8,
    ];
    return isset($fields[$field]) ? $fields[$field] : 0;
  }

  public function indexingApi($category, $type, $operation, $returnresult = false) {
    $log = ['total' => 0, 'successful' => 0, 'failed' => 0, 'error' => []];
    
    switch ($category) {
      case 'node':
        $nids = \Drupal::entityQuery('node')->condition('type', $type)->condition('status', 1)->execute();
        foreach ($nids as $key => $nid) {
          $response = $this->indexingProcessDoc($category, $operation, $nid, $response);
          $log = $this->translateLog($response, $log);
        }
        break;
      case 'taxonomy':
        $nids = \Drupal::entityQuery('taxonomy_term')->condition('vid',  $type)->condition('status', 1)->execute();
        foreach ($nids as $key => $nid) {
          $response = $this->indexingProcessDoc($category, $operation, $nid, $response);
          $log = $this->translateLog($response, $log);
        }
        break;
      case 'makemake':
        $fila = 1;
        if (($handle = fopen('sites/default/files/import/makemake.csv', 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
              if($fila > 1){
                $nid = $data[0];
                $response = $this->indexingProcessDoc($category, $operation, $nid, $data);
                $log = $this->translateLog($response, $log);
              }  
              $fila++;
            }
            fclose($handle);
        }else {
          $msj = 'Verifique que el archivo "makemake.csv" exista en "sites/default/files/import/" o cárguelo nuevamente.';
          if($returnresult){
            return $msj;
          }else {
            drupal_set_message($msj, 'error');
          }
        }
        break;
    }
    $msj = 'Documentos procesados: ' . $log['total'] . ', exitosos: ' . $log['successful'] . ', han fallado: ' .  $log['failed'];
    if($returnresult){
      return $msj;
    }else {
      drupal_set_message($msj);
    }
  }

  public function indexingProcessDoc($category, $operation, $nid, $data = null) {
    switch ($category) {
      case 'node':
        switch ($operation) {
          case 'create':
            $node = Node::load($nid);
            $node = $node->toArray();
            $node_es = json_decode(json_encode($this->mappingFields($category)));
            $node_es->nid = $node['nid'][0]['value'];
            $node_es->type =  $node['type'][0]['target_id'];
            $node_es->title = $node['title'][0]['value'];
            $node_es->subtitle = (isset($node['field_subtitle']) && !empty($node['field_subtitle'])) ? $node['field_subtitle'][0]['value'] : '';
            $node_es->summary = (isset($node['field_sumario']) && !empty($node['field_sumario'])) ? $node['field_sumario'][0]['value'] : '';
            $node_es->abstract = (isset($node['body']) && !empty($node['body'])) ? str_replace("&nbsp;", " ",strip_tags($node['body'][0]['summary'])) : '';
            $node_es->body = (isset($node['body']) && !empty($node['body'])) ? str_replace("&nbsp;", " ",strip_tags($node['body'][0]['value'])) : '';
            if (isset($node['field_tags']) && !empty($node['field_tags'])) {
              $node_es->tags = [];
              foreach ($node['field_tags'] as $key => $value) {
                $term = Term::load($value['target_id']);
                $node_es->tags[] = $term->name->value;
              }
              $node_es->tags = join(',', $node_es->tags);
            }
            if (isset($node['field_city']) && !empty($node['field_city'])) {
              $node_es->city_place = [];
              foreach ($node['field_city'] as $key => $value) {
                $term = Term::load($value['target_id']);
                if($key == 0) {
                  $node_es->city = $term->name->value;
                }
                $node_es->city_place[] = $term->name->value;
              }
              $node_es->city_place = join(',', $node_es->city_place);
            }
            if (isset($node['ubication']) && !empty($node['ubication'])) {
              $node_es->city_place = [];
              foreach ($node['ubication'] as $key => $value) {
                $term = Term::load($value['target_id']);
                if($key == 0) {
                  $node_es->city = $term->name->value;
                }
                $node_es->city_place[] = $term->name->value;
              }
              $node_es->city_place = join(',', $node_es->city_place);
            }
            if (isset($node['field_area_misional']) && !empty($node['field_area_misional'])) {
              $node_es->area = [];
              foreach ($node['field_area_misional'] as $key => $value) {
                $term = Term::load($value['target_id']);
                $node_es->area[] = $term->name->value;
              }
              $node_es->area = join(',', $node_es->area);
            }
            if (isset($node['field_p_blico_objetivo']) && !empty($node['field_p_blico_objetivo'])) {
              $node_es->public = [];
              foreach ($node['field_p_blico_objetivo'] as $key => $value) {
                $term = Term::load($value['target_id']);
                $node_es->public[] = $term->name->value;
              }
              $node_es->public = join(',', $node_es->public);
            }
            if(isset($node['field_numero_de_registro']) && !empty($node['field_numero_de_registro'])){
              $node_es->registry_number = $node['field_numero_de_registro'][0]['value'];
            }
            if (isset($node['field_image_media']) && !empty($node['field_image_media'])) {
              foreach ($node['field_image_media'] as $key => $value) {
                $node_es->image = $this->processFieldImage($value['target_id']);//default_image
              }
            }
            if (isset($node['field_portada_media']) && !empty($node['field_portada_media'])) {
              foreach ($node['field_portada_media'] as $key => $value) {
                $node_es->image = $this->processFieldImage($value['target_id']);//default_image
              }
            }
            if (isset($node['field_gallery_media']) && !empty($node['field_gallery_media'])) {
              foreach ($node['field_gallery_media'] as $key => $value) {
                if($key == 0) {
                  $node_es->image = $this->processFieldImage($value['target_id']);//default_image
                }
              }
            }
            $node_es->path = $node['path'][0]['alias'];
            $node_es->created = date('Y-m-d',  $node['created'][0]['value']);
            $node_es->changed = date('Y-m-d',  $node['changed'][0]['value']);
            $node_es->status = $node['status'][0]['value'] == 1 ? true : false;
            
            switch ($node_es->type) {
              case 'activity':
                if(isset($node['field_date']) && !empty($node['field_date'])){
                  $node_es->date = $node['field_date'][0]['value'];
                  $node_es->date_end = $node['field_date'][0]['end_value'];
                }
                if(isset($node['field_type_activity']) && !empty($node['field_type_activity'])){
                  $cats = ["Música antigua para nuestro tiempo", "Música y músicos de Latinoamérica y del mundo", "Recorridos por la música de cámara", "Retratos de un compositor", "Jóvenes intérpretes"];
                  $term = Term::load($node['field_type_activity'][0]['target_id']);
                  $term = $term->name->value;
                  $node_es->category = (in_array($term, $cats)) ? "Concierto" : $term;
                }
                if(isset($node['field_disponibilidad']) && !empty($node['field_disponibilidad'])){
                  $term = Term::load($node['field_disponibilidad'][0]['target_id']);
                  $node_es->period = $term->name->value;
                }
                if(isset($node['field_price']) && !empty($node['field_price'])){
                  $term = Term::load($node['field_price'][0]['target_id']);
                  $node_es->price = $term->name->value;
                  $node_es->style = $term->field_style->value;
                }
                if(isset($node['field_disponibilidad']) && !empty($node['field_disponibilidad'])){
                  $term = Term::load($node['field_disponibilidad'][0]['target_id']);
                  $node_es->modality = $term->name->value;
                }
                break;
              case 'article':
                if(isset($node['field_publication_date']) && !empty($node['field_publication_date'])){
                  $date = explode('T', $node['field_publication_date'][0]['value']);
                  $node_es->date = $date[0];
                  $node_es->hour = $date[1];
                }
                if (isset($node['field_categoria_de_noticia']) && !empty($node['field_categoria_de_noticia'])) {
                  $node_es->category = [];
                  foreach ($node['field_categoria_de_noticia'] as $key => $value) {
                    $term = Term::load($value['target_id']);
                    $node_es->category[] = $term->name->value;
                  }
                  $node_es->category = join(',', $node_es->category);
                }
                if(isset($node['field_autor_del_art_culo']) && !empty($node['field_autor_del_art_culo'])){
                  $term = Term::load($node['field_autor_del_art_culo'][0]['target_id']);
                  $node_es->author = $term->name->value;
                }
                break;
              case 'book':
                if(isset($node['field_publication_date']) && !empty($node['field_publication_date'])){
                  $date = explode('T', $node['field_publication_date'][0]['value']);
                  $node_es->date = $date[0];
                  $node_es->hour = $date[1];
                }
                $node_es->featured = false;
                if ($node['field_is_front'][0]['value'] == 1) {
                  $node_es->featured = true;
                  if (isset($node['field_collection']) && !empty($node['field_collection'])) {
                    $node_es->category = [];
                    foreach ($node['field_collection'] as $key => $value) {
                      $term = Term::load($value['target_id']);
                      $node_es->category[] = $term->name->value;
                    }
                    $node_es->category = join(',', $node_es->category);
                  }
                }
                if(isset($node['field_book_type']) && !empty($node['field_book_type'])){
                  $term = Term::load($node['field_book_type'][0]['target_id']);
                  $node_es->subcategory = $term->name->value;
                }
                if(isset($node['field_author']) && !empty($node['field_author'])){
                  $term = Term::load($node['field_author'][0]['target_id']);
                  $node_es->author = $term->name->value;
                }
                break;
              case 'coleccion_bibliografica':
                if(isset($node['field_date_coleccion']) && !empty($node['field_date_coleccion'])){
                  $node_es->date = $node['field_date_coleccion'][0]['value'];
                  $node_es->date_end = $node['field_date_coleccion'][0]['end_value'];
                }
                if (isset($node['field_tipo_documental']) && !empty($node['field_tipo_documental'])) {
                  $node_es->category = [];
                  foreach ($node['field_tipo_documental'] as $key => $value) {
                    $term = Term::load($value['target_id']);
                    $node_es->category[] = $term->name->value;
                  }
                  $node_es->category = join(',', $node_es->category);
                }
                if (isset($node['field_tema_coleccion_bibliografi']) && !empty($node['field_tema_coleccion_bibliografi'])) {
                  $node_es->subcategory = [];
                  foreach ($node['field_tema_coleccion_bibliografi'] as $key => $value) {
                    $term = Term::load($value['target_id']);
                    $node_es->subcategory[] = $term->name->value;
                  }
                  $node_es->subcategory = join(',', $node_es->subcategory);
                }
                break;
              case 'estampilla':
                if(isset($node['field_fecha_de_creaci_n']) && !empty($node['field_fecha_de_creaci_n'])){
                  $node_es->date = $node['field_fecha_de_creaci_n'][0]['value'];
                }
                if(isset($node['field_fecha_de_creaci_n_completa']) && !empty($node['field_fecha_de_creaci_n_completa'])){
                  $node_es->date_full = $node['field_fecha_de_creaci_n_completa'][0]['value'];
                }
                if(isset($node['field_tipo_estampilla']) && !empty($node['field_tipo_estampilla'])){
                  $term = Term::load($node['field_tipo_estampilla'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                break;
              case 'instrumento':
                if(isset($node['field_a_o_de_creaci_n']) && !empty($node['field_a_o_de_creaci_n'])){
                  $node_es->date = $node['field_a_o_de_creaci_n'][0]['value'];
                  $node_es->date_end = $node['field_a_o_de_creaci_n'][0]['end_value'];
                }
                if(isset($node['field_prefijo_fecha_de_creacion']) && !empty($node['field_prefijo_fecha_de_creacion'])){
                  $node_es->prefix = $node['field_prefijo_fecha_de_creacion'][0]['value'];
                }
                if(isset($node['field_tipo_de_instrumento']) && !empty($node['field_tipo_de_instrumento'])){
                  $term = Term::load($node['field_tipo_de_instrumento'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                if(isset($node['field_fabricante_instrumento']) && !empty($node['field_fabricante_instrumento'])){
                  $term = Term::load($node['field_fabricante_instrumento'][0]['target_id']);
                  $node_es->author = $term->name->value;
                }
                break;
              case 'minisitio':
                if(isset($node['field_date']) && !empty($node['field_date'])){
                  $date = $node['field_date'][0]['value'];
                  $date_end = $node['field_date'][0]['end_value'];
                  if (!empty($date)) {
                    $node_es->date = $date;
                  }
                  if (!empty($date_end)) {
                    $node_es->date_end = $date_end;
                  }
                  if($date < date('Y-m-d') && $date_end > date('Y-m-d')){
                    $node_es->display = true;
                  }else if($date > date('Y-m-d') && $date_end > date('Y-m-d')){
                    $node_es->display = true;
                  }else {
                     $node_es->display = false;
                  }
                }
                if(isset($node['field_tipo_de_minisitio']) && !empty($node['field_tipo_de_minisitio'])){
                  $term = Term::load($node['field_tipo_de_minisitio'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                if (isset($node['field_car_cter_de_la_exposici_n']) && !empty($node['field_car_cter_de_la_exposici_n'])) {
                  $node_es->subcategory = [];
                  foreach ($node['field_car_cter_de_la_exposici_n'] as $key => $value) {
                    $term = Term::load($value['target_id']);
                    $node_es->subcategory[] = $term->name->value;
                  }
                  $node_es->subcategory = join(',', $node_es->subcategory);
                }
                $node_es->featured = false;
                if ($node['field_is_front'][0]['value'] == 1) {
                  $node_es->featured = true;
                }
                if(isset($node['field_curador']) && !empty($node['field_curador'])){
                  $node_es->author = Term::load($node['field_curador'][0]['value']);
                }
                /*$mild = array_reverse(book_get_flat_menu($node->book));
                $key = count($mild) - 1 ;
                $items_menu = book_menu_subtree_data(menu_link_load($mild[$key]['mlid']));
                $node_es->book = $items_menu[key( $items_menu)]['link']['link_title'];*/
               break;
              case 'multimedia':
                if(isset($node['field_plugin']) && !empty($node['field_plugin'])){
                  $plugin = Node::load($node['field_plugin'][0]['target_id']);
                  $node_es->category = $plugin->title->value;
                }
                break;
              case 'obra_de_arte':
                if(isset($node['field_fecha_creacion_obra']) && !empty($node['field_fecha_creacion_obra'])){
                  $node_es->date = $node['field_fecha_creacion_obra'][0]['value'];
                  $node_es->date_end = $node['field_fecha_creacion_obra'][0]['end_value'];
                }
                if (isset($node['field_fecha_ingreso_coleccion']) && !empty($node['field_fecha_ingreso_coleccion'])) {
                  $node_es->date_collection = $node['field_fecha_ingreso_coleccion'][0]['value'];
                }
                if(isset($node['field_prefijo_fecha_de_creacion']) && !empty($node['field_prefijo_fecha_de_creacion'])){
                  $node_es->prefix = $node['field_prefijo_fecha_de_creacion'][0]['value'];
                }
                if(isset($node['field_denominacion']) && !empty($node['field_denominacion'])){
                  $term = Term::load($node['field_denominacion'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                if(isset($node['field_artista']) && !empty($node['field_artista'])){
                  $term = Term::load($node['field_artista'][0]['target_id']);
                  $node_es->author = $term->name->value;
                }
                if (isset($node['field_tecnica']) && !empty($node['field_tecnica'])) {
                  $node_es->subcategory = [];
                  foreach ($node['field_tecnica'] as $key => $value) {
                    $term = Term::load($value['target_id']);
                    $node_es->subcategory[] = $term->name->value;
                  }
                  $node_es->subcategory = join(',', $node_es->subcategory);
                }
                $node_es->featured = false;
                if ($node['field_obra_destacada'][0]['value'] == 1) {
                  $node_es->featured = true;
                }
                $node_es->display = false;
                if ($node['field_estado_exhibicion'][0]['value'] == 'Si') {
                  $node_es->display = true;
                }
                break;
              case 'page':
                if(isset($node['field_minisite_type']) && !empty($node['field_minisite_type'])){
                  $term = Term::load($node['field_minisite_type'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                break;
              case 'pieza_arqueologica':
                if(isset($node['field_tipo']) && !empty($node['field_tipo'])){
                  $term = Term::load($node['field_tipo'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                if(isset($node['field_estilo']) && !empty($node['field_estilo'])){
                  $term = Term::load($node['field_estilo'][0]['target_id']);
                  $node_es->style = $term->name->value;
                }
                if(isset($node['field_region']) && !empty($node['field_region'])){
                  $term = Term::load($node['field_region'][0]['target_id']);
                  $node_es->region = $term->name->value;
                }
                if(isset($node['field_cmo_tecnica']) && !empty($node['field_cmo_tecnica'])){
                  $term = Term::load($node['field_cmo_tecnica'][0]['target_id']);
                  $node_es->technique = $term->name->value;
                }
                if(isset($node['field_material']) && !empty($node['field_material'])){
                  $term = Term::load($node['field_material'][0]['target_id']);
                  $node_es->material = $term->name->value;
                }
                if(isset($node['field_cmo_funcion']) && !empty($node['field_cmo_funcion'])){
                  $term = Term::load($node['field_cmo_funcion'][0]['target_id']);
                  $node_es->function = $term->name->value;
                }
                if(isset($node['field_periodo']) && !empty($node['field_periodo'])){
                  $term = Term::load($node['field_periodo'][0]['target_id']);
                  $node_es->period = $term->name->value;
                }
                break;
              case 'pieza_coleccion_monedas_billetes':
                if (isset($node['field_fecha_de_emision']) && !empty($node['field_fecha_de_emision'])) {
                  $node_es->date = $node['field_fecha_de_emision'][0]['value'];
                }
                if(isset($node['field_tipo_de_pieza']) && !empty($node['field_tipo_de_pieza'])){
                  $term = Term::load($node['field_tipo_de_pieza'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                if(isset($node['field_emisor']) && !empty($node['field_emisor'])){
                  $term = Term::load($node['field_emisor'][0]['target_id']);
                  $node_es->sub_category = $term->name->value;
                }
                $node_es->featured = false;
                if ($node['field_obra_destacada'][0]['value'] == 1) {
                  $node_es->featured = true;
                }
                break;
              case 'publicacion':
                if (isset($node['field_ano_publicacion_']) && !empty($node['field_ano_publicacion_'])) {
                  $node_es->date = $node['field_ano_publicacion_'][0]['value'];
                }
                if(isset($node['field_precio_de_venta']) && !empty($node['field_precio_de_venta'])){
                  $node_es->price = $node['field_precio_de_venta'][0]['value'];
                }
                if(isset($node['field_precio_de_venta']) && !empty($node['field_precio_de_venta'])){
                  $node_es->price = $node['field_precio_de_venta'][0]['value'];
                }
                if(isset($node['field_isbn']) && !empty($node['field_isbn'])){
                  $node_es->isbn = $node['field_isbn'][0]['value'];
                }
                if(isset($node['field_url_publicacion']) && !empty($node['field_url_publicacion'])){
                  $node_es->path_external = $node['field_url_publicacion'][0]['value'];
                }
                if(isset($node['field_tipo_de_publicacion']) && !empty($node['field_tipo_de_publicacion'])){
                  $term = Term::load($node['field_tipo_de_publicacion'][0]['target_id']);
                  $node_es->category = $term->name->value;
                }
                if(isset($node['field_editorial']) && !empty($node['field_editorial'])){
                  $term = Term::load($node['field_editorial'][0]['target_id']);
                  $node_es->editorial = $term->name->value;
                }
                if (isset($node['field_author']) && !empty($node['field_author'])) {
                  $node_es->author = [];
                  foreach ($node['field_author'] as $key => $value) {
                    $term = Term::load($value['target_id']);
                    $node_es->author[] = $term->name->value;
                  }
                  $node_es->author = join(',', $node_es->author);
                }
                $node_es->available = false;
                if ($node['field_obra_destacada'][0]['target_id'] == 36983) {
                  $node_es->available = true;
                }
               break;
              case 'service':
                if (isset($node['field_type_service']) && !empty($node['field_type_service'])) {
                  foreach ($node['field_type_service'] as $key => $value) {
                    if($value['target_id'] == 1453) {
                        $term = Term::load($value['target_id']);
                        $node_es->category = $term->name->value;
                      }else {
                        $term = Term::load($value['target_id']);
                        $node_es->subcategory = $term->name->value;
                        break;
                    }
                  }
                }
                $node_es->score_order = 1;
                if(isset($node['field_score_order']) && !empty($node['field_score_order'])){
                  $node_es->score_order = $node['field_score_order'][0]['value'];
                }
                /*field collection
                if(isset($node->field_content['und']) && !empty($node->field_content['und'])){
                  $citys = [];
                  foreach ($node->field_content['und'] as $key => $item){
                  $entityf = entity_load('field_collection_item', [$item['value']]);
                    if(isset($entityf[key($entityf)]->field_city['und'][0]['tid']) && !empty($entityf[key($entityf)]->field_city['und'][0]['tid'])){
                      $citys[] = taxonomy_term_load($entityf[key($entityf)]->field_city['und'][0]['tid'])->name;
                    }
                  }
                  $node_es->city = implode(", ", $citys);
                }*/
                break;
            }

            $node_es = $this->clearObject($node_es);dpm((array)$node_es);
            $result = $this->clientElasticsearch('create', 'banrepcultural', 'node', $node_es);
          break;
          case 'delete':
            $result = $this->clientElasticsearch('delete', 'banrepcultural', 'node', $nid);
            break;
        }
        break;
      case 'taxonomy':
        switch ($operation) {
          case 'create':
            $node = Term::load($nid);
            $node = $node->toArray();
            $node_es = json_decode(json_encode($this->mappingFields($category)));
            $node_es->nid = $node['tid'][0]['value'];
            $node_es->type =  $node['vid'][0]['target_id'];
            $node_es->title = $node['name'][0]['value'];
            $node_es->summary = (isset($node['field_resumen']) && !empty($node['field_resumen'])) ? $node['field_resumen'][0]['value'] : '';
            $node_es->body = (isset($node['description']) && !empty($node['description'])) ? $node['description'][0]['value'] : '';
            if(isset($node['field_tipo_de_espacio']) && !empty($node['field_tipo_de_espacio'])){
              $term = Term::load($node['field_tipo_de_espacio'][0]['target_id']);
              $node_es->category = $term->name->value;
            }
            $node_es->path = (isset($node['field_url_ubicacion']) && !empty($node['field_url_ubicacion'])) ? $node['field_url_ubicacion'][0]['value'] : '';//$node['path'][0]['alias'];
            $node_es->created = date('Y-m-d',  $node['created'][0]['value']);
            $node_es->changed = date('Y-m-d',  $node['changed'][0]['value']);
            $node_es->status = $node['status'][0]['value'] == 1 ? true : false;

            $node_es = $this->clearObject($node_es);
            $result = $this->clientElasticsearch('create', 'banrepcultural', 'node', $node_es);
            break;
          case 'delete':
            $result = dayscript_elastcisearch_client_api('delete', 'banrepcultural', 'node', $nid);
            break;
        }
        break;
      case 'babelojs':
        switch ($operation) {
          case 'create':
            $node = (array)$data->metadata->oai_dc;
            $node_es = json_decode(json_encode($this->mappingFields('oai_dc')));

            if(isset($node) && !empty($node)){
              if(isset($node['title']) && !empty($node['title'])){
                $node_es->title = (is_array($node['title'])) ? $node['title'][0] : $node['title'];
              }
              if(isset($node['subject']) && !empty($node['subject'])){
                $node_es->tags = (is_array($node['subject'])) ? implode(',', $node['subject']) : $node['subject'];
              }
              if(isset($node['description']) && !empty($node['description'])){
                $node_es->body = is_array($node['description']) ? strip_tags($node['description'][0]) : strip_tags($node['description']);
              }
              if(isset($node['date']) && !empty($node['date'])){
                $node_es->date =  $node['date'];
              }
              if(isset($node['type']) && !empty($node['type'])){
                $node_es->category = (is_array($node['type'])) ? str_replace('info:eu-repo/semantics/', '', $node['type'][0]) : str_replace('info:eu-repo/semantics/', '', $node['type']);
              }
              if(isset($node['identifier']) && !empty($node['identifier'])){
                $node_es->path = (is_array($node['identifier'])) ? end($node['identifier']) : $node['identifier'];
              }
              if(isset($node['creator']) && !empty($node['creator'])){
                if(is_array($node['creator'])){
                  foreach ($node['creator'] as $key => $value) {
                    $da = explode(',', $value);
                    $data_autor .= isset($da[1]) ? $da[1] : '';
                    $data_autor .= isset($da[0]) ? ' ' . $da[0] : '';
                    $data_autor .= ($key+1 < count($node['creator'])) ? ';' : '';
                  }
                }else {
                  $da = explode(',', $node['creator']);
                  $data_autor = isset($da[1]) ? $da[1] : '';
                  $data_autor .= isset($da[0]) ? ' ' . $da[0] : '';
                }
                 $node_es->author = $data_autor;
              }
              if(isset($node['coverage']) && !empty($node['coverage'])){
                $node_es->city_place =  $node['coverage'];
              }
              $node_es->image = $this->processFieldImageSet($nid, $node_es->title, $node_es->path);
              $node_es->type = $this->clearType($nid);
              $node_es->type_name = $this->typeNameSets($node_es->type);
 
              $header = (array)$data->header;
               if(isset($header['identifier']) && !empty($header['identifier'])){
                $node_es->nid =  $header['identifier'];
              }
              if(isset($header['datestamp']) && !empty($header['datestamp'])){
                $date = explode('T', $header['datestamp']);
                $node_es->created = $date[0];
              }
              $node_es->changed = date('Y-m-d');

              $node_es = $this->clearObject($node_es);
              $result = $this->clientElasticsearch('create', 'babelojs', 'node', $node_es);
            }
            break;
          case 'delete':
            $result = dayscript_elastcisearch_client_api('delete', 'babelojs', 'node', $nid);
            break;
        }
        break;
      case 'makemake':
        switch ($operation) {
          case 'create':
            $node_es = json_decode(json_encode($this->mappingFields($category)));
            $node_es->nid  = $data[0];
            $node_es->title  = $data[1];
            $node_es->author  = $data[2];
            $node_es->tags  = $data[3];
            $node_es->body  = $data[4];
            $node_es->date  = $data[5];
            $node_es->category  = $data[6];
            $node_es->subcategory  = $data[7];
            $node_es->path  = $data[9];
            $node_es->image  = [
              'src' => $data[8],
              'alt' => $data[1],
              'title' => $data[1],
              'author' => '',
              'rights' => '',
              'longdesc' => '',
            ];
            $node_es->type  = $category;
            $node_es->type_name  = $this->config->get('categories')[$category]['endpoint'][1]['name'];
            $node_es->created  = date("Y-m-d");
            $node_es->changed  = date("Y-m-d");
            $node_es->status = true;
            $node_es = $this->clearObject($node_es);
            $result = $this->clientElasticsearch('create', 'banrepcultural', 'node', $node_es);
            break;
          case 'delete':
            $result = dayscript_elastcisearch_client_api('delete', 'banrepcultural', 'node', $nid);
            break;
        }
        break;
    }
    return $result;
  }

  public function clientElasticsearch($operation, $index, $type, $body = null) {
    try {
      $client = ClientBuilder::create()->build(); 
      switch ($operation) {
        case 'search':
          $params = [
            'index' => $index,
            'type' => $type,
            'body' => $body
          ];
          $response = $client->search($params);
          break;
        case 'create':
          $params = [
            'index' => $index,
            'type' => $type,
            'id' => $body->nid,
            'body' => $body
          ];
          $response = $client->index($params);
          break;
        case 'delete':
          $params = [
            'index' => $index,
            'type' => $type,
            'id' => $body,
          ];
          $response = $client->delete($params);
          break;
        case 'mapping':
          $params = [
            'index' => $index,
            'type' => $type
          ];
          $response = $client->indices()->getMapping($params);
          break;
        case 'suggest':
          $params = [
            'index' => $index,
            'type' => $type,
            'body' => $body
          ];
          $response = $client->search($params);
          break;
      }
      return json_encode((object)$response, JSON_FORCE_OBJECT);
    
    } catch (Exception $e) {
      return ['operation' => $operation, 'data' => 'Verifique que el servicio Elasticsearch este activado y funcionando por el puerto 9200', 'exception' => $e, 'status' => 'error'];
    }
  }

  public function clientOaipmh($operation, $endpoint, $set = null, $token = null) {
    try {
      $client = new Client();
      switch ($operation) {
        case 'sets':
          $data = ['verb' => 'ListSets'];
          $xml = @simplexml_load_file($endpoint . '?' . http_build_query($data));
          $response = json_decode(json_encode($xml));
          return $response->ListSets->set;
          break;
        case 'recordsperset':
          $data = ['verb' => 'ListRecords','set' => $set,'metadataPrefix' => 'oai_dc'];
          $res = $client->get($endpoint . '?' . http_build_query($data));
          if($res->getStatusCode() == 200) { 
            $xml = $this->clearXml($res->getBody());
            $response = @simplexml_load_string($xml);
            return $response->ListRecords;
          }
          break;
        case 'next':
          $data = ['verb' => 'ListRecords','resumptionToken' => $token];
          $res = $client->get($endpoint . '?' . http_build_query($data));
          if($res->getStatusCode() == 200) { 
            $xml = $this->clearXml($res->getBody());
            $response = @simplexml_load_string($xml);
            return $response->ListRecords;
          }
          break;
      }
    }
    catch (RequestException $e) {
      watchdog_exception('brc_elasticsearch_custom', $e->getMessage());
    }
  }

  public function processResult($data, $type, $parameters = null) {
    $data = json_decode($data);
    $result = ['nodes' => [], 'pagination' => [], 'original' => $data];
    $node = [];
    $types = $this->checkedType($this->config->get('categories')[$type]['mapping']);
    switch ($type) {
      case 'suggest':
        foreach ($data->suggest->{'my-suggestion'} as $key => $value) {
          foreach ($value->options as $ok => $option) {
            $suggest = $ok < 2 ? str_replace($value->text, $option->text, $term) : $option->text;
            if(!in_array($suggest, $result)){
              $result[] = $suggest;
            }
          }
        }
        break;
      case 'mediawiki':
        foreach ($data->hits->hits as $key => $value) {
          array_push($node, array(
            'score' => $value->_score,
            '_score' => $value->_score * $parameters['score'],
            'type' => $parameters['name'],
            'type_id' => 'mediawiki',
            'title' => isset($value->_source->title) ? $value->_source->title : '',
            'body' => isset($value->_source->text) ? text_summary($value->_source->text) : '',
            'path' => (isset($parameters['endpoint']) && !empty($parameters['endpoint'])) ? $parameters['endpoint'].'/index.php?title='.str_replace(' ', '_', $value->_source->title) : '/'.str_replace(' ', '_', $value->_source->title),
            'target' => '_blank',
            'order' => $parameters['from']+$key,
          ));
        }
        break;
      case 'babel':
        foreach ($data->hits->hits as $key => $value) {
          if(strpos($value->_source->nid, 'babel.')){
            array_push($node, array(
              'score' => $value->_score,
              '_score' => $value->_score * $parameters['score'],
              'type' => $types[$value->_source->type],
              'type_id' => 'babel',
              'nid' => isset($value->_source->nid) ? $value->_source->nid : '',
              'title' => isset($value->_source->title) ? $value->_source->title : '',
              'body' => isset($value->_source->body) ? text_summary($value->_source->body) : '',
              'image' => array(
                'src' => isset($value->_source->image->src) ? $value->_source->image->src : '',
                'alt' => isset($value->_source->image->alt) ? $value->_source->image->alt : '',
                'title' => isset($value->_source->image->title) ? $value->_source->image->title : '',
                'author' => isset($value->_source->image->author) ? $value->_source->image->author : '',
                'rights' => isset($value->_source->image->rights) ? $value->_source->image->rights : '',
                'longdesc' => isset($value->_source->image->longdesc) ? $value->_source->image->longdesc : '',
              ),
              'path' => is_object($value->_source->path) ? json_decode(json_encode($value->_source->path), true)[1] : $value->_source->path, 
              'target' => '_blank',
              'order' => $parameters['from']+$key,
            ));
          }else {
            $data->hits->total--;
          }
        }
        break;
      case 'ojs':
        foreach ($data->hits->hits as $key => $value) {
          if(strpos($value->_source->nid, 'publicaciones.')){
            array_push($node, array(
              'score' => $value->_score,
              '_score' => $value->_score * $parameters['score'],
              'type' => $parameters['name'],
              'type_id' => 'ojs',
              'nid' => isset($value->_source->nid) ? $value->_source->nid : '',
              'title' => isset($value->_source->title) ? $value->_source->title : '',
              'body' => isset($value->_source->body) ? text_summary($value->_source->body) : '',
              'image' => array(
                'src' => isset($value->_source->image->src) ? $value->_source->image->src : '',
                'alt' => isset($value->_source->image->alt) ? $value->_source->image->alt : '',
                'title' => isset($value->_source->image->title) ? $value->_source->image->title : '',
                'author' => isset($value->_source->image->author) ? $value->_source->image->author : '',
                'rights' => isset($value->_source->image->rights) ? $value->_source->image->rights : '',
                'longdesc' => isset($value->_source->image->longdesc) ? $value->_source->image->longdesc : '',
              ),
              'path' => is_object($value->_source->path) ? json_decode(json_encode($value->_source->path), true)[1] : $value->_source->path, 
              'target' => '_blank',
              'order' => $parameters['from']+$key,
            ));
          }else {
            $data->hits->total--;
          }
        }
        break;
      default:
        foreach ($data->hits->hits as $key => $value) {   
          $image = null;
          if (!empty($value->_source->image->src)) {
             $image = !empty($parameters['image_style']) ? image_style_url($parameters['image_style'], $value->_source->image->src) : file_create_url($value->_source->image->src);
             $image = str_replace("//assets.", "//admin.", $image);
          }
          $target = '_self';
          if(isset($value->_source->path) && strpos($value->_source->path, '/http') === 0 || strpos($value->_source->path, 'http') === 0 || strpos($value->_source->path, '/https') === 0 || strpos($value->_source->path, 'https') === 0) {
            $target = '_blank';
          }
          array_push($node, array(
            'score' => $value->_score,
            '_score' => $value->_score * $parameters['score'],
            'type' => $types[$value->_source->type],
            'nid' => isset($value->_source->nid) ? $value->_source->nid : '',
            'title' => isset($value->_source->title) ? $value->_source->title : '',
            'subtitle' => isset($value->_source->subtitle) ? $value->_source->subtitle : '',
            'summary' => isset($value->_source->summary) ? $value->_source->summary : '',
            'abstract' => isset($value->_source->abstract) ? $value->_source->abstract : '',
            'body' => isset($value->_source->body) ? text_summary($value->_source->body) : '',
            'book' => isset($value->_source->book) ? $value->_source->book : '',
            'date' => isset($value->_source->date) ? $value->_source->date : '',
            'date_end' => isset($value->_source->date_end) ? $value->_source->date_end : '',
            'date_full'  => isset($value->_source->date_full) ? $value->_source->date_full : '',
            'date_collection' => isset($value->_source->date_collection) ? $value->_source->date_collection : '',
            'hour' => isset($value->_source->hour) ? $value->_source->hour : '',
            'prefix' => isset($value->_source->prefix) ? $value->_source->prefix : '',
            'category' => isset($value->_source->category) ? $value->_source->category : '',
            'subcategory' => isset($value->_source->subcategory) ? $value->_source->subcategory : '',
            'price' => isset($value->_source->price) ? $value->_source->price : '',
            'image' => array(
              'src' => $image,
              'alt' => isset($value->_source->image->alt) ? $value->_source->image->alt : '',
              'title' => isset($value->_source->image->title) ? $value->_source->image->title : '',
              'author' => isset($value->_source->image->author) ? $value->_source->image->author : '',
              'rights' => isset($value->_source->image->rights) ? $value->_source->image->rights : '',
              'longdesc' => isset($value->_source->image->longdesc) ? $value->_source->image->longdesc : '',
            ),
            'city' => isset($value->_source->city) ? $value->_source->city : '',
            'city_place' => isset($value->_source->city_place) ? $value->_source->city_place : '',
            'featured' => isset($value->_source->featured) ? $value->_source->featured : '',
            'display' => isset($value->_source->display) ? $value->_source->display : '',
            'available' => isset($value->_source->available) ? $value->_source->available : '',
            'area' => isset($value->_source->area) ? $value->_source->area : '',
            'public' => isset($value->_source->public) ? $value->_source->public : '',
            'tags' => isset($value->_source->tags) ? $value->_source->tags : '',
            'registry_number' => isset($value->_source->registry_number) ? $value->_source->registry_number : '',
            'score_order' => isset($value->_source->score_order) ? $value->_source->score_order : '',
            'style' => isset($value->_source->style) ? $value->_source->style : '',
            'technique' => isset($value->_source->technique) ? $value->_source->technique : '',
            'material' => isset($value->_source->material) ? $value->_source->material : '',
            'modality' => isset($value->_source->modality) ? $value->_source->modality : '',
            'function' => isset($value->_source->function) ? $value->_source->function : '',
            'region' => isset($value->_source->region) ? $value->_source->region : '',
            'period' => isset($value->_source->period) ? $value->_source->period : '',
            'isbn' => isset($value->_source->isbn) ? $value->_source->isbn : '',
            'editorial' => isset($value->_source->editorial) ? $value->_source->editorial : '',
            'author' => isset($value->_source->author) ? $value->_source->author : '',
            'path' => isset($value->_source->path) ? $value->_source->path : '',
            'target' => $target,
            'path_external' => isset($value->_source->path_external) ? $value->_source->path_external : '',
            'status' => isset($value->_source->status) ? $value->_source->status : '',
            'created' => isset($value->_source->created) ? $value->_source->created : '',
            'changed' => isset($value->_source->changed) ? $value->_source->changed : '',
            'access' => isset($value->_source->type) ? $value->_source->type == 'makemake' ? 'Disponible para socios' : '' : '',
            'order' => $parameters['from']+$key,
          ));
        }
        break;
    }

    array_push($result['nodes'], $node);
    array_push($result['pagination'], $data);
    return $result;
  }

  public function processFieldImage($tid) {
    $media = Media::load($tid);

    $fid = $media->getSource()->getSourceFieldValue($media);
    $file = File::load($fid);

    $term_author = Term::load($media->get('field_media_author')->target_id);
    $term_author = $term_author->name->value;

    $term_rights = Term::load($media->get('field_media_rights')->target_id);
    $term_rights = $term_rights->name->value;

    return [
      'src' => $file->url(),
      'alt' => $media->get('field_media_image')->alt,
      'title' => $media->get('field_media_image')->title,
      'width' => $media->get('field_media_image')->width,
      'height' => $media->get('field_media_image')->height,
      'author' => $term_author,
      'rights' => $term_rights,
      'longdesc' => $media->get('field_media_longdesc')->value,
    ];
  }

  public function processFieldImageSet($type, $title, $path){
    $t = explode(":", $type)[0];
    switch ($t) {
      case 'banrep':
       $uri = 'https://publicaciones.banrepcultural.org/public/site/images/admin/Revista_300px.jpg';
       break;
      case 'fian':
       $uri = 'https://publicaciones.banrepcultural.org/public/site/images/admin/boletin_arqueologia.png';
       break;
      case 'reporte-mercado-laboral':
       $uri = 'https://publicaciones.banrepcultural.org/public/site/images/admin/ReportesMercado_300px.jpg';
       break;
      case 'bmo':
       $uri = 'https://publicaciones.banrepcultural.org/public/site/images/admin/boletin_museo.png';
       break;
      case 'boletin_cultural':
       $uri = 'https://publicaciones.banrepcultural.org/public/site/images/admin/boletin_cultural1.png';
       break;
      case 'emisor':
       $uri = 'https://publicaciones.banrepcultural.org/public/site/images/admin/ReportesEmisor_300px.jpg';
       break;
      default:
       $uri = 'http://babel.banrepcultural.org/utils/getthumbnail/collection/' . str_replace("http://babel.banrepcultural.org/cdm/ref/collection/", "", $path);
       break;
    }
    return $fields = [
        'src' => $uri,
        'alt' => $title,
        'title' => $title,
        'author' => '',
        'rights' => '',
        'longdesc' => '',
      ];
  }

  public function checkedType($t) {
    $r = [];
    foreach ($t as $k => $v) {
      if(isset($v['type'])){
        if($v['type'] == 1){
          $r[$k] = $v['name'];
        }
      }else if(isset($v['field'])){
        if($v['field'] !== 0){
          $r[$k] = $k;
        }
      }else if($v !== 0){
        $r[$k] = $v;
      }
    }
    return $r;
  }

  public function translateLog($data, $log) {
    if(is_string($data)){
      $data = json_decode($data);
      $log['total']++;
      $log['successful'] += $data->_shards->successful;
      $log['failed'] += $data->_shards->failed;
    }
    if(is_array($data)){
      if($data['status'] == 'error'){
        $log['total']++;
        $log['failed']++;
        $log['error'][] = $data;
      }
    }
    return $log;
  }

  public function typeNameSets($type){
    $types = array_merge($this->mappingCategory('babel'), $this->mappingCategory('ojs'));
    return isset($types[$type]) ? $types[$type] : '';
  }

  public function clearType($t) {
    $t = str_replace(':', '_', $t);
    $t = str_replace('.', '', $t);
    $t = str_replace('-', '_', $t);
    return strtolower($t);
  }
  
  public function clearObject($node) {
    foreach ($node as $key => $value) {
      if (empty($value)) {
        unset($node->$key);
      }
    }
    return $node;
  }

  public function clearXml($xml) {
    $xml = (string)$xml;
    $xml = str_replace('xml:lang="es-ES"', '', $xml);
    $xml = str_replace(':dc', '', $xml);
    $xml = str_replace('dc:', '', $xml);
    return $xml;
  }
}
