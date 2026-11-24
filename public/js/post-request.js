/* Post-request page: live cost summary and a local draft (moved out of the view for the CSP). */
(function () {
    // Runs when loaded (also on Turbo visits). Server values come from data-*
    // attributes on the form, so the page needs no inline script (CSP, R6).
    var form = document.getElementById('postRequestForm');
    if (!form || form.pbPostRequestReady) { return; }
    form.pbPostRequestReady = true;
    var DRAFT_KEY = 'pasabuy:post-request-draft:v1';
    var FEE_BASIS_POINTS = parseInt(form.getAttribute('data-fee-basis-points'), 10) || 1000;
    var hadServerInput = form.getAttribute('data-had-server-input') === '1';
    var CSRF_NAME = form.getAttribute('data-csrf-name') || '';

    function cents(value) {
        var match = /^(\d{1,8})(?:\.(\d{1,2}))?$/.exec(String(value || '').trim());
        if (!match) { return 0; }
        return parseInt(match[1], 10) * 100 + parseInt((match[2] || '').padEnd(2, '0') || '0', 10);
    }

    function peso(c) {
        return '₱' + Math.floor(c / 100).toLocaleString('en-PH') + '.' + String(c % 100).padStart(2, '0');
    }

    function text(id, value) { document.getElementById(id).textContent = value; }

    function selectedLabel(select) {
        return select.value ? select.options[select.selectedIndex].text : 'Not set';
    }

    function update() {
        var item = cents(form.estimated_price.value);
        var fee = cents(form.service_fee.value);
        var platform = Math.floor((fee * FEE_BASIS_POINTS + 5000) / 10000); // same half-up rule as the server

        text('summaryItem', form.item_name.value.trim() || 'Not set');
        text('summaryQuantity', form.quantity.value || '1');
        text('summaryStore', form.store_name.value.trim() || 'Not set');
        text('summaryUrgency', selectedLabel(form.urgency));
        text('summaryPayment', selectedLabel(form.payment_method));
        text('summaryEstimated', peso(item));
        text('summaryServiceFee', peso(fee));
        text('summaryTotal', peso(item + fee));
        text('summaryPlatformFee', peso(platform));
        text('summaryRunnerNet', peso(item + fee - platform));
    }

    // ------------------------------------------------------ local draft
    function fields() {
        return Array.prototype.filter.call(form.elements, function (el) {
            return el.name && el.name !== CSRF_NAME && el.type !== 'hidden' && el.type !== 'submit' && el.type !== 'button';
        });
    }

    function saveDraft() {
        var data = {};
        fields().forEach(function (el) { data[el.name] = el.value; });
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
            text('draftStatus', 'Draft saved on this device.');
        } catch (e) {
            text('draftStatus', 'Could not save a draft in this browser.');
        }
    }

    function restoreDraft() {
        if (hadServerInput) { return; } // server re-filled the form after an error
        var raw = null;
        try { raw = localStorage.getItem(DRAFT_KEY); } catch (e) { return; }
        if (!raw) { return; }
        try {
            var data = JSON.parse(raw);
            fields().forEach(function (el) {
                if (Object.prototype.hasOwnProperty.call(data, el.name)) { el.value = data[el.name]; }
            });
            document.getElementById('draftNotice').hidden = false;
        } catch (e) { /* ignore corrupt draft */ }
    }

    document.getElementById('saveDraft').addEventListener('click', saveDraft);
    document.getElementById('discardDraft').addEventListener('click', function () {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        form.reset();
        document.getElementById('draftNotice').hidden = true;
        update();
    });

    form.addEventListener('submit', function () {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    });

    form.addEventListener('input', update);
    form.addEventListener('change', update);

    restoreDraft();
    update();
})();
