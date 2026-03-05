jQuery(document).ready(function($) {
    function updatePrice() {
        var basePrice = parseFloat($('#product-price').data('base-price'));
        var additionalPrice = 0;

        $('.custom-field-checkbox:checked').each(function() {
            additionalPrice += parseFloat($(this).val());
        });
        if (isNaN(basePrice)) {
            basePrice = 0;
        }

        var totalPrice = basePrice + additionalPrice;

        $('#product-price').text(custom_fields_vars.currency_symbol + basePrice.toFixed(2));
        $('#custom-fields-price').text(custom_fields_vars.currency_symbol + additionalPrice.toFixed(2));
        $('#total-price').text(custom_fields_vars.currency_symbol + totalPrice.toFixed(2));
    }

    function updateBasePrice(newPrice) {
        $('#product-price').data('base-price', newPrice);
        updatePrice();
    }

    // Initial price update on page load
    updatePrice();

    $('.custom-field-checkbox').on('change', function() {
        updatePrice();
    });

    // Handle variation changes
    $('form.variations_form').on('found_variation', function(event, variation) {
        var variationPrice = parseFloat(variation.display_price);
        updateBasePrice(variationPrice);
    });

    // Ensure price update when variation is cleared
    $('form.variations_form').on('reset_data', function() {
        var basePrice = parseFloat($('.single_variation .woocommerce-Price-amount').first().text().replace(/[^0-9.-]+/g, ""));
        updateBasePrice(basePrice);
    });
});
