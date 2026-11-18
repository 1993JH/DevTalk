// Replaces inline onsubmit="return confirm(...)" handlers so the page needs no
// inline script (CSP-compliant). Any <form data-confirm="message"> will prompt
// before submitting.
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
