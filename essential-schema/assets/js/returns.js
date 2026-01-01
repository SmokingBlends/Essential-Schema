jQuery(document).ready(function($) {
    console.log('Returns JS loaded');
    var $category = $('select[name="es_domestic_returns[return_policy_category]"]');
    console.log('Category selector found:', $category.length > 0);
    var $daysWrap = $('.es-days-wrap');
    console.log('Days wrap count:', $daysWrap.length);
    var $otherWrap = $('.es-other-returns-wrap');
    console.log('Other wrap count:', $otherWrap.length);

    function toggleFields() {
        var val = $category.val();
        console.log('Toggle called, val:', val);
        if (val === 'MerchantReturnNotPermitted') {
            $daysWrap.closest('tr').hide();
            $otherWrap.closest('tr').hide();
        } else if (val === 'MerchantReturnUnlimitedWindow') {
            $daysWrap.closest('tr').hide();
            $otherWrap.closest('tr').show();
        } else {
            $daysWrap.closest('tr').show();
            $otherWrap.closest('tr').show();
        }
    }

    $category.on('change', function() {
        console.log('Category change event triggered');
        toggleFields();
    });
    toggleFields();
});