jQuery(document).ready(function ($) {
    // Initialize Color Picker
    $('.clth-color-field').wpColorPicker();

    const iconStyleRadios = $('input[name="clth_icon_style"]');
    const customIconRow = $('.clth-custom-icon-row');
    const iconColorRow = $('.clth-icon-color-row');
    const iconThicknessRow = $('.clth-icon-thickness-row');
    const colorWarning = $('.clth-custom-icon-color-warning');

    function toggleRows() {
        const selectedValue = $('input[name="clth_icon_style"]:checked').val();

        // Always show the color row
        iconColorRow.show();

        if (selectedValue === 'custom') {
            customIconRow.show();
            iconThicknessRow.hide();
            colorWarning.show();
        } else {
            customIconRow.hide();
            iconThicknessRow.show();
            colorWarning.hide();
        }
    }

    iconStyleRadios.on('change', toggleRows);
    toggleRows(); // Initial check

    // Tooltip options toggle
    const tooltipCheckbox = $('#clth_enable_tooltip');
    const tooltipTextOptions = $('.tooltip-text-options');

    tooltipCheckbox.on('change', function () {
        if ($(this).is(':checked')) {
            tooltipTextOptions.show();
        } else {
            tooltipTextOptions.hide();
        }
    });

    // Media Uploader
    let frame;
    $('#clth_upload_icon_button').on('click', function (e) {
        e.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Select Custom Icon',
            button: {
                text: 'Use this icon'
            },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            $('#clth_custom_icon_url').val(attachment.url);
            $('#clth_custom_icon_preview').html('<img src="' + attachment.url + '" style="max-width: 50px; max-height: 50px;" />');
        });

        frame.open();
    });
});
