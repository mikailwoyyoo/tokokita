'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-menu-toggle]');
    var sidebar = document.getElementById('sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth <= 760 && sidebar.classList.contains('open')
                && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    document.querySelectorAll('[data-image-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            var preview = document.querySelector('[data-image-preview]');
            if (!preview) return;
            if (input.files && input.files[0]) {
                preview.src = URL.createObjectURL(input.files[0]);
                preview.hidden = false;
            } else {
                preview.removeAttribute('src');
                preview.hidden = true;
            }
        });
    });

    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    var barcodeInput = document.querySelector('[data-barcode-input]');
    if (barcodeInput) {
        barcodeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') event.preventDefault();
        });
    }

    document.querySelectorAll('[data-barcode-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            var popup = window.open('', '_blank', 'width=420,height=260');
            if (!popup) {
                window.alert('Izinkan pop-up browser untuk mencetak label barcode.');
                return;
            }

            var barcode = button.closest('[data-barcode-label]').querySelector('.ean13-barcode').outerHTML;
            popup.document.open();
            popup.document.write('<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Label Barcode</title><style>@page{size:58mm 30mm;margin:0}*{box-sizing:border-box}body{display:grid;width:58mm;height:30mm;margin:0;place-items:center;font-family:Arial,sans-serif}.ean13-barcode{width:50mm;height:auto}</style></head><body>' + barcode + '</body></html>');
            popup.document.close();
            window.setTimeout(function () {
                popup.focus();
                popup.print();
                popup.close();
            }, 300);
        });
    });

    var posForm = document.querySelector('[data-pos-form]');
    if (!posForm) return;

    var productCards = Array.prototype.slice.call(document.querySelectorAll('[data-product-card]'));
    var cart = new Map();
    var cartContainer = document.querySelector('[data-cart-items]');
    var cartInput = document.querySelector('[data-cart-input]');
    var cartCount = document.querySelector('[data-cart-count]');
    var discountField = document.querySelector('[data-discount-percent]');
    var discountInput = document.querySelector('[data-discount-input]');
    var cashInput = document.querySelector('[data-cash-input]');
    var paidInput = document.querySelector('[data-paid-input]');
    var checkoutButton = document.querySelector('[data-checkout-button]');
    var customerSelect = document.querySelector('[name="customer_id"]');
    var productSearch = document.querySelector('[data-product-search]');
    var subtotalOutput = document.querySelector('[data-subtotal]');
    var discountOutput = document.querySelector('[data-discount-value]');
    var taxOutput = document.querySelector('[data-tax]');
    var totalOutput = document.querySelector('[data-total]');
    var changeOutput = document.querySelector('[data-change]');
    var noResults = document.querySelector('[data-no-results]');
    var paymentInputs = Array.prototype.slice.call(document.querySelectorAll('[data-payment-method]'));
    var paymentMethod = 'Tunai';

    function formatMoney(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(Math.max(0, value || 0));
    }

    function productFromCard(card) {
        return {
            id: Number(card.dataset.productId),
            name: card.dataset.productName,
            price: Number(card.dataset.productPrice),
            stock: Number(card.dataset.productStock)
        };
    }

    function totals() {
        var subtotal = 0;
        cart.forEach(function (item) {
            subtotal += item.price * item.quantity;
        });
        var percent = Math.min(100, Math.max(0, Number(discountField.value) || 0));
        var discount = Math.round(subtotal * percent) / 100;
        var tax = Math.round((subtotal - discount) * 11) / 100;
        return {
            subtotal: subtotal,
            discount: discount,
            tax: tax,
            total: subtotal - discount + tax
        };
    }

    function updateSummary() {
        var amount = totals();
        var received = paymentMethod === 'Tunai' ? Number(cashInput.value) || 0 : amount.total;
        var change = cart.size > 0 && paymentMethod === 'Tunai'
            ? Math.max(0, received - amount.total)
            : 0;
        var count = 0;
        cart.forEach(function (item) { count += item.quantity; });

        subtotalOutput.textContent = formatMoney(amount.subtotal);
        discountOutput.textContent = '− ' + formatMoney(amount.discount);
        taxOutput.textContent = formatMoney(amount.tax);
        totalOutput.textContent = formatMoney(amount.total);
        changeOutput.textContent = formatMoney(change);
        cartCount.textContent = String(count);
        discountInput.value = String(Math.min(100, Math.max(0, Number(discountField.value) || 0)));
        paidInput.value = String(received);
        checkoutButton.disabled = cart.size === 0 || !customerSelect.value
            || (paymentMethod === 'Tunai' && received < amount.total);
    }

    function renderCart() {
        cartContainer.replaceChildren();

        if (cart.size === 0) {
            var empty = document.createElement('div');
            empty.className = 'cart-empty';
            var icon = document.createElement('div');
            icon.className = 'empty-cart-icon';
            icon.textContent = '🛒';
            var title = document.createElement('strong');
            title.textContent = 'Keranjang masih kosong';
            var hint = document.createElement('span');
            hint.textContent = 'Pilih produk untuk memulai pesanan';
            empty.append(icon, title, hint);
            cartContainer.appendChild(empty);
        } else {
            cart.forEach(function (item, id) {
                var row = document.createElement('div');
                row.className = 'cart-item';

                var info = document.createElement('div');
                info.className = 'cart-item-info';
                var name = document.createElement('strong');
                name.textContent = item.name;
                var price = document.createElement('span');
                price.textContent = formatMoney(item.price) + ' / item';
                info.append(name, price);

                var actions = document.createElement('div');
                actions.className = 'cart-item-actions';
                var decrease = document.createElement('button');
                decrease.type = 'button';
                decrease.className = 'quantity-button';
                decrease.textContent = '−';
                decrease.setAttribute('aria-label', 'Kurangi ' + item.name);
                decrease.addEventListener('click', function () { changeQuantity(id, -1); });
                var quantity = document.createElement('span');
                quantity.className = 'cart-quantity';
                quantity.textContent = String(item.quantity);
                var increase = document.createElement('button');
                increase.type = 'button';
                increase.className = 'quantity-button';
                increase.textContent = '+';
                increase.setAttribute('aria-label', 'Tambah ' + item.name);
                increase.disabled = item.quantity >= item.stock;
                increase.addEventListener('click', function () { changeQuantity(id, 1); });
                actions.append(decrease, quantity, increase);

                var lineTotal = document.createElement('strong');
                lineTotal.className = 'cart-line-total';
                lineTotal.textContent = formatMoney(item.price * item.quantity);
                row.append(info, actions, lineTotal);
                cartContainer.appendChild(row);
            });
        }

        cartInput.value = JSON.stringify(Array.from(cart.values()).map(function (item) {
            return { id: item.id, quantity: item.quantity };
        }));
        updateSummary();
    }

    function changeQuantity(id, difference) {
        var item = cart.get(id);
        if (!item) return;
        item.quantity += difference;
        if (item.quantity <= 0) cart.delete(id);
        else if (item.quantity > item.stock) item.quantity = item.stock;
        renderCart();
    }

    function addProductToCart(card) {
        var product = productFromCard(card);
        var existing = cart.get(product.id);
        if (existing) {
            if (existing.quantity < product.stock) existing.quantity += 1;
        } else if (product.stock > 0) {
            product.quantity = 1;
            cart.set(product.id, product);
        }
        renderCart();
    }

    productCards.forEach(function (card) {
        var button = card.querySelector('[data-add-product]');
        if (!button) return;
        button.addEventListener('click', function () {
            addProductToCart(card);
        });
    });

    document.querySelectorAll('[data-category-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('[data-category-filter]').forEach(function (chip) {
                chip.classList.toggle('active', chip === button);
            });
            filterProducts();
        });
    });

    function filterProducts() {
        var query = (productSearch.value || '').trim().toLocaleLowerCase('id-ID');
        var activeCategory = document.querySelector('[data-category-filter].active');
        var category = activeCategory ? activeCategory.dataset.categoryFilter : 'all';
        var visible = 0;

        productCards.forEach(function (card) {
            var matchesText = card.dataset.productSearchText.toLocaleLowerCase('id-ID').includes(query)
                || card.dataset.productBarcode.toLocaleLowerCase('id-ID').includes(query);
            var matchesCategory = category === 'all' || card.dataset.productCategory === category;
            var show = matchesText && matchesCategory;
            card.hidden = !show;
            if (show) visible += 1;
        });
        if (noResults) noResults.hidden = visible > 0;
    }

    productSearch.addEventListener('input', filterProducts);
    productSearch.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        var barcode = productSearch.value.trim();
        var productCard = productCards.find(function (card) {
            return card.dataset.productBarcode === barcode;
        });
        if (!productCard || Number(productCard.dataset.productStock) < 1) return;
        addProductToCart(productCard);
        productSearch.value = '';
        filterProducts();
    });
    discountField.addEventListener('input', updateSummary);
    cashInput.addEventListener('input', updateSummary);
    customerSelect.addEventListener('change', updateSummary);

    paymentInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.checked) return;
            paymentMethod = input.value;
            document.querySelectorAll('.payment-method').forEach(function (label) {
                label.classList.toggle('active', label.contains(input));
            });
            var cashField = document.querySelector('.cash-field');
            cashField.hidden = paymentMethod !== 'Tunai';
            cashInput.required = paymentMethod === 'Tunai';
            updateSummary();
        });
    });

    document.querySelectorAll('[data-cash-shortcut]').forEach(function (button) {
        button.addEventListener('click', function () {
            var amount = totals().total;
            if (button.dataset.cashShortcut !== 'exact') {
                amount = Math.ceil(amount / 50000) * 50000 + Number(button.dataset.cashShortcut);
            }
            cashInput.value = String(Math.ceil(amount));
            updateSummary();
        });
    });

    if (productSearch) {
        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                productSearch.focus();
            }
        });
    }

    posForm.addEventListener('submit', function (event) {
        updateSummary();
        if (cart.size === 0 || !customerSelect.value || checkoutButton.disabled) {
            event.preventDefault();
        }
    });

    renderCart();
});
