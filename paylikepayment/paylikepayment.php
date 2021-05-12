<?php
/**
 *
 * @author    DerikonDevelopment <ionut@derikon.com>
 * @copyright Copyright (c) permanent, DerikonDevelopment
 * @license   Addons PrestaShop license limitation
 * @version   1.1.2
 * @link      http://www.derikon.com/
 *
 */

if ( ! defined( '_PS_VERSION_' ) ) {
	exit;
}

if ( ! class_exists( 'Paylike\\Client' ) ) {
	require_once( 'api/Client.php' );
}

require_once __DIR__.'/vendor/autoload.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class PaylikePayment extends PaymentModule {
	private $html = '';
	protected $statuses_array = array();

	public function __construct() {
		$this->name      = 'paylikepayment';
		$this->tab       = 'payments_gateways';
		$this->version   = '1.1.2';
		$this->author    = 'DerikonDevelopment';
		$this->bootstrap = true;

		$this->currencies      = true;
		$this->currencies_mode = 'checkbox';

		$this->validationPublicKeys = array ('live'=>array(),'test'=>array());

		parent::__construct();

		$this->displayName      = $this->l( 'Paylike' );
		$this->description      = $this->l( 'Receive payment with Paylike' );
		$this->confirmUninstall = $this->l( 'Are you sure about removing Paylike?' );
		$popupDescription       = $this->l( 'Secure payment with credit card via © Paylike' );
	}

	public function install() {
		$popup_title   = ( ! empty( Configuration::get( 'PS_SHOP_NAME' ) ) ) ? Configuration::get( 'PS_SHOP_NAME' ) : 'Payment';
		$language_code = $this->context->language->iso_code;

		Configuration::updateValue( 'PAYLIKE_LANGUAGE_CODE', $language_code );
		Configuration::updateValue( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE', 'Credit card' );
		Configuration::updateValue( 'PAYLIKE_PAYMENT_METHOD_LOGO', 'visa.svg,visa-electron.svg,mastercard.svg,mastercard-maestro.svg' );
		Configuration::updateValue( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC', 'Secure payment with credit card via © Paylike' );
		Configuration::updateValue( $language_code . '_PAYLIKE_POPUP_TITLE', $popup_title );
		Configuration::updateValue( 'PAYLIKE_SHOW_POPUP_DESC', 'no' );
		Configuration::updateValue( $language_code . '_PAYLIKE_POPUP_DESC', '' );
		Configuration::updateValue( 'PAYLIKE_TRANSACTION_MODE', 'test' );
		Configuration::updateValue( 'PAYLIKE_TEST_SECRET_KEY', '' );
		Configuration::updateValue( 'PAYLIKE_TEST_PUBLIC_KEY', '' );
		Configuration::updateValue( 'PAYLIKE_LIVE_SECRET_KEY', '' );
		Configuration::updateValue( 'PAYLIKE_LIVE_PUBLIC_KEY', '' );
		Configuration::updateValue( 'PAYLIKE_CHECKOUT_MODE', 'delayed' );
		Configuration::updateValue( 'PAYLIKE_ORDER_STATUS', Configuration::get( 'PAYLIKE_ORDER_STATUS' ) );
		Configuration::updateValue( 'PAYLIKE_STATUS', 'enabled' );
		Configuration::updateValue( 'PAYLIKE_SECRET_KEY', '' );

		return ( parent::install()
		         && $this->registerHook( 'header' )
		         && $this->registerHook( 'payment' )
		         && $this->registerHook( 'paymentOptions' )
		         && $this->registerHook( 'paymentReturn' )
		         && $this->registerHook( 'DisplayAdminOrder' )
		         && $this->registerHook( 'BackOfficeHeader' )
		         && $this->registerHook( 'actionOrderStatusPostUpdate' )
		         && $this->registerHook( 'actionOrderSlipAdd' )
		         && $this->installDb() );
	}

	public function installDb() {
		return (
			Db::getInstance()->Execute( 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'paylike_admin` (
                `id`				INT(11) NOT NULL AUTO_INCREMENT,
                `paylike_tid`		VARCHAR(255) NOT NULL,
                `order_id`			INT(11) NOT NULL,
                `payed_at`			DATETIME NOT NULL,
                `payed_amount`		DECIMAL(20,6) NOT NULL,
                `refunded_amount`	DECIMAL(20,6) NOT NULL,
                `captured`		    VARCHAR(255) NOT NULL,
                PRIMARY KEY			(`id`)
                ) ENGINE=InnoDB		DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;' )

			&& Db::getInstance()->Execute( 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'paylike_logos` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `default_logo` INT(11) NOT NULL DEFAULT 1 COMMENT "1=Default",
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;' )

			&& Db::getInstance()->insert(
				'paylike_logos',
				array(
					array(
						'id'         => 1,
						'name'       => pSQL( 'VISA' ),
						'slug'       => pSQL( 'visa' ),
						'file_name'  => pSQL( 'visa.svg' ),
						'created_at' => date( 'Y-m-d H:i:s' ),
					),
					array(
						'id'         => 2,
						'name'       => pSQL( 'VISA Electron' ),
						'slug'       => pSQL( 'visa-electron' ),
						'file_name'  => pSQL( 'visa-electron.svg' ),
						'created_at' => date( 'Y-m-d H:i:s' ),
					),
					array(
						'id'         => 3,
						'name'       => pSQL( 'Mastercard' ),
						'slug'       => pSQL( 'mastercard' ),
						'file_name'  => pSQL( 'mastercard.svg' ),
						'created_at' => date( 'Y-m-d H:i:s' ),
					),
					array(
						'id'         => 4,
						'name'       => pSQL( 'Mastercard Maestro' ),
						'slug'       => pSQL( 'mastercard-maestro' ),
						'file_name'  => pSQL( 'mastercard-maestro.svg' ),
						'created_at' => date( 'Y-m-d H:i:s' ),
					),
				)
			)
		);
	}

	public function uninstall() {
		//$sql = 'SELECT * FROM `'._DB_PREFIX_.'paylike_logos`';
		$sql = new DbQuery();
		$sql->select( '*' );
		$sql->from( 'paylike_logos', 'PL' );
		$sql->where( 'PL.default_logo != 1' );
		$logos = Db::getInstance()->executes( $sql );

		foreach ( $logos as $logo ) {
			if ( file_exists( _PS_MODULE_DIR_ . $this->name . '/views/img/' . $logo['file_name'] ) ) {
				unlink( _PS_MODULE_DIR_ . $this->name . '/views/img/' . $logo['file_name'] );
			}
		}

		//Fetch all languages and delete Paylike configurations which has language iso_code as prefix
		$languages = Language::getLanguages( true, $this->context->shop->id );
		foreach ( $languages as $language ) {
			$language_code = $language['iso_code'];
			Configuration::deleteByName( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' );
			Configuration::deleteByName( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC' );
			Configuration::deleteByName( $language_code . '_PAYLIKE_POPUP_TITLE' );
			Configuration::deleteByName( $language_code . '_PAYLIKE_POPUP_DESC' );
		}

		return (
			parent::uninstall()
			&& Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'paylike_admin`' )
			&& Db::getInstance()->Execute( 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'paylike_logos`' )
			&& Configuration::deleteByName( 'PAYLIKE_PAYMENT_METHOD_TITLE' )
			&& Configuration::deleteByName( 'PAYLIKE_PAYMENT_METHOD_LOGO' )
			&& Configuration::deleteByName( 'PAYLIKE_PAYMENT_METHOD_DESC' )
			&& Configuration::deleteByName( 'PAYLIKE_POPUP_TITLE' )
			&& Configuration::deleteByName( 'PAYLIKE_SHOW_POPUP_DESC' )
			&& Configuration::deleteByName( 'PAYLIKE_POPUP_DESC' )
			&& Configuration::deleteByName( 'PAYLIKE_TRANSACTION_MODE' )
			&& Configuration::deleteByName( 'PAYLIKE_TEST_SECRET_KEY' )
			&& Configuration::deleteByName( 'PAYLIKE_TEST_PUBLIC_KEY' )
			&& Configuration::deleteByName( 'PAYLIKE_LIVE_SECRET_KEY' )
			&& Configuration::deleteByName( 'PAYLIKE_LIVE_PUBLIC_KEY' )
			&& Configuration::deleteByName( 'PAYLIKE_CHECKOUT_MODE' )
			&& Configuration::deleteByName( 'PAYLIKE_ORDER_STATUS' )
			&& Configuration::deleteByName( 'PAYLIKE_STATUS' )
			&& Configuration::deleteByName( 'PAYLIKE_SECRET_KEY' )
		);
	}

	public function getPSV() {
		return Tools::substr( _PS_VERSION_, 0, 5 );
	}

	public function getContent() {
		$this->html = '';
		if ( Tools::isSubmit( 'submitPaylike' ) ) {
			$language_code = Configuration::get( 'PAYLIKE_LANGUAGE_CODE' );
			$valid         = true;

			$PAYLIKE_PAYMENT_METHOD_TITLE = ! empty( Tools::getvalue( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) ) ? Tools::getvalue( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) : '';
			$PAYLIKE_PAYMENT_METHOD_DESC  = ! empty( Tools::getvalue( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC' ) ) ? Tools::getvalue( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC' ) : '';
			$PAYLIKE_POPUP_TITLE          = ( ! empty( Tools::getvalue( $language_code . '_PAYLIKE_POPUP_TITLE' ) ) ) ? Tools::getvalue( $language_code . '_PAYLIKE_POPUP_TITLE' ) : '';
			$_PAYLIKE_POPUP_DESC          = ( ! empty( Tools::getvalue( $language_code . '_PAYLIKE_POPUP_DESC' ) ) ) ? Tools::getvalue( $language_code . '_PAYLIKE_POPUP_DESC' ) : '';

			if ( empty( $PAYLIKE_PAYMENT_METHOD_TITLE ) ) {
				$this->context->controller->errors[ $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ] = $this->l( 'Payment method title required!' );
				$PAYLIKE_PAYMENT_METHOD_TITLE                                                          = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) : '';
				$valid                                                                                 = false;
			}

			if ( count( Tools::getvalue( 'PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO' ) ) > 1 ) {
				$creditCardLogo = implode( ',', Tools::getvalue( 'PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO' ) );
			} else {
				$creditCardLogo = Tools::getvalue( 'PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO' );
			}

			if ( Tools::getvalue( 'PAYLIKE_TRANSACTION_MODE' ) == 'test' ) {
				/** Load db value or set it to empty **/
				$PAYLIKE_TEST_SECRET_KEY = ( ! empty( Configuration::get( 'PAYLIKE_TEST_SECRET_KEY' ) ) ) ? Configuration::get( 'PAYLIKE_TEST_SECRET_KEY' ) : '';
				$validationPublicKeyMessage = $this->validateAppKeyField(Tools::getvalue( 'PAYLIKE_TEST_SECRET_KEY' ),'test');
				if($validationPublicKeyMessage){
					$this->context->controller->errors['PAYLIKE_TEST_SECRET_KEY'] = $validationPublicKeyMessage;
					$valid                                                        = false;
				}else{
					$PAYLIKE_TEST_SECRET_KEY = ( ! empty( Tools::getvalue( 'PAYLIKE_TEST_SECRET_KEY' ) ) ) ? Tools::getvalue( 'PAYLIKE_TEST_SECRET_KEY' ) : '';
				}

				/** Load db value or set it to empty **/
				$PAYLIKE_TEST_PUBLIC_KEY = ( ! empty( Configuration::get( 'PAYLIKE_TEST_PUBLIC_KEY' ) ) ) ? Configuration::get( 'PAYLIKE_TEST_PUBLIC_KEY' ) : '';
				$validationAppKeyMessage = $this->validatePublicKeyField(Tools::getvalue( 'PAYLIKE_TEST_PUBLIC_KEY' ),'test');
				if($validationAppKeyMessage){
					$this->context->controller->errors['PAYLIKE_TEST_PUBLIC_KEY'] = $validationAppKeyMessage;
					$valid                                                        = false;
				}else{
					$PAYLIKE_TEST_PUBLIC_KEY = ( ! empty( Tools::getvalue( 'PAYLIKE_TEST_PUBLIC_KEY' ) ) ) ? Tools::getvalue( 'PAYLIKE_TEST_PUBLIC_KEY' ) : '';
				}

			} elseif ( Tools::getvalue( 'PAYLIKE_TRANSACTION_MODE' ) == 'live' ) {
				/** Load db value or set it to empty **/
				$PAYLIKE_LIVE_SECRET_KEY = ( ! empty( Configuration::get( 'PAYLIKE_LIVE_SECRET_KEY' ) ) ) ? Configuration::get( 'PAYLIKE_LIVE_SECRET_KEY' ) : '';
				$validationPublicKeyMessage = $this->validateAppKeyField(Tools::getvalue( 'PAYLIKE_LIVE_SECRET_KEY' ),'live');
				if($validationPublicKeyMessage){
					$this->context->controller->errors['PAYLIKE_LIVE_SECRET_KEY'] = $validationPublicKeyMessage;
					$valid                                                        = false;
				}else{
					$PAYLIKE_LIVE_SECRET_KEY = ( ! empty( Tools::getvalue( 'PAYLIKE_LIVE_SECRET_KEY' ) ) ) ? Tools::getvalue( 'PAYLIKE_LIVE_SECRET_KEY' ) : '';
				}

				/** Load db value or set it to empty **/
				$PAYLIKE_LIVE_PUBLIC_KEY = ( ! empty( Configuration::get( 'PAYLIKE_LIVE_PUBLIC_KEY' ) ) ) ? Configuration::get( 'PAYLIKE_LIVE_PUBLIC_KEY' ) : '';
				$validationAppKeyMessage = $this->validatePublicKeyField(Tools::getvalue( 'PAYLIKE_LIVE_PUBLIC_KEY' ),'live');
				if($validationAppKeyMessage){
					$this->context->controller->errors['PAYLIKE_LIVE_PUBLIC_KEY'] = $validationAppKeyMessage;
					$valid                                                        = false;
				}else{
					$PAYLIKE_LIVE_PUBLIC_KEY = ( ! empty( Tools::getvalue( 'PAYLIKE_LIVE_PUBLIC_KEY' ) ) ) ? Tools::getvalue( 'PAYLIKE_LIVE_PUBLIC_KEY' ) : '';
				}
			}

			Configuration::updateValue( 'PAYLIKE_TRANSACTION_MODE', $language_code );
			Configuration::updateValue( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE', $PAYLIKE_PAYMENT_METHOD_TITLE );
			Configuration::updateValue( 'PAYLIKE_PAYMENT_METHOD_LOGO', $creditCardLogo );
			Configuration::updateValue( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC', $PAYLIKE_PAYMENT_METHOD_DESC );
			Configuration::updateValue( $language_code . '_PAYLIKE_POPUP_TITLE', $PAYLIKE_POPUP_TITLE );
			Configuration::updateValue( 'PAYLIKE_SHOW_POPUP_DESC', Tools::getvalue( 'PAYLIKE_SHOW_POPUP_DESC' ) );
			Configuration::updateValue( $language_code . '_PAYLIKE_POPUP_DESC', $_PAYLIKE_POPUP_DESC );
			Configuration::updateValue( 'PAYLIKE_TRANSACTION_MODE', Tools::getvalue( 'PAYLIKE_TRANSACTION_MODE' ) );
			if ( Tools::getvalue( 'PAYLIKE_TRANSACTION_MODE' ) == 'test' ) {
				Configuration::updateValue( 'PAYLIKE_TEST_SECRET_KEY', $PAYLIKE_TEST_SECRET_KEY );
				Configuration::updateValue( 'PAYLIKE_TEST_PUBLIC_KEY', $PAYLIKE_TEST_PUBLIC_KEY );
			}
			if ( Tools::getvalue( 'PAYLIKE_TRANSACTION_MODE' ) == 'live' ) {
				Configuration::updateValue( 'PAYLIKE_LIVE_SECRET_KEY', $PAYLIKE_LIVE_SECRET_KEY );
				Configuration::updateValue( 'PAYLIKE_LIVE_PUBLIC_KEY', $PAYLIKE_LIVE_PUBLIC_KEY );
			}
			Configuration::updateValue( 'PAYLIKE_CHECKOUT_MODE', Tools::getValue( 'PAYLIKE_CHECKOUT_MODE' ) );
			Configuration::updateValue( 'PAYLIKE_ORDER_STATUS', Tools::getValue( 'PAYLIKE_ORDER_STATUS' ) );
			Configuration::updateValue( 'PAYLIKE_STATUS', Tools::getValue( 'PAYLIKE_STATUS' ) );

			if ( $valid ) {
				$this->context->controller->confirmations[] = $this->l( 'Settings saved successfully' );
			}
		}

		//Get configuration form
		$this->html .= $this->renderForm();

		$this->html .= $this->getModalForAddMoreLogo();

		$this->context->controller->addJS( $this->_path . 'views/js/backoffice.js' );

		return $this->html;
	}

	public function renderForm() {
		$this->languages_array = array();
		$this->statuses_array  = array();
		$this->logos_array     = array();

		$language_code = Configuration::get( 'PAYLIKE_LANGUAGE_CODE' );

		//Fetch all active languages
		$languages = Language::getLanguages( true, $this->context->shop->id );
		foreach ( $languages as $language ) {
			$data = array(
				'id_option' => $language['iso_code'],
				'name'      => $language['name']
			);
			array_push( $this->languages_array, $data );
		}

		//Fetch Status list
		$valid_statuses = array( '2', '3', '4', '5', '12' );
		$statuses       = OrderState::getOrderStates( (int) $this->context->language->id );
		foreach ( $statuses as $status ) {
			//$this->statuses_array[$status['id_order_state']] = $status['name'];
			if ( in_array( $status['id_order_state'], $valid_statuses ) ) {
				$data = array(
					'id_option' => $status['id_order_state'],
					'name'      => $status['name']
				);
				array_push( $this->statuses_array, $data );
			}
		}

		//$sql = 'SELECT * FROM `'._DB_PREFIX_.'paylike_logos`';
		$sql = new DbQuery();
		$sql->select( '*' );
		$sql->from( 'paylike_logos' );
		$logos = Db::getInstance()->executes( $sql );

		foreach ( $logos as $logo ) {
			$data = array(
				'id_option' => $logo['file_name'],
				'name'      => $logo['name']
			);
			array_push( $this->logos_array, $data );
		}

		//Set configuration form fields
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l( 'Paylike Payments Settings' ),
					'icon'  => 'icon-cogs'
				),
				'input'  => array(
					/*array(
                        'type' => 'select',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Language').'">'.$this->l('Language').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_LANGUAGE_CODE',
                        'class' => 'paylike-config paylike-language',
                        'options' => array(
                            'query' => $this->languages_array,
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                    ),*/
					array(
						'type'     => 'text',
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'Payment method title' ) . '">' . $this->l( 'Payment method title' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE',
						'class'    => 'paylike-config',
						'required' => true
					),
					array(
						'type'     => 'select',
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'Choose a logo to show in frontend checkout page.' ) . '">' . $this->l( 'Payment method credit card logos' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => 'PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO[]',
						'class'    => 'paylike-config creditcard-logo',
						'multiple' => true,
						'options'  => array(
							'query' => $this->logos_array,
							'id'    => 'id_option',
							'name'  => 'name'
						),
					),
					array(
						'type'  => 'textarea',
						'label' => '<span data-toggle="tooltip" title="' . $this->l( 'Payment method description' ) . '">' . $this->l( 'Payment method description' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'  => $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC',
						'class' => 'paylike-config',
						//'required' => true
					),
					array(
						'type'  => 'text',
						'label' => '<span data-toggle="tooltip" title="' . $this->l( 'The text shown in the popup where the customer inserts the card details' ) . '">' . $this->l( 'Payment popup title' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'  => $language_code . '_PAYLIKE_POPUP_TITLE',
						'class' => 'paylike-config',
						//'required' => true
					),
					array(
						'type'    => 'select',
						'lang'    => true,
						'label'   => '<span data-toggle="tooltip" title="' . $this->l( 'If this is set to no the product list will be shown' ) . '">' . $this->l( 'Show payment popup description' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'    => 'PAYLIKE_SHOW_POPUP_DESC',
						'class'   => 'paylike-config',
						'options' => array(
							'query' => array(
								array(
									'id_option' => 'yes',
									'name'      => 'Yes'
								),
								array(
									'id_option' => 'no',
									'name'      => 'No'
								),
							),
							'id'    => 'id_option',
							'name'  => 'name'
						)
					),
					array(
						'type'  => 'text',
						'label' => '<span data-toggle="tooltip" title="' . $this->l( 'Text description that shows up on the payment popup.' ) . '">' . $this->l( 'Popup description' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'  => $language_code . '_PAYLIKE_POPUP_DESC',
						'class' => 'paylike-config'
					),
					array(
						'type'     => 'select',
						'lang'     => true,
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'In test mode, you can create a successful transaction with the card number 4100 0000 0000 0000 with any CVC and a valid expiration date.' ) . '">' . $this->l( 'Transaction mode' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => 'PAYLIKE_TRANSACTION_MODE',
						'class'    => 'paylike-config',
						'options'  => array(
							'query' => array(
								array(
									'id_option' => 'test',
									'name'      => 'Test'
								),
								array(
									'id_option' => 'live',
									'name'      => 'Live'
								),
							),
							'id'    => 'id_option',
							'name'  => 'name'
						),
						'required' => true
					),
					array(
						'type'     => 'text',
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'Get it from your Paylike dashboard' ) . '">' . $this->l( 'Test mode App Key' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => 'PAYLIKE_TEST_SECRET_KEY',
						'class'    => 'paylike-config',
						'required' => true
					),
					array(
						'type'     => 'text',
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'Get it from your Paylike dashboard' ) . '">' . $this->l( 'Test mode Public Key' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => 'PAYLIKE_TEST_PUBLIC_KEY',
						'class'    => 'paylike-config',
						'required' => true
					),
					array(
						'type'     => 'text',
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'Get it from your Paylike dashboard' ) . '">' . $this->l( 'Live mode App Key' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => 'PAYLIKE_LIVE_SECRET_KEY',
						'class'    => 'paylike-config',
						'required' => true
					),
					array(
						'type'     => 'text',
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'Get it from your Paylike dashboard' ) . '">' . $this->l( 'Live mode Public Key' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => 'PAYLIKE_LIVE_PUBLIC_KEY',
						'class'    => 'paylike-config',
						'required' => true
					),
					array(
						'type'     => 'select',
						'lang'     => true,
						'label'    => '<span data-toggle="tooltip" title="' . $this->l( 'If you deliver your product instantly (e.g. a digital product), choose Instant mode. If not, use Delayed' ) . '">' . $this->l( 'Capture mode' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'     => 'PAYLIKE_CHECKOUT_MODE',
						'class'    => 'paylike-config',
						'options'  => array(
							'query' => array(
								array(
									'id_option' => 'delayed',
									'name'      => $this->l( 'Delayed' )
								),
								array(
									'id_option' => 'instant',
									'name'      => $this->l( 'Instant' )
								),
							),
							'id'    => 'id_option',
							'name'  => 'name'
						),
						'required' => true,
						// 'desc' => $this->l('Instant capture: Amount is captured as soon as the order is confirmed by customer.').'<br>'.$this->l('Delayed capture: Amount is captured after order status is changed to shipped.')
					),
					array(
						'type'    => 'select',
						'lang'    => true,
						'label'   => '<span data-toggle="tooltip" title="' . $this->l( 'The transaction will be captured once the order has the chosen status' ) . '">' . $this->l( 'Capture on order status (delayed mode)' ) . '<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
						'name'    => 'PAYLIKE_ORDER_STATUS',
						'class'   => 'paylike-config',
						'options' => array(
							'query' => $this->statuses_array,
							'id'    => 'id_option',
							'name'  => 'name'
						)
					),
					array(
						'type'    => 'select',
						'lang'    => true,
						'name'    => 'PAYLIKE_STATUS',
						'label'   => $this->l( 'Status' ),
						'class'   => 'paylike-config',
						'options' => array(
							'query' => array(
								array(
									'id_option' => 'enabled',
									'name'      => 'Enabled'
								),
								array(
									'id_option' => 'disabled',
									'name'      => 'Disabled'
								),
							),
							'id'    => 'id_option',
							'name'  => 'name'
						)
					),
				),
				'submit' => array(
					'title' => $this->l( 'Save' ),
				),

			),
		);


		$helper                           = new HelperForm();
		$helper->show_toolbar             = false;
		$helper->table                    = $this->table;
		$lang                             = new Language( (int) Configuration::get( 'PS_LANG_DEFAULT' ) );
		$helper->default_form_language    = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get( 'PS_BO_ALLOW_EMPLOYEE_FORM_LANG' ) ? Configuration::get( 'PS_BO_ALLOW_EMPLOYEE_FORM_LANG' ) : 0;
		$this->fields_form                = array();

		$helper->identifier    = $this->identifier;
		$helper->submit_action = 'submitPaylike';
		$helper->currentIndex  = $this->context->link->getAdminLink( 'AdminModules', false ) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
		$helper->token         = Tools::getAdminTokenLite( 'AdminModules' );
		$helper->tpl_vars      = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages'    => $this->context->controller->getLanguages(),
			'id_language'  => $this->context->language->id
		);


		$errors = $this->context->controller->errors;
		foreach ( $fields_form['form']['input'] as $key => $field ) {
			if ( array_key_exists( $field['name'], $errors ) ) {
				$fields_form['form']['input'][ $key ]['class'] = ! empty( $fields_form['form']['input'][ $key ]['class'] ) ? $fields_form['form']['input'][ $key ]['class'] . ' has-error' : 'has-error';
			}
		}

		return $helper->generateForm( array( $fields_form ) );
	}

	public function getConfigFieldsValues() {
		$language_code = Configuration::get( 'PAYLIKE_LANGUAGE_CODE' );

		$creditCardLogo = explode( ',', Configuration::get( 'PAYLIKE_PAYMENT_METHOD_LOGO' ) );

		$payment_method_title = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_TITLE' ) ) ? Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_TITLE' ) : '' );
		$payment_method_desc  = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_DESC' ) ) ? Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_DESC' ) : '' );
		$popup_title          = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_POPUP_TITLE' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_POPUP_TITLE' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_POPUP_TITLE' ) ) ? Configuration::get( 'en_PAYLIKE_POPUP_TITLE' ) : '' );
		$popup_description    = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_POPUP_DESC' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_POPUP_DESC' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_POPUP_DESC' ) ) ? Configuration::get( 'en_PAYLIKE_POPUP_DESC' ) : '' );

		if ( empty( $payment_method_title ) ) {
			$this->context->controller->errors[ $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ] = $this->l( 'Payment method title required!' );
		}

		if ( Configuration::get( 'PAYLIKE_TRANSACTION_MODE' ) == 'test' ) {
			$validationAppKeyMessage = $this->validateAppKeyField(Configuration::get( 'PAYLIKE_TEST_SECRET_KEY' ),'test');
			if ($validationAppKeyMessage && empty($this->context->controller->errors['PAYLIKE_TEST_SECRET_KEY'])) {
				$this->context->controller->errors['PAYLIKE_TEST_SECRET_KEY'] = $validationAppKeyMessage;
			}
			$validationPublicKeyMessage = $this->validatePublicKeyField(Configuration::get( 'PAYLIKE_TEST_PUBLIC_KEY' ),'test');
			if ($validationPublicKeyMessage && empty($this->context->controller->errors['PAYLIKE_TEST_PUBLIC_KEY'])) {
				$this->context->controller->errors['PAYLIKE_TEST_PUBLIC_KEY'] = $validationPublicKeyMessage;
			}
		} elseif ( Configuration::get( 'PAYLIKE_TRANSACTION_MODE') == 'live' ) {
			$validationAppKeyMessage = $this->validateAppKeyField(Configuration::get( 'PAYLIKE_LIVE_SECRET_KEY' ),'live');
			if ($validationAppKeyMessage && empty($this->context->controller->errors['PAYLIKE_LIVE_SECRET_KEY'])) {
				$this->context->controller->errors['PAYLIKE_LIVE_SECRET_KEY'] = $validationAppKeyMessage;
			}
			$validationPublicKeyMessage = $this->validatePublicKeyField(Configuration::get( 'PAYLIKE_LIVE_PUBLIC_KEY' ),'live');
			if ($validationPublicKeyMessage && empty($this->context->controller->errors['PAYLIKE_LIVE_PUBLIC_KEY'])) {
				$this->context->controller->errors['PAYLIKE_LIVE_PUBLIC_KEY'] = $validationPublicKeyMessage;
			}
		}
		//print_r($this->context->controller->errors);
		//die(Configuration::get('PAYLIKE_TRANSACTION_MODE'));

		return array(
			'PAYLIKE_LANGUAGE_CODE'                          => Configuration::get( 'PAYLIKE_LANGUAGE_CODE' ),
			$language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' => $payment_method_title,
			'PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO[]'       => $creditCardLogo,
			$language_code . '_PAYLIKE_PAYMENT_METHOD_DESC'  => $payment_method_desc,
			$language_code . '_PAYLIKE_POPUP_TITLE'          => $popup_title,
			'PAYLIKE_SHOW_POPUP_DESC'                        => Configuration::get( 'PAYLIKE_SHOW_POPUP_DESC' ),
			$language_code . '_PAYLIKE_POPUP_DESC'           => $popup_description,
			'PAYLIKE_TRANSACTION_MODE'                       => Configuration::get( 'PAYLIKE_TRANSACTION_MODE' ),
			'PAYLIKE_TEST_PUBLIC_KEY'                        => Configuration::get( 'PAYLIKE_TEST_PUBLIC_KEY' ),
			'PAYLIKE_TEST_SECRET_KEY'                        => Configuration::get( 'PAYLIKE_TEST_SECRET_KEY' ),
			'PAYLIKE_LIVE_PUBLIC_KEY'                        => Configuration::get( 'PAYLIKE_LIVE_PUBLIC_KEY' ),
			'PAYLIKE_LIVE_SECRET_KEY'                        => Configuration::get( 'PAYLIKE_LIVE_SECRET_KEY' ),
			'PAYLIKE_CHECKOUT_MODE'                          => Configuration::get( 'PAYLIKE_CHECKOUT_MODE' ),
			'PAYLIKE_ORDER_STATUS'                           => Configuration::get( 'PAYLIKE_ORDER_STATUS' ),
			'PAYLIKE_STATUS'                                 => Configuration::get( 'PAYLIKE_STATUS' ),
		);
	}

	/**
	 * Validate the App key.
	 *
	 * @param string $value - the value of the input.
	 * @param string $mode - the transaction mode 'test' | 'live'.
	 *
	 * @return string - the error message
	 */
	public function validateAppKeyField( $value, $mode ) {
		/** Check if the key value is empty **/
		if ( ! $value ) {
			return $this->l( 'The '.$mode.' App Key required!' );
		}
		/** Load the client from API**/
		$paylikeClient = new \Paylike\Paylike( $value );
		try {
			/** Load the identity from API**/
			$identity = $paylikeClient->apps()->fetch();
		} catch ( \Paylike\Exception\ApiException $exception ) {
			PrestaShopLogger::addLog( $exception );
			return $this->l( "The ".$mode." App Key doesn't seem to be valid!");
		}

		try {
			/** Load the merchants public keys list corresponding for current identity **/
			$merchants = $paylikeClient->merchants()->find( $identity['id'] );
			if ( $merchants ) {
				foreach ( $merchants as $merchant ) {
					/** Check if the key mode is the same as the transaction mode **/
					if(($mode == 'test' && $merchant['test']) || ($mode != 'test' && !$merchant['test'])){
						$this->validationPublicKeys[$mode][] = $merchant['key'];
					}
				}
			}
		} catch ( \Paylike\Exception\ApiException $exception ) {
			PrestaShopLogger::addLog( $exception );
		}

		/** Check if public keys array for the current mode is populated **/
		if ( empty( $this->validationPublicKeys[$mode] ) ) {
			/** Generate the error based on the current mode **/
			$error = $this->l( 'The '.$mode .' App Key is not valid or set to '.array_values(array_diff(array_keys($this->validationPublicKeys), array($mode)))[0].' mode!' );
			PrestaShopLogger::addLog( $error );
			return $error;
		}
	}

	/**
	 * Validate the Public key.
	 *
	 * @param string $value - the value of the input.
	 * @param string $mode - the transaction mode 'test' | 'live'.
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function validatePublicKeyField($value, $mode) {
		/** Check if the key value is not empty **/
		if ( ! $value ) {
			return $this->l( 'The '.$mode.' Public Key required!' );
		}
		/** Check if the local stored public keys array is empty OR the key is not in public keys list **/
		if ( empty( $this->validationPublicKeys[$mode] ) || ! in_array( $value, $this->validationPublicKeys[$mode] ) ) {
			$error = $this->l( 'The '.$mode.' Public Key doesn\'t seem to be valid!' );
			PrestaShopLogger::addLog( $error );
			return $error;
		}
	}

	public function getModalForAddMoreLogo() {
		$this->context->smarty->assign( array(
			'request_uri' => $this->context->link->getAdminLink( 'AdminOrders', false ),
			'tok'         => Tools::getAdminToken( 'AdminOrders' )
		) );

		return $this->display( __FILE__, 'views/templates/admin/modal.tpl' );
	}

	public function hookHeader() {
		/*if(Configuration::get('PAYLIKE_STATUS') == 'enabled' && $this->context->controller->php_self == 'order') {
            $this->context->controller->addJs('https://sdk.paylike.io/3.js');
        }*/
	}

	public function hookPaymentOptions( $params ) {
		$language_code = Configuration::get( 'PAYLIKE_LANGUAGE_CODE' );
		//ensure paylike key is set
		if ( Configuration::get( 'PAYLIKE_TRANSACTION_MODE' ) == 'test' ) {
			if ( ! Configuration::get( 'PAYLIKE_TEST_PUBLIC_KEY' ) || ! Configuration::get( 'PAYLIKE_TEST_SECRET_KEY' ) ) {
				return false;
			} else {
				$PAYLIKE_PUBLIC_KEY = Configuration::get( 'PAYLIKE_TEST_PUBLIC_KEY' );
				Configuration::updateValue( 'PAYLIKE_SECRET_KEY', Configuration::get( 'PAYLIKE_TEST_SECRET_KEY' ) );
			}
		}

		if ( Configuration::get( 'PAYLIKE_TRANSACTION_MODE' ) == 'live' ) {
			if ( ! Configuration::get( 'PAYLIKE_LIVE_PUBLIC_KEY' ) || ! Configuration::get( 'PAYLIKE_LIVE_SECRET_KEY' ) ) {
				return false;
			} else {
				$PAYLIKE_PUBLIC_KEY = Configuration::get( 'PAYLIKE_LIVE_PUBLIC_KEY' );
				Configuration::updateValue( 'PAYLIKE_SECRET_KEY', Configuration::get( 'PAYLIKE_LIVE_SECRET_KEY' ) );
			}
		}

		if ( ! Configuration::get( 'PAYLIKE_TEST_PUBLIC_KEY' ) && ! Configuration::get( 'PAYLIKE_TEST_SECRET_KEY' ) && ! Configuration::get( 'PAYLIKE_LIVE_PUBLIC_KEY' ) && ! Configuration::get( 'PAYLIKE_LIVE_SECRET_KEY' ) ) {
			return false;
		}

		$products       = $params['cart']->getProducts();
		$products_array = array();
		$products_label = array();
		$p              = 0;
		foreach ( $products as $product ) {
			$products_array[]     = array(
				$this->l( 'ID' )       => $product['id_product'],
				$this->l( 'Name' )     => $product['name'],
				$this->l( 'Quantity' ) => $product['cart_quantity']
			);
			$products_label[ $p ] = $product['quantity'] . 'x ' . $product['name'];
			$p ++;
		}

		$payment_method_title = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_TITLE' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_TITLE' ) ) ? Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_TITLE' ) : '' );
		$payment_method_desc  = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_PAYMENT_METHOD_DESC' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_DESC' ) ) ? Configuration::get( 'en_PAYLIKE_PAYMENT_METHOD_DESC' ) : '' );
		$popup_title          = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_POPUP_TITLE' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_POPUP_TITLE' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_POPUP_TITLE' ) ) ? Configuration::get( 'en_PAYLIKE_POPUP_TITLE' ) : '' );

		if ( Configuration::get( 'PAYLIKE_SHOW_POPUP_DESC' ) == 'yes' ) {
			$popup_description = ( ! empty( Configuration::get( $language_code . '_PAYLIKE_POPUP_DESC' ) ) ) ? Configuration::get( $language_code . '_PAYLIKE_POPUP_DESC' ) : ( ! empty( Configuration::get( 'en_PAYLIKE_POPUP_DESC' ) ) ? Configuration::get( 'en_PAYLIKE_POPUP_DESC' ) : '' );
		} else {
			//$popup_description = implode( ", & ", $products_label );
			$popup_description = '';
		}

		$currency            = new Currency( (int) $params['cart']->id_currency );
		$currency_code       = $currency->iso_code;
		$currency_multiplier = $this->getPaylikeCurrencyMultiplier( $currency->iso_code );
		$amount              = $this->getPaylikeAmount(  $params['cart']->getOrderTotal(), $currency->iso_code );
		$customer            = new Customer( (int) $params['cart']->id_customer );
		$name                = $customer->firstname . ' ' . $customer->lastname;
		$email               = $customer->email;
		$customer_address    = new Address( (int) ( $params['cart']->id_address_delivery ) );
		$telephone           = ! empty( $customer_address->phone ) ? $customer_address->phone : ( ! empty( $customer_address->phone_mobile ) ? $customer_address->phone_mobile : '' );
		$address             = $customer_address->address1 . ', ' . $customer_address->address2 . ', ' . $customer_address->city . ', ' . $customer_address->country . ' - ' . $customer_address->postcode;
		$ip                  = Tools::getRemoteAddr();
		$locale              = $this->context->language->iso_code;
		$platform_version    = _PS_VERSION_;
		$ecommerce           = 'prestashop';
		$module_version      = $this->version;

		$redirect_url = $this->context->link->getModuleLink( 'paylikepayment', 'paymentreturn', array(), true, (int) $this->context->language->id );

		if ( Configuration::get( 'PS_REWRITING_SETTINGS' ) == 1 ) {
			$redirect_url = Tools::strReplaceFirst( '&', '?', $redirect_url );
		}

		$this->context->smarty->assign( array(
			'PAYLIKE_PUBLIC_KEY'             => $PAYLIKE_PUBLIC_KEY,
			'PS_SSL_ENABLED'                 => ( Configuration::get( 'PS_SSL_ENABLED' ) ? 'https' : 'http' ),
			'http_host'                      => Tools::getHttpHost(),
			'shop_name'                      => $this->context->shop->name,
			'payment_method_title'           => $payment_method_title,
			'payment_method_creditcard_logo' => explode( ',', Configuration::get( 'PAYLIKE_PAYMENT_METHOD_LOGO' ) ),
			'payment_method_desc'            => $payment_method_desc,
			'paylike_status'                 => Configuration::get( 'PAYLIKE_STATUS' ),
			'popup_title'                    => $popup_title,
			'popup_description'              => $popup_description,
			'currency_code'                  => $currency_code,
			'amount'                         => $amount,
			'id_cart'                        => Tools::jsonEncode( $params['cart']->id ),
			'products'                       => str_replace("\u0022","\\\\\"",Tools::jsonEncode(  $products_array ,JSON_HEX_QUOT)),
			'name'                           => $name,
			'email'                          => $email,
			'telephone'                      => $telephone,
			'address'                        => $address,
			'ip'                             => $ip,
			'locale'                         => $locale,
			'platform_version'               => $platform_version,
			'ecommerce'                      => $ecommerce,
			'module_version'                 => $module_version,
			'redirect_url'                   => $redirect_url,
			'qry_str'                        => ( Configuration::get( 'PS_REWRITING_SETTINGS' ) ? '?' : '&' ),
			'base_uri'                       => __PS_BASE_URI__,
			'this_path_paylike'              => $this->_path
		) );

		$newOption = new PaymentOption();
		$newOption->setModuleName( $this->name )
		          ->setCallToActionText( $this->trans( $payment_method_title, array() ) )
		          ->setAction( $this->context->link->getModuleLink( $this->name, 'validation', array(), true ) )
		          ->setAdditionalInformation( $this->display( __FILE__, 'views/templates/hook/payment.tpl' ) );
		$payment_options = array( $newOption );

		return $payment_options;
	}

	public function hookpaymentReturn( $params ) {
		if ( ! $this->active || ! isset( $params['objOrder'] ) || $params['objOrder']->module != $this->name ) {
			return false;
		}

		if ( isset( $params['objOrder'] ) && Validate::isLoadedObject( $params['objOrder'] ) && isset( $params['objOrder']->valid ) && isset( $params['objOrder']->reference ) ) {
			$this->smarty->assign(
				'paylike_order',
				array(
					'id'        => $params['objOrder']->id,
					'reference' => $params['objOrder']->reference,
					'valid'     => $params['objOrder']->valid
				)
			);

			return $this->display( __FILE__, 'views/templates/hook/payment-return.tpl' );
		}
	}

	public function storeTransactionID( $paylike_id_transaction, $order_id, $total, $captured = 'NO' ) {
		$query = 'INSERT INTO ' . _DB_PREFIX_ . 'paylike_admin (`paylike_tid`, `order_id`, `payed_amount`, `payed_at`, `captured`) VALUES ("' . pSQL( $paylike_id_transaction ) . '", "' . pSQL( $order_id ) . '", "' . pSQL( $total ) . '" , NOW(), "' . pSQL( $captured ) . '")';

		return Db::getInstance()->Execute( $query );
	}

	public function updateTransactionID( $paylike_id_transaction, $order_id, $fields = array() ) {
		if ( $paylike_id_transaction && $order_id && ! empty( $fields ) ) {
			$fieldsStr  = '';
			$fieldCount = count( $fields );
			$counter    = 0;

			foreach ( $fields as $field => $value ) {
				$counter ++;
				$fieldsStr .= '`' . pSQL( $field ). '` = "' . pSQL( $value ) . '"';

				if ( $counter < $fieldCount ) {
					$fieldsStr .= ', ';
				}
			}

			$query = 'UPDATE ' . _DB_PREFIX_ . 'paylike_admin SET ' . $fieldsStr . ' WHERE `paylike_tid`="' . pSQL( $paylike_id_transaction ). '" AND `order_id`="' . (int)$order_id . '"';

			return Db::getInstance()->Execute( $query );
		} else {
			return false;
		}
	}

	/**
	 * Return the number that should be used to compute cents from the total amount
	 *
	 * @param $currency_iso_code
	 *
	 * @return int|number
	 */
	public function getPaylikeCurrencyMultiplier( $currency_iso_code ) {
		$currency = $this->getPaylikeCurrency( $currency_iso_code );
		if ( isset( $currency['exponent'] ) ) {
			return pow( 10, $currency['exponent'] );
		} else {
			return pow( 10, 2 );
		}
	}

	/**
	 * Get Paylike amount to pay
	 *
	 * @param float $total Amount due.
	 * @param       $currency_iso_code
	 *
	 * @return float|int
	 */
	public function getPaylikeAmount( $total, $currency_iso_code ) {
		$multiplier = $this->getPaylikeCurrencyMultiplier( $currency_iso_code );
		$amount     = ceil( $total * $multiplier ); // round to make sure we are always minor units.
		// if ( function_exists( 'bcmul' ) ) {
		// 	$amount = ceil( bcmul( $total, $multiplier ) );
		// }

		return $amount;
	}

	public function getPaylikeCurrency( $currency_iso_code ) {
		$currencies = array(
			'AED' =>
				array(
					'code'     => 'AED',
					'currency' => 'United Arab Emirates dirham',
					'numeric'  => '784',
					'exponent' => 2,
				),
			'AFN' =>
				array(
					'code'     => 'AFN',
					'currency' => 'Afghan afghani',
					'numeric'  => '971',
					'exponent' => 2,
				),
			'ALL' =>
				array(
					'code'     => 'ALL',
					'currency' => 'Albanian lek',
					'numeric'  => '008',
					'exponent' => 2,
				),
			'AMD' =>
				array(
					'code'     => 'AMD',
					'currency' => 'Armenian dram',
					'numeric'  => '051',
					'exponent' => 2,
				),
			'ANG' =>
				array(
					'code'     => 'ANG',
					'currency' => 'Netherlands Antillean guilder',
					'numeric'  => '532',
					'exponent' => 2,
				),
			'AOA' =>
				array(
					'code'     => 'AOA',
					'currency' => 'Angolan kwanza',
					'numeric'  => '973',
					'exponent' => 2,
				),
			'ARS' =>
				array(
					'code'     => 'ARS',
					'currency' => 'Argentine peso',
					'numeric'  => '032',
					'exponent' => 2,
				),
			'AUD' =>
				array(
					'code'     => 'AUD',
					'currency' => 'Australian dollar',
					'numeric'  => '036',
					'exponent' => 2,
				),
			'AWG' =>
				array(
					'code'     => 'AWG',
					'currency' => 'Aruban florin',
					'numeric'  => '533',
					'exponent' => 2,
				),
			'AZN' =>
				array(
					'code'     => 'AZN',
					'currency' => 'Azerbaijani manat',
					'numeric'  => '944',
					'exponent' => 2,
				),
			'BAM' =>
				array(
					'code'     => 'BAM',
					'currency' => 'Bosnia and Herzegovina convertible mark',
					'numeric'  => '977',
					'exponent' => 2,
				),
			'BBD' =>
				array(
					'code'     => 'BBD',
					'currency' => 'Barbados dollar',
					'numeric'  => '052',
					'exponent' => 2,
				),
			'BDT' =>
				array(
					'code'     => 'BDT',
					'currency' => 'Bangladeshi taka',
					'numeric'  => '050',
					'exponent' => 2,
				),
			'BGN' =>
				array(
					'code'     => 'BGN',
					'currency' => 'Bulgarian lev',
					'numeric'  => '975',
					'exponent' => 2,
				),
			'BHD' =>
				array(
					'code'     => 'BHD',
					'currency' => 'Bahraini dinar',
					'numeric'  => '048',
					'exponent' => 3,
				),
			'BIF' =>
				array(
					'code'     => 'BIF',
					'currency' => 'Burundian franc',
					'numeric'  => '108',
					'exponent' => 0,
				),
			'BMD' =>
				array(
					'code'     => 'BMD',
					'currency' => 'Bermudian dollar',
					'numeric'  => '060',
					'exponent' => 2,
				),
			'BND' =>
				array(
					'code'     => 'BND',
					'currency' => 'Brunei dollar',
					'numeric'  => '096',
					'exponent' => 2,
				),
			'BOB' =>
				array(
					'code'     => 'BOB',
					'currency' => 'Boliviano',
					'numeric'  => '068',
					'exponent' => 2,
				),
			'BRL' =>
				array(
					'code'     => 'BRL',
					'currency' => 'Brazilian real',
					'numeric'  => '986',
					'exponent' => 2,
				),
			'BSD' =>
				array(
					'code'     => 'BSD',
					'currency' => 'Bahamian dollar',
					'numeric'  => '044',
					'exponent' => 2,
				),
			'BTN' =>
				array(
					'code'     => 'BTN',
					'currency' => 'Bhutanese ngultrum',
					'numeric'  => '064',
					'exponent' => 2,
				),
			'BWP' =>
				array(
					'code'     => 'BWP',
					'currency' => 'Botswana pula',
					'numeric'  => '072',
					'exponent' => 2,
				),
			'BYR' =>
				array(
					'code'     => 'BYR',
					'currency' => 'Belarusian ruble',
					'numeric'  => '974',
					'exponent' => 0,
				),
			'BZD' =>
				array(
					'code'     => 'BZD',
					'currency' => 'Belize dollar',
					'numeric'  => '084',
					'exponent' => 2,
				),
			'CAD' =>
				array(
					'code'     => 'CAD',
					'currency' => 'Canadian dollar',
					'numeric'  => '124',
					'exponent' => 2,
				),
			'CDF' =>
				array(
					'code'     => 'CDF',
					'currency' => 'Congolese franc',
					'numeric'  => '976',
					'exponent' => 2,
				),
			'CHF' =>
				array(
					'code'     => 'CHF',
					'currency' => 'Swiss franc',
					'numeric'  => '756',
					'funding'  => true,
					'exponent' => 2,
				),
			'CLP' =>
				array(
					'code'     => 'CLP',
					'currency' => 'Chilean peso',
					'numeric'  => '152',
					'exponent' => 0,
				),
			'CNY' =>
				array(
					'code'     => 'CNY',
					'currency' => 'Chinese yuan',
					'numeric'  => '156',
					'exponent' => 2,
				),
			'COP' =>
				array(
					'code'     => 'COP',
					'currency' => 'Colombian peso',
					'numeric'  => '170',
					'exponent' => 2,
				),
			'CRC' =>
				array(
					'code'     => 'CRC',
					'currency' => 'Costa Rican colon',
					'numeric'  => '188',
					'exponent' => 2,
				),
			'CUP' =>
				array(
					'code'     => 'CUP',
					'currency' => 'Cuban peso',
					'numeric'  => '192',
					'exponent' => 2,
				),
			'CVE' =>
				array(
					'code'     => 'CVE',
					'currency' => 'Cape Verde escudo',
					'numeric'  => '132',
					'exponent' => 2,
				),
			'CZK' =>
				array(
					'code'     => 'CZK',
					'currency' => 'Czech koruna',
					'numeric'  => '203',
					'exponent' => 2,
				),
			'DJF' =>
				array(
					'code'     => 'DJF',
					'currency' => 'Djiboutian franc',
					'numeric'  => '262',
					'exponent' => 0,
				),
			'DKK' =>
				array(
					'code'     => 'DKK',
					'currency' => 'Danish krone',
					'numeric'  => '208',
					'funding'  => true,
					'exponent' => 2,
				),
			'DOP' =>
				array(
					'code'     => 'DOP',
					'currency' => 'Dominican peso',
					'numeric'  => '214',
					'exponent' => 2,
				),
			'DZD' =>
				array(
					'code'     => 'DZD',
					'currency' => 'Algerian dinar',
					'numeric'  => '012',
					'exponent' => 2,
				),
			'EGP' =>
				array(
					'code'     => 'EGP',
					'currency' => 'Egyptian pound',
					'numeric'  => '818',
					'exponent' => 2,
				),
			'ERN' =>
				array(
					'code'     => 'ERN',
					'currency' => 'Eritrean nakfa',
					'numeric'  => '232',
					'exponent' => 2,
				),
			'ETB' =>
				array(
					'code'     => 'ETB',
					'currency' => 'Ethiopian birr',
					'numeric'  => '230',
					'exponent' => 2,
				),
			'EUR' =>
				array(
					'code'     => 'EUR',
					'currency' => 'Euro',
					'numeric'  => '978',
					'funding'  => true,
					'exponent' => 2,
				),
			'FJD' =>
				array(
					'code'     => 'FJD',
					'currency' => 'Fiji dollar',
					'numeric'  => '242',
					'exponent' => 2,
				),
			'FKP' =>
				array(
					'code'     => 'FKP',
					'currency' => 'Falkland Islands pound',
					'numeric'  => '238',
					'exponent' => 2,
				),
			'GBP' =>
				array(
					'code'     => 'GBP',
					'currency' => 'Pound sterling',
					'numeric'  => '826',
					'funding'  => true,
					'exponent' => 2,
				),
			'GEL' =>
				array(
					'code'     => 'GEL',
					'currency' => 'Georgian lari',
					'numeric'  => '981',
					'exponent' => 2,
				),
			'GHS' =>
				array(
					'code'     => 'GHS',
					'currency' => 'Ghanaian cedi',
					'numeric'  => '936',
					'exponent' => 2,
				),
			'GIP' =>
				array(
					'code'     => 'GIP',
					'currency' => 'Gibraltar pound',
					'numeric'  => '292',
					'exponent' => 2,
				),
			'GMD' =>
				array(
					'code'     => 'GMD',
					'currency' => 'Gambian dalasi',
					'numeric'  => '270',
					'exponent' => 2,
				),
			'GNF' =>
				array(
					'code'     => 'GNF',
					'currency' => 'Guinean franc',
					'numeric'  => '324',
					'exponent' => 0,
				),
			'GTQ' =>
				array(
					'code'     => 'GTQ',
					'currency' => 'Guatemalan quetzal',
					'numeric'  => '320',
					'exponent' => 2,
				),
			'GYD' =>
				array(
					'code'     => 'GYD',
					'currency' => 'Guyanese dollar',
					'numeric'  => '328',
					'exponent' => 2,
				),
			'HKD' =>
				array(
					'code'     => 'HKD',
					'currency' => 'Hong Kong dollar',
					'numeric'  => '344',
					'exponent' => 2,
				),
			'HNL' =>
				array(
					'code'     => 'HNL',
					'currency' => 'Honduran lempira',
					'numeric'  => '340',
					'exponent' => 2,
				),
			'HRK' =>
				array(
					'code'     => 'HRK',
					'currency' => 'Croatian kuna',
					'numeric'  => '191',
					'exponent' => 2,
				),
			'HTG' =>
				array(
					'code'     => 'HTG',
					'currency' => 'Haitian gourde',
					'numeric'  => '332',
					'exponent' => 2,
				),
			'HUF' =>
				array(
					'code'     => 'HUF',
					'currency' => 'Hungarian forint',
					'numeric'  => '348',
					'funding'  => true,
					'exponent' => 2,
				),
			'IDR' =>
				array(
					'code'     => 'IDR',
					'currency' => 'Indonesian rupiah',
					'numeric'  => '360',
					'exponent' => 2,
				),
			'ILS' =>
				array(
					'code'     => 'ILS',
					'currency' => 'Israeli new shekel',
					'numeric'  => '376',
					'exponent' => 2,
				),
			'INR' =>
				array(
					'code'     => 'INR',
					'currency' => 'Indian rupee',
					'numeric'  => '356',
					'exponent' => 2,
				),
			'IQD' =>
				array(
					'code'     => 'IQD',
					'currency' => 'Iraqi dinar',
					'numeric'  => '368',
					'exponent' => 3,
				),
			'IRR' =>
				array(
					'code'     => 'IRR',
					'currency' => 'Iranian rial',
					'numeric'  => '364',
					'exponent' => 2,
				),
			'ISK' =>
				array(
					'code'     => 'ISK',
					'currency' => 'Icelandic króna',
					'numeric'  => '352',
					'exponent' => 2,
				),
			'JMD' =>
				array(
					'code'     => 'JMD',
					'currency' => 'Jamaican dollar',
					'numeric'  => '388',
					'exponent' => 2,
				),
			'JOD' =>
				array(
					'code'     => 'JOD',
					'currency' => 'Jordanian dinar',
					'numeric'  => '400',
					'exponent' => 3,
				),
			'JPY' =>
				array(
					'code'     => 'JPY',
					'currency' => 'Japanese yen',
					'numeric'  => '392',
					'exponent' => 0,
				),
			'KES' =>
				array(
					'code'     => 'KES',
					'currency' => 'Kenyan shilling',
					'numeric'  => '404',
					'exponent' => 2,
				),
			'KGS' =>
				array(
					'code'     => 'KGS',
					'currency' => 'Kyrgyzstani som',
					'numeric'  => '417',
					'exponent' => 2,
				),
			'KHR' =>
				array(
					'code'     => 'KHR',
					'currency' => 'Cambodian riel',
					'numeric'  => '116',
					'exponent' => 2,
				),
			'KMF' =>
				array(
					'code'     => 'KMF',
					'currency' => 'Comoro franc',
					'numeric'  => '174',
					'exponent' => 0,
				),
			'KPW' =>
				array(
					'code'     => 'KPW',
					'currency' => 'North Korean won',
					'numeric'  => '408',
					'exponent' => 2,
				),
			'KRW' =>
				array(
					'code'     => 'KRW',
					'currency' => 'South Korean won',
					'numeric'  => '410',
					'exponent' => 0,
				),
			'KWD' =>
				array(
					'code'     => 'KWD',
					'currency' => 'Kuwaiti dinar',
					'numeric'  => '414',
					'exponent' => 3,
				),
			'KYD' =>
				array(
					'code'     => 'KYD',
					'currency' => 'Cayman Islands dollar',
					'numeric'  => '136',
					'exponent' => 2,
				),
			'KZT' =>
				array(
					'code'     => 'KZT',
					'currency' => 'Kazakhstani tenge',
					'numeric'  => '398',
					'exponent' => 2,
				),
			'LAK' =>
				array(
					'code'     => 'LAK',
					'currency' => 'Lao kip',
					'numeric'  => '418',
					'exponent' => 2,
				),
			'LBP' =>
				array(
					'code'     => 'LBP',
					'currency' => 'Lebanese pound',
					'numeric'  => '422',
					'exponent' => 2,
				),
			'LKR' =>
				array(
					'code'     => 'LKR',
					'currency' => 'Sri Lankan rupee',
					'numeric'  => '144',
					'exponent' => 2,
				),
			'LRD' =>
				array(
					'code'     => 'LRD',
					'currency' => 'Liberian dollar',
					'numeric'  => '430',
					'exponent' => 2,
				),
			'LSL' =>
				array(
					'code'     => 'LSL',
					'currency' => 'Lesotho loti',
					'numeric'  => '426',
					'exponent' => 2,
				),
			'MAD' =>
				array(
					'code'     => 'MAD',
					'currency' => 'Moroccan dirham',
					'numeric'  => '504',
					'exponent' => 2,
				),
			'MDL' =>
				array(
					'code'     => 'MDL',
					'currency' => 'Moldovan leu',
					'numeric'  => '498',
					'exponent' => 2,
				),
			'MGA' =>
				array(
					'code'     => 'MGA',
					'currency' => 'Malagasy ariary',
					'numeric'  => '969',
					'exponent' => 2,
				),
			'MKD' =>
				array(
					'code'     => 'MKD',
					'currency' => 'Macedonian denar',
					'numeric'  => '807',
					'exponent' => 2,
				),
			'MMK' =>
				array(
					'code'     => 'MMK',
					'currency' => 'Myanmar kyat',
					'numeric'  => '104',
					'exponent' => 2,
				),
			'MNT' =>
				array(
					'code'     => 'MNT',
					'currency' => 'Mongolian tögrög',
					'numeric'  => '496',
					'exponent' => 2,
				),
			'MOP' =>
				array(
					'code'     => 'MOP',
					'currency' => 'Macanese pataca',
					'numeric'  => '446',
					'exponent' => 2,
				),
			'MRU' =>
				array(
					'code'     => 'MRU',
					'currency' => 'Mauritanian ouguiya',
					'numeric'  => '929',
					'exponent' => 2,
				),
			'MUR' =>
				array(
					'code'     => 'MUR',
					'currency' => 'Mauritian rupee',
					'numeric'  => '480',
					'exponent' => 2,
				),
			'MVR' =>
				array(
					'code'     => 'MVR',
					'currency' => 'Maldivian rufiyaa',
					'numeric'  => '462',
					'exponent' => 2,
				),
			'MWK' =>
				array(
					'code'     => 'MWK',
					'currency' => 'Malawian kwacha',
					'numeric'  => '454',
					'exponent' => 2,
				),
			'MXN' =>
				array(
					'code'     => 'MXN',
					'currency' => 'Mexican peso',
					'numeric'  => '484',
					'exponent' => 2,
				),
			'MYR' =>
				array(
					'code'     => 'MYR',
					'currency' => 'Malaysian ringgit',
					'numeric'  => '458',
					'exponent' => 2,
				),
			'MZN' =>
				array(
					'code'     => 'MZN',
					'currency' => 'Mozambican metical',
					'numeric'  => '943',
					'exponent' => 2,
				),
			'NAD' =>
				array(
					'code'     => 'NAD',
					'currency' => 'Namibian dollar',
					'numeric'  => '516',
					'exponent' => 2,
				),
			'NGN' =>
				array(
					'code'     => 'NGN',
					'currency' => 'Nigerian naira',
					'numeric'  => '566',
					'exponent' => 2,
				),
			'NIO' =>
				array(
					'code'     => 'NIO',
					'currency' => 'Nicaraguan córdoba',
					'numeric'  => '558',
					'exponent' => 2,
				),
			'NOK' =>
				array(
					'code'     => 'NOK',
					'currency' => 'Norwegian krone',
					'numeric'  => '578',
					'funding'  => true,
					'exponent' => 2,
				),
			'NPR' =>
				array(
					'code'     => 'NPR',
					'currency' => 'Nepalese rupee',
					'numeric'  => '524',
					'exponent' => 2,
				),
			'NZD' =>
				array(
					'code'     => 'NZD',
					'currency' => 'New Zealand dollar',
					'numeric'  => '554',
					'exponent' => 2,
				),
			'OMR' =>
				array(
					'code'     => 'OMR',
					'currency' => 'Omani rial',
					'numeric'  => '512',
					'exponent' => 3,
				),
			'PAB' =>
				array(
					'code'     => 'PAB',
					'currency' => 'Panamanian balboa',
					'numeric'  => '590',
					'exponent' => 2,
				),
			'PEN' =>
				array(
					'code'     => 'PEN',
					'currency' => 'Peruvian Sol',
					'numeric'  => '604',
					'exponent' => 2,
				),
			'PGK' =>
				array(
					'code'     => 'PGK',
					'currency' => 'Papua New Guinean kina',
					'numeric'  => '598',
					'exponent' => 2,
				),
			'PHP' =>
				array(
					'code'     => 'PHP',
					'currency' => 'Philippine peso',
					'numeric'  => '608',
					'exponent' => 2,
				),
			'PKR' =>
				array(
					'code'     => 'PKR',
					'currency' => 'Pakistani rupee',
					'numeric'  => '586',
					'exponent' => 2,
				),
			'PLN' =>
				array(
					'code'     => 'PLN',
					'currency' => 'Polish złoty',
					'numeric'  => '985',
					'funding'  => true,
					'exponent' => 2,
				),
			'PYG' =>
				array(
					'code'     => 'PYG',
					'currency' => 'Paraguayan guaraní',
					'numeric'  => '600',
					'exponent' => 0,
				),
			'QAR' =>
				array(
					'code'     => 'QAR',
					'currency' => 'Qatari riyal',
					'numeric'  => '634',
					'exponent' => 2,
				),
			'RON' =>
				array(
					'code'     => 'RON',
					'currency' => 'Romanian leu',
					'numeric'  => '946',
					'funding'  => true,
					'exponent' => 2,
				),
			'RSD' =>
				array(
					'code'     => 'RSD',
					'currency' => 'Serbian dinar',
					'numeric'  => '941',
					'exponent' => 2,
				),
			'RUB' =>
				array(
					'code'     => 'RUB',
					'currency' => 'Russian ruble',
					'numeric'  => '643',
					'exponent' => 2,
				),
			'RWF' =>
				array(
					'code'     => 'RWF',
					'currency' => 'Rwandan franc',
					'numeric'  => '646',
					'exponent' => 0,
				),
			'SAR' =>
				array(
					'code'     => 'SAR',
					'currency' => 'Saudi riyal',
					'numeric'  => '682',
					'exponent' => 2,
				),
			'SBD' =>
				array(
					'code'     => 'SBD',
					'currency' => 'Solomon Islands dollar',
					'numeric'  => '090',
					'exponent' => 2,
				),
			'SCR' =>
				array(
					'code'     => 'SCR',
					'currency' => 'Seychelles rupee',
					'numeric'  => '690',
					'exponent' => 2,
				),
			'SDG' =>
				array(
					'code'     => 'SDG',
					'currency' => 'Sudanese pound',
					'numeric'  => '938',
					'exponent' => 2,
				),
			'SEK' =>
				array(
					'code'     => 'SEK',
					'currency' => 'Swedish krona',
					'numeric'  => '752',
					'funding'  => true,
					'exponent' => 2,
				),
			'SGD' =>
				array(
					'code'     => 'SGD',
					'currency' => 'Singapore dollar',
					'numeric'  => '702',
					'exponent' => 2,
				),
			'SHP' =>
				array(
					'code'     => 'SHP',
					'currency' => 'Saint Helena pound',
					'numeric'  => '654',
					'exponent' => 2,
				),
			'SLL' =>
				array(
					'code'     => 'SLL',
					'currency' => 'Sierra Leonean leone',
					'numeric'  => '694',
					'exponent' => 2,
				),
			'SOS' =>
				array(
					'code'     => 'SOS',
					'currency' => 'Somali shilling',
					'numeric'  => '706',
					'exponent' => 2,
				),
			'SRD' =>
				array(
					'code'     => 'SRD',
					'currency' => 'Surinamese dollar',
					'numeric'  => '968',
					'exponent' => 2,
				),
			'STN' =>
				array(
					'code'     => 'STN',
					'currency' => 'São Tomé and Príncipe dobra',
					'numeric'  => '930',
					'exponent' => 2,
				),
			'SYP' =>
				array(
					'code'     => 'SYP',
					'currency' => 'Syrian pound',
					'numeric'  => '760',
					'exponent' => 2,
				),
			'SZL' =>
				array(
					'code'     => 'SZL',
					'currency' => 'Swazi lilangeni',
					'numeric'  => '748',
					'exponent' => 2,
				),
			'THB' =>
				array(
					'code'     => 'THB',
					'currency' => 'Thai baht',
					'numeric'  => '764',
					'exponent' => 2,
				),
			'TJS' =>
				array(
					'code'     => 'TJS',
					'currency' => 'Tajikistani somoni',
					'numeric'  => '972',
					'exponent' => 2,
				),
			'TMT' =>
				array(
					'code'     => 'TMT',
					'currency' => 'Turkmenistani manat',
					'numeric'  => '934',
					'exponent' => 2,
				),
			'TND' =>
				array(
					'code'     => 'TND',
					'currency' => 'Tunisian dinar',
					'numeric'  => '788',
					'exponent' => 3,
				),
			'TOP' =>
				array(
					'code'     => 'TOP',
					'currency' => 'Tongan paʻanga',
					'numeric'  => '776',
					'exponent' => 2,
				),
			'TRY' =>
				array(
					'code'     => 'TRY',
					'currency' => 'Turkish lira',
					'numeric'  => '949',
					'exponent' => 2,
				),
			'TTD' =>
				array(
					'code'     => 'TTD',
					'currency' => 'Trinidad and Tobago dollar',
					'numeric'  => '780',
					'exponent' => 2,
				),
			'TWD' =>
				array(
					'code'     => 'TWD',
					'currency' => 'New Taiwan dollar',
					'numeric'  => '901',
					'exponent' => 2,
				),
			'TZS' =>
				array(
					'code'     => 'TZS',
					'currency' => 'Tanzanian shilling',
					'numeric'  => '834',
					'exponent' => 2,
				),
			'UAH' =>
				array(
					'code'     => 'UAH',
					'currency' => 'Ukrainian hryvnia',
					'numeric'  => '980',
					'exponent' => 2,
				),
			'UGX' =>
				array(
					'code'     => 'UGX',
					'currency' => 'Ugandan shilling',
					'numeric'  => '800',
					'exponent' => 0,
				),
			'USD' =>
				array(
					'code'     => 'USD',
					'currency' => 'United States dollar',
					'numeric'  => '840',
					'funding'  => true,
					'exponent' => 2,
				),
			'UYU' =>
				array(
					'code'     => 'UYU',
					'currency' => 'Uruguayan peso',
					'numeric'  => '858',
					'exponent' => 2,
				),
			'UZS' =>
				array(
					'code'     => 'UZS',
					'currency' => 'Uzbekistan som',
					'numeric'  => '860',
					'exponent' => 2,
				),
			'VES' =>
				array(
					'code'     => 'VEF',
					'currency' => 'Venezuelan bolívar',
					'numeric'  => '928',
					'exponent' => 2,
				),
			'VND' =>
				array(
					'code'     => 'VND',
					'currency' => 'Vietnamese dong',
					'numeric'  => '704',
					'exponent' => 0,
				),
			'VUV' =>
				array(
					'code'     => 'VUV',
					'currency' => 'Vanuatu vatu',
					'numeric'  => '548',
					'exponent' => 0,
				),
			'WST' =>
				array(
					'code'     => 'WST',
					'currency' => 'Samoan tala',
					'numeric'  => '882',
					'exponent' => 2,
				),
			'XAF' =>
				array(
					'code'     => 'XAF',
					'currency' => 'CFA franc BEAC',
					'numeric'  => '950',
					'exponent' => 0,
				),
			'XCD' =>
				array(
					'code'     => 'XCD',
					'currency' => 'East Caribbean dollar',
					'numeric'  => '951',
					'exponent' => 2,
				),
			'XOF' =>
				array(
					'code'     => 'XOF',
					'currency' => 'CFA franc BCEAO',
					'numeric'  => '952',
					'exponent' => 0,
				),
			'XPF' =>
				array(
					'code'     => 'XPF',
					'currency' => 'CFP franc',
					'numeric'  => '953',
					'exponent' => 0,
				),
			'YER' =>
				array(
					'code'     => 'YER',
					'currency' => 'Yemeni rial',
					'numeric'  => '886',
					'exponent' => 2,
				),
			'ZAR' =>
				array(
					'code'     => 'ZAR',
					'currency' => 'South African rand',
					'numeric'  => '710',
					'exponent' => 2,
				),
			'ZMK' =>
				array(
					'code'     => 'ZMK',
					'currency' => 'Zambian kwacha',
					'numeric'  => '894',
					'exponent' => 2,
				),
			'ZWL' =>
				array(
					'code'     => 'ZWL',
					'currency' => 'Zimbabwean dollar',
					'numeric'  => '716',
					'exponent' => 2,
				),
		);
		if ( isset( $currencies[ $currency_iso_code ] ) ) {
			return $currencies[ $currency_iso_code ];
		} else {
			return null;
		}
	}

	public function hookDisplayAdminOrder( $params ) {
		$id_order = $params['id_order'];
		$order    = new Order( (int) $id_order );
		if ( $order->module == $this->name ) {
			$order_token        = Tools::getAdminToken( 'AdminOrders' . (int) Tab::getIdFromClassName( 'AdminOrders' ) . (int) $this->context->employee->id );
			$payliketransaction = Db::getInstance()->getRow( 'SELECT * FROM ' . _DB_PREFIX_ . 'paylike_admin WHERE order_id = ' . (int) $id_order );
			$this->context->smarty->assign( array(
				'ps_version'          => _PS_VERSION_,
				'id_order'            => $id_order,
				'order_token'         => $order_token,
				'payliketransaction'  => $payliketransaction,
				'not_captured_text'	  => $this->l('Captured Transaction prior to Refund via Paylike.'),
				'checkbox_text' 	  => $this->l("Refund Paylike")
			) );

			return $this->display( __FILE__, 'views/templates/hook/admin-order.tpl' );
		}
	}

	public function dispErrors( $string = 'Fatal error', $htmlentities = true, Context $context = null ) {
		if ( true ) {
			return ( Tools::htmlentitiesUTF8( 'Paylike: ' . Tools::stripslashes( $string ) ) . '<br/>' );
		}

		return Context::getContext()->getTranslator()->trans( 'Fatal error', array(), 'Admin.Notifications.Error' );
	}

	public function hookActionOrderSlipAdd( $params ){
		/* Check if "Refund Paylike" checkbox is checked */
		if (Tools::isSubmit('doRefundPaylike')) {
			$id_order = $params['order']->id;
			$amount = 0;

			/* Calculate total amount */
			foreach ($params['productList'] as $product) {
				$amount += floatval($product['amount']);
			}

			/* Add shipping to total */
			if (Tools::getValue('partialRefundShippingCost')) {
				$amount += floatval(Tools::getValue('partialRefundShippingCost'));
			}

			/* For prestashop version > 1.7.7 */
			if  ($refundData = \Tools::getValue('cancel_product')) {
				$shipping_amount = floatval(str_replace(',', '.', $refundData['shipping_amount']));
				if(isset($refundData['shipping']) && $refundData['shipping']==1 && $shipping_amount == 0){
					$shipping_amount = floatval(str_replace(',', '.', $params['order']->total_shipping));
				}
				$amount += $shipping_amount;
			}

			/* Init Paylike action */
			$response = $this->doPaylikeAction($id_order,"refund",false,$amount);
			PrestaShopLogger::addLog( $response['message'] );

			/* Add response to cookies  */
			if(isset($response['error']) && $response['error'] == '1'){
				$this->context->cookie->__set('response_error', $response['message']);
				$this->context->cookie->write();
			}elseif(isset($response['warning']) && $response['warning'] == '1'){
				$this->context->cookie->__set('response_warnings', $response['message']);
				$this->context->cookie->write();
			}else{
				$this->context->cookie->__set('response_confirmations', $response['message']);
				$this->context->cookie->write();
			}
		}
	}

	public function hookActionOrderStatusPostUpdate( $params ) {
		$order_state = $params['newOrderStatus'];
		$id_order    = $params['id_order'];

		/* Skip if no Paylike transaction */
		$payliketransaction = Db::getInstance()->getRow( 'SELECT * FROM ' . _DB_PREFIX_ . 'paylike_admin WHERE order_id = ' . (int) $id_order );
		if ( empty( $payliketransaction ) ) {
			return false;
		}
	
		/* If Capture or Void */
		if ( $order_state->id == (int) Configuration::get( 'PAYLIKE_ORDER_STATUS' ) || $order_state->id == (int) Configuration::get( 'PS_OS_CANCELED' ) ) {
			/* If custom Captured status  */
			if ( $order_state->id == (int) Configuration::get( 'PAYLIKE_ORDER_STATUS' ) ) {
				$response = $this->doPaylikeAction($id_order,"capture");
			}
			
			/* If Canceled status */
			if ( $order_state->id == (int) Configuration::get( 'PS_OS_CANCELED' ) ) {
				$response = $this->doPaylikeAction($id_order,"void");	
			}
			
			/* Log response */
			PrestaShopLogger::addLog( $response['message'] );

			/* Add response to cookies  */
			if(isset($response['error']) && $response['error'] == '1'){
				$this->context->cookie->__set('response_error', $response['message']);
				$this->context->cookie->write();
			}elseif(isset($response['warning']) && $response['warning'] == '1'){
				$this->context->cookie->__set('response_warnings', $response['message']);
				$this->context->cookie->write();
			}else{
				$this->context->cookie->__set('response_confirmations', $response['message']);
				$this->context->cookie->write();
			}
		}
	}

	public function hookBackOfficeHeader() {
		if ($this->context->cookie->__isset('response_error')) {
			/** Display persistent */
			$this->context->controller->errors[] = '<p>'.$this->context->cookie->__get('response_error').'</p>';
			/** Clean persistent error */
			$this->context->cookie->__unset('response_error');
		}

		if ($this->context->cookie->__isset('response_warnings')) {
			/** Display persistent */
			$this->context->controller->warnings[] = '<p>'.$this->context->cookie->__get('response_warnings').'</p>';
			/** Clean persistent */
			$this->context->cookie->__unset('response_warnings');
		}

		if ($this->context->cookie->__isset('response_confirmations')) {
			/** Display persistent */
			$this->context->controller->confirmations[] = '<p>'.$this->context->cookie->__get('response_confirmations').'</p>';
			/** Clean persistent */
			$this->context->cookie->__unset('response_confirmations');
		}

		if ( Tools::getIsset( 'vieworder' ) && Tools::getIsset( 'id_order' ) && Tools::getIsset( 'paylike_action' ) ) {
			$paylike_action = Tools::getValue( 'paylike_action' );
			$id_order = (int) Tools::getValue( 'id_order' );
			$response = $this->doPaylikeAction($id_order,$paylike_action,true,Tools::getValue( 'paylike_amount_to_refund' ));
			die( Tools::jsonEncode( $response ) );
		}
		
		if ( Tools::getIsset( 'upload_logo' ) ) {
			$logo_name = Tools::getValue( 'logo_name' );

			if ( empty( $logo_name ) ) {
				$response = array(
					'status'  => 0,
					'message' => 'Please give logo name.'
				);
				die( Tools::jsonEncode( $response ) );
			}

			$logo_slug = Tools::strtolower( str_replace( ' ', '-', $logo_name ) );
			$sql       = new DbQuery();
			$sql->select( '*' );
			$sql->from( 'paylike_logos', 'PL' );
			$sql->where( 'PL.slug = "' . pSQL($logo_slug) . '"' );
			$logos = Db::getInstance()->executes( $sql );
			if ( ! empty( $logos ) ) {
				$response = array(
					'status'  => 0,
					'message' => 'This name already exists.'
				);
				die( Tools::jsonEncode( $response ) );
			}

			if ( ! empty( $_FILES['logo_file']['name'] ) ) {
				$target_dir    = _PS_MODULE_DIR_ . $this->name . '/views/img/';
				$name          = basename( $_FILES['logo_file']["name"] );
				$path_parts    = pathinfo( $name );
				$extension     = $path_parts['extension'];
				$file_name     = $logo_slug . '.' . $extension;
				$target_file   = $target_dir . basename( $file_name );
				$imageFileType = pathinfo( $target_file, PATHINFO_EXTENSION );

				/*$check = getimagesize($_FILES['logo_file']["tmp_name"]);
                if($check === false) {
                    $response = array(
                        'status' => 0,
                        'message' => 'File is not an image. Please upload JPG, JPEG, PNG or GIF file.'
                    );
                    die(Tools::jsonEncode($response));
                }*/

				// Check if file already exists
				if ( file_exists( $target_file ) ) {
					$response = array(
						'status'  => 0,
						'message' => 'Sorry, file already exists.'
					);
					die( Tools::jsonEncode( $response ) );
				}

				// Allow certain file formats
				if ( $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
				     && $imageFileType != "gif" && $imageFileType != "svg" ) {
					$response = array(
						'status'  => 0,
						'message' => 'Sorry, only JPG, JPEG, PNG, GIF & SVG files are allowed.'
					);
					die( Tools::jsonEncode( $response ) );
				}

				if ( move_uploaded_file( $_FILES['logo_file']["tmp_name"], $target_file ) ) {
					$query = 'INSERT INTO ' . _DB_PREFIX_ . 'paylike_logos (`name`, `slug`, `file_name`, `default_logo`, `created_at`) VALUES ("' . pSQL( $logo_name ) . '", "' . pSQL( $logo_slug ) . '", "' . pSQL( $file_name ) . '", 0, NOW())';
					if ( Db::getInstance()->Execute( $query ) ) {
						$response = array(
							'status'  => 1,
							'message' => "The file " . basename( $file_name ) . " has been uploaded."
						);
						//Configuration::updateValue('PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO', basename($file_name));
						die( Tools::jsonEncode( $response ) );
					} else {
						unlink( $target_file );
						$response = array(
							'status'  => 0,
							'message' => "Oops! An error occured while save logo."
						);
						die( Tools::jsonEncode( $response ) );
					}
				} else {
					$response = array(
						'status'  => 0,
						'message' => 'Sorry, there was an error uploading your file.'
					);
					die( Tools::jsonEncode( $response ) );
				}
			} else {
				$response = array(
					'status'  => 0,
					'message' => 'Please select a file for upload.'
				);
				die( Tools::jsonEncode( $response ) );
			}
		}

		if ( Tools::getIsset( 'change_language' ) ) {
			$language_code = ( ! empty( Tools::getvalue( 'lang_code' ) ) ) ? Tools::getvalue( 'lang_code' ) : Configuration::get( 'PAYLIKE_LANGUAGE_CODE' );
			Configuration::updateValue( 'PAYLIKE_LANGUAGE_CODE', $language_code );
			$token = Tools::getAdminToken( 'AdminModules' . (int) Tab::getIdFromClassName( 'AdminModules' ) . (int) $this->context->employee->id );
			$link  = $this->context->link->getAdminLink( 'AdminModules' ) . '&token=' . $token . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
			Tools::redirectAdmin( $link );
		}

		if ( Tools::getValue( 'configure' ) == $this->name ) {
			$this->context->controller->addCSS( $this->_path . 'views/css/backoffice.css' );
		}
	}

	/**
	 * Call action via Paylike API.
	 *
	 * @param string $id_order - the order id
	 * @param string $paylike_action - the action to be called.
	 * @param boolean $change_status - change status flag, default false.
	 * @param float $paylike_amount_to_refund - the refund amount, default 0.
	 *
	 * @return mixed
	 */
	 protected function doPaylikeAction($id_order, $paylike_action, $change_status = false, $paylike_amount_to_refund = 0){
		$order              = new Order( (int) $id_order );
		$payliketransaction = Db::getInstance()->getRow( 'SELECT * FROM ' . _DB_PREFIX_ . 'paylike_admin WHERE order_id = ' . (int) $id_order );
		$transactionid      = $payliketransaction['paylike_tid'];
		Paylike\Client::setKey( Configuration::get( 'PAYLIKE_SECRET_KEY' ) );
		$fetch    			= Paylike\Transaction::fetch( $transactionid );
		$customer 			= new Customer( $order->id_customer );

		switch ( $paylike_action ) {
			case "capture":
				if ( $payliketransaction['captured'] == 'YES' ) {
					$response = array(
						'warning' => 1,
						'message' => $this->dispErrors( $this->l('Transaction already Captured.') ),
					);
				} elseif ( isset( $payliketransaction ) ) {
					$amount   = ( ! empty( $fetch['transaction']['pendingAmount'] ) ) ? (int) $fetch['transaction']['pendingAmount'] : 0;
					$currency = new Currency( (int) $order->id_currency );
					if ( $amount ) {
						/* Capture transaction */
						$data    = array(
							'currency'   => $currency->iso_code,
							'amount'     => $amount,
						);
						$capture = Paylike\Transaction::capture( $transactionid, $data );

						if ( is_array( $capture ) && ! empty( $capture['error'] ) && $capture['error'] == 1 ) {
							PrestaShopLogger::addLog( $capture['message'] );
							$response = array(
								'error'   => 1,
								'message' => $this->dispErrors( $capture['message'] ),
							);
						} else {
							if ( ! empty( $capture['transaction'] ) ) {
								//Update order status
								if($change_status){
									$order->setCurrentState( (int) Configuration::get( 'PAYLIKE_ORDER_STATUS' ), $this->context->employee->id );
								}

								/* Update transaction details */
								$fields = array(
									'captured' => 'YES',
								);
								$this->updateTransactionID( $transactionid, (int) $id_order, $fields );

								$currency_multiplier = $this->getPaylikeCurrencyMultiplier( $currency->iso_code );
								/* Set message */
								$message = 'Trx ID: ' . $transactionid . '
								Authorized Amount: ' . ( $capture['transaction']['amount'] / $currency_multiplier ) . '
								Captured Amount: ' . ( $capture['transaction']['capturedAmount'] / $currency_multiplier ) . '
								Order time: ' . $capture['transaction']['created'] . '
								Currency code: ' . $capture['transaction']['currency'];

								$message = strip_tags( $message, '<br>' );
								if ( Validate::isCleanHtml( $message ) ) {
									if ( $this->getPSV() == '1.7.2' ) {
										$id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder( $customer->email, $order->id );
										if ( ! $id_customer_thread ) {
											$customer_thread              = new CustomerThread();
											$customer_thread->id_contact  = 0;
											$customer_thread->id_customer = (int) $order->id_customer;
											$customer_thread->id_shop     = (int) $this->context->shop->id;
											$customer_thread->id_order    = (int) $order->id;
											$customer_thread->id_lang     = (int) $this->context->language->id;
											$customer_thread->email       = $customer->email;
											$customer_thread->status      = 'open';
											$customer_thread->token       = Tools::passwdGen( 12 );
											$customer_thread->add();
										} else {
											$customer_thread = new CustomerThread( (int) $id_customer_thread );
										}

										$customer_message                     = new CustomerMessage();
										$customer_message->id_customer_thread = $customer_thread->id;
										$customer_message->id_employee        = 0;
										$customer_message->message            = $message;
										$customer_message->private            = 1;

										$customer_message->add();
									} else {
										$msg              = new Message();
										$msg->message     = $message;
										$msg->id_cart     = (int) $order->id_cart;
										$msg->id_customer = (int) $order->id_customer;
										$msg->id_order    = (int) $order->id;
										$msg->private     = 1;
										$msg->add();
									}
								}

								/* Set response */
								$response = array(
									'success' => 1,
									'message' => $this->dispErrors( $this->l('Transaction successfully Captured.') ),
								);
							} else {
								if ( ! empty( $capture[0]['message'] ) ) {
									$response = array(
										'warning' => 1,
										'message' => $this->dispErrors( $capture[0]['message'] ),
									);
								} else {
									$response = array(
										'error'   => 1,
										'message' => $this->dispErrors( $this->l('Opps! An error occured while Capture.') ),
									);
								}
							}
						}
					} else {
						$response = array(
							'error'   => 1,
							'message' => $this->dispErrors( $this->l('Invalid amount to Capture.') ),
						);
					}
				} else {
					$response = array(
						'error'   => 1,
						'message' => $this->dispErrors( $this->l('Invalid Paylike Transaction.') ),
					);
				}

				break;

			case "refund":
				if ( $payliketransaction['captured'] == 'NO' ) {
					$response = array(
						'warning' => 1,
						'message' => $this->dispErrors( $this->l('You need to Captured Transaction prior to Refund.') ),
					);
				} elseif ( isset( $payliketransaction ) ) {

					$currency = new Currency( (int) $order->id_currency );

					if ( ! Validate::isPrice( $paylike_amount_to_refund ) ) {
						$response = array(
							'error'   => 1,
							'message' => $this->dispErrors( $this->l('Invalid amount to Refund.') ),
						);
					} else {
						/* Refund transaction */
						$amount              = $this->getPaylikeAmount($paylike_amount_to_refund, $currency->iso_code);
						$data                = array(
							'descriptor' => '',
							'amount'     => $amount,
						);

						$refund              = Paylike\Transaction::refund( $transactionid, $data );

						if ( is_array( $refund ) && ! empty( $refund['error'] ) && $refund['error'] == 1 ) {
							PrestaShopLogger::addLog( $refund['message'] );
							$response = array(
								'error'   => 1,
								'message' => $this->dispErrors( $refund['message'] ),
							);
						} else {
							if ( ! empty( $refund['transaction'] ) ) {
								//Update order status
								if($change_status){
									$order->setCurrentState( (int) Configuration::get( 'PS_OS_REFUND' ), $this->context->employee->id );
								}
								
								/* Update transaction details */
								$fields = array(
									'refunded_amount' => $payliketransaction['refunded_amount'] + $paylike_amount_to_refund,
								);
								$this->updateTransactionID( $transactionid, (int) $id_order, $fields );

								$currency_multiplier = $this->getPaylikeCurrencyMultiplier( $currency->iso_code );
								/* Set message */
								$message = 'Trx ID: ' . $transactionid . '
									Authorized Amount: ' . ( $refund['transaction']['amount'] / $currency_multiplier ) . '
									Refunded Amount: ' . ( $refund['transaction']['refundedAmount'] / $currency_multiplier ) . '
									Order time: ' . $refund['transaction']['created'] . '
									Currency code: ' . $refund['transaction']['currency'];

								$message = strip_tags( $message, '<br>' );
								if ( Validate::isCleanHtml( $message ) ) {
									if ( $this->getPSV() == '1.7.2' ) {
										$id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder( $customer->email, $order->id );
										if ( ! $id_customer_thread ) {
											$customer_thread              = new CustomerThread();
											$customer_thread->id_contact  = 0;
											$customer_thread->id_customer = (int) $order->id_customer;
											$customer_thread->id_shop     = (int) $this->context->shop->id;
											$customer_thread->id_order    = (int) $order->id;
											$customer_thread->id_lang     = (int) $this->context->language->id;
											$customer_thread->email       = $customer->email;
											$customer_thread->status      = 'open';
											$customer_thread->token       = Tools::passwdGen( 12 );
											$customer_thread->add();
										} else {
											$customer_thread = new CustomerThread( (int) $id_customer_thread );
										}

										$customer_message                     = new CustomerMessage();
										$customer_message->id_customer_thread = $customer_thread->id;
										$customer_message->id_employee        = 0;
										$customer_message->message            = $message;
										$customer_message->private            = 1;

										$customer_message->add();
									} else {
										$msg              = new Message();
										$msg->message     = $message;
										$msg->id_cart     = (int) $order->id_cart;
										$msg->id_customer = (int) $order->id_customer;
										$msg->id_order    = (int) $order->id;
										$msg->private     = 1;
										$msg->add();
									}
								}

								/* Set response */
								$response = array(
									'success' => 1,
									'message' => $this->dispErrors( $this->l('Transaction successfully Refunded.') ),
								);
							} else {
								if ( ! empty( $refund[0]['message'] ) ) {
									$response = array(
										'warning' => 1,
										'message' => $this->dispErrors( $refund[0]['message'] ),
									);
								} else {
									$response = array(
										'error'   => 1,
										'message' => $this->dispErrors( $this->l('Opps! An error occured while Refund.') ),
									);
								}
							}
						}
					}
				} else {
					$response = array(
						'error'   => 1,
						'message' => $this->dispErrors( $this->l('Invalid Paylike Transaction.') ),
					);
				}

				break;

			case "void":
				if ( $payliketransaction['captured'] == 'YES' ) {
					$response = array(
						'warning' => 1,
						'message' => $this->dispErrors( $this->l('You can\'t Void transaction now . It\'s already Captured, try to Refund.') ),
					);
				} elseif ( isset( $payliketransaction ) ) {
					$currency = new Currency( (int) $order->id_currency );
					/* Void transaction */
					$amount = (int) $fetch['transaction']['amount'] - $fetch['transaction']['refundedAmount'];
					$data   = array(
						'amount' => $amount,
					);
					$void   = Paylike\Transaction::void( $transactionid, $data );

					if ( is_array( $void ) && ! empty( $void['error'] ) && $void['error'] == 1 ) {
						PrestaShopLogger::addLog( $void['message'] );
						$response = array(
							'error'   => 1,
							'message' => $this->dispErrors( $void['message'] ),
						);
					} else {
						if ( ! empty( $void['transaction'] ) ) {
							//Update order status
							if($change_status){
								$order->setCurrentState( (int) Configuration::get( 'PS_OS_CANCELED' ), $this->context->employee->id );
							}
							$currency_multiplier = $this->getPaylikeCurrencyMultiplier( $currency->iso_code );
							/* Set message */
							$message = 'Trx ID: ' . $transactionid . '
									Authorized Amount: ' . ( $void['transaction']['amount'] / $currency_multiplier ) . '
									Refunded Amount: ' . ( $void['transaction']['refundedAmount'] / $currency_multiplier ) . '
									Order time: ' . $void['transaction']['created'] . '
									Currency code: ' . $void['transaction']['currency'];

							$message = strip_tags( $message, '<br>' );
							if ( Validate::isCleanHtml( $message ) ) {
								if ( $this->getPSV() == '1.7.2' ) {
									$id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder( $customer->email, $order->id );
									if ( ! $id_customer_thread ) {
										$customer_thread              = new CustomerThread();
										$customer_thread->id_contact  = 0;
										$customer_thread->id_customer = (int) $order->id_customer;
										$customer_thread->id_shop     = (int) $this->context->shop->id;
										$customer_thread->id_order    = (int) $order->id;
										$customer_thread->id_lang     = (int) $this->context->language->id;
										$customer_thread->email       = $customer->email;
										$customer_thread->status      = 'open';
										$customer_thread->token       = Tools::passwdGen( 12 );
										$customer_thread->add();
									} else {
										$customer_thread = new CustomerThread( (int) $id_customer_thread );
									}

									$customer_message                     = new CustomerMessage();
									$customer_message->id_customer_thread = $customer_thread->id;
									$customer_message->id_employee        = 0;
									$customer_message->message            = $message;
									$customer_message->private            = 1;

									$customer_message->add();
								} else {
									$msg              = new Message();
									$msg->message     = $message;
									$msg->id_cart     = (int) $order->id_cart;
									$msg->id_customer = (int) $order->id_customer;
									$msg->id_order    = (int) $order->id;
									$msg->private     = 1;
									$msg->add();
								}
							}

							/* Set response */
							$response = array(
								'success' => 1,
								'message' => $this->dispErrors( $this->l('Transaction successfully Voided.') ),
							);
						} else {
							if ( ! empty( $void[0]['message'] ) ) {
								$response = array(
									'warning' => 1,
									'message' => $this->dispErrors( $void[0]['message'] ),
								);
							} else {
								$response = array(
									'error'   => 1,
									'message' => $this->dispErrors( $this->l('Opps! An error occured while Void.') ),
								);
							}
						}
					}
				} else {
					$response = array(
						'error'   => 1,
						'message' => $this->dispErrors( $this->l('Invalid paylike transaction.') ),
					);
				}
				break;
		}
		return $response;
	}
}