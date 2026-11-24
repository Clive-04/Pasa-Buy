/* Apply the saved light/dark choice before first paint (no flash).
 * Loaded synchronously in <head>; a file rather than an inline script so the
 * Content-Security-Policy can forbid inline scripts. */
try {
    var t = localStorage.getItem('pasabuy-theme');
    if (t === 'light' || t === 'dark') { document.documentElement.setAttribute('data-theme', t); }
} catch (e) {}
