document.addEventListener('DOMContentLoaded', function () {
    const placeholders = document.querySelectorAll('.user-ip-placeholder');
    if (placeholders.length === 0) {
        return;
    }

    // Use a single API call for all placeholders on the page
    fetch(userIpLocationData.apiUrl, {
        cache: 'default' // Respect browser caching
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok.');
        }
        return response.json();
    })
    .then(data => {
        if (data.status && data.status === 'success') {
            placeholders.forEach(el => {
                const type = el.dataset.type;
                let value = data[type] || '';

                // Handle special cases
                if (type === 'flag') {
                    const countryCode = data.countryCode ? data.countryCode.toLowerCase() : 'unknown';
                    const flagUrl = userIpLocationData.flagsUrl + countryCode + '.png';
                    const height = el.dataset.height || 'auto';
                    const width = el.dataset.width || 'auto';
                    const verticalAlign = el.dataset.verticalAlign || 'middle';

                    const img = document.createElement('img');
                    img.src = flagUrl;
                    img.alt = data.country || 'Country Flag';
                    img.style.height = height;
                    img.style.width = width;
                    img.style.verticalAlign = verticalAlign;
                    img.classList.add('user-ip-flag');

                    el.replaceWith(img);
                } else if (type === 'mobile' || type === 'proxy' || type === 'hosting') {
                    // These now have pre-formatted text from the API
                    value = data[type + '_text'];
                    el.replaceWith(document.createTextNode(value));
                } else {
                    // For all other types, just replace the placeholder with the text value
                    el.replaceWith(document.createTextNode(value));
                }
            });
        } else {
            // If API call fails, remove the placeholders to show nothing
            placeholders.forEach(el => el.remove());
            console.error('User IP & Location Error:', data.message || 'Failed to retrieve location data.');
        }
    })
    .catch(error => {
        placeholders.forEach(el => el.remove());
        console.error('User IP & Location Fetch Error:', error);
    });
}); 