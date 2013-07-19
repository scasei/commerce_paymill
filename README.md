# Commerce Paymill

## Introduction 

**Commerce Paymill** is [Drupal Commerce](https://drupal.org/project/commerce)
module that integrates the [Paymill](https://paymill.com) payement
gateway into your Drupal Commerce shop.

All development happens on the 2.x branch. The 1.x branch is
**unmaintained** and will have no further releases.

## Features

 1. **multiple currencies**
 2. **pre-authorization** and **capture** &mdash; thus avoiding refund
    charges for you as a merchant in the case of a return by a
    customer, also allowing complete control of order balancing.    
 3. **card on file** functionality that allows for you securely to
    charge a client card without having to deal with the huge hassle
    of storing credit card numbers.

Note that to enable the card on file funcionality you need to install
the **2.x** version of the
[`commerce_cardonfile`](https://drupal.org/project/commerce_cardonfile)
module.

## Roadmap

 1. Release **2.1** will have all of the above and:
  + proxy support for sites that use a forward proxy to whitelist
    server to server calls as a security measure.
  + [drush](http://drush.ws) command to download and update the library.
  + informative translatable error messages for the client when an
    error occurs.
    
 2. Release **2.2** adds extensive logging for security and analytics.
 
Development of the module is sponsored by
[CommerceGuys](http://commerceguys.com) and
[Paymill](https://paymill.com).
