<?php
// PMPpro pricing tables

if (!defined('ABSPATH')) exit;

function generate_pricing_tables($atts) {
    $atts = shortcode_atts(array(
        'levels' => '',
        'button_label' => 'Sign Up',
        'cycle_period_translation' => '',
        'decimal_separator' => '.',
        'currency_symbol' => '$',
        'free_text' => 'Free',
        'columns' => 3
    ), $atts);

    $levels_to_show = array_map('intval', explode(',', $atts['levels']));

    if (!function_exists('pmpro_getLevel')) {
        return '<p>Error: Paid Memberships Pro no está activo.</p>';
    }

    $columns = max(1, min(4, intval($atts['columns'])));
    $column_width = (100 / $columns) . '%';

    $unique_id = uniqid('pricing-tables-');

    $output = '<div class="pricing-tables ' . esc_attr($unique_id) . '">';
    $output .= '<style>
    .' . esc_attr($unique_id) . ' .columns {
      float: left;
      width: ' . esc_attr($column_width) . ';
      padding: 16px 2px;
    }
    .' . esc_attr($unique_id) . ' .price {
      list-style-type: none;
      border: 1px solid #041e3e;
      margin: 0;
      padding: 0;
    }
    .' . esc_attr($unique_id) . ' .price .header {
      background-color: #041e3e;
      font-family: var(--wp--preset--font-family--oswold);
      color: white !important;
      font-size: 1.5rem;
    }
    .' . esc_attr($unique_id) . ' .price li {
      background-color: #fff;
      border-bottom: 1px solid #fff;
      padding: 15px;
      text-align: center;
      font-size: 98%;
    }
    .' . esc_attr($unique_id) . ' .price .description, .' . esc_attr($unique_id) . ' .price .description li {
      text-align: left;
    }
    .' . esc_attr($unique_id) . ' .price .description li {
      padding-top: 8px;
      padding-bottom: 0;
    }
    .' . esc_attr($unique_id) . ' .price .grey {
      background-color: #e5e5e5;
      font-size: 20px;
      font-weight: 500;
      color: #000;
    }
    .' . esc_attr($unique_id) . ' .button {
      background-color: #15509e;
      border: none;
          border-radius: 5px;
      color: white;
      padding: 10px 20px;
      text-align: center;
      text-decoration: none;
      font-size: 16px;
    }
    .' . esc_attr($unique_id) . ' .button.disabled {
      background-color: #999;
      cursor: not-allowed;
    }
    .' . esc_attr($unique_id) . ' .button:hover {
      color: white !important;
      text-decoration: underline !important;
    }
    @media only screen and (max-width: 600px) {
      .' . esc_attr($unique_id) . ' .columns {
        width: 100%;
      }
    }
    </style>';

    foreach ($levels_to_show as $level_id) {
        $level = pmpro_getLevel($level_id);
        
        if ($level) {
            $amount = number_format($level->billing_amount, 2, $atts['decimal_separator'], '');
            $cycle_period = !empty($atts['cycle_period_translation']) ? $atts['cycle_period_translation'] : $level->cycle_period;
            $currency_symbol = esc_html($atts['currency_symbol']);
            $price_display = $level->billing_amount == 0 ? esc_html($atts['free_text']) : $currency_symbol . ' ' . esc_html($amount) . ' / ' . esc_html($cycle_period);

            // Verificar si el usuario ya tiene este nivel
            $user_has_level = pmpro_hasMembershipLevel($level_id);

            $button_label = $user_has_level ? 'Seu plano' : esc_html($atts['button_label']);
            $button_class = $user_has_level ? 'button disabled' : 'button';

            $output .= '<div class="columns">
                          <ul class="price">
                            <li class="header">' . esc_html($level->name) . '</li>
                            <li class="grey">' . $price_display . '</li>
                            <li class="description">' . wp_kses_post($level->description) . '</li>
                            <li class="grey"><a href="' . ($user_has_level ? '#' : esc_url(pmpro_url("checkout", "?pmpro_level=" . $level->id))) . '" class="' . esc_attr($button_class) . '">' . esc_html($button_label) . '</a></li>
                          </ul>
                        </div>';
        }
    }

    $output .= '</div>';
    return $output;
}

add_shortcode('pricing_tables', 'generate_pricing_tables');
