/* global $, jQuery */

/* Initial visibility — panels stay hidden until a fitting is selected. */
$('#eftexport').hide();
$('#showeft').val('');

/* EFT export quick-open from fit detail */
$(document).on('click', '#eftexportTrigger', function () {
    $('#eftexport').show();
    const target = $('#eftexport').offset();
    if (target) {
        $('html, body').animate({scrollTop: target.top - 60}, 250);
    }
});
