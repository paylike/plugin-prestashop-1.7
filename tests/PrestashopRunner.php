<?php


namespace Prestashop;

use Facebook\WebDriver\Exception\ElementNotVisibleException;
use Facebook\WebDriver\Exception\NoAlertOpenException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Exception\UnexpectedTagNameException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;

class PrestashopRunner extends PrestashopTestHelper {

	/**
	 * @param $args
	 *
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	public function ready( $args ) {
		$this->set( $args );
		$this->go();
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function loginAdmin() {
		$this->goToPage( '', '#email', true );
		while ( ! $this->hasValue( '#email', $this->user ) ) {
			$this->typeLogin();
		}
		$this->click( '.ladda-button' );
		$this->waitForElement( '.admindashboard' );

	}

	/**
	 *  Insert user and password on the login screen
	 */
	private function typeLogin() {
		$this->type( '#email', $this->user );
		$this->type( '#passwd', $this->pass );
	}

	/**
	 * @param $args
	 */
	private function set( $args ) {
		foreach ( $args as $key => $val ) {
			$name = $key;
			if ( isset( $this->{$name} ) ) {
				$this->{$name} = $val;
			}
		}
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function changeCurrency() {
		$this->click( '.currency-selector' );
		try {
			$this->click( "//div[contains(@class, 'currency-selector')]//*[contains(text(), '" . $this->currency . "')]" );
		} catch ( ElementNotVisibleException $e ) {
			$this->click( "//div[contains(@class, 'currency-selector')]//*[contains(text(), '" . $this->currency . "')]" );
		}
	}

	public function changeLanguage() {
		$this->click( '.language-selector' );
		$this->click( "//div[contains(@class, 'language-selector')]//*[contains(text(), 'English')]" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function disableEmail() {
		if ( $this->stop_email === true ) {
			try {
				$this->goToPage( '/index.php?controller=AdminEmails', '#PS_MAIL_METHOD_3', true );
				$this->checkbox( '#PS_MAIL_METHOD_3' );
				$this->click( 'submitOptionsmail' );
			}catch (\Exception $exception){
				// not possible in new versions
			}
		}
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */

	public function changeMode() {
		$this->goToPage( '/index.php?controller=AdminModules&configure=paylikepayment&tab_module=payments_gateways&module_name=paylikepayment', '.btn-continue', true );
		$this->click( ".btn-continue" );
		$this->captureMode();
	}

	/**
	 * @throws NoSuchElementException`
	 * @throws TimeOutException
	 */
	private function settingsCheck() {

		$this->disableEmail();
		$this->outputVersions();
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */

	private function logVersionsRemotly() {
		$versions = $this->getVersions();
		$this->wd->get( getenv( 'REMOTE_LOG_URL' ) . '&key=' . $this->get_slug( $versions['ecommerce'] ) . '&tag=prestashop17&view=html&' . http_build_query( $versions ) );
		$this->waitForElement( '#message' );
		$message = $this->getText( '#message' );
		$this->main_test->assertEquals( 'Success!', $message, "Remote log failed" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	private function getVersions() {
		$this->goToPage( '', null, true );
		$prestashop = $this->getText( '#shop_version' );
		$this->goToPage( "index.php?controller=AdminModules", '.btn-continue', true );
        $this->click( ".btn-continue" );
		$this->waitForElement( ".module-item-list" );
		$paylike = $this->getElementData( '.module-item[data-name="Paylike"]', 'version' );

		return [ 'ecommerce' => $prestashop, 'plugin' => $paylike ];
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	private function outputVersions() {
		$versions = $this->getVersions();
		$this->main_test->log( "Prestashop Version: %s", $versions['ecommerce'] );
		$this->main_test->log( "Paylike Version: %s", $versions['plugin'] );

	}


	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	private function directPayment() {
		$this->goToPage( '', '.currency-selector' );
		$this->changeCurrency();
		$this->changeLanguage();
		$this->clearCartItem();
		$this->addToCart();
		$this->proceedToCheckout();
		$this->choosePaylike();
		$this->finalPaylike();
		$this->selectOrder();
		if ( $this->capture_mode == 'delayed' ) {
			$this->capture();
		} else {
			$this->refund();
		}

	}


	/**
	 * @param $status
	 *
	 * @throws NoSuchElementException
	 * @throws UnexpectedTagNameException
	 */
	public function moveOrderToStatus( $status ) {
		$this->selectValueByLabel('#update_order_status_action_input',$status);
		$this->click( '#update_order_status_action_btn' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	public function capture() {
		$this->moveOrderToStatus( 'Delivered' );
		$text = $this->pluckElement( '.tab-content #historyTabContent tr td', 0 )->getText();
		$this->main_test->assertEquals( 'Delivered', $text, "Payment captured" );
	}

	/**
	 *
	 */
	public function captureMode() {
		$this->click( '#PAYLIKE_CHECKOUT_MODE' );
		$this->click( "//*[contains(@value, '" . $this->capture_mode . "')]" );
		$this->click( '#module_form_submit_btn' );;
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function clearCartItem() {

		try {
			$cartCount = $this->getText( '.cart-products-count' );
		} catch ( StaleElementReferenceException $exception ) {
			// try again
			$cartCount = $this->getText( '.cart-products-count' );
		}

		$cartCount = preg_replace( "/[^0-9.]/", "", $cartCount );
		if ( $cartCount ) {
			$this->waitElementDisappear( "#blockcart-modal" );
			$this->click( ".cart-preview" );
			$productRemoves = $this->findElements( '.cart-items .remove-from-cart' );

			try {
				$productRemoves[0]->click( '.cart-items .remove-from-cart' );
			} catch ( StaleElementReferenceException $exception ) {
				// can happen
			}


		}
	}

	/**
	 *
	 */
	public function addToCart() {
		$this->waitForElement( '.product-miniature' );
		$this->click( '.product-miniature' );
		$this->waitForElement( '.product-information' );
		$this->click( '.add-to-cart' );
		$this->waitForElement( '.cart-content-btn .btn-primary' );
		$this->click( '.cart-content-btn .btn-primary' );

	}

	/**
	 *
	 */
	public function proceedToCheckout() {
		$this->waitForElement( '.checkout .btn-primary' );
		$this->click( '.checkout .btn-primary' );
		$this->click( "//*[contains(@data-link-action, 'show-login-form')]" );
		$this->click( ".tab-pane.active  #login-form .form-control" );
		$this->type( ".tab-pane.active  #login-form .form-control", $this->client_user );
		$this->click( ".tab-pane.active  #login-form .js-visible-password" );
		$this->type( ".tab-pane.active  #login-form .js-visible-password", $this->client_pass );
		$this->click( '.tab-pane.active  #login-form .continue' );
		$this->waitForElement( '.address-selector' );
		$this->click( 'confirm-addresses' );
		$this->waitForElement( '.delivery-options' );
		$this->click( 'confirmDeliveryOption' );;
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function choosePaylike() {
		$this->click( '.custom-radio [data-module-name="paylikepayment"]' );
		$this->click( "conditions_to_approve[terms-and-conditions]" );
		$this->click( "#pay-by-paylike" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function finalPaylike() {
		$expectedAmount = $this->getText('.cart-total span.value');
		$expectedAmount = preg_replace("/[^0-9.]/", "", $expectedAmount);
		$expectedAmount = ceil(round($expectedAmount, 3) * get_paylike_currency_multiplier($this->currency));
        $amount         = $this->getText('.paylike .payment .amount');
        $amount         = preg_replace("/[^0-9.]/", "", $amount);
        $amount         = trim($amount, '.');
        $amount         = ceil(round($amount, 4) * get_paylike_currency_multiplier($this->currency));

		$this->main_test->assertEquals($expectedAmount, $amount, "Checking minor amount for " . $this->currency);
		$this->popupPaylike();
		$this->waitForElement( ".qty" );
		$priceValue = $this->getText( ".h1.card-title" );
		// because the title of the page matches the checkout title, we need to use the order received class on body
		$this->main_test->assertEquals( 'YOUR ORDER IS CONFIRMED', $priceValue );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function popupPaylike() {
		try {
			$this->waitForElement( '.paylike.overlay .payment form #card-number' );
			$this->type( '.paylike.overlay .payment form #card-number', 41000000000000 );
			$this->type( '.paylike.overlay .payment form #card-expiry', '11/22' );
			$this->type( '.paylike.overlay .payment form #card-code', '122' );
			$this->click( '.paylike.overlay .payment form button' );
		} catch ( NoSuchElementException $exception ) {
			$this->confirmOrder();
			$this->popupPaylike();
		}

	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function selectOrder() {
		$this->goToPage( "/index.php?controller=AdminOrders", '#page-header-desc-configuration-add', true );
		$this->waitForElement( '.text-right .btn[data-original-title="View"]' );
		$this->click( '.text-right .btn[data-original-title="View"]' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	public function refund() {
		$this->waitForElement( '.partial-refund-display' );
		$this->click('.partial-refund-display');
		$this->type( '.refund-quantity', 1 );
		$this->click( '#cancel_product_save' );

		$this->waitForElement( '.alert.alert-success' );
		$text = $this->pluckElement( '.alert.alert-success', 0 )->getText();
		$this->main_test->assertEquals( 'Paylike: Transaction successfully Refunded.', $text, "Refunded" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	public function confirmOrder() {
		$this->waitForElement( '#paylike-payment-button' );
		$this->click( '#paylike-payment-button' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 */
	private function settings() {
		$this->changeMode();

	}

	/**
	 * @throws NoSuchElementException
	 * @throws TimeOutException
	 * @throws UnexpectedTagNameException
	 */
	private function go() {
		$this->changeWindow();
		$this->loginAdmin();
		if ( $this->settings_check ) {
			$this->settingsCheck();

			return $this;
		}

		if ( $this->log_version ) {
			$this->logVersionsRemotly();

			return $this;
		}


		$this->settings();


		$this->directPayment();

	}

	/**
	 *
	 */
	private function changeWindow() {
		$this->wd->manage()->window()->setSize( new WebDriverDimension( 1600, 996 ) );
	}


}

