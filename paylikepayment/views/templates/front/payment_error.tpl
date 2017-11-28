{*
* Team Paylike
*
*  @author     Team Paylike
*  @copyright  Team Paylike
*  @license    MIT license: https://opensource.org/licenses/MIT
*}

{if $paylike_order_error == 1}
    <div class="error alert alert-danger">
        {l s='Unfortunately, an error occurred while processing the transaction.' mod='paylikepayment'}<br/><br/>
        {if !empty($paylike_error_message) }
            {l s='ERROR : "' mod='paylikepayment'}{l s={$paylike_error_message} mod='paylikepayment'}{l s='"' mod='paylikepayment'}
            <br/>
            <br/>
        {/if}
        {l s='Your order cannot be created. If you think this is an error, feel free to contact our' mod='paylikepayment'}
        <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='customer support team' mod='paylikepayment'}</a>
    </div>
{/if}
