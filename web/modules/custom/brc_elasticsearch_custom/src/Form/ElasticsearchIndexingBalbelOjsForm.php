<?php

namespace Drupal\brc_elasticsearch_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brc_elasticsearch_custom\ElasticsearchManagerInterface;
/**
 * Declaration of class ElasticsearchIndexingBalbelOjsForm.
 */
class ElasticsearchIndexingBalbelOjsForm extends ConfigFormBase {

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
    $form['babelojs'] = [
      '#type' => 'details',
      '#title' => $this->t('OAI-PMH'),
      '#group' => 'settings',
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
      '#submit' => ['::indicesBabelOjsLoad'],
      '#ajax'   => [
        'callback' => [ $this, 'indicesBabelOjsCallback'],
        'wrapper'  => 'babelojs-container',
      ],
    ];
    $form['babelojs']['container']['actions']['add_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Indexar'),
      '#submit' => ['::indicesBabelOjsCreate'],
      '#ajax'   => [
        'callback' => [ $this, 'indicesBabelOjsCallback'],
        'wrapper'  => 'babelojs-container',
      ],
    ];
    $form['babelojs']['container']['actions']['remove_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Eliminar'),
      '#submit' => ['::indicesBabelOjsDelete'],
      '#limit_validation_errors' => [],
      '#ajax'                    => [
        'callback' => [ $this, 'indicesBabelOjsCallback'],
        'wrapper'  => 'babelojs-container',
      ],
    ];
    $form_state->setCached(FALSE);
    return $form;
  }

  public function indicesBabelOjsLoad(array $form, FormStateInterface $form_state) {
    $i = $form_state->getValue('babelojs');
    $endpoint = $i['container']['babelojs']['endpoint'];
    $sets = [];
    foreach ($this->commonFunction->clientOaipmh('sets', $endpoint) as $key => $set) {
      $sets[$this->commonFunction->clearType($set->setSpec)] = $set->setName;
    }
    $this->sets = $sets;
    $form_state->setRebuild();
  }

  public function indicesBabelOjsCreate(array $form, FormStateInterface $form_state) {
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

  public function indicesBabelOjsDelete(array $form, FormStateInterface $form_state) {
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

  public function indicesBabelOjsCallback(array $form, FormStateInterface $form_state) {
    return $form['babelojs']['container'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    return parent::submitForm($form, $form_state);
  }
}
