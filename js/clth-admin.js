jQuery(document).ready(function($){
    $('.clth-color-picker').wpColorPicker();

    // Toggle tooltip text options visibility
    const tooltipCheckbox = document.getElementById( 'clth_enable_tooltip' );
    const tooltipTextOptions = document.querySelectorAll( '.tooltip-text-options' );

    if (tooltipCheckbox) {
        tooltipCheckbox.addEventListener( 'change', function () {
            tooltipTextOptions.forEach( option => {
                option.style.display = this.checked ? '' : 'none';
            } );
        } );
    }
});