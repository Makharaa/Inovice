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

    const amount = (tetri) => `${(tetri / 100).toFixed(2)} ${currencySelect.value}`;

    const isVatPayer = () => issuerSelect.options[issuerSelect.selectedIndex]?.dataset.vat === '1';

    // Mirrors App\Services\InvoiceCalculator: integer tetri, half-up rounding, so the live
    // preview always equals what the server stores and prints.
    const VAT_BASIS = 1800;
    const MAX_LINE = 10000000000;
    const round = (value) => Math.round(Number(value.toPrecision(15)));
    const netFromGross = (gross) => {
        const divisor = 10000 + VAT_BASIS;
        return Math.floor((gross * 20000 + divisor) / (2 * divisor));
    };
    const positive = (input) => {
        const number = Number(input.value || 0);
        return Number.isFinite(number) && number > 0 ? number : 0;
    };

    const calculate = () => {
        let subtotal = 0;
        let discountTotal = 0;
        let vatTotal = 0;
        let grandTotal = 0;
        const vatPayer = isVatPayer();
        const includeVat = vatPayer && vatCheckbox.checked;

        itemsContainer.querySelectorAll('[data-item]').forEach((row) => {
            const quantity = round(positive(row.querySelector('[data-quantity]')) * 1000);
            const price = round(positive(row.querySelector('[data-price]')) * 10000);
            const discountBasis = round(Math.min(100, positive(row.querySelector('[data-discount]'))) * 100);
            const product = quantity * price;

            if (quantity <= 0 || product > MAX_LINE * 100000) {
                row.querySelector('[data-line-total]').textContent = amount(0);
                return;
            }

            const base = Math.floor((product + 50000) / 100000);
            const discount = Math.floor((base * discountBasis + 5000) / 10000);
            const discounted = base - discount;
            let baseNet = base;
            let discountNet = discount;
            let net = discounted;
            let vat = 0;
            let total = discounted;

            if (includeVat) {
                net = netFromGross(discounted);
                vat = total - net;
                baseNet = netFromGross(base);
                discountNet = baseNet - net;
            } else if (vatPayer) {
                vat = Math.floor((net * VAT_BASIS + 5000) / 10000);
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
        document.querySelector('.vat-total-row').hidden = !vatPayer;
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

    form.querySelectorAll('[data-confirm-submit]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (!window.confirm(button.dataset.confirmSubmit)) {
                event.preventDefault();
            }
        });
    });

    vatCheckbox.addEventListener('change', calculate);
    currencySelect.addEventListener('change', calculate);
    itemsContainer.querySelectorAll('[data-item]').forEach(bindRow);
    calculate();
})();
