/**
 * @file
 * Javascript to generate Paymill token in PCI-compliant way.
 */

/* global paymill */

// We need to set PAYMILL_PUBLIC_KEY var.
var PAYMILL_PUBLIC_KEY = '...';

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attaches the commercePaymillForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commercePaymillForm behavior.
   *
   * @see Drupal.commercePaymill
   */
  Drupal.behaviors.commercePaymillForm = {
    attach: function (context) {
      if (!drupalSettings.commercePaymill || !drupalSettings.commercePaymill.publicKey) {
        return;
      }
      $('.paymill-form', context).once('paymill-processed').each(function () {
    	  
        var $form = $(this).closest('form');
        var options = {
                lang: 'de'
                };
        
        // Clear the token every time the payment form is loaded. We only need the token
        // one time, as it is submitted to Paymill after a card is validated. If this
        // form reloads it's due to an error; received tokens are stored in the checkout pane.
        $('#paymill_token').val('');
        PAYMILL_PUBLIC_KEY = drupalSettings.commercePaymill.publicKey;

        var paymillResponseHandler = function (error, result) {
          if (error) {
            // Show the errors on the form
        	console.log(error.apierror,error.message);
            $form.find('.payment-errors').text(error.apierror);
            $form.find('button').prop('disabled', false);
          }
          else {
//            // Token contains id, last4, and card type.
//            var token = result.token;
//            // Insert the token into the form so it gets submitted to the server.
//            $('#paymill_token').val(token);
//
//            // And re-submit.
//            $form.get(0).submit();
          }
        };
        
        var id = 'edit-payment-information-add-payment-method-payment-details';
        if ($('#edit-payment-information-add-payment-method-payment-details').length<1) {
        	id = $('.credit-card-form').attr('id');
        }
        paymill.embedFrame(id, options, paymillResponseHandler);

        $form.submit(function (e) {
          var $form = $(this);
          
          if ($('input[id^="edit-payment-information-payment-method-new-credit-card-paymill"]').is(':checked')) {
	          paymill.createTokenViaFrame({
	              amount_int: drupalSettings.commercePaymill.amount, //required
	              currency: drupalSettings.commercePaymill.currency, //required
	              email: $('#edit-contact-information-email').val() //required
              },function(error, result) {
                  // Handle error or process result.
                  if (error) {
                    // Token could not be created, check error object for reason.
                    console.log(error.apierror, error.message);
        	  		  $form.find('[type="submit"]')
        	  				.removeProp('disabled')
        	  				.removeAttr('disabled')
        	  				.removeClass('c-button--disabled')
        	  				.siblings('.progress-message').remove();
                  }
                    // Token was created successfully and can be sent to backend.
                  else {
	                    console.log(result.token);
	                  var token = result.token;
	                  // Insert the token into the form so it gets submitted to the server.
	                  $('#paymill_token').val(token);
	      
	                  // And re-submit.
	                  $form.get(0).submit();
                    }
                  }
	          );
//	          
	          // Disable the submit button to prevent repeated clicks
	          $form.find('[type="submit"]')
	          		.prop('disabled', true)
	          		.attr('disabled','disabled')
	          		.addClass('c-button--disabled')
	          		.after('<span class="progress-message">&nbsp;Bitte warten...</span>');
	          return false;
          }

//          // Validate card form data.
//          var card_number = $('.card-number').val();
//          var card_expiry_month = $('.card-expiry-month').val();
//          var card_expiry_year = $('.card-expiry-year').val();
//          var card_cvc = $('.card-cvc').val();
//
//          var validated = true;
//          var error_messages = [];
//          if (!paymill.validateCardNumber(card_number)) {
//            validated = false;
//            error_messages.push(Drupal.t('The card number is invalid.'));
//            $('.card-number').addClass('error');
//          }
//          else {
//            $('.card-number').removeClass('error');
//          }
//          if (!paymill.validateExpiry(card_expiry_month, card_expiry_year)) {
//            validated = false;
//            error_messages.push(Drupal.t('The expiry date is invalid.'));
//            $('.card-expiry-month').addClass('error');
//            $('.card-expiry-year').addClass('error');
//          }
//          else {
//            $('.card-expiry-month').removeClass('error');
//            $('.card-expiry-year').removeClass('error');
//          }
//          if (!paymill.validateCvc(card_cvc)) {
//            validated = false;
//            error_messages.push(Drupal.t('The verification code is invalid.'));
//            $('.card-cvc').addClass('error');
//          }
//          else {
//            $('.card-cvc').removeClass('error');
//          }
//          if (error_messages.length > 0) {
//            var payment_errors = '<ul>';
//            error_messages.forEach(function (error_message) {
//              payment_errors += '<li>' + error_message + '</li>';
//            });
//            payment_errors += '</ul>';
//            $form.find('.payment-errors').html(Drupal.theme('commercePaymillError', payment_errors));
//          }
//
//          // Create token if the card form was validated.
//          if (validated) {
//            paymill.createToken({
//              number: card_number,
//              exp_month: card_expiry_month,
//              exp_year: card_expiry_year,
//              cvc: card_cvc
//            }, paymillResponseHandler);
//          }
//
//          // Prevent the form from submitting with the default action.
//          if ($('.card-number').length) {
//            return false;
//          }
        });
      });
    }
  };

  $.extend(Drupal.theme, /** @lends Drupal.theme */{
    commercePaymillError: function (message) {
      return $('<div class="messages messages--error"></div>').html(message);
    }
  });

})(jQuery, Drupal, drupalSettings);
