// returns-repeater.js
jQuery(function($) {
    console.log('returns-repeater.js loaded successfully');
    if ($('.es-returns-repeater').length > 0) {
        console.log('Repeater div found');
    } else {
        console.log('Repeater div not found');
    }
    $('.es-returns-repeater').on('click', '.es-returns-add-item', function() {
        console.log('Add button clicked');
        var $repeater = $(this).closest('.es-returns-repeater');
        console.log('Repeater:', $repeater.length);
        var $lastItem = $repeater.find('.es-returns-repeater-item').last();
        console.log('Last item:', $lastItem.length);
        if ($lastItem.length === 0) {
            console.log('No item to clone - add logic for initial item');
            return;
        }
        var $newItem = $lastItem.clone(true);
        console.log('Item cloned');
        $newItem.find('input[type="text"], input[type="number"], textarea').val('');
        $newItem.find('select').val($newItem.find('select option:first').val());
        $newItem.find('input[type="checkbox"]').prop('checked', false);
        var index = $repeater.find('.es-returns-repeater-item').length;
        $newItem.find('[name]').each(function() {
            var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
            $(this).attr('name', name);
        });
        $repeater.find('.es-returns-add-item').before($newItem);
        console.log('New item added with index ' + index);
    });
    $('.es-returns-repeater').on('click', '.es-returns-remove-item', function() {
        console.log('Remove button clicked');
        var $repeater = $(this).closest('.es-returns-repeater');
        if ($repeater.find('.es-returns-repeater-item').length > 1) {
            $(this).closest('.es-returns-repeater-item').remove();
            console.log('Item removed');
        } else {
            console.log('Cannot remove last item');
        }
    });
});