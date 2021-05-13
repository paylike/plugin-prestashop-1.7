<?php

namespace Prestashop;


use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Exception\UnexpectedTagNameException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * @group prestashop_quick_test
 */
class PrestashopQuickTest extends AbstractTestCase {

	public $runner;

    /**
     * @throws NoSuchElementException
     * @throws TimeOutException
     * @throws UnexpectedTagNameException
     */
	public function testUsdPaymentBeforeOrderInstant() {
		$this->runner = new PrestashopRunner( $this );
		$this->runner->ready( array(
				'capture_mode'           => 'delayed',
			)
		);
	}
}