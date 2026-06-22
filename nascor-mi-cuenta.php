<?php
/**
 * Plugin Name: Nascor Mi Cuenta Personalizado
 * Description: Panel de Mi Cuenta unificado, con navegación AJAX. Funciona CON y SIN WooCommerce.
 * Version:     3.0.0
 * Author:      nascor.ar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Nascor_Mi_Cuenta_Plugin' ) ) {

    class Nascor_Mi_Cuenta_Plugin {

        // Variable para saber si WC está activo
        private $is_wc_active = false;

        public function __construct() {
            // Comprobar si WooCommerce existe
            $this->is_wc_active = class_exists( 'WooCommerce' );

            // Registrar Shortcode Principal
            add_shortcode( 'nascor_mi_cuenta', [ $this, 'render_shortcode' ] );

            // Panel de Administrador
            add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
            add_action( 'admin_init', [ $this, 'register_settings' ] );

            // Hooks específicos si WooCommerce está activo
            if ( $this->is_wc_active ) {
                add_action( 'woocommerce_account_orders_endpoint', [ $this, 'custom_orders_endpoint' ], 5 );
                add_action( 'woocommerce_account_downloads_endpoint', [ $this, 'custom_downloads_endpoint' ], 5 );
                remove_action( 'woocommerce_account_orders_endpoint', 'woocommerce_account_orders' );
                remove_action( 'woocommerce_account_downloads_endpoint', 'woocommerce_account_downloads' );
            } else {
                // Endpoints AJAX para el formulario nativo (Sin WC)
                add_action( 'wp_ajax_nascor_native_save_profile', [ $this, 'ajax_native_save_profile' ] );
            }
        }

        /**
         * ==========================================
         * 1. PANEL DE ADMINISTRADOR Y CONFIGURACIÓN
         * ==========================================
         */
        public function add_admin_menu() {
            add_menu_page( 'Ajustes Nascor Mi Cuenta', 'Mi Cuenta (Nascor)', 'manage_options', 'nascor-mi-cuenta', [ $this, 'admin_page_html' ], 'dashicons-admin-users', 21 );
        }

        public function register_settings() {
            register_setting( 'nascor_mc_settings', 'nmc_primary_color' );
            register_setting( 'nascor_mc_settings', 'nmc_bg_color' );
            register_setting( 'nascor_mc_settings', 'nmc_sidebar_bg' );
            register_setting( 'nascor_mc_settings', 'nmc_text_color' );
            register_setting( 'nascor_mc_settings', 'nmc_border_radius' );
        }

        public function admin_page_html() {
            if ( ! current_user_can( 'manage_options' ) ) return;
            
            $primary_color = get_option( 'nmc_primary_color', '#1b3f82' );
            $bg_color      = get_option( 'nmc_bg_color', '#f9f9f9' );
            $sidebar_bg    = get_option( 'nmc_sidebar_bg', '#ffffff' );
            $text_color    = get_option( 'nmc_text_color', '#333333' );
            $border_radius = get_option( 'nmc_border_radius', '8' );

            $status_msg = $this->is_wc_active 
                ? '<span style="color: green; font-weight: bold;">Activo (Integrado)</span>' 
                : '<span style="color: #d63638; font-weight: bold;">Inactivo (Usando modo Stand-alone de WordPress)</span>';
            ?>
            <div class="wrap">
                <h1>Configuración de Nascor Mi Cuenta</h1>
                
                <div style="background: #fff; padding: 15px 20px; border-left: 4px solid #1b3f82; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3>📌 Instrucciones y Estado</h3>
                    <p><strong>Estado de WooCommerce:</strong> <?php echo $status_msg; ?></p>
                    <p>Usa el siguiente shortcode en la página que uses como "Mi Cuenta":</p>
                    <p><code style="font-size: 16px; padding: 5px 10px; background: #f0f0f1;">[nascor_mi_cuenta]</code></p>
                </div>

                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px; background: #fff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                        <form method="post" action="options.php">
                            <?php settings_fields( 'nascor_mc_settings' ); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Color Principal (Botones)</th>
                                    <td><input type="color" name="nmc_primary_color" value="<?php echo esc_attr( $primary_color ); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Color Fondo General</th>
                                    <td><input type="color" name="nmc_bg_color" value="<?php echo esc_attr( $bg_color ); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Color Fondo Sidebar</th>
                                    <td><input type="color" name="nmc_sidebar_bg" value="<?php echo esc_attr( $sidebar_bg ); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Color de Texto</th>
                                    <td><input type="color" name="nmc_text_color" value="<?php echo esc_attr( $text_color ); ?>" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Bordes Redondeados (px)</th>
                                    <td><input type="number" name="nmc_border_radius" value="<?php echo esc_attr( $border_radius ); ?>" /> px</td>
                                </tr>
                            </table>
                            <?php submit_button('Guardar Estilos'); ?>
                        </form>
                    </div>

                    <div style="flex: 2; min-width: 500px;">
                        <h3>👁️ Vista Previa en Vivo</h3>
                        <div style="padding: 20px; background: #f0f0f1; border-radius: 8px; pointer-events: none; opacity: 0.9;">
                            <?php echo do_shortcode('[nascor_mi_cuenta]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * ==========================================
         * 2. RENDERIZADO DEL SHORTCODE FRONTEND
         * ==========================================
         */
        public function render_shortcode() {
            ob_start();
            $this->print_css();

            // Si no está logueado
            if ( ! is_user_logged_in() ) {
                echo '<div class="nascor-mc-container" style="justify-content: center;">';
                echo '<div class="nascor-mc-sidebar" style="width: 100%; max-width: 400px; padding: 40px;">';
                echo '<h3 style="text-align:center;">Iniciar Sesión</h3>';
                if ( $this->is_wc_active ) {
                    echo do_shortcode('[woocommerce_my_account]');
                } else {
                    wp_login_form( ['label_username' => 'Usuario o Correo', 'label_password' => 'Contraseña'] );
                }
                echo '</div></div>';
                return ob_get_clean();
            }

            // OBTENER MENÚS DINÁMICOS
            if ( $this->is_wc_active ) {
                $current_endpoint = WC()->query->get_current_endpoint();
                if ( empty( $current_endpoint ) ) $current_endpoint = 'dashboard';
                $menu_items = wc_get_account_menu_items();
            } else {
                $current_endpoint = isset( $_GET['n-tab'] ) ? sanitize_text_field( $_GET['n-tab'] ) : 'dashboard';
                $menu_items = [
                    'dashboard'       => 'Inicio',
                    'edit-account'    => 'Detalles de la cuenta',
                    'customer-logout' => 'Cerrar sesión'
                ];
            }
            ?>

            <div class="nascor-mc-container" id="nascor-mc-app">
                <div class="nascor-mc-sidebar woo-sidebar">
                    <h3>Mi Cuenta</h3>
                    <ul>
                        <?php foreach ( $menu_items as $endpoint => $label ) : 
                            $active = ( $current_endpoint === $endpoint ) ? 'active' : '';
                            
                            // Determinar URL
                            if ( $this->is_wc_active ) {
                                $url = wc_get_account_endpoint_url( $endpoint );
                            } else {
                                if ( $endpoint === 'customer-logout' ) {
                                    $url = wp_logout_url( get_permalink() );
                                } else {
                                    $url = add_query_arg( 'n-tab', $endpoint, get_permalink() );
                                }
                            }

                            // Asignación de iconos
                            $icon = 'fas fa-chevron-right';
                            if(strpos($endpoint, 'orders') !== false) $icon = 'fas fa-receipt';
                            if(strpos($endpoint, 'downloads') !== false) $icon = 'fas fa-cloud-download-alt';
                            if(strpos($endpoint, 'address') !== false) $icon = 'fas fa-map-marker-alt';
                            if(strpos($endpoint, 'edit-account') !== false) $icon = 'fas fa-user';
                            if(strpos($endpoint, 'logout') !== false) $icon = 'fas fa-sign-out-alt';
                            if(strpos($endpoint, 'dashboard') !== false) $icon = 'fas fa-home';
                        ?>
                            <li>
                                <a href="<?php echo esc_url( $url ); ?>" class="nascor-mc-nav-link <?php echo $active; ?>" data-endpoint="<?php echo esc_attr($endpoint); ?>">
                                    <i class="<?php echo $icon; ?>"></i> <?php echo esc_html( $label ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="nascor-mc-content wc-mcs-contenido-wrapper" id="nascor-mc-content-area">
                    <div class="nascor-mc-loader" style="display:none;"><i class="fas fa-spinner fa-spin"></i>&nbsp; Cargando...</div>
                    <div class="nascor-mc-inner">
                        <?php 
                        if ( $this->is_wc_active ) {
                            // Modo WooCommerce
                            echo '<div class="nascor-mc-dynamic-content">';
                            echo do_shortcode('[woocommerce_my_account]'); 
                            echo '</div>';
                        } else {
                            // Modo Nativo WordPress
                            $this->render_native_content( $current_endpoint );
                        }
                        ?>
                    </div>
                </div>
            </div>

            <?php
            $this->print_js();
            return ob_get_clean();
        }

        /**
         * ==========================================
         * 3. MODO NATIVO: RENDERIZADO Y AJAX
         * ==========================================
         */
        private function render_native_content( $tab ) {
            $user = wp_get_current_user();
            echo '<div class="nascor-mc-dynamic-content">';
            
            if ( $tab === 'edit-account' ) {
                ?>
                <h3>Detalles de la cuenta</h3>
                <div id="nascor-native-msg" style="display:none; padding:15px; margin-bottom:20px; border-radius:5px;"></div>
                <form class="nascor-native-form">
                    <?php wp_nonce_field( 'nascor_native_profile', 'security' ); ?>
                    
                    <p class="form-row form-row-first">
                        <label>Nombre *</label>
                        <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required>
                    </p>
                    <p class="form-row form-row-last">
                        <label>Apellidos *</label>
                        <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required>
                    </p>
                    <div class="clear"></div>

                    <p class="form-row form-row-wide">
                        <label>Nombre visible *</label>
                        <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required>
                        <small><em>Así será como se mostrará tu nombre en la web.</em></small>
                    </p>
                    <p class="form-row form-row-wide">
                        <label>Correo electrónico *</label>
                        <input type="email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>" required>
                    </p>

                    <h3>Cambio de contraseña</h3>
                    <p class="form-row form-row-wide">
                        <label>Nueva contraseña (déjalo en blanco para no cambiarla)</label>
                        <div style="position:relative;">
                            <input type="password" name="pass1" class="n-pass-input">
                            <i class="fas fa-eye wc-mcs-toggle-password"></i>
                        </div>
                    </p>
                    <p class="form-row form-row-wide">
                        <label>Confirmar nueva contraseña</label>
                        <div style="position:relative;">
                            <input type="password" name="pass2" class="n-pass-input">
                            <i class="fas fa-eye wc-mcs-toggle-password"></i>
                        </div>
                    </p>
                    <div class="clear"></div>

                    <p>
                        <button type="submit" class="button">Guardar cambios</button>
                    </p>
                </form>
                <?php
            } else {
                echo '<p>Hola <strong>' . esc_html( $user->display_name ) . '</strong> (¿no eres tú? <a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '">Cerrar sesión</a>)</p>';
                echo '<p>Desde el escritorio de tu cuenta puedes gestionar los detalles de tu perfil y cambiar tu contraseña.</p>';
            }
            
            echo '</div>';
        }

        public function ajax_native_save_profile() {
            check_ajax_referer( 'nascor_native_profile', 'security' );
            $user_id = get_current_user_id();
            
            if ( ! $user_id ) wp_send_json_error( 'Debes iniciar sesión.' );

            $errors = [];
            $data = [
                'ID'           => $user_id,
                'first_name'   => sanitize_text_field( $_POST['first_name'] ?? '' ),
                'last_name'    => sanitize_text_field( $_POST['last_name'] ?? '' ),
                'display_name' => sanitize_text_field( $_POST['display_name'] ?? '' ),
                'user_email'   => sanitize_email( $_POST['user_email'] ?? '' ),
            ];

            if ( ! empty( $_POST['pass1'] ) ) {
                if ( $_POST['pass1'] !== $_POST['pass2'] ) {
                    $errors[] = 'Las contraseñas no coinciden.';
                } else {
                    $data['user_pass'] = $_POST['pass1'];
                }
            }

            if ( ! empty( $errors ) ) wp_send_json_error( implode( '<br>', $errors ) );

            $result = wp_update_user( $data );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( $result->get_error_message() );
            }

            wp_send_json_success( 'Tus datos se han guardado correctamente.' );
        }

        /**
         * ==========================================
         * 4. MODO WOOCOMMERCE: VISTAS PERSONALIZADAS
         * ==========================================
         */
        public function custom_orders_endpoint() {
            $user_id = get_current_user_id();
            $orders = wc_get_orders( [ 'customer_id' => $user_id, 'orderby' => 'date', 'order' => 'DESC', 'limit' => -1 ] );
            
            echo '<div class="wc-mcs-filter">';
            echo '<select id="wc-mcs-status-filter"><option value="">Todos los estados</option>';
            foreach ( wc_get_order_statuses() as $key => $label ) {
                echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
            }
            echo '</select></div>';
            
            echo '<table class="wc-mcs-table"><thead><tr>';
            echo '<th>Imagen</th><th>Producto</th><th>Estado</th><th>Precio</th><th>Fecha</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $orders as $order ) {
                foreach ( $order->get_items() as $item ) {
                    $product = $item->get_product();
                    if ( ! $product ) continue;
                    $status = $order->get_status();
                    $date   = $order->get_date_created()->date_i18n( get_option( 'date_format' ) );
                    
                    echo '<tr data-status="wc-' . esc_attr( $status ) . '">';
                    echo '<td data-label="Imagen"><img src="' . esc_url( wp_get_attachment_url( $product->get_image_id() ) ) . '" width="50" style="border-radius:4px;"/></td>';
                    echo '<td data-label="Producto">' . esc_html( $product->get_name() ) . '</td>';
                    echo '<td data-label="Estado">' . esc_html( wc_get_order_status_name( $status ) ) . '</td>';
                    echo '<td data-label="Precio">' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td>';
                    echo '<td data-label="Fecha">' . esc_html( $date ) . '</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';
        }

        public function custom_downloads_endpoint() {
            $user_id = get_current_user_id();
            $orders = wc_get_orders( [ 'customer_id' => $user_id, 'orderby' => 'date', 'order' => 'DESC', 'limit' => -1 ] );
            
            echo '<table class="wc-mcs-table"><thead><tr>';
            echo '<th>Imagen</th><th>Producto</th><th>Fecha</th><th>Descarga</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $orders as $order ) {
                $downloads = $order->get_downloadable_items();
                if ( empty( $downloads ) ) continue;
                $date = $order->get_date_created()->date_i18n( get_option( 'date_format' ) );
                
                foreach ( $downloads as $dl ) {
                    $pid  = is_array( $dl ) ? $dl['product_id'] : $dl->product_id;
                    $url  = is_array( $dl ) ? $dl['download_url'] : $dl->download_url;
                    $name = is_array( $dl ) ? $dl['product_name'] : $dl->product_name;
                    $product    = wc_get_product( $pid );
                    $image_url = $product && $product->get_image_id() ? wp_get_attachment_url( $product->get_image_id() ) : '';
                    
                    echo '<tr>';
                    echo '<td data-label="Imagen">';
                    if ( $image_url ) echo '<img src="' . esc_url( $image_url ) . '" width="50" style="border-radius:4px;">';
                    echo '</td>';
                    echo '<td data-label="Producto">' . esc_html( $name ) . '</td>';
                    echo '<td data-label="Fecha">' . esc_html( $date ) . '</td>';
                    echo '<td data-label="Descarga"><a href="' . esc_url( $url ) . '" class="wc-mcs-download-btn button" target="_blank">Descargar</a></td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';
        }

        /**
         * ==========================================
         * 5. ESTILOS (CSS)
         * ==========================================
         */
        private function print_css() {
            $primary_color = get_option( 'nmc_primary_color', '#1b3f82' );
            $bg_color      = get_option( 'nmc_bg_color', '#f9f9f9' );
            $sidebar_bg    = get_option( 'nmc_sidebar_bg', '#ffffff' );
            $text_color    = get_option( 'nmc_text_color', '#333333' );
            $border_radius = get_option( 'nmc_border_radius', '8' );
            ?>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
            <style>
                .nascor-mc-inner .woocommerce-MyAccount-navigation { display: none !important; }
                .nascor-mc-inner .woocommerce-MyAccount-content { width: 100% !important; float: none !important; }

                .nascor-mc-container {
                    display: flex; flex-wrap: wrap; gap: 30px; background-color: <?php echo $bg_color; ?>;
                    padding: 20px; border-radius: <?php echo $border_radius; ?>px; color: <?php echo $text_color; ?>;
                    font-family: 'Open Sans', sans-serif;
                }

                .nascor-mc-sidebar {
                    width: 300px; background-color: <?php echo $sidebar_bg; ?>; border-radius: <?php echo $border_radius; ?>px;
                    padding: 30px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); align-self: flex-start;
                }
                .nascor-mc-sidebar h3 { margin-top: 0; margin-bottom: 20px; color: <?php echo $primary_color; ?>; }
                .nascor-mc-sidebar ul { list-style: none; padding: 0; margin: 0; }
                .nascor-mc-sidebar li { margin-bottom: 10px; }
                .nascor-mc-sidebar a {
                    display: flex; align-items: center; padding: 12px 15px; text-decoration: none; color: <?php echo $text_color; ?>;
                    border-radius: <?php echo $border_radius; ?>px; transition: all 0.3s ease;
                }
                .nascor-mc-sidebar a i { margin-right: 12px; font-size: 16px; color: <?php echo $primary_color; ?>; width: 20px; text-align: center; }
                .nascor-mc-sidebar a:hover, .nascor-mc-sidebar a.active { background-color: <?php echo $primary_color; ?>; color: #fff; }
                .nascor-mc-sidebar a:hover i, .nascor-mc-sidebar a.active i { color: #fff; }

                .nascor-mc-content {
                    flex: 1; min-width: 0; background-color: <?php echo $sidebar_bg; ?>; border-radius: <?php echo $border_radius; ?>px;
                    padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: relative;
                }
                .nascor-mc-loader {
                    position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8);
                    z-index: 10; display: flex; justify-content: center; align-items: center; font-size: 1.5em; color: <?php echo $primary_color; ?>;
                    border-radius: <?php echo $border_radius; ?>px;
                }

                .nascor-mc-container input[type="text"], .nascor-mc-container input[type="password"], 
                .nascor-mc-container input[type="email"], .nascor-mc-container select {
                    width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 15px; box-sizing: border-box;
                }
                .nascor-mc-container button.button, .nascor-mc-container input[type="submit"] {
                    background-color: <?php echo $primary_color; ?> !important; color: #fff !important; padding: 10px 20px;
                    border: none; border-radius: 5px; transition: opacity 0.3s; cursor: pointer; text-decoration: none; display: inline-block; font-size:16px;
                }
                .nascor-mc-container button.button:hover, .nascor-mc-container input[type="submit"]:hover { opacity: 0.8; }

                .wc-mcs-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .wc-mcs-table th, .wc-mcs-table td { padding: 12px; border: 1px solid #eee; text-align: left; }
                .wc-mcs-table th { background-color: #f5f5f5; color: <?php echo $primary_color; ?>; }

                .wc-mcs-toggle-password { position: absolute; right: 15px; top: 12px; cursor: pointer; color: #777; z-index: 2; }
                
                .form-row-first { width: 48%; float: left; margin-right: 4%; }
                .form-row-last { width: 48%; float: left; margin-right: 0; }
                .form-row-wide { width: 100%; clear: both; }
                .clear { clear: both; display: block; height: 0; }

                .nascor-mc-login-form p { margin-bottom: 15px; }

                @media (max-width: 768px) {
                    .nascor-mc-container { flex-direction: column; padding: 10px; }
                    .nascor-mc-sidebar { width: 100%; padding: 20px 15px; }
                    .nascor-mc-content { padding: 20px 15px; }
                    .form-row-first, .form-row-last { width: 100%; float: none; margin-right: 0; }

                    .wc-mcs-table thead { display: none; }
                    .wc-mcs-table, .wc-mcs-table tbody, .wc-mcs-table tr, .wc-mcs-table td { display: block; width: 100%; }
                    .wc-mcs-table tr { margin-bottom: 1rem; background: #fff; border-radius: 5px; border: 1px solid <?php echo $primary_color; ?>; }
                    .wc-mcs-table td { position: relative; padding: 0.5rem 1rem 0.5rem 45% !important; text-align: right; border: none; }
                    .wc-mcs-table td:before { content: attr(data-label); position: absolute; top: 50%; left: 1rem; transform: translateY(-50%); width: 40%; text-align: left; font-weight: bold; }
                }
            </style>
            <?php
        }

        /**
         * ==========================================
         * 6. JAVASCRIPT: NAVEGACIÓN Y FORMULARIOS
         * ==========================================
         */
        private function print_js() {
            ?>
            <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                const app = document.getElementById('nascor-mc-app');
                if(!app) return;

                const loader = document.querySelector('.nascor-mc-loader');
                const innerContent = document.querySelector('.nascor-mc-inner');

                function initPasswordToggle() {
                    const icons = document.querySelectorAll('.wc-mcs-toggle-password');
                    icons.forEach(icon => {
                        // Evitar listeners múltiples
                        if (icon.dataset.bound) return;
                        icon.dataset.bound = true;
                        
                        icon.addEventListener('click', function() {
                            const input = this.previousElementSibling;
                            if (input && input.tagName === 'INPUT') {
                                if(input.type === 'password') {
                                    input.type = 'text';
                                    this.classList.replace('fa-eye', 'fa-eye-slash');
                                } else {
                                    input.type = 'password';
                                    this.classList.replace('fa-eye-slash', 'fa-eye');
                                }
                            }
                        });
                    });
                }

                // Handler para Modo WooCommerce
                function initWcAjaxForms() {
                    const forms = document.querySelectorAll('.nascor-mc-inner form.woocommerce-EditAccountForm, .nascor-mc-inner form.woocommerce-address-form');
                    forms.forEach(form => {
                        form.addEventListener('submit', async function(e) {
                            e.preventDefault();
                            const btn = form.querySelector('button[type="submit"]');
                            if(btn) { btn.innerText = 'Guardando...'; btn.disabled = true; }
                            loader.style.display = 'flex';

                            const formData = new FormData(form);
                            if(btn) formData.append(btn.name, btn.value);

                            try {
                                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                                const html = await response.text();
                                const doc = new DOMParser().parseFromString(html, 'text/html');
                                const newContent = doc.querySelector('.nascor-mc-dynamic-content') || doc.querySelector('.woocommerce-MyAccount-content');
                                
                                if(newContent) {
                                    innerContent.innerHTML = newContent.innerHTML;
                                    reInitAll();
                                    const msg = document.querySelector('.woocommerce-message, .woocommerce-error');
                                    if(msg) msg.scrollIntoView({behavior: "smooth", block: "center"});
                                }
                            } catch (error) { console.error(error); } finally { loader.style.display = 'none'; }
                        });
                    });
                }

                // Handler para Modo Nativo WordPress
                function initNativeAjaxForms() {
                    const form = document.querySelector('.nascor-native-form');
                    if (!form) return;
                    
                    form.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        const btn = form.querySelector('button[type="submit"]');
                        btn.innerText = 'Guardando...'; btn.disabled = true;
                        loader.style.display = 'flex';
                        
                        const msgDiv = document.getElementById('nascor-native-msg');
                        msgDiv.style.display = 'none';

                        const formData = new FormData(form);
                        formData.append('action', 'nascor_native_save_profile');

                        try {
                            const response = await fetch("<?php echo admin_url('admin-ajax.php'); ?>", { method: 'POST', body: formData });
                            const result = await response.json();
                            
                            msgDiv.style.display = 'block';
                            if (result.success) {
                                msgDiv.style.backgroundColor = '#d4edda';
                                msgDiv.style.color = '#155724';
                                msgDiv.style.border = '1px solid #c3e6cb';
                                msgDiv.innerHTML = result.data;
                                // Limpiar contraseñas
                                form.querySelector('input[name="pass1"]').value = '';
                                form.querySelector('input[name="pass2"]').value = '';
                            } else {
                                msgDiv.style.backgroundColor = '#f8d7da';
                                msgDiv.style.color = '#721c24';
                                msgDiv.style.border = '1px solid #f5c6cb';
                                msgDiv.innerHTML = result.data;
                            }
                            msgDiv.scrollIntoView({behavior: "smooth", block: "center"});
                        } catch (error) {
                            console.error(error);
                        } finally {
                            btn.innerText = 'Guardar cambios';
                            btn.disabled = false;
                            loader.style.display = 'none';
                        }
                    });
                }

                // Navegación Pjax compartida (WC y Nativo)
                const links = document.querySelectorAll('.nascor-mc-nav-link');
                links.forEach(link => {
                    link.addEventListener('click', async function(e) {
                        if(this.getAttribute('data-endpoint') === 'customer-logout') return;
                        
                        e.preventDefault();
                        const url = this.href;

                        links.forEach(l => l.classList.remove('active'));
                        this.classList.add('active');
                        loader.style.display = 'flex';

                        try {
                            const response = await fetch(url);
                            const html = await response.text();
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            
                            // Busca el contenedor interior ya sea WC o Nativo
                            const newContent = doc.querySelector('.nascor-mc-dynamic-content') || doc.querySelector('.woocommerce-MyAccount-content');

                            if(newContent) {
                                innerContent.innerHTML = newContent.innerHTML;
                                window.history.pushState(null, '', url);
                                reInitAll();
                            } else {
                                window.location.href = url;
                            }
                        } catch(error) {
                            window.location.href = url;
                        } finally {
                            loader.style.display = 'none';
                        }
                    });
                });

                function reInitAll() {
                    initPasswordToggle();
                    initWcAjaxForms();
                    initNativeAjaxForms();
                    
                    // Reiniciar filtro de pedidos (si existe)
                    const filter = document.getElementById('wc-mcs-status-filter');
                    if(filter) {
                        filter.addEventListener('change', function() {
                            const selected = this.value;
                            document.querySelectorAll('.wc-mcs-table tbody tr').forEach(row => {
                                if(selected === '') row.style.display = '';
                                else row.style.display = (row.getAttribute('data-status') === 'wc-'+selected) ? '' : 'none';
                            });
                        });
                    }
                }

                reInitAll();
            });
            </script>
            <?php
        }
    }

    new Nascor_Mi_Cuenta_Plugin();
}