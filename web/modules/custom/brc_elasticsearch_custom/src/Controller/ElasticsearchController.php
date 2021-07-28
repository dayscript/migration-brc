<?php
/**
 * @file
 * Contains \Drupal\brc_elasticsearch_custom\Controller\ElasticsearchController.
 */

namespace Drupal\brc_elasticsearch_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brc_elasticsearch_custom\ElasticsearchManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ElasticsearchController extends ControllerBase {

  public $term;
  public $type;
  public $fields;
  public $filter;
  public $page;
  public $config;
  protected $commonFunction;

  public function __construct(ConfigFactory $config_factory, ElasticsearchManagerInterface $interface) {
    $this->config = $config_factory->get('brc_elasticsearch_custom.settings');
    $this->commonFunction = $interface;

    if (isset($_REQUEST['term'])) {
      $this->term = $_REQUEST['term'];
    }else {
      $this->term = null;
    }
    if (isset($_REQUEST['type'])) {
      $this->type = $_REQUEST['type'];
    }else {
      $this->type = 'general';
    }
    if (isset($_REQUEST['fields'])) {
      $this->fields = $_REQUEST['fields'];
    }else {
      $this->fields = null;
    }
    if (isset($_REQUEST['filter'])) {
      $this->filter = $_REQUEST['filter'];
      $this->type = 'general_' . $_REQUEST['filter'];
    }else {
      $this->filter = null;
    }

    if (isset($_REQUEST['page'])) {
      $this->page = $_REQUEST['page'];
    }else {
      $this->page = null;
    }
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('brc_elasticsearch_custom.manager')
    );
  }

  public function elastic() {
    $categories = $this->config->get('categories');
    $indices = [];
    foreach ($this->config->get('indices') as $ik => $i) {
      $indices[$i['index']] = $i;
    }
    $seekers = $this->config->get('sconfig');
    $categorys = $seekers[$this->type];
    $config = $seekers[$this->type]['config'];
    $types = [];
    $fields = $this->fields;
    $parameters = [];
    $params = [];
    $result = ['nodes' => [], 'pagination' => ['all' => ['total' => 0]], 'suggest' => []];
    $terms = isset($this->term) && !empty($this->term) ? explode(':',  $this->term) : null;

    $suggest_term = $terms[0];
    if ($terms !== null) {
      $clte = null;
      $clte = array_search('title', explode(',', $this->fields));
      if ($clte !== null) {
        $suggest_term = $terms[$clte];
        $terms[$clte] = count(explode(" ", $terms[$clte])) > 6 ? $terms[$clte] : '"'.$terms[$clte].'"';
      }
    }

    if(!empty($categorys)){
      foreach ($categorys as $ck => $c) {
        switch ($ck) {
          case 'node':
            $types = $this->commonFunction->checkedType($c['type']);
            foreach ($types as $tk => $t) {
              $parameters[$t] = [
                'category' => $t,
                'type' => $categories[$ck]['mapping'][$t]['name'],
                'score' => $categories[$ck]['mapping'][$t]['score'],
                'indice' => $categories[$ck]['endpoint'][1]['indice'],
                'doc' => $indices[$categories[$ck]['endpoint'][1]['indice']]['doc'],
                'endpoint' => $categories[$ck]['endpoint'][1]['endpoint'],
                'name' =>  $categories[$ck]['endpoint'][1]['name'],
                'from' => isset($this->page) ? $this->page : 0,
                'size' => $config['size'],
                'image_style' => $config['image_style'],
              ];
              $params[$ck][$t] = ['query' => ['bool' => ['must' => []]],'from' => '','size' => '','sort' => []];

              array_push($params[$ck][$t]['query']['bool']['must'], ['term' => ['type' => $t]]);

              switch ($t) {
                case 'activity':
                  array_push($params[$ck][$t]['query']['bool']['must'], ['range' => ['date' => ['gte' => 'now/d']]]);
                  $params[$ck][$t]['sort'] = ["date" => ["order" => "asc"], "_score" => ["order" => "desc"]];
                  break;
                case 'article':
                  $params[$ck][$t]['sort'] = $this->type == 'general' ? ["date" => ["order" => "desc"], "_score" => ["order" => "desc"]] : ["created" => ["order" => "desc"]];
                  break;
                case 'book':
                  $params[$ck][$t]['sort'] = $this->type == 'general' ? ["_score" => ["order" => "desc"]] : ["date" => ["order" => "desc"]];
                  break;
                case 'coleccion_bibliografica':
                  $params[$ck][$t]['sort'] = ["created" => ["order" => "asc"]];
                  break;
                case 'estampilla':
                  $params[$ck][$t]['sort'] = ["created" => ["order" => "asc"]];
                  break;
                case 'instrumento':
                  $params[$ck][$t]['sort'] = ["created" => ["order" => "asc"]];
                  break;
                case 'minisitio':
                  if ($this->type !== 'general') {
                    if (in_array('title', explode(',', $field))) {
                      array_push($params[$ck][$t]['query']['bool']['must'], ['query_string' => ['default_field' => 'category', 'query' => 'exposiciones']]);
                      array_push($params[$ck][$t]['query']['bool']['must'], ['query_string' => ['default_field' => 'featured', 'query' => true]]);
                      $field = null;
                    }
                  }
                  $params[$ck][$t]['sort'] = ["date" => ["order" => "desc"], "_score" => ["order" => "desc"]];
                  break;
                case 'multimedia':
                  $params[$ck][$t]['sort'] = ["created" => ["order" => "desc"], "_score" => ["order" => "desc"]];
                  break;
                case 'obra_de_arte':
                  $params[$ck][$t]['sort'] = $this->type == 'general' ? ["date" => ["order" => "asc"], "_score" => ["order" => "desc"]] : ["date_collection" => ["order" => "desc"]];
                  if ($this->type === 'node_category_obra_de_arte') {
                    $params[$ck][$t]['sort'] = ["date_collection" => ["order" => "desc"]];
                    if (in_array('title', explode(',', $field))) {
                      $field = null;
                    }
                  }
                  break;
                case 'pieza_arqueologica':
                  $params[$ck][$t]['sort'] = ["_score" => ["order" => "desc"]];
                  break;
                case 'pieza_coleccion_monedas_billetes':
                  $params[$ck][$t]['sort'] = ["_score" => ["order" => "desc"]];
                  break;
                case 'publicacion':
                  $params[$ck][$t]['sort'] = $this->type == 'general' ? ["_score" => ["order" => "desc"]] : ["date" => ["order" => "desc"]]; 
                  break;
                case 'service':
                  $params[$ck][$t]['sort'] = ["_score" => ["order" => "desc"]];
                  break;
              }

              if($this->type == 'general'){
                if(isset($this->fields) && !empty($this->fields)){
                  $fg = explode(',', $this->fields);
                  foreach ($fg as $key => $f) {
                    if($f !== 'title' && $f !== 'type' && $f !== 'date' && $f !== 'date_end'){
                      array_push($params[$ck][$t]['query']['bool']['must'], ['query_string' => ['default_field' => $f, 'query' => $terms[$key]]]);
                    }
                    if ($f == 'date') {
                      array_push($params[$ck][$t]['query']['bool']['must'], ['range' => ['date' => ['gte' => str_replace('"', '', $terms[$key]), 'lte' => str_replace('"', '', $terms[$key+1])]]]);
                    }
                  }
                }
                $this->fields = null;
              }else {
                if (in_array('title', explode(',', $this->fields))) {
                  $this->fields = null;
                }
              }

              if (!empty($this->fields) && !empty($terms)) {
                $fg = explode(',', $this->fields);
                foreach ($fg as $key => $f) {
                  switch ($f) {
                    case 'date':              
                    case 'date_end':
                      if ($t == 'book') {
                        array_push($params[$ck][$t]['query']['bool']['must'], ['range' => [$f => ['gte' => $terms[$key].'-01-01', 'lte' => $terms[$key].'-12-31']]]);
                      }else if($t == 'activity'){
                        if (in_array('date', $fg) && $f == 'date') {
                          $date_activity = explode('-', str_replace('"', '', $terms[$key]))[0].'-'.explode('-', str_replace('"', '', $terms[$key]))[1].'-'.date("d");
                          array_push($params[$ck][$t]['query']['bool']['must'], ['range' => ['date' => ['gte' => $date_activity, 'lte' => str_replace('"', '',$terms[$key])]]]);
                        }
                        if (in_array('date_end', $fg) && $f == 'date_end') {
                          $date_activity = explode('-', str_replace('"', '', $terms[$key]))[0].'-'.explode('-', str_replace('"', '', $terms[$key]))[1].'-01';
                          array_push($params[$ck][$t]['query']['bool']['must'], ['range' => ['date' => ['gte' => $date_activity, 'lte' => str_replace('"', '',$terms[$key])]]]);
                          array_push($params[$ck][$t]['query']['bool']['must'], ['range' => ['date_end' => ['lte' => str_replace('"', '',$terms[$key])]]]);
                        }
                        $params[$ck][$t]['sort'] = ["date" => ["order" => "asc"], "_score" => ["order" => "desc"]];
                      }else {
                        if (in_array('date', $fg) && !in_array('date_end', $fg)) {
                          array_push($params[$ck][$t]['query']['bool']['must'], ['range' => [$f => ['gte' => str_replace('"', '', $terms[$key])]]]);
                        }elseif (in_array('date', $fg) && in_array('date_end', $fg) && $f == 'date') {
                          array_push($params[$ck][$t]['query']['bool']['must'], ['range' => ['date' => ['gte' => str_replace('"', '',$terms[$key]), 'lte' => str_replace('"', '',$terms[$key+1])]]]);
                        }
                      }
                      $params[$ck][$t]['sort'] = ["date" => ["order" => "desc"]];
                      break;
                    case 'date_collection':
                      array_push($params[$ck][$t]['query']['bool']['must'], ['range' => [$f => ['gte' => str_replace('"', '', $terms[$key])]]]);
                      break;
                    case 'date_full':
                      array_push($params[$ck][$t]['query']['bool']['must'], ['range' => [$f => ['gte' => $terms[$key].'-01-01', 'lte' => $terms[$key].'-12-31']]]);
                      break;
                    case 'sort':
                      switch ($terms[$key]) {
                        case 'random':
                          $Infields = ['date', 'date_collection', 'created', 'changed'];//common_types_format($content['fields']);
                          $params[$ck][$t]['sort'] = [$Infields[rand(0,count($Infields)-1)] => ["order" => $order[rand(0,count($order)-1)]]];
                          break;
                        case 'date_asc':
                          $params[$ck][$t]['sort'] = ['date' => ["order" => 'asc']];
                          break;
                        case 'date_desc':
                          $params[$ck][$t]['sort'] = ['date' => ["order" => 'desc']];
                          break;
                        case 'price_asc':
                          $params[$ck][$t]['sort'] = ['price' => ["order" => 'asc']];
                          break;
                        case 'price_desc':
                          $params[$ck][$t]['sort'] = ['price' => ["order" => 'desc']];
                          break;
                        case 'image_default':
                          $params[$ck][$t]['query']['bool']['must_not'] = [];
                          array_push($params[$ck][$t]['query']['bool']['must_not'], ['query_string' => ['default_field' => 'image.src', 'query' => '"public://default_images/defecto_coleccion_arte.jpg"']]);
                          break;
                      }
                      break;
                    default:
                      array_push($params[$ck][$t]['query']['bool']['must'], ['query_string' => ['default_field' => $f, 'query' => $terms[$key]]]);
                      break;
                  }
                }     
              }
              if (empty($this->fields) && !empty($terms)) {
                $fg = [];
                foreach ($this->commonFunction->checkedType($c['container']) as $key => $f) {
                  $fg[] = $f . '^' . $this->commonFunction->bootingFields($f);
                }
                array_push($params[$ck][$t]['query']['bool']['must'], ['query_string' => ['fields' => $fg, 'query' => (in_array('title', $fg)) ? $terms[0] : $terms[0]]]);
              }

              array_push($params[$ck][$t]['query']['bool']['must'], ['term' => ['status' => true]]);
              $params[$ck][$t]['from'] = (isset($this->page) && !empty($this->page)) ? $this->page : (isset($parameters[$t]['from']) && !empty($parameters[$t]['from'])) ? $parameters[$t]['from'] : 0;
              $params[$ck][$t]['size'] = $parameters[$t]['size'];  
            }
            break;
          case 'taxonomy':
            $types = $this->commonFunction->checkedType($c['type']);
            foreach ($types as $tk => $t) {
              $parameters[$t] = [
                'category' => $t,
                'type' => $categories[$ck]['mapping'][$t]['name'],
                'score' => $categories[$ck]['mapping'][$t]['score'],
                'indice' => $categories[$ck]['endpoint'][1]['indice'],
                'doc' => $indices[$categories[$ck]['endpoint'][1]['indice']]['doc'],
                'endpoint' => $categories[$ck]['endpoint'][1]['endpoint'],
                'name' =>  $categories[$ck]['endpoint'][1]['name'],
                'from' => isset($this->page) ? $this->page : 0,
                'size' => $config['size'],
                'image_style' => $config['image_style'],
              ];
              $params[$ck][$t] = ['query' => ['bool' => ['must' => []]],'from' => '','size' => '','sort' => []];
              array_push($params[$ck][$t]['query']['bool']['must'], ['term' => ['type' => $t]]);

              if($this->type == 'general'){
                if(isset($this->fields) && !empty($this->fields)){
                  $fg = explode(',', $this->fields);
                  foreach ($fg as $key => $f) {
                    if($f !== 'title' && $f !== 'type' && $f !== 'date' && $f !== 'date_end'){
                      array_push($params[$ck][$t]['query']['bool']['must'], ['query_string' => ['default_field' => $f, 'query' => $terms[$key]]]);
                    }
                    if ($f == 'date') {
                      array_push($params[$ck][$t]['query']['bool']['must'], ['range' => ['date' => ['gte' => str_replace('"', '', $terms[$key]), 'lte' => str_replace('"', '', $terms[$key+1])]]]);
                    }
                  }
                }
                $this->fields = null;
              }else {
                if (in_array('title', explode(',', $this->fields))) {
                  $this->fields = null;
                }
              }

              if (empty($this->fields) && !empty($terms)) {
                $fg = [];
                foreach ($this->commonFunction->checkedType($c['container']) as $key => $f) {
                  $fg[] = $f . '^'  . $this->commonFunction->bootingFields($ck, $f);
                }
                array_push($params[$ck][$t]['query']['bool']['must'], ['query_string' => ['fields' => $fg, 'query' => (in_array('title', $fg)) ? $terms[0] : $terms[0]]]);
              }

              array_push($params[$ck][$t]['query']['bool']['must'], ['term' => ['status' => true]]);
              $params[$ck][$t]['from'] = (isset($this->page) && !empty($this->page)) ? $this->page : (isset($parameters[$t]['from']) && !empty($parameters[$t]['from'])) ? $parameters[$t]['from'] : 0;
              $params[$ck][$t]['size'] = $parameters[$t]['size']; 
              $params[$ck][$t]['sort'] = ["_score" => ["order" => "desc"]];
            }
            break;
          case 'mediawiki':
          case 'babel':
          case 'ojs':
          case 'makemake':
            $types = $this->commonFunction->checkedType($c['type']);
            foreach ($types as $tk => $t) {
              $parameters[($ck == 'mediawiki') ? $ck : $t] = [
                'category' => $ck,
                'type' => $categories[$ck]['mapping'][$t]['name'],
                'score' => $categories[$ck]['mapping'][$t]['score'],
                'indice' => $categories[$ck]['endpoint'][1]['indice'],
                'doc' => $indices[$categories[$ck]['endpoint'][1]['indice']]['doc'],
                'endpoint' => $categories[$ck]['endpoint'][1]['endpoint'],
                'name' =>  $categories[$ck]['endpoint'][1]['name'],
                'from' => isset($this->page) ? $this->page : 0,
                'size' => $config['size'],
                'image_style' => $config['image_style'],
              ];
              $params[$ck][($ck == 'mediawiki') ? $ck : $t] = ['query' => ['bool' => ['must' => []]],'from' => '','size' => '','sort' => []];
              if($ck !== 'mediawiki'){
                array_push($params[$ck][($ck == 'mediawiki') ? $ck : $t]['query']['bool']['must'], ['term' => ['type' => $t]]);
              }

              if($this->type == 'general'){
                if(isset($this->fields) && !empty($this->fields)){
                  $fg = explode(',', $this->fields);
                  foreach ($fg as $key => $f) {
                    if($f !== 'title' && $f !== 'type' && $f !== 'date' && $f !== 'date_end'){
                      array_push($params[$ck][($ck == 'mediawiki') ? $ck : $t]['query']['bool']['must'], ['query_string' => ['default_field' => $f, 'query' => $terms[$key]]]);
                    }
                    if ($f == 'date') {
                      array_push($params[$ck][($ck == 'mediawiki') ? $ck : $t]['query']['bool']['must'], ['range' => ['date' => ['gte' => str_replace('"', '', $terms[$key]), 'lte' => str_replace('"', '', $terms[$key+1])]]]);
                    }
                  }
                }
                $this->fields = null;
              }else {
                if (in_array('title', explode(',', $this->fields))) {
                  $this->fields = null;
                }
              }

              if (empty($this->fields) && !empty($terms)) {
                $fg = [];
                foreach ($this->commonFunction->checkedType($c['container']) as $key => $f) {
                  $fg[] = $f . '^' . $this->commonFunction->bootingFields($ck, $f);
                }
                array_push($params[$ck][($ck == 'mediawiki') ? $ck : $t]['query']['bool']['must'], ['query_string' => ['fields' => $fg, 'query' => (in_array('title', $fg)) ? $terms[0] : $terms[0]]]);
              }

              $params[$ck][($ck == 'mediawiki') ? $ck : $t]['from'] = (isset($this->page) && !empty($this->page)) ? $this->page : (isset($parameters[($ck == 'mediawiki') ? $ck : $t]['from']) && !empty($parameters[($ck == 'mediawiki') ? $ck : $t]['from'])) ? $parameters[($ck == 'mediawiki') ? $ck : $t]['from'] : 0;
              $params[$ck][($ck == 'mediawiki') ? $ck : $t]['size'] = $parameters[($ck == 'mediawiki') ? $ck : $t]['size']; 
              $params[$ck][($ck == 'mediawiki') ? $ck : $t]['sort'] = ["_score" => ["order" => "desc"]];
            }
            break;
        }
      }
    }

    if(!empty($this->filter)){
      if($this->filter == 'collections' || $this->filter == 'others' || $this->filter == 'biblioteca_virtual' || $this->filter == 'mediawiki' || $this->filter == 'makemake'){
        $clave = array_search('type', explode(',', $fields));
        $fltr = $terms[$clave];
        
        foreach ($params as $pck => $pc) {
          foreach ($pc as $ptk => $pt) {
            if($ptk === $fltr) {
              try {
                $response = $this->commonFunction->processResult($this->commonFunction->clientElasticsearch('search', $parameters[$ptk]['indice'], $parameters[$ptk]['doc'], $pt), $pck, $parameters[$ptk]);
                $clave = $parameters[$ptk]['category'];
                $result['nodes'][$clave] = empty($result['nodes'][$clave]) ? $response['nodes'][0] : array_merge($result['nodes'][$clave], $response['nodes'][0]);
                $result['pagination'][$clave]['total'] = $response['pagination'][0]->hits->total;
                $result['pagination']['all']['total'] += $response['pagination'][0]->hits->total;
                $result['params'][$ptk] = [$pt, $parameters[$ptk]];
              } catch (Exception $e) {
                return new JsonResponse($e->getMessage());
              }
            }
          }
        }
      }
    }else {
      foreach ($params as $pck => $pc) {
        foreach ($pc as $ptk => $pt) {
          try {
            $response = $this->commonFunction->processResult($this->commonFunction->clientElasticsearch('search', $parameters[$ptk]['indice'], $parameters[$ptk]['doc'], $pt), $pck, $parameters[$ptk]);
            $clave = $parameters[$ptk]['category'];
            $result['nodes'][$clave] = empty($result['nodes'][$clave]) ? $response['nodes'][0] : array_merge($result['nodes'][$clave], $response['nodes'][0]);
            $result['pagination'][$clave]['total'] = $response['pagination'][0]->hits->total;
            $result['pagination']['all']['total'] += $response['pagination'][0]->hits->total;
            $result['params'][$ptk] = [$pt, $parameters[$ptk]];
          } catch (Exception $e) {
            return new JsonResponse($e->getMessage());
          }
        }
        try {
          /*Revisar en prod
          if($pck == 'node'){
            $suggest = ['suggest' => ["my-suggestion" => [ "text" => $suggest_term, "term" => [ "field" => "title"] ] ] ];
            $response = $this->commonFunction->processResult($this->commonFunction->clientElasticsearch('suggest', $parameters[$t]['index'], $parameters[$t]['doc'], $suggest), 'suggest');
            $result['suggest'] = $response;
          }*/
        } catch (Exception $e) {
          return new JsonResponse($e->getMessage());
        }
      }
    }
    return new JsonResponse($result);
  }

  public function taxonomys() {
    $result = [];
    array_push($result, [
      'id' => 'pages',
      'types' => [
        [
          'id' => 'category',
          'name' => 'Tipo',
          'format' => 'radio',
          'filters' => $this->commonFunction->mappingFieldsTaxonomy('tipo_de_minisitio', 'category', 'pages', 'radio')
        ],
        [
          'id' => 'area',
          'name' => 'Área misional',
          'format' => 'checkbox',
          'filters' => $this->commonFunction->mappingFieldsTaxonomy('area', 'area', 'pages', 'checkbox')
        ],
        [
          'id' => 'date',
          'name' => 'Fecha',
          'format' => 'date',
          'filters' => [
            'id' => 'pages-date',
            'id_start' => 'pages-date_start',
            'id_end' =>  'pages-date_end',
            'config' => [
              'start' => 2000, 'end' =>  2020, 'step' => 1, 'label' => false,
            ]
          ],
          'father' => [
            'pages-tipo_de_minisitio-41800'
          ]
        ]
      ]]
    );
    array_push($result, [
    'id' => 'collections',
    'types' => [
      [
        'id' => 'type',
        'name' => 'Colecciones',
        'format' => 'radio',
        'filters' => [
          ['id' => 'collections-type-obra_de_arte', 'name' => 'collections-type', 'value' => 'obra_de_arte', 'label' => "Colección de Arte", 'active' => false],
          ['id' => 'collections-type-pieza_arqueologica', 'name' => 'collections-type', 'value' => 'pieza_arqueologica', 'label' => "Colección del Museo del Oro", 'active' => false],
          ['id' => 'collections-type-estampilla', 'name' => 'collections-type', 'value' => 'estampilla', 'label' => "Estampillas", 'active' => false],
          ['id' => 'collections-type-pieza_coleccion_monedas_billetes', 'name' => 'collections-type', 'value' => 'pieza_coleccion_monedas_billetes', 'label' => "Billetes y Monedas", 'active' => false],
          ['id' => 'collections-type-instrumento', 'name' => 'collections-type', 'value' => 'instrumento', 'label' => "Instrumentos Musicales", 'active' => false],
          ['id' => 'collections-type-coleccion_bibliografica', 'name' => 'collections-type', 'value' => 'coleccion_bibliografica', 'label' => "Documentos especiales", 'active' => false],
          ['id' => 'collections-type-biblioteca_virtual', 'name' => 'collections-type', 'value' => 'biblioteca_virtual', 'label' => "Biblioteca Virtual", 'active' => false],
          ['id' => 'collections-type-mediawiki', 'name' => 'collections-type', 'value' => 'mediawiki', 'label' => "Enciclopedia", 'active' => false],
          ['id' => 'collections-type-makemake', 'name' => 'collections-type', 'value' => 'makemake', 'label' => $this->config->get('categories')['makemake']['endpoint'][1]['name'], 'active' => false]
          ]
      ],
      [
        'id' => 'date',
        'name' => 'Fecha',
        'format' => 'date',
        'filters' => [
          'id' => 'collections-date',
          'id_start' => 'collections-date_start',
          'id_end' =>  'collections-date_end',
          'config' => [
            'start' => 2000, 'end' =>  2020, 'step' => 1, 'label' => false,
          ]
        ]
      ]
    ]]);
    array_push($result, [
    'id' => 'activity',
    'types' => [
      [
        'id' => 'city',
        'name' => 'Ciudad',
        'format' => 'radio',
        'filters' => $this->commonFunction->mappingFieldsTaxonomy('city', 'city', 'activity', 'radio')
      ],
      [
        'id' => 'price',
        'name' => 'Tarifa',
        'format' => 'radio',
        'filters' => $this->commonFunction->mappingFieldsTaxonomy('price', 'price', 'activity', 'radio')
      ],
      [
        'id' => 'publico_objetivo',
        'name' => 'Público Objetivo',
        'format' => 'checkbox',
        'filters' => $this->commonFunction->mappingFieldsTaxonomy('publico_objetivo', 'public', 'activity', 'checkbox')
      ],
      [
        'id' => 'area',
        'name' => 'Tema',
        'format' => 'checkbox',
        'filters' => $this->commonFunction->mappingFieldsTaxonomy('area', 'area', 'activity', 'checkbox')
      ],
      [
        'id' => 'date',
        'name' => 'Fecha',
        'format' => 'date',
        'filters' => [
          'id' => 'activity-date',
          'id_start' => 'activity-date_start',
          'id_end' =>  'activity-date_end',
          'config' => [
            'start' => 1, 'end' =>  12, 'step' => 1, 'label' => true,
          ]
        ]
      ]
    ]]);
    array_push($result, [
    'id' => 'article',
    'types' => [
      [
        'id' => 'city',
        'name' => 'Ciudad',
        'format' => 'radio',
        'filters' => $this->commonFunction->mappingFieldsTaxonomy('city', 'city', 'article', 'radio')
      ],
      [
        'id' => 'category',
        'name' => 'Tipo',
        'format' => 'radio',
        'filters' => $this->commonFunction->mappingFieldsTaxonomy('categoria_de_noticia', 'category', 'article', 'radio')
      ],
      [
        'id' => 'date',
        'name' => 'Fecha',
        'format' => 'date',
        'filters' => [
          'id' => 'article-date',
          'id_start' => 'article-date_start',
          'id_end' =>  'article-date_end',
          'config' => [
            'start' => 2008, 'end' =>  2020, 'step' => 1, 'label' => false,
          ]
        ]
      ]
    ]]);
    array_push($result, [
    'id' => 'multimedia',
    'types' => [
      [
        'id' => 'category',
        'name' => 'Tipo',
        'format' => 'radio',
        'filters' => [
          ['id' => 'multimedia-category-Youtube', 'name' => 'multimedia-category', 'value' => 'Youtube', 'label' => 'Videos', 'active' => false],
          ['id' => 'multimedia-category-HTML', 'name' => 'multimedia-category', 'value' => 'Html', 'label' => 'Interactivo', 'active' => false]
        ]
      ]
    ]]);
    array_push($result, [
    'id' => 'others',
    'types' => [
      [
        'id' => 'type',
        'name' => 'Tipo',
        'format' => 'radio',
        'filters' => [
          ['id' => 'others-type-city', 'name' => 'others-type', 'value' => 'city', 'label' => 'Centro cultural, museo, biblioteca', 'active' => false],
          ['id' => 'others-category-publicacion', 'name' => 'others-type', 'value' => 'publicacion', 'label' => 'Publicaciones', 'active' => false],
          ['id' => 'others-category-service', 'name' => 'others-type', 'value' => 'service', 'label' => 'Servicios', 'active' => false],
          ['id' => 'others-category-page', 'name' => 'others-type', 'value' => 'page', 'label' => 'Páginas', 'active' => false]
        ]
      ]
    ]]);

    return new JsonResponse($result);
  }
}