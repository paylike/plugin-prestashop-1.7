/// <reference types="cypress" />

'use strict';

import { PaylikeTestHelper } from './test_helper.js';

export var TestMethods = {

    /** Admin & frontend user credentials. */
    StoreUrl: (Cypress.env('ENV_ADMIN_URL').match(/^(?:http(?:s?):\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/im))[0],
    AdminUrl: Cypress.env('ENV_ADMIN_URL'),
    RemoteVersionLogUrl: Cypress.env('REMOTE_LOG_URL'),

    /** Construct some variables to be used bellow. */
    ShopName: 'prestashop17',
    PaylikeName: 'paylike',
    OrderStatusForCapture: '',
    PaymentMethodAdminUrl: '/index.php?controller=AdminModules&configure=paylikepayment',
    OrdersPageAdminUrl: '/index.php/sell/orders',

    /**
     * Login to admin backend account
     */
    loginIntoAdminBackend() {
        cy.loginIntoAccount('input[name=email]', 'input[name=passwd]', 'admin');
    },
    /**
     * Login to client|user frontend account
     */
    loginIntoClientAccount() {
        cy.loginIntoAccount('input[id=field-email]', 'input[name=password]', 'client');
    },

    /**
     * Modify Paylike settings
     * @param {String} captureMode
     */
    changePaylikeCaptureMode(captureMode) {
        /** Go to Paylike payment method. */
        cy.goToPage(this.PaymentMethodAdminUrl);

        /**
         * Accept token warning.
         * This warning show up even if we set the token on url.
         * So, we do not set it and click on the button.
         */
        cy.get(`a[href*="${this.PaymentMethodAdminUrl}"]`).click();

        /**
         * Get order statuses to be globally used.
         */
        this.getPaylikeOrderStatuses();

        /** Select capture mode. */
        cy.get('#PAYLIKE_CHECKOUT_MODE').select(captureMode);

        /** Save. */
        cy.get('#module_form_submit_btn').click();
    },

    /**
     * Make payment with specified currency and process order
     *
     * @param {String} currency
     * @param {String} paylikeAction
     * @param {Boolean} partialAmount
     */
     payWithSelectedCurrency(currency, paylikeAction, partialAmount = false) {
        /** Make an instant payment. */
        it(`makes a Paylike payment with "${currency}"`, () => {
            this.makePaymentFromFrontend(currency);
        });

        /** Process last order from admin panel. */
        it(`process (${paylikeAction}) an order from admin panel`, () => {
            this.processOrderFromAdmin(paylikeAction, partialAmount);
        });
    },

    /**
     * Make an instant payment
     * @param {String} currency
     */
    makePaymentFromFrontend(currency) {
        /** Go to store frontend. */
        cy.goToPage(this.StoreUrl);

        /** Change currency. */
        this.changeShopCurrency(currency);

        cy.wait(300);

        /**
         * Go to random product page.
         */
        var randomInt = PaylikeTestHelper.getRandomInt(/*max*/ 5);
        var productId = randomInt + 1; // product id > 0
        cy.goToPage(this.StoreUrl + `/index.php?id_product=${productId}&controller=product`);

        /** Add to cart. */
        cy.get('button.add-to-cart').click();

        /** Go to checkout. */
        cy.goToPage(this.StoreUrl + '/index.php?controller=order', {timeout: 10000});

        /** Continue. */
        cy.get('button[name="confirm-addresses"]').click();
        cy.wait(200);
        cy.get('button[name="confirmDeliveryOption"]').click();
        cy.wait(200);

        /** Choose Paylike. */
        cy.get(`input[data-module-name*=${this.PaylikeName}]`).click();

        /** Check amount. */
        cy.get('div.cart-summary-line.cart-total .value').then($grandTotal => {
            var expectedAmount = PaylikeTestHelper.filterAndGetAmountInMinor($grandTotal, currency);
            cy.window().then(win => {
                expect(expectedAmount).to.eq(Number(win.PayLikePayment.amount));
            });
        });

        /** Agree Terms & Conditions. */
        cy.get('input[id="conditions_to_approve[terms-and-conditions]"]').click();

        /** Show paylike popup. */
        cy.get('#pay-by-paylike').click();

        /**
         * Fill in Paylike popup.
         */
         PaylikeTestHelper.fillAndSubmitPaylikePopup();

        cy.get('h3.h1.card-title', {timeout: 10000}).should('contain', 'Your order is confirmed');
    },

    /**
     * Process last order from admin panel
     * @param {String} paylikeAction
     * @param {Boolean} partialAmount
     */
    processOrderFromAdmin(paylikeAction, partialAmount = false) {
        /** Go to admin orders page. */
        cy.goToPage(this.OrdersPageAdminUrl);

        /**
         * Accept token warning.
         * This warning show up even if we set the token on url.
         * So, we do not set it and click on the button.
         */
         cy.get(`a[href*="${this.OrdersPageAdminUrl}"]`).click();

        PaylikeTestHelper.setPositionRelativeOn('#header_infos');
        PaylikeTestHelper.setPositionRelativeOn('.header-toolbar');

        /** Click on first (latest in time) order from orders table. */
        cy.get('table tbody tr').first().click();

        /**
         * Take specific action on order
         */
        this.paylikeActionOnOrderAmount(paylikeAction, partialAmount);
    },

    /**
     * Capture an order amount
     * @param {String} paylikeAction
     * @param {Boolean} partialAmount
     */
     paylikeActionOnOrderAmount(paylikeAction, partialAmount = false) {
        switch (paylikeAction) {
            case 'capture':
                cy.get('#update_order_status_new_order_status_id').select(this.OrderStatusForCapture);
                cy.get('.btn-primary.update-status').click();
            break;

            case 'refund':
                cy.get('.btn-action.partial-refund-display').click();
                /** Verify if refund with Paylike is checked. */
                cy.get('input#doRefundPaylike').should('have.attr', 'checked');

                /** If we got multiple products, be sure to select only one. */
                cy.get('input.refund-quantity').first().clear().type(1);
                cy.get('input[id*="cancel_product_amount"]').click();

                if (partialAmount) {
                    /**
                     * Put 8 major units to be refunded.
                     * Premise: any product must have price >= 8.
                     */
                    cy.get('input[id*="cancel_product_amount"]').clear().type(8);
                }
                /** Save. */
                cy.get('#cancel_product_save').click();
            break;

            case 'void':
                cy.get('#update_order_status_new_order_status_id').select('Canceled');
            break;
        }

        /** Check if success message. */
        cy.get('.alert.alert-success').should('be.visible');
    },

    /**
     * Change shop currency in frontend
     */
    changeShopCurrency(currency) {
        cy.get('button[aria-label="Currency dropdown"]').click();
        cy.get('ul[aria-labelledby="currency-selector-label"] li a').each($listLink => {
            if ($listLink.text().includes(currency)) {
                cy.get($listLink).click();
            }
        });
    },

    /**
     * Get Paylike order statuses from settings
     */
     getPaylikeOrderStatuses() {
        /** Get order status for capture. */
        cy.get('#PAYLIKE_ORDER_STATUS > option[selected=selected]').then($captureStatus => {
            this.OrderStatusForCapture = $captureStatus.text();
        });
    },

    /**
     * Get Shop & Paylike versions and send log data.
     */
    logVersions() {
        /** Get framework version. */
        cy.get('#footer').then($frameworkVersion => {
            var frameworkVersion = ($frameworkVersion.text()).replace(/.*[^0-9.]/g, '');
            cy.wrap(frameworkVersion).as('frameworkVersion');
        });

        cy.goToPage(this.PaymentMethodAdminUrl);

        /** Get Paylike version. */
        cy.get('.panel-title').invoke('attr', 'data-paylike-version').then($pluginVersion => {
            cy.wrap($pluginVersion).as('pluginVersion');
        });

        /** Get global variables and make log data request to remote url. */
        cy.get('@frameworkVersion').then(frameworkVersion => {
            cy.get('@pluginVersion').then(pluginVersion => {

                cy.request('GET', this.RemoteVersionLogUrl, {
                    key: frameworkVersion,
                    tag: this.ShopName,
                    view: 'html',
                    ecommerce: frameworkVersion,
                    plugin: pluginVersion
                }).then((resp) => {
                    expect(resp.status).to.eq(200);
                });
            });
        });
    },
}