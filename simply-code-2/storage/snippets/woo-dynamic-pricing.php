<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function get_pricing_data() {
    $add_tb = 120;
    $frequency_options = array(
        'monthly'      => array('label' => 'Mensal', 'months' => 1),
        'semiannual'   => array('label' => 'Semestral (-5%)', 'months' => 5.7),
        'annual'       => array('label' => 'Anual (-10%)', 'months' => 10.8),
        /*'biennial'     => array('label' => 'Bienal (-15%)', 'months' => 20.4),
        'triennial'    => array('label' => 'Trienal (-20%)', 'months' => 28.8),
        'quadrennial'  => array('label' => 'Cuadrienal (-25%)', 'months' => 36),
        'quinquennial' => array('label' => 'Quinquenal (-30%)', 'months' => 42),*/
    );

    $storage_options = array(
        '1tb' => array('label' => '1 Terabytes', 'multi' => 0),
        '2tb' => array('label' => '2 Terabytes', 'multi' => 1),
        '3tb' => array('label' => '3 Terabytes', 'multi' => 2),
        '4tb' => array('label' => '4 Terabytes', 'multi' => 3),
        '5tb' => array('label' => '5 Terabytes', 'multi' => 4),
        '6tb' => array('label' => '6 Terabytes', 'multi' => 5),
        '7tb' => array('label' => '7 Terabytes', 'multi' => 6),
        '8tb' => array('label' => '8 Terabytes', 'multi' => 7),
        '9tb' => array('label' => '9 Terabytes', 'multi' => 8),
        '10tb' => array('label' => '10 Terabytes', 'multi' => 9),
        '15tb' => array('label' => '15 Terabytes', 'multi' => 14),
        '20tb' => array('label' => '20 Terabytes', 'multi' => 19),
        '30tb' => array('label' => '30 Terabytes', 'multi' => 29),
        '40tb' => array('label' => '40 Terabytes', 'multi' => 39),
        '50tb' => array('label' => '50 Terabytes', 'multi' => 49),
        '60tb' => array('label' => '60 Terabytes', 'multi' => 59),
        '70tb' => array('label' => '70 Terabytes', 'multi' => 69),
        '80tb' => array('label' => '80 Terabytes', 'multi' => 79),
        '90tb' => array('label' => '90 Terabytes', 'multi' => 89),
        '100tb' => array('label' => '100 Terabytes', 'multi' => 99),
        '200tb' => array('label' => '200 Terabytes', 'multi' => 199),
        '300tb' => array('label' => '300 Terabytes', 'multi' => 299),
        '400tb' => array('label' => '400 Terabytes', 'multi' => 399),
        '500tb' => array('label' => '500 Terabytes', 'multi' => 499),
    );

    return array(
        'add_tb'           => $add_tb,
        'frequency_options'=> $frequency_options,
        'storage_options'  => $storage_options,
    );
}

/**
 * Encolar scripts y pasar datos
 */
function wc_enqueue_dynamic_pricing_scripts() {
    $pricing_data = get_pricing_data();

    wp_enqueue_script( 'wc-subscription-pricing-js', get_template_directory_uri() . '/wc-subscription-pricing.js', array( 'jquery' ), null, true );
    wp_localize_script( 'wc-subscription-pricing-js', 'wcPricingData', $pricing_data );
    wp_enqueue_style( 'wc-subscription-pricing-css', get_template_directory_uri() . '/wc-subscription-pricing.css' );
}
add_action( 'wp_enqueue_scripts', 'wc_enqueue_dynamic_pricing_scripts' );

/**
 * Mostrar selects DENTRO del formulario add-to-cart
 * Cambio: hook movido a woocommerce_before_add_to_cart_button
 */
function wc_add_pricing_dropdowns() {
    global $product;

    if ( ! $product || ! $product->is_type( 'simple' ) ) {
        return;
    }

    if ( get_post_meta( $product->get_id(), '_is_subscription_product', true ) !== 'yes' ) {
        return;
    }

    $pricing_data = get_pricing_data();
    ?>
    <div class="wc-subscription-pricing-wrapper">
        <p class="form-row form-row-wide">
            <label for="storage_space"><?php echo __( 'Espaço de armazenamento', 'simply-code' ); ?></label>
            <select id="storage_space" name="storage_space" class="wc-subscription-select">
                <?php foreach ( $pricing_data['storage_options'] as $key => $option ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $option['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="form-row form-row-wide">
            <label for="payment_frequency"><?php echo __( 'Frequência de pagamento', 'simply-code' ); ?></label>
            <select id="payment_frequency" name="payment_frequency" class="wc-subscription-select">
                <?php foreach ( $pricing_data['frequency_options'] as $key => $option ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $option['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
    </div>
    <?php
}
add_action( 'woocommerce_before_add_to_cart_button', 'wc_add_pricing_dropdowns' );

/**
 * Guardar selecciones en el cart item data
 * Añadimos unique_key para evitar merges
 */
function wc_add_pricing_options_to_cart_item( $cart_item_data, $product_id ) {
    if ( isset( $_REQUEST['storage_space'] ) && isset( $_REQUEST['payment_frequency'] ) ) {
        $cart_item_data['storage_space'] = sanitize_text_field( wp_unslash( $_REQUEST['storage_space'] ) );
        $cart_item_data['payment_frequency'] = sanitize_text_field( wp_unslash( $_REQUEST['payment_frequency'] ) );
        $cart_item_data['unique_key'] = md5( microtime() . rand() ); // evita que artículos con distintas opciones se combinen
    }
    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'wc_add_pricing_options_to_cart_item', 10, 2 );

/**
 * Aplicar precio personalizado en el carrito
 * Uso prioridad 20 y casteo float
 */
function wc_apply_custom_pricing_to_cart( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( empty( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
        return;
    }

    $pricing_data = get_pricing_data();

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['storage_space'] ) && isset( $cart_item['payment_frequency'] ) && isset( $cart_item['data'] ) ) {
            $selected_storage = $cart_item['storage_space'];
            $selected_frequency = $cart_item['payment_frequency'];
            $product_obj = $cart_item['data']; // ya es el objeto product en el carrito
            $base_price = (float) $product_obj->get_price(); // get_price() es más seguro que regular_price

            $add_tb = (float) $pricing_data['add_tb'];
            $storage_multi = isset( $pricing_data['storage_options'][$selected_storage]['multi'] ) ? (float) $pricing_data['storage_options'][$selected_storage]['multi'] : 0;
            $months = isset( $pricing_data['frequency_options'][$selected_frequency]['months'] ) ? (float) $pricing_data['frequency_options'][$selected_frequency]['months'] : 1;

            $storage_price = $base_price + ( $add_tb * $storage_multi );
            $total_price = ceil( $storage_price * $months );

            $cart_item['data']->set_price( (float) $total_price );
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'wc_apply_custom_pricing_to_cart', 20, 1 );

/**
 * Mostrar las selecciones en el carrito/checkout
 */
function wc_display_pricing_options_in_cart( $item_data, $cart_item ) {
    if ( isset( $cart_item['storage_space'] ) && isset( $cart_item['payment_frequency'] ) ) {
        $pricing_data = get_pricing_data();

        $storage_label = isset( $pricing_data['storage_options'][$cart_item['storage_space']]['label'] ) ? $pricing_data['storage_options'][$cart_item['storage_space']]['label'] : $cart_item['storage_space'];
        $frequency_label = isset( $pricing_data['frequency_options'][$cart_item['payment_frequency']]['label'] ) ? $pricing_data['frequency_options'][$cart_item['payment_frequency']]['label'] : $cart_item['payment_frequency'];

        $item_data[] = array(
            'key'   => __( 'Espaço de armazenamento', 'simply-code' ),
            'value' => $storage_label,
        );
        $item_data[] = array(
            'key'   => __( 'Frequência de pagamento', 'simply-code' ),
            'value' => $frequency_label,
        );
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'wc_display_pricing_options_in_cart', 10, 2 );

/**
 * Checkbox admin para marcar producto como subscription
 */
function wc_add_subscription_checkbox() {
    woocommerce_wp_checkbox( array(
        'id'            => '_is_subscription_product',
        'wrapper_class' => 'show_if_simple',
        'label'         => __( 'Subscription Product', 'simply-code' ),
        'description'   => __( 'Check this box if this product has custom pricing based on options.', 'simply-code' ),
    ) );
}
add_action( 'woocommerce_product_options_general_product_data', 'wc_add_subscription_checkbox' );

function wc_save_subscription_checkbox( $post_id ) {
    $is_subscription = isset( $_POST['_is_subscription_product'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_is_subscription_product', $is_subscription );
}
add_action( 'woocommerce_process_product_meta', 'wc_save_subscription_checkbox' );
