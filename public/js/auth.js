/*
 * Login and sign-up helpers (progressive enhancement; the server still
 * validates everything and the forms work without JavaScript).
 *  - show/hide password buttons and a Caps Lock warning;
 *  - checks as you type: email format, Philippine mobile number, password
 *    strength (advice only; the rule stays 8-72 characters), passwords match;
 *  - sign-up in two steps with a step indicator; "Continue" checks step 1
 *    first, and a failed submit reopens the step with the problem.
 */
(function () {
    'use strict';

    var PHONE = /^(\+639|09)\d{9}$/;
    var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    function hintFor(input) {
        return document.querySelector('[data-hint-for="' + input.id + '"]') || document.getElementById(input.id + 'Hint');
    }

    function setHint(input, text, tone) {
        var hint = hintFor(input);
        if (!hint) { return; }
        hint.textContent = text || '';
        hint.className = 'field-hint-live' + (tone ? ' is-' + tone : '');
    }

    function markInvalid(input, invalid) {
        if (invalid) {
            input.setAttribute('aria-invalid', 'true');
        } else if (!input.closest('.form-group').querySelector('.field-error')) {
            input.removeAttribute('aria-invalid');
        }
    }

    // ------------------------------------------------ show/hide password
    function initPasswordToggles(form) {
        form.querySelectorAll('[data-pw-toggle]').forEach(function (button) {
            var input = document.getElementById(button.getAttribute('aria-controls'));
            button.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.setAttribute('title', show ? 'Hide password' : 'Show password');
                input.focus();
            });
        });
        // Never submit with the password visible in the field's history/autofill.
        form.addEventListener('submit', function () {
            form.querySelectorAll('input[data-was-password], .pw-field input').forEach(function (input) { input.type = 'password'; });
        });
    }

    // ------------------------------------------------------- Caps Lock
    function initCapsLock(form) {
        var hint = form.querySelector('.caps-hint');
        if (!hint) { return; }
        form.querySelectorAll('.pw-field input').forEach(function (input) {
            ['keydown', 'keyup'].forEach(function (type) {
                input.addEventListener(type, function (event) {
                    if (event.getModifierState) { hint.hidden = !event.getModifierState('CapsLock'); }
                });
            });
            input.addEventListener('blur', function () { hint.hidden = true; });
        });
    }

    // ------------------------------------------------------ live checks
    function strength(value) {
        var score = 0;
        if (value.length >= 8) { score++; }
        if (value.length >= 12) { score++; }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score++; }
        if (/\d/.test(value)) { score++; }
        if (/[^A-Za-z0-9]/.test(value)) { score++; }
        if (value.length < 8) { return { level: 0, label: (8 - value.length) + ' more character' + (8 - value.length === 1 ? '' : 's') + ' needed' }; }
        if (score <= 2) { return { level: 1, label: 'Okay: add numbers, symbols or more length' }; }
        if (score === 3) { return { level: 2, label: 'Good' }; }
        return { level: 3, label: 'Strong' };
    }

    function check(input, final) {
        var value = input.value.trim();
        var kind = input.getAttribute('data-check');
        if (kind === 'email') {
            if (value === '') { setHint(input, ''); markInvalid(input, false); return true; }
            var ok = EMAIL.test(value);
            setHint(input, ok ? '' : (final ? 'Enter an email like name@example.com' : ''), ok ? null : 'error');
            if (final) { markInvalid(input, !ok); }
            return ok;
        }
        if (kind === 'phone') {
            var digits = value.replace(/[\s-]/g, '');
            if (digits === '') { setHint(input, ''); markInvalid(input, false); return true; }
            var good = PHONE.test(digits);
            setHint(input, good ? 'Looks good' : (final ? 'Use 09XX XXX XXXX or +639XX XXX XXXX' : ''), good ? 'ok' : 'error');
            if (final || good) { markInvalid(input, !good); }
            return good;
        }
        if (kind === 'strength') {
            var meter = input.form.querySelector('[data-strength]');
            if (!meter) { return true; }
            meter.hidden = input.value === '';
            var s = strength(input.value);
            meter.setAttribute('data-level', String(s.level));
            meter.querySelector('.strength-text').textContent = input.value === '' ? '' : s.label;
            var confirm = input.form.querySelector('[data-check="match"]');
            if (confirm && confirm.value !== '') { check(confirm, false); }
            return input.value.length >= 8 && input.value.length <= 72;
        }
        if (kind === 'match') {
            var password = input.form.querySelector('#password');
            if (input.value === '') { setHint(input, ''); return true; }
            var same = input.value === password.value;
            setHint(input, same ? 'Passwords match' : 'Passwords don’t match yet', same ? 'ok' : (final ? 'error' : null));
            if (final) { markInvalid(input, !same); }
            return same;
        }
        return true;
    }

    function initChecks(form) {
        form.querySelectorAll('[data-check]').forEach(function (input) {
            input.addEventListener('input', function () { check(input, false); });
            input.addEventListener('blur', function () { check(input, true); });
        });
    }

    // ------------------------------------------------------ two steps
    function initSteps(form) {
        var steps = form.querySelectorAll('.auth-step');
        var stepper = document.querySelector('[data-stepper]');
        var next = form.querySelector('[data-step-next]');
        var back = form.querySelector('[data-step-back]');
        if (steps.length < 2 || !next) { return; }

        stepper.hidden = false;
        next.hidden = false;
        back.hidden = false;
        form.classList.add('is-stepped');

        function show(n, focus) {
            steps.forEach(function (step) {
                var on = step.getAttribute('data-step') === String(n);
                step.hidden = !on;
                if (on) { step.classList.remove('step-enter'); void step.offsetWidth; step.classList.add('step-enter'); }
            });
            stepper.querySelectorAll('[data-step-indicator]').forEach(function (item) {
                var k = Number(item.getAttribute('data-step-indicator'));
                item.classList.toggle('is-current', k === n);
                item.classList.toggle('is-done', k < n);
                if (k === n) { item.setAttribute('aria-current', 'step'); } else { item.removeAttribute('aria-current'); }
            });
            if (focus) {
                var target = steps[n - 1].querySelector('[aria-invalid="true"], input');
                if (target) { target.focus(); }
            }
        }

        next.addEventListener('click', function () {
            var first = null;
            steps[0].querySelectorAll('input').forEach(function (input) {
                var ok = input.value.trim() !== '' && check(input, true);
                if (input.value.trim() === '') {
                    input.setAttribute('aria-invalid', 'true');
                } else if (ok) {
                    markInvalid(input, false);
                }
                if (!ok && !first) { first = input; }
            });
            if (first) { first.focus(); return; }
            show(2, true);
        });
        back.addEventListener('click', function () { show(1, true); });

        // Enter in step 1 continues instead of submitting a half-filled form.
        steps[0].addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') { event.preventDefault(); next.click(); }
        });

        show(Number(form.getAttribute('data-start-step')) || 1, false);
    }

    document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        // DOM properties are not copied into Turbo's cloned snapshots.
        if (form.pasabuyAuthReady) { return; }
        form.pasabuyAuthReady = true;
        initPasswordToggles(form);
        initCapsLock(form);
        initChecks(form);
        if (form.hasAttribute('data-register')) { initSteps(form); }
    });
})();
