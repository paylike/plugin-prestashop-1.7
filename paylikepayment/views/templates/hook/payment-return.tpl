{*
* Team Paylike
*
*  @author     Team Paylike
*  @copyright  Team Paylike
*  @license    MIT license: https://opensource.org/licenses/MIT
*}

{if $paylike_order.valid == 1}
    <div class="conf alert alert-success">
        {l s='Congratulations, your payment has been approved' mod='paylikepayment'}</div>
    </div>
{else}
    <div class="error alert alert-danger">
        {l s='Unfortunately, an error occurred while processing the transaction.' mod='paylikepayment'}<br/><br/>
        {l s='We noticed a problem with your order. If you think this is an error, feel free to contact our' mod='paylikepayment'}
        <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='customer support team' mod='paylikepayment'}</a>.
    </div>
{/if}
