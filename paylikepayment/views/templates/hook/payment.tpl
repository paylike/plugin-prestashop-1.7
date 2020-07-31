{*
* Team Paylike
*
*  @author    Team Paylike
*  @copyright Team Paylike
*  @license   MIT license: https://opensource.org/licenses/MIT
*}
{if $paylike_status == 'enabled'}
    <style type="text/css">
        .cards {
            display: inline-flex;
        }

        .cards li img {
            vertical-align: middle;
            margin-right: 10px;
            width: 37px;
            height: 27px;
        }
    </style>
    <script type="text/javascript" src="https://sdk.paylike.io/3.js"></script>
    <script>
        var PAYLIKE_PUBLIC_KEY = "{$PAYLIKE_PUBLIC_KEY|escape:'htmlall':'UTF-8'}";
        var paylike = Paylike(PAYLIKE_PUBLIC_KEY);
        var shop_name = "{$shop_name|escape:'htmlall':'UTF-8'}";
        var PS_SSL_ENABLED = "{$PS_SSL_ENABLED|escape:'htmlall':'UTF-8'}";
        var host = "{$http_host|escape:'htmlall':'UTF-8'}";
        var BASE_URI = "{$base_uri|escape:'htmlall':'UTF-8'}";
        var popup_title = "{$popup_title|escape:'htmlall':'UTF-8'}";
        var popup_description = "{$popup_description}";
        var currency_code = "{$currency_code|escape:'htmlall':'UTF-8'}";
        var amount = "{$amount|escape:'htmlall':'UTF-8'}";
        var products = "{$products}"; //html variable can not be escaped;
        products = JSON.parse(products.replace(/&quot;/g, '"'));
        var name = "{$name|escape:'htmlall':'UTF-8'}";
        var email = "{$email|escape:'htmlall':'UTF-8'}";
        var telephone = "{$telephone|escape:'htmlall':'UTF-8'}";
        var address = "{$address|escape:'htmlall':'UTF-8'}";
        var ip = "{$ip|escape:'htmlall':'UTF-8'}";
        var locale = "{$locale|escape:'htmlall':'UTF-8'}";
        var platform_version = "{$platform_version|escape:'htmlall':'UTF-8'}";
        var ecommerce = "{$ecommerce|escape:'htmlall':'UTF-8'}";
        var module_version = "{$module_version|escape:'htmlall':'UTF-8'}";
        var url_controller = "{$redirect_url|escape:'htmlall':'UTF-8'}";
        var pay_text = "{l s='Pay' mod='paylikepayment' js=1}";
        var qry_str = "{$qry_str}";

        console.log(products);

        function pay() {
            paylike.popup({
                    title: popup_title,
                    currency: currency_code,
                    amount: amount,
                    description: popup_description,
                    locale: locale,
                    custom: {
                        products: products,
                        customer: {
                            name: name,
                            email: email,
                            phoneNo: telephone,
                            address: address,
                            IP: ip
                        },
                        platform: {
                            name: 'Prestashop',
                            version: platform_version
                        },
                        PaylikePluginVersion: module_version
                    }
                },
                function (err, r) {
                    if (typeof r !== 'undefined') {
                        var return_url = url_controller + qry_str + 'transactionid=' + r.transaction.id;
                        if (err) {
                            return console.warn(err);
                        }
                        location.href = htmlDecode(return_url);
                    }
                });
            ifCheckedUncheck();
        }

        function htmlDecode(url) {
            return String(url).replace(/&amp;/g, '&');
        }


        ////////////////////////////////////////////

        function ifCheckedUncheck() {
            $('#conditions-to-approve input[type="checkbox"]').not(this).prop('checked', false);

        }

        function bindTermsCheck() {
            $('#conditions-to-approve input[type="checkbox"]').change(function () {
                var $paymentConfirmation = $('#payment-confirmation');
                if ($(this).prop("checked") == true) {
                    $paymentConfirmation.find("div").removeClass('disabled').addClass('active');
                    $paymentConfirmation.find("button").removeClass('disabled').addClass('active');
                } else {
                    $paymentConfirmation.find("div").removeClass('active').addClass('disabled');
                    $paymentConfirmation.find("button").removeClass('active').addClass('disabled');
                }
            });
        }

        function bindPaymentMethodsClick() {
            var paymentMethodsAll = document.querySelectorAll('.payment-option');
            if (!paymentMethodsAll) return false;

            for (var x = 0; x < paymentMethodsAll.length; x++) {
                paymentMethodsAll[x].addEventListener("click", function (e) {
                    maybeBindPaylikePopup();
                });
            }
            $()
        }

        function bindPaylkePopup() {
            $('#pay-by-paylike').on('click', function (e) {
                e.preventDefault();
                if (!$('#conditions-to-approve input[type="checkbox"]:checked').length) return false;
                pay();
            });
        }

        function maybeBindPaylikePopup() {
            var paymentMethod = document.querySelector('input[name="payment-option"]:checked');
            if (!paymentMethod) return false;
            var $payButton = $('#pay-by-paylike');
            var $submitButton = $('#payment-confirmation button');
            // uncheck terms checkbox
            ifCheckedUncheck();
            // if payment method is not paylike add the buttons back
            if (paymentMethod.dataset.moduleName !== 'paylikepayment') {
                $submitButton.removeClass('hide-element');
                $payButton.addClass('hide-element');
            } else {
                if (!$payButton.length) {
                    $submitButton.after('<div ' +
                        'style="-webkit-appearance: none; background-color: #2fb5d2;" ' +
                        'class="btn btn-primary center-block disabled " id="pay-by-paylike">' + pay_text + '</div>');
                    bindPaylkePopup();
                }
                $submitButton.addClass('hide-element');
                $payButton.removeClass('hide-element');

            }
        }

        document.addEventListener(“DOMContentLoaded”, function(event) {
            bindPaymentMethodsClick();
            maybeBindPaylikePopup();
            bindPaylkePopup();
            bindTermsCheck();
        })

        ////////////////////////////////////////////


    </script>
    {*<div class="row">
        <div class="col-xs-12">
            <p class="payment_module paylike" onclick="pay();">
                <span class="paylike_text">{l s='Pay with credit card' mod='paylikepayment'}</span>
            </p>
        </div>
    </div>*}
    <style>
        .hide-element {
            display: none !important;
        }
    </style>
    <div class="row">
        <div class="col-xs-12 col-md-12">
            <div class="payment_module paylike-payment clearfix"
                 style="
                         border: 1px solid #d6d4d4;
                         border-radius: 4px;
                         color: #333333;
                         display: block;
                         font-size: 17px;
                         font-weight: bold;
                         letter-spacing: -1px;
                         line-height: 23px;
                         padding: 20px 20px;
                         position: relative;
                         cursor:pointer;
                         margin-top: 10px;
                 {*" onclick="pay();" >*}
                         ">
                <input style="float:left;" id="paylike-btn" type="image" name="submit"
                       src="{$this_path_paylike}logo.png" alt=""
                       style="vertical-align: middle; margin-right: 10px; width:57px; height:57px;"/>
                <div style="float:left; margin-left:10px;">
                    <span style="margin-right: 10px;">{l s={$payment_method_title} mod='paylikepayment'}</span>
                    <span>
                        <ul class="cards">
                            {foreach from=$payment_method_creditcard_logo item=logo}
                                <li>
                                    <img src="{$this_path_paylike}/views/img/{$logo}" title="{$logo}" alt="{$logo}"/>
                                </li>
                            {/foreach}
                        </ul>
                    </span>
                    <small style="font-size: 12px; display: block; font-weight: normal; letter-spacing: 1px;">{l s={$payment_method_desc} mod='paylikepayment'}</small>
                </div>
            </div>
        </div>
    </div>
{/if}
