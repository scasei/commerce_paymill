<?php

namespace Drupal\commerce_paymill\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Paymill payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paymill",
 *   label = "Paymill",
 *   display_label = "Paymill",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_paymill\PluginForm\Paymill\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class Paymill extends OnsitePaymentGatewayBase implements PaymillInterface {

  /**
   * The Paymill gateway used for making API calls.
   *
   * @var \Paymill\Request
   */
  protected $paymill_request;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);
    $private_key = ($this->getMode() == 'test') ? $this->configuration['test_private_key'] : $this->configuration['live_private_key'];
    $this->paymill_request = new \Paymill\Request($private_key);
    $this->public_key = $this->getPaymillPublicKey();

  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $operations = [];

    $access = $payment->getState()->value == 'authorization';
    $operations['capture'] = [
      'title' => $this->t('Capture'),
      'page_title' => $this->t('Capture payment'),
      'plugin_form' => 'capture-payment',
      'access' => $access,
    ];

    $access = in_array($payment->getState()->value, [
      'capture_completed',
      'capture_partially_refunded'
    ]);

    $operations['refund'] = [
      'title' => $this->t('Refund'),
      'page_title' => $this->t('Refund payment'),
      'plugin_form' => 'refund-payment',
      'access' => $access,
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymillPublicKey() {
    return $key = ($this->getMode() == 'test') ? $this->configuration['test_public_key'] : $this->configuration['live_public_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'test_private_key' => '',
      'test_public_key' => '',
      'live_private_key' => '',
      'live_public_key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['test_private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private key (test)'),
      '#default_value' => $this->configuration['test_private_key'],
      '#required' => TRUE,
    ];

    $form['test_public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public key (test)'),
      '#default_value' => $this->configuration['test_public_key'],
      '#required' => TRUE,
    ];

    $form['live_private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private key (live)'),
      '#default_value' => $this->configuration['live_private_key'],
      '#required' => TRUE,
    ];

    $form['live_public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public key (live)'),
      '#default_value' => $this->configuration['live_public_key'],
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
      $this->configuration['test_private_key'] = $values['test_private_key'];
      $this->configuration['test_public_key'] = $values['test_public_key'];
      $this->configuration['live_private_key'] = $values['live_private_key'];
      $this->configuration['live_public_key'] = $values['live_public_key'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    if ($payment->getState()->value != 'new') {
      throw new \InvalidArgumentException('The provided payment is in an invalid state.');
    }
    $payment_method = $payment->getPaymentMethod();

    if (empty($payment_method)) {
      throw new \InvalidArgumentException('The provided payment has no payment method referenced.');
    }
    if (REQUEST_TIME >= $payment_method->getExpiresTime()) {
      throw new HardDeclineException('The provided payment method has expired');
    }
    $amount = $payment->getAmount();
    $currency_code = $payment->getAmount()->getCurrencyCode();
    $owner = $payment_method->getOwner();
    $customer_id = $owner->commerce_remote_id->getByProvider('commerce_paymill');

    $amount_integer = $this->formatNumber($amount->getNumber());

    // Create Paymill payment or preauthorization.
    if ($capture) {
      // Create Paymill transaction.
      $transaction = new \Paymill\Models\Request\Transaction();
      $transaction->setAmount($amount_integer)
        ->setCurrency($currency_code)
        ->setPayment($payment_method->getRemoteId())
        ->setDescription('Test Transaction');

      $remote_transaction = $this->paymill_request->create($transaction);
      $payment->setRemoteId($remote_transaction->getId());
      $payment->setCapturedTime(REQUEST_TIME);
    }
    else {
      // Create Paymill preauthorization.
      $preauthorization = new \Paymill\Models\Request\Preauthorization();
      $preauthorization->setPayment($payment_method->getRemoteId())
        ->setAmount($amount_integer)
        ->setCurrency($currency_code)
        ->setDescription('Test Preauthorization');
      $remote_preauthorization = $this->paymill_request->create($preauthorization);
      $payment->setRemoteId($remote_preauthorization->getId());
    }

    $payment->state = $capture ? 'capture_completed' : 'authorization';

    $payment->setAuthorizedTime(REQUEST_TIME);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {

  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {

  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {

  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'paymill_token'
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    /** @var \Paymill\Models\Response\Payment $remote_payment_method */
    $remote_payment_method = $this->doCreatePaymentMethod($payment_method, $payment_details);
    $payment_method->card_type = $this->mapCreditCardType($remote_payment_method->getCardType());
    $payment_method->card_number = $remote_payment_method->getLastFour();
    $payment_method->card_exp_month = $remote_payment_method->getExpireMonth();
    $payment_method->card_exp_year = $remote_payment_method->getExpireYear();
    $remote_id = $remote_payment_method->getId();
    $expires = CreditCard::calculateExpirationTimestamp($remote_payment_method->getExpireMonth(), $remote_payment_method->getExpireYear());
    $payment_method->setRemoteId($remote_id);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
  }

  /**
   * Creates the payment method on the gateway.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return \Paymill\Models\Response\Payment $remote_payment_method
   *   The Paymill API payment object.
   */
  protected function doCreatePaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $owner = $payment_method->getOwner();
    $customer_id = NULL;
    if ($owner) {
      $customer_id = $owner->commerce_remote_id->getByProvider('commerce_paymill');
      $customer_email = $owner->getEmail();
    }

    $client = new \Paymill\Models\Request\Client();
    $create_client = TRUE;

    // Check if the customer exists as Paymill client, if not create a new one.
    if ($customer_id) {
      $client->setId($customer_id);
      /** @var \Paymill\Models\Response\Client $remote_client */
      $remote_client = $this->paymill_request->getOne($client);

      if (!empty($remote_client->getId())) {
        $create_client = FALSE;
      }
    }

    // Create Paymill client if there is no client found or already set.
    if ($create_client) {
      $client->setEmail($owner->getEmail())
        ->setDescription(t('Customer for :mail', array(':mail' => $customer_email)));
      $remote_client = $this->paymill_request->create($client);
      if (!empty($remote_client->getId())) {
        $customer_id = $remote_client->getId();
        $owner->commerce_remote_id->setByProvider('commerce_paymill', $customer_id);
      }
    }

    // Create new Paymill payment.
    $paymill_payment = new \Paymill\Models\Request\Payment();
    $paymill_payment->setToken($payment_details['paymill_token']);
    // Create a payment method for an existing customer.
    if ($customer_id) {
      $paymill_payment->setClient($owner->commerce_remote_id->getByProvider('commerce_paymill'));
    }
    $remote_payment_method = $this->paymill_request->create($paymill_payment);
    return $remote_payment_method;
  }

  /**
   * Maps the Paymill credit card type to a Commerce credit card type.
   *
   * @param string $card_type
   *   The Paymill credit card type.
   *
   * @return string
   *   The Commerce credit card type.
   */
  protected function mapCreditCardType($card_type) {
    // https://support.paymill.com/questions/which-cards-and-payment-types-can-i-accept-with-paymill.
    $map = [
      'amex' => 'amex',
      'dinersclub' => 'dinersclub',
      'discover' => 'discover',
      'jcb' => 'jcb',
      'mastercard' => 'mastercard',
      'visa' => 'visa',
    ];
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * Formats the charge amount for paymill.
   *
   * @param integer $amount
   *   The amount being charged.
   *
   * @return integer
   *   The Paymill formatted amount.
   */
  protected function formatNumber($amount) {
    $amount = $amount * 100;
    $amount = number_format($amount, 0, '.', '');
    return $amount;
  }

}
