<?php

namespace Drupal\commerce_paymill\Plugin\Commerce\PaymentGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;


trait PaymillConfigurationTrait {

  /**
   * The Paymill gateway used for making API calls.
   *
   * @var \Paymill\Request
   */
  protected $api;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time) {
      parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

      $this->api = new \Paymill\Request($this->configuration['private_key']);
      $this->public_key = $this->getPaymillPublicKey();
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymillPublicKey() {
    return $this->configuration['public_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'private_key' => '',
      'public_key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private key'),
      '#default_value' => $this->configuration['private_key'],
      '#required' => TRUE,
    ];
    $form['public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public key'),
      '#default_value' => $this->configuration['public_key'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['private_key'] = $values['private_key'];
      $this->configuration['public_key'] = $values['public_key'];
    }
  }

}
