/**
 * PMPro Banda Dynamic Pricing - JavaScript CON PRORRATEO VISUAL v2.7.7
 */

/* global jQuery, window, console */

var priceCache = {};
var originalTextsCache = {};
var initialUserValues = {};
var debugMode = false;
var NEXTCLOUD_BANDA_BASE_PRICE = 70.00;

// Waits for window.nextcloud_banda_pricing (polling) and then calls cb(config|null)
function whenPricingReady(cb, timeoutMs) {
    timeoutMs = timeoutMs || 5000;
    if (typeof window.nextcloud_banda_pricing !== 'undefined') {
        return cb(window.nextcloud_banda_pricing);
    }
    var waited = 0;
    var interval = setInterval(function() {
        waited += 100;
        if (typeof window.nextcloud_banda_pricing !== 'undefined') {
            clearInterval(interval);
            cb(window.nextcloud_banda_pricing);
        } else if (waited >= timeoutMs) {
            clearInterval(interval);
            cb(null);
        }
    }, 100);
}

// Document ready wrapper
jQuery(function($) {
    // First, wait for pricing data (handles case where localize is injected AFTER this script)
    whenPricingReady(function(config) {
        if (!config) {
            console.warn('[PMPro Banda] pricing data not found after wait; initialization skipped');
            return;
        }
        // Store global config and initialize
        window.nextcloud_banda_pricing = config;
        debugMode = config.debug || false;

        // Ensure initialization happens when DOM elements are available
        initializePricingSystem();
    }, 4000); // 4s timeout (ajustable)
});

/* ---------------------------
   Initialization & helpers
   --------------------------- */

function initializePricingSystem() {
    try {
        if (typeof window.nextcloud_banda_pricing === 'undefined') {
            logDebug('No pricing data found, skipping initialization');
            return;
        }
        var config = window.nextcloud_banda_pricing;
        debugMode = config.debug || false;
        logDebug('Initializing pricing system', config);

        window.nextcloud_banda_config = config;

        // Wait for fields to exist in DOM (timeout-safe)
        waitForFields(['#storage_space', '#num_users', '#payment_frequency', '#total_price_display'], 3000, function(found) {
            // Initialize fields even if some missing; functions are robust to absent elements
            initializeFieldValues(config);
            storeInitialUserValues(config);

            // Attach event listeners (delegated)
            attachEventListeners();

            // Always compute initial price
            updatePriceDisplay(config, false);

            // Clean previous state caches
            clearPreviousClientState();
        });

    } catch (err) {
        logError('Error initializing pricing system', { error: err && err.message });
    }
}

// Waits until at least one of the selectors exists (or timeout), then cb(foundBoolean)
function waitForFields(selectors, timeoutMs, cb) {
    var waited = 0;
    var interval = setInterval(function() {
        var foundAny = false;
        for (var i=0;i<selectors.length;i++){
            if (jQuery(selectors[i]).length) {
                foundAny = true;
                break;
            }
        }
        if (foundAny) {
            clearInterval(interval);
            cb(true);
        } else {
            waited += 100;
            if (waited >= timeoutMs) {
                clearInterval(interval);
                cb(false);
            }
        }
    }, 100);
}

/* ---------------------------
   Field initialization
   --------------------------- */

function initializeFieldValues(config) {
    var defaultStorage = '1tb';
    var defaultUsers = 2;
    var defaultFrequency = 'monthly';

    // Verificar si hay membresía activa Y configuración previa
    if (config.hasActiveMembership && config.has_previous_config && 
        config.current_subscription_data) {
        defaultStorage = config.current_subscription_data.storage_space;
        defaultUsers = config.current_subscription_data.num_users;
        defaultFrequency = config.current_subscription_data.payment_frequency;
        logDebug('Using previous config values for active membership', { 
            storage: defaultStorage, 
            users: defaultUsers, 
            frequency: defaultFrequency 
        });
    } else {
        logDebug('Using default values (no active membership or no previous config)', { 
            storage: defaultStorage, 
            users: defaultUsers, 
            frequency: defaultFrequency,
            hasActiveMembership: config.hasActiveMembership,
            hasPreviousConfig: config.has_previous_config
        });
    }

    var $storageField = jQuery('#storage_space');
    var $usersField = jQuery('#num_users');
    var $frequencyField = jQuery('#payment_frequency');

    if ($storageField.length && (!$storageField.val() || $storageField.val() === '')) {
        $storageField.val(defaultStorage).trigger('change');
    }
    if ($usersField.length && (!$usersField.val() || $usersField.val() === '')) {
        $usersField.val(defaultUsers).trigger('change');
    }
    if ($frequencyField.length && (!$frequencyField.val() || $frequencyField.val() === '')) {
        $frequencyField.val(defaultFrequency).trigger('change');
    }

    logDebug('Field values initialized with defaults', {
        storage: $storageField.val(),
        users: $usersField.val(),
        frequency: $frequencyField.val(),
        has_previous_config: config.has_previous_config,
        hasActiveMembership: config.hasActiveMembership
    });
}

function storeInitialUserValues(config) {
    initialUserValues = {
        storage: jQuery('#storage_space').val() || '1tb',
        users: parseInt(jQuery('#num_users').val(), 10) || 2,
        frequency: jQuery('#payment_frequency').val() || 'monthly',
        hasPreviousConfig: !!(config && config.has_previous_config && config.hasActiveMembership),
        hasActiveMembership: !!(config && config.hasActiveMembership),
        subscriptionData: config.current_subscription_data || null
    };
    logDebug('Initial user values stored from fields', initialUserValues);
}

/* ---------------------------
   Events (delegated)
   --------------------------- */

function attachEventListeners() {
    // Delegated events on document to be robust ante render dinámico.
    jQuery(document)
        .off('.pmproband', '#storage_space, #num_users, #payment_frequency')
        .on('input.pmproband change.pmproband', '#storage_space, #num_users, #payment_frequency', function() {
            logDebug('Field changed', { field: jQuery(this).attr('id'), value: jQuery(this).val() });
            handleSelectionChange();
        });

    // Submit buttons
    jQuery(document)
        .off('.pmproband', 'input[name="submit"], button[type="submit"], #pmpro_btn_submit')
        .on('click.pmproband', 'input[name="submit"], button[type="submit"], #pmpro_btn_submit', function() {
            jQuery('.pmpro-downgrade-warning').remove();
        });
}

/* ---------------------------
   Change handler
   --------------------------- */

function handleSelectionChange() {
    try {
        var config = window.nextcloud_banda_config;
        if (!config) {
            logError('Config not available');
            return;
        }
        var currentSelection = {
            storage: jQuery('#storage_space').val(),
            users: jQuery('#num_users').val(),
            frequency: jQuery('#payment_frequency').val()
        };
        logDebug('Current selection', currentSelection);

        if (initialUserValues.hasPreviousConfig && hasUserMadeChanges()) {
            updateStorageOptions(config, currentSelection);
            updateUserOptions(config, currentSelection);
            updateFrequencyOptions(config, currentSelection);
        }

        var shouldApplyProration = initialUserValues.hasPreviousConfig && hasUserMadeChanges() && isUpgradeChange();
        updatePriceDisplay(config, shouldApplyProration);

    } catch (err) {
        logError('Error handling selection change', { error: err && err.message });
    }
}

/* ---------------------------
   Update option blocking UI
   --------------------------- */

function updateStorageOptions(config, currentSelection) {
    try {
        var $storageSelect = jQuery('#storage_space');
        if (!$storageSelect.length) return;

        var currentStorageValue = parseStorageValue(initialUserValues.storage);
        var selectedStorageValue = parseStorageValue(currentSelection.storage);

        $storageSelect.find('option').each(function() {
            var key = 'storage_space_' + jQuery(this).val();
            if (!originalTextsCache[key]) originalTextsCache[key] = jQuery(this).text();
        });

        $storageSelect.find('option').each(function() {
            var optionValue = parseStorageValue(jQuery(this).val());
            var key = 'storage_space_' + jQuery(this).val();

            if (initialUserValues.hasPreviousConfig && optionValue < currentStorageValue) {
                jQuery(this).prop('disabled', true);
                jQuery(this).text(originalTextsCache[key] + ' (Downgrade bloqueado)');
            } else {
                jQuery(this).prop('disabled', false);
                jQuery(this).text(originalTextsCache[key]);
            }
        });

        if (initialUserValues.hasPreviousConfig && selectedStorageValue < currentStorageValue) {
            $storageSelect.val(initialUserValues.storage).trigger('change');
            logDebug('Reverted storage selection to current value');
        }
    } catch (err) {
        logError('Error updating storage options', { error: err && err.message });
    }
}

function updateUserOptions(config, currentSelection) {
    try {
        var $userSelect = jQuery('#num_users');
        if (!$userSelect.length) return;

        var currentUsers = initialUserValues.users;
        var selectedUsers = parseInt(currentSelection.users, 10) || 0;

        $userSelect.find('option').each(function() {
            var key = 'num_users_' + jQuery(this).val();
            if (!originalTextsCache[key]) originalTextsCache[key] = jQuery(this).text();
        });

        $userSelect.find('option').each(function() {
            var optionValue = parseInt(jQuery(this).val(), 10);
            var key = 'num_users_' + jQuery(this).val();

            if (initialUserValues.hasPreviousConfig && optionValue < currentUsers) {
                jQuery(this).prop('disabled', true);
                jQuery(this).text(originalTextsCache[key] + ' (Downgrade bloqueado)');
            } else {
                jQuery(this).prop('disabled', false);
                jQuery(this).text(originalTextsCache[key]);
            }
        });

        if (initialUserValues.hasPreviousConfig && selectedUsers < currentUsers) {
            $userSelect.val(initialUserValues.users).trigger('change');
            logDebug('Reverted user selection to current value');
        }
    } catch (err) {
        logError('Error updating user options', { error: err && err.message });
    }
}

function updateFrequencyOptions(config, currentSelection) {
    logDebug('Frequency options updated', currentSelection.frequency);
}

/* ---------------------------
   Price calculation & display WITH PRORATION
   --------------------------- */

function updatePriceDisplay(config, applyProrationLogic) {
    try {
        if (typeof applyProrationLogic === 'undefined') applyProrationLogic = false;

        var calculation = calculateTotalPrice(config);
        var displayInfo = {
            mainPrice: calculation.totalPrice,
            proratedAmount: 0,
            isProrated: false,
            isUpgrade: false
        };

        if (applyProrationLogic && initialUserValues.subscriptionData) {
            var prorationResult = calculateProration(initialUserValues.subscriptionData, calculation);
            if (prorationResult.isValid) {
                displayInfo.proratedAmount = prorationResult.proratedAmount;
                displayInfo.mainPrice = prorationResult.proratedAmount;
                displayInfo.isProrated = true;
                displayInfo.isUpgrade = prorationResult.isUpgrade;
                displayInfo.daysRemaining = prorationResult.daysRemaining;
                displayInfo.currentProportionalValue = prorationResult.currentProportionalValue;
                displayInfo.newProportionalValue = prorationResult.newProportionalValue;
            }
        }

        var formattedPrice = formatPrice(displayInfo.mainPrice, config.currency_symbol || 'R$');
        var periodText = getPeriodText(calculation.frequencyValue || jQuery('#payment_frequency').val() || (config && config.current_frequency) || 'monthly');
        var displayText = formattedPrice + periodText;

        var $display = jQuery('#total_price_display, input[name="total_price_display"], .total_price_display, .pmpro_price_display').first();
        if ($display.length) {
            if ($display.is('input,textarea')) {
                $display.val(displayText);
            } else {
                $display.text(displayText);
            }
            logDebug('Price display updated', { displayText: displayText, totalPrice: displayInfo.mainPrice });
        } else {
            var $label = jQuery('.pmpro_checkout-field-price-display label, label.pmpro_price_label').first();
            if ($label.length) {
                var $inline = $label.find('.pmpro_price_display_inline');
                if (!$inline.length) $inline = jQuery('<span class="pmpro_price_display_inline"></span>').appendTo($label);
                $inline.text(' ' + displayText);
                logDebug('Price injected into label fallback', { displayText: displayText });
            } else {
                logDebug('No price element found to update', {});
            }
        }

        if (applyProrationLogic) {
            var decision = determineProrationDecision(window.nextcloud_banda_pricing || {}, {
                storage: jQuery('#storage_space').val(),
                users: jQuery('#num_users').val(),
                frequency: jQuery('#payment_frequency').val()
            }, calculation, config, displayInfo);

            logDebug('Proration decision', decision);

            if (decision.blockedDowngrade) {
                restoreNormalUI();
                applyDowngradeWarning(decision.reason);
            } else {
                jQuery('.pmpro-downgrade-warning').remove();
                if (decision.shouldProrate && displayInfo.isProrated) {
                    showProratedUI(config, {
                        proratedValue: displayInfo.proratedAmount,
                        totalPrice: displayInfo.mainPrice,
                        nextCycleValue: calculation.totalPrice,
                        daysRemaining: displayInfo.daysRemaining,
                        isUpgrade: displayInfo.isUpgrade,
                        currentProportionalValue: displayInfo.currentProportionalValue,
                        newProportionalValue: displayInfo.newProportionalValue
                    });
                } else {
                    restoreNormalUI();
                }
            }
        } else {
            restoreNormalUI();
            logDebug('Displaying normal price without proration logic');
        }

        jQuery(document).trigger('pmprobandaspricing:updated', [calculation]);

    } catch (err) {
        logError('Error updating price display', { error: err && err.message });
    }
}

function calculateTotalPrice(config) {
    try {
        var storageSpace = jQuery('#storage_space').val() || '1tb';
        var numUsers = parseInt(jQuery('#num_users').val(), 10) || 2;
        var paymentFrequency = jQuery('#payment_frequency').val() || (config && config.current_frequency) || 'monthly';

        logDebug('Calculating price for', { storageSpace: storageSpace, numUsers: numUsers, paymentFrequency: paymentFrequency });

        var cacheKey = storageSpace + '_' + numUsers + '_' + paymentFrequency;
        if (priceCache[cacheKey]) {
            logDebug('Price retrieved from cache', { cacheKey: cacheKey, price: priceCache[cacheKey] });
            return priceCache[cacheKey];
        }

        var basePrice = parseFloat((config && config.base_price)) || NEXTCLOUD_BANDA_BASE_PRICE;
        var pricePerTb = parseFloat((config && config.price_per_tb)) || 70.00;
        var pricePerUser = parseFloat((config && config.price_per_user)) || 10.00;
        var baseUsersIncluded = parseInt((config && config.base_users_included), 10) || 2;
        var baseStorageIncluded = parseInt((config && config.base_storage_included), 10) || 1;

        var storageTb = parseStorageValue(storageSpace);
        var additionalTb = Math.max(0, storageTb - baseStorageIncluded);

        var storagePrice = basePrice + (pricePerTb * additionalTb);

        var additionalUsers = Math.max(0, numUsers - baseUsersIncluded);
        var userPrice = pricePerUser * additionalUsers;

        var combinedPrice = storagePrice + userPrice;

        var frequencyMultipliers = (config && config.frequency_multipliers) || {
            'monthly': 1.0, 'semiannual': 5.7, 'annual': 10.8, 'biennial': 20.4, 'triennial': 28.8, 'quadrennial': 36.0, 'quinquennial': 42.0
        };
        var frequencyMultiplier = frequencyMultipliers[paymentFrequency] || 1.0;
        var totalPrice = Math.ceil(combinedPrice * frequencyMultiplier);

        var result = {
            storagePrice: storagePrice,
            userPrice: userPrice,
            combinedPrice: combinedPrice,
            frequencyMultiplier: frequencyMultiplier,
            totalPrice: totalPrice,
            storageSpace: storageSpace,
            numUsers: numUsers,
            frequencyValue: paymentFrequency
        };

        priceCache[cacheKey] = result;
        logDebug('Price calculated and cached', result);
        return result;

    } catch (err) {
        logError('Error calculating total price', { error: err && err.message });
        return {
            totalPrice: NEXTCLOUD_BANDA_BASE_PRICE,
            storagePrice: NEXTCLOUD_BANDA_BASE_PRICE,
            userPrice: 0,
            combinedPrice: NEXTCLOUD_BANDA_BASE_PRICE,
            frequencyMultiplier: 1.0,
            storageSpace: '1tb',
            numUsers: 2,
            frequencyValue: 'monthly'
        };
    }
}

/* ---------------------------
   PRORATION CALCULATION - CORE FUNCTION
   --------------------------- */

function calculateProration(currentSubscription, newCalculation) {
    try {
        if (!currentSubscription || !currentSubscription.subscription_end_date || !currentSubscription.subscription_start_date) {
            return { isValid: false, proratedAmount: newCalculation.totalPrice };
        }

        // Parse dates
        var cycleStart = new Date(currentSubscription.subscription_start_date);
        var cycleEnd = new Date(currentSubscription.subscription_end_date);
        var now = new Date();

        // Calculate total days in cycle
        var totalDays = Math.ceil((cycleEnd - cycleStart) / (1000 * 60 * 60 * 24));
        if (totalDays <= 0) {
            return { isValid: false, proratedAmount: newCalculation.totalPrice };
        }

        // Calculate days remaining
        var daysRemaining = Math.ceil((cycleEnd - now) / (1000 * 60 * 60 * 24));
        if (daysRemaining <= 0) {
            return { isValid: false, proratedAmount: newCalculation.totalPrice };
        }

        // Current subscription amount
        var currentAmount = parseFloat(currentSubscription.final_amount) || 0;
        if (currentAmount <= 0) {
            return { isValid: false, proratedAmount: newCalculation.totalPrice };
        }

        // Calculate proportional values
        var currentProportionalValue = (currentAmount * daysRemaining) / totalDays;
        var newProportionalValue = (newCalculation.totalPrice * daysRemaining) / totalDays;
        
        // Calculate adjustment (can be positive for upgrades, negative for downgrades)
        var adjustment = newProportionalValue - currentProportionalValue;
        
        // For upgrades: charge the difference
        // For downgrades: could be refund (negative) but we block them anyway
        var proratedAmount = Math.max(0, adjustment); // Ensure non-negative
        
        // Determine if it's an upgrade
        var isUpgrade = adjustment > 0;

        var result = {
            isValid: true,
            proratedAmount: Math.round(proratedAmount * 100) / 100, // Round to 2 decimals
            daysRemaining: daysRemaining,
            totalDays: totalDays,
            currentProportionalValue: Math.round(currentProportionalValue * 100) / 100,
            newProportionalValue: Math.round(newProportionalValue * 100) / 100,
            adjustment: Math.round(adjustment * 100) / 100,
            isUpgrade: isUpgrade,
            currentAmount: currentAmount,
            newAmount: newCalculation.totalPrice
        };

        logDebug('Proration calculated', result);
        return result;

    } catch (err) {
        logError('Error calculating proration', { error: err && err.message });
        return { isValid: false, proratedAmount: newCalculation.totalPrice };
    }
}

/* ---------------------------
   Utils & UI helpers
   --------------------------- */

function parseStorageValue(value) {
    if (typeof value !== 'string') return 1;
    var match = value.match(/^(\d+(?:\.\d+)?)\s*(tb|gb)$/i);
    if (match) {
        var num = parseFloat(match[1]);
        var unit = match[2].toLowerCase();
        return unit === 'gb' ? num / 1024 : num;
    }
    return parseFloat(value) || 1;
}

function formatPrice(amount, currencySymbol) {
    if (isNaN(amount)) amount = 0;
    return currencySymbol + ' ' + parseFloat(amount).toFixed(2).replace('.', ',');
}

function getPeriodText(frequency) {
    var periodTexts = {
        'monthly': '/mês', 'semiannual': '/semestre', 'annual': '/ano',
        'biennial': '/2 anos', 'triennial': '/3 anos', 'quadrennial': '/4 anos', 'quinquennial': '/5 anos'
    };
    return periodTexts[frequency] || '/mês';
}

function clearPreviousClientState() {
    try {
        priceCache = {};
        originalTextsCache = {};
        logDebug('Client state cleared');
    } catch (err) {
        logError('Error clearing client state', { error: err && err.message });
    }
}

function hasUserMadeChanges() {
    if (!initialUserValues.hasPreviousConfig) return false;
    
    var currentStorage = jQuery('#storage_space').val();
    var currentUsers = jQuery('#num_users').val();
    var currentFrequency = jQuery('#payment_frequency').val();
    
    return (
        currentStorage !== initialUserValues.storage ||
        currentUsers !== initialUserValues.users.toString() ||
        currentFrequency !== initialUserValues.frequency
    );
}

function isUpgradeChange() {
    if (!initialUserValues.hasPreviousConfig || !initialUserValues.subscriptionData) return false;
    
    var currentStorage = parseStorageValue(initialUserValues.storage);
    var selectedStorage = parseStorageValue(jQuery('#storage_space').val());
    var currentUsers = parseInt(initialUserValues.users, 10);
    var selectedUsers = parseInt(jQuery('#num_users').val(), 10);
    
    // Compare frequencies by order
    var frequencyOrder = {
        'monthly': 1, 'semiannual': 2, 'annual': 3, 'biennial': 4, 
        'triennial': 5, 'quadrennial': 6, 'quinquennial': 7
    };
    
    var currentFreqOrder = frequencyOrder[initialUserValues.frequency] || 1;
    var selectedFreqOrder = frequencyOrder[jQuery('#payment_frequency').val()] || 1;
    
    return (
        selectedStorage > currentStorage ||
        selectedUsers > currentUsers ||
        selectedFreqOrder > currentFreqOrder
    );
}

/* ---------------------------
   Proration & UI warnings (enhanced logic)
   --------------------------- */

function determineProrationDecision(config, selection, calculation, originalConfig, displayInfo) {
    try {
        var decision = { 
            shouldProrate: false, 
            blockedDowngrade: false, 
            reason: '', 
            proratedValue: 0, 
            nextCycleValue: calculation.totalPrice, 
            estimated: false 
        };

        if (!initialUserValues.hasPreviousConfig || !hasUserMadeChanges()) {
            logDebug('No previous config or no changes, no proration needed');
            return decision;
        }

        var currentStorage = parseStorageValue(initialUserValues.storage);
        var selectedStorage = parseStorageValue(selection.storage);
        var currentUsers = initialUserValues.users;
        var selectedUsers = parseInt(selection.users, 10);

        if (selectedStorage < currentStorage || selectedUsers < currentUsers) {
            decision.blockedDowngrade = true;
            decision.reason = 'Downgrades não são permitidos. Entre em contato com o suporte para alterações.';
            logDebug('Downgrade blocked', { 
                currentStorage: currentStorage, 
                selectedStorage: selectedStorage, 
                currentUsers: currentUsers, 
                selectedUsers: selectedUsers 
            });
            return decision;
        }

        if (isUpgradeChange() && displayInfo.isProrated) {
            decision.shouldProrate = true;
            decision.proratedValue = displayInfo.proratedAmount;
            decision.nextCycleValue = calculation.totalPrice;
            decision.estimated = false;
            logDebug('Upgrade detected, proration applied', decision);
        }

        return decision;
    } catch (err) {
        logError('Error in proration decision', { error: err && err.message });
        return { 
            shouldProrate: false, 
            blockedDowngrade: false, 
            reason: '', 
            proratedValue: 0, 
            nextCycleValue: calculation.totalPrice, 
            estimated: true 
        };
    }
}

function showProratedUI(config, prorationData) {
    try {
        jQuery('.pmpro-downgrade-warning').remove();
        var $warning = jQuery('<div class="pmpro-downgrade-warning" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin: 15px 0; font-size: 0.9em;"></div>');
        
        var warningHtml = '<strong>⚖️ Upgrade detectado:</strong> ';
        warningHtml += 'Você está atualizando seu plano. ';
        
        if (prorationData.daysRemaining) {
            warningHtml += 'Restam <strong>' + prorationData.daysRemaining + ' dias</strong> no ciclo atual.<br>';
        }
        
        warningHtml += '<div style="margin-top: 8px; font-size: 0.85em;">';
        warningHtml += '• Valor proporcional do plano atual: <strong>' + formatPrice(prorationData.currentProportionalValue, 'R$') + '</strong><br>';
        warningHtml += '• Valor proporcional do novo plano: <strong>' + formatPrice(prorationData.newProportionalValue, 'R$') + '</strong><br>';
        warningHtml += '• Ajuste a ser cobrado: <strong>' + formatPrice(prorationData.proratedValue, 'R$') + '</strong>';
        warningHtml += '</div>';
        
        warningHtml += '<div style="margin-top: 8px; font-size: 0.85em; color: #155724;">';
        warningHtml += '✓ O novo valor de <strong>' + formatPrice(prorationData.nextCycleValue, 'R$') + '</strong> será aplicado no próximo ciclo.';
        warningHtml += '</div>';

        $warning.html(warningHtml);

        var $priceField = jQuery('#total_price_display').closest('.pmpro_checkout-field');
        if ($priceField.length) $warning.insertBefore($priceField);
        else jQuery('.pmpro_form').prepend($warning);

        logDebug('Prorated UI shown', prorationData);
    } catch (err) {
        logError('Error showing prorated UI', { error: err && err.message });
    }
}

function applyDowngradeWarning(reason) {
    try {
        jQuery('.pmpro-downgrade-warning').remove();
        var $warning = jQuery('<div class="pmpro-downgrade-warning" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; margin: 15px 0; font-size: 0.9em; color: #721c24;"></div>');
        $warning.html('<strong>⚠️ Ação bloqueada:</strong> ' + reason);

        var $priceField = jQuery('#total_price_display').closest('.pmpro_checkout-field');
        if ($priceField.length) $warning.insertBefore($priceField);
        else jQuery('.pmpro_form').prepend($warning);

        jQuery('input[name="submit"], button[type="submit"], #pmpro_btn_submit').prop('disabled', true).css('opacity', '0.6');

        logDebug('Downgrade warning applied', { reason: reason });
    } catch (err) {
        logError('Error applying downgrade warning', { error: err && err.message });
    }
}

function restoreNormalUI() {
    try {
        jQuery('.pmpro-downgrade-warning').remove();
        jQuery('input[name="submit"], button[type="submit"], #pmpro_btn_submit').prop('disabled', false).css('opacity', '1');
        logDebug('Normal UI restored');
    } catch (err) {
        logError('Error restoring normal UI', { error: err && err.message });
    }
}

/* ---------------------------
   Helpers logs
   --------------------------- */

function logDebug(message, data) {
    if (debugMode && console && console.log) {
        console.log('[PMPro Banda Debug] ' + message, data || '');
    }
}
function logError(message, data) {
    if (console && console.error) {
        console.error('[PMPro Banda Error] ' + message, data || '');
    }
}
