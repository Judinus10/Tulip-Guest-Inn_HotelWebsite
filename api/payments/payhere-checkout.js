/* External script: compatible with the public site's same-origin CSP. */
(function () {
    'use strict';
    var form = document.getElementById('payhere-checkout-form');
    if (!(form instanceof HTMLFormElement)) return;

    // Keep automatic submission limited to the two supported PayHere endpoints.
    var allowedActions = [
        'https://www.payhere.lk/pay/checkout',
        'https://sandbox.payhere.lk/pay/checkout'
    ];
    if (form.method.toLowerCase() !== 'post' || allowedActions.indexOf(form.action) === -1) return;

    // A payload input named "submit" must not shadow the native submit method.
    HTMLFormElement.prototype.submit.call(form);
}());
