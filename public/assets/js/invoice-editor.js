(() => {
    const form = document.getElementById('invoice-form');
    if (!form) return;

    const itemsContainer = document.getElementById('invoice-items');
    const template = document.getElementById('invoice-item-template');
    const issuerSelect = document.getElementById('issuer-select');
    const currencySelect = document.getElementById('currency-select');
    const vatOption = document.querySelector('.vat-price-option');
    const vatCheckbox = vatOption.querySelector('input');
    let nextIndex = itemsContainer.querySelectorAll('[data-item]').length;

    const amount = (value) => `${Number(value || 0).toFixed(2)} ${currencySelect.value}`;

    const isVatPayer = () => issuerSelect.options[issuerSelect.selectedIndex]?.dataset.vat === '1';

    const calculate = () => {
        let subtotal = 0;
        let discountTotal = 0;
        let vatTotal = 0;
        let grandTotal = 0;
        const includeVat = isVatPayer() && vatCheckbox.checked;

        itemsContainer.querySelectorAll('[data-item]').forEach((row) => {
            const quantity = Math.max(0, Number(row.querySelector('[data-quantity]').value || 0));
            const price = Math.max(0, Number(row.querySelector('[data-price]').value || 0));
            const discountPercent = Math.min(100, Math.max(0, Number(row.querySelector('[data-discount]').value || 0)));
            const base = quantity * price;
            const discount = base * discountPercent / 100;
            const discounted = base - discount;
            let baseNet = base;
            let discountNet = discount;
            let net = discounted;
            let vat = 0;
            let total = discounted;

            if (isVatPayer() && includeVat) {
                baseNet = base / 1.18;
                discountNet = discount / 1.18;
                net = discounted / 1.18;
                vat = discounted - net;
            } else if (isVatPayer()) {
                vat = net * 0.18;
                total = net + vat;
            }

            subtotal += baseNet;
            discountTotal += discountNet;
            vatTotal += vat;
            grandTotal += total;
            row.querySelector('[data-line-total]').textContent = amount(total);
        });

        document.querySelector('[data-subtotal]').textContent = amount(subtotal);
        document.querySelector('[data-discount-total]').textContent = amount(discountTotal);
        document.querySelector('[data-vat-total]').textContent = amount(vatTotal);
        document.querySelector('[data-grand-total]').textContent = amount(grandTotal);
        document.querySelector('.vat-total-row').hidden = !isVatPayer();
    };

    const bindRow = (row) => {
        row.querySelectorAll('input, select, textarea').forEach((input) => {
            input.addEventListener('input', calculate);
            input.addEventListener('change', calculate);
        });

        row.querySelector('[data-remove-item]').addEventListener('click', () => {
            if (itemsContainer.querySelectorAll('[data-item]').length === 1) {
                row.querySelector('textarea').value = '';
                row.querySelector('[data-price]').value = '';
                row.querySelector('[data-discount]').value = '0';
                row.querySelector('[data-quantity]').value = '1';
            } else {
                row.remove();
            }
            calculate();
        });
    };

    document.getElementById('add-item').addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        itemsContainer.insertAdjacentHTML('beforeend', html);
        const row = itemsContainer.lastElementChild;
        bindRow(row);
        row.querySelector('textarea').focus();
        calculate();
    });

    issuerSelect.addEventListener('change', () => {
        const option = issuerSelect.options[issuerSelect.selectedIndex];
        const vat = option.dataset.vat === '1';
        vatOption.hidden = !vat;
        if (!vat) vatCheckbox.checked = false;
        document.getElementById('language-select').value = option.dataset.language || 'ka';
        const templateOption = form.querySelector(`input[name="template_key"][value="${option.dataset.template || 'standard'}"]`);
        if (templateOption) templateOption.checked = true;
        calculate();
    });

    vatCheckbox.addEventListener('change', calculate);
    currencySelect.addEventListener('change', calculate);
    itemsContainer.querySelectorAll('[data-item]').forEach(bindRow);
    calculate();
})();
