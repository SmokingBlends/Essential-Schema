/*!
 * Repeater.js
 */
jQuery(document).ready(function($) {
    $('.es-repeater').each(function() {
        var repeater = $(this);
        var key = repeater.data('key');
        repeater.on('click', '.es-add-item', function() {
            var count = repeater.find('.es-repeater-item').length;
            var html = '<div class="es-repeater-item">' +
                '<p><label>Question</label><br>' +
                '<input type="text" name="es_faq[' + key + '][' + count + '][question]" class="regular-text"></p>' +
                '<p><label>Answer</label><br>' +
                '<textarea name="es_faq[' + key + '][' + count + '][answer]" rows="5" class="regular-text"></textarea></p>' +
                '<button type="button" class="button es-remove-item">Remove</button>' +
                '</div>';
            repeater.append(html);
        });
        repeater.on('click', '.es-remove-item', function() {
            $(this).parent().remove();
        });
    });
});