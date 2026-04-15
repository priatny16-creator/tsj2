function calculateRow(input) {
    var price = parseFloat(input.getAttribute('data-price')) || 0;
    var prev = parseFloat(input.getAttribute('data-prev')) || 0;
    var current = parseFloat(input.value) || 0;

    var tariffId = input.name.replace('current_', '');
    var totalEl = document.getElementById('total_' + tariffId);

    if (current > prev) {
        var consumption = current - prev;
        var amount = consumption * price;
        totalEl.innerHTML = formatMoney(amount) + ' \u20BD';
    } else {
        totalEl.innerHTML = '0,00 \u20BD';
    }

    calculateGrandTotal();
}

function calculateGrandTotal() {
    var inputs = document.querySelectorAll('input[name^="current_"]');
    var grandTotal = 0;
    var details = [];

    inputs.forEach(function(input) {
        var price = parseFloat(input.getAttribute('data-price')) || 0;
        var prev = parseFloat(input.getAttribute('data-prev')) || 0;
        var current = parseFloat(input.value) || 0;

        if (current > prev) {
            var consumption = current - prev;
            var amount = consumption * price;
            grandTotal += amount;

            var row = input.closest('.reading-row');
            if (row) {
                var label = row.querySelector('.reading-label');
                if (label) {
                    details.push(label.textContent + ': ' + formatMoney(amount) + ' \u20BD');
                }
            }
        }
    });

    var grandTotalEl = document.getElementById('grandTotal');
    var grandDetailEl = document.getElementById('grandDetail');

    if (grandTotalEl) {
        grandTotalEl.innerHTML = formatMoney(grandTotal) + ' \u20BD';
    }
    if (grandDetailEl) {
        grandDetailEl.textContent = details.join(' | ');
    }
}

function formatMoney(num) {
    return num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modals = document.querySelectorAll('.modal-overlay.active');
        modals.forEach(function(m) { m.classList.remove('active'); });
    }
});
