define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Customer/js/model/customer',
    'Magento_Customer/js/action/check-email-availability',
    'Magento_Customer/js/action/login',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'Axl_UIDLogin/js/action/check-username-availability',
    'mage/validation',
], function ($, Component, ko, customer, checkEmailAvailability, loginAction, quote, checkoutData, fullScreenLoader, checkUsernameAvailability) {
    'use strict';

    var validatedEmail;

    if (!checkoutData.getValidatedEmailValue() &&
        window.checkoutConfig.validatedEmailValue
    ) {
        checkoutData.setInputFieldEmailValue(window.checkoutConfig.validatedEmailValue);
        checkoutData.setValidatedEmailValue(window.checkoutConfig.validatedEmailValue);
    }

    validatedEmail = checkoutData.getValidatedEmailValue();

    if (validatedEmail && !customer.isLoggedIn()) {
        quote.guestEmail = validatedEmail;
    }

    return Component.extend({
        defaults: {
            template: 'Magento_Checkout/form/element/email',
            email: checkoutData.getInputFieldEmailValue(),
            emailFocused: false,
            uid: '',
            extEmail: checkoutData.getInputFieldEmailValue(),
            isLoading: false,
            isPasswordVisible: false,
            isEmailVisible: false,
            listens: {
                email: 'emailHasChanged',
                emailFocused: 'validateEmail',
                uid: 'usernameHasChanged',
                extEmail: 'extEmailHasChanged'
            },
            ignoreTmpls: {
                email: true
            }
        },
        checkDelay: 2000,
        checkRequest: null,
        isEmailCheckComplete: null,
        isUidCheckComplete: null,
        isCustomerLoggedIn: customer.isLoggedIn,
        forgotPasswordUrl: window.checkoutConfig.forgotPasswordUrl,
        emailCheckTimeout: 0,
        uidCheckTimeout: 0,

        /**
         * Initializes observable properties of instance
         *
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super()
                .observe(['email', 'emailFocused', 'isLoading', 'isPasswordVisible', 'uid', 'isEmailVisible', 'extEmail']);

            return this;
        },

        /** @inheritdoc */
        initConfig: function () {
            this._super();

            this.isPasswordVisible = this.resolveInitialPasswordVisibility();

            return this;
        },

        /**
         * Callback on changing email property
         */
        emailHasChanged: function () {
            var self = this;

            clearTimeout(this.emailCheckTimeout);

            if (self.validateEmail()) {
                quote.guestEmail = self.email();
                checkoutData.setValidatedEmailValue(self.email());
            }
            this.emailCheckTimeout = setTimeout(function () {
                if (self.validateEmail()) {
                    self.checkEmailAvailability();
                } else {
                    self.isPasswordVisible(false);
                }
            }, self.checkDelay);

            checkoutData.setInputFieldEmailValue(self.email());
        },

        /**
         * Check email existing.
         */
        checkEmailAvailability: function () {
            this.validateRequest();
            this.isEmailCheckComplete = $.Deferred();
            this.isLoading(true);
            this.checkRequest = checkEmailAvailability(this.isEmailCheckComplete, this.email());

            $.when(this.isEmailCheckComplete).done(function () {
                this.isPasswordVisible(false);
            }.bind(this)).fail(function () {
                this.isPasswordVisible(true);
                checkoutData.setCheckedEmailValue(this.email());
            }.bind(this)).always(function () {
                this.isLoading(false);
            }.bind(this));
        },

        /**
         * If request has been sent -> abort it.
         * ReadyStates for request aborting:
         * 1 - The request has been set up
         * 2 - The request has been sent
         * 3 - The request is in process
         */
        validateRequest: function () {
            if (this.checkRequest != null && $.inArray(this.checkRequest.readyState, [1, 2, 3])) {
                this.checkRequest.abort();
                this.checkRequest = null;
            }
        },

        /**
         * Local email validation.
         *
         * @param {Boolean} focused - input focus.
         * @returns {Boolean} - validation result.
         */
        validateEmail: function (focused) {
            var loginFormSelector = 'form[data-role=email-with-possible-login]',
                usernameSelector = loginFormSelector + ' input[name=username]',
                loginForm = $(loginFormSelector),
                validator;

            loginForm.validation();

            if (focused === false && !!this.email()) {
                return !!$(usernameSelector).valid();
            }

            validator = loginForm.validate();

            return validator.check(usernameSelector);
        },

        /**
         * Log in form submitting callback.
         *
         * @param {HTMLElement} loginForm - form element.
         */
        login: function (loginForm) {
            var loginData = {},
                formDataArray = $(loginForm).serializeArray();

            formDataArray.forEach(function (entry) {
                loginData[entry.name] = entry.value;
            });

            if (this.isPasswordVisible() && $(loginForm).validation() && $(loginForm).validation('isValid')) {
                fullScreenLoader.startLoader();
                loginAction(loginData).always(function () {
                    fullScreenLoader.stopLoader();
                });
            }
        },

        /**
         * Resolves an initial sate of a login form.
         *
         * @returns {Boolean} - initial visibility state.
         */
        resolveInitialPasswordVisibility: function () {
            if (checkoutData.getInputFieldEmailValue() !== '') {
                return checkoutData.getInputFieldEmailValue() === checkoutData.getCheckedEmailValue();
            }

            return false;
        },

        isUIDEnabled: function () {
            if (typeof window.checkoutConfig.isUIDEnabled != 'undefined') {
                if (window.checkoutConfig.isUIDEnabled == 1) {
                    return true;
                }
                else {
                    return false;
                }
            }
            else {
                return false;
            }
        },

        /**
         * Check username existing.
         */
        checkUsernameAvailability: function () {
            this.validateRequest();
            this.isUidCheckComplete = $.Deferred();
            this.isLoading(true);
            this.checkRequest = checkUsernameAvailability(this.isUidCheckComplete, this.uid());

            $.when(this.isUidCheckComplete).done(function () {
                this.isPasswordVisible(false);
                this.isEmailVisible(true);
            }.bind(this)).fail(function () {
                this.isPasswordVisible(true);
                this.isEmailVisible(false);
                // checkoutData.setCheckedEmailValue(this.email());
            }.bind(this)).always(function () {
                this.isLoading(false);
            }.bind(this));
        },

        /**
         * Check extEmail existing.
         */
        checkExtEmailAvailability: function () {
            var elm = $('form[data-role=email-with-possible-login] input[name=username]');
            $('#customer-email-error').remove();
            elm.removeClass('mage-error');
            this.validateRequest();
            this.isEmailCheckComplete = $.Deferred();
            this.isLoading(true);
            this.checkRequest = checkEmailAvailability(this.isEmailCheckComplete, this.extEmail());

            $.when(this.isEmailCheckComplete).done(function () {
            }.bind(this)).fail(function () {
                elm.addClass('mage-error');
                elm.after('<div for="customer-email" generated="true" class="mage-error" id="customer-email-error">This email has been taken.</div>');
            }.bind(this)).always(function () {
                this.isLoading(false);
            }.bind(this));
        },

        /**
         * Callback on changing username property
         */
        usernameHasChanged: function () {
            var self = this;

            clearTimeout(this.uidCheckTimeout);

            this.uidCheckTimeout = setTimeout(function () {
                self.checkUsernameAvailability();
            }, self.checkDelay);
        },

        /**
         * Local extEmail validation.
         *
         * @param {Boolean} focused - input focus.
         * @returns {Boolean} - validation result.
         */
        validateExtEmail: function (focused) {
            var loginFormSelector = 'form[data-role=email-with-possible-login]',
                usernameSelector = loginFormSelector + ' input[name=username]',
                loginForm = $(loginFormSelector),
                validator;

            loginForm.validation();

            if (focused === false && !!this.extEmail()) {
                return !!$(usernameSelector).valid();
            }

            validator = loginForm.validate();

            return validator.check(usernameSelector);
        },

        /**
         * Callback on changing email property
         */
        extEmailHasChanged: function () {
            var self = this;

            clearTimeout(this.emailCheckTimeout);

            if (self.validateExtEmail()) {
                quote.guestEmail = self.extEmail();
                checkoutData.setValidatedEmailValue(self.extEmail());
            }
            this.emailCheckTimeout = setTimeout(function () {
                if (self.validateExtEmail()) {
                    self.checkExtEmailAvailability();
                }
            }, self.checkDelay);

            checkoutData.setInputFieldEmailValue(self.extEmail());
        }
    });
});
