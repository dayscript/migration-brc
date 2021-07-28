<?php

namespace Drupal\brc_elasticsearch_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brc_elasticsearch_custom\ElasticsearchManagerInterface;
/**
 * Declaration of class ElasticsearchIndexingMakemakeForm.
 */
class ElasticsearchIndexingMakemakeForm extends ConfigFormBase {

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
    $form['makemake'] = [
      '#type' => 'details',
      '#title' => $this->t('Make Make'),
      '#group' => 'settings',
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
    return $form;
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
