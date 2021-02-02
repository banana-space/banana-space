let hideNotificationsTimeout;

$(document).ready(function () {
    let $actions = $('#p-cactions');
    $actions.addClass('b-dropdown');
    $actions.children('#p-cactions-label').addClass('b-dropdown-toggle');
    $actions.children('.mw-portlet-body').addClass('b-dropdown-content');
});
