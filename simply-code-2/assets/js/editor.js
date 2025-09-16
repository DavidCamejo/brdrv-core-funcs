/**
 * Simply Code Editor JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Tab functionality
        $('.sc-tab-button').on('click', function(e) {
            e.preventDefault();
            
            var tabId = $(this).data('tab');
            
            // Remove active classes
            $('.sc-tab-button').removeClass('active');
            $('.sc-tab-pane').removeClass('active');
            
            // Add active classes
            $(this).addClass('active');
            $('#' + tabId).addClass('active');
        });
        
        // Auto-select first tab if none selected
        if ($('.sc-tab-button.active').length === 0) {
            $('.sc-tab-button:first').click();
        }
        
        // Confirm deletion
        $(document).on('click', '.sc-confirm-delete', function(e) {
            if (!confirm(simplyCodeEditor.confirmDelete)) {
                e.preventDefault();
            }
        });
        
        // Syntax highlighting toggle (optional enhancement)
        $('.sc-toggle-syntax').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            var $textarea = $('#' + target);
            
            if ($textarea.hasClass('syntax-highlighted')) {
                // Remove syntax highlighting
                $textarea.removeClass('syntax-highlighted');
                $(this).text(simplyCodeEditor.enableSyntax);
            } else {
                // Add syntax highlighting (would require additional libraries)
                $textarea.addClass('syntax-highlighted');
                $(this).text(simplyCodeEditor.disableSyntax);
            }
        });
        
        // Auto-resize textareas
        $('textarea.code').each(function() {
            this.setAttribute('style', 'height:' + (this.scrollHeight) + 'px;overflow-y:hidden;');
        }).on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Form validation
        $('form').on('submit', function() {
            var $nameField = $('#snippet_name');
            if ($nameField.val().trim() === '') {
                alert(simplyCodeEditor.nameRequired);
                $nameField.focus();
                return false;
            }
            return true;
        });
    });
    
})(jQuery);
