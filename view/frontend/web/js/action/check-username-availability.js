define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder'
], function (storage, urlBuilder) {
    'use strict';

    return function (deferred, email) {
        return storage.post(
            urlBuilder.createUrl('/customers/isEmailAvailable', {}),
            JSON.stringify({
                customerEmail: email
            }),
            false
        ).done(function (isUserAvailable) {
            if (isUserAvailable) {
                deferred.resolve();
            } else {
                deferred.reject();
            }
        }).fail(function () {
            deferred.reject();
        });
    };
});
