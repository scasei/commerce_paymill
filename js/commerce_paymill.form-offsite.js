/**
 * @file
 * Javascript to generate Paymill token in PCI-compliant way.
 */

/* global paymill */
var PAYMILL_PUBLIC_KEY = '...';


// We need to set PAYMILL_PUBLIC_KEY var.
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
      if (
    	!drupalSettings.commercePaymill 
    	|| !drupalSettings.commercePaymill.checksum
      ) {
        return;
      }
      PAYMILL_PUBLIC_KEY = drupalSettings.commercePaymill.publicKey;
      
      $('.payment-redirect-form .form-submit', context)
      	.on('click',Drupal.behaviors.commercePaymillForm.createTransaction)
      	.click()
      ;     
    },
    createTransaction: function(e) {
    	console.log(drupalSettings.commercePaymill.checksum);
        paymill.createTransaction({
      	  checksum: drupalSettings.commercePaymill.checksum
      	}, function(error) {
      	  if (error) {
      	    // Payment setup failed, handle error and try again.
      	    console.log(error);
      	  }
        });
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
  };

})(jQuery, Drupal, drupalSettings);
