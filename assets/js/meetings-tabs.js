jQuery(document).ready(function($) {
    // Handle tab switching
    $('.lcd-meetings-tabs-nav .tab-button').on('click', function() {
        const tabId = $(this).data('tab');
        
        // Update active state of tab buttons
        $('.lcd-meetings-tabs-nav .tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Show selected tab content
        $('.tab-content').removeClass('active');
        $(`#${tabId}-tab`).addClass('active');
    });
}); 