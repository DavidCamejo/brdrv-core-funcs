/**
 * PMPro Banda Dynamic Pricing - Frontend JavaScript con Storage v2.5
 * 
 * @version 2.5.0 - CORREGIDO para 2 usuarios base
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // ====
    // CONFIGURACIÓN Y VARIABLES GLOBALES - ACTUALIZADA
    // ====
    
    var PLUGIN_VERSION = '2.5.0';
    var DEBUG_MODE = typeof console !== 'undefined' && (typeof nextcloud_banda_pricing !== 'undefined' && nextcloud_banda_pricing.debug);
    var CACHE_EXPIRY = 60000; // 1 minuto
    
    // Cache simple para optimización
    var priceCache = {};
    var originalTextsCache = {};
    
    // ====
    // SISTEMA DE LOGGING (sin cambios)
    // ====
    
    function log(level, message, data) {
        if (!DEBUG_MODE) return;
        
        var prefix = '[PMPro Banda ' + level + ']';
        if (data && typeof data === 'object') {
            console.log(prefix, message, data);
        } else {
            console.log(prefix, message);
        }
    }
    
    function logError(message, data) { log('ERROR', message, data); }
    function logInfo(message, data) { log('INFO', message, data); }
    function logDebug(message, data) { log('DEBUG', message, data); }
    
    // ====
    // VALIDACIÓN DE DEPENDENCIAS (sin cambios)
    // ====
    
    function validateDependencies() {
        var checks = {
            nextcloud_banda_pricing: typeof nextcloud_banda_pricing !== 'undefined',
            jquery: typeof $ !== 'undefined',
            required_elements: $('#storage_space, #num_users, #payment_frequency').length >= 3
        };
        
        var missing = [];
        for (var check in checks) {
            if (!checks[check]) {
                missing.push(check);
            }
        }
        
        if (missing.length > 0) {
            logError('Missing dependencies', { missing: missing });
            return false;
        }
        
        logInfo('Dependencies validated successfully');
        return true;
    }
    
    // ====
    // CONFIGURACIÓN DINÁMICA - ACTUALIZADA PARA 2 USUARIOS BASE
    // ====
    
    function getConfig() {
        if (typeof nextcloud_banda_pricing === 'undefined') {
            logError('nextcloud_banda_pricing configuration not available');
            return null;
        }
        
        return {
            levelId: nextcloud_banda_pricing.level_id || 2,
            basePrice: parseFloat(nextcloud_banda_pricing.base_price) || 0,
            pricePerTb: parseFloat(nextcloud_banda_pricing.price_per_tb) || 70,
            pricePerUser: parseFloat(nextcloud_banda_pricing.price_per_user) || 10,
            baseUsersIncluded: parseInt(nextcloud_banda_pricing.base_users_included) || 2, // NUEVO
            baseStorageIncluded: parseInt(nextcloud_banda_pricing.base_storage_included) || 1, // NUEVO
            currencySymbol: nextcloud_banda_pricing.currency_symbol || 'R$',
            currentStorage: nextcloud_banda_pricing.current_storage || '1tb',
            currentUsers: parseInt(nextcloud_banda_pricing.current_users) || 2,
            usedSpaceTb: parseFloat(nextcloud_banda_pricing.used_space_tb) || 0,
            frequencyMultipliers: {
                'monthly': 1.0,
                'semiannual': 5.7,
                'annual': 10.8,
                'biennial': 20.4,
                'triennial': 28.8,
                'quadrennial': 36.0,
                'quinquennial': 42.0
            }
        };
    }
    
    // ====
    // SISTEMA DE CACHÉ (sin cambios)
    // ====
    
    function getCachedPrice(key) {
        var cached = priceCache[key];
        if (cached && (Date.now() - cached.timestamp) < CACHE_EXPIRY) {
            logDebug('Cache hit for price', { key: key });
            return cached.value;
        }
        return null;
    }
    
    function setCachedPrice(key, value) {
        priceCache[key] = {
            value: value,
            timestamp: Date.now()
        };
        logDebug('Price cached', { key: key });
    }
    
    // ====
    // GESTIÓN DE PRECIOS CON STORAGE Y USUARIOS - CORREGIDO PARA 2 USUARIOS BASE
    // ====
    
    function formatPrice(price, currencySymbol) {
        var formatted = Math.ceil(price).toFixed(2)
            .replace('.', ',')
            .replace(/(\d)(?=(\d{3})+\,)/g, '$1.');
        
        return currencySymbol + ' ' + formatted;
    }
    
    function getPeriodText(frequency) {
        var periods = {
            'monthly': ' (por mês)',
            'semiannual': ' (por 6 meses)',
            'annual': ' (por ano)',
            'biennial': ' (por 2 anos)',
            'triennial': ' (por 3 anos)',
            'quadrennial': ' (por 4 anos)',
            'quinquennial': ' (por 5 anos)'
        };
        
        return periods[frequency] || '';
    }
    
    /**
     * Calcula el precio total - CORREGIDO para 2 usuarios base
     */
    function calculateTotalPrice(config) {
        var storageValue = $('#storage_space').val() || config.currentStorage;
        var numUsers = parseInt($('#num_users').val()) || config.currentUsers;
        var frequencyValue = $('#payment_frequency').val() || 'monthly';
        
        // Verificar caché
        var cacheKey = storageValue + '_' + numUsers + '_' + frequencyValue + '_' + config.basePrice;
        var cached = getCachedPrice(cacheKey);
        if (cached !== null) {
            return cached;
        }
        
        // Calcular precio de almacenamiento (1TB incluido en base_price)
        var storageTb = parseInt(storageValue.replace('tb', ''));
        var additionalTb = Math.max(0, storageTb - config.baseStorageIncluded);
        var storagePrice = config.basePrice + (additionalTb * config.pricePerTb);
        
        // Calcular precio por usuarios (2 usuarios incluidos en base_price) - CORREGIDO
        var additionalUsers = Math.max(0, numUsers - config.baseUsersIncluded);
        var userPrice = additionalUsers * config.pricePerUser;
        
        // Precio combinado
        var combinedPrice = storagePrice + userPrice;
        
        // Aplicar multiplicador de frecuencia
        var multiplier = config.frequencyMultipliers[frequencyValue] || 1.0;
        
        var calculation = {
            storageValue: storageValue,
            numUsers: numUsers,
            frequencyValue: frequencyValue,
            storageTb: storageTb,
            additionalTb: additionalTb,
            additionalUsers: additionalUsers,
            storagePrice: storagePrice,
            userPrice: userPrice,
            combinedPrice: combinedPrice,
            multiplier: multiplier,
            totalPrice: Math.ceil(combinedPrice * multiplier)
        };
        
        setCachedPrice(cacheKey, calculation);
        
        logDebug('Price calculated with storage and users (2 users base)', calculation);
        return calculation;
    }
    
    function updatePriceDisplay(config) {
        try {
            var calculation = calculateTotalPrice(config);
            var formattedPrice = formatPrice(calculation.totalPrice, config.currencySymbol);
            var periodText = getPeriodText(calculation.frequencyValue);
            var displayText = formattedPrice + periodText;
            
            var $display = $('#total_price_display');
            if ($display.length) {
                $display.val(displayText);
                logDebug('Price display updated', { displayText: displayText });
            }
            
            // Trigger evento personalizado
            $(document).trigger('pmprobandaspricing:updated', [calculation]);
            
        } catch (error) {
            logError('Error updating price display', { error: error.message });
        }
    }
    
    // ====
    // GESTIÓN DE OPCIONES DE STORAGE
    // ====
    
    function storeOriginalTexts() {
        // Almacenar textos de opciones
        $('#storage_space option, #num_users option, #payment_frequency option').each(function() {
            var $option = $(this);
            var selectId = $option.closest('select').attr('id');
            var key = selectId + '_' + $option.val();
            originalTextsCache[key] = $option.text();
        });
        
        // ✅ NUEVO: Almacenar texto del label del precio
        var $priceLabel = $('.pmpro_checkout-field-price-display label');
        if ($priceLabel.length) {
            originalTextsCache.priceLabel = $priceLabel.text();
        }
        
        logDebug('Original texts stored', { count: Object.keys(originalTextsCache).length });
    }
    
    function updateStorageOptions(config) {
        var currentTb = parseInt(config.currentStorage.replace('tb', '')) || 1;
        var $storageSelect = $('#storage_space');
        
        if (!$storageSelect.length) return;
        
        $storageSelect.find('option').each(function() {
            var $option = $(this);
            var optionTb = parseInt($option.val().replace('tb', ''));
            var originalKey = 'storage_space_' + $option.val();
            var originalText = originalTextsCache[originalKey] || $option.text();
            
            // Limpiar texto previo
            var cleanText = originalText.replace(/ \(.*\)$/, '');
            
            if (optionTb < currentTb && optionTb < config.usedSpaceTb) {
                $option.prop('disabled', true);
                $option.text(cleanText + ' (Espaço insuficiente)');
            } else if (optionTb < currentTb) {
                $option.prop('disabled', true);
                $option.text(cleanText + ' (Downgrade não permitido)');
            } else {
                $option.prop('disabled', false);
                $option.text(cleanText);
            }
        });
        
        // Ajustar selección si está deshabilitada
        if ($storageSelect.find('option:selected').prop('disabled')) {
            var $firstEnabled = $storageSelect.find('option:not(:disabled)').first();
            if ($firstEnabled.length) {
                $storageSelect.val($firstEnabled.val()).trigger('change');
            }
        }
        
        // Mostrar alerta si es necesario
        if (config.usedSpaceTb > currentTb) {
            showStorageAlert(config);
        }
        
        logDebug('Storage options updated', { 
            currentTb: currentTb, 
            usedSpaceTb: config.usedSpaceTb 
        });
    }
    
    function showStorageAlert(config) {
        var alertId = 'storage_alert';
        
        if ($('#' + alertId).length) return;
        
        var alertHtml = '<div class="pmpro_message pmpro_error" id="' + alertId + '">' +
            '<strong>Atenção:</strong> Você está usando ' + config.usedSpaceTb.toFixed(2) + ' TB de armazenamento. ' +
            'Não é possível reduzir abaixo deste limite.' +
            '</div>';
        
        $('#storage_space').before(alertHtml);
        logInfo('Storage alert shown', { usedSpace: config.usedSpaceTb });
    }

    // ====
    // DETECCIÓN Y VISUALIZACIÓN DE PRORRATEO - NUEVO
    // ====

    function checkForProratedUpgrade(config) {
        try {
            // Verificar si hay una configuración previa (indicando upgrade dentro del mismo plan)
            var hasPreviousConfig = typeof nextcloud_banda_pricing !== 'undefined' && 
                                   nextcloud_banda_pricing.current_storage && 
                                   nextcloud_banda_pricing.current_users;
            
            if (!hasPreviousConfig) {
                logDebug('No previous config found - new subscription');
                return false;
            }
            
            // Obtener valores actuales (seleccionados) y anteriores (configuración guardada)
            var currentStorage = parseInt($('#storage_space').val().replace('tb', '')) || 1;
            var previousStorage = parseInt(nextcloud_banda_pricing.current_storage.replace('tb', '')) || 1;
            var currentUsers = parseInt($('#num_users').val()) || 2;
            var previousUsers = parseInt(nextcloud_banda_pricing.current_users) || 2;
            var currentFrequency = $('#payment_frequency').val() || 'monthly';
            var previousFrequency = nextcloud_banda_pricing.current_frequency || 'monthly';
            
            // Verificar upgrades dentro del mismo plan
            var isStorageUpgrade = currentStorage > previousStorage;
            var isUsersUpgrade = currentUsers > previousUsers;
            
            // Para frecuencia, solo considerar cambio si afecta el precio (raro caso)
            var isFrequencyChange = currentFrequency !== previousFrequency;
            
            var isUpgrade = isStorageUpgrade || isUsersUpgrade || isFrequencyChange;
            
            if (!isUpgrade) {
                logDebug('No upgrade detected within the same plan', {
                    currentStorage: currentStorage + 'TB',
                    previousStorage: previousStorage + 'TB',
                    currentUsers: currentUsers,
                    previousUsers: previousUsers,
                    currentFrequency: currentFrequency,
                    previousFrequency: previousFrequency
                });
                return false;
            }
            
            logInfo('Upgrade within same plan detected - showing prorated UI', {
                storageUpgrade: isStorageUpgrade,
                usersUpgrade: isUsersUpgrade,
                frequencyChange: isFrequencyChange,
                fromStorage: previousStorage + 'TB',
                toStorage: currentStorage + 'TB',
                fromUsers: previousUsers,
                toUsers: currentUsers,
                fromFrequency: previousFrequency,
                toFrequency: currentFrequency
            });
            
            return true;
            
        } catch (error) {
            logError('Error checking for prorated upgrade', { error: error.message });
            return false;
        }
    }

    function showProratedUI(config, calculation) {
        try {
            // 1. Modificar el label para mostrar "(prorrateado)"
            var $priceLabel = $('.pmpro_checkout-field-price-display label');
            if ($priceLabel.length && !$priceLabel.text().includes('prorrateado')) {
                originalTextsCache.priceLabel = $priceLabel.text();
                $priceLabel.text($priceLabel.text() + ' (prorrateado)');
                $priceLabel.closest('.pmpro_checkout-field-price-display').addClass('prorated-label');
            }
            
            // 2. Aplicar estilo especial al campo de precio
            var $priceDisplay = $('#total_price_display');
            if ($priceDisplay.length) {
                $priceDisplay.addClass('prorated-price');
            }
            
            // 3. Mostrar mensaje informativo ESPECÍFICO para upgrades dentro del mismo plan
            var noticeHtml = '<div class="pmpro-prorated-notice">' +
                '<p><strong>⚖️ Ajuste proporcional</strong></p>' +
                '<p>Este valor incluye un prorrateo por el aumento en su plan actual.</p>' +
                '<p>A partir do próximo ciclo de pagamento, você pagará o valor integral do novo plano.</p>' +
                '</div>';
            
            // Remover noticia anterior si existe
            $('.pmpro-prorated-notice').remove();
            
            // Insertar después del campo de precio
            $priceDisplay.after(noticeHtml);
            
            logInfo('Prorated UI displayed for same-plan upgrade');
            
        } catch (error) {
            logError('Error showing prorated UI', { error: error.message });
        }
    }

    function restoreNormalUI() {
        try {
            // Restaurar label original
            if (originalTextsCache.priceLabel) {
                $('.pmpro_checkout-field-price-display label').text(originalTextsCache.priceLabel);
                $('.pmpro_checkout-field-price-display').removeClass('prorated-label');
            }
            
            // Remover estilos de prorrateo
            $('#total_price_display').removeClass('prorated-price');
            
            // Remover mensaje informativo
            $('.pmpro-prorated-notice').remove();
            
            logDebug('Normal UI restored');
            
        } catch (error) {
            logError('Error restoring normal UI', { error: error.message });
        }
    }

    // Modifica la función updatePriceDisplay para incluir la detección de prorrateo
    function updatePriceDisplay(config) {
        try {
            var calculation = calculateTotalPrice(config);
            var formattedPrice = formatPrice(calculation.totalPrice, config.currencySymbol);
            var periodText = getPeriodText(calculation.frequencyValue);
            var displayText = formattedPrice + periodText;
            
            var $display = $('#total_price_display');
            if ($display.length) {
                $display.val(displayText);
                logDebug('Price display updated', { displayText: displayText });
            }
            
            // ✅ NUEVO: Verificar y mostrar UI de prorrateo si corresponde
            var isProrated = checkForProratedUpgrade(config);
            if (isProrated) {
                showProratedUI(config, calculation);
            } else {
                restoreNormalUI();
            }
            
            // Trigger evento personalizado
            $(document).trigger('pmprobandaspricing:updated', [calculation]);
            
        } catch (error) {
            logError('Error updating price display', { error: error.message });
        }
    }

    // ====
    // INICIALIZACIÓN PRINCIPAL
    // ====
    
    function initializePMProBanda() {
        logInfo('Starting PMPro Banda with Storage initialization (2 users base)', { version: PLUGIN_VERSION });
        
        // Verificar dependencias
        if (!validateDependencies()) {
            logError('Dependencies validation failed');
            return false;
        }
        
        // ✅ NUEVO: Limpiar UI de prorrateo al iniciar
        restoreNormalUI();
        
        // Obtener configuración
        var config = getConfig();
        if (!config) {
            logError('Configuration not available');
            return false;
        }
         
        try {
            // 1. Guardar textos originales
            storeOriginalTexts();
            
            // 2. Configurar valores iniciales
            var $storage = $('#storage_space');
            var $numUsers = $('#num_users');
            var $frequency = $('#payment_frequency');
            
            if ($storage.length && !$storage.val()) {
                $storage.val(config.currentStorage);
            }
            
            if ($numUsers.length && !$numUsers.val()) {
                $numUsers.val(config.currentUsers);
            }
            
            if ($frequency.length && !$frequency.val()) {
                $frequency.val('monthly');
            }
            
            // 3. Actualizar opciones de storage
            updateStorageOptions(config);
            
            // 4. Calcular precio inicial
            updatePriceDisplay(config);
            
            // 5. Configurar event listeners
            $('#storage_space, #num_users, #payment_frequency')
                .off('change.pmproband')
                .on('change.pmproband', function() {
                    logDebug('Field changed', { 
                        field: $(this).attr('id'), 
                        value: $(this).val() 
                    });
                    
                    // Limpiar caché
                    priceCache = {};
                    
                    // Actualizar precio
                    updatePriceDisplay(config);
                });
            
            // 6. Mostrar sección de precio
            $('.pmpro_checkout-field-price-display').show();
            
            logInfo('PMPro Banda with Storage initialized successfully (2 users base)', {
                fieldsFound: $('#storage_space, #num_users, #payment_frequency').length,
                basePrice: config.basePrice,
                baseUsersIncluded: config.baseUsersIncluded
            });
            
            return true;
            
        } catch (error) {
            logError('Exception during initialization', { 
                message: error.message, 
                stack: error.stack 
            });
            return false;
        }
    }
    
    // ====
    // ESTRATEGIA DE INICIALIZACIÓN MÚLTIPLE (sin cambios)
    // ====
    
    var initializationAttempts = 0;
    var maxAttempts = 3;
    var initializationDelays = [100, 500, 1000];
    
    function attemptInitialization() {
        if (initializationAttempts >= maxAttempts) {
            logError('Maximum initialization attempts reached');
            return;
        }
        
        var delay = initializationDelays[initializationAttempts] || 1000;
        initializationAttempts++;
        
        setTimeout(function() {
            logDebug('Initialization attempt ' + initializationAttempts + '/' + maxAttempts);
            
            if (initializePMProBanda()) {
                logInfo('Initialization successful');
                return;
            }
            
            // Si falló, intentar de nuevo
            if (initializationAttempts < maxAttempts) {
                attemptInitialization();
            }
        }, delay);
    }
    
    // Iniciar proceso
    attemptInitialization();
    
    // API para debugging (solo en modo debug)
    if (DEBUG_MODE) {
        window.PMProBanda = {
            version: PLUGIN_VERSION,
            reinitialize: initializePMProBanda,
            clearCache: function() { priceCache = {}; },
            getConfig: getConfig,
            log: {
                error: logError,
                info: logInfo,
                debug: logDebug
            }
        };
        
        logInfo('Debug mode enabled - API available in window.PMProBanda (2 users base)');
    }
});
