/**
 * wc-subscription-pricing.js
 *
 * This script handles the pricing calculation and data submission
 * for dynamic subscription options.
 */

(function($) {
    $(function(){
        // Asegurar que values están sincronizados antes de que WooCommerce capture el formulario
        $(document).on('click', 'button.single_add_to_cart_button', function(){
            // Si tus selects tienen name="" ya se incluirán; aquí por seguridad.
            var storage = $('#storage_space').val();
            var freq = $('#payment_frequency').val();
            // Si quieres mantener hidden fields:
            $('#hidden_storage_space').val(storage);
            $('#hidden_payment_frequency').val(freq);
        });
    });

    $(document).ready(function() {
        var $form = $('form.cart');
        var $selects = $('.wc-subscription-select');
        var $priceDisplay = $('.woocommerce-Price-amount');
        var originalPrice = parseFloat($priceDisplay.first().text().replace(/[^\d,.]/g, '').replace(',', '.'));
        
        // Cachar los campos ocultos
        var $hiddenStorage = $('#hidden_storage_space');
        var $hiddenFrequency = $('#hidden_payment_frequency');

        function updatePrice() {
            var selectedStorage = $('#storage_space').val();
            var selectedFrequency = $('#payment_frequency').val();

            var newPrice = originalPrice;
            
            // Obtener los datos de pricing desde el objeto global
            var add_tb = wcPricingData.add_tb;
            var storageOptions = wcPricingData.storage_options;
            var frequencyOptions = wcPricingData.frequency_options;

            // Multiplicador de almacenamiento
            var storageMulti = storageOptions[selectedStorage] ? storageOptions[selectedStorage].multi : 0;
            
            // Meses según la frecuencia
            var months = frequencyOptions[selectedFrequency] ? frequencyOptions[selectedFrequency].months : 1;

            // Calcular precio total con la fórmula exacta de PMPro
            var storagePrice = originalPrice + (add_tb * storageMulti);
            newPrice = Math.ceil(storagePrice * months);

            // Actualizar el precio mostrado
            var formattedPrice = wc_price_format(newPrice);
            $priceDisplay.html(formattedPrice);
        }

        // Interceptar el envío del formulario para asegurar que los datos estén presentes
        $form.on('submit', function() {
            var selectedStorage = $('#storage_space').val();
            var selectedFrequency = $('#payment_frequency').val();

            // Actualizar los valores de los campos ocultos justo antes del envío
            $hiddenStorage.val(selectedStorage);
            $hiddenFrequency.val(selectedFrequency);
        });

        // Llamar a la función de actualización de precio al cambiar un select
        $selects.on('change', updatePrice);

        // Formato simple de precio (ajustar según la configuración de WooCommerce)
        function wc_price_format(price) {
            var symbol = $('.woocommerce-Price-currencySymbol').first().text() || '$'; // Fallback
            var formatted = price.toFixed(2).replace('.', ',');
            return '<span class="woocommerce-Price-currencySymbol">' + symbol + '</span>' + ' ' + formatted.replace(/(\d)(?=(\d{3})+\,)/g, '$1.');
        }

        // Llamar a updatePrice al cargar la página para reflejar las selecciones iniciales
        updatePrice();
    });
})(jQuery);
