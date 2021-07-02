<?php

namespace Drupal\brc_social_media_feeds_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Declaration of class SocialFeedsSettingsForm.
 */
class SocialFeedsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'brc_social_media_feeds_custom_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'brc_social_media_feeds_custom.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Default settings.
    $config = $this->config('brc_social_media_feeds_custom.settings');
    $form['settings'] = ['#type' => 'vertical_tabs'];

    $form['account_fb'] = [
      '#type' => 'details',
      '#title' => $this->t('Account Facebook Information'),
      '#group' => 'settings',
      '#description' => $this->t('This will be used for authenticating with API Graph service in order to embed <a href="@link">Facebook</a> data.', ['@link' => 'https://developers.facebook.com/tools/explorer/']),
    ];
    $form['account_tw'] = [
      '#type' => 'details',
      '#title' => $this->t('Account Twitter Information'),
      '#group' => 'settings',
      '#description' => $this->t('This will be used for authenticating with API service in order to embed <a href="@link">Twitter</a> data.', ['@link' => 'https://developer.twitter.com/en/docs/twitter-api/v1/tweets/timelines/api-reference/get-statuses-user_timeline']),
    ];
    $form['account_ig'] = [
      '#type' => 'details',
      '#title' => $this->t('Account Instagram Information'),
      '#group' => 'settings',
      '#description' => $this->t('This will be used for authenticating with API Graph service in order to embed <a href="@link">Instagram</a> data.', ['@link' => 'https://developers.facebook.com/tools/explorer/']),
    ];
    $form['account_yt'] = [
      '#type' => 'details',
      '#title' => $this->t('Account YouTube Information'),
      '#group' => 'settings',
      '#description' => $this->t('This will be used for authenticating with Google Client service in order to embed <a href="@link">YouTube</a> data.', ['@link' => 'https://github.com/googleapis/google-api-php-client/']),
    ];

    $form['account_fb']['accounts_fb'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accounts'),
      '#description' => $this->t('If you want to enter more accounts separated with a comma ","'),
      '#default_value' => $config->get('accounts_fb'),
      '#maxlength' => 220
    ];
    $form['account_fb']['access_token_fb'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('Access token generated from API Graph'),
      '#default_value' => $config->get('access_token_fb'),
      '#maxlength' => 220
    ];


    $form['account_tw']['accounts_tw'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accounts'),
      '#description' => $this->t('@ in Twitter'),
      '#default_value' => $config->get('accounts_tw'),
      '#maxlength' => 220
    ];
    $form['account_tw']['oauth_access_token_tw'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Oauth Access Token'),
      '#description' => $this->t('Get the key in the App'),
      '#default_value' => $config->get('oauth_access_token_tw'),
      '#maxlength' => 220
    ];
    $form['account_tw']['oauth_access_token_secret_tw'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Oauth Access Token Secret'),
      '#description' => $this->t('Get the key in the App'),
      '#default_value' => $config->get('oauth_access_token_secret_tw'),
      '#maxlength' => 220
    ];
    $form['account_tw']['consumer_key_tw'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer key'),
      '#description' => $this->t('Get the key in the App '),
      '#default_value' => $config->get('consumer_key_tw'),
      '#maxlength' => 220
    ];
    $form['account_tw']['consumer_secret_tw'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Secret'),
      '#description' => $this->t('Get the key in the App'),
      '#default_value' => $config->get('consumer_secret_tw'),
      '#maxlength' => 220
    ];
    
    $form['account_ig']['accounts_ig'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDs Instagram Business Account'),
      '#description' => $this->t('If you want to enter more IDs accounts separated with a comma ","'),
      '#default_value' => $config->get('accounts_ig'),
      '#maxlength' => 220
    ];
    $form['account_ig']['access_token_ig'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('Access token generated from API Graph '),
      '#default_value' => $config->get('access_token_ig'),
      '#maxlength' => 220
    ];

    $form['account_yt']['channel_id_yt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Channel Id'),
      '#description' => $this->t('Get account config'),
      '#default_value' => $config->get('channel_id_yt'),
      '#maxlength' => 220
    ];
    $form['account_yt']['developer_key_yt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Developer Key'),
      '#description' => $this->t('Get account config'),
      '#default_value' => $config->get('developer_key_yt'),
      '#maxlength' => 220
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Retrieve the configuration.
    $config = $this->configFactory->getEditable('brc_social_media_feeds_custom.settings');
    

    $config->set('accounts_fb', $form_state->getValue('accounts_fb'));
    $config->set('access_token_fb', $form_state->getValue('access_token_fb'));
    $config->set('accounts_tw', $form_state->getValue('accounts_tw'));
    $config->set('oauth_access_token_tw', $form_state->getValue('oauth_access_token_tw'));
    $config->set('oauth_access_token_secret_tw', $form_state->getValue('oauth_access_token_secret_tw'));
    $config->set('consumer_key_tw', $form_state->getValue('consumer_key_tw'));
    $config->set('consumer_secret_tw', $form_state->getValue('consumer_secret_tw'));
    $config->set('accounts_ig', $form_state->getValue('accounts_ig'));
    $config->set('access_token_ig', $form_state->getValue('access_token_ig'));
    $config->set('channel_id_yt', $form_state->getValue('channel_id_yt'));
    $config->set('developer_key_yt', $form_state->getValue('developer_key_yt'));
    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
