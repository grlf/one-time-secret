/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

$.fn.secretShow = function () {
    if (this.length > 0) {
        let postData = this.serialize();
        $.post('/get', postData, function (data) {
            $('#found').show();
            $('#secret').text(data).fadeIn(1000);
        }).fail(function (data) {
            $('#error-msg').html(data.responseText).show();
        });
    }
};

$.fn.copyInput = function () {
    if (this.length > 0) {
        //Popover
        const popover = this.popover({
            container: 'body',
            placement: 'bottom',
            content: 'Copied!!',
            trigger: 'manual',
        });

        this.on('click', function () {
            const link = $(this).data('copy-selector');
            navigator.clipboard.writeText($(link).val());

            popover.popover('show');
            setTimeout(() => popover.popover('hide'), 5000);
        });
    }
};

$(document).ready(function () {
    //Load the secret
    $('#get-secret').secretShow();

    //Copy input to clipboard
    $('.copy-btn').copyInput();
});
