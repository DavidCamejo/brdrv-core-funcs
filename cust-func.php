<?php
// Enqueue_scripts
function theme_libs() {
    wp_deregister_script('jquery');
    wp_register_script('jquery', 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js', false, null);
    wp_enqueue_script('jquery');
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 'bootstrap_css', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.1/css/bootstrap.min.css' );
    wp_register_script('bootstrap_js', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.1/js/bootstrap.min.js', false, null);
}
add_action( 'wp_enqueue_scripts', 'theme_libs');

// Configure SMTP settings
function setup_phpmailer_init( $phpmailer ) {
    $phpmailer->IsSMTP();
    $phpmailer->Host = 'smtp-relay.sendinblue.com';
    $phpmailer->Port = 587;
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = 'jdavidcamejo@gmail.com';
    $phpmailer->Password = 'Ij8DcFwLxmZM6ytX';
    $phpmailer->From = 'web@brasdrive.com.br';
    $phpmailer->FromName = 'Brasdrive';
}
add_action( 'phpmailer_init', 'setup_phpmailer_init' );

// Redirigir copias de emails enviados al admin a un email secundario
function bcc_admin_emails($args) {
    $bcc_email = 'jdavidcamejo@gmail.com';
    if (!isset($args['headers'])) {
        $args['headers'] = '';
    }
    $args['headers'] .= "Bcc: $bcc_email\r\n";
    return $args;
}
add_filter('wp_mail', 'bcc_admin_emails');

// Back to Top Button
function back_to_top_button() {
?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function(event) {
            var offset = 500, duration = 400;
            var toTopButton = document.getElementById('toTop');

            window.addEventListener('scroll', function() {
                (window.pageYOffset > offset) ? toTopButton.style.display = 'block' : toTopButton.style.display = 'none';
            });

            toTopButton.addEventListener('click', function(event) {
                event.preventDefault();
                window.scroll({ top: 0, left: 0, behavior: 'smooth' });
            });
        });
    </script>
<?php
}
add_action( 'wp_footer', 'back_to_top_button' );

// Remove WP logo from login page
function custom_login_logo() {
    echo '<style type ="text/css">.login h1 a { visibility:hidden!important; }</style>';
}
add_action('login_head', 'custom_login_logo');

// Current year shortcode
function currentYear() {
    return date('Y');
}
add_shortcode( 'year', 'currentYear' );

// Establecer la cookie por defecto si no existe
function establecer_cookie_afiliado() {
    $cookie_name = 'wpam_id'; // Nombre de la cookie
    $default_affiliate_id = 2; // ID de afiliado por defecto
    $affiliate_id = isset($_GET['wpam_id']) ? $_GET['wpam_id'] : $default_affiliate_id;
    $verify_affiliate_id = $affiliate_id !== 2 ? verify_affiliate_id($affiliate_id) : 2;

    if ( empty($verify_affiliate_id) || !isset($_COOKIE['wpam_id']) ) {
        $affiliate_id = $default_affiliate_id;
        // Establecer la cookie con una duración de 10 años
        setcookie($cookie_name, $affiliate_id, time() + (86400 * 365 * 10), "/"); // 86400 = 1 día
    }
}
add_action('init', 'establecer_cookie_afiliado');

// Verificar si el afiliado existe
function verify_affiliate_id($affiliate_id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'wpam_affiliates';
    $get_user_id = $wpdb->get_var($wpdb->prepare("SELECT userId FROM $table_name WHERE affiliateId = %d", $affiliate_id));

    return $get_user_id;
}

function no_spaces() {
?>
<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
      var username = document.getElementById("username");
      
      // Actualizar el patrón para permitir sólo caracteres de palabra
      username.setAttribute("pattern", "\\w+");
      
      username.addEventListener("input", function(event) {
        var value = event.target.value;
        
        // Actualizar la expresión regular para reemplazar todo lo que no sea un caracter de palabra
        if (/\W/.test(value)) { // \W es equivalente a [^\w]
          event.preventDefault();
          event.target.value = value.replace(/\W/g, "");
        }
      });
    });

    // Prevent form submission by pressing Enter
    document.addEventListener("DOMContentLoaded", function() {
      var form = document.querySelector("form");
      form.addEventListener("keydown", function(event) {
        if (event.key === "Enter") {
          event.preventDefault();
        }
      });
    });

    // Add placeholder to username field
    document.addEventListener("DOMContentLoaded", function() {
        var input_ph = document.getElementById("username");
        input_ph.placeholder = "Só letras, números e sublinhado.";
    });

    // Add placeholder to name/company field
    document.addEventListener("DOMContentLoaded", function() {
        var input_ph = document.getElementById("first_name");
        input_ph.placeholder = "Seu nome ou nome da empresa.";
    });

    // Add placeholder to last name/department field
    document.addEventListener("DOMContentLoaded", function() {
        var input_ph = document.getElementById("last_name");
        input_ph.placeholder = "Seu sobrenome ou setor da empresa.";
    });

    // Add placeholder to CPF field
    document.addEventListener("DOMContentLoaded", function() {
        var input_ph = document.getElementById("user_cpf");
        input_ph.setAttribute("maxLength",18);
        input_ph.setAttribute("inputmode","numeric");
        input_ph.placeholder = "000.000.000-00";
    });

    // Add placeholder to Telefone field
    document.addEventListener("DOMContentLoaded", function() {
        var input_ph = document.getElementById("user_telefone");
        input_ph.setAttribute("maxLength",15);
        input_ph.setAttribute("inputmode","numeric");
        input_ph.placeholder = "(00) 00000.0000";
    });

    // Aplicar máscara a campo CPF/CNPJ
    document.addEventListener("DOMContentLoaded", function() {
      var inputMask = document.getElementById("user_cpf");
      inputMask.required = true;
      inputMask.addEventListener("input", function() {
        var n = documentDestroyMask(this.value);
        this.setAttribute("data-normalized", n);
        this.value = documentCreateMask(n);
      });

      function documentCreateMask(string) {
        var len = string.length;
        if (len <= 11) { // CPF
          return cpfCreateMask(string);
        } else { // CNPJ
          return cnpjCreateMask(string);
        }
      }

      function cpfCreateMask(string) {
        var maskedString = string.substring(0, Math.min(string.length, 3));
        if (string.length > 3) {
          maskedString += "." + string.substring(3, 6);
        }
        if (string.length > 6) {
          maskedString += "." + string.substring(6, 9);
        }
        if (string.length > 9) {
          maskedString += "-" + string.substring(9, 11);
        }
        return maskedString;
      }

      function cnpjCreateMask(string) {
        var maskedString = string.substring(0, Math.min(string.length, 2));
        if (string.length > 2) {
          maskedString += "." + string.substring(2, 5);
        }
        if (string.length > 5) {
          maskedString += "." + string.substring(5, 8);
        }
        if (string.length > 8) {
          maskedString += "/" + string.substring(8, 12);
        }
        if (string.length > 12) {
          maskedString += "-" + string.substring(12, 14);
        }
        return maskedString;
      }

      function documentDestroyMask(string) {
        return string.replace(/\D/g, "").substring(0, 14); // Máximo de 14 dígitos para CNPJ
      }

      // Se ajusta el patrón para validar tanto CPF como CNPJ
      inputMask.setAttribute("pattern", "(\\d{3}\\.\\d{3}\\.\\d{3}-\\d{2})|(\\d{2}\\.\\d{3}\\.\\d{3}/\\d{4}-\\d{2})");
    });

    // Aplicar máscara a campo Telefone
    document.addEventListener("DOMContentLoaded", function() {
        var inputMask = document.getElementById("user_telefone");
        inputMask.required = true;
        inputMask.addEventListener("input", function() {
            var n = telefone_destroyMask(this.value);
            this.setAttribute("data-normalized", n);
            this.value = telefone_createMask(n);
        });

      function telefone_createMask(string) {
        var maskedString = "";
        var len = string.length;
        if (len > 0) {
          maskedString += "(" + string.substring(0, Math.min(len, 2));
        }
        if (len > 2) {
          maskedString += ") " + string.substring(2, Math.min(len, 7));
        }
        if (len > 7) {
          maskedString += "." + string.substring(7, Math.min(len, 11));
        }
        return maskedString;
      }

        function telefone_destroyMask(string) {
            return string.replace(/\D/g, "").substring(0, 11);
        }

        inputMask.setAttribute("pattern", "&lparen;[0-9]{2}+&rparen;&nbsp;[0-9]{5}.[0-9]{4}");
    });
</script>
<?php
}
add_action( 'wp_footer', 'no_spaces' );

// Contador regresivo de expiración de cuenta
function expiration_counter() {
    $user_id = wp_get_current_user()->ID;
    $membership_level = pmpro_hasMembershipLevel('5', $user_id);
    $membership_enddate = pmpro_getMembershipLevelForUser($user_id);
    $membership_status = get_membership_status($user_id);
    date_default_timezone_set('America/Boa_Vista');

    if ($membership_level) {
        $enddate1 = $membership_enddate->enddate;
        $enddate2 = strtotime(ajustar_proxima_fecha_pago($user_id));
        $enddate = date( 'Y-m-d H:i:s', min( $enddate1, $enddate2 ) );
    } elseif ($membership_status == "expired") {
        $level = $membership_enddate->level;
        $enddate = "Sua avalialção gratuita expirou!";
?>
    <script type="text/javascript">
        window.onload = function() {
            var enlace = document.querySelector('div#pmpro_account-membership > table.pmpro_table > tbody > tr > td > a');
            enlace.href = 'https://brasdrive.com.br/minha-conta/planos-nextcloud/';
            enlace.innerText = 'Escolha um Plano.';
        };
    </script>
<?php
    }

    if ($membership_level) {
?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // Set the date we're counting down to
            var EndDate = document.getElementById("expiration").innerHTML;
            var countDownDate = new Date(EndDate).getTime();

            // Update the count down every 1 second
            var x = setInterval(function() {

                // Get today's date and time
                var now = new Date().getTime();

                // Find the distance between now and the count down date
                var distance = countDownDate - now;

                // Time calculations for days, hours, minutes and seconds
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                if( days < 10 ) {
                    days = "0" + days;
                }
                if( hours < 10 ) {
                    hours = "0" + hours;
                }
                if (minutes < 10) {
                    minutes = "0" + minutes;
                }
                if( seconds < 10 ) {
                    seconds = "0" + seconds;
                }

                // Output the result in an element with id="demo"
                document.getElementById("expiration").innerHTML = days + "d " + hours + "h " + minutes + "m " + seconds + "s";

                // If the count down is over, write some text 
                if (distance < 0) {
                    clearInterval(x);
                    document.getElementById("free_eval").innerHTML = "";
                    document.getElementById("expiration").innerHTML = "Sua avalialção gratuita expirou!";
                }
            }, 1000);
        });
    </script>
<?php
        return "<div class='plan_expiration' id='plano_teste'><span id='free_eval'>Sua avalialção gratuita expira em:</span> <span id='expiration' class='expiration'>" . $enddate . "</span></div>";
    } elseif ($level == 0 && $user_id !== 1) {
        return "<div class='plan_expiration' id='plano_teste'><span id='expiration' class='expiration expired'>" . $enddate . "</span></div>";
    }
}
add_action( 'wp_footer', 'expiration_counter' );
add_shortcode( 'expiration', 'expiration_counter' );

// Modifies Paid Memberships Pro to include more profile fields
function mytheme_add_fields_to_signup(){
    //don't break if Register Helper is not loaded
    if(!function_exists( 'pmprorh_add_registration_field' )) {
        return false;
    }

    $fields = array();

    $fields[] = new PMProRH_Field(
        'first_name',
        'text',
        array(
            'label' => 'Nome / Empresa',
            'size'  => 30,
            'profile'   => false,
            'required' => true,
            'memberslistcsv'  => true,
            'addmember'       => true,
            'location' => 'after_username',
        )
    );
    $fields[] = new PMProRH_Field(
        'last_name',
        'text',
        array(
            'label' => 'Sobrenome / Setor',
            'size'  => 30,
            'profile'   => false,
            'required' => true,
            'memberslistcsv'  => true,
            'addmember'       => true,
            'location' => 'after_username',
        )
    );
    $fields[] = new PMProRH_Field(
        'user_cpf',
        'text',
        array(
            'label'     => 'CPF / CNPJ',
            'size'      => 30,
            'profile'   => true,
            'required'  => true,
            'memberslistcsv'  => true,
            'addmember'       => true,
            'location' => 'after_username',
        )
    );
    $fields[] = new PMProRH_Field(
        'user_telefone',
        'text',
        array(
            'label'     => 'Telefone',
            'size'      => 30,
            'profile'   => true,
            'memberslistcsv'  => true,
            'addmember'       => true,
           'required'  => true,
            'location' => 'after_username',
        )
    );

    //add the fields to default forms
    foreach($fields as $field){
        pmprorh_add_registration_field(
            $field->location, // location on checkout page
            $field // PMProRH_Field object
        );
    }
}
add_action( 'init', 'mytheme_add_fields_to_signup' );

// Set default currency
function pmpro_brl_currency_format( $pmpro_currencies ) {
    $pmpro_currencies['BRL'] = array(
        'name' => __( 'Real Brasileiro', 'paid-memberships-pro' ),
        'decimals' => '2',
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'symbol' => 'R$ ',
        'position' => 'left',
    );
    return $pmpro_currencies;
}
add_filter( 'pmpro_currencies', 'pmpro_brl_currency_format' );

// Change string text on the site
if (!is_admin()) {
    function change_text_string( $text ) {
        $text = str_ireplace( 'Aceito o', 'Aceito os', $text );
        $text = str_ireplace( 'associação', 'assinatura', $text );
        $text = str_ireplace( 'Date de expiração', 'Expiração', $text );
        $text = str_ireplace( 'Nível de assinatura', 'Plano', $text );
        $text = str_ireplace( 'Nível', 'Plano', $text );
        $text = str_ireplace( 'o Plano Nextcloud', 'o plano ', $text );
        $text = str_ireplace( 'Nome de usuário', 'Usuário', $text );
        $text = str_ireplace( 'Minhas associações', 'Minha assinatura', $text );
        $text = str_ireplace( 'Tem certeza de que deseja cancelar a sua', 'Você deseja cancelar o seu Plano', $text );
        $text = str_ireplace( 'associação?', 'agora?', $text );
        $text = str_ireplace( 'Ver todas as opções de assinaturas', 'Ver mais opções...', $text );
        $text = str_ireplace( 'Você não tem uma assinatura ativa.', 'Você não tem um Plano ativo.', $text );
        $text = str_ireplace( 'A sua assinatura não está ativa.', 'Você não tem um Plano ativo.', $text );
        $text = str_ireplace( 'Exibir publicamente o nome como', 'Exibir usuário como', $text );
        $text = str_ireplace( 'Alterar', 'Trocar', $text );
        $text = str_ireplace( 'Minhas Assinaturas', 'Meu Plano', $text );
        $text = str_ireplace( 'A assinatura expira após', 'Avaliação por', $text );
        $text = str_ireplace( 'preço da assinatura', 'preço do plano', $text );
        //$text = str_ireplace( 'O preço do plano é ', '', $text );
        $text = str_ireplace( 'O preço da associação é ', 'O preço do plano é ', $text );
        $text = str_ireplace( 'Return to Home', 'Voltar á Página Inicial', $text );
        $text = str_ireplace( 'Associe-se Agora', 'Ativar um Plano', $text );
        $text = str_ireplace( 'Primeiro nome', 'Nome', $text );
        $text = str_ireplace( 'Último nome', 'Sobrenome', $text );
        $text = str_ireplace( 'logada no WordPress', 'logada no site', $text );
        $text = str_ireplace( 'logue em outra conta WordPress', 'faza login com outra conta.', $text );
        $text = str_ireplace( 'Apresentar a candidatura', 'Enviar solicitude', $text );
        $text = str_ireplace( 'Select a Payment Plan', 'Selecione um plano de pagamento', $text );
        $text = str_ireplace( 'Choose Your Payment Method', 'Escolha a forma de pagamento', $text );
        $text = str_ireplace( 'Pay by', 'Pagar com', $text );
        $text = str_ireplace( 'Pay with', 'Pagar com', $text );
        $text = str_ireplace( 'Membership Information', 'Detalhes do plano', $text );
        $text = str_ireplace( 'I agree to the', 'Concordo com os', $text );
        $text = str_ireplace( 'Show Password', 'Ver...', $text );
        $text = str_ireplace( 'Hide Password', 'Ocultar...', $text );
        $text = str_ireplace( 'Check Out With', 'Pagar com', $text );
        $text = str_ireplace( 'Order date', 'Data do pedido', $text );
        $text = str_ireplace( 'Order', 'Pedido', $text );
        $text = str_ireplace( 'Payment method', 'Forma de pagamento', $text );
        $text = str_ireplace( 'Pay to', 'Pago a', $text );
        $text = str_ireplace( 'Bill to', 'Faturado a', $text );
        $text = str_ireplace( 'View All', 'Ver', $text );
        $text = str_ireplace( 'for Pedido', 'com Pedido', $text );
        $text = str_ireplace( 'Print or Save as PDF', 'Imprimir ou salvar como PDF', $text );

        return $text;
    }
    add_filter( 'gettext', 'change_text_string' );


    function replace_text($text2) {
        $text2 = str_replace( '<label for="gateway_stripe" class="pmpro_form_label pmpro_form_label-inline pmpro_clickable"> Pagar com a Credit Card Here </label>', '<label for="gateway_stripe" class="pmpro_form_label pmpro_form_label-inline pmpro_clickable"> Stripe </label>', $text2 );
        $text2 = str_replace( '<h2 class="pmpro_form_heading pmpro_font-large">Informações de pagamento</h2>', '<h2 class="pmpro_form_heading pmpro_font-large">Opções de pagamento</h2>', $text2 );
        return $text2;
    }
    add_filter('the_content', 'replace_text');
}

// Redirect to Homepage after logout
function auto_redirect_after_logout(){
    $home_url = home_url();
    wp_safe_redirect( $home_url );
    exit;
}
add_action('wp_logout','auto_redirect_after_logout');

// Remitente personalizado
function custom_pmpro_email_sender_name( $sender_name ) {
    return 'Brasdrive';
}
add_filter( 'pmpro_email_sender_name', 'custom_pmpro_email_sender_name' );

// Esconde la barra de herramientas
apply_filters( 'pmpro_hide_toolbar', true );

// Disable comments in attachments
function filter_media_comment_status( $open, $post_id ) {
    $post = get_post( $post_id );
    if( $post->post_type == 'attachment' || $post->post_type == 'post' ) {
        return false;
    }
    return $open;
}
add_filter( 'comments_open', 'filter_media_comment_status', 10, 2 );

// Minify inline code
function minify_inline_code($htmlIn) {
    preg_match_all('#<(style|script).*>(.*)</(style|script)>#Usmi',$htmlIn,$code);
    foreach ($code[0] as $codeIn) {
        $repl["in"] = $codeIn;
        $repl["out"] = str_replace(array("\r\n", "\n", "\r", "\t"),"",$codeIn);
        $replArr[] = $repl;
    }
    foreach ($replArr as $replNow) {
        $htmlIn = str_replace($replNow["in"],$replNow["out"],$htmlIn);
    }
    return $htmlIn;
}
add_filter("autoptimize_html_after_minify","minify_inline_code");

// Remove version from head
remove_action('wp_head', 'wp_generator');

// remove version from rss
add_filter('the_generator', '__return_empty_string');

// remove version from scripts and styles
function remove_version_scripts_styles($src) {
    if (strpos($src, 'ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('style_loader_src', 'remove_version_scripts_styles', 9999);
add_filter('script_loader_src', 'remove_version_scripts_styles', 9999);

// Disable the emoji's
function disable_emojis() {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' ); 
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' ); 
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    add_filter( 'tiny_mce_plugins', 'disable_emojis_tinymce' );
    add_filter( 'wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2 );
}
add_action( 'init', 'disable_emojis' );

// Filter function used to remove the tinymce emoji plugin.
function disable_emojis_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) {
        return array_diff( $plugins, array( 'wpemoji' ) );
    } else {
        return array();
    }
}

// Remove emoji CDN hostname from DNS prefetching hints.
function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
    if ( 'dns-prefetch' == $relation_type ) {
        // This filter is documented in wp-includes/formatting.php
        $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
        $urls = array_diff( $urls, array( $emoji_svg_url ) );
    }
    return $urls;
}

// Hoja de estilos
function styles_sheet() {
?>
<style>
    #AccountNumber, .StripeElement .StripeElement--empty {
        #cadbe6 !important;
    }

    .pmpro_advanced_levels-div .pmpro_level {
        border-bottom: 1px solid #ccc;
    }
    #toTop {
        display: none;
        background-color: #15509E;
        color: #fff;
        font-weight: 600;
        font-size: 150%;
        opacity: 0.4;
        position: fixed;
        align-items: center;
        padding-top: .3em;
        border-radius: 3px;
        cursor:pointer;
        bottom: .35em;
        right: .5em;
        width: 1.5em;
        height: 1.5em;
        z-index: 999;
    }
    #toTop:hover {
        opacity: 0.7;
    }
    .animated {
        -webkit-animation-duration: 0s !important;
        animation-duration: 0s !important;
    }
    .sp-navigation {
        position: -webkit-fixed !important;
        position: fixed !important;
        z-index: 9999 !important;
        width: 100%;
        top: 0;
        transition: 0.2s;
        box-shadow: 0 0 10px 2px #151515;
        -webkit-box-shadow: 0 0 10px 2px #151515;
    }
    a:where(:not(.wp-block-navigation-item__label)), .pmpro_actions_nav :where(a), {
        color: #15509e !important;
    }
    @media (min-width: 360px) {
        .wp-block-navigation__responsive-container .wp-block-navigation-link a, .wp-block-navigation__responsive-container-close, .wp-block-navigation__responsive-container .wp-block-navigation-link a:active {
            color: #15509e !important;
        }
    }
    @media (min-width: 576px) {
        .wp-block-navigation__responsive-container .wp-block-navigation-link a, .wp-block-navigation__responsive-container .wp-block-navigation-link a:active {
            color: #fff !important;
        }
    }
    @media (min-width: 1024px) {
        .wp-block-navigation__responsive-container .wp-block-navigation-link a, .wp-block-navigation__responsive-container .wp-block-navigation-link a:active {
            color: #fff !important;
        }
    }
    header a:hover, header a:visited, footer a:hover, footer a:visited, .wp-element-button {
        text-decoration: underline;
        color: #fff !important;
    }
    .wp-elements-cb93764ab6ae6a5998dec498dc5831e8 .wp-block-site-title .has-large-font-size .has-system-font-family {
        color: #fff !important;
    }
    h1 a, h1 a:visited {
        transition: none !important;
        text-decoration: none !important;
    }
    .featItem {
        color: #15509e;
        font-size: 350% !important;
    }
    .featItemPad {
        padding-left: 20px;
    }
    .featItemProd {
        padding-right: 60px;
    }
    input[type="text"], input[type="search"], input[type="password"], form.pmpro_form input[type="email"], input[type="email"], textarea {
        background-color: #cadbe6 !important;
    }
    input[type="submit"] {
        background-color: #15509e !important;
    }
    input[type="submit"]:hover {
        background-color: #164b91 !important;
    }
    .pmpro_btn, .btn-primary {
        background-color: #15509e !important;
        color: #fff !important;
        border: none !important;
        font-weight: 500 !important;
    }
    .btn-info {
        background-color: #2b67b5 !important;
        color: #fff !important;
        border: none !important;
        font-weight: 500 !important;
    }
    .pmpro_btn a:hover, .btn-primary a:hover {
        background-color: #164b91 !important;
    }
    .mfp-image-holder .mfp-content {
          width: 60% !important;
          margin-top: 45px !important;
    }
    .wp-block-latest-posts-footer a {
        color: #fff !important;
    }
    #ExpirationMonth, #ExpirationYear {
        width: 45%;
    }
    .wp-site-blocks > * + * {
        margin-block-start: 0;
    }
    .storage, .plan_expiration {
        width: 100%;
        text-align: center;
    }
    .expiration {
        font-weight: 600;
    }
    .expired {
        color: #E9322D;
    }
    .nuvem {
        line-height: 1.1;
    }
    .icon-color-green {
        color: #008000;
    }
    .icon-color-orange {
        color: #FF7900;
    }
    .icon-color-red {
        color: #E9322D;
        animation: blinker 3s linear infinite;
    }
    @keyframes blinker {  
      50% { opacity: .3; }
    }
    .pmpro_advanced_levels-bootstrap h2 {
        font-size: 120%;
        font-weight: 500;
    }
    tr > th + th { width: 23%; }
    input::placeholder {
        color: #717171;
    }
    .nav-previous a {
        color: var(--wp--preset--color--primary) !important;
    }
    .nav-previous a:hover, .pmpro_actions_nav a:hover, #pmpro_account a:hover {
        text-decoration: underline !important;
    }
    .wp-block-post-title a, .site-title a {
        background: none !important;
    }
    .site-title a:active {
        text-decoration: none !important;
    }
    #pmpro_license {
        margin-bottom: 15px !important;
    }
    form.pmpro_form .pmpro_submit {
        margin-top: -0.5em !important;
    }
    .process-text {
        animation: blinker 1.2s linear infinite;
    }
    .pix-center {
        text-align: center;
        padding-top: 10px;
    }
    .txt-pix-center {
        font-weight: 600;
    }
    .img-pix-center {
        display: block;
        margin-left: auto;
        margin-right: auto;
        width: 50%;
    }

.tabs {
    font-family: Arial, sans-serif;
    width: 100%;
}

.tab-links {
    display: flex;
    justify-content: center;
    border-bottom: 1px solid #ccc;
}

.tab-link {
    background-color: #f1f1f1;
    border: none;
    outline: none;
    cursor: pointer;
    padding: 14px 16px;
    transition: background-color 0.3s ease;
    margin: 0;
}

.tab-link.active {
    background-color: #555;
    color: white;
}

.tab-content {
    display: none;
    padding: 20px;
    border-top: none;
}

.tab-content.active {
    display: block;
}

#wp-submit.button.button-primary:hover {
    color: white !important;
    text-decoration: underline !important;
}

.feather.feather-eye, .feather.feather-eye-off {
    fill: #fff !important;
}

.pmpro_form_field-password-toggle-state {
    padding-right: 5px;
}

.wp-site-blocks .wp-block-search.field-light-color .wp-block-search__inside-wrapper .wp-block-search__input {
        color: #000;
}
</style>
<?php
}
add_action( 'wp_footer', 'styles_sheet' );

//---------------------------------------------//

function check_nextcloud_enabled($username) {
    // Leer credenciales del administrador
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if (!$nextcloud_api_admin || !$nextcloud_api_pass) {
        error_log("Credenciales de Nextcloud no configuradas.");
        return false; // o lanzar una excepción, pero retornar false es más consistente
    }

    $nextcloud_authentication = base64_encode($nextcloud_api_admin . ':' . $nextcloud_api_pass);
    $site_url = get_option('siteurl');
    $nextcloud_url = 'https://cloud.' . basename($site_url);

    // Obtener el estatus de usuario Nextcloud
    $url = $nextcloud_url . "/ocs/v2.php/cloud/users/" . $username;

    $args = [
        'headers' => [
            'OCS-APIRequest: true',
            'Authorization: Basic ' . $nextcloud_authentication,
        ],
        'method' => 'GET',
        'sslverify' => true, // ¡MUY IMPORTANTE!
        'timeout' => 15,    // Timeout para evitar bloqueos
    ];

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        error_log("Error al verificar usuario en Nextcloud: " . $response->get_error_message() . " URL: " . $url);
        return false; // Manejar el error retornando false
    }

    $body = wp_remote_retrieve_body($response);
    $simplexml = simplexml_load_string($body);

    if ($simplexml === false) {
        error_log("Error al parsear XML de Nextcloud. Response: " . $body);
        return false;
    }

    $json = json_encode($simplexml);
    $obj = json_decode($json, true); // true para array asociativo

    // Verifica si el dato existe o no.  Usa isset() para mayor seguridad.
    $nextcloud_enabled = isset($obj['data']['enabled']) ? $obj['data']['enabled'] : 0;

    return $nextcloud_enabled;
}

// Register a custom schedule
function brdrv_custom_schedule( $schedules ) {
    $schedules[ 'check_daily_membership_expiring' ] = array(
        'interval' => 86400,
        'display'  => esc_html__( 'Every Day' )
    );

    return $schedules;
}

// Cron job: cada día a las 12 am (suscripciones por vencer)
function schedule_daily_membership_expiring_check() {
    // Hook to register the new custom schedule
    add_filter( 'cron_schedules', 'brdrv_custom_schedule' );

    if ( ! wp_next_scheduled( 'check_membership_expiring' ) ) {
        wp_schedule_event( strtotime( 'midnight America/Boa_Vista' ), 'check_daily_membership_expiring', 'check_membership_expiring' );
    }
    if ( ! wp_next_scheduled( 'check_membership_expiring2' ) ) {
        wp_schedule_event( strtotime( 'tomorrow noon America/Boa_Vista' ), 'check_daily_membership_expiring', 'check_membership_expiring2' );
    }
}
add_action( 'init', 'schedule_daily_membership_expiring_check' );

// Verificar suscripciones gratuitas vencidas y por vencer
function check_membership_expiration_status() {
    $users = get_users();
    $current_date = date('Y-m-d', time());
    $site_url = get_option('siteurl');
    $to_admin = get_option('admin_email');
    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Credenciales Nextcloud decodificadas
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if ( ! $nextcloud_api_admin || ! $nextcloud_api_pass ) {
        error_log("Credenciales de Nextcloud no configuradas.");
        return; // o lanzar una excepción.
    }

    $nextcloud_url = 'https://cloud.' . basename($site_url);
    $nextcloud_autentication = "$nextcloud_api_admin:$nextcloud_api_pass";
    $nextcloud_header = "OCS-APIRequest: true";

    foreach ($users as $user) {
        $user_id = $user->ID;
        $membership_status = get_membership_status($user_id);
        $membership_level = pmpro_getMembershipLevelForUser($user_id);

        if ($user_id !== 1) {
            // Si el usuario tiene una membresía activa de nivel 5
            if (($membership_status == "active" || $membership_status == "changed") && $membership_level->id == 5) {
                $enddate = $membership_level->enddate;
                $diff = date_diff(date_create($current_date), date_create(date('Y-m-d', $enddate)));
                $days_remaining = $diff->d;

                // Si la membresía está por vencer
                if (in_array($days_remaining, [15, 7, 3, 1])) {
                    send_membership_expiring_email($user_id, $days_remaining);
                }
            } elseif (in_array($membership_status, ["expired", "cancelled", "admin_cancelled"])) {
                $user_data = get_userdata($user_id);
                $membership_level_enddate = date('Y-m-d', strtotime($user_data->user_registered . ' + 30 days'));
                $diff = date_diff(date_create($membership_level_enddate), date_create($current_date));
                $days_exceeded = $diff->format('%a');
                $days_last_login = get_last_login_in_days($user_id);

                // Obtener el estado del usuario en Nextcloud
                $username = $user_data->user_login;
                $nextcloud_enabled = check_nextcloud_enabled($username);

                // Deshabilitar usuario en Nextcloud si está habilitado
                if ($nextcloud_enabled == 1) {
                    update_user_meta($user_id, 'free_level_expired', true);
                    $disable_response = nextcloud_api_request($nextcloud_url . "/ocs/v1.php/cloud/users/$username/disable", $nextcloud_autentication, "PUT");

                    if ($disable_response['status'] == 'ok') {
                        send_membership_expired_email($user_id);
                    }
                }

                // Alertar y eliminar usuario si ha excedido los días permitidos
                if ($days_exceeded >= 90 || $days_last_login >= 90) {
                    // Obtener el grupo del usuario antes de eliminarlo
                    $user_group = get_nextcloud_user_group($username, $nextcloud_url, $nextcloud_autentication);

                    // Eliminar el usuario en Nextcloud
                    $delete_user_response = nextcloud_api_request($nextcloud_url . "/ocs/v2.php/cloud/users/$username", $nextcloud_autentication, "DELETE");

                    if ($delete_user_response['statuscode'] == 100) {
                        // Si el usuario fue eliminado correctamente, eliminar el grupo al que pertenecía
                        if (!empty($user_group)) {
                            $delete_group_response = nextcloud_api_request($nextcloud_url . "/ocs/v1.php/cloud/groups/$user_group", $nextcloud_autentication, "DELETE");

                            if ($delete_group_response['statuscode'] == 100) {
                                // Grupo eliminado con éxito
                                wp_delete_user($user_id);
                                send_user_deletion_email($user, $days_last_login);
                            } else {
                                wp_mail($to_admin, "Error eliminando grupo", "Hubo un error al eliminar el grupo $user_group para el usuario $username.", $headers);
                            }
                        } else {
                            wp_delete_user($user_id);
                            send_user_deletion_email($user, $days_last_login);
                        }
                    } elseif ($days_exceeded >= 90 || $days_last_login >= 90) { // Temporal. Para usuarios sin datos en Nextcloud.
                                                wp_delete_user($user_id);
                                                send_user_deletion_email($user, $days_last_login);
                                        }
                                        
                } elseif ($days_exceeded > 30 && $days_exceeded < 90) {
                    $days_remainded = 90 - $days_exceeded;
                    wp_mail($to_admin, "Usuario $user_id no limbo", "Usuario $username será eliminado en $days_remainded días. Expiró el $membership_level_enddate.", $headers);
                }
            }
        }
    }
}
add_action( 'check_membership_expiring', 'check_membership_expiration_status' );
add_action( 'check_membership_expiring2', 'check_membership_expiration_status' );

// Obtener el grupo de un usuario en Nextcloud
function get_nextcloud_user_group($username, $nextcloud_url, $nextcloud_autentication) {
    $response = nextcloud_api_request($nextcloud_url . "/ocs/v1.php/cloud/users/$username/groups", $nextcloud_autentication, "GET");

    if ($response['status'] === 'ok' && !empty($response['data']['groups']['element'])) { // Verifica 'groups' y 'element'
        // Manejar casos donde 'element' es un array o un string
        $groups = $response['data']['groups']['element'];
        $first_group = is_array($groups) ? $groups[0] : $groups;
        return $first_group;
    }
    return null; // No se encontró grupo o hubo un error
}

// Función para manejar las solicitudes a la API de Nextcloud
function nextcloud_api_request($url, $auth, $method = "GET", $data = null) {
    $args = [
        'method' => $method,
        'headers' => [
            'OCS-APIRequest: true',
            'Authorization: Basic ' . base64_encode($auth), // Autenticación Base64
        ],
        'sslverify' => true, // ¡MUY IMPORTANTE!
        'timeout' => 15,    // Timeout para evitar bloqueos
    ];

    if ($data !== null) {
        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = $data; // Datos para POST o PUT
        } else {
            $url = add_query_arg($data, $url); // Datos para GET (en la URL)
        }
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        error_log("Error en solicitud a Nextcloud: " . $response->get_error_message() . " URL: " . $url);
        return ['status' => 'error', 'message' => $response->get_error_message()]; // Devuelve mensaje de error
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($http_code != 200) {
        error_log("Error en solicitud a Nextcloud. Código HTTP: " . $http_code . " URL: " . $url . " Response: " . $body); // Log con más detalles
        return ['status' => 'error', 'http_code' => $http_code, 'message' => 'Error en la solicitud']; // Devuelve código y mensaje
    }

    $xml = simplexml_load_string($body);
    if ($xml === false) {
        error_log("Error al parsear XML de Nextcloud. Response: " . $body . " URL: " . $url);
        return ['status' => 'error', 'message' => 'Error parsing XML'];
    }

    // Mejor manejo de la respuesta XML.  Verifica si existen los elementos meta.
    $status = isset($xml->meta->status) ? (string)$xml->meta->status : 'unknown';
    $statuscode = isset($xml->meta->statuscode) ? (int)$xml->meta->statuscode : 0;
    $data = isset($xml->data) ? json_decode(json_encode($xml->data), true) : null; // Convertir data a array asociativo

    return ['status' => $status, 'statuscode' => $statuscode, 'data' => $data];
}

// Enviar correo de eliminación de cuenta
function send_user_deletion_email($user, $days_last_login) {
    $site_name = get_option('blogname');
    $email = $user->user_email;
    $user_display_name = $user->display_name;
    $subject = "Sua conta foi excluída";

    $message = "<h1>Cloud $site_name</h1>";
    $message .= "<p>Prezado(a) $user_display_name,</p>";
    $message .= "<p>Informamos que sua conta ficou inativa por $days_last_login dias e foi automaticamente excluída do nosso sistema. Para proteger sua privacidade, os dados e arquivos da sua conta Nextcloud também foram completamente excluídos.</p>";
    $message .= "<p>Atenciosamente,<br/>Equipe $site_name</p>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($email, $subject, $message, $headers);
}

//---------------------------------------------//

// Obtener el estatus de la membresía del usuario
function get_membership_status($user_id) {
    global $wpdb;
    $status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $wpdb->pmpro_memberships_users WHERE user_id = %d ORDER BY modified DESC LIMIT 1", $user_id ) );
    return $status;
}

// Deshabilitar usuarios con suscripciones gratuitas vencidas
function membership_post_expiry_email($user_id, $membership_id) {
    // Obtener el estatus de usuario en Nextcloud
    $user_data = get_userdata($user_id);
    $username = $user_data->user_login;
    $nextcloud_enabled = check_nextcloud_enabled($username);
    $site_url = get_option('siteurl');

    // Deshabilitar usuario en Nextcloud si está habilitado
    if ($nextcloud_enabled == 1) {
        // Añadir nuevo campo 'free_level_expired' para marcar al usuario
        update_user_meta($user_id, 'free_level_expired', true);

        // Leer credenciales de Nextcloud
        $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
        $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

        if (!$nextcloud_api_admin || !$nextcloud_api_pass) {
            error_log("Credenciales de Nextcloud no configuradas.");
            return; // o lanzar una excepción.
        }

        $nextcloud_auth = "$nextcloud_api_admin:$nextcloud_api_pass";
        $nextcloud_url = 'https://cloud.' . basename($site_url);
        $disable_url = $nextcloud_url . "/ocs/v1.php/cloud/users/$username/disable";
        $nextcloud_header = "OCS-APIRequest: true";

        $args = [
            'method' => 'PUT',
            'headers' => [
                'OCS-APIRequest: true',
                'Authorization: Basic ' . base64_encode($nextcloud_auth), // Autenticación Base64
            ],
            'sslverify' => true, // ¡MUY IMPORTANTE!
            'timeout' => 15,    // Timeout para evitar bloqueos
        ];

        $response = wp_remote_request($disable_url, $args);

        if (is_wp_error($response)) {
            error_log("Error al deshabilitar usuario en Nextcloud: " . $response->get_error_message() . " URL: " . $disable_url);
            return; // o manejar el error de otra forma
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response); // Obtener el cuerpo de la respuesta

        if ($http_code === 200) {
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                error_log("Error al parsear XML de Nextcloud. Response: " . $body . " URL: " . $disable_url);
                return; // o manejar el error de otra forma
            }

            $status_code = (int)$xml->meta->statuscode;

            if ($status_code === 100) {
                // Usuario deshabilitado correctamente en Nextcloud
                send_membership_expired_email($user_id);
            } else {
                // Hubo un problema al deshabilitar al usuario
                error_log("Error al deshabilitar al usuario en Nextcloud. Status code: $status_code. Response: " . $body . " URL: " . $disable_url); // Log con más detalles
            }
        } else {
            // Error en la solicitud cURL
            error_log("Error en la solicitud HTTP al deshabilitar usuario en Nextcloud. HTTP code: $http_code. Response: " . $body . " URL: " . $disable_url); // Log con más detalles
        }
    } else {
        // Si el usuario no estaba habilitado en Nextcloud, solo enviamos el correo de membresía expirada
        send_membership_expired_email($user_id);
    }
}
add_action('pmpro_membership_post_membership_expiry', 'membership_post_expiry_email');

// Enviar correo electrónico a usuarios con suscripciones por vencer
function send_membership_expiring_email( $user_id, $days_remaining ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        // Manejar el caso en que el usuario no existe.  Log o retornar un error.
        error_log("Usuario no encontrado: " . $user_id);
        return; // O throw new Exception("Usuario no encontrado");
    }

    $email = $user->user_email;
    $username = $user->user_login;
    $site_url = get_option('siteurl');
    $site_name = get_option('blogname');
    $nextcloud_url = 'https://cloud.' . basename($site_url);

    // Obtener credenciales de forma más segura
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if ( ! $nextcloud_api_admin || ! $nextcloud_api_pass ) {
        error_log("Credenciales de Nextcloud no configuradas.");
        return; // o lanzar una excepción.
    }

    $nextcloud_autentication = $nextcloud_api_admin . ':' . $nextcloud_api_pass;
    $nextcloud_header = "OCS-APIRequest: true";
    $to_admin = get_option('admin_email');

    if ( $days_remaining === 1 ) {
        $message_content = " expira amanhã";
        $subject = "Seu plano" . $message_content;

        $to_admin_subject = "Um plano" . $message_content;
        $to_admin_message = "O plano do " . $user->display_name . $message_content;
    } else {
        $subject = "Seu plano expira em " . $days_remaining . " dias";
        $message_content = " está prestes a expirar em " . $days_remaining . " dias";

        $to_admin_subject = "Um plano a expirar em " . $days_remaining . " dias";
        $to_admin_message = "O plano do " . $user->display_name . " está prestes a expirar em " . $days_remaining . " dias.";
    }

    $long_message = "Informamos que seu plano" . $message_content . ". Para renová-lo você pode fazê-lo a partir da sua ";
    $user_account_link = "<a href='" . esc_url($site_url . '/minha-conta') . "'>conta</a>"; // Escapar la URL
    $user_account_text = "conta no site " . $site_url . ".";

    $message = "<h1>Cloud " . esc_html($site_name) . "</h1>"; // Escapar el nombre del sitio
    $message .= "<p>Prezado(a) " . esc_html($user->display_name) . ",</p>"; // Escapar el nombre del usuario
    $message .= "<p>" . $long_message . $user_account_link . ".</p>";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    //enviar correos
    wp_mail( $email, $subject, $message, $headers );
    wp_mail($to_admin, $to_admin_subject, $to_admin_message, $headers);

    //enviar notificación -  Usar wp_remote_post para mayor seguridad
    $notification_url = $nextcloud_url . "/ocs/v2.php/apps/notifications/api/v2/admin_notifications/";

    $notification_data_user = array(
        'shortMessage' => $subject,
        'longMessage' => $long_message . $user_account_text,
    );

    $notification_data_admin = array(
        'shortMessage' => $to_admin_subject,
        'longMessage' => $to_admin_message,
    );

    $args = array(
        'headers' => array(
            'OCS-APIRequest' => 'true',
            'Authorization' => 'Basic ' . base64_encode( $nextcloud_autentication ),
        ),
        'body' => $notification_data_user,
    );

    $response_user = wp_remote_post( $notification_url . $username, $args );
    $response_admin = wp_remote_post( $notification_url . $nextcloud_api_admin, $args ); // Reutilizar $args

    // Manejar las respuestas de wp_remote_post (errores, códigos de estado, etc.)
    if ( is_wp_error( $response_user ) ) {
        error_log( "Error en notificación a usuario: " . $response_user->get_error_message() );
    } else {
        $response_code_user = wp_remote_retrieve_response_code( $response_user );
        if ( $response_code_user != 200 ) { // Ejemplo: Verificar código 200 (OK)
            error_log( "Error en notificación a usuario. Código de respuesta: " . $response_code_user . ".  Respuesta: " . wp_remote_retrieve_body( $response_user ) );
        }
    }

    if ( is_wp_error( $response_admin ) ) {
        error_log( "Error en notificación a admin: " . $response_admin->get_error_message() );
    } else {
        $response_code_admin = wp_remote_retrieve_response_code( $response_admin );
        if ( $response_code_admin != 200 ) { // Ejemplo: Verificar código 200 (OK)
            error_log( "Error en notificación a admin. Código de respuesta: " . $response_code_admin . ". Respuesta: " . wp_remote_retrieve_body( $response_admin ) );
        }
    }
}

//---------------------------------------------//

// Reajustar la próxima fecha de pago
function pmpro_next_payment_filter($timestamp, $user_id, $order)
{
    // Calculate the next payment date
    $new_timestamp = strtotime(ajustar_proxima_fecha_pago($user_id));
    return $new_timestamp;
}
add_filter('pmpro_next_payment', 'pmpro_next_payment_filter', 10, 3);

// Calcular la próxima fecha de pago
function ajustar_proxima_fecha_pago($user_id) {
    // Obtener la fecha actual y la fecha original de registro del usuario
    $dia_fecha_registro = date( 'd', pmpro_getMemberStartdate($user_id) );
    $fecha_actual = date('Y-m');

    // Fecha para calcular la próxima fecha de pago
    $fecha_registro = $fecha_actual . '-' . $dia_fecha_registro;

    // Solicitar los feriados en Brasil para el año actual
    $url_feriados_brasil = 'https://date.nager.at/api/v3/PublicHolidays/' . date('Y') . '/BR';
    $feriados_brasil_json = file_get_contents($url_feriados_brasil);
    $feriados_brasil_array = json_decode($feriados_brasil_json, true);

    // Calcular la fecha de pago deseada para el siguiente mes
    $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_registro . '+1 month'));

    // Si la fecha de pago cae en Sábado o Domingo, ajustarla al Lunes siguiente
    $dia_semana_pago_siguiente_mes = date('N', strtotime($fecha_pago_siguiente_mes));
    if ($dia_semana_pago_siguiente_mes == 6) { // Sábado
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+2 days'));
    } elseif ($dia_semana_pago_siguiente_mes == 7) { // Domingo
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+1 day'));
    }

    // Si la fecha de pago cae en un feriado en Brasil, ajustarla al siguiente día hábil
    while (in_array($fecha_pago_siguiente_mes, array_column($feriados_brasil_array, 'date'))) {
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+1 day'));
        $dia_semana_pago_siguiente_mes = date('N', strtotime($fecha_pago_siguiente_mes));
        if ($dia_semana_pago_siguiente_mes == 6) { // Sábado
            $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+2 days'));
        } elseif ($dia_semana_pago_siguiente_mes == 7) { // Domingo
            $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($fecha_pago_siguiente_mes . '+1 day'));
        }
    }

    // Verificar si la fecha de pago ajustada se desvía demasiado de la fecha original de registro y ajustarla si es necesario
    $dias_desviacion = abs(strtotime($fecha_registro) - strtotime($fecha_pago_siguiente_mes)) / (60 * 60 * 24);
    $max_dias_desviacion = 10; // Máxima cantidad de días permitida de desviación
    if ($dias_desviacion < $max_dias_desviacion) {
        $dias_ajuste = $dias_desviacion - $max_dias_desviacion;
        $ajuste = ($fecha_registro > $fecha_pago_siguiente_mes) ? '+ ' : '- ';
        $fecha_pago_siguiente_mes = date('Y-m-d', strtotime($ajuste . $dias_ajuste . ' days', strtotime($fecha_pago_siguiente_mes)));
    }

    $fecha_pago_siguiente_mes = date('Y-m-d H:i:s', strtotime($fecha_pago_siguiente_mes));

    // Retornar la fecha de pago deseada para el siguiente mes
    return $fecha_pago_siguiente_mes;
}

// Acción ejecutada al confirmarse el pago
function pago_confirmado( $user_id, $morder ) {
    // Obtener información del usuario
    $user = get_userdata( $user_id );
    $level = pmpro_getMembershipLevelForUser($user_id);

    // Get the User Plan level
    $plan_level = $level->id;
    $valid_levels_list = array(1, 2, 3, 4, 5, 7, 9);

    if (in_array($plan_level, $valid_levels_list)) {
        crear_usuario_nextcloud( $user_id, $morder );
    } else {
        plan_nextcloud_ti( $user_id, $morder );
    }
}
add_action( 'pmpro_after_checkout', 'pago_confirmado', 10, 2 );

// Acción ejecutada antes de confirmar el checkout
function guardar_afiliado_antes_checkout($user_id) {
    // Verificar si el usuario ya tiene un ID de afiliado en los metadatos
    $affiliate_id_meta = get_user_meta($user_id, 'affiliate_id', true);

    // Si el ID de afiliado no existe en los metadatos, buscar en la cookie
    if (empty($affiliate_id_meta) && isset($_COOKIE['wpam_id'])) {
        $affiliate_id = $_COOKIE['wpam_id'];

        // Guardar el ID de afiliado en los metadatos del usuario
        update_user_meta($user_id, 'affiliate_id', $affiliate_id);
    }
}
add_action('pmpro_before_commit_express_checkout', 'guardar_afiliado_antes_checkout', 10, 1);

// Responder a solicitud de plan Nextcloud TI
function plan_nextcloud_ti( $user_id, $morder ) {
    // Generar password para la nueva cuenta Nextcloud
    $password = wp_generate_password( 12, false );
    
    // Obtener información del usuario
    $user = get_userdata( $user_id );
    $email = $user->user_email;
    $username = $user->user_login;
    $displayname = $user->display_name;
    $level = $user->membership_level = pmpro_getMembershipLevelForUser($user_id);
    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('America/Boa_Vista'));
    $dt->setTimestamp($morder->timestamp);

    // Obtener la fecha del próximo pago
    $fecha_pedido = $dt->format('d/m/Y H:i:s');
    $fecha_pago_proximo_mes = ajustar_proxima_fecha_pago( $user_id );

    // Get the User Plan level
    $plan_level = $level->id;

    // mailto
    $brdrv_email = "cloud@" . basename( get_site_url() );
    $mailto = "mailto:" . $brdrv_email;

    // Título de email
    $subject = "Sua instância Nextcloud será criada";
    // Mensaje
    $message = "<h1>Cloud Brasdrive</h1>";
    $message .= "<p>Prezado(a) <b>" . $displayname . "</b> (" . $username . "),</p>";
    $message .= "<p>Parabéns! Seu pagamento foi confirmado e sua instância Nextcloud será criada em breve.</p>";
    $message .= "<p>Dados da sua conta admin do Nextcloud:<br/>";
    $message .= "Usuário: " . $username . "<br/>";
    $message .= "Senha: " . $password . "</p>";
    $message .= "<p>Seu plano: <b>" . $level->name . "</b></p>";
    $message .= "<p>Data do seu pedido: " . $fecha_pedido . "<br/>";

    $message .= "Valor " . $monthly_message . "do seu plano: <b>R$ " . number_format($morder->total, 2, ',', '.') . "</b><br/>";
    $message .= "Data do próximo pagamanto: " . $date_message . date('d/m/Y', strtotime($fecha_pago_proximo_mes)) . "</p>";
    $message .= "<p><b>Por segurança, recomendamos manter guardada a senha da instância Nextcloud em um local seguro e excluir esse e-mail.</b> Você também pode alterar sua senha nas Configurações pessoais de usuário da sua instância Nextcloud.</p>";
    $message .= "<p>Se você tiver alguma dúvida, entre em contato conosco no e-mail: <a href='" . $mailto . "'>" . $brdrv_email . "</a>.</p>";
    $message .= "Atenciosamente,<br/>Equipe Brasdrive";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    //enviar correo
    wp_mail( $email, $subject, $message, $headers );

    $to = get_option('admin_email');

    $admin_subject = "Nova instância Nextcloud TI";
    $admin_message = "<p>Uma nova instância Nextcloud TI foi contratada: <strong>" . $level->name . "</strong><br/>";
    $admin_message .= "Nome: " . $displayname . "<br/>";
    $admin_message .= "Usuário: " . $username . "<br/>";
    $admin_message .= "Senha: " . $password . "</p>";

    wp_mail($to, $admin_subject, $admin_message);
}

// Crear usuario en plan Nextcloud Solo
function crear_usuario_nextcloud( $user_id, $morder ) {
    // Verificar si el usuario fue creado previamente en Nextcloud
    $created_in_nextcloud = get_user_meta($user_id, 'created_in_nextcloud', true);

    // Obtener información del usuario
    $user = get_userdata( $user_id );
    $email = $user->user_email;
    $username = $user->user_login;
    $displayname = $user->display_name;
    $level = $user->membership_level = pmpro_getMembershipLevelForUser($user_id);
    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('America/Boa_Vista'));
    $dt->setTimestamp($morder->timestamp);

    // Obtener la fecha del próximo pago
    $fecha_pedido = $dt->format('d/m/Y H:i:s');
    $fecha_pago_proximo_mes = ajustar_proxima_fecha_pago( $user_id );

    // Get the User Plan level
    $plan_level = $level->id;

    // Variables quota, status, curl, shell
    $quota = explode(" ", $level->name);
    $user_group = strtolower( $quota[1] ) . $user_id;
    $total_quota = ($quota[2] >= 1000) ? $quota[2]/1000 : $quota[2];
    $measure_quota = ($quota[2] >= 1000) ? "TB" : "GB";
    $user_quota = $total_quota . $measure_quota;
    $user_status = '{"statusType": "invisible"}';
    $locale = 'pt_BR';
    $curl_command = $output_shell_exec = [];

    if ($plan_level !== 5) {
        $date_message = "Data do próximo pagamento: ";
        $monthly_message = "mensal ";
    } else {
        $date_message = "Avaliação gratuita até: ";
        $monthly_message = "";
    }

    // Leer credenciales del administrador
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if ( ! $nextcloud_api_admin || ! $nextcloud_api_pass ) {
        error_log("Credenciales de Nextcloud no configuradas.");
        return; // o lanzar una excepción.
    }

    $nextcloud_autentication = $nextcloud_api_admin . ':' . $nextcloud_api_pass;
    $nextcloud_header = "OCS-APIRequest: true";
    $notifications_link = "/ocs/v2.php/apps/notifications/api/v2/admin_notifications/";

    $notification_url = "https://cloud." . basename( get_site_url() ) . $notifications_link;

    // Si no se ha creado la cuenta en Nextcloud, proceder con su creación
    if (!$created_in_nextcloud) {
        // Generar password para la nueva cuenta Nextcloud
        $password = wp_generate_password( 12, false );
        $ncnewuser_autentication = $username . ":" . $password;

        $to_admin_subject = 'Nova conta criada';
        $to_admin_smessage = 'Foi criada a conta ' . $level->name . ' do ' . $username . '.';

        // Crear usuario en Nextcloud
        $curl_command[0] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'groupid=$user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups";
        $curl_command[1] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'userid=$username&password=$password&groups[]=$user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users?format=json";
        $curl_command[2] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=displayname&value=$displayname' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[3] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=email&value=$email' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[4] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=quota&value=$user_quota' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[5] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=profile_enabled&value=false' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[6] = "curl -H '" . $nextcloud_header .  "' -u " . $ncnewuser_autentication . " -X PUT -H 'Content-Type: application/json' --data-raw '" . $user_status . "' https://cloud.brasdrive.com.br/ocs/v2.php/apps/user_status/api/v1/user_status/status";
        $curl_command[7] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=locale&value=$locale' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[8] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST " . $notification_url . $nextcloud_api_admin . " -d shortMessage='$to_admin_subject' -d longMessage='$to_admin_smessage'";

        $count = count($curl_command);

        for ($i = 0; $i < $count; $i++) {
            $output_shell_exec[$i] = shell_exec($curl_command[$i]);
            sleep(1);
        }

        $new_account = true;

    } else {
        $short_message = 'Atualização do plano';
        $long_message = 'Seu plano foi atualizado para: ' . $level->name . '. Esperamos que você aproveite ao máximo.';
        $new_user_group = strtolower( $quota[1] ) . $user_id;
        $nextcloud_header_json = "Content-type: application/json";

        $response = shell_exec("curl -H '" . $nextcloud_header_json . "' -H '" . $nextcloud_header . "' -u " . $nextcloud_autentication . " -X GET https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username."/groups");

        $simplexml = simplexml_load_string($response);
        $json = json_encode($simplexml);
        $obj = json_decode($json);
        $old_user_group = $obj->data->groups->element;

        $curl_command[0] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X PUT -d 'key=quota&value=$user_quota' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username;
        $curl_command[1] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST " . $notification_url . $username." -d shortMessage='$short_message' -d longMessage='$long_message'";
        $curl_command[2] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'groupid=$new_user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups";
        $curl_command[3] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X POST -d 'groupid=$new_user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username."/groups";
        $curl_command[4] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X DELETE -d 'groupid=$old_user_group' https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/".$username."/groups";
        $curl_command[5] = "curl -H '" . $nextcloud_header .  "' -u " . $nextcloud_autentication . " -X DELETE https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups/".$old_user_group;

        $count = count($curl_command);

        for ($i = 0; $i < $count; $i++) {
            $output_shell_exec[$i] = shell_exec($curl_command[$i]);
        }

        $new_account = false;
    }

    // Verificar si hubo errores en las solicitudes de API
    $error_occurred = false;
    $error_message = '';

    $response = json_decode($output_shell_exec[1]);
    if ($response->ocs->meta->statuscode !== 100 && !$new_account) {
        // Registrar cualquier error que surja
        $error_occurred = true;
        $error_message = "Error en la solicitud de API: " . $response->ocs->meta->message . "\n";
    }

    if ($error_occurred) { // Si se produjo un error, enviar un correo electrónico al administrador

        $to = get_option('admin_email');

        if (!$created_in_nextcloud) {
            $subject = 'Error en la creación de usuario en Nextcloud';
            $message = 'Se ha producido un error al crear el usuario en Nextcloud. Los detalles del error son los siguientes:' . "\n\n" . $error_message;
        } else {
            $subject = 'Error al actualizar plan de usuario en Nextcloud';
            $message = 'Se ha producido un error al actualizar plan de usuario en Nextcloud. Los detalles del error son los siguientes:' . "\n\n" . $error_message;
        }

        wp_mail($to, $subject, $message);

    }

    if ($new_account) { // Agregar una clave meta al usuario y enviarle un e-mail con información de su nueva cuenta Nextcloud

        update_user_meta($user_id, 'created_in_nextcloud', true);

        // Cloud URL
        $cloud_url = 'https://cloud.' . basename( get_site_url() );
        $client_cloud_url = 'https://cloud.' . basename( get_site_url() ) . '/remote.php/dav/files/' . $username;

        // App links
        $google_play = "https://play.google.com/store/apps/details?id=com.nextcloud.client";
        $f_droid = "https://f-droid.org/pt_BR/packages/com.nextcloud.client/";
        $app_store = "https://itunes.apple.com/br/app/nextcloud/id1125420102?mt=8";

        // mailto
        $brdrv_email = "cloud@" . basename( get_site_url() );
        $mailto = "mailto:" . $brdrv_email;

        //Título de email
        $subject = "Sua conta Nextcloud foi criada";
        //mensaje
        $message = "<h1>Cloud Brasdrive</h1>";
        $message .= "<p>Prezado(a) <b>" . $displayname . "</b> (" . $username . "),</p>";
        $message .= "<p>Parabéns! Sua conta Nextcloud foi criada satisfatoriamente!</p>";
        $message .= "<p>Dados da sua conta:<br/>";
        $message .= "Usuário: " . $username . "<br/>";
        $message .= "Senha: " . $password . "</p>";
        $message .= "<p>Acesso à sua conta Nextcloud: <a href='" . $cloud_url . "'>" . $cloud_url . "</a></p>";
        $message .= "<p>Baixe o aplicativo <strong>Nextcloud Files</strong>:<br/>";
        $message .= "<a href='" . $google_play . "' >Google Play</a><br/>";
        $message .= "<a href='" . $f_droid . "' >F-Droid</a><br/>";
        $message .= "<a href='" . $app_store . "' >App Store</a><br/>";
        $message .= "Baixe o <strong>Nextcloud Desktop</strong> para Windows, macOS ou Linux: <a href='https://nextcloud.com/install'>Baixar</a><br/>";
        $message .= "Conecte o aplicativo utilizando o link <a href='" . $cloud_url . "'>" . $cloud_url . "</a>, com seu usuário e sua senha.</p>";
        $message .= "<p>Para conectar clientes WebDAV utilice: <a href='" . $client_cloud_url . "'>" . $client_cloud_url . "</a>, com seu usuário e sua senha.</p>";

        $message .= "<p>Seu plano: <b>" . $level->name . "</b></p>";
        $message .= "<p>Data do seu pedido: " . $fecha_pedido . "<br/>";
        $message .= "Valor " . $monthly_message . "do seu plano: <b>R$ " . number_format($morder->total, 2, ',', '.') . "</b><br/>";
        $message .= $date_message . date('d/m/Y', strtotime($fecha_pago_proximo_mes)) . "</p>";
        $message .= "<p><b>Por segurança, recomendamos manter guardada a senha do Nextcloud em um local seguro e excluir esse e-mail.</b> Você também pode alterar sua senha nas Configurações pessoais da sua conta Nextcloud.</p>";
        $message .= "<p>Se você tiver alguma dúvida, entre em contato conosco no e-mail: <a href='" . $mailto . "'>" . $brdrv_email . "</a>.</p>";
        $message .= "Atenciosamente,<br/>Equipe Brasdrive";

        $headers = array('Content-Type: text/html; charset=UTF-8');

        //enviar correo
        wp_mail( $email, $subject, $message, $headers );
    }
}

add_filter('pmpro_email_headers', function($headers, $email) {
    $headers .= "X-Brasdrive-Origin: paid-memberships-pro\r\n";
    return $headers;
}, 10, 2);


/*
// Crear usuario en plan Nextcloud Solo
function crear_usuario_nextcloud($user_id, $morder) {
    // Verificar si el usuario fue creado previamente en Nextcloud
    $created_in_nextcloud = get_user_meta($user_id, 'created_in_nextcloud', true);

    // Obtener información del usuario
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'Usuario no encontrado.');
    }

    $email = $user->user_email;
    $username = $user->user_login;
    $displayname = $user->display_name;
    $level = pmpro_getMembershipLevelForUser($user_id);

    if (!$level) {
        return new WP_Error('level_not_found', 'Nivel de membresía no encontrado.');
    }

    $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('America/Boa_Vista'));
    $dt->setTimestamp($morder->timestamp);

    // Obtener la fecha del próximo pago
    $fecha_pedido = $dt->format('d/m/Y H:i:s');
    $fecha_pago_proximo_mes = ajustar_proxima_fecha_pago($user_id);

    // Get the User Plan level
    $plan_level = $level->id;

    // Variables quota, status, curl, shell
    $quota = explode(" ", $level->name);
    $user_group = strtolower($quota[1]) . $user_id;
    $total_quota = ($quota[2] >= 1000) ? $quota[2] / 1000 : $quota[2];
    $measure_quota = ($quota[2] >= 1000) ? "TB" : "GB";
    $user_quota = $total_quota . $measure_quota;
    $user_status = '{"statusType": "invisible"}';
    $locale = 'pt_BR';

    $date_message = ($plan_level !== 5) ? "Data do próximo pagamento: " : "Avaliação gratuita até: ";
    $monthly_message = ($plan_level !== 5) ? "mensal " : "";


    // Obtener credenciales del administrador
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if (!$nextcloud_api_admin || !$nextcloud_api_pass) {
        error_log("Credenciales de Nextcloud no configuradas.");
        return new WP_Error('credentials_not_found', 'Credenciales de Nextcloud no encontradas.');
    }

    $nextcloud_autentication = $nextcloud_api_admin . ':' . $nextcloud_api_pass;
    $nextcloud_header = "OCS-APIRequest: true";
    $notifications_link = "/ocs/v2.php/apps/notifications/api/v2/admin_notifications/";
    $notification_url = "https://cloud." . basename(get_site_url()) . $notifications_link;

    // Si no se ha creado la cuenta en Nextcloud, proceder con su creación
    if (!$created_in_nextcloud) {
        // ... (código para crear usuario - sin cambios)
    } else { // Actualización de plan

        $short_message = 'Atualização do plano';
        $long_message = 'Seu plano foi atualizado para: ' . esc_html($level->name) . '. Esperamos que você aproveite ao máximo.';
        $new_user_group = strtolower($quota[1]) . $user_id;

        // Obtener el grupo anterior (usando wp_remote_get)
        $response = wp_remote_get('https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username . '/groups', [
            'headers' => ['OCS-APIRequest: true'],
            'auth' => $nextcloud_autentication,
        ]);

        if (is_wp_error($response)) {
            error_log("Error al obtener grupos de Nextcloud: " . $response->get_error_message() . " URL: " . 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username . '/groups');
            return $response;
        }

        $simplexml = simplexml_load_string(wp_remote_retrieve_body($response));
        $json = json_encode($simplexml);
        $obj = json_decode($json);
        $old_user_group = $obj->data->groups->element;

        $requests = [
            [
                'url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username,
                'data' => ['key' => 'quota', 'value' => $user_quota],
                'method' => 'PUT'
            ],
            [
                'url' => $notification_url . $username,
                'data' => ['shortMessage' => $short_message, 'longMessage' => $long_message],
                'method' => 'POST'
            ],
            [
                'url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups',
                'data' => ['groupid' => $new_user_group],
                'method' => 'POST'
            ],
            [
                'url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username . '/groups',
                'data' => ['groupid' => $new_user_group],
                'method' => 'POST'
            ],
            [
                'url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/users/' . $username . '/groups',
                'data' => ['groupid' => $old_user_group],
                'method' => 'DELETE'
            ],
            [
                'url' => 'https://cloud.brasdrive.com.br/ocs/v1.php/cloud/groups/' . $old_user_group,
                'method' => 'DELETE'
            ],
        ];

        $responses = [];
        foreach ($requests as $request) {
            $args = [
                'headers' => ['OCS-APIRequest: true'],
                'body' => $request['data'],
                'method' => $request['method'],
                'auth' => $nextcloud_autentication,
            ];

            $response = wp_remote_request($request['url'], $args);

            if (is_wp_error($response)) {
                error_log("Error en solicitud a Nextcloud: " . $response->get_error_message() . " URL: " . $request['url']);
                return $response;
            }

            $responses[] = $response;
        }

        $new_account = false;
    }

    // Verificar si hubo errores en las solicitudes de API
    $error_occurred = false;
    $error_message = '';

    foreach ($responses as $response) {
        $body = json_decode(wp_remote_retrieve_body($response));

        if (is_object($body) && isset($body->ocs->meta->statuscode) && $body->ocs->meta->statuscode !== 100) {
            $error_occurred = true;
            $error_message .= "Error en la solicitud de API: " . (isset($body->ocs->meta->message) ? $body->ocs->meta->message : 'Error desconocido') . "\n";
        }
    }

    if ($error_occurred) {

        $to = get_option('admin_email');

        if (!$created_in_nextcloud) {
            $subject = 'Error en la creación de usuario en Nextcloud';
            $message = 'Se ha producido un error al crear el usuario en Nextcloud. Los detalles del error son los siguientes:' . "\n\n" . $error_message;
        } else {
            $subject = 'Error al actualizar plan de usuario en Nextcloud';
            $message = 'Se ha producido un error al actualizar plan de usuario en Nextcloud. Los detalles del error son los siguientes:' . "\n\n" . $error_message;
        }

        wp_mail($to, $subject, $message);

        return new WP_Error('api_error', $error_message);
    }

    if ($new_account) {
        update_user_meta($user_id, 'created_in_nextcloud', true);

        // Cloud URL
        $cloud_url = 'https://cloud.' . basename( get_site_url() );
        $client_cloud_url = 'https://cloud.' . basename( get_site_url() ) . '/remote.php/dav/files/' . $username;

        // App links
        $google_play = "https://play.google.com/store/apps/details?id=com.nextcloud.client";
        $f_droid = "https://f-droid.org/pt_BR/packages/com.nextcloud.client/";
        $app_store = "https://itunes.apple.com/br/app/nextcloud/id1125420102?mt=8";

        // mailto
        $brdrv_email = "cloud@" . basename( get_site_url() );
        $mailto = "mailto:" . $brdrv_email;

        //Título de email
        $subject = "Sua conta Nextcloud foi criada";
        //mensaje
        $message = "<h1>Cloud Brasdrive</h1>";
        $message .= "<p>Prezado(a) <b>" . $displayname . "</b> (" . $username . "),</p>";
        $message .= "<p>Parabéns! Sua conta Nextcloud foi criada satisfatoriamente!</p>";
        $message .= "<p>Dados da sua conta:<br/>";
        $message .= "Usuário: " . $username . "<br/>";
        $message .= "Senha: " . $password . "</p>";
        $message .= "<p>Acesso à sua conta Nextcloud: <a href='" . $cloud_url . "'>" . $cloud_url . "</a></p>";
        $message .= "<p>Baixe o aplicativo <strong>Nextcloud Files</strong>:<br/>";
        $message .= "<a href='" . $google_play . "' >Google Play</a><br/>";
        $message .= "<a href='" . $f_droid . "' >F-Droid</a><br/>";
        $message .= "<a href='" . $app_store . "' >App Store</a><br/>";
        $message .= "Baixe o <strong>Nextcloud Desktop</strong> para Windows, macOS ou Linux: <a href='https://nextcloud.com/install'>Baixar</a><br/>";
        $message .= "Conecte o aplicativo utilizando o link <a href='" . $cloud_url . "'>" . $cloud_url . "</a>, com seu usuário e sua senha.</p>";
        $message .= "<p>Para conectar clientes WebDAV utilice: <a href='" . $client_cloud_url . "'>" . $client_cloud_url . "</a>, com seu usuário e sua senha.</p>";

        $message .= "<p>Seu plano: <b>" . $level->name . "</b></p>";
        $message .= "<p>Data do seu pedido: " . $fecha_pedido . "<br/>";
        $message .= "Valor " . $monthly_message . "do seu plano: <b>R$ " . number_format($morder->total, 2, ',', '.') . "</b><br/>";
        $message .= $date_message . date('d/m/Y', strtotime($fecha_pago_proximo_mes)) . "</p>";
        $message .= "<p><b>Por segurança, recomendamos manter guardada a senha do Nextcloud em um local seguro e excluir esse e-mail.</b> Você também pode alterar sua senha nas Configurações pessoais da sua conta Nextcloud.</p>";
        $message .= "<p>Se você tiver alguma dúvida, entre em contato conosco no e-mail: <a href='" . $mailto . "'>" . $brdrv_email . "</a>.</p>";
        $message .= "Atenciosamente,<br/>Equipe Brasdrive";

        $headers = array('Content-Type: text/html; charset=UTF-8');

        //enviar correo
        wp_mail( $email, $subject, $message, $headers );
    }

    return true;
}
*/

// Enviar email a usuarios que actualizaron sus planes de suscripción
function custom_email_on_subscription_change( $level_id, $user_id, $cancel_level ) {
    // Obtener información del usuario
    $user = get_userdata( $user_id );
    $email = $user->user_email;
    $level = pmpro_getMembershipLevelForUser($user_id);

    //get last order
    $morder = new MemberOrder();

    // mailto
    $brdrv_email = "cloud@" . basename( get_site_url() );
    $mailto = "mailto:" . $brdrv_email;

    //Título de email
    $subject = "Atualização do seu plano";
    //mensaje
    $message = "<h1>Cloud Brasdrive</h1>";
    $message .= "<p>Prezado(a) <b>" . $user->display_name . "</b>,</p>";
    $message .= "<p>Seu plano foi atualizado para: " . $level->name . "</p>";
    $message .= "<p>Data do seu pedido: " . $morder->timestamp . "<br/>";
    $message .= "Valor mensal do seu plano: <b>R$ " . number_format($morder->total, 2, ',', '.') . "</b><br/>";
    $message .= "Data do próximo pagamento: " . $user->membership_payment . "</p>";
    $message .= "</p>Esperamos que você aproveite ao máximo seu novo plano. Se tiver alguma dúvida, entre em contato conosco no e-mail: <a href='" . $mailto . "'>" . $brdrv_email . "</a>.</p>";
    $message .= "Atenciosamente,<br/>Equipe Brasdrive";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    //enviar correo
    wp_mail( $email, $subject, $message, $headers );
}
do_action( 'pmpro_after_change_membership_level', 'custom_email_on_subscription_change', 10, 2 );

/**
 * Obtiene y calcula el uso de almacenamiento de Nextcloud para el usuario actual
 * @return void Envía respuesta JSON con el uso de almacenamiento
 */
function get_nextcloud_storage_used() {
        check_ajax_referer('get_storage_used', '_ajax_nonce') || wp_die('Nonce inválido', 403);

    // Validar si el usuario está autenticado
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Usuario no autenticado'], 401);
        return;
    }

    // Configuración base
    $nextcloud_url = 'https://cloud.' . basename(get_site_url());
    $headers = [
        'Content-type: application/json',
        'OCS-APIRequest: true'
    ];

    // Obtener credenciales de forma segura
    $api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $api_pass = getenv('NEXTCLOUD_API_PASS');

    if (empty($api_admin) || empty($api_pass)) {
        error_log('Nextcloud: Credenciales no configuradas');
        wp_send_json_error(['message' => 'Error de configuración'], 500);
        return;
    }

    // Preparar autenticación
    $current_user = wp_get_current_user();
    $username = $current_user->ID !== 1 ? sanitize_user($current_user->user_login) : $api_admin;
    $auth = base64_encode("$api_admin:$api_pass");
    
    // Usar WP HTTP API en lugar de shell_exec por seguridad
    $response = wp_remote_get(
        "$nextcloud_url/ocs/v2.php/cloud/users/$username",
        [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
                'OCS-APIRequest' => 'true'
            ],
            'timeout' => 15,
            'sslverify' => true
        ]
    );

    // Verificar respuesta
    if (is_wp_error($response)) {
        error_log('Nextcloud API Error: ' . $response->get_error_message());
        wp_send_json_error(['message' => 'Error en la API'], 500);
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $xml = @simplexml_load_string($body);
    
    if ($xml === false) {
        error_log('Nextcloud: Error parseando XML response');
        wp_send_json_error(['message' => 'Error procesando datos'], 500);
        return;
    }

    // Procesar datos
    $data = json_decode(json_encode($xml));
    $bytes_total = (int) $data->data->quota->quota;
    $bytes_used = (int) $data->data->quota->used;

    // Calcular cuotas
    list($quota_total, $total_used, $quota_used, $unit) = calculate_storage_units($bytes_total, $bytes_used);
    $icon_color = determine_icon_color($bytes_total, $bytes_used);

    // Preparar respuesta
    $html = sprintf(
        '<span class="dashicons dashicons-cloud nuvem %s"></span> %.1f %s de %s%s',
        esc_attr($icon_color),
        $quota_used,
        esc_html($unit),
        esc_html($quota_total),
        esc_html($total_used)
    );

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_get_storage_used', 'get_nextcloud_storage_used');

/**
 * Calcula las unidades de almacenamiento y formatos
 * @param int $bytes_total Total de bytes disponibles
 * @param int $bytes_used Bytes usados
 * @return array [total, texto total, usado, unidad]
 */
function calculate_storage_units($bytes_total, $bytes_used) {
    if ($bytes_total === -3) {
        return ['ilimitado', '.', round($bytes_used / (1024 * 1024 * 1024), 1), 'GB'];
    }

    $quota_total = round($bytes_total / (1024 * 1024 * 1024), 1);
    $quota_used = round($bytes_used / (1024 * 1024 * 1024), 1);
    $unit = 'GB';

    if ($quota_used < 1) {
        $quota_used = round($bytes_used / (1024 * 1024), 1);
        $unit = 'MB';
    }

    return [$quota_total, ' GB usados', $quota_used, $unit];
}

/**
 * Determina el color del ícono según el uso
 * @param int $bytes_total Total de bytes disponibles
 * @param int $bytes_used Bytes usados
 * @return string Clase CSS del color
 */
function determine_icon_color($bytes_total, $bytes_used) {
    if ($bytes_total === -3) {
        return 'icon-color-green';
    }

    $percentage = ($bytes_used * 100) / $bytes_total;
    if ($percentage <= 65) {
        return 'icon-color-green';
    } elseif ($percentage < 85) {
        return 'icon-color-orange';
    }
    return 'icon-color-red';
}

/**
 * Agrega el script AJAX para mostrar el almacenamiento
 * @return void
 */
function get_nextcloud_storage_used_ajax() {
    if (!is_user_logged_in() || !is_page(9)) {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                type: 'POST',
                data: {
                    action: 'get_storage_used',
                    _ajax_nonce: '<?php echo wp_create_nonce('get_storage_used'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('.storage').html(response.data.html);
                    } else {
                        console.error('Error:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'get_nextcloud_storage_used_ajax');

// Acciones al inicio de sesión
add_action( 'wp_login', 'smartwp_capture_login_time', 10, 2 );
function smartwp_capture_login_time( $user_login, $user ) {
    $user_id = $user->ID;

    // Record user's last login to custom meta
    update_user_meta( $user_id, 'last_login', time() );

    // Verificar integridad de la cookie de afiliado
    if ($user_id !== 1) {
        $affiliate_id_meta = get_user_meta($user_id, 'affiliate_id', true);

        // Crear nuevo metadato si no existe
        if ( empty($affiliate_id_meta) ) {
            update_user_meta( $user_id, 'affiliate_id', 2 );
            $affiliate_id_meta = 2;
        }

        // Verificar si la cookie existe y es diferente del metadato
        if (isset($_COOKIE['wpam_id']) && $_COOKIE['wpam_id'] == $affiliate_id_meta) {
            // La cookie existe y es correcta, no hacer nada
        } else {
            // La cookie no existe o es incorrecta, establecerla con el valor del metadato
            setcookie('wpam_id', $affiliate_id_meta, time() + (86400 * 365 * 10), "/");
        }
    }
}

//Register new custom column with last login time
add_filter( 'manage_users_columns', 'smartwp_user_last_login_column' );
add_filter( 'manage_users_custom_column', 'smartwp_last_login_column', 10, 3 );
function smartwp_user_last_login_column( $columns ) {
    $columns['last_login'] = 'Último Login';
    return $columns;
}

function smartwp_last_login_column( $output, $column_id, $user_id ){
    if( $column_id == 'last_login' ) {
    $last_login = get_user_meta( $user_id, 'last_login', true );
    $date_format = 'M j, Y';
    $hover_date_format = 'F j, Y, g:i a';
    
        $output = $last_login ? '<div title="Last login: '.date( $hover_date_format, $last_login ).'">Há '.human_time_diff( $last_login ).'</div>' : 'Sem registro';
    }
  
    return $output;
}

//Allow the last login columns to be sortable
add_filter( 'manage_users_sortable_columns', 'smartwp_sortable_last_login_column' );
add_action( 'pre_get_users', 'smartwp_sort_last_login_column' );
function smartwp_sortable_last_login_column( $columns ) {
    return wp_parse_args( array(
        'last_login' => 'last_login'
    ), $columns );
}

function smartwp_sort_last_login_column( $query ) {
    if( !is_admin() ) {
        return $query;
    }
 
    $screen = get_current_screen();
 
    if( isset( $screen->base ) && $screen->base !== 'users' ) {
        return $query;
    }
 
    if( isset( $_GET[ 'orderby' ] ) && $_GET[ 'orderby' ] == 'last_login' ) {
        $query->query_vars['meta_key'] = 'last_login';
        $query->query_vars['orderby'] = 'meta_value';
    }
    return $query;
}

// Always hide renew links for the (free) level 5.
function pmpro_hide_renew_levels( $show, $level ){
  // Hide a level renew link
  $show_levels = array( 5 );

  if( in_array( $level->id, $show_levels ) ) {
    $show = false;
  }
  return $show;
}
add_filter( 'pmpro_is_level_expiring_soon', 'pmpro_hide_renew_levels', 10, 2 );

// Stop members from renewing their (free) membership level 5.
function stop_members_from_renewing( $okay ) {
    // If something else isn't okay, stop from running this code further.
    if ( ! $okay ) {
        return $okay;
    }

    // If the user doesn't have a membership level carry on with checkout.
    if ( ! pmpro_hasMembershipLevel() ) {
        return $okay;
    }

    // Check if the user's current membership level is the same for checking out.
    if ( pmpro_hasMembershipLevel( '5' ) && $_REQUEST['level'] == '5' ) {
        $okay = false;
        pmpro_setMessage( 'Não é possível extender o periodo de avalialção gratuita! Você deve selecionar um novo plano.', 'pmpro_error' );
    }
    return $okay;
} 
add_filter( 'pmpro_registration_checks', 'stop_members_from_renewing', 10, 1 );

// Prevent multiple clicks on the cancel membership button before it completes the process
function my_disable_cancel_btn_on_click() {
    global $pmpro_pages;

    if ( !is_page( $pmpro_pages['cancel'] ) ) {
        return;
    }
    ?>
    <script>
        jQuery(document).ready(function(){
            jQuery('#pmpro_cancel .pmpro_yeslink').on('click', function(){
                jQuery(this).attr('disabled', true);
            });
        });
    </script>
    <?php
}
add_action( 'wp_head', 'my_disable_cancel_btn_on_click' );

function change_link() { // 15/08/2024
    $user_id = wp_get_current_user()->ID;
    $membership_status = get_membership_status($user_id);
    $free_level_expired = get_user_meta($user_id, 'free_level_expired', true);

    if (is_user_logged_in() && $free_level_expired) {
?>
<script type="text/javascript">
    window.onload = function() {
        var buttons = document.querySelectorAll('.pricing-tables .button');
        buttons.forEach(function(button) {
            var parent = button.closest('.price');
            if (parent && parent.querySelector('.header').innerText.trim() === 'Nextcloud Teste 50') { // Ajusta según el ID del plan
                button.innerText = 'Expirado';
                button.classList.add('disabled');
                button.style.color = '#999';
                button.style.backgroundColor = '#bbb';
                button.href = 'javascript:void(0)';
            }
        });
    };
</script>
<?php
    }
}
add_action('wp_footer', 'change_link');

function user_activity_check($user_id) {
    // Obtener información del usuario
    $user = get_userdata( $user_id );
    $email = $user->user_email;
    $username = $user->user_login;
    $displayname = $user->display_name;

    $membership_status = get_membership_status($user_id);
    $level = $user->membership_level = pmpro_getMembershipLevelForUser($user_id);

    // Credenciales y links Nextcloud
    $nextcloud_url = 'https://cloud.' . basename( get_site_url() );
    $url = "$nextcloud_url/ocs/v2.php/cloud/users/$username";
    $nextcloud_header = array("Content-type: application/json", "OCS-APIRequest: true");
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN');
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if ( ! $nextcloud_api_admin || ! $nextcloud_api_pass ) {
        error_log("Credenciales de Nextcloud no configuradas.");
        return; // o lanzar una excepción.
    }

    $nextcloud_autentication = $nextcloud_api_admin . ':' . $nextcloud_api_pass;
    $to = get_option('admin_email');
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $subject = $message = "";

    // Obtener último acceso en Nextcloud
    $response = shell_exec('curl -H "' . $nextcloud_header[0] . '" -H "' . $nextcloud_header[1] .  '" -u ' . $nextcloud_autentication . ' -X GET ' . $url);

    $simplexml = simplexml_load_string($response);
    $json = json_encode($simplexml);
    $obj = json_decode($json);
    $obj_data = $obj->data->lastLogin / 1000;
    $last_login = date( 'Y-m-d', $obj_data );
    $current_date = date( 'Y-m-d', time() );
    $diff = date_diff( date_create( $current_date ), date_create( $last_login ) );

    // Obtener tiempo inactividad en Nextcloud
    $days_remaining = $diff->d;

    if ($membership_status == "active" || $membership_status == "changed") {
        if ($days_remaining == 7) {
            // Título de email
            $subject = "Aproveite ao máximo sua conta Nextcloud!";

            // Mensaje
            $message = "<h1>Cloud Brasdrive</h1>";
            $message .= "<p>Olá <strong>" . $displayname . "</strong>,</p>";
            $message .= "<p>Obrigado por adquirir o Plano <strong>" . $level->name . "</strong> da Brasdrive! Estamos animados em tê-lo conosco e queremos garantir que você esteja aproveitando ao máximo as incríveis funcionalidades que o Nextcloud tem a oferecer.</p>";
            $message .= "<p><strong>Você sabia que com o Nextcloud você pode sincronizar todos os seus arquivos, documentos, fotos e vídeos de maneira fácil e segura?</strong> Aqui estão algumas ideias sobre como você pode começar a organizar sua vida digital com mais eficiência:</p>";
            $message .= "<ol>";
            $message .= "<li><strong>Nextcloud Files para seu Smartphone:</strong><br/>Instale o aplicativo Nextcloud Files em seu dispositivo Android ou iOS e comece a enviar suas fotos e documentos diretamente do seu celular. É perfeito para garantir que seus arquivos estejam sempre seguros e acessíveis!</li>";
            $message .= "<li><strong>Nextcloud Desktop para seu PC:</strong><br/>Sincronize facilmente todos os seus arquivos entre seu computador e seu espaço na nuvem. Trabalhe em seus projetos de qualquer lugar sem se preocupar em perder informações importantes devido a uma falha do dispositivo.</li>";
            $message .= "<li><strong>Acesso de qualquer navegador:</strong><br/>Simplesmente faça login em sua conta Nextcloud de qualquer navegador para acessar seus arquivos quando precisar, sem a necessidade de instalações adicionais.</li>";
            $message .= "</ol>";
            $message .= "<p>O Nextcloud não oferece apenas um espaço para guardar seus dados, mas também fornece as ferramentas para manter sua vida organizada e segura. Seja para uso pessoal, acadêmico ou profissional, o Nextcloud se adapta às suas necessidades.</p>";
            $message .= "<p>Se você ainda não começou a explorar todas essas possibilidades, <strong>convidamos você a fazê-lo hoje mesmo!</strong> Se tiver alguma dúvida ou precisar de ajuda para começar, não hesite em responder a este e-mail. Estamos aqui para ajudá-lo a aproveitar ao máximo sua conta.</p>";
            $message .= "<p>Cordialmente,<br/>Equipe Brasdrive</p>";
        } elseif ($days_remaining == 15) {
            // Título de email
            $subject = "Descubra tudo o que você pode fazer no Nextcloud";

            // Mensaje
            $message = "<h1>Cloud Brasdrive</h1>";
            $message .= "<p>Olá <strong>" . $displayname . "</strong>,</p>";
            $message .= "<p>Comece a explorar as possibilidades da sua conta Nextcloud! Queremos garantir que você está aproveitando ao máximo sua nova conta. Com o Nextcloud, você pode sincronizar e acessar seus arquivos, fotos e documentos de forma segura, de qualquer lugar e dispositivo.</p>";
            $message .= "<p><strong>Comece assim:</p>";
            $message .= "<ul>";
            $message .= "<li><strong>No seu smartphone:</strong> Baixe o aplicativo Nextcloud Files para enviar e acessar seus arquivos em movimento.</li>";
            $message .= "<li><strong>No seu PC:</strong> Instale o Nextcloud Desktop para manter todos os seus arquivos automaticamente sincronizados.</li>";
            $message .= "<li><strong>Do seu navegador:</strong> Acesse seus arquivos a qualquer momento, entrando no Nextcloud pelo seu navegador favorito.</li>";
            $message .= "</ul>";
            $message .= "<p>Precisa de ajuda ou tem perguntas? Estamos aqui para ajudar. Não hesite em responder a este e-mail.</p>";
            $message .= "<p>Cordialmente,<br/>Equipe Brasdrive</p>";
        }

        // Enviar correo
        if ( !empty($subject) && !empty($message) ) {
            wp_mail( $email, $subject, $message, $headers );
        }
    }
}

function get_next_payment($user_id) {
    $order = new MemberOrder();
    $order->getLastMemberOrder( $user_id );
    print_r($order);
}

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
                            <li class="grey"><a href="' . ($user_has_level ? '#' : esc_url(pmpro_url("checkout", "?level=" . $level->id))) . '" class="' . esc_attr($button_class) . '">' . esc_html($button_label) . '</a></li>
                          </ul>
                        </div>';
        }
    }

    $output .= '</div>';
    return $output;
}

add_shortcode('pricing_tables', 'generate_pricing_tables');

// Número de días desde el último inicio de sesión de un usuario
function get_last_login_in_days($user_id) {
    // Obtener el valor del último inicio de sesión almacenado en el meta-dato
    $last_login = get_user_meta($user_id, 'last_login', true);
    
    // Si no hay un valor de last_login, devolver 0
    if (empty($last_login)) {
        return 0;
    }

    // Convertir la fecha actual y la de last_login a timestamps
    $current_time = current_time('timestamp');
    $difference_in_seconds = $current_time - $last_login;

    // Calcular la diferencia en días
    $difference_in_days = floor($difference_in_seconds / DAY_IN_SECONDS);

    return $difference_in_days;
}

/*function ajustar_proximo_pago() {
    // Define el ID del usuario específico y el ID de su nivel de membresía
    $user_id_especifico = 96; // Cambia 123 por el ID del usuario
    $membership_id_especifico = 2; // Cambia 1 por el ID de la membresía del usuario

    // Verifica si es el usuario y membresía específicos
    if ($user_id == $user_id_especifico && $membership_id == $membership_id_especifico) {
        // Calcula la fecha de pago en dos meses desde la fecha actual
        $next_payment_timestamp = strtotime("+1 hour", time());
    }

    return $next_payment_timestamp;
}
add_filter('pmpro_next_payment_date', 'ajustar_proximo_pago', 10, 3);*/

/*function check_unpaid_manual_memberships_cli() {
    global $wpdb;

    // Nombre de la tabla de órdenes de PMPro
    $orders_table = $wpdb->prefix . 'pmpro_membership_orders';

    // Consulta para obtener usuarios con métodos de pago 'check' o 'paypalexpress'
    $query = "
        SELECT u.ID, u.user_login, u.display_name, u.user_email, o.gateway AS payment_method
        FROM {$wpdb->users} u
        INNER JOIN {$orders_table} o ON u.ID = o.user_id
        WHERE o.gateway IN ('check', 'paypalexpress') AND o.status = 'success'
        GROUP BY u.ID
    ";

    // Depuración: Imprimir la consulta SQL
    echo "Consulta SQL:\n";
    echo $query . "\n\n";

    // Ejecutar la consulta
    $users = $wpdb->get_results($query);

    if (empty($users)) {
        echo "No se encontraron usuarios con métodos de pago 'check' o 'paypalexpress'.\n";
        return;
    }

    $current_date = new DateTime('now', new DateTimeZone('America/Boa_Vista'));

    // Credenciales Nextcloud (¡DEBEN estar almacenadas de forma segura!)
    $nextcloud_api_admin = getenv('NEXTCLOUD_API_ADMIN'); // Usar variables de entorno
    $nextcloud_api_pass = getenv('NEXTCLOUD_API_PASS');

    if (!$nextcloud_api_admin || !$nextcloud_api_pass) {
        error_log("Credenciales de Nextcloud no configuradas.");
        echo "Error: Credenciales de Nextcloud no configuradas.\n"; // Mensaje para CLI
        return;
    }

    $nextcloud_authentication = base64_encode("$nextcloud_api_admin:$nextcloud_api_pass");
    $site_url = get_option('siteurl');
    $nextcloud_url = 'https://cloud.' . basename($site_url);

    echo "Usuarios con pagos atrasados:\n"; // Encabezado para la lista

    foreach ($users as $user) {
        $user_id = $user->ID;
        $membership_status = get_membership_status($user_id);
        $membership_level = pmpro_getMembershipLevelForUser($user_id);

        // Depuración: Mostrar información del usuario
        echo "Usuario: $user->user_login\n";
        echo "Membresía activa: " . ($membership_status == "active" ? "Sí" : "No") . "\n";
        echo "Nivel de membresía: " . $membership_level->id . "\n";

        if ($user_id == 1) { // Excluir usuario con ID 1 (si es necesario)
            echo "Usuario excluido (ID 1).\n";
            continue;
        }

        if ($membership_status == "active" && $membership_level->id != 5) {
            // Obtener la próxima fecha de pago usando pmpro_next_payment_date()
            $next_payment_date = pmpro_next_payment($user_id);

            if (!empty($next_payment_date)) {
                try {
                    // Formatear la fecha
                    $next_payment_date = new DateTime($next_payment_date, new DateTimeZone('America/Boa_Vista'));
                    echo "Fecha de pago: " . $next_payment_date->format('Y-m-d H:i:s') . "\n";
                } catch (Exception $e) {
                    error_log("Error al crear objeto DateTime: " . $e->getMessage());
                    continue;
                }

                if ($next_payment_date < $current_date) {
                    $username = $user->user_login;
                    $user_email = $user->user_email; // Obtener email
                    $user_name = $user->display_name; // Obtener nombre

                    $nextcloud_enabled = check_nextcloud_enabled($username);

                    if ($nextcloud_enabled == 1) {
                        echo "- " . $user_name . " (" . $user_email . ") - " . $username . "\n"; // Mostrar info del usuario
                    } else {
                        echo "Usuario $username no está habilitado en Nextcloud.\n";
                    }
                } else {
                    echo "Usuario $username tiene fecha de pago válida.\n";
                }
            } else {
                echo "Usuario $username no tiene fecha de pago configurada.\n";
            }
        } else {
            echo "Usuario $user_id no tiene membresía activa o es nivel 5.\n----------------------\n";
        }
    }
}*/

/**
 * Show the Stripe Checkout Session ID when editing an order.
 *
 * title: Show Stripe Checkout Session ID
 * layout: snippet
 * collection: payment-gateways, stripe
 * category: libraries
 *
 * You can add this recipe to your site by creating a custom plugin
 * or using the Code Snippets plugin available for free in the WordPress repository.
 * Read this companion article for step-by-step directions on either method.
 * https://www.paidmembershipspro.com/create-a-plugin-for-pmpro-customizations/
 */

/**
 * If there is a Stripe Checkout session ID for an order, show it when editing the order.
 *
 * @param MemberOrder $order The order object being edited.
 */
function pmpro_after_order_settings_stripe_checkout_session_id( $order ) {
        $stripe_checkout_session_id = get_pmpro_membership_order_meta( $order->id, 'stripe_checkout_session_id', true );
        if ( empty( $stripe_checkout_session_id ) ) {
                return;
        }

        ?>
        <tr class="pmpro_checkout_session_id">
                <th scope="row" valign="top">
                        <label for="stripe_checkout_session_id"><?php esc_html_e( 'Stripe Checkout Session ID', 'paid-memberships-pro' ); ?></label>
                </th>
                <td>
                        <input type="text" id="stripe_checkout_session_id" name="stripe_checkout_session_id" value="<?php echo esc_attr( $stripe_checkout_session_id ); ?>" size="75"  readonly />
                </td>
        </tr>
        <?php
}
add_action( 'pmpro_after_order_settings', 'pmpro_after_order_settings_stripe_checkout_session_id' );
