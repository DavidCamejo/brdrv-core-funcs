document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.sc-tab-button');
    const tabPanes = document.querySelectorAll('.sc-tab-pane');
    
    if (tabButtons.length > 0) {
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                
                // Remove active class from all buttons and panes
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanes.forEach(pane => pane.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Show corresponding pane
                const targetPane = document.querySelector(`[data-tab-content="${targetTab}"]`);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    }
    
    // Auto-resize textareas
    const textareas = document.querySelectorAll('.sc-code-editor');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Initialize height
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    });
});
