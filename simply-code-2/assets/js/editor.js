jQuery(document).ready(function($) {
    // Basic syntax highlighting
    var $textarea = $('#snippet_content');
    var $typeSelect = $('#snippet_type');
    
    // Simple syntax highlighting based on type
    function updateSyntaxHighlighting() {
        var type = $typeSelect.val();
        $textarea.removeClass('php js css').addClass(type);
    }
    
    if ($textarea.length && $typeSelect.length) {
        updateSyntaxHighlighting();
        $typeSelect.change(updateSyntaxHighlighting);
    }
    
    // Form validation
    $('form').submit(function() {
        var $id = $('#snippet_id');
        if ($id.val().trim() === '') {
            alert(simplyCodeEditor.nameRequired);
            $id.focus();
            return false;
        }
        return true;
    });
    
    // Confirm delete
    $('.button-link-delete').click(function() {
        return confirm(simplyCodeEditor.confirmDelete);
    });
});
