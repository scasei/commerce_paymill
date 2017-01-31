<?php

namespace Drupal\commerce_paymill\PluginForm\Paymill;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_paymill\Plugin\Commerce\PaymentGateway\PaymillInterface $plugin */
    $plugin = $this->plugin;

    $element = parent::buildCreditCardForm($element, $form_state);

    // Set our key to settings array.
    $element['#attached']['library'][] = 'commerce_paymill/form';
    $element['#attached']['drupalSettings']['commercePaymill'] = [
      'publicKey' => $plugin->getPaymillPublicKey(),
    ];

    $element['#attributes']['class'][] = 'paymill-form';

    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<span class="payment-errors"></span>',
    ];

    // Add class identifiers for card data.
    $element['number']['#attributes']['class'][] = 'card-number';
    $element['expiration']['month']['#attributes']['class'][] = 'card-expiry-month';
    $element['expiration']['year']['#attributes']['class'][] = 'card-expiry-year';
    $element['security_code']['#attributes']['class'][] = 'card-cvc';

    // Populated by the JS library.
    $element['paymill_token'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'paymill_token'
      ]
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    // The JS library performs its own validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    // The payment gateway plugin will process the submitted payment details.
  }

}
