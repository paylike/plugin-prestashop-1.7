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
        var check = 0;

        console.log(products);
        //        var qweqweqweq = jQuery.parseJSON(products);
        //        console.log(qweqweqweq);
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
            ifCheckedUncheck(1);
            check = 1;
        }

        function htmlDecode(url) {
            return String(url).replace(/&amp;/g, '&');
        }


        ////////////////////////////////////////////

        function ifCheckedUncheck(val) {
            $('input[type="checkbox"]').not(this).prop('checked', false);
            if (val == 1) {
//                $('#payment-confirmation').find("div").find("div").toggleClass('active disabled');
                $('#payment-confirmation').find("div").find("div").removeClass('active');
                $('#payment-confirmation').find("div").find("div").addClass('disabled');
            } else {
                $('#payment-confirmation').find("div").children(0).addClass('disabled');
            }

        }

        var idOfPayLike;
        var options = document.getElementsByClassName('payment-option');


        for (var p = 0; p < options.length; p++) {

            if (options[p].getElementsByTagName("span")[2].innerHTML == '{$payment_method_title}') {
                idOfPayLike = options[p].getAttribute('id');
            }
        }

        buttons = document.getElementById(idOfPayLike);

        for (var i = 0; i < options.length - 1; i++) {

            options[i].addEventListener('click', function () {

                if (this.getAttribute('id') != buttons) {
                    $(".ps-shown-by-js button").replaceWith('<button type="submit" disabled="disabled" style="background-color: #2fb5d2" class="btn btn-primary center-block">' + pay_text + '</button>');
                    $(".ps-shown-by-js div").replaceWith('<button type="submit" disabled="disabled" style="background-color: #2fb5d2" class="btn btn-primary center-block">' + pay_text + '</button>');

                    var buttonsPay = document.getElementById('payment-confirmation');
                    buttonsPay.addEventListener('click', function (e) {
                        e.preventDefault();
                        return false;
                    });
                }
            });
        }

        buttons.addEventListener('click', function () {

            $(".ps-shown-by-js button").replaceWith('<div ' +
                'style="-webkit-appearance: none; background-color: #2fb5d2;" ' +
                'class="btn btn-primary center-block disabled ">' + pay_text + '</div>');

            ifCheckedUncheck(0);

            $('input[type="checkbox"]').click(function () {
                var divButtonsPay = document.getElementById('payment-confirmation').firstElementChild || elem.firstChild;
                var buttonsPay = divButtonsPay.firstElementChild || elem.firstChild;
                if ($(this).prop("checked") == true) {
                    $('#payment-confirmation').find("div").find("div").removeClass('disabled');
                    $('#payment-confirmation').find("div").find("div").addClass('active');
                    check = 0;
                    buttonsPay.addEventListener('click', function (e) {
                        if (check == 0) {
                            e.preventDefault();
                            pay();
                        }
                        return false;
                    });

                } else if ($(this).prop("checked") == false) {
                    buttonsPay.addEventListener('click', function (e) {
                        e.preventDefault();
                        return false;
                    });
//                    $('#payment-confirmation').find("div").children(0).toggleClass('active disabled');
                    $('#payment-confirmation').find("div").find("div").removeClass('active');
                    $('#payment-confirmation').find("div").find("div").addClass('disabled');
                }
            });
        });
        ////////////////////////////////////////////


    </script>
    {*<div class="row">
        <div class="col-xs-12">
            <p class="payment_module paylike" onclick="pay();">
                <span class="paylike_text">{l s='Pay with credit card' mod='paylikepayment'}</span>
            </p>
        </div>
    </div>*}
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
