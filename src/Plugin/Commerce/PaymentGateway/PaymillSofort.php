<?php

namespace Drupal\commerce_paymill\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_paymill\Models\Request\Checksum as ChecksumRequest;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Paymill\Models\Response\Transaction;

/**
 * Provides the Paymill payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paymill_sofort",
 *   label = @Translation("Sofort (Paymill)"),
 *   display_label = @Translation("Sofort"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paymill\PluginForm\Paymill\PaypalPaymentOffsiteForm",
 *   }
 * )
 */
class PaymillSofort extends PaymillOffsiteBase {

    const TYPE = 'sofort';

    protected function prepareChecksum(PaymentInterface $payment, ChecksumRequest $checksum)
    {
        $order = $payment->getOrder();
        $address = $order->getBillingProfile()->address;

        $billingAddress = array( // Optional - Billing address
            'name'                    => $address->given_name.' '. $address->family_name,
            'street_address'          => $address->address_line1,
            'postal_code'             => $address->postal_code, // Optional
            'city'                    => $address->locality,
            'country'                 => $address->country_code,  // Alpha-2 country code
        );

        if ($address->address_line2) {
            $billingAddress['street_address_addition'] = $address->address_line2; // Optional
        }

        $checksum
            ->setBillingAddress($billingAddress)
            ->setClientEmail($order->getEmail())
        ;
    }
}
