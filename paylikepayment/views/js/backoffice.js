/**
 *
 * @author    DerikonDevelopment <ionut@derikon.com>
 * @copyright Copyright (c) permanent, DerikonDevelopment
 * @license   Addons PrestaShop license limitation
 * @version   1.0.0
 * @link      http://www.derikon.com/
 *
 */

$(document).ready(function () {
    var html = '<a href="#" class="add-more-btn" data-toggle="modal" data-target="#logoModal"><i class="process-icon-plus" data-toggle="tooltip" title="Add your own logo"></i></a>';
    $('select[name="PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO[]"]').parent('div').append(html);

    $('[data-toggle="tooltip"]').tooltip();

    $('.paylike-config').each(function (index, item) {
        if ($(item).hasClass('has-error')) {
            $(item).parents('.form-group').addClass('has-error');
        }
    });

    $('.paylike-language').bind('change', paylikeLanguageChange);
    $('#logo_form').on('submit', ajaxSaveLogo);

});

function paylikeLanguageChange(e) {
    var lang_code = $(e.currentTarget).val();
    window.location = admin_orders_uri + "&change_language&lang_code=" + lang_code;
}

function ajaxSaveLogo(e) {
    e.preventDefault();
    $('#save_logo').button('loading');
    $('#alert').html("").hide();
    var url = $('#logo_form').attr('action');
    url = url + "&token=" + tok;

    //grab all form data
    var formData = new FormData($(this)[0]);
    //formData.append("token", token);
    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        dataType: 'json',
        async: false,
        cache: false,
        contentType: false,
        processData: false,
        success: function (response) {
            console.log(response);
            $('#save_logo').button('reset');
            if (response.status == 0) {
                var html = "<strong>Error !</strong> " + response.message;
                $('#alert').html(html)
                    .show()
                    .removeClass('alert-success')
                    .removeClass('alert-danger')
                    .addClass('alert-danger');
            } else if (response.status == 1) {
                var html = "<strong>Seccess !</strong> " + response.message;
                $('#alert').html(html)
                    .show()
                    .removeClass('alert-success')
                    .removeClass('alert-danger')
                    .addClass('alert-success');

                window.location = window.location;
            }
        },
        error: function (response) {
            console.log(response);
        },
    });

    return false;
}

$(function() {
    /** Triggers for hiding and showing LIVE/STAGING INPUTS */
    $(document).ready(checkTransactionMode);
    $(document).on('change', 'select[name="PAYLIKE_TRANSACTION_MODE"]', checkTransactionMode);
});

/** Function to hide or show LIVE/TEST inputs on module configuration page */
function checkTransactionMode(e) {
    var isLive = $(document).find('select[name="PAYLIKE_TRANSACTION_MODE"] :selected').val() == 'live';
    /** If the live mode is chacked */
    if (isLive) {
        /** Hide - Staging - Site ID / Private Key */
        $('#PAYLIKE_TEST_SECRET_KEY, #PAYLIKE_TEST_PUBLIC_KEY').closest('.form-group').slideUp(0);
        /** Show - Live - Site ID / Private Key */
        $('#PAYLIKE_LIVE_SECRET_KEY, #PAYLIKE_LIVE_PUBLIC_KEY').closest('.form-group').slideDown(0);
    }
    else {
        /** Show - Staging - Site ID / Private Key */
        $('#PAYLIKE_TEST_SECRET_KEY, #PAYLIKE_TEST_PUBLIC_KEY').closest('.form-group').slideDown(0);
        /** Hide - Live - Site ID / Private Key */
        $('#PAYLIKE_LIVE_SECRET_KEY, #PAYLIKE_LIVE_PUBLIC_KEY').closest('.form-group').slideUp(0);
    }
}
