<?php

namespace Drupal\commerce_paymill\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_paymill\Models\Request\Checksum as ChecksumRequest;
use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Provides the Paymill payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paymill_paypal",
 *   label = @Translation("Paypal (Paymill)"),
 *   display_label = @Translation("PayPal"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paymill\PluginForm\Paymill\PaypalPaymentOffsiteForm",
 *   },
 *   payment_method_types = {"paypal"},
 * )
 */
class PaymillPaypal extends PaymillOffsiteBase {

    const TYPE = \Paymill\Models\Request\Checksum::TYPE_PAYPAL;

    protected function prepareChecksum(PaymentInterface $payment, ChecksumRequest $checksum)
    {
    }


}
