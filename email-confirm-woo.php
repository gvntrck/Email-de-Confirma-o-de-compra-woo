<?php
/*
Plugin Name: Email de Confirmação de Inscrição por Produto
Description: Envia emails personalizados para diferentes produtos quando o pedido é marcado como concluído
Version: 1.3
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
                            <textarea id="edit_content" name="edit_content" rows="15" style="width: 100%" required></textarea>
                        </div>
                        <input type="submit" name="edit_config" class="button button-primary" value="Salvar Alterações">
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
            .close-modal:hover {
                color: #666;
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
                $this->send_email($order, $configs[$product_id]);
                update_post_meta($order_id, '_email_enviado_'.$product_id, 'yes');
            }
        }
    }

    private function send_email($order, $config) {
        $to = $order->get_billing_email();
        $subject = $config['subject'];
        $message = $config['content'];
        
        // Garantir que o conteúdo seja tratado como HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Rio Chess Open <noreply@riochessopen.com>'
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

}

new Custom_Confirmation_Emails();

// Registrar ajax
add_action('wp_ajax_delete_email_config', array('Custom_Confirmation_Emails', 'delete_email_config'));