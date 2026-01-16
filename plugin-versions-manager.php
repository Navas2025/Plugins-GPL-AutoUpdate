<?php
/**
 * Plugin Versions Manager (con identifiers para matching)
 * Version: 2.0.8
 * Author: Navas (adaptado)
 *
 * Genera plugin-versions-cache.json en uploads con, entre otros campos:
 * - version
 * - identifiers (array)
 * - info_url (para "Ver detalles")
 *
 * Nota: esta versión es la misma que me pasaste que funciona, con la única
 * modificación: se añade un buscador en la UI (campo 's' en la página).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Plugin_Versions_Manager {

    private $cache_file;
    private $table_name;
    private $build_transient_key = 'pvm_build_cache';
    private $running_transient_key = 'pvm_rebuild_running';
    
    // Pagination constants
    private const MAX_PER_PAGE_THRESHOLD = 1000;
    private const ALL_RESULTS_VALUE = 999;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'plugin_slugs_manual';

        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        add_action( 'wp_ajax_pvm_regenerate_cache_batch', array( $this, 'ajax_regenerate_cache_batch' ) );
        add_action( 'wp_ajax_pvm_save_manual_slug', array( $this, 'ajax_save_manual_slug' ) );

        register_activation_hook( __FILE__, array( $this, 'create_table' ) );
    }

    public function init() {
        $upload_dir = wp_upload_dir();
        if ( ! $upload_dir['basedir'] || ! is_writable( $upload_dir['basedir'] ) ) {
            add_action( 'admin_notices', array( $this, 'show_error_notice' ) );
            return;
        }

        $this->cache_file = $upload_dir['basedir'] . '/plugin-versions-cache.json';

        add_action( 'save_post_product', array( $this, 'update_cache_on_product_save' ), 10, 3 );

        // Si no existe, generarla en segundo plano (ó al activar)
        if ( ! file_exists( $this->cache_file ) ) {
            // crear de forma síncrona primera vez
            $this->regenerate_full_cache();
        }
    }

    public function show_error_notice() {
        echo '<div class="notice notice-error"><p>Plugin Versions Manager: El directorio de uploads no es escribible.</p></div>';
    }

    public function add_admin_menu() {
        add_menu_page(
            'Gestión de Slugs',
            'Gestión Slugs',
            'manage_options',
            'plugin-slugs-manager',
            array( $this, 'render_admin_page' ),
            'dashicons-admin-plugins',
            58
        );
    }

    public function render_admin_page() {
        // Obtener término de búsqueda
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        
        // Obtener parámetros de paginación
        $per_page = isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 20;
        if ( $per_page <= 0 ) $per_page = 20;
        if ( $per_page > self::MAX_PER_PAGE_THRESHOLD ) $per_page = -1; // "Todos"
        
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        
        if ( isset( $_POST['pvm_action'] ) && check_admin_referer( 'pvm_action_nonce' ) ) {
            $action = sanitize_text_field( wp_unslash( $_POST['pvm_action'] ) );

            switch ( $action ) {
                case 'save_manual':
                    $product_id = intval( $_POST['product_id'] );
                    $manual_slug = sanitize_title( wp_unslash( $_POST['manual_slug'] ) );

                    if ( $product_id && $manual_slug ) {
                        $result = $this->save_manual_slug( $product_id, $manual_slug );
                        if ( $result ) {
                            $this->regenerate_full_cache();
                            echo '<div class="notice notice-success is-dismissible"><p>✅ Slug manual guardado: <code>' . esc_html( $manual_slug ) . '</code></p></div>';
                        } else {
                            echo '<div class="notice notice-error is-dismissible"><p>❌ Error al guardar el slug en la base de datos</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>❌ Datos inválidos</p></div>';
                    }
                    break;

                case 'delete_manual':
                    $product_id = intval( $_POST['product_id'] );
                    if ( $product_id ) {
                        $this->delete_manual_slug( $product_id );
                        $this->regenerate_full_cache();
                        echo '<div class="notice notice-success is-dismissible"><p>✅ Slug manual eliminado</p></div>';
                    }
                    break;
            }
        }
        
        // Obtener total de productos (para paginación)
        $total_products = $this->get_total_products_count_with_search( $search );
        
        // Calcular offset
        $offset = ( $current_page - 1 ) * $per_page;
        if ( $per_page === -1 ) {
            $offset = 0;
            $per_page = $total_products; // Mostrar todos
        }
        
        // Obtener productos paginados
        $products = $this->get_products_with_slugs( $search, $offset, $per_page );
        
        // Calcular total de páginas
        $total_pages = $per_page > 0 ? ceil( $total_products / $per_page ) : 1;
        
        $ajax_nonce = wp_create_nonce( 'pvm_regenerate_nonce' );

        ?>
        <div class="wrap">
            <h1>🔧 Gestión de Slugs de Plugins</h1>

            <!-- BUSCADOR -->
            <form method="get" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="plugin-slugs-manager">
                <input type="hidden" name="per_page" value="<?php echo esc_attr( $per_page === $total_products ? self::ALL_RESULTS_VALUE : $per_page ); ?>">
                <label for="pvm_search" class="screen-reader-text">Buscar productos</label>
                <input type="search" id="pvm_search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar por título..." class="regular-text" />
                <button class="button" type="submit">Buscar</button>
                <?php if ( $search ) : ?>
                    <a href="<?php echo esc_url( remove_query_arg( array( 's', 'paged' ) ) ); ?>" class="button">Limpiar</a>
                <?php endif; ?>
            </form>

            <!-- REGENERAR CACHÉ -->
            <div style="margin-bottom:20px;">
                <button id="pvm-regenerate-button" class="button button-primary">🔄 Regenerar Caché Completo (por lotes)</button>
                <span id="pvm-regenerate-status" style="margin-left:15px;color:#555;"></span>
            </div>

            <!-- SELECTOR DE RESULTADOS POR PÁGINA -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <label for="per-page-selector" class="screen-reader-text">Resultados por página</label>
                    <select name="per_page" id="per-page-selector" onchange="changePerPage(this.value)">
                        <option value="20" <?php selected( $per_page, 20 ); ?>>20 por página</option>
                        <option value="40" <?php selected( $per_page, 40 ); ?>>40 por página</option>
                        <option value="100" <?php selected( $per_page, 100 ); ?>>100 por página</option>
                        <option value="<?php echo self::ALL_RESULTS_VALUE; ?>" <?php selected( $per_page === self::ALL_RESULTS_VALUE || $per_page === -1 || $per_page === $total_products, true ); ?>>Todos (<?php echo $total_products; ?>)</option>
                    </select>
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total_products; ?> elementos</span>
                    <?php if ( $total_pages > 1 ) : ?>
                        <?php
                        $base_url = add_query_arg( array(
                            'page' => 'plugin-slugs-manager',
                            'per_page' => $per_page === $total_products ? self::ALL_RESULTS_VALUE : $per_page,
                            's' => $search
                        ), admin_url( 'admin.php' ) );
                        ?>
                        
                        <span class="pagination-links">
                            <?php if ( $current_page > 1 ) : ?>
                                <a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">
                                    <span aria-hidden="true">«</span>
                                </a>
                                <a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>">
                                    <span aria-hidden="true">‹</span>
                                </a>
                            <?php else : ?>
                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                            <?php endif; ?>
                            
                            <span class="paging-input">
                                <label for="current-page-selector" class="screen-reader-text">Página actual</label>
                                <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr( $current_page ); ?>" size="<?php echo strlen( $total_pages ); ?>" aria-describedby="table-paging">
                                <span class="tablenav-paging-text"> de <span class="total-pages"><?php echo $total_pages; ?></span></span>
                            </span>
                            
                            <?php if ( $current_page < $total_pages ) : ?>
                                <a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>">
                                    <span aria-hidden="true">›</span>
                                </a>
                                <a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ); ?>">
                                    <span aria-hidden="true">»</span>
                                </a>
                            <?php else : ?>
                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <h2>Productos (mostrando <?php echo count( $products ); ?> de <?php echo $total_products; ?>)</h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Versión</th>
                        <th>Slug Automático</th>
                        <th>Slug Manual</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $products ) ) : ?>
                        <tr><td colspan="5">No hay productos publicados</td></tr>
                    <?php else : ?>
                        <?php foreach ( $products as $product ) : ?>
                            <tr data-product-id="<?php echo $product['id']; ?>">
                                <td><strong><?php echo esc_html( $product['name'] ); ?></strong><br><small>ID: <?php echo $product['id']; ?></small></td>
                                <td><code><?php echo esc_html( $product['version'] ); ?></code></td>
                                <td><code><?php echo esc_html( $product['auto_slug'] ); ?></code></td>
                                <td class="manual-slug-cell">
                                    <?php echo $product['manual_slug'] ? '<code style="background:#d4edda;padding:3px;">✓ '.esc_html($product['manual_slug']).'</code>' : '<span style="color:#999;">—</span>'; ?>
                                </td>
                                <td>
                                    <button class="button" onclick="editSlug(<?php echo $product['id']; ?>, '<?php echo esc_js( $product['name'] ); ?>', '<?php echo esc_js( $product['manual_slug'] ?: $product['auto_slug'] ); ?>')">✏️ Editar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="edit-slug-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:30px; border-radius:8px; min-width:500px;">
                <h2>✏️ Editar Slug Manual</h2>
                <form method="post">
                    <?php wp_nonce_field( 'pvm_action_nonce' ); ?>
                    <input type="hidden" name="pvm_action" value="save_manual">
                    <input type="hidden" name="product_id" id="edit-product-id">
                    <p><strong>Producto:</strong> <span id="edit-product-name"></span></p>
                    <p>
                        <label for="manual-slug">Slug Manual:</label><br>
                        <input type="text" name="manual_slug" id="manual-slug" pattern="[a-z0-9-]+" title="Solo letras minúsculas, números y guiones" class="regular-text" required>
                    </p>
                    <p><button class="button button-primary" type="submit">Guardar</button> <button type="button" class="button" onclick="closeModal()">Cancelar</button></p>
                </form>
            </div>
        </div>

        <script data-no-minify="1" data-no-defer="1">
        // ========== MODAL DE EDICIÓN ==========
        function editSlug(productId, productName, currentSlug) {
            document.getElementById('edit-product-id').value = productId;
            document.getElementById('edit-product-name').textContent = productName;
            document.getElementById('manual-slug').value = currentSlug;
            document.getElementById('edit-slug-modal').style.display = 'block';
        }

        function closeModal() { 
            document.getElementById('edit-slug-modal').style.display = 'none'; 
        }

        // ========== GUARDAR SLUG CON AJAX (SIN RECARGAR) ==========
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#edit-slug-modal form');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const productId = document.getElementById('edit-product-id').value;
                    const manualSlug = document.getElementById('manual-slug').value;
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;
                    
                    console.log('=== DEBUG: Guardando slug ===');
                    console.log('Product ID:', productId);
                    console.log('Manual Slug:', manualSlug);
                    console.log('AJAX URL:', '<?php echo admin_url( 'admin-ajax.php' ); ?>');
                    
                    // Deshabilitar botón
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Guardando...';
                    
                    // Timeout de seguridad: cerrar modal después de 10 segundos
                    const timeoutId = setTimeout(function() {
                        console.warn('⏱️ Timeout: Cerrando modal después de 10 segundos');
                        closeModal();
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        showNotice('info', 'ℹ️ Timeout alcanzado. Verifique si el slug se guardó correctamente en la tabla.');
                    }, 10000);
                    
                    // Enviar por AJAX
                    const formData = new FormData();
                    formData.append('action', 'pvm_save_manual_slug');
                    formData.append('product_id', productId);
                    formData.append('manual_slug', manualSlug);
                    formData.append('nonce', '<?php echo wp_create_nonce( 'pvm_save_slug_nonce' ); ?>');
                    
                    fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(response => {
                        console.log('Respuesta HTTP status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Respuesta del servidor:', data);
                        clearTimeout(timeoutId);
                        
                        // Detección más robusta de éxito
                        if (data && (data.success === true || (data.data && data.data.manual_slug))) {
                            console.log('✅ Slug guardado exitosamente');
                            
                            // Actualizar la fila en la tabla SIN recargar
                            const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                            if (row) {
                                const manualSlugCell = row.querySelector('.manual-slug-cell');
                                if (manualSlugCell) {
                                    manualSlugCell.innerHTML = `<code style="background:#d4edda;padding:3px;">✓ ${manualSlug}</code>`;
                                }
                                
                                // Efecto visual de éxito
                                row.style.backgroundColor = '#d4edda';
                                setTimeout(() => { row.style.backgroundColor = ''; }, 2000);
                            }
                            
                            // Mostrar notificación de éxito
                            showNotice('success', '✅ Slug guardado: <code>' + manualSlug + '</code>');
                            
                            // CRÍTICO: Cerrar modal automáticamente después de 1 segundo
                            setTimeout(function() {
                                closeModal();
                                submitBtn.disabled = false;
                                submitBtn.textContent = originalText;
                            }, 1000);
                            
                        } else {
                            console.error('❌ Respuesta inesperada del servidor:', data);
                            clearTimeout(timeoutId);
                            
                            showNotice('warning', '⚠️ Respuesta inesperada del servidor. Verifique la tabla.');
                            
                            // Cerrar modal de todas formas después de 2 segundos
                            setTimeout(function() {
                                closeModal();
                                submitBtn.disabled = false;
                                submitBtn.textContent = originalText;
                            }, 2000);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error en fetch AJAX:', error);
                        clearTimeout(timeoutId);
                        
                        showNotice('error', '❌ Error de conexión: ' + error.message);
                        
                        // Cerrar modal después de 2 segundos incluso con error
                        setTimeout(function() {
                            closeModal();
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        }, 2000);
                    });
                });
            }
        });

        // Función para mostrar notificaciones
        function showNotice(type, message) {
            const noticeClasses = {
                'success': 'notice-success',
                'error': 'notice-error',
                'warning': 'notice-warning',
                'info': 'notice-info'
            };
            const noticeClass = noticeClasses[type] || 'notice-info';
            
            const notice = document.createElement('div');
            notice.className = `notice ${noticeClass} is-dismissible`;
            notice.innerHTML = `<p>${message}</p>`;
            notice.style.marginTop = '20px';
            
            const wrap = document.querySelector('.wrap');
            if (wrap) {
                wrap.insertBefore(notice, wrap.firstChild);
                
                // Auto-ocultar después de 5 segundos
                setTimeout(() => {
                    notice.style.opacity = '0';
                    notice.style.transition = 'opacity 0.5s';
                    setTimeout(() => notice.remove(), 500);
                }, 5000);
            }
        }

        // Permitir cambio de página con Enter en el input
        document.addEventListener('DOMContentLoaded', function() {
            const pageInput = document.getElementById('current-page-selector');
            if (pageInput) {
                pageInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const page = parseInt(this.value);
                        const totalPages = parseInt(document.querySelector('.total-pages').textContent);
                        if (page >= 1 && page <= totalPages) {
                            const url = new URL(window.location.href);
                            url.searchParams.set('paged', page);
                            window.location.href = url.toString();
                        }
                    }
                });
            }
        });

        // ========== CAMBIAR RESULTADOS POR PÁGINA ==========
        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.delete('paged'); // Reset a página 1
            window.location.href = url.toString();
        }

        // ========== REGENERAR CACHÉ POR LOTES ==========
        (function(){
            const batchSize = 20;
            const minDelay = 1200;
            const maxDelay = 2400;
            const ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            const nonce = '<?php echo esc_js( $ajax_nonce ); ?>';
            let running = false;
            
            const regenButton = document.getElementById('pvm-regenerate-button');
            if (regenButton) {
                regenButton.addEventListener('click', function(){
                    if ( running ) return;
                    if ( ! confirm('Iniciar regeneración por lotes?') ) return;
                    running = true;
                    this.disabled = true;
                    runBatch(0);
                });
            }

            function runBatch(offset) {
                document.getElementById('pvm-regenerate-status').textContent = 'Procesando offset ' + offset;
                const form = new FormData();
                form.append('action', 'pvm_regenerate_cache_batch');
                form.append('offset', offset);
                form.append('limit', batchSize);
                form.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
                .then(r => r.json())
                .then(data => {
                    if ( ! data || ! data.success ) {
                        document.getElementById('pvm-regenerate-status').textContent = 'Error: ' + JSON.stringify(data);
                        running = false;
                        document.getElementById('pvm-regenerate-button').disabled = false;
                        return;
                    }
                    const d = data.data;
                    document.getElementById('pvm-regenerate-status').textContent = 'Procesados ' + d.processed + ' de ' + d.total + (d.finished ? ' — Finalizado' : '');
                    if ( d.finished ) {
                        running = false;
                        document.getElementById('pvm-regenerate-button').disabled = false;
                        return;
                    }
                    const delay = Math.floor(Math.random()*(maxDelay-minDelay))+minDelay;
                    setTimeout(function(){ runBatch(d.next_offset); }, delay);
                }).catch(err=>{
                    console.error('Error en regeneración:', err);
                    document.getElementById('pvm-regenerate-status').textContent = 'Error en petición: ' + err;
                    running = false;
                    document.getElementById('pvm-regenerate-button').disabled = false;
                });
            }
        })();
        </script>
        <?php
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            product_name varchar(255) NOT NULL,
            manual_slug varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id),
            KEY manual_slug (manual_slug)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    private function save_manual_slug( $product_id, $manual_slug ) {
        global $wpdb;
        $product = get_post( $product_id );
        if ( ! $product ) return false;
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" );
        if ( ! $table_exists ) $this->create_table();

        $result = $wpdb->replace(
            $this->table_name,
            array(
                'product_id' => $product_id,
                'product_name' => $product->post_title,
                'manual_slug' => $manual_slug
            ),
            array( '%d', '%s', '%s' )
        );
        if ( $result === false ) error_log( 'PVM Error: ' . $wpdb->last_error );
        return $result !== false;
    }

    private function delete_manual_slug( $product_id ) {
        global $wpdb;
        $wpdb->delete( $this->table_name, array( 'product_id' => $product_id ), array( '%d' ) );
    }

    private function get_manual_slug( $product_id ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT manual_slug FROM {$this->table_name} WHERE product_id = %d", $product_id ) );
    }

    private function get_all_manual_slugs() {
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT product_id, manual_slug FROM {$this->table_name}", ARRAY_A );
        $manuals = array();
        if ( is_array( $rows ) ) {
            foreach ( $rows as $r ) $manuals[ intval( $r['product_id'] ) ] = $r['manual_slug'];
        }
        return $manuals;
    }

    private function get_products_with_slugs( $search = '', $offset = 0, $limit = -1 ) {
        $manuals = $this->get_all_manual_slugs();

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => 'version-producto-externoafiliado', 'compare' => 'EXISTS' ),
                array( 'key' => '_product_url', 'compare' => 'EXISTS' )
            )
        );

        if ( $limit > 0 ) {
            $args['posts_per_page'] = $limit;
            $args['offset'] = $offset;
        } else {
            $args['posts_per_page'] = -1;
        }

        if ( $search ) $args['s'] = $search;

        $ids = get_posts( $args );
        $result = array();

        foreach ( $ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) continue;

            $version = get_post_meta( $post_id, 'version-producto-externoafiliado', true );
            $download_url = get_post_meta( $post_id, '_product_url', true );

            if ( empty( $version ) ) continue; // aceptamos productos sin download_url, solo version

            $manual_slug = isset( $manuals[ $post_id ] ) ? $manuals[ $post_id ] : '';
            $auto_slug = $this->generate_auto_slug( $post->post_title );

            $result[] = array(
                'id' => $post_id,
                'name' => $post->post_title,
                'version' => ltrim( $version, 'vV' ),
                'auto_slug' => $auto_slug,
                'manual_slug' => $manual_slug,
                'final_slug' => $manual_slug ?: $auto_slug,
                'download_url' => $download_url
            );
        }

        return $result;
    }

    private function get_total_products_count() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => 'version-producto-externoafiliado', 'compare' => 'EXISTS' ),
                array( 'key' => '_product_url', 'compare' => 'EXISTS' )
            )
        );

        $q = new WP_Query( $args );
        $total = isset( $q->found_posts ) ? intval( $q->found_posts ) : 0;
        wp_reset_postdata();
        return $total;
    }

    /**
     * Obtener total de productos con filtro de búsqueda
     */
    private function get_total_products_count_with_search( $search = '' ) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => 'version-producto-externoafiliado', 'compare' => 'EXISTS' ),
                array( 'key' => '_product_url', 'compare' => 'EXISTS' )
            )
        );
        
        if ( $search ) {
            $args['s'] = $search;
        }

        $q = new WP_Query( $args );
        $total = isset( $q->found_posts ) ? intval( $q->found_posts ) : 0;
        wp_reset_postdata();
        return $total;
    }

    public function ajax_regenerate_cache_batch() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permiso denegado' );

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'pvm_regenerate_nonce' ) ) wp_send_json_error( 'Nonce inválido' );

        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 20;
        if ( $limit <= 0 ) $limit = 20;

        if ( ! get_transient( $this->running_transient_key ) ) {
            set_transient( $this->running_transient_key, 1, 30 * MINUTE_IN_SECONDS );
            if ( file_exists( $this->cache_file ) ) {
                $content = @file_get_contents( $this->cache_file );
                $existing = $content ? json_decode( $content, true ) : false;
                if ( is_array( $existing ) && isset( $existing['plugins'] ) ) {
                    set_transient( $this->build_transient_key, $existing, 30 * MINUTE_IN_SECONDS );
                } else {
                    set_transient( $this->build_transient_key, array( 'last_update' => current_time( 'timestamp' ), 'plugins' => array() ), 30 * MINUTE_IN_SECONDS );
                }
            } else {
                set_transient( $this->build_transient_key, array( 'last_update' => current_time( 'timestamp' ), 'plugins' => array() ), 30 * MINUTE_IN_SECONDS );
            }
        }

        $result = $this->regenerate_cache_batch( $offset, $limit );
        if ( ! is_array( $result ) ) {
            delete_transient( $this->running_transient_key );
            delete_transient( $this->build_transient_key );
            wp_send_json_error( 'Error al procesar batch' );
        }

        if ( isset( $result['finished'] ) && $result['finished'] ) {
            delete_transient( $this->running_transient_key );
            delete_transient( $this->build_transient_key );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Guardar slug manual sin recargar página
     */
    public function ajax_save_manual_slug() {
        // Verificar permisos
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permiso denegado' ) );
        }
        
        // Verificar nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'pvm_save_slug_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
        }
        
        // Obtener datos
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $manual_slug = isset( $_POST['manual_slug'] ) ? sanitize_title( wp_unslash( $_POST['manual_slug'] ) ) : '';
        
        // Validar datos - slug vacío no es permitido (usar delete_manual para eliminar)
        if ( ! $product_id || empty( $manual_slug ) ) {
            wp_send_json_error( array( 'message' => 'Datos inválidos' ) );
        }
        
        // Guardar en base de datos
        $result = $this->save_manual_slug( $product_id, $manual_slug );
        
        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Error al guardar en base de datos' ) );
        }
        
        // Regenerar caché completo para mantener sincronización del JSON
        // Nota: Es necesario para que el archivo plugin-versions-cache.json refleje los cambios
        $this->regenerate_full_cache();
        
        // Obtener datos actualizados del producto
        $product = get_post( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Producto no encontrado' ) );
        }
        
        $version = get_post_meta( $product_id, 'version-producto-externoafiliado', true );
        $auto_slug = $this->generate_auto_slug( $product->post_title );
        
        wp_send_json_success( array(
            'message' => 'Slug guardado correctamente',
            'product_id' => $product_id,
            'manual_slug' => $manual_slug,
            'auto_slug' => $auto_slug,
            'version' => ltrim( $version, 'vV' )
        ) );
    }

    public function regenerate_cache_batch( $offset = 0, $limit = 20 ) {
        $cache = get_transient( $this->build_transient_key );
        if ( ! is_array( $cache ) ) $cache = array( 'last_update' => current_time( 'timestamp' ), 'plugins' => array() );

        $total = $this->get_total_products_count();
        $products = $this->get_products_with_slugs( '', $offset, $limit );

        $processed_this_batch = 0;

        foreach ( $products as $product_data ) {
            $post_id = $product_data['id'];
            $download_url = $product_data['download_url'];
            $manual = $product_data['manual_slug'];
            $auto_slug = $product_data['auto_slug'];
            $final_slug = $product_data['final_slug'];
            $version = $product_data['version'];

            // generar identifiers
            $ident_data = $this->make_identifiers( $post_id, $auto_slug, $manual, $download_url );
            $identifiers = $ident_data['identifiers'];

            $cache['plugins'][ $final_slug ] = array(
                'name' => $product_data['name'],
                'slug' => $final_slug,
                'version' => $version,
                'identifiers' => $identifiers,
                'info_url' => get_permalink( $post_id ),
                'updated' => current_time( 'timestamp' )
            );

            $processed_this_batch++;
        }

        $cache['last_update'] = current_time( 'timestamp' );
        set_transient( $this->build_transient_key, $cache, 30 * MINUTE_IN_SECONDS );

        $next_offset = $offset + $limit;
        $finished = ( $next_offset >= $total );

        if ( $finished ) {
            $json = json_encode( $cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $tmp = $this->cache_file . '.tmp';
            if ( @file_put_contents( $tmp, $json, LOCK_EX ) !== false ) {
                @rename( $tmp, $this->cache_file );
            } else {
                return array(
                    'processed' => min( $next_offset, $total ),
                    'total' => $total,
                    'next_offset' => $next_offset,
                    'finished' => false,
                    'processed_this_batch' => $processed_this_batch,
                    'error' => 'Fallo al escribir fichero final'
                );
            }
        }

        return array(
            'processed' => min( $next_offset, $total ),
            'total' => $total,
            'next_offset' => $next_offset,
            'finished' => $finished,
            'processed_this_batch' => $processed_this_batch
        );
    }

    public function regenerate_full_cache() {
        if ( get_transient( $this->running_transient_key ) ) return;
        set_transient( $this->running_transient_key, 1, 30 * MINUTE_IN_SECONDS );

        $manuals = $this->get_all_manual_slugs();

        $cache = array(
            'last_update' => current_time( 'timestamp' ),
            'plugins' => array()
        );

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => 'version-producto-externoafiliado', 'compare' => 'EXISTS' ),
                array( 'key' => '_product_url', 'compare' => 'EXISTS' )
            )
        );

        $ids = get_posts( $args );

        foreach ( $ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) continue;

            $version = get_post_meta( $post_id, 'version-producto-externoafiliado', true );
            $download_url = get_post_meta( $post_id, '_product_url', true );

            if ( empty( $version ) ) continue;

            $manual_slug = isset( $manuals[ $post_id ] ) ? $manuals[ $post_id ] : '';
            $auto_slug = $this->generate_auto_slug( $post->post_title );
            $final_slug = $manual_slug ?: $auto_slug;

            $ident_data = $this->make_identifiers( $post_id, $auto_slug, $manual_slug, $download_url );
            $identifiers = $ident_data['identifiers'];

            $cache['plugins'][ $final_slug ] = array(
                'name' => $post->post_title,
                'slug' => $final_slug,
                'version' => ltrim( $version, 'vV' ),
                'identifiers' => $identifiers,
                'info_url' => get_permalink( $post_id ),
                'updated' => current_time( 'timestamp' )
            );
        }

        $json = json_encode( $cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $tmp = $this->cache_file . '.tmp';
        @file_put_contents( $tmp, $json, LOCK_EX );
        @rename( $tmp, $this->cache_file );

        delete_transient( $this->running_transient_key );
        delete_transient( $this->build_transient_key );
    }

    /**
     * Crea identifiers y devuelve tambien download_basename limpio (pero no lo exportamos en JSON)
     */
    private function make_identifiers( $post_id, $auto_slug, $manual_slug, $download_url ) {
        $ids = array();
        $download_basename = '';

        if ( ! empty( $manual_slug ) ) $ids[] = sanitize_title( $manual_slug );
        if ( ! empty( $auto_slug ) ) $ids[] = sanitize_title( $auto_slug );

        if ( ! empty( $download_url ) ) {
            $path = parse_url( $download_url, PHP_URL_PATH );
            $basename = $path ? basename( $path ) : '';
            $basename_noext = preg_replace( '/\.(zip|tar\.gz|tgz|rar|gz|7z)$/i', '', $basename );
            // quitar sufijos de version al final
            $basename_clean = preg_replace( '/(-v?\d+(\.\d+){0,})$/i', '', $basename_noext );
            $basename_clean = preg_replace( '/(\.\d+(\.\d+){0,})$/i', '', $basename_clean );
            $basename_clean = preg_replace( '/(-download|-latest|-stable|-release)$/i', '', $basename_clean );
            $basename_clean = preg_replace( '/[^a-zA-Z0-9\-]/', '-', $basename_clean );
            $basename_clean = trim( strtolower( $basename_clean ), '-' );
            if ( $basename_clean ) {
                $ids[] = $basename_clean;
                $download_basename = $basename_clean;
            }
        }

        $post = get_post( $post_id );
        if ( $post ) {
            $title_slug = sanitize_title( $post->post_title );
            if ( $title_slug ) $ids[] = $title_slug;
        }

        // normalizar, quitar vacíos y duplicados
        $ids = array_map( 'trim', $ids );
        $ids = array_filter( $ids );
        $ids = array_unique( $ids );

        // lowercase
        $ids = array_map( 'strtolower', $ids );

        return array( 'identifiers' => array_values( $ids ), 'download_basename' => $download_basename );
    }

    private function generate_auto_slug( $product_name ) {
        $known_plugins = array(
            'automatewoo' => array( 'automatewoo' ),
            'elementor' => array( 'elementor' ),
            'elementor-pro' => array( 'elementor', 'pro' ),
            'brizy-pro' => array( 'brizy', 'pro' ),
            'brizy' => array( 'brizy' ),
            'yith' => array( 'yith' ),
            'woocommerce' => array( 'woocommerce' ),
            'wp-rocket' => array( 'wp', 'rocket' ),
            'slider-revolution' => array( 'slider', 'revolution' ),
            'learndash' => array( 'learndash' ),
            'buddyboss' => array( 'buddyboss' ),
            'kadence' => array( 'kadence' ),
            'astra' => array( 'astra' ),
            'divi' => array( 'divi' ),
            'avada' => array( 'avada' ),
            'wpml' => array( 'wpml' ),
            'acf-pro' => array( 'acf', 'pro' ),
            'gravity-forms' => array( 'gravity', 'forms' ),
            'ninja-forms' => array( 'ninja', 'forms' ),
            'contact-form-7' => array( 'contact', 'form', '7' ),
            'wpforms' => array( 'wpforms' ),
            'jetpack' => array( 'jetpack' ),
            'wordfence' => array( 'wordfence' ),
            'updraftplus' => array( 'updraftplus' ),
            'rank-math' => array( 'rank', 'math' ),
            'seopress' => array( 'seopress' ),
            'wp-optimize' => array( 'wp', 'optimize' ),
            'smush' => array( 'smush' ),
            'wp-mail-smtp' => array( 'wp', 'mail', 'smtp' ),
            'mailpoet' => array( 'mailpoet' ),
            'mailchimp' => array( 'mailchimp' ),
            'woocommerce-subscriptions' => array( 'woocommerce', 'subscriptions' ),
            'polylang' => array( 'polylang' ),
            'translatepress' => array( 'translatepress' ),
            'loco-translate' => array( 'loco', 'translate' ),
            'beaver-builder' => array( 'beaver', 'builder' ),
            'oxygen' => array( 'oxygen' ),
            'bricks' => array( 'bricks' ),
            'generatepress' => array( 'generatepress' ),
            'oceanwp' => array( 'oceanwp' ),
            'flatsome' => array( 'flatsome' ),
            'the7' => array( 'the7' ),
            'bridge' => array( 'bridge' ),
            'enfold' => array( 'enfold' ),
            'salient' => array( 'salient' ),
            'x-theme' => array( 'x', 'theme' ),
            'betheme' => array( 'betheme' ),
            'porto' => array( 'porto' ),
            'woodmart' => array( 'woodmart' ),
            'shopkeeper' => array( 'shopkeeper' ),
            'storefront' => array( 'storefront' )
        );

        $name_lower = strtolower( $product_name );

        foreach ( $known_plugins as $slug => $keywords ) {
            $match_count = 0;
            foreach ( $keywords as $keyword ) {
                if ( strpos( $name_lower, $keyword ) !== false ) $match_count++;
            }
            if ( $match_count === count( $keywords ) ) return $slug;
        }

        $words = preg_split( '/[\s\-_:,]+/', $name_lower );
        $ignore = array( 'plugin', 'theme', 'pro', 'premium', 'wordpress', 'wp', 'para', 'de', 'la', 'el', 'en', 'y', 'con', 'sin', 'por', 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for' );

        $significant_words = array();
        foreach ( $words as $word ) {
            $word = trim( $word );
            if ( strlen( $word ) > 2 && ! in_array( $word, $ignore ) && ! is_numeric( $word ) ) $significant_words[] = $word;
            if ( count( $significant_words ) >= 3 ) break;
        }

        return ! empty( $significant_words ) ? implode( '-', $significant_words ) : sanitize_title( substr( $product_name, 0, 30 ) );
    }

    public function update_cache_on_product_save( $post_id, $post, $update ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
        if ( $post->post_status !== 'publish' ) return;
        $this->regenerate_full_cache();
    }
}

// Inicializar
new Plugin_Versions_Manager();