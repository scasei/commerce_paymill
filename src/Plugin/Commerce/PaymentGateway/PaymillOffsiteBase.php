<?php

namespace Drupal\commerce_paymill\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_price\Price;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Paymill\Models\Response\Checksum as ChecksumResponse;
use Drupal\commerce_paymill\Models\Request\Checksum as ChecksumRequest;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Paymill\Services\ResponseHandler;
use Paymill\Models\Request\Transaction as TransactionRequest;
use Paymill\Models\Response\Transaction;


abstract class PaymillOffsiteBase extends OffsitePaymentGatewayBase {

    use PaymillConfigurationTrait;


    abstract protected function prepareChecksum(PaymentInterface $payment, ChecksumRequest $checksum);

    public function createChecksum(PaymentInterface $payment, array $data){

        $checksum = new ChecksumRequest();
        $checksum
            ->setAmount($this->toMinorUnits($data['total'])) // e.g. "4200" for 42.00 EUR
            ->setCurrency($data['total']->getCurrencyCode()) // Alpha-3 country code
            ->setReturnUrl($data['return']) // Required for e.g. PayPal - Valid return URL
            ->setCancelUrl($data['cancel']) // Required for e.g. PayPal - Valid cancel URL
            ->setChecksumType(static::TYPE) // Checksum type
        ;


        $this->prepareChecksum($payment, $checksum);
        $this->createOrUpdateWebhook();

        /* @var $response ChecksumResponse  */
        $response = $this->api->create($checksum);
        return $response->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request) {
        // @todo Add examples of request validation.
        // Note: Since requires_billing_information is FALSE, the order is
        // not guaranteed to have a billing profile. Confirm that
        // $order->getBillingProfile() is not NULL before trying to use it.

        // REQUEST DATA
        /**
         *
    paymill_trx_id: PAYMILL Transaction ID, used to identify the transaction in PAYMILL's system
    paypal_trx_id: PayPal Transaction ID, used to identify the transaction in PayPal's system
    paymill_trx_status: Transaction result, either closed, failed or pending.
    paymill_response_code: Response code providing more details about the transaction status. See the API Reference for more details on response codes.
    paymill_mode: Indicates if the transaction was made in live or test mode.

         */

        if ($request->query->get('paymill_trx_status') == 'failed') {
            throw new PaymentGatewayException($this->t('Payment failed'), $request->query->get('paymill_response_code'));
        }

        // check transaction
        $transaction = $this->loadTransaction($request->query->get('paymill_trx_id'));

        if (!$transaction || !$transaction->getId()) {
            throw new PaymentGatewayException($this->t('Payment failed'), $request->query->get('paymill_response_code'));
        }

        //
        $data = [
            'state' => $this->mapStatus($transaction->getStatus()),
            'amount' => $order->getBalance(),
            'payment_gateway' => $this->entityId,
            'order_id' => $order->id(),
            'remote_id' => $transaction->getId(),
            'remote_state' => $transaction->getStatus(),
        ];

        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payment = $payment_storage->create($data);
        $payment->save();
    }

    protected function createOrUpdateWebhook()
    {
        $url = $this->getNotifyUrl()->toString();

        $event_types = [
            "transaction.succeeded",
            "transaction.failed"
        ];

        // list available webhooks
        $webhookSearch = new \Paymill\Models\Request\Webhook();
        $webhookSearch->setFilter(array(
            'url' => $url
        ));

        /* @var $response \Paymill\Models\Response\Base */
        $responses = $this->api->getAllAsModel($webhookSearch);

        foreach ($responses as $webook_remote) {
            /* @var $webook_remote \Paymill\Models\Response\Webhook */
            if ($url == $webook_remote->getUrl()) {
                return;
            }
        }


        $webhook = new \Paymill\Models\Request\Webhook();
        $webhook
            ->setUrl($url)
            ->setEventTypes($event_types)
        ;

        $response = $this->api->create($webhook);
    }

    /**
     * Processes the notification request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request.
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     *   The response, or NULL to return an empty HTTP 200 response.
     */
    public function onNotify(Request $request)
    {
        // log to drupal
        \Drupal::logger('commerce_paymill')
        ->debug('e-Commerce notification: <pre>@body</pre>', [
            '@body' => var_export($request->query->all(), TRUE),
        ]);

        $notification = json_decode($request->getContent(),true);

        if (!isset($notification['event']['event_resource']['transaction']['id'])) {
            return false;
        }

        $transaction = $this->loadTransaction($notification['event']['event_resource']['transaction']['id']);

        if (!$transaction) {
            return false;
        }

        if (!$this->canProcessNotify($transaction)) {
            return false;
        }

        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payment = $payment_storage->loadByRemoteId($transaction->getId());

        if (!$payment) {
            return false;
        }

        $payment->set('state', $this->mapStatus($transaction->getStatus()));
        $payment->set('remote_state', $transaction->getStatus());
        $payment->save();
        return null;
    }

    protected function canProcessNotify(Transaction $transaction)
    {
        return $transaction->getPayment()->getType() === static::TYPE;
    }

    /**
     *
     * @param string $id
     * @return Transaction
     */
  protected function loadTransaction($id)
  {
      $transaction = new TransactionRequest();
      $transaction->setId($id);

      return $this->api->getOne($transaction);
  }

  protected function calculateTax(OrderInterface $order)
  {
      $ret = 0.0;

      /* @var $tax */
      $tax = $order->getAdjustments(['tax']);
      if (
          isset($tax[0]) && $tax = $tax[0]) {
          $ret += $tax->getAmount()->getNumber();
      }


      foreach ($order->getItems() as $item) {
          $tax = $item->getAdjustments(['tax'])[0];
          $ret += $tax->getAmount()->getNumber();
      }

      return $ret;
  }

  protected function mapStatus($state)
  {
      $statusMap = [
          ''=> 'pending',
          'open' => 'pending',
          'pending' => 'pending',
          'closed' => 'completed',
          'successful'=> 'completed',
          'failed' => 'failed',
          'partial_refunded' => '',
          'refunded' => '',
          'preauthorize' => '',
          'chargeback' => '',
      ];
      return $statusMap[$state];
  }
}
