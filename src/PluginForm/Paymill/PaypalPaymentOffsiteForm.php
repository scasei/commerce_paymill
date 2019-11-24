<?php

namespace Drupal\commerce_paymill\PluginForm\Paymill;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class PaypalPaymentOffsiteForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_paymill\Plugin\Commerce\PaymentGateway\PaymillPaypal $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
//     $redirect_method = $payment_gateway_plugin->getConfiguration()['redirect_method'];
//     $remove_js = ($redirect_method == 'post_manual');
    $remove_js = true;
//     if (in_array($redirect_method, ['post', 'post_manual'])) {
//       $redirect_url = Url::fromRoute('commerce_payment_example.dummy_redirect_post')->toString();
//       $redirect_method = 'post';
//     }
//     else {
      // Gateways that use the GET redirect method usually perform an API call
      // that prepares the remote payment and provides the actual url to
      // redirect to. Any params received from that API call that need to be
      // persisted until later payment creation can be saved in $order->data.
      // Example: $order->setData('my_gateway', ['test' => '123']), followed
      // by an $order->save().
      $order = $payment->getOrder();
      // Simulate an API call failing and throwing an exception, for test purposes.
      // See PaymentCheckoutTest::testFailedCheckoutWithOffsiteRedirectGet().
      if ($order->getBillingProfile()->get('address')->family_name == 'FAIL') {
        throw new PaymentGatewayException('Could not get the redirect URL.');
      }
//       $redirect_url = Url::fromRoute('commerce_payment_example.dummy_redirect_302', [], ['absolute' => TRUE])->toString();
      // not needed, will be redirected with js
      $redirect_url = '';
//     }
    $data = [
      'return' => $form['#return_url'],
      'cancel' => $form['#cancel_url'],
      'total' => $payment->getAmount()->getNumber()
    ];

    $form = $this->buildRedirectForm($form, $form_state, $redirect_url, $data, self::REDIRECT_POST);

    if ($remove_js) {
      // Disable the javascript that auto-clicks the Submit button.
      unset($form['#attached']['library']);
    }

    $data['total'] = $payment->getAmount();
    $form['#attached']['library'][] = 'commerce_paymill/form-offsite';
    $form['#attached']['drupalSettings']['commercePaymill'] = [
        'publicKey' => $payment_gateway_plugin->getPaymillPublicKey(),
        'checksum' => $payment_gateway_plugin->createChecksum($payment, $data)
    ];

    return $form;
  }

}
