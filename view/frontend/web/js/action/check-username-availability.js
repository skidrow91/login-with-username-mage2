define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder'
], function (storage, urlBuilder) {
    'use strict';

    return function (deferred, uid) {
        return storage.post(
            urlBuilder.createUrl('/customers/isUsernameAvailable', {}),
            JSON.stringify({
                username: uid
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
