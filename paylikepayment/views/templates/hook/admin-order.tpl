{*
* Team Paylike
*
*  @author     Team Paylike
*  @copyright  Team Paylike
*  @license    MIT license: https://opensource.org/licenses/MIT
*}

<script type="text/javascript">
    $(document).ready(function () {
        var appendEl;
        {*{die;}*}
        appendEl = $('select[name=id_order_state]').parents('form').after($('<div/>'));
        $("#paylike").appendTo(appendEl);

        $('#paylike_action').bind('change', paylikeActionChangeHandler);
        $('#submit_paylike_action').bind('click', submitPaylikeActionClickHandler);
    });

    function paylikeActionChangeHandler(e) {
        var option_value = $('#paylike_action option:selected').val();
        if (option_value == 'refund') {
            $('input[name="paylike_amount_to_refund"]').show();
        } else {
            $('input[name="paylike_amount_to_refund"]').hide();
        }
    }

    function submitPaylikeActionClickHandler(e) {
        e.preventDefault();
        $('#alert').hide();
        var paylike_action = $('#paylike_action').val();
        var errorFlag = false;

        if (paylike_action == '') {
            var html = '<strong>Warning!</strong> Please select an action.';
            errorFlag = true;

        } else if (paylike_action == 'refund') {
            var refund_amount = $('input[name="paylike_amount_to_refund"]').val();
            var html = '';

            if (refund_amount == '') {
                var html = '<strong>Warning!</strong> Please provide the refund amount.';
                errorFlag = true;

            }
        }

        if (errorFlag) {
            $('#alert').html(html);
            $('#alert').removeClass('alert-success')
                .removeClass('alert-info')
                .removeClass('alert-warning')
                .removeClass('alert-danger')
                .addClass('alert-warning');
            $('#alert').show();
            return false;
        }

        //Make an AJAX call for Paylike action
        $(e.currentTarget).button('loading');
        var url = $('#paylike_form').attr('action');
        $.ajax({
            url: url,
            type: 'POST',
            data: $('#paylike_form').serializeArray(),
            dataType: 'JSON',
            success: function (response) {
                $(e.currentTarget).button('reset');
                console.log(response);

                if (response.hasOwnProperty('success') && response.hasOwnProperty('message')) {
                    var message = response.message;
                    var html = '<strong>Success!</strong> ' + message;
                    $('#alert').html(html);
                    $('#alert').removeClass('alert-success')
                        .removeClass('alert-info')
                        .removeClass('alert-warning')
                        .removeClass('alert-danger')
                        .addClass('alert-success');
                    $('#alert').show();
                    setTimeout(function () {
                        console.log('page reloaded');
                        location.reload();
                    }, 1500)

                } else if (response.hasOwnProperty('warning') && response.hasOwnProperty('message')) {
                    var message = response.message;
                    var html = '<strong>Warning!</strong> ' + message;
                    $('#alert').html(html);
                    $('#alert').removeClass('alert-success')
                        .removeClass('alert-info')
                        .removeClass('alert-warning')
                        .removeClass('alert-danger')
                        .addClass('alert-warning');
                    $('#alert').show();

                } else if (response.hasOwnProperty('error') && response.hasOwnProperty('message')) {
                    var message = response.message;
                    var html = '<strong>Error!</strong> ' + message;
                    $('#alert').html(html);
                    $('#alert').removeClass('alert-success')
                        .removeClass('alert-info')
                        .removeClass('alert-warning')
                        .removeClass('alert-danger')
                        .addClass('alert-danger');
                    $('#alert').show();
                }
            },
            error: function (response) {
                console.log(response);
            }
//
        });

    }
</script>
<div id="paylike" class="row" style="margin-top:5%;">
    <div class="panel">
        <form id="paylike_form"
                action="{$link->getAdminLink('AdminOrders', false)|escape:'htmlall':'UTF-8'}&amp;id_order={$id_order|escape:'htmlall':'UTF-8'}&amp;vieworder&amp;token={$order_token|escape:'htmlall':'UTF-8'}"
                method="post">
            <fieldset {if $ps_version < 1.5}style="width: 400px;"{/if}>
                <legend class="panel-heading">
                    <img src="../img/os/7.gif" alt=""/>{l s='Process Paylike Payment' mod='paylikepayment'}
                </legend>
                <div id="alert" class="alert" style="display: none;"></div>
                <div class="form-group margin-form">
                    <select class="form-control" id="paylike_action" name="paylike_action">
                        <option value="">{l s='-- Select Paylike Action --' mod='paylikepayment'}</option>
                        {if $payliketransaction['captured'] == "NO"}
                            <option value="capture">{l s='Capture' mod='paylikepayment'}</option>
                        {/if}
                        <option value="refund">{l s='Refund' mod='paylikepayment'}</option>
                        {if $payliketransaction['captured'] == "NO"}
                            <option value="void">{l s='Void' mod='paylikepayment'}</option>
                        {/if}
                    </select>
                </div>

                <div class="form-group margin-form">
                    <div class="col-md-12">
                        <input class="form-control" name="paylike_amount_to_refund" style="display: none;"
                                placeholder="{l s='Amount to refund' mod='paylikepayment'}" type="text"/>
                    </div>
                </div>

                <div class="form-group margin-form">
                    <input class="pull-right btn btn-default" name="submit_paylike_action" id="submit_paylike_action"
                            type="submit" class="btn btn-primary" value="{l s='Process Action' mod='paylikepayment'}"/>
                </div>
            </fieldset>
        </form>
    </div>
</div>
