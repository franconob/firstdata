/**
 * Created by fherrero on 5/20/14.
 */
$(function () {
    var opts = {
            lines: 13, // The number of lines to draw
            length: 20, // The length of each line
            width: 10, // The line thickness
            radius: 30, // The radius of the inner circle
            corners: 1, // Corner roundness (0..1)
            rotate: 0, // The rotation offset
            direction: 1, // 1: clockwise, -1: counterclockwise
            color: '#6087B3', // #rgb or #rrggbb or array of colors
            speed: 1, // Rounds per second
            trail: 60, // Afterglow percentage
            shadow: true, // Whether to render a shadow
            hwaccel: false, // Whether to use hardware acceleration
            className: 'spinner', // The CSS class to assign to the spinner
            zIndex: 2e9, // The z-index (defaults to 2000000000)
            top: '50%', // Top position relative to parent
            left: '50%' // Left position relative to parent
        };
        var target = document.getElementById('transactions');
        var spinner = new Spinner();
    $(document).ajaxStart(function() {
        spinner.spin(target);
    });
    $(document).ajaxStop(function() {
        spinner.stop();
    });
    $('#transactions').WATable({
        url: '/reportes',
        filter: true,
        pageSize: [10],
        debug: true,
        columnPicker: true,
        types: {
            string: {
                placeHolder: "Filtro..."
            },
            date: {
                datePicker: true,
                format: 'd/M/yyyy H:m:s'
            }
        }
    });
});


