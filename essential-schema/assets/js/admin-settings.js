jQuery(document).ready(function($) {
    var $orgType = $('select[name="es_org[org_type]"]');
    var $priceRangeRow = $('input[name="es_org[price_range]"]').closest('tr');

    function togglePriceRange() {
        if ($orgType.val() === 'LocalBusiness') {
            $priceRangeRow.show();
        } else {
            $priceRangeRow.hide();
        }
    }

    $orgType.on('change', togglePriceRange);
    togglePriceRange();
});