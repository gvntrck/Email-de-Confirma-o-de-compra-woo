<?php
/*
Plugin Name: Email de Confirmação de Inscrição por Produto
Description: Envia emails personalizados para diferentes produtos quando o pedido é marcado como concluído
Version: 1.6
Author: Gvntrck
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;

class Custom_Confirmation_Emails {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_order_status_completed', array($this, 'send_custom_emails'));
    }

    // Adiciona menu de administração
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Configurações de Email de Confirmação',
            'Emails de Confirmação',
            'manage_options',
            'custom-confirmation-emails',
            array($this, 'settings_page')
        );
    }

    // Registra as configurações
    public function register_settings() {
        register_setting('custom_confirmation_emails_group', 'custom_confirmation_emails', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }

    // Sanitização dos dados
    public function sanitize_settings($input) {
        $new_input = array();
        
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $new_input[$key] = array(
                    'product_id' => absint($value['product_id']),
                    'subject' => sanitize_text_field($value['subject']),
                    'content' => wp_kses_post($value['content'])
                );
            }
        }
        return $new_input;
    }

    // Página de configurações
    public function settings_page() {
        // Processar edição
        if (isset($_POST['edit_config'])) {
            $configs = get_option('custom_confirmation_emails', array());
            $edit_product_id = absint($_POST['edit_product_id']);
            
            if ($edit_product_id > 0) {
                $configs[$edit_product_id] = array(
                    'product_id' => $edit_product_id,
                    'subject' => sanitize_text_field($_POST['edit_subject']),
                    'content' => wp_kses_post($_POST['edit_content'])
                );
                
                update_option('custom_confirmation_emails', $configs);
                echo '<div class="notice notice-success"><p>Configuração atualizada com sucesso!</p></div>';
            }
        }

        // Processar adição
        if (isset($_POST['add_config'])) {
            $configs = get_option('custom_confirmation_emails', array());
            $new_product_id = absint($_POST['new_product_id']);
            
            if ($new_product_id > 0) {
                $configs[$new_product_id] = array(
                    'product_id' => $new_product_id,
                    'subject' => sanitize_text_field($_POST['new_subject']),
                    'content' => wp_kses_post($_POST['new_content'])
                );
                
                update_option('custom_confirmation_emails', $configs);
                echo '<div class="notice notice-success"><p>Configuração adicionada com sucesso!</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Configurações de Emails de Confirmação</h1>
            
            <div class="postbox" style="margin-top: 20px; padding: 20px;">
                <h2>Adicionar Nova Configuração</h2>
                
                <form method="post" action="">
                    <div class="form-field">
                        <label for="new_product_id">ID do Produto</label>
                        <input type="number" id="new_product_id" name="new_product_id" required>
                    </div>
                    
                    <div class="form-field">
                        <label for="new_subject">Assunto do Email</label>
                        <input type="text" id="new_subject" name="new_subject" style="width: 100%" required>
                    </div>
                    
                    <div class="form-field">
                        <label for="new_content">Conteúdo do Email (HTML)</label>
                        <p class="description" style="margin-bottom: 5px; color: #666; font-size: 13px;">
                            Você pode usar os seguintes placeholders: <code>{nomedocomprador}</code> e <code>{nomeproduto}</code>.
                        </p>
                        <textarea id="new_content" name="new_content" rows="10" style="width: 100%" required></textarea>
                    </div>
                    
                    <input type="submit" name="add_config" class="button button-primary" value="Adicionar Configuração">
                </form>
            </div>

            <h2 style="margin-top: 30px;">Configurações Existentes</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID do Produto</th>
                        <th>Assunto</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $configs = get_option('custom_confirmation_emails', array()); ?>
                    <?php foreach ($configs as $product_id => $config): ?>
                    <tr>
                        <td><?php echo $product_id; ?></td>
                        <td><?php echo $config['subject']; ?></td>
                        <td>
                            <button type="button" class="button edit-config" data-product-id="<?php echo $product_id; ?>" 
                                    data-subject="<?php echo esc_attr($config['subject']); ?>"
                                    data-content="<?php echo esc_attr($config['content']); ?>">
                                Editar
                            </button>
                            <button type="button" class="button test-config" data-product-id="<?php echo $product_id; ?>"
                                    data-subject="<?php echo esc_attr($config['subject']); ?>"
                                    data-content="<?php echo esc_attr($config['content']); ?>">
                                Teste
                            </button>
                            <a href="#" class="button delete-config" data-product-id="<?php echo $product_id; ?>">Excluir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal de Edição -->
            <div id="edit-modal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                <div class="modal-content" style="background-color: #fff; margin: 5% auto; padding: 20px; width: 80%; max-width: 800px; position: relative;">
                    <span class="close-modal" style="position: absolute; right: 10px; top: 5px; font-size: 20px; cursor: pointer;">&times;</span>
                    <h2>Editar Configuração</h2>
                    <form method="post" action="">
                        <input type="hidden" id="edit_product_id" name="edit_product_id">
                        <div class="form-field">
                            <label for="edit_subject">Assunto do Email</label>
                            <input type="text" id="edit_subject" name="edit_subject" style="width: 100%" required>
                        </div>
                        <div class="form-field">
                            <label for="edit_content">Conteúdo do Email (HTML)</label>
                            <p class="description" style="margin-bottom: 5px; color: #666; font-size: 13px;">
                                Você pode usar os seguintes placeholders: <code>{nomedocomprador}</code> e <code>{nomeproduto}</code>.
                            </p>
                            <textarea id="edit_content" name="edit_content" rows="15" style="width: 100%" required></textarea>
                        </div>
                        <input type="submit" name="edit_config" class="button button-primary" value="Salvar Alterações">
                    </form>
                </div>
            </div>

            <!-- Modal de Teste -->
            <div id="test-modal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                <div class="modal-content" style="background-color: #fff; margin: 10% auto; padding: 20px; width: 50%; max-width: 500px; position: relative;">
                    <span class="close-test-modal" style="position: absolute; right: 10px; top: 5px; font-size: 20px; cursor: pointer;">&times;</span>
                    <h2>Enviar Email de Teste</h2>
                    <div id="test-response-message"></div>
                    <form id="test-email-form">
                        <input type="hidden" id="test_product_id" name="test_product_id">
                        <input type="hidden" id="test_subject" name="test_subject">
                        <input type="hidden" id="test_content" name="test_content">
                        
                        <div class="form-field">
                            <label for="test_email_address">Email para envio:</label>
                            <input type="email" id="test_email_address" name="test_email_address" style="width: 100%" required placeholder="exemplo@email.com">
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <button type="submit" class="button button-primary">Enviar Teste</button>
                            <span class="spinner" style="float: none; margin: 0 10px;"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Deletar configuração
                $('.delete-config').on('click', function(e) {
                    e.preventDefault();
                    if (confirm('Tem certeza que deseja excluir esta configuração?')) {
                        var productId = $(this).data('product-id');
                        
                        $.post(ajaxurl, {
                            action: 'delete_email_config',
                            product_id: productId,
                            security: '<?php echo wp_create_nonce('delete_email_config'); ?>'
                        }, function() {
                            location.reload();
                        });
                    }
                });

                // Editar configuração
                $('.edit-config').on('click', function() {
                    var productId = $(this).data('product-id');
                    var subject = $(this).data('subject');
                    var content = $(this).data('content');

                    $('#edit_product_id').val(productId);
                    $('#edit_subject').val(subject);
                    $('#edit_content').val(content);
                    $('#edit-modal').show();
                });

                // Fechar modal
                $('.close-modal').on('click', function() {
                    $('#edit-modal').hide();
                });

                // Fechar modal ao clicar fora
                $(window).on('click', function(e) {
                    if ($(e.target).is('#edit-modal')) {
                        $('#edit-modal').hide();
                    }
                    if ($(e.target).is('#test-modal')) {
                        $('#test-modal').hide();
                    }
                });

                // --- Lógica do Modal de Teste ---

                // Abrir modal de teste
                $('.test-config').on('click', function() {
                    var productId = $(this).data('product-id');
                    var subject = $(this).data('subject');
                    var content = $(this).data('content');

                    $('#test_product_id').val(productId);
                    $('#test_subject').val(subject);
                    $('#test_content').val(content);
                    $('#test_email_address').val(''); // Limpa email anterior
                    $('#test-response-message').html(''); // Limpa mensagens anteriores
                    $('#test-modal').show();
                });

                // Fechar modal de teste
                $('.close-test-modal').on('click', function() {
                    $('#test-modal').hide();
                });

                // Enviar email de teste via AJAX
                $('#test-email-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var $form = $(this);
                    var $submitBtn = $form.find('button[type="submit"]');
                    var $spinner = $form.find('.spinner');
                    
                    $submitBtn.prop('disabled', true);
                    $spinner.addClass('is-active');
                    $('#test-response-message').html('');

                    var data = {
                        action: 'send_test_email_config',
                        security: '<?php echo wp_create_nonce('send_test_email_config'); ?>',
                        email: $('#test_email_address').val(),
                        subject: $('#test_subject').val(),
                        content: $('#test_content').val()
                    };

                    $.post(ajaxurl, data, function(response) {
                        $submitBtn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                        
                        if (response.success) {
                            $('#test-response-message').html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                            setTimeout(function() {
                                $('#test-modal').hide();
                            }, 2000);
                        } else {
                            $('#test-response-message').html('<div class="notice notice-error inline"><p>' + (response.data || 'Erro ao enviar email.') + '</p></div>');
                        }
                    }).fail(function() {
                        $submitBtn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                        $('#test-response-message').html('<div class="notice notice-error inline"><p>Erro de conexão. Tente novamente.</p></div>');
                    });
                });
            });
        </script>

        <style>
            .form-field {
                margin-bottom: 15px;
            }
            .form-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .postbox {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .modal-content {
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            .close-modal:hover, .close-test-modal:hover {
                color: #666;
            }
            /* Ajuste para spinner do WP */
            .spinner {
                float: none;
                margin: 0 10px;
                visibility: hidden;
            }
            .spinner.is-active {
                visibility: visible;
            }
        </style>
        <?php
    }

    // Lógica para enviar emails
    public function send_custom_emails($order_id) {
        $order = wc_get_order($order_id);
        $configs = get_option('custom_confirmation_emails', array());

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

            if (isset($configs[$product_id]) && !get_post_meta($order_id, '_email_enviado_'.$product_id, true)) {
                $this->send_email($order, $configs[$product_id], $product->get_name());
                update_post_meta($order_id, '_email_enviado_'.$product_id, 'yes');
            }
        }
    }

    private function send_email($order, $config, $product_name) {
        $to = $order->get_billing_email();
        
        // Dados para substituição
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $buyer_name = trim($first_name . ' ' . $last_name);
        if (empty($buyer_name)) {
            $buyer_name = 'Cliente';
        }

        $replacements = array(
            '{nomedocomprador}' => $buyer_name,
            '{nomeproduto}' => $product_name
        );

        $subject = str_replace(array_keys($replacements), array_values($replacements), $config['subject']);
        $message = str_replace(array_keys($replacements), array_values($replacements), $config['content']);
        
        // Garantir que o conteúdo seja tratado como HTML
        $site_title = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        // Tentar pegar configurações do WooCommerce se disponíveis
        if (function_exists('WC')) {
            $woo_from_name = get_option('woocommerce_email_from_name');
            $woo_from_email = get_option('woocommerce_email_from_address');
            
            if (!empty($woo_from_name)) {
                $site_title = $woo_from_name;
            }
            if (!empty($woo_from_email)) {
                $admin_email = $woo_from_email;
            }
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_title . ' <' . $admin_email . '>'
        );

        wp_mail($to, $subject, $message, $headers);
    }

    // Ajax para excluir configurações
    public static function delete_email_config() {
        check_ajax_referer('delete_email_config', 'security');

        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }

        $product_id = absint($_POST['product_id']);
        $configs = get_option('custom_confirmation_emails', array());

        if (isset($configs[$product_id])) {
            unset($configs[$product_id]);
            update_option('custom_confirmation_emails', $configs);
        }

        wp_die();
    }

    // Ajax para enviar email de teste
    public static function send_test_email_config() {
        check_ajax_referer('send_test_email_config', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
        }

        $email = sanitize_email($_POST['email']);
        $subject_raw = sanitize_text_field($_POST['subject']);
        $content_raw = wp_kses_post($_POST['content']);

        if (!is_email($email)) {
            wp_send_json_error('Email inválido.');
        }

        // Dados fictícios para teste
        $replacements = array(
            '{nomedocomprador}' => 'Fulano de Tal',
            '{nomeproduto}' => 'Produto de Teste'
        );

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_raw);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content_raw);

        $site_title = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        // Tentar pegar configurações do WooCommerce se disponíveis
        if (function_exists('WC')) {
            $woo_from_name = get_option('woocommerce_email_from_name');
            $woo_from_email = get_option('woocommerce_email_from_address');
            
            if (!empty($woo_from_name)) {
                $site_title = $woo_from_name;
            }
            if (!empty($woo_from_email)) {
                $admin_email = $woo_from_email;
            }
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_title . ' <' . $admin_email . '>'
        );

        $sent = wp_mail($email, $subject, $content, $headers);

        if ($sent) {
            wp_send_json_success('Email de teste enviado com sucesso!');
        } else {
            wp_send_json_error('Falha ao enviar o email. Verifique as configurações do servidor.');
        }
    }

}

new Custom_Confirmation_Emails();

// Registrar ajax
add_action('wp_ajax_delete_email_config', array('Custom_Confirmation_Emails', 'delete_email_config'));
add_action('wp_ajax_send_test_email_config', array('Custom_Confirmation_Emails', 'send_test_email_config'));