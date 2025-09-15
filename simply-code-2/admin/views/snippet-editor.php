<div class="sc-editor-container">
    <div class="sc-tabs-nav">
        <button type="button" class="sc-tab-button active" data-tab="php">
            <?php _e('PHP', 'simply-code'); ?>
        </button>
        <button type="button" class="sc-tab-button" data-tab="javascript">
            <?php _e('JavaScript', 'simply-code'); ?>
        </button>
        <button type="button" class="sc-tab-button" data-tab="css">
            <?php _e('CSS', 'simply-code'); ?>
        </button>
    </div>
    
    <div class="sc-tabs-content">
        <div class="sc-tab-pane active" data-tab-content="php">
            <textarea 
                name="sc_php_code" 
                class="sc-code-editor" 
                placeholder="<?php esc_attr_e('Enter your PHP code here...', 'simply-code'); ?>"
                rows="15"><?php echo esc_textarea($php_code); ?></textarea>
            <p class="description"><?php _e('Note: PHP opening tags are not required.', 'simply-code'); ?></p>
        </div>
        
        <div class="sc-tab-pane" data-tab-content="javascript">
            <textarea 
                name="sc_js_code" 
                class="sc-code-editor" 
                placeholder="<?php esc_attr_e('Enter your JavaScript code here...', 'simply-code'); ?>"
                rows="15"><?php echo esc_textarea($js_code); ?></textarea>
        </div>
        
        <div class="sc-tab-pane" data-tab-content="css">
            <textarea 
                name="sc_css_code" 
                class="sc-code-editor" 
                placeholder="<?php esc_attr_e('Enter your CSS code here...', 'simply-code'); ?>"
                rows="15"><?php echo esc_textarea($css_code); ?></textarea>
        </div>
    </div>
</div>

<style>
.tab-content {
    display: none;
    margin-top: 1em;
}
.tab-content.active {
    display: block;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejo de pestañas
    const tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            // Remover clase activa de todas las pestañas y contenidos
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Activar la pestaña seleccionada
            tab.classList.add('nav-tab-active');
            document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
        });
    });

    // Manejo de plantillas
    const templateSelect = document.getElementById('template');
    if (templateSelect) {
        templateSelect.addEventListener('change', function() {
            if (this.value && confirm('¿Seguro que quieres cargar esta plantilla? Se sobrescribirá el código actual.')) {
                document.getElementById('snippet-form').submit();
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const phpTextarea = document.querySelector('textarea[name="php_code"]');
    const hookContainer = document.getElementById('detected-hooks');
    const refreshButton = document.getElementById('refresh-hooks');
    const addManualButton = document.getElementById('add-manual-hook');
    
    let detectTimeout;
    let criticalHooks = <?php echo json_encode($critical_hooks); ?>;
    
    function detectHooks() {
        const phpCode = phpTextarea.value;
        if (!phpCode.trim()) return;
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'simply_code_detect_hooks',
                php_code: phpCode,
                nonce: '<?php echo wp_create_nonce("simply_code_detect_hooks"); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderHooksList(data.data.hooks);
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    function renderHooksList(hooks) {
        // Obtener prioridades existentes
        const existingInputs = hookContainer.querySelectorAll('input[type="number"]');
        const existingPriorities = {};
        existingInputs.forEach(input => {
            const hookName = input.name.match(/hook_priorities\[([^\]]+)\]/)?.[1];
            if (hookName) {
                existingPriorities[hookName] = input.value;
            }
        });
        
        hookContainer.innerHTML = '';
        
        hooks.forEach(hook => {
            const priority = existingPriorities[hook.name] || hook.priority;
            const isCritical = criticalHooks.hasOwnProperty(hook.name);
            
            const hookDiv = document.createElement('div');
            hookDiv.className = 'hook-priority-item';
            hookDiv.style.cssText = 'margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;';
            
            hookDiv.innerHTML = `
                <label style="display: flex; align-items: center; gap: 10px;">
                    <strong>${hook.name}</strong>
                    <span class="badge" style="background: ${hook.type === 'action' ? '#00a32a' : '#0073aa'}; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                        ${hook.type}
                    </span>
                    <input type="number" 
                           name="hook_priorities[${hook.name}]" 
                           value="${priority}" 
                           min="1" 
                           max="9999" 
                           style="width: 80px;">
                    <span class="description">Prioridad (menor número = se ejecuta antes)</span>
                    ${isCritical ? '<span style="color: #d63638; font-size: 12px;">⚠️ Hook crítico</span>' : ''}
                    <button type="button" class="button-link remove-hook" data-hook="${hook.name}" style="color: #d63638;">✕</button>
                </label>
            `;
            
            hookContainer.appendChild(hookDiv);
        });
        
        // Agregar event listeners para remover hooks
        hookContainer.querySelectorAll('.remove-hook').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.hook-priority-item').remove();
            });
        });
    }
    
    function addManualHook() {
        const hookName = prompt('Nombre del hook:');
        if (!hookName) return;
        
        const hookType = confirm('¿Es una acción? (Aceptar = Acción, Cancelar = Filtro)') ? 'action' : 'filter';
        const priority = prompt('Prioridad (1-9999):', '10');
        
        if (!priority || isNaN(priority)) return;
        
        renderHooksList([{
            name: hookName,
            type: hookType,
            priority: parseInt(priority),
            auto_detected: false
        }]);
    }
    
    // Event listeners
    phpTextarea.addEventListener('input', function() {
        clearTimeout(detectTimeout);
        detectTimeout = setTimeout(detectHooks, 1500);
    });
    
    refreshButton.addEventListener('click', detectHooks);
    addManualButton.addEventListener('click', addManualHook);
    
    // Detectar hooks al cargar si hay código PHP
    if (phpTextarea.value.trim()) {
        detectHooks();
    }
});
</script>
