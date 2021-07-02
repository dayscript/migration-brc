<?php

namespace Drupal\brc_elasticsearch_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brc_elasticsearch_custom\ElasticsearchManagerInterface;
/**
 * Declaration of class ElasticsearchSettingsForm.
 */
class ElasticsearchSettingsForm extends ConfigFormBase {

  protected $indices = 1;
  protected $commonFunction;

  public function __construct(ElasticsearchManagerInterface $interface) {
    $this->commonFunction = $interface;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('brc_elasticsearch_custom.manager')
    );
  }

  public function getFormId() {
    return 'brc_elasticsearch_custom_settings_form';
  }

  protected function getEditableConfigNames() {
    return [
      'brc_elasticsearch_custom.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('brc_elasticsearch_custom.settings');
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#tree'] = TRUE;
     
    $form['settings'] = ['#type' => 'vertical_tabs'];

    $form['indices'] = [
      '#type' => 'details',
      '#title' => $this->t('Índices de Elasticsearch'),
      '#group' => 'settings',
    ];
    $form['categories'] = [
      '#type' => 'details',
      '#title' => $this->t('Categorías de búsqueda'),
      '#group' => 'settings',
    ];
    $form['seekers'] = [
      '#type' => 'details',
      '#title' => $this->t('Configurar buscador'),
      '#group' => 'settings',
    ];

    $form['indices']['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'indices-container'],
    ];
    $this->indices = !empty($config->get('indices')) ? count($config->get('indices')) : 1;
    for ($i = 1; $i <= $this->indices; $i++) {
      $form['indices']['container']['index'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Índice'),
        '#group' => 'index '.$i,
        '#tree' => TRUE,
      ];
      $form['indices']['container']['index'][$i]['ip'] = [
        '#type' => 'textfield',
        '#title' => $this->t('IP'),
        '#default_value' => isset($config->get('indices')[$i]) ? $config->get('indices')[$i]['ip'] : ''
      ];
      $form['indices']['container']['index'][$i]['port'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Puerto'),
        '#default_value' => isset($config->get('indices')[$i]) ? $config->get('indices')[$i]['port'] : ''
      ];
      $form['indices']['container']['index'][$i]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Nombre del índice'),
        '#default_value' => isset($config->get('indices')[$i]) ? $config->get('indices')[$i]['name'] : ''
      ];
      $form['indices']['container']['index'][$i]['index'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Índice'),
        '#default_value' => isset($config->get('indices')[$i]) ? $config->get('indices')[$i]['index'] : ''
      ];
      $form['indices']['container']['index'][$i]['doc'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Documento'),
        '#default_value' => isset($config->get('indices')[$i]) ? $config->get('indices')[$i]['doc'] : ''
      ];
      $form['indices']['container']['index'][$i]['oaipmh'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('OAI-PMH'),
        '#default_value' => isset($config->get('indices')[$i]) ? $config->get('indices')[$i]['oaipmh'] : ''
      ];
    }
    $form['indices']['container']['actions'] = [
      '#type' => 'actions',
    ];
    $form['indices']['container']['actions']['add_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Agregar más'),
      '#submit' => ['::indicesAddMore'],
      '#ajax'   => [
        'callback' => [ $this, 'indicesAddMoreCallback'],
        'wrapper'  => 'indices-container',
      ],
    ];
    if ($this->indices > 1) {
      $form['indices']['container']['actions']['remove_item'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Eliminar el último'),
        '#submit' => ['::indicesRemoveMore'],
        '#limit_validation_errors' => [],
        '#ajax'                    => [
          'callback' => [ $this, 'indicesAddMoreCallback'],
          'wrapper'  => 'indices-container',
        ],
      ];
    }

    $form['categories']['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'categories-container'],
    ];

    $categorys = ['node','taxonomy','mediawiki','babel','ojs','makemake'];
    $endpoints = [];
    if(!is_null($config->get('indices'))){
      foreach ($config->get('indices') as $ek => $e) {
        $endpoints[$e['index']] = $e['name'];
      }
    }

    foreach ($categorys as $ck => $c) {
      $form['categories']['container']['index'][$c] = [
        '#type' => 'details',
        '#title' => $c,
        '#group' => 'index '.$c,
        '#tree' => TRUE,
      ];
      $form['categories']['container']['index'][$c]['container']['mapping'] = [
        '#type' => 'details',//'fieldset',
        '#title' => 'Tipos de contenido',
      ];
      foreach ($this->commonFunction->mappingCategory($c) as $mk => $m) {
        $form['categories']['container']['index'][$c]['container']['mapping']['container'][$mk] = [
          '#type' => 'fieldset',
        ];
        $form['categories']['container']['index'][$c]['container']['mapping']['container'][$mk]['type'] = [
          '#type' => 'checkbox',
          '#title' => $m,
          '#default_value' => isset($config->get('categories')[$c],  $config->get('categories')[$c]['mapping'][$mk]['type']) ? $config->get('categories')[$c]['mapping'][$mk]['type'] : '',
        ];
        $form['categories']['container']['index'][$c]['container']['mapping']['container'][$mk]['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Name'),
          '#default_value' => isset($config->get('categories')[$c], $config->get('categories')[$c]['mapping'][$mk]['name']) ? $config->get('categories')[$c]['mapping'][$mk]['name'] : $m,
        ];
        $form['categories']['container']['index'][$c]['container']['mapping']['container'][$mk]['score'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Score'),
          '#default_value' => isset($config->get('categories')[$c], $config->get('categories')[$c]['mapping'][$mk]['score']) ? $config->get('categories')[$c]['mapping'][$mk]['score'] : '',
        ];
      }
      $form['categories']['container']['index'][$c]['container']['endpoint'] = [
        '#type' => 'details',//'fieldset',
        '#title' => 'Servicio',
      ];
      $count_endpoints = $c == 'ojs' ? 6 : 1;
      for ($i=1; $i <= $count_endpoints ; $i++) { 
        $form['categories']['container']['index'][$c]['container']['endpoint']['container'][$i] = [
          '#type' => 'fieldset',
        ];
        $form['categories']['container']['index'][$c]['container']['endpoint']['container'][$i]['indice'] = array(
          '#type' => 'select',
          '#title' => 'Índice',
          '#options' => $endpoints,
          '#default_value' => isset($config->get('categories')[$c]) ? $config->get('categories')[$c]['endpoint'][$i]['indice'] : ''
        );
        $form['categories']['container']['index'][$c]['container']['endpoint']['container'][$i]['name'] = array(
          '#type' => 'textfield',
          '#title' => 'Nombre',
          '#default_value' => isset($config->get('categories')[$c]) ? $config->get('categories')[$c]['endpoint'][$i]['name'] : ''
        );
        $form['categories']['container']['index'][$c]['container']['endpoint']['container'][$i]['endpoint'] = array(
          '#type' => 'textfield',
          '#title' => 'Enlace',
          '#default_value' => isset($config->get('categories')[$c]) ? $config->get('categories')[$c]['endpoint'][$i]['endpoint'] : ''
        );
      }
    }

    $form['seekers']['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'seekers-container'],
    ];

    foreach ($config->get('seekers') as $sk => $s) {
      $form['seekers']['container'][$sk] = [
        '#type' => 'details',
        '#title' => $s,
        '#group' => 'index '.$sk,
        '#tree' => TRUE,
      ];

      $category = explode('_', $sk);
      $category = $category[0];
      $categorys = ($category == 'general') ? $categorys : [$category];

      foreach ($categorys as $ck => $c) {
        $form['seekers']['container'][$sk]['container'][$c] = [
          '#type' => 'details',//'fieldset',
          '#title' => $c,
        ];
        $form['seekers']['container'][$sk]['container'][$c]['type'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Contenido'),
          '#options' => $this->commonFunction->checkedType($config->get('categories')[$c]['mapping']),
          '#default_value' => isset($config->get('sconfig')[$sk], $config->get('sconfig')[$sk][$c]['type']) ? $config->get('sconfig')[$sk][$c]['type'] : '',
        ];
        $fields = $this->commonFunction->mappingCategory('fields', $c);
        foreach ($fields as $fk => $f) {
          $form['seekers']['container'][$sk]['container'][$c]['container'][$fk] = [
            '#type' => 'fieldset',
          ];
          $form['seekers']['container'][$sk]['container'][$c]['container'][$fk]['field'] = [
            '#type' => 'checkbox',
            '#title' => $f,
            '#default_value' => isset($config->get('sconfig')[$sk], $config->get('sconfig')[$sk][$c]['container'][$fk]['field']) ? $config->get('sconfig')[$sk][$c]['container'][$fk]['field'] : '',
          ];
          /*$form['seekers']['container'][$sk]['container'][$c]['container'][$fk]['boot'] = [
            '#type' => 'textfield',
            '#title' => 'Boot',
            //'#default_value' => '',
          ];*/
        }
      }
      $form['seekers']['container'][$sk]['from'] = [
        '#type' => 'textfield',
        '#title' => 'from',
        '#description' => $this->t('Define el desplazamiento desde el primer resultado que desea obtener. (predeterminado 0)'), 
        '#default_value' => isset($config->get('sconfig')[$sk], $config->get('sconfig')[$sk]['config']['from']) ? $config->get('sconfig')[$sk]['config']['from'] : '',
      ];
      $form['seekers']['container'][$sk]['size'] = [
        '#type' => 'textfield',
        '#title' => 'size',
        '#description' => $this->t('Permite configurar la cantidad máxima de visitas a devolver. (predeterminado 10).'), 
        '#default_value' => isset($config->get('sconfig')[$sk], $config->get('sconfig')[$sk]['config']['size']) ? $config->get('sconfig')[$sk]['config']['size'] : '',
      ];
      $form['seekers']['container'][$sk]['image_style'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Estilo de Imagen'),
        '#description' => $this->t('Estilos definido para redimensionar o ajustar la presentación de imagen.'), 
        '#default_value' => isset($config->get('sconfig')[$sk], $config->get('sconfig')[$sk]['config']['image_style']) ? $config->get('sconfig')[$sk]['config']['image_style'] : '',
      ];
    }

    $form_state->setCached(FALSE);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;//parent::buildForm($form, $form_state);
  }

  public function indicesAddMore(array $form, FormStateInterface $form_state) {
    $this->indices++;
    $form_state->setRebuild();
  }

  public function indicesRemoveMore(array $form, FormStateInterface $form_state) {
    if ($this->indices > 1) {
      $this->indices--;
    }
    $form_state->setRebuild();
  }

  public function indicesAddMoreCallback(array $form, FormStateInterface $form_state) {
    return $form['indices']['container'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('brc_elasticsearch_custom.settings');
    
    $fs['indices'] = $form_state->getValue('indices');
    $config->set('indices', $fs['indices']['container']['index']);

    $fs['categories'] = $form_state->getValue('categories');
    $categories = [];
    foreach ($fs['categories']['container']['index'] as $fsk => $fs) {
      $categories[$fsk] = ['mapping' => [],'endpoint' => []];
      foreach ($fs['container'] as $fsck => $fsc) {
        if($fsck == 'mapping') {
          $categories[$fsk]['mapping'] = $fsc['container'];
        }
        if($fsck == 'endpoint') {
          $categories[$fsk]['endpoint'] = $fsc['container'];
        }
      }
    }
    $config->set('categories', $categories);

    $seekers = [
      'general' => 'Buscador general',
      'general_pages' => 'Exposiciones y proyectos',
      'general_collections' => 'Colecciones digitales',
      'general_activity' => 'Actividades',
      'general_article' => 'Noticias',
      'general_multimedia' => 'Videos',
      'general_others' => 'Otros contenidos',
      'general_biblioteca_virtual' => 'Biblioteca virtual',
      'general_mediawiki' => 'Enciclopedia',
      'general_makemake' => 'Make make'
    ];
    foreach ($categories as $ck => $c) {
      switch ($ck) {
        case 'node':
        case 'taxonomy':
          if(isset($c['mapping'])){
            foreach ($c['mapping'] as $mk => $m) {
              if($m['type'] == 1){
                $seekers[$ck.'_category_'.$mk] = $m['name'];
              }
            }
          }
          break;
        case 'mediawiki':
        case 'babel':
        case 'ojs':
        case 'makemake':
          $seekers[$ck.'_category_'.$ck] = $c['endpoint'][1]['name'];
          break;
      }
    }
    $config->set('seekers', $seekers);

    $fs['sconfig'] = $form_state->getValue('seekers');
    foreach ($fs['sconfig']['container'] as $fsk => $fs) {
      $sconfig[$fsk] = ['mapping' => [],'endpoint' => []];
      $sconfig[$fsk] = $fs['container'];
      $sconfig[$fsk] += ['config' => ['from' => $fs['from'], 'size' => $fs['size'], 'image_style' => $fs['image_style']]];
    }
    $config->set('sconfig', $sconfig);

    $config->save();
    return parent::submitForm($form, $form_state);
  }
}
