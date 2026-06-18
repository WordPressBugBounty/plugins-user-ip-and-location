/**
 * Live preview for the time/date format selects on the settings page.
 */
(function () {
    'use strict';

    function pad(n) {
        return n < 10 ? '0' + n : n;
    }

    function updateTime() {
        var sel = document.getElementById('time_format_select');
        var out = document.getElementById('time_preview');
        if (!sel || !out) {
            return;
        }
        var format = sel.value;
        var now = new Date();
        var hours = now.getHours();
        var minutes = now.getMinutes();
        var seconds = now.getSeconds();
        var ampm = hours >= 12 ? (format.indexOf('A') !== -1 ? 'PM' : 'pm') : (format.indexOf('A') !== -1 ? 'AM' : 'am');
        var h12 = hours % 12;
        h12 = h12 ? h12 : 12;
        var s = '';

        switch (format) {
            case 'g:i a':
            case 'g:i A':
                s = h12 + ':' + pad(minutes) + ' ' + ampm;
                break;
            case 'H:i':
                s = pad(hours) + ':' + pad(minutes);
                break;
            case 'h:i a':
            case 'h:i A':
                s = pad(h12) + ':' + pad(minutes) + ' ' + ampm;
                break;
            case 'g:i:s a':
            case 'g:i:s A':
                s = h12 + ':' + pad(minutes) + ':' + pad(seconds) + ' ' + ampm;
                break;
            case 'H:i:s':
                s = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
                break;
        }

        out.textContent = s;
    }

    function updateDate() {
        var sel = document.getElementById('date_format_select');
        var out = document.getElementById('date_preview');
        if (!sel || !out) {
            return;
        }
        var format = sel.value;
        var now = new Date();
        var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        var monthsShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var daysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var s = '';

        switch (format) {
            case 'F j, Y':
                s = months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
                break;
            case 'Y-m-d':
                s = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
                break;
            case 'm/d/Y':
                s = pad(now.getMonth() + 1) + '/' + pad(now.getDate()) + '/' + now.getFullYear();
                break;
            case 'd/m/Y':
                s = pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear();
                break;
            case 'M j, Y':
                s = monthsShort[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
                break;
            case 'j F Y':
                s = now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
                break;
            case 'l, F j, Y':
                s = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
                break;
            case 'D, M j, Y':
                s = daysShort[now.getDay()] + ', ' + monthsShort[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
                break;
        }

        out.textContent = s;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var t = document.getElementById('time_format_select');
        var d = document.getElementById('date_format_select');
        if (t) {
            t.addEventListener('change', updateTime);
        }
        if (d) {
            d.addEventListener('change', updateDate);
        }
    });
})();
