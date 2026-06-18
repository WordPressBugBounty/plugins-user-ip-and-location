/**
 * Persists dismissal of the cache-compatibility admin notice (no jQuery).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var notice = document.getElementById('user-ip-location-cache-notice');
        if (!notice || typeof window.userIpLocationNotice === 'undefined') {
            return;
        }

        notice.addEventListener('click', function (event) {
            if (!event.target || !event.target.classList.contains('notice-dismiss')) {
                return;
            }
            var data = window.userIpLocationNotice;
            var body = 'action=user_ip_location_dismiss_cache_notice'
                + '&nonce=' + encodeURIComponent(data.nonce || '');

            if (window.fetch) {
                window.fetch(data.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                });
            } else {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', data.ajaxUrl);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send(body);
            }
        });
    });
})();
