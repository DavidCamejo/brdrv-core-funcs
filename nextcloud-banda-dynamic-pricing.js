/**
 * PMPro Banda Dynamic Pricing - Frontend JavaScript con Storage v2.6.3
 *
 * @version 2.6.3 - Deshabilitar opciones que reducen precio actual y "Este pagamento" en prorrateo
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var PLUGIN_VERSION = '2.6.3';
    var DEBUG_MODE = typeof console !== 'undefined' && (typeof nextcloud_banda_pricing !== 'undefined' && nextcloud_banda_pricing.debug);
    var CACHE_EXPIRY = 60000; // 1 minuto
    
    var priceCache = {};
    var originalTextsCache = {};
    
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
    
    function validateDependencies() {
        var checks = {
            nextcloud_banda_pricing: typeof nextcloud_banda_pricing !== 'undefined',
            jquery: typeof $ !== 'undefined',
            // RELAJADO: requerimos al menos 1 de los campos para permitir inicializar en formularios dinámicos
            required_elements: $('#storage_space, #num_users, #payment_frequency').length >= 1
        };
        var missing = [];
        for (var check in checks) {
            if (!checks[check]) missing.push(check);
        }
        if (missing.length > 0) {
            logError('Missing dependencies', { missing: missing });
            return false;
        }
        logInfo('Dependencies validated successfully');
        return true;
    }
    
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
            baseUsersIncluded: parseInt(nextcloud_banda_pricing.base_users_included) || 2,
            baseStorageIncluded: parseInt(nextcloud_banda_pricing.base_storage_included) || 1,
            currencySymbol: nextcloud_banda_pricing.currency_symbol || 'R$',
            currentStorage: nextcloud_banda_pricing.current_storage || nextcloud_banda_pricing.currentStorage || null,
            currentUsers: nextcloud_banda_pricing.current_users != null ? parseInt(nextcloud_banda_pricing.current_users) : (nextcloud_banda_pricing.currentUsers != null ? parseInt(nextcloud_banda_pricing.currentUsers) : null),
            currentFrequency: nextcloud_banda_pricing.current_frequency || nextcloud_banda_pricing.currentFrequency || null,
            usedSpaceTb: parseFloat(nextcloud_banda_pricing.used_space_tb || nextcloud_banda_pricing.usedSpaceTb) || 0,
            hasPreviousConfig: !!(nextcloud_banda_pricing.has_previous_config || nextcloud_banda_pricing.hasPreviousConfig),
            nextPaymentDate: nextcloud_banda_pricing.next_payment_date || nextcloud_banda_pricing.nextPaymentDate || null,
            frequencyMultipliers: nextcloud_banda_pricing.frequency_multipliers || nextcloud_banda_pricing.frequencyMultipliers || {
                'monthly': 1.0, 'semiannual': 5.7, 'annual': 10.8, 'biennial': 20.4,
                'triennial': 28.8, 'quadrennial': 36.0, 'quinquennial': 42.0
            }
        };
    }
    
    function getCachedPrice(key) {
        var cached = priceCache[key];
        if (cached && (Date.now() - cached.timestamp) < CACHE_EXPIRY) {
            logDebug('Cache hit for price', { key: key });
            return cached.value;
        }
        return null;
    }
    function setCachedPrice(key, value) {
        priceCache[key] = { value: value, timestamp: Date.now() };
        logDebug('Price cached', { key: key });
    }
    
    function formatPrice(price, currencySymbol) {
        var val = Math.ceil(price);
        var formatted = val.toFixed(2)
            .replace('.', ',')
            .replace(/(\d)(?=(\d{3})+\,)/g, '$1.');
        return (currencySymbol || 'R$') + ' ' + formatted;
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
    
    // Helper: calcula precio sin leer DOM, a partir de valores pasados
    function calculateTotalWithValues(config, storageValue, numUsers, frequencyValue) {
        var storageVal = storageValue || (config.currentStorage || (config.baseStorageIncluded + 'tb'));
        var usersVal = typeof numUsers !== 'undefined' ? parseInt(numUsers) : (config.currentUsers || config.baseUsersIncluded || 2);
        var frequencyVal = frequencyValue || (config.currentFrequency || 'monthly');
        
        var storageTb = parseInt((storageVal + '').replace('tb', '')) || config.baseStorageIncluded;
        var additionalTb = Math.max(0, storageTb - config.baseStorageIncluded);
        var storagePrice = (config.basePrice || 0) + (additionalTb * (config.pricePerTb || 0));
        
        var additionalUsers = Math.max(0, usersVal - config.baseUsersIncluded);
        var userPrice = additionalUsers * (config.pricePerUser || 0);
        
        var combinedPrice = storagePrice + userPrice;
        var multiplier = (config.frequencyMultipliers && config.frequencyMultipliers[frequencyVal]) ? config.frequencyMultipliers[frequencyVal] : 1.0;
        
        return {
            storageValue: storageVal,
            numUsers: usersVal,
            frequencyValue: frequencyVal,
            totalPrice: Math.ceil(combinedPrice * multiplier),
            combinedPrice: combinedPrice,
            multiplier: multiplier
        };
    }
    
    function calculateTotalPrice(config) {
        var storageValue = $('#storage_space').val() || config.currentStorage || (config.baseStorageIncluded + 'tb');
        var numUsers = parseInt($('#num_users').val()) || config.currentUsers || config.baseUsersIncluded || 2;
        var frequencyValue = $('#payment_frequency').val() || config.currentFrequency || 'monthly';
        
        var cacheKey = storageValue + '_' + numUsers + '_' + frequencyValue + '_' + config.basePrice;
        var cached = getCachedPrice(cacheKey);
        if (cached !== null) {
            return cached;
        }
        
        var calc = calculateTotalWithValues(config, storageValue, numUsers, frequencyValue);
        setCachedPrice(cacheKey, calc);
        logDebug('Price calculated', calc);
        return calc;
    }
    
    function storeOriginalTexts() {
        try {
            $('#storage_space option, #num_users option, #payment_frequency option').each(function() {
                var $option = $(this);
                var selectId = $option.closest('select').attr('id');
                if (!selectId) return;
                var key = selectId + '_' + $option.val();
                originalTextsCache[key] = $option.text();
            });
            var $priceLabel = $('.pmpro_checkout-field-price-display label, label.pmpro_price_label').first();
            if ($priceLabel.length) originalTextsCache.priceLabel = $priceLabel.text();
            var $priceValue = $('#total_price_display, input[name="total_price_display"], .total_price_display, .pmpro_price_display').first();
            if ($priceValue.length) {
                originalTextsCache.priceValue = $priceValue.val() || $priceValue.text() || null;
            }
            logDebug('Original texts stored', { count: Object.keys(originalTextsCache).length, originalTextsCache: originalTextsCache });
        } catch (e) {
            logDebug('storeOriginalTexts error', { error: e && e.message ? e.message : e });
        }
    }
    
    // Actualiza opciones de storage (mantiene lógica previa) y aplica deshabilitado por precio previo
    function updateStorageOptions(config) {
        var currentTb = parseInt((config.currentStorage || (config.baseStorageIncluded + 'tb')).replace('tb', '')) || config.baseStorageIncluded;
        var $storageSelect = $('#storage_space');
        if (!$storageSelect.length) return;
        var localized = window.nextcloud_banda_pricing || {};
        var prevTotals = getPreviousTotals(localized, config);
        $storageSelect.find('option').each(function() {
            var $option = $(this);
            var val = $option.val() || '';
            var optionTb = parseInt((val + '').replace('tb', '')) || 0;
            var originalKey = 'storage_space_' + val;
            var originalText = originalTextsCache[originalKey] || $option.text();
            var cleanText = originalText.replace(/ \(.*\)$/, '');
            // Bloqueos previos por espacio usado
            if (optionTb < currentTb && optionTb < config.usedSpaceTb) {
                $option.prop('disabled', true).text(cleanText + ' (Espaço insuficiente)');
                return;
            } else if (optionTb < currentTb) {
                $option.prop('disabled', true).text(cleanText + ' (Downgrade não permitido)');
                return;
            }
            // Bloquear si la opción produce un precio menor que el precio previo (prevTotals.prevTotal)
            var hypo = calculateTotalWithValues(config, val, $('#num_users').val() || config.currentUsers || config.baseUsersIncluded, $('#payment_frequency').val() || config.currentFrequency || 'monthly');
            if (prevTotals.prevTotal != null && typeof hypo.totalPrice !== 'undefined' && hypo.totalPrice < prevTotals.prevTotal) {
                $option.prop('disabled', true).text(cleanText + ' (Reducción de precio no permitida)');
                return;
            }
            // Si llegó aquí: habilitar y restaurar texto original
            $option.prop('disabled', false).text(cleanText);
        });
        // Si la opción seleccionada está deshabilitada, seleccionar primera habilitada
        if ($storageSelect.find('option:selected').prop('disabled')) {
            var $firstEnabled = $storageSelect.find('option:not(:disabled)').first();
            if ($firstEnabled.length) {
                $storageSelect.val($firstEnabled.val()).trigger('change');
            }
        }
        if (config.usedSpaceTb > currentTb) showStorageAlert(config);
        logDebug('Storage options updated', { currentTb: currentTb, usedSpaceTb: config.usedSpaceTb });
    }
    
    function updateUserOptions(config) {
        var $userSelect = $('#num_users');
        if (!$userSelect.length) return;
        var localized = window.nextcloud_banda_pricing || {};
        var prevTotals = getPreviousTotals(localized, config);
        $userSelect.find('option').each(function() {
            var $option = $(this);
            var val = parseInt($option.val()) || 0;
            var originalKey = 'num_users_' + $option.val();
            var originalText = originalTextsCache[originalKey] || $option.text();
            var cleanText = originalText.replace(/ \(.*\)$/, '');
            // Bloquear si usuarios < prevUsers
            var prevUsers = prevTotals.prevUsers;
            if (prevUsers != null && val < prevUsers) {
                $option.prop('disabled', true).text(cleanText + ' (Downgrade de usuarios no permitido)');
                return;
            }
            // Bloquear si produce precio menor que prevTotal
            var hypo = calculateTotalWithValues(config, $('#storage_space').val() || config.currentStorage, val, $('#payment_frequency').val() || config.currentFrequency);
            if (prevTotals.prevTotal != null && hypo.totalPrice < prevTotals.prevTotal) {
                $option.prop('disabled', true).text(cleanText + ' (Reducción de precio no permitida)');
                return;
            }
            $option.prop('disabled', false).text(cleanText);
        });
        if ($userSelect.find('option:selected').prop('disabled')) {
            var $firstEnabled = $userSelect.find('option:not(:disabled)').first();
            if ($firstEnabled.length) $userSelect.val($firstEnabled.val()).trigger('change');
        }
        logDebug('User options updated');
    }
    
    function updateFrequencyOptions(config) {
        var $freqSelect = $('#payment_frequency');
        if (!$freqSelect.length) return;
        var localized = window.nextcloud_banda_pricing || {};
        var prevTotals = getPreviousTotals(localized, config);
        var prevFreq = prevTotals.prevFrequency;
        $freqSelect.find('option').each(function() {
            var $option = $(this);
            var val = $option.val();
            var originalKey = 'payment_frequency_' + val;
            var originalText = originalTextsCache[originalKey] || $option.text();

            // Solo eliminar previamente agregado "(Cambio de ciclo no permitido)" si existe,
            // pero preservar otras aclaraciones como "(-5%)"
            var cleanText = originalText.replace(/\s*\(Cambio de ciclo no permitido\)\s*$/i, '').replace(/\s*\(Reducción de precio no permitida\)\s*$/i, '');

            // Bloquear si frecuencia se considera downgrade
            if (isFrequencyDowngrade(prevFreq, val)) {
                $option.prop('disabled', true).text(cleanText + ' (Cambio de ciclo no permitido)');
                return;
            }
            // Bloquear si produce precio menor que prevTotal
            var hypo = calculateTotalWithValues(config, $('#storage_space').val() || config.currentStorage, $('#num_users').val() || config.currentUsers || config.baseUsersIncluded, val);
            if (prevTotals.prevTotal != null && hypo.totalPrice < prevTotals.prevTotal) {
                $option.prop('disabled', true).text(cleanText + ' (Reducción de precio no permitida)');
                return;
            }
            $option.prop('disabled', false).text(cleanText);
        });
        if ($freqSelect.find('option:selected').prop('disabled')) {
            var $firstEnabled = $freqSelect.find('option:not(:disabled)').first();
            if ($firstEnabled.length) $freqSelect.val($firstEnabled.val()).trigger('change');
        }
        logDebug('Frequency options updated');
    }
    
    function showStorageAlert(config) {
        var alertId = 'storage_alert';
        if ($('#' + alertId).length) return;
        var alertHtml = '<div class="pmpro_message pmpro_error" id="' + alertId + '">' +
            '<strong>Atenção:</strong> Você está usando ' + (parseFloat(config.usedSpaceTb) || 0).toFixed(2) + ' TB de armazenamento. ' +
            'Não é possível reduzir abaixo deste limite.' +
            '</div>';
        $('#storage_space').before(alertHtml);
        logInfo('Storage alert shown', { usedSpace: config.usedSpaceTb });
    }
    
    var FREQUENCY_DAYS = {
        monthly: 30,
        semiannual: 182,
        annual: 365,
        biennial: 365 * 2,
        triennial: 365 * 3,
        quadrennial: 365 * 4,
        quinquennial: 365 * 5
    };
    
    var FREQUENCY_ORDER = ['monthly','semiannual','annual','biennial','triennial','quadrennial','quinquennial'];
    function isFrequencyDowngrade(currentFreq, newFreq) {
        if (!currentFreq || !newFreq) return false;
        var curIdx = FREQUENCY_ORDER.indexOf(currentFreq);
        var newIdx = FREQUENCY_ORDER.indexOf(newFreq);
        if (curIdx === -1 || newIdx === -1) return false;
        return newIdx < curIdx;
    }
    
    function computeRemainingFraction(localizedCfg) {
        try {
            var rawNext = (localizedCfg && (localizedCfg.next_payment_date || localizedCfg.nextPaymentDate || localizedCfg.nextPaymentDateIso)) || null;
            if (rawNext) {
                var nextDate = new Date(rawNext);
                if (!isNaN(nextDate.getTime())) {
                    var now = new Date();
                    var diffMs = nextDate.getTime() - now.getTime();
                    var daysRemaining = Math.max(0, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));
                    var freq = (localizedCfg.current_frequency || localizedCfg.currentFrequency || 'monthly');
                    var periodDays = FREQUENCY_DAYS[freq] || 30;
                    var fraction = Math.min(1, Math.max(0, daysRemaining / periodDays));
                    logDebug('Remaining fraction computed from next_payment_date', { rawNext: rawNext, daysRemaining: daysRemaining, periodDays: periodDays, fraction: fraction });
                    return { fraction: fraction, exact: true, daysRemaining: daysRemaining, periodDays: periodDays };
                }
            }
        } catch (e) {
            logDebug('computeRemainingFraction error', { error: e.message });
        }
        logDebug('computeRemainingFraction fallback to estimated 50%');
        return { fraction: 0.5, exact: false, daysRemaining: null, periodDays: null };
    }
    
    // Obtiene totals previos (prevTotal, prevUsers, prevStorage, prevFrequency)
    function getPreviousTotals(localizedCfg, configObj) {
        try {
            var prevStorageRaw = localizedCfg.current_storage || localizedCfg.currentStorage || configObj.currentStorage || (configObj.baseStorageIncluded + 'tb');
            var prevStorage = parseInt((prevStorageRaw + '').replace('tb','')) || configObj.baseStorageIncluded || 1;
            var prevUsers = localizedCfg.current_users != null ? parseInt(localizedCfg.current_users) : (localizedCfg.currentUsers != null ? parseInt(localizedCfg.currentUsers) : (configObj.currentUsers || configObj.baseUsersIncluded || 2));
            var prevFrequency = localizedCfg.current_frequency || localizedCfg.currentFrequency || configObj.currentFrequency || 'monthly';
            var prevCalc = calculateTotalWithValues(configObj, (prevStorage + 'tb'), prevUsers, prevFrequency);
            return {
                prevStorage: prevStorage,
                prevUsers: prevUsers,
                prevFrequency: prevFrequency,
                prevTotal: prevCalc.totalPrice
            };
        } catch (e) {
            logDebug('getPreviousTotals error', { error: e && e.message ? e.message : e });
            return { prevStorage: null, prevUsers: null, prevFrequency: null, prevTotal: null };
        }
    }
    
    /**
     * determineProrationDecision(localizedCfg, selection, calculation, configObj)
     * - Devuelve un objeto con:
     *   { blockedDowngrade: bool, reason: string|null, shouldProrate: bool, proratedValue: number|null, nextCycleValue: number|null, estimated: bool }
     */
    function determineProrationDecision(localizedCfg, selection, calculation, configObj) {
        try {
            logDebug('determineProrationDecision start', { localizedCfg: localizedCfg, selection: selection, calculation: calculation, configObj: configObj });
            
            var hasPrevious = !!(localizedCfg && (localizedCfg.has_previous_config || localizedCfg.hasPreviousConfig || configObj && configObj.hasPreviousConfig));
            if (!hasPrevious) {
                return { blockedDowngrade: false, reason: null, shouldProrate: false, proratedValue: null, nextCycleValue: null, estimated: false };
            }
            
            // Valores previos (acepta snake_case y camelCase)
            var prevStorageRaw = localizedCfg.current_storage || localizedCfg.currentStorage || configObj.currentStorage || (configObj.baseStorageIncluded + 'tb');
            var prevStorage = parseInt((prevStorageRaw + '').replace('tb','')) || configObj.baseStorageIncluded || 1;
            var prevUsers = localizedCfg.current_users != null ? parseInt(localizedCfg.current_users) : (localizedCfg.currentUsers != null ? parseInt(localizedCfg.currentUsers) : (configObj.currentUsers || configObj.baseUsersIncluded || 2));
            var prevFrequency = localizedCfg.current_frequency || localizedCfg.currentFrequency || configObj.currentFrequency || 'monthly';
            var usedSpaceTb = parseFloat(localizedCfg.used_space_tb || localizedCfg.usedSpaceTb) || configObj.usedSpaceTb || 0;
            
            // Selección actual (desde selection o DOM)
            var selStorageRaw = selection.storage || $('#storage_space').val() || configObj.currentStorage || (configObj.baseStorageIncluded + 'tb');
            var selStorage = parseInt((selStorageRaw + '').replace('tb','')) || configObj.baseStorageIncluded || 1;
            var selUsers = selection.users ? parseInt(selection.users) : (parseInt($('#num_users').val()) || configObj.currentUsers || configObj.baseUsersIncluded || 2);
            var selFrequency = selection.frequency || $('#payment_frequency').val() || configObj.currentFrequency || 'monthly';
            
            logDebug('Proration comparison', {
                prevStorage: prevStorage, selStorage: selStorage, usedSpaceTb: usedSpaceTb,
                prevUsers: prevUsers, selUsers: selUsers,
                prevFrequency: prevFrequency, selFrequency: selFrequency
            });
            
            // 1) Almacenamiento por debajo del usado -> bloqueo
            if (selStorage < usedSpaceTb) {
                return { blockedDowngrade: true, reason: 'storage_below_used', shouldProrate: false, proratedValue: null, nextCycleValue: null, estimated: false };
            }
            // 2) Usuarios por debajo de actuales -> bloqueo
            if (selUsers < prevUsers) {
                return { blockedDowngrade: true, reason: 'users_below_current', shouldProrate: false, proratedValue: null, nextCycleValue: null, estimated: false };
            }
            // 3) Cambio de frecuencia considerado downgrade -> bloqueo
            if (isFrequencyDowngrade(prevFrequency, selFrequency)) {
                return { blockedDowngrade: true, reason: 'frequency_downgrade', shouldProrate: false, proratedValue: null, nextCycleValue: null, estimated: false };
            }
            
            // 4) Determinar si es upgrade (storage/users/frequency)
            var isStorageUpgrade = selStorage > prevStorage;
            var isUsersUpgrade = selUsers > prevUsers;
            var isFrequencyChange = selFrequency !== prevFrequency;
            var isUpgrade = isStorageUpgrade || isUsersUpgrade || isFrequencyChange;
            if (!isUpgrade) {
                return { blockedDowngrade: false, reason: null, shouldProrate: false, proratedValue: null, nextCycleValue: null, estimated: false };
            }
            
            // 5) Calcular precios previos y nuevos
            var prevTotals = getPreviousTotals(localizedCfg || {}, configObj);
            var prevTotal = prevTotals.prevTotal || 0;
            var newCalc = calculateTotalWithValues(configObj, selStorage + 'tb', selUsers, selFrequency);
            var newTotal = newCalc.totalPrice || 0;
            
            // 6) Fracción restante del periodo (next_payment_date)
            var fracInfo = computeRemainingFraction(localizedCfg || {});
            var remainingFraction = fracInfo.fraction;
            var estimated = !fracInfo.exact;
            
            // 7) Prorrateo: (newTotal - prevTotal) * remainingFraction (solo si newTotal > prevTotal)
            var diff = newTotal - prevTotal;
            var proratedNow = null;
            if (diff > 0 && remainingFraction > 0) {
                proratedNow = Math.ceil(diff * remainingFraction);
            } else {
                proratedNow = 0;
            }
            
            logDebug('proration amounts', { prevTotal: prevTotal, newTotal: newTotal, diff: diff, remainingFraction: remainingFraction, proratedNow: proratedNow, estimated: estimated });
            
            return {
                blockedDowngrade: false,
                reason: null,
                shouldProrate: (proratedNow > 0),
                proratedValue: proratedNow,
                nextCycleValue: newTotal,
                estimated: estimated
            };
            
        } catch (err) {
            logError('Error in determineProrationDecision', { error: err.message });
            return { blockedDowngrade: false, reason: null, shouldProrate: false, proratedValue: null, nextCycleValue: null, estimated: false };
        }
    }
    
    function applyDowngradeWarning(reason) {
        $('.pmpro-downgrade-warning').remove();
        var msg = {
            'storage_below_used': 'No puede seleccionar menos almacenamiento del usado actualmente.',
            'users_below_current': 'No puede seleccionar menos usuarios que los actualmente configurados en su cuenta.',
            'frequency_downgrade': 'No se permite cambiar desde un plan con ciclo largo (p. ej. bienal) a uno de ciclo más corto (p. ej. mensual) desde el frontend: contacta soporte para cambios que impliquen reembolsos.'
        }[reason] || 'Cambio no permitido';
        
        var $warn = $('<div class="pmpro-downgrade-warning" style="color:#b33; margin-top:8px;"><strong>' + msg + '</strong></div>');
        if ($('#payment_frequency').length) {
            $('#payment_frequency').closest('.pmpro_checkout-field').append($warn);
        } else {
            $('.pmpro_checkout').first().prepend($warn);
        }
        
        var $btn = $('input[name="submit"], button[type="submit"], #pmpro_btn_submit').first();
        if ($btn.length) {
            $btn.prop('disabled', true).addClass('disabled-for-downgrade');
        }
    }
    
    function showProratedUI(config, calculation) {
        try {
            logDebug('showProratedUI called', { config: config, calculation: calculation });
            var $priceDisplay = $('#total_price_display');
            if (!$priceDisplay.length) {
                $priceDisplay = $('input[name="total_price_display"], .total_price_display, .pmpro_price_display').first();
            }
            var $priceLabel = $('.pmpro_checkout-field-price-display label, label.pmpro_price_label').first();
            if (!$priceLabel.length && $priceDisplay.length) {
                var $container = $priceDisplay.closest('.pmpro_checkout-field-price-display');
                if (!$container.length) {
                    $container = $('<div class="pmpro_checkout-field-price-display"></div>');
                    $priceDisplay.before($container);
                }
                $priceLabel = $('<label class="pmpro_price_label"></label>').prependTo($container);
            }
            if ($priceLabel.length && !originalTextsCache.priceLabel) {
                originalTextsCache.priceLabel = $priceLabel.text();
                logDebug('Original price label cached', { priceLabel: originalTextsCache.priceLabel });
            }
            // Añadir "(prorrateado)" al label si no existe
            if ($priceLabel.length) {
                var currentLabelText = $priceLabel.text() || '';
                if (currentLabelText.indexOf('prorrateado') === -1) {
                    $priceLabel.text(currentLabelText + (currentLabelText ? ' ' : '') + '(prorrateado)');
                    $priceLabel.closest('.pmpro_checkout-field-price-display').addClass('prorated-label');
                }
            }
            // Aplicar estilo al campo de precio
            if ($priceDisplay.length) {
                $priceDisplay.addClass('prorated-price');
                // Mostrar Este pagamento (prorrateado) y reemplazar texto de periodo por " (Este pagamento)"
                if (calculation && calculation.proratedValue != null) {
                    var formattedNow = formatPrice(calculation.proratedValue, config.currencySymbol || 'R$');
                    $priceDisplay.val(formattedNow + ' (Este pagamento)');
                } else if (calculation && calculation.totalPrice != null && calculation.proratedValue != null) {
                    var formatted2 = formatPrice(calculation.proratedValue, config.currencySymbol || 'R$');
                    $priceDisplay.val(formatted2 + ' (Este pagamento)');
                } else if (calculation && calculation.proratedPrice != null) {
                    $priceDisplay.val(formatPrice(calculation.proratedPrice, config.currencySymbol || 'R$') + ' (Este pagamento)');
                }
            } else {
                logError('showProratedUI: #total_price_display not found; styling skipped');
            }
            $('.pmpro-prorated-notice').remove();
            var nowHtml = '';
            var nextHtml = '';
            if (calculation && calculation.proratedValue != null) {
                nowHtml = '<p><strong>A ser pago agora (prorrateado):</strong> <span class="pmpro-prorated-now">' + formatPrice(calculation.proratedValue, config.currencySymbol || 'R$') + ' *</span></p>';
            }
            if (calculation && calculation.nextCycleValue != null) {
                nextHtml = '<p><strong>A partir do próximo ciclo:</strong> <span class="pmpro-prorated-next">' + formatPrice(calculation.nextCycleValue, config.currencySymbol || 'R$') + getPeriodText($('#payment_frequency').val() || calculation.frequencyValue) + '</span></p>';
            }
            var noteHtml = '';
            if (calculation && calculation.estimated) {
                noteHtml = '<p style="font-size:0.9em;color:#666;"><em><span class="pmpro-prorated-now">*</span> Valor proporcional estimado, que pode ser ajustado no momento do pagamento.</em></p>';
            }
            var noticeHtml = '<div class="pmpro-prorated-notice" role="status" aria-live="polite">' +
                '<p><strong>⚖️ Cobrança proporcional</strong></p>' +
                (nowHtml || '') +
                (nextHtml || '') +
                (noteHtml || '') +
                '</div>';
            if ($priceDisplay.length) {
                $priceDisplay.after(noticeHtml);
            } else if ($priceLabel.length) {
                $priceLabel.after(noticeHtml);
            } else {
                $('#pmpro_checkout, .pmpro_checkout').first().prepend(noticeHtml);
            }
            // Estilos inline para elementos prorrateados
            $('.pmpro-prorated-now').css({ color: '#b33', 'font-weight': 700 });
            $('.pmpro-prorated-next').css({ color: '#333', 'font-weight': 600 });
            // Habilitar botón si estaba deshabilitado (upgrade permitido)
            var $btn = $('input[name="submit"], button[type="submit"], #pmpro_btn_submit').first();
            if ($btn.length) {
                $btn.prop('disabled', false).removeClass('disabled-for-downgrade');
            }
            logDebug('showProratedUI applied', {
                priceLabelExists: $priceLabel.length,
                priceDisplayExists: $priceDisplay.length,
                noticeExists: !!$('.pmpro-prorated-notice').length
            });
            return true;
        } catch (error) {
            logError('Error showing prorated UI', { error: error.message, stack: error.stack });
            return false;
        }
    }
    
    function restoreNormalUI() {
        try {
            logDebug('restoreNormalUI called', { originalTextsCache: originalTextsCache });
            var $label = $('.pmpro_checkout-field-price-display label, label.pmpro_price_label').first();
            if (originalTextsCache.priceLabel && $label.length) {
                $label.text(originalTextsCache.priceLabel);
                $label.closest('.pmpro_checkout-field-price-display').removeClass('prorated-label');
            }
            $('#total_price_display').removeClass('prorated-price');
            var $priceValue = $('#total_price_display, input[name="total_price_display"], .total_price_display, .pmpro_price_display').first();
            if ($priceValue.length && originalTextsCache.priceValue != null) {
                try {
                    $priceValue.val(originalTextsCache.priceValue);
                } catch (e) {
                    try { $priceValue.text(originalTextsCache.priceValue); } catch(e2) {}
                }
            }
            $('input[name="total_price_display"], .total_price_display, .pmpro_price_display').removeClass('prorated-price');
            $('.pmpro-prorated-notice').remove();
            logDebug('Normal UI restored', {
                priceLabelNow: $label.length ? $label.text() : null,
                noticeCount: $('.pmpro-prorated-notice').length
            });
            return true;
        } catch (error) {
            logError('Error restoring normal UI', { error: error.message, stack: error.stack });
            return false;
        }
    }
    
    function handleSelectionChange() {
        var config = getConfig();
        if (!config) return;
        priceCache = {};
        // actualizar opciones y deshabilitar las que reduzcan precio
        updateStorageOptions(config);
        updateUserOptions(config);
        updateFrequencyOptions(config);
        updatePriceDisplay(config);
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
            
            logDebug('About to check proration', {
                calculation: calculation,
                configHasPrevious: config.hasPreviousConfig,
                localizedObj: window.nextcloud_banda_pricing
            });
            
            var decision = determineProrationDecision(window.nextcloud_banda_pricing || {}, {
                storage: $('#storage_space').val(),
                users: $('#num_users').val(),
                frequency: $('#payment_frequency').val()
            }, calculation, config);
            
            logDebug('Proration decision', decision);
            
            if (decision.blockedDowngrade) {
                restoreNormalUI();
                applyDowngradeWarning(decision.reason);
            } else {
                $('.pmpro-downgrade-warning').remove();
                if (decision.shouldProrate) {
                    showProratedUI(config, {
                        proratedValue: decision.proratedValue,
                        proratedPrice: decision.proratedValue,
                        totalPrice: decision.nextCycleValue,
                        nextCycleValue: decision.nextCycleValue,
                        estimated: decision.estimated,
                        frequencyValue: $('#payment_frequency').val() || config.currentFrequency
                    });
                } else {
                    restoreNormalUI();
                }
            }
            
            $(document).trigger('pmprobandaspricing:updated', [calculation]);
            
        } catch (error) {
            logError('Error updating price display', { error: error.message });
        }
    }
    
    $('#storage_space, #num_users, #payment_frequency')
        .off('change.pmproband')
        .on('change.pmproband', function() {
            logDebug('Field changed', { field: $(this).attr('id'), value: $(this).val() });
            handleSelectionChange();
        });
    
    setTimeout(function() {
        storeOriginalTexts();
        handleSelectionChange();
    }, 200);
    
    function initializePMProBanda() {
        logInfo('Starting PMPro Banda initialization', { version: PLUGIN_VERSION });
        logDebug('Localized object (nextcloud_banda_pricing):', window.nextcloud_banda_pricing);
        if (!validateDependencies()) {
            logError('Dependencies validation failed');
            return false;
        }
        restoreNormalUI();
        var config = getConfig();
        if (!config) {
            logError('Configuration not available');
            return false;
        }
        try {
            storeOriginalTexts();
            var $storage = $('#storage_space');
            var $numUsers = $('#num_users');
            var $frequency = $('#payment_frequency');
            if ($storage.length && !$storage.val()) $storage.val(config.currentStorage || (config.baseStorageIncluded + 'tb'));
            if ($numUsers.length && !$numUsers.val()) $numUsers.val(config.currentUsers || config.baseUsersIncluded);
            if ($frequency.length && !$frequency.val()) $frequency.val(config.currentFrequency || 'monthly');
            updateStorageOptions(config);
            updateUserOptions(config);
            updateFrequencyOptions(config);
            updatePriceDisplay(config);
            $('.pmpro_checkout-field-price-display').show();
            logInfo('PMPro Banda initialized successfully', { basePrice: config.basePrice, baseUsersIncluded: config.baseUsersIncluded });
            return true;
        } catch (error) {
            logError('Exception during initialization', { message: error.message, stack: error.stack });
            return false;
        }
    }
    
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
            if (initializationAttempts < maxAttempts) attemptInitialization();
        }, delay);
    }
    attemptInitialization();
    
    if (DEBUG_MODE) {
        window.PMProBanda = {
            version: PLUGIN_VERSION,
            reinitialize: initializePMProBanda,
            clearCache: function() { priceCache = {}; },
            getConfig: getConfig,
            log: { error: logError, info: logInfo, debug: logDebug }
        };
        logInfo('Debug mode enabled - API available in window.PMProBanda');
    }
});
