/*!
 * Repeater.js
 */
jQuery(document).ready(function($) {
    $('.es-repeater').on('click', '.es-add-item', function(e) {
        e.preventDefault();
        var repeater = $(this).parent();
        var key = repeater.data('key');
        var index = repeater.children('.es-repeater-item').length;
        var html = '<div class="es-repeater-item">' +
                   '<p><label>Question</label><br>' +
                   '<input type="text" name="es_faq[' + key + '][' + index + '][question]" class="regular-text"></p>' +
                   '<p><label>Answer</label><br>' +
                   '<textarea name="es_faq[' + key + '][' + index + '][answer]" rows="5" class="regular-text"></textarea></p>' +
                   '<button type="button" class="button es-remove-item">Remove</button>' +
                   '</div>';
        repeater.append(html);
    });

    $('.es-repeater').on('click', '.es-remove-item', function(e) {
        e.preventDefault();
        $(this).parent().remove();
    });
});