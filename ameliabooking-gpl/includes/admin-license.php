<?php
if (!defined('ABSPATH')) exit;

// Menú en Ajustes
add_action('admin_menu', function() {
    if (current_user_can('manage_options')) {
        add_options_page(
            __('Licencia Amelia','ameliabooking-gpl'), 
            __('Licencia Amelia','ameliabooking-gpl'), 
            'manage_options', 
            'amelia-gpl-license', 
            'amelia_gpl_render_license_page' 
        );
    }
}, 99);

function amelia_gpl_render_license_page() { 
    if (!current_user_can('manage_options')) return; 
    
    $api_key = get_option('amelia_gpl_api_key','');
    $status = get_option('amelia_gpl_key_status','inactive');
    $expiry_date = get_option('amelia_gpl_expiry','');
    $max_activations = get_option('amelia_gpl_max_activations', 0);
    $remaining_activations = get_option('amelia_gpl_remaining_activations', 0);
    // Calcular activaciones usadas correctamente
    $activation_count = ($max_activations > 0) ? max(0, $max_activations - $remaining_activations) : 0;
    
    $is_active = $status === 'active';

    if ($is_active && !empty($expiry_date)) {
        $expiry_timestamp = strtotime($expiry_date);
        $current_timestamp = current_time('timestamp');
        
        if ($current_timestamp >= $expiry_timestamp) {
            $is_active = false;
            $status = 'expired';
            update_option('amelia_gpl_key_status', $status);
        }
    }

    $nonce = wp_create_nonce('amelia_gpl_nonce');

    ?>
    <div class="wrap">
        <div class="amelia-gpl-container">
            <h1><?php echo esc_html(__('Licencia Amelia','ameliabooking-gpl')); ?></h1>
            
            <?php if ($is_active): ?>
                <div id="license-message" class="notice notice-success is-dismissible" style="display: none;"></div>
                <div class="amelia-license-status amelia-license-active">
                    <div class="status-icon">✅</div>
                    <h2 class="status-title"><?php echo esc_html(__('Su API Key está ACTIVA.','ameliabooking-gpl')); ?></h2>
                    <p><strong><?php echo esc_html(__('Válida hasta:','ameliabooking-gpl')); ?></strong> <?php echo esc_html(date_i18n('d/m/Y', strtotime($expiry_date))); ?></p>
                    
                    <?php if ($max_activations > 0): ?>
                        <p><strong><?php echo esc_html(__('Activaciones:','ameliabooking-gpl')); ?></strong> <?php echo esc_html($activation_count); ?> / <?php echo esc_html($max_activations); ?></p>
                        <?php if ($remaining_activations <= 3 && $remaining_activations >= 0): ?>
                            <p class="amelia-license-warning">⚠️ <?php echo esc_html(sprintf(__('Le quedan %d activaciones.','ameliabooking-gpl'), $remaining_activations)); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><strong><?php echo esc_html(__('Activaciones:','ameliabooking-gpl')); ?></strong> Ilimitadas</p>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="deactivate-section">
                        <button id="deactivate-key" class="button button-secondary button-large"><?php echo esc_html(__('Desactivar Licencia','ameliabooking-gpl')); ?></button>
                    </div>
                </div>
            <?php elseif ($status === 'expired'): ?>
                <div id="license-message" class="notice notice-error is-dismissible" style="display: none;"></div>
                <div class="amelia-license-status amelia-license-inactive" style="border-left: 5px solid #ffba00; background-color: #fffbe6;">
                    <div class="status-icon">⚠️</div>
                    <h2 class="status-title"><?php echo esc_html(__('Su API Key ha CADUCADO.','ameliabooking-gpl')); ?></h2>
                    <p><strong><?php echo esc_html(__('Caducó el:','ameliabooking-gpl')); ?></strong> <?php echo esc_html(date_i18n('d/m/Y', strtotime($expiry_date))); ?></p>
                    <p class="description-text"><?php echo esc_html(__('Por favor, contacte con su proveedor para renovar su API Key.','ameliabooking-gpl')); ?></p>
                    
                    <form id="amelia-gpl-license-form">
                        <?php wp_nonce_field('amelia_gpl_nonce', 'security'); ?>
                        <input type="text" id="api_key_input" name="api_key" placeholder="<?php echo esc_attr(__('Escriba su API Key aquí','ameliabooking-gpl')); ?>" value="<?php echo esc_attr($api_key); ?>" class="regular-text" style="width: 100%;">
                        <button id="validate-key" class="button button-primary button-large" disabled><?php echo esc_html(__('Activar Licencia','ameliabooking-gpl')); ?></button>
                    </form>
                </div>
            <?php else: ?>
                <div id="license-message" class="notice notice-error is-dismissible" style="display: none;"></div>
                <div class="amelia-license-status amelia-license-inactive">
                    <div class="status-icon">❌</div>
                    <h2 class="status-title"><?php echo esc_html(__('Su API Key está INACTIVA.','ameliabooking-gpl')); ?></h2>
                    <p class="description-text"><?php echo esc_html(__('Introduzca su API Key para activar las actualizaciones automáticas.','ameliabooking-gpl')); ?></p>
                    
                    <form id="amelia-gpl-license-form">
                        <?php wp_nonce_field('amelia_gpl_nonce', 'security'); ?>
                        <input type="text" id="api_key_input" name="api_key" placeholder="<?php echo esc_attr(__('Escriba su API Key aquí','ameliabooking-gpl')); ?>" value="<?php echo esc_attr($api_key); ?>" class="regular-text" style="width: 100%;">
                        <button id="validate-key" class="button button-primary button-large" disabled><?php echo esc_html(__('Activar Licencia','ameliabooking-gpl')); ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .amelia-gpl-container { max-width: 650px; margin: 0 auto; padding-top: 20px; text-align: center; }
        .amelia-license-status { padding: 30px; border-radius: 8px; margin-top: 30px; box-shadow: 0 4px 12px rgba(0,0,0,.08); border: 1px solid #eee; text-align: left; }
        .status-title { margin-top: 0; margin-bottom: 20px; font-size: 1.5em; font-weight: 600; display: inline-block; vertical-align: middle; margin-left: 10px; }
        .status-icon { font-size: 1.8em; display: inline-block; vertical-align: middle; }
        .amelia-license-active { border-left: 5px solid #46b450; background-color: #f9fff9; }
        .amelia-license-inactive { border-left: 5px solid #dc3232; background-color: #fff9f9; }
        .amelia-license-status p { margin: 10px 0; line-height: 1.6; }
        .amelia-license-status p strong { display: inline-block; min-width: 150px; font-weight: 700; color: #333; }
        .amelia-license-warning { color: #d8a002; font-weight: 600; background-color: #fffbe6; padding: 8px; border-radius: 4px; border: 1px solid #d8a00230; margin-top: 15px; }
        #amelia-gpl-license-form { margin-top: 25px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 10px; align-items: center; }
        #api_key_input { width: 100%; margin-bottom: 5px; padding: 10px; line-height: 1.4; height: auto; font-size: 1.1em; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-color: #ccc; }
        #validate-key, #deactivate-key { width: 100%; text-align: center; height: 40px; line-height: 38px; font-size: 1em; margin-top: 5px; }
        hr { border: 0; height: 1px; background-color: #eee; margin: 20px 0; }
        .deactivate-section { text-align: center; margin: 0; }
        #license-message { margin-top: 20px; display: none; text-align: left; }
        .description-text { color: #555; font-style: italic; text-align: center; }
    </style>
    
    <script>
    (function(){
        const input = document.getElementById('api_key_input');
        const btn = document.getElementById('validate-key');
        const deact = document.getElementById('deactivate-key');
        const msgBox = document.getElementById('license-message');
        const nonce = '<?php echo esc_js($nonce); ?>';
        const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        
        function showMessage(text, type) {
            const p = document.createElement('p');
            p.textContent = text;
            msgBox.innerHTML = '';
            msgBox.appendChild(p);
            msgBox.className = `notice notice-${type} is-dismissible`;
            msgBox.style.display = 'block';
            msgBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => { msgBox.style.display = 'none'; }, 5000); 
        }

        if (input) {
            input.addEventListener('input', function() {
                btn.disabled = this.value.trim().length === 0;
            });
        }

        function validateKey() {
            if (!input.value.trim()) return;
            
            msgBox.style.display = 'none';
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<?php echo esc_js(__('Validando...','ameliabooking-gpl')); ?>';
            
            const formData = new URLSearchParams();
            formData.append('action', 'amelia_gpl_validate_key');
            formData.append('security', nonce);
            formData.append('api_key', input.value.trim());

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (data.success) {
                    showMessage('✅ API Key activada correctamente. Recargando...', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    const errorMsg = data.data && data.data.message ? data.data.message : 'API Key no válida.';
                    showMessage(`❌ ${errorMsg}`, 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showMessage('❌ Error de conexión.', 'error');
            });
        }
        
        function deactivateKey() {
            if (!confirm('<?php echo esc_js(__('¿Está seguro de desactivar la API Key?','ameliabooking-gpl')); ?>')) {
                return;
            }
            
            msgBox.style.display = 'none';
            deact.disabled = true;
            const originalText = deact.innerHTML;
            deact.innerHTML = '<?php echo esc_js(__('Desactivando...','ameliabooking-gpl')); ?>';
            
            const formData = new URLSearchParams();
            formData.append('action', 'amelia_gpl_deactivate_key');
            formData.append('security', nonce);

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                deact.disabled = false;
                deact.innerHTML = originalText;
                
                if (data.success) {
                    showMessage('✅ API Key desactivada. Recargando...', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    const errorMsg = data.data && data.data.message ? data.data.message : 'Error al desactivar.';
                    showMessage(`❌ ${errorMsg}`, 'error');
                }
            })
            .catch(err => {
                deact.disabled = false;
                deact.innerHTML = originalText;
                showMessage('❌ Error de conexión.', 'error');
            });
        }
        
        if (btn) {
            btn.addEventListener('click', function(e){
                e.preventDefault();
                validateKey();
            });
        }
        
        if (deact) {
            deact.addEventListener('click', function(e){
                e.preventDefault();
                deactivateKey();
            });
        }
        
        if (input) {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !btn.disabled) {
                    e.preventDefault();
                    validateKey();
                }
            });
        }
    })();
    </script>
    <?php 
}
