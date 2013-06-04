<?php

/**
 * @file
 * Administrative functions for Paymill
 */

/**
 * Allow users to capture authorized amounts.
 */
function commerce_paymill_capture_form($form, &$form_state, $order, $transaction) {
  $form_state['order'] = $order;
  $form_state['transaction'] = $transaction;

  // Load and store the payment method instance for this transaction.
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $form_state['payment_method'] = $payment_method;

  $balance = commerce_payment_order_balance($order);

  if ($balance['amount'] > 0 && $balance['amount'] < $transaction->amount) {
    $default_amount = $balance['amount'];
  }
  else {
    $default_amount = $transaction->amount;
  }

  // Convert the price amount to a user friendly decimal value.
  $default_amount = commerce_currency_amount_to_decimal($default_amount, $transaction->currency_code);

  $description = implode('<br />', array(
    t('Authorization: @amount', array('@amount' => commerce_currency_format($transaction->amount, $transaction->currency_code))),
    t('Order balance: @balance', array('@balance' => commerce_currency_format($balance['amount'], $balance['currency_code']))),
  ));

  $form['amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Capture amount'),
    '#description' => filter_xss($description),
    '#default_value' => $default_amount,
    '#field_suffix' => check_plain($transaction->currency_code),
    '#size' => 16,
  );

  $form = confirm_form($form,
    t('What amount do you want to capture?'),
    'admin/commerce/orders/' . $order->order_id . '/payment',
    '',
    t('Capture'),
    t('Cancel'),
    'confirm'
  );

  return $form;
}

/**
 * Submit handler: process a transaction for the authorized amount.
 */
function commerce_paymill_capture_form_submit($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $amount = $form_state['values']['amount'];
  $order = $form_state['order'];
  $payment_method = $form_state['payment_method'];

  // Include Paymill libraries.
  libraries_load('paymill');

  // Get current API key.
  $api_key = commerce_paymill_get_key($payment_method['settings']);

  // Attempt capture using Paymill.
  $transaction_service = new Services_Paymill_Transactions($api_key, COMMERCE_PAYMILL_SERVER);
  $transaction_params = array(
    'amount' => $amount,
    'currency' => $transaction->currency_code,
    'preauthorization' => $transaction->remote_id,
    'description' => 'Order:' . $order->order_id,
  );
  $response = $transaction_service->create($transaction_params);

  // Write transaction status.
  if (isset($response['error'])) {
    drupal_set_message(t('Prior authorization capture failed, so the transaction will remain in a pending status.'), 'error');
    drupal_set_message(check_plain($response['error']), 'error');
  }
  else {
    drupal_set_message(t('Prior authorization captured successfully.'));
    $transaction->amount = commerce_currency_decimal_to_amount($amount, $transaction->currency_code);
    $transaction->status = COMMERCE_PAYMENT_STATUS_SUCCESS;
    $transaction->remote_status = 'success';
    $transaction->remote_id = $response['id'];
  }

  commerce_payment_transaction_save($transaction);

  $form_state['redirect'] = 'admin/commerce/orders/' . $form_state['order']->order_id . '/payment';
}

/**
 * Redirect user to configuration page.
 */
function commerce_paymill_configure() {
  // Check Payment UI is enabled.
  if (!module_exists('commerce_payment_ui')) {
    drupal_set_message(t('The Payment UI module is disabled - please enable it before configuring Commerce Paymill'), 'warning');
    drupal_goto('admin/modules');
    return;
  }

  // Load default payment rule configuration.
  $rule = rules_config_load('commerce_payment_commerce_paymill');
  if (empty($rule)) {
    drupal_set_message(t('The default payment rule configuration cannot be found'), 'warning');
    drupal_goto('admin/commerce/config/payment-methods');
    return;
  }

  // Check first action for default payment rule configuration.
  $action = $rule->actions()->current();
  if (empty($action)) {
    drupal_set_message(t('No actions exist for the default payment rule configuration'), 'warning');
    $goto = sprintf(
      'admin/commerce/config/payment-methods/manage/%d',
      $rule->id
    );
    drupal_goto($goto);
    return;
  }

  // Redirect user to configuration page.
  $goto = sprintf(
    'admin/commerce/config/payment-methods/manage/%s/edit/%d',
    $rule->name,
    $action->elementId()
  );
  drupal_goto($goto);
}
