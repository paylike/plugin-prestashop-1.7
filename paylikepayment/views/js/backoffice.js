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
