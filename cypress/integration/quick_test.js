/// <reference types="cypress" />

'use strict';

import { TestMethods } from '../support/test_methods.js';

describe('paylike plugin quick test', () => {
    /**
     * Login into admin and frontend to store cookies.
     */
    before(() => {
        TestMethods.loginIntoClientAccount();
        TestMethods.loginIntoAdminBackend();
    });

    /**
     * Run this on every test case bellow
     * - preserve cookies between tests
     */
    beforeEach(() => {
        Cypress.Cookies.defaults({
            preserve: (cookie) => {
              return true;
            }
        });
    });

    let currency = Cypress.env('ENV_CURRENCY_TO_CHANGE_WITH');
    let captureMode = 'Delayed';

    /**
     * Modify Paylike capture mode
     */
    it('modify Paylike settings for capture mode', () => {
        TestMethods.changePaylikeCaptureMode(captureMode);
    });

    /** Pay and process order. */
    /** Capture */
    TestMethods.payWithSelectedCurrency(currency, 'capture');

    /** Refund last created order (previously captured). */
    it('Process last order captured from admin panel to be refunded', () => {
        TestMethods.processOrderFromAdmin('refund');
    });

    /** Capture */
    TestMethods.payWithSelectedCurrency(currency, 'capture');

    /** Partial refund last created order (previously captured). */
    it('Process last order captured from admin panel to be refunded', () => {
        TestMethods.processOrderFromAdmin('refund', /*partialAmount*/ true);
    });

    /** Void */
    TestMethods.payWithSelectedCurrency(currency, 'void');

}); // describe