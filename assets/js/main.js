document.addEventListener('DOMContentLoaded', function() {
    // Initialize date range picker if available
    if ($.fn.daterangepicker && document.getElementById('daterange')) {
        $('#daterange').daterangepicker({
            startDate: moment($('#start_date').val()),
            endDate: moment($('#end_date').val()),
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end, label) {
            $('#start_date').val(start.format('YYYY-MM-DD'));
            $('#end_date').val(end.format('YYYY-MM-DD'));
        });
    }
    
    // Update order totals
    const updateOrderTotals = function() {
        const quantityInputs = document.querySelectorAll('.item-quantity');
        let totalItems = 0;
        let totalAmount = 0;
        
        quantityInputs.forEach(input => {
            const quantity = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            
            totalItems += quantity;
            totalAmount += quantity * price;
        });
        
        if (document.getElementById('total-items')) {
            document.getElementById('total-items').textContent = totalItems;
        }
        
        if (document.getElementById('total-amount')) {
            document.getElementById('total-amount').textContent = totalAmount.toFixed(2);
        }
        
        // Enable/disable submit button based on items selected
        const submitButton = document.getElementById('submit-order');
        if (submitButton) {
            submitButton.disabled = totalItems === 0;
        }
    };
    
    // Add event listeners to quantity inputs
    const quantityInputs = document.querySelectorAll('.item-quantity');
    if (quantityInputs.length > 0) {
        quantityInputs.forEach(input => {
            input.addEventListener('change', updateOrderTotals);
        });
        
        // Initialize totals
        updateOrderTotals();
    }
});