
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

$(document).ready(function() {
    if ($('#get-secret').length > 0) {
        let postData = $("#get-secret").serialize();
        $.post(
            '/get',
            postData,
            function(data) {
                $("#found").show();
                $("#secret").text(data).fadeIn(1000);
            }
        ).fail(function(data) {
            $('#error-msg').html(data.responseText).show();
        });
    }
});