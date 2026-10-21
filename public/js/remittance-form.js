/* Remittance creation: route constraints and scoped customer autofill. */
$(function () {
    const config = window.remittanceFormConfig;
    const form = document.getElementById('remittanceForm');
    if (!form || !config) return;
    const from = $('#fromBranch'), to = $('#toBranch'), routing = $('#routing');
    const branch = id => config.branches[String(id)];
    const values = {sender: null, receiver: null};
    function fill(party, customer) {
        const mapping = {name: 'name', father_name: 'father', phone: 'phone', tazkira_number: 'tazkira'};
        Object.entries(mapping).forEach(([field, key]) => {
            const input = document.getElementById(`${party}-${field}`);
            input.value = customer?.[key] || ''; input.readOnly = !!customer;
        });
    }
    function customerSelect(party, branchId) {
        const select = $(`#${party}Account`);
        if (values[party] === branchId) return;
        const first = values[party] === null;
        values[party] = branchId;
        if (select.hasClass('select2-hidden-accessible')) select.select2('destroy');
        if (!first && select.val()) fill(party, null);
        select.empty().append(new Option('New / enter details', ''));
        const allowed = !!branchId && (config.isMain || Number(branchId) === config.userBranch);
        select.prop('disabled', !allowed);
        $(`#${party}AccountHint`).text(allowed ? 'Search by name, account code, or phone.' : 'Enter the details supplied by the other branch.');
        if (!allowed) return;
        select.select2({width: '100%', placeholder: 'New / enter details', allowClear: true,
            ajax: {url: config.customersUrl, dataType: 'json', delay: 200,
                data: p => ({branch_id: branchId, q: p.term || '', page: p.page || 1}), processResults: data => data}
        });
        if (first && select[0].dataset.oldId) {
            const oldId = select[0].dataset.oldId;
            $.getJSON(config.customersUrl, {branch_id: branchId, id: oldId}).done(data => {
                if (values[party] !== branchId) return;
                const customer = data.results.find(r => String(r.id) === oldId);
                if (customer) { select.append(new Option(customer.text, customer.id, true, true)).trigger('change'); fill(party, customer); }
            });
        }
        select.off('.remittance').on('select2:select.remittance', event => fill(party, event.params.data))
            .on('select2:clear.remittance', () => fill(party, null));
    }
    function totals() {
        const val = name => Number(form.elements[name]?.value || 0);
        const code = $('#currency option:selected').data('code') || '';
        const fee = val('sender_branch_commission') + val('receiver_branch_commission') + (routing.val() === 'via_main' ? val('bridge_commission') : 0);
        const money = value => value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + ' ' + code;
        $('#payoutPreview').text(money(val('amount'))); $('#commissionPreview').text(money(fee)); $('#collectionPreview').text(money(val('amount') + fee));
        const afn = code === 'AFN'; const rate = $('#rate');
        if (afn) rate.val(1); else if (rate.prop('readOnly')) rate.val('');
        rate.prop('readOnly', afn);
    }
    function syncRoute(event) {
        const type = form.elements.direction.value;
        const via = routing.val() === 'via_main';
        if (event?.target?.id === 'routing' && via && type === 'transfer' && config.isMain && Number(from.val()) === config.main) from.val('');
        const sourceFixed = type === 'outgoing' || (type === 'transfer' && !(config.isMain && via));
        const destFixed = type === 'incoming';
        if (sourceFixed) from.val(String(config.userBranch));
        if (destFixed) to.val(String(config.userBranch));
        // Keep controls submitted while restricting their available options.
        from.find('option').prop('disabled', false);
        to.find('option').prop('disabled', false);
        if (sourceFixed) from.find('option').each(function () {this.disabled = this.value !== String(config.userBranch);});
        if (destFixed) to.find('option').each(function () {this.disabled = this.value !== String(config.userBranch);});
        if (from.val() && from.val() === to.val()) { if (destFixed) from.val(''); else to.val(''); }
        $('#fromHint').text(sourceFixed ? 'Your authenticated branch' : 'Branch supplying the remittance');
        $('#toHint').text(destFixed ? 'Your authenticated branch' : 'Branch paying the receiver');
        const a = branch(from.val()), b = branch(to.val());
        const endpointMain = a?.main || b?.main;
        const mustBridge = a && b && !endpointMain && (!a.access || !b.access);
        if (endpointMain && routing.val() === 'via_main') routing.val('direct');
        if (mustBridge) routing.val('via_main');
        routing.find('option[value="direct"]').prop('disabled', !!mustBridge);
        routing.find('option[value="via_main"]').prop('disabled', !config.main || (!!endpointMain && !(config.isMain && type === 'transfer')));
        const bridge = routing.val() === 'via_main';
        $('label[for=sender_branch_commission]').text((a?.name || 'Sending branch') + ' commission');
        $('label[for=receiver_branch_commission]').text((b?.name || 'Paying branch') + ' commission');
        $('#bridgeFeeField').toggle(bridge); $('#bridge_commission').prop('disabled', !bridge);
        $('#routePreview').text(a && b ? [a.name, ...(bridge ? [branch(config.main)?.name || 'Main Branch'] : []), b.name].join(' → ') : 'Choose the sending and receiving branches');
        $('#routeHint').text(mustBridge ? 'An offline or restricted branch must route through Main Branch.' : bridge ? 'Main Branch coordinates two separate branch debts.' : 'A direct payment obligation between these branches.');
        $('#storeRemittance').prop('disabled', !!(bridge && !config.main));
        from.trigger('change.select2'); to.trigger('change.select2'); routing.trigger('change.select2');
        customerSelect('sender', from.val()); customerSelect('receiver', to.val()); totals();
    }
    $('input[name="direction"], #routing, #fromBranch, #toBranch').on('change.remittance', syncRoute);
    $('#amount, #sender_branch_commission, #bridge_commission, #receiver_branch_commission').on('input', totals);
    $('#currency').on('change', totals);
    syncRoute();
});
