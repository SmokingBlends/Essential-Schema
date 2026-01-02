// File: assets/js/shipping-repeater.js
jQuery(document).ready(function($) {
    $('.es-shipping-repeater').each(function() {
        var repeater = $(this);
        var key = repeater.data('key');
        repeater.on('click', '.es-shipping-add-item', function() {
            var count = repeater.find('.es-shipping-repeater-item').length;
            var html = '<div class="es-shipping-repeater-item">' +
                '<p><label>Shipping Rate</label><br>' +
                '<input type="number" name="es_shipping[' + key + '][' + count + '][rate]" min="0" step="0.01" class="regular-text"></p>' +
                '<p><label>Currency</label><br>' +
                '<input type="text" name="es_shipping[' + key + '][' + count + '][currency]" class="regular-text"></p>' +
                '<p><label>Handling Min Days</label><br>' +
                '<input type="number" name="es_shipping[' + key + '][' + count + '][handling_min]" min="0" class="regular-text"></p>' +
                '<p><label>Handling Max Days</label><br>' +
                '<input type="number" name="es_shipping[' + key + '][' + count + '][handling_max]" min="0" class="regular-text"></p>' +
                '<p><label>Transit Min Days</label><br>' +
                '<input type="number" name="es_shipping[' + key + '][' + count + '][transit_min]" min="0" class="regular-text"></p>' +
                '<p><label>Transit Max Days</label><br>' +
                '<input type="number" name="es_shipping[' + key + '][' + count + '][transit_max]" min="0" class="regular-text"></p>' +
                '<p><label>Shipping Description</label><br>' +
                '<input type="text" name="es_shipping[' + key + '][' + count + '][description]" class="regular-text"></p>' +
                '<p><label>Shipping Countries</label><br>' +
                '<textarea name="es_shipping[' + key + '][' + count + '][countries]" rows="5" class="regular-text"></textarea></p>' +
                '<button type="button" class="button es-shipping-remove-item">Remove Profile</button>' +
                '</div>';
            repeater.append(html);
        });
        repeater.on('click', '.es-shipping-remove-item', function() {
            $(this).parent().remove();
        });
    });
});