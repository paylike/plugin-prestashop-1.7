{**
 *
 * @author    DerikonDevelopment <ionut@derikon.com>
 * @copyright Copyright (c) permanent, DerikonDevelopment
 * @license   Addons PrestaShop license limitation
 * @link      http://www.derikon.com/
 *
 *}
<script type="text/javascript">
    var admin_orders_uri = '{$request_uri}';
    var tok = '{$tok}';
</script>
<div id="logoModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <form id="logo_form" name="logo_form" action="{$request_uri}&upload_logo" method="post"
                    enctype="multipart/form-data">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">{l s='New logo' mod='paylikepayment'}</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success" id="alert" style="display:none;"></div>
                    <div class="form-group">
                        <label for="logo_name"
                                class="control-label required">{l s='Logo name' mod='paylikepayment'}</label>
                        <input type="text" class="form-control" id="logo_name" name="logo_name"
                                placeholder="{l s='Enter logo name' mod='paylikepayment'}">
                    </div>
                    <div class="form-group">
                        <input type="file" class="form-control" id="logo_file" name="logo_file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-default" id="save_logo"
                            data-loading-text="{l s='Saving logo ...' mod='paylikepayment'}">{l s='Save' mod='paylikepayment'}</button>
                    <button type="button" class="btn btn-danger"
                            data-dismiss="modal">{l s='Close' mod='paylikepayment'}</button>
                </div>
            </form>
        </div>
    </div>
</div>