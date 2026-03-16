jQuery(document).ready(function ($) {

    // Function to handle showing different states in a notice/box
    function handleNoticeStates($container) {
        var $initial = $container.find('.clth-state-initial');
        var $review = $container.find('.clth-state-review');
        var $support = $container.find('.clth-state-support');

        $container.find('.clth-btn-happy').on('click', function (e) {
            e.preventDefault();
            $initial.hide();
            $review.show();
        });

        $container.find('.clth-btn-unhappy').on('click', function (e) {
            e.preventDefault();
            $initial.hide();
            $support.show();
        });
    }

    // Initialize states for site-wide notice
    var $siteWideNotice = $('#clth-review-notice');
    if ($siteWideNotice.length) {
        handleNoticeStates($siteWideNotice);

        // Handle dismiss via AJAX
        $siteWideNotice.find('.clth-btn-dismiss, .clth-btn-dismiss-icon, .clth-external-link').on('click', function (e) {
            var dismissReason = '';

            if (!$(this).hasClass('clth-external-link')) {
                e.preventDefault();
            }

            // Check if this is the "I already did" button
            if ($(this).text().trim() === 'I already did') {
                dismissReason = 'already_reviewed';
            } else if ($(this).text().trim() === 'No, thanks') {
                dismissReason = 'needs_help_dismissed';
            } else if ($(this).text().trim() === 'Create a Support Ticket') {
                dismissReason = 'needs_help_clicked';
            } else if ($(this).text().trim() === 'Leave a Review') {
                dismissReason = 'leave_review_clicked';
            }

            $.post(clth_admin_notices_data.ajaxurl, {
                action: 'clth_dismiss_review_notice',
                nonce: clth_admin_notices_data.nonce,
                dismiss_reason: dismissReason
            }, function (response) {
                if (response.success) {
                    $siteWideNotice.slideUp();
                }
            });
        });
    }

    // Initialize states for sidebar box
    var $sidebarBox = $('.clth-sidebar-box-review');
    if ($sidebarBox.length) {
        handleNoticeStates($sidebarBox);

        $sidebarBox.find('.clth-external-link').on('click', function () {
            // We just let them click it and keep the box as-is for future use
        });
    }

});
