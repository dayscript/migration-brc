<?php

namespace Drupal\brc_elasticsearch_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brc_elasticsearch_custom\ElasticsearchManagerInterface;
/**
 * Declaration of class ElasticsearchIndexingForm.
 */
class ElasticsearchIndexingForm extends ConfigFormBase {

  public $sets;
  public $tokens;
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
    return 'brc_elasticsearch_custom_indexing_form';
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

    $form['drupal'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal'),
      '#group' => 'settings',
    ];
    $form['babelojs'] = [
      '#type' => 'details',
      '#title' => $this->t('OAI-PMH'),
      '#group' => 'settings',
    ];
    $form['makemake'] = [
      '#type' => 'details',
      '#title' => $this->t('Make Make'),
      '#group' => 'settings',
    ];

    $form['drupal']['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'drupal-container'],
    ];

    $form['drupal']['container']['node'] = [
      '#type' => 'fieldset',
      '#title' => 'Nodos a indexar',
    ];
    foreach ($this->commonFunction->checkedType($config->get('categories')['node']['mapping']) as $mk => $m) {
      $form['drupal']['container']['node'][$mk] = [
        '#type' => 'checkbox',
        '#title' => $m,
        '#default_value' => '',
      ];
    }

    $form['drupal']['container']['taxonomy'] = [
      '#type' => 'fieldset',
      '#title' => 'Taxonomía a indexar',
    ];
    foreach ($this->commonFunction->checkedType($config->get('categories')['taxonomy']['mapping']) as $mk => $m) {
      $form['drupal']['container']['taxonomy'][$mk] = [
        '#type' => 'checkbox',
        '#title' => $m,
        '#default_value' => '',
      ];
    }
    $form['drupal']['container']['actions'] = [
      '#type' => 'actions',
    ];
    $form['drupal']['container']['actions']['add_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Indexar'),
      '#submit' => ['::indicesDrupalCreate'],
      '#ajax'   => [
        'callback' => [ $this, 'indicesDrupalCallback'],
        'wrapper'  => 'drupal-container',
      ],
    ];
    $form['drupal']['container']['actions']['remove_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Eliminar'),
      '#submit' => ['::indicesDrupalDelete'],
      '#limit_validation_errors' => [],
      '#ajax'                    => [
        'callback' => [ $this, 'indicesDrupalCallback'],
        'wrapper'  => 'drupal-container',
      ],
    ];

    $form['babelojs']['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'babelojs-container'],
    ];
    $form['babelojs']['container']['babelojs'] = [
      '#type' => 'fieldset',
      '#title' => 'Babel - OJS',
    ];
    $e = [];
    foreach ($config->get('categories')['babel']['endpoint'] as $key => $index) {
      $e[$index['endpoint']] = $index['name'];
    }
    foreach ($config->get('categories')['ojs']['endpoint'] as $key => $index) {
      $e[$index['endpoint']] = $index['name'];
    }
    $form['babelojs']['container']['babelojs']['endpoint'] = [
      '#type' => 'select',
      '#title' => 'Índice',
      '#options' => $e,
    ];
    if(!empty($this->sets)){
      $form['babelojs']['container']['babelojs']['sets'] = [
        '#type' => 'select',
        '#title' => 'Sets',
        '#options' => $this->sets,
      ];
    }
    if(!empty($this->tokens)){
      $form['babelojs']['container']['babelojs']['tokens'] = [
        '#type' => 'select',
        '#title' => 'Token',
        '#options' => $this->tokens,
      ];
    }
    $form['babelojs']['container']['actions'] = [
      '#type' => 'actions',
    ];
    $form['babelojs']['container']['actions']['load_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Cargar'),
      '#submit' => ['::indicesBjLoad'],
      '#ajax'   => [
        'callback' => [ $this, 'indicesBjCallback'],
        'wrapper'  => 'babelojs-container',
      ],
    ];
    $form['babelojs']['container']['actions']['add_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Indexar'),
      '#submit' => ['::indicesBjCreate'],
      '#ajax'   => [
        'callback' => [ $this, 'indicesBjCallback'],
        'wrapper'  => 'babelojs-container',
      ],
    ];
    $form['babelojs']['container']['actions']['remove_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Eliminar'),
      '#submit' => ['::indicesBjDelete'],
      '#limit_validation_errors' => [],
      '#ajax'                    => [
        'callback' => [ $this, 'indicesBjCallback'],
        'wrapper'  => 'babelojs-container',
      ],
    ];

    $form['makemake']['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'makemake-container'],
    ];
    $form['makemake']['container']['makemake'] = [
      '#type' => 'fieldset',
      '#title' => 'Importar Make make',
    ];

    $form['makemake']['container']['makemake']['file'] = [ 
      '#title' => 'Archivo en formato CSV',
      '#type' => 'managed_file',
      '#title' => $this->t('Archivo en formato CSV'),
      '#upload_location' => 'public://import',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];
    $form['makemake']['container']['actions'] = [
      '#type' => 'actions',
    ];
    $form['makemake']['container']['actions']['add_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Indexar'),
      '#submit' => ['::indicesMakeCreate'],
      '#limit_validation_errors' => [],
      '#ajax'                    => [
        'callback' => [ $this, 'indicesMakeCallback'],
        'wrapper'  => 'makemake-container',
      ],
    ];
    $form['makemake']['container']['actions']['remove_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Eliminar'),
      '#submit' => ['::indicesMakeDelete'],
      '#limit_validation_errors' => [],
      '#ajax'                    => [
        'callback' => [ $this, 'indicesMakeCallback'],
        'wrapper'  => 'makemake-container',
      ],
    ];

    $form_state->setCached(FALSE);

    /*$form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];*/

    return $form;//parent::buildForm($form, $form_state);
  }

  public function indicesDrupalCreate(array $form, FormStateInterface $form_state) {
    $i = $form_state->getValue('drupal');
    foreach ($i['container']['node'] as $k => $v) {
      if($v == 1) {
        $this->commonFunction->indexingApi('node', $k, 'create');
      }
    }
    foreach ($i['container']['taxonomy'] as $k => $v) { 
      if($v == 1) {
        $this->commonFunction->indexingApi('taxonomy', $k, 'create');
      }
    }
    $form_state->setRebuild();
  }

  public function indicesDrupalDelete(array $form, FormStateInterface $form_state) {
    $i = $form_state->getValue('indices');
    
    foreach ($i['container']['node'] as $k => $v) {
      if($v == 1) {
        $this->commonFunction->indexingApi('node', $k, 'delete');
      }
    }
    foreach ($i['container']['taxonomy'] as $k => $v) { 
      if($v == 1) {
        $this->commonFunction->indexingApi('taxonomy', $k, 'delete');
      }
    }
    $form_state->setRebuild();
  }

  public function indicesDrupalCallback(array $form, FormStateInterface $form_state) {
    return $form['drupal']['container'];
  }

  public function indicesBjLoad(array $form, FormStateInterface $form_state) {
    $i = $form_state->getValue('babelojs');
    $endpoint = $i['container']['babelojs']['endpoint'];
    $sets = [];
    foreach ($this->commonFunction->clientOaipmh('sets', $endpoint) as $key => $set) {
      $sets[$this->commonFunction->clearType($set->setSpec)] = $set->setName;
    }
    $this->sets = $sets;
    $form_state->setRebuild();
  }

  public function indicesBjCreate(array $form, FormStateInterface $form_state) {
    $log = ['total' => 0, 'successful' => 0, 'failed' => 0, 'error' => []];
    $i = $form_state->getValue('babelojs');
    $endpoint = $i['container']['babelojs']['endpoint'];
    $set = $i['container']['babelojs']['sets'];
    $token = (!empty($i['container']['babelojs']['tokens'])) ? $i['container']['babelojs']['tokens'] : '';

    if(!empty($token)){
      $response = $this->commonFunction->clientOaipmh('next', $endpoint, $set, $token);

    }else {
      $response = $this->commonFunction->clientOaipmh('recordsperset', $endpoint, $set);
      if(isset($response->resumptionToken)){
        $this->tokens[$response->resumptionToken] = $response->resumptionToken;
      }
      foreach ($response->record as $key => $record) {
        $response = $this->commonFunction->indexingProcessDoc('babelojs', 'create', $set, $record);
        $log = $this->commonFunction->translateLog($response, $log);
      }

    }
    drupal_set_message('Documentos procesados: ' . $log['total'] . ', exitosos: ' . $log['successful'] . ', han fallado: ' .  $log['failed']);
    $form_state->setRebuild();
  }

  public function indicesBjDelete(array $form, FormStateInterface $form_state) {
    $log = ['total' => 0, 'successful' => 0, 'failed' => 0, 'error' => []];
    $i = $form_state->getValue('babelojs');
    $endpoint = $i['container']['babelojs']['endpoint'];
    $set = $i['container']['babelojs']['sets'];
    $token = (!empty($i['container']['babelojs']['tokens'])) ? $i['container']['babelojs']['tokens'] : '';

    if(!empty($token)){
      $response = $this->commonFunction->clientOaipmh('next', $endpoint, $set, $token);

    }else {
      $response = $this->commonFunction->clientOaipmh('recordsperset', $endpoint, $set);
      if(isset($response->resumptionToken)){
        $this->tokens[$response->resumptionToken] = $response->resumptionToken;
      }
      foreach ($response->record as $key => $record) {
        $response = $this->commonFunction->indexingProcessDoc('babelojs', 'delete', $set, $record);
        $log = $this->commonFunction->translateLog($response, $log);
      }

    }
    drupal_set_message('Documentos procesados: ' . $log['total'] . ', exitosos: ' . $log['successful'] . ', han fallado: ' .  $log['failed']);
    $form_state->setRebuild();
  }

  public function indicesBjCallback(array $form, FormStateInterface $form_state) {
    return $form['babelojs']['container'];
  }

  public function indicesMakeCreate(array $form, FormStateInterface $form_state) {
    $this->commonFunction->indexingApi('makemake', '', 'create');
  }

  public function indicesMakeDelete(array $form, FormStateInterface $form_state) {
    $this->commonFunction->indexingApi('makemake', '', 'delete');
  }

  public function indicesMakeCallback(array $form, FormStateInterface $form_state) {
    return $form['makemake']['container'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    return parent::submitForm($form, $form_state);
  }
}
