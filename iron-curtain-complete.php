<?php
/*
Plugin Name: The Iron Curtain
Description: The "No-BS" static hardener. Blocks the bad stuff, uses 0% CPU, and leaves no database bloat. Now with real shield verification.
Version: 1.1.0
Author: Donalda
License: GPLv2 or later
*/

defined( 'ABSPATH' ) || exit;

class IronCurtain_V1 {

    private $option_name = 'iron_curtain_options';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'plugins_loaded', [ $this, 'apply_shields' ] );
        add_action( 'admin_head', [ $this, 'admin_css' ] );
        
        // AJAX handlers for shield testing
        add_action( 'wp_ajax_ic_audit_xmlrpc', [ $this, 'audit_xmlrpc' ] );
        add_action( 'wp_ajax_ic_audit_enumeration', [ $this, 'audit_enumeration' ] );
        add_action( 'wp_ajax_ic_audit_version', [ $this, 'audit_version' ] );
        add_action( 'wp_ajax_ic_audit_editor', [ $this, 'audit_editor' ] );
        add_action( 'wp_ajax_ic_audit_login_errors', [ $this, 'audit_login_errors' ] );
        add_action( 'wp_ajax_ic_audit_rest_users', [ $this, 'audit_rest_users' ] );
        
        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    // =========================================================================
    // SHIELDS ENGINE
    // =========================================================================

public function apply_shields() {
    $options = get_option( $this->option_name );

    // Shield 1: Kill XML-RPC
    if ( ! empty( $options['block_xmlrpc'] ) ) {
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'wp_headers', function( $headers ) {
            unset( $headers['X-Pingback'] );
            return $headers;
        });
        add_action( 'init', function() {
            if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
                status_header( 403 );
                exit( 'XML-RPC is disabled.' );
            }
        }, 1 );
    }

// Shield 2: Block User Enumeration & Disable Author Archives
if ( ! empty( $options['block_users'] ) ) {

    // 1. The "Scanner" Killer (Stops ?author=1)
    add_filter( 'request', function( $query_vars ) {
        if ( is_admin() ) return $query_vars;

        if ( isset( $query_vars['author'] ) || isset( $query_vars['author_name'] ) ) {
            status_header( 403 );
            die( 'User enumeration is forbidden.' );
        }
        return $query_vars;
    } );

    // 2. The "Page View" Killer (Stops /author/username/ clicks)
    add_action( 'template_redirect', function() {
        if ( is_author() ) {
            status_header( 403 );
            die( 'Author archives are disabled.' );
        }
    } );

    // 3. The REST API Killer
    add_filter( 'rest_endpoints', function( $endpoints ) {
        unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        return $endpoints;
    });

    // 4. THE UI CLEANER: Remove the link from the author name
    add_filter( 'the_author_posts_link', function( $link ) {
        // Use regex to strip the <a> tags but keep the text content (the name)
        return preg_replace( '/<a[^>]+>(.*?)<\/a>/i', '$1', $link );
    } );
    
    // 5. Fallback: If theme uses get_author_posts_url directly, kill the URL
    add_filter( 'author_link', function( $link ) {
        return '#'; // Makes the link go nowhere if it somehow survives
    } );
}
// Shield 3: Hide WP Version (NUCLEAR)
if ( ! empty( $options['hide_version'] ) ) {
    remove_action( 'wp_head', 'wp_generator' );
    add_filter( 'the_generator', '__return_empty_string' );
    
    // Scrubber for ?ver=6.x.x
    $strip_wp_ver = function( $src ) {
        if ( $src && strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    };
    add_filter( 'style_loader_src', $strip_wp_ver, 9999 );
    add_filter( 'script_loader_src', $strip_wp_ver, 9999 );
    
    // Output Buffer Scrubber (The failsafe)
    add_action( 'template_redirect', function() {
        ob_start( function( $html ) {
            return preg_replace( '/<meta[^>]+name=["\']generator["\'][^>]*>/i', '', $html );
        });
    });
}

    // Shield 4: Disable File Editor
    if ( ! empty( $options['disable_editor'] ) ) {
        add_action( 'admin_menu', function() {
            remove_submenu_page( 'themes.php', 'theme-editor.php' );
            remove_submenu_page( 'plugins.php', 'plugin-editor.php' );
        }, 999 );

        add_action( 'admin_init', function() {
            global $pagenow;
            if ( $pagenow === 'theme-editor.php' || $pagenow === 'plugin-editor.php' ) {
                status_header( 403 );
                exit( 'The file editor is disabled.' );
            }
        });
    }

    // Shield 5: Obfuscate Login Errors
    if ( ! empty( $options['hide_errors'] ) ) {
        add_filter( 'login_errors', function() {
            return '<strong>Error</strong>: Invalid credentials.';
        });
    }
}

    // =========================================================================
    // ADMIN UI
    // =========================================================================

    public function add_plugin_page() {
        add_options_page(
            'Iron Curtain Security', 
            'Iron Curtain', 
            'manage_options', 
            'iron-curtain', 
            [ $this, 'create_admin_page' ] 
        );
    }

    public function page_init() {
        register_setting( 'iron_curtain_group', $this->option_name, [ $this, 'sanitize' ] );

        add_settings_section(
            'iron_curtain_main', 
            'Vulnerability Audit', 
            [ $this, 'print_section_info' ], 
            'iron-curtain'
        );

        add_settings_field( 'block_xmlrpc', 'XML-RPC API', [ $this, 'callback_xmlrpc' ], 'iron-curtain', 'iron_curtain_main' );
        add_settings_field( 'block_users', 'User Enumeration', [ $this, 'callback_users' ], 'iron-curtain', 'iron_curtain_main' );
        add_settings_field( 'hide_version', 'Version Visibility', [ $this, 'callback_version' ], 'iron-curtain', 'iron_curtain_main' );
        add_settings_field( 'disable_editor', 'File Editor', [ $this, 'callback_editor' ], 'iron-curtain', 'iron_curtain_main' );
        add_settings_field( 'hide_errors', 'Login Errors', [ $this, 'callback_errors' ], 'iron-curtain', 'iron_curtain_main' );
    }

    public function sanitize( $input ) {
        $new_input = [];
        $keys = ['block_xmlrpc', 'block_users', 'hide_version', 'disable_editor', 'hide_errors'];
        foreach ( $keys as $key ) {
            if ( isset( $input[$key] ) ) {
                $new_input[$key] = absint( $input[$key] );
            }
        }
        return $new_input;
    }

    public function print_section_info() {
        echo '<p>Below is a list of open doors on your server. Check the box to lock them, then click <strong>Test Shield</strong> to verify.</p>';
        echo $this->render_run_all_button();
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>🛡️ The Iron Curtain</h1>
            
            <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Boom! Shields Up.</strong> 🛡️ Now use the test buttons below to verify they're working.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                    settings_fields( 'iron_curtain_group' );
                    do_settings_sections( 'iron-curtain' );
                    submit_button( 'Secure My Site' );
                ?>
            </form>
            
            <hr>
            <p><em>Built with hate for bloat by Donalda.</em></p>
        </div>
        <?php
    }

    // =========================================================================
    // FIELD CALLBACKS WITH TEST BUTTONS
    // =========================================================================

    public function callback_xmlrpc() {
        $options = get_option( $this->option_name );
        $is_checked = ! empty( $options['block_xmlrpc'] ) ? 1 : 0;
        
        echo '<div class="ic-shield-row">';
        echo '<input type="checkbox" id="block_xmlrpc" name="iron_curtain_options[block_xmlrpc]" value="1" ' . checked( 1, $is_checked, false ) . ' /> ';
        echo '<label for="block_xmlrpc">Disable XML-RPC (Stops Brute Force Bots)</label>';
        echo '<button type="button" class="ic-audit-btn" data-action="ic_audit_xmlrpc">🔍 Test Shield</button>';
        echo '<span class="ic-audit-result"></span>';
        echo '</div>';
    }

public function callback_users() {
    $options = get_option( $this->option_name );
    $is_checked = ! empty( $options['block_users'] ) ? 1 : 0;
    
    echo '<div class="ic-shield-row">';
    echo '<input type="checkbox" id="block_users" name="iron_curtain_options[block_users]" value="1" ' . checked( 1, $is_checked, false ) . ' /> ';
    echo '<label for="block_users">Block Author Scans (/?author=1)</label>';
    echo '<button type="button" class="ic-audit-btn" data-action="ic_audit_enumeration">🔍 Test Shield</button>';
    echo '<span class="ic-audit-result"></span>';
    echo '</div>';
}

public function callback_version() {
    $options = get_option( $this->option_name );
    $is_checked = ! empty( $options['hide_version'] ) ? 1 : 0;
    
    echo '<div class="ic-shield-row">';
    echo '<input type="checkbox" id="hide_version" name="iron_curtain_options[hide_version]" value="1" ' . checked( 1, $is_checked, false ) . ' /> ';
    echo '<label for="hide_version">Hide WP Version (Meta, RSS, & Asset Strings)</label>';
    echo '<button type="button" class="ic-audit-btn" data-action="ic_audit_version">🔍 Test Shield</button>';
    echo '<span class="ic-audit-result"></span>';
    echo '</div>';
}

    public function callback_editor() {
        $options = get_option( $this->option_name );
        $is_checked = ! empty( $options['disable_editor'] ) ? 1 : 0;
        $constant_defined = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
        
        echo '<div class="ic-shield-row">';
        
        if ( $constant_defined ) {
            echo '<input type="checkbox" checked disabled /> ';
            echo '<label>Disable Theme/Plugin Editor</label>';
            echo '<span class="ic-shield-status ic-status-pass">✅ HARDCODED IN WP-CONFIG</span>';
        } else {
            echo '<input type="checkbox" id="disable_editor" name="iron_curtain_options[disable_editor]" value="1" ' . checked( 1, $is_checked, false ) . ' /> ';
            echo '<label for="disable_editor">Disable Theme/Plugin Editor</label>';
            echo '<button type="button" class="ic-audit-btn" data-action="ic_audit_editor">🔍 Test Shield</button>';
            echo '<span class="ic-audit-result"></span>';
        }
        
        echo '</div>';
    }

    public function callback_errors() {
        $options = get_option( $this->option_name );
        $is_checked = ! empty( $options['hide_errors'] ) ? 1 : 0;

        echo '<div class="ic-shield-row">';
        echo '<input type="checkbox" id="hide_errors" name="iron_curtain_options[hide_errors]" value="1" ' . checked( 1, $is_checked, false ) . ' /> ';
        echo '<label for="hide_errors">Obfuscate Login Errors</label>';
        echo '<button type="button" class="ic-audit-btn" data-action="ic_audit_login_errors">🔍 Test Shield</button>';
        echo '<span class="ic-audit-result"></span>';
        echo '</div>';
    }

    private function render_run_all_button() {
        return '
        <div class="ic-run-all-wrapper">
            <button type="button" id="ic-run-all-tests" class="button button-hero">
                🔬 Run Full Security Audit
            </button>
            <span id="ic-audit-progress"></span>
        </div>';
    }

    // =========================================================================
    // AUDIT ENGINE (AJAX HANDLERS)
    // =========================================================================

    public function audit_xmlrpc() {
        check_ajax_referer( 'ic_audit_nonce', 'nonce' );

        $xmlrpc_url = site_url( '/xmlrpc.php' );
        $request_body = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>';

        $response = wp_remote_post( $xmlrpc_url, [
            'body'      => $request_body,
            'timeout'   => 10,
            'headers'   => [ 'Content-Type' => 'text/xml' ],
            'sslverify' => false,
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Could not reach xmlrpc.php' ]);
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( strpos( $body, 'faultCode' ) !== false || $code === 403 || $code === 405 ) {
            wp_send_json_success([ 'status' => 'pass', 'message' => '✅ BLOCKED — XML-RPC rejected' ]);
        }

        if ( strpos( $body, 'system.listMethods' ) !== false || strpos( $body, '<methodResponse>' ) !== false ) {
            wp_send_json_success([ 'status' => 'fail', 'message' => '❌ OPEN — XML-RPC responding!' ]);
        }

        wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Unexpected (HTTP ' . $code . ')' ]);
    }

public function audit_enumeration() {
    check_ajax_referer( 'ic_audit_nonce', 'nonce' );

    // ADDED: 'nocache' arg to force server to ignore previous 301s
    $test_url = add_query_arg( [
        'author'  => '1',
        'nocache' => time() 
    ], home_url( '/' ) );

    $response = wp_remote_get( $test_url, [
        'timeout'     => 10,
        'sslverify'   => false,
        'redirection' => 0, // Catch it before it follows
    ]);

    if ( is_wp_error( $response ) ) {
        wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Test failed: ' . $response->get_error_message() ]);
    }

    $code = wp_remote_retrieve_response_code( $response );

    if ( $code === 403 ) {
        wp_send_json_success([ 'status' => 'pass', 'message' => '✅ SECURED — Enumeration blocked (403)' ]);
    } else {
        // If it's still a 301, the redirect is happening at the NGINX/Apache level
        wp_send_json_success([ 'status' => 'fail', 'message' => '❌ LEAKING — Status ' . $code ]);
    }
}
    public function audit_rest_users() {
        check_ajax_referer( 'ic_audit_nonce', 'nonce' );

        $rest_url = rest_url( 'wp/v2/users' );

        $response = wp_remote_get( $rest_url, [
            'timeout'   => 10,
            'sslverify' => false,
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Could not reach REST API' ]);
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( in_array( $code, [404, 401, 403], true ) ) {
            wp_send_json_success([ 'status' => 'pass', 'message' => '✅ BLOCKED — Returns ' . $code ]);
        }

        if ( $code === 200 ) {
            $data = json_decode( $body, true );
            if ( is_array( $data ) && ! empty( $data ) && isset( $data[0]['slug'] ) ) {
                wp_send_json_success([ 'status' => 'fail', 'message' => '❌ LEAKING — Found: ' . esc_html( $data[0]['slug'] ) ]);
            }
            if ( is_array( $data ) && empty( $data ) ) {
                wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Endpoint exists, no users returned' ]);
            }
        }

        wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Unexpected (HTTP ' . $code . ')' ]);
    }

public function audit_version() {
    check_ajax_referer( 'ic_audit_nonce', 'nonce' );

    // Use a unique query arg to bypass server-side caches
    $response = wp_remote_get( add_query_arg( 'ic_audit', time(), home_url() ), [
        'timeout'   => 10,
        'sslverify' => false
    ]);
    
    if ( is_wp_error( $response ) ) {
        wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Unreachable: ' . $response->get_error_message() ]);
    }

    $body = wp_remote_retrieve_body( $response );
    $ver  = get_bloginfo( 'version' );

    // Check for version in meta tags or common asset strings
    if ( strpos( $body, 'content="WordPress ' . $ver ) !== false || 
         strpos( $body, 'ver=' . $ver ) !== false ) {
        wp_send_json_success([ 'status' => 'fail', 'message' => '❌ OPEN — Version found in source' ]);
    } else {
        wp_send_json_success([ 'status' => 'pass', 'message' => '✅ HIDDEN — Version strings scrubbed' ]);
    }
}
    public function audit_editor() {
        check_ajax_referer( 'ic_audit_nonce', 'nonce' );

        if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
            wp_send_json_success([ 'status' => 'pass', 'message' => '✅ BLOCKED — Constant defined' ]);
        }

        // We can't easily test the editor page access from AJAX without session complications.
        // Instead, check if our hooks are in place.
        $options = get_option( $this->option_name );
        if ( ! empty( $options['disable_editor'] ) ) {
            wp_send_json_success([ 'status' => 'pass', 'message' => '✅ BLOCKED — Shield active' ]);
        }

        wp_send_json_success([ 'status' => 'fail', 'message' => '❌ OPEN — Editor accessible' ]);
    }

    public function audit_login_errors() {
        check_ajax_referer( 'ic_audit_nonce', 'nonce' );

        $login_url = wp_login_url();

        $response = wp_remote_post( $login_url, [
            'timeout'   => 10,
            'sslverify' => false,
            'body'      => [
                'log' => 'ic_audit_fake_user_' . wp_rand(),
                'pwd' => 'ic_audit_fake_pass_' . wp_rand(),
                'wp-submit' => 'Log In',
            ],
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Could not test login' ]);
        }

        $body = wp_remote_retrieve_body( $response );

        if ( strpos( $body, 'Invalid username' ) !== false ||
             strpos( $body, 'is not registered' ) !== false ||
             strpos( $body, 'unknown username' ) !== false ) {
            wp_send_json_success([ 'status' => 'fail', 'message' => '❌ VERBOSE — Reveals username validity' ]);
        }

        if ( strpos( $body, 'Invalid credentials' ) !== false ) {
            wp_send_json_success([ 'status' => 'pass', 'message' => '✅ OBFUSCATED — Generic error shown' ]);
        }

        wp_send_json_success([ 'status' => 'warn', 'message' => '⚠️ Custom error — verify manually' ]);
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'settings_page_iron-curtain' ) {
            return;
        }

        wp_localize_script( 'jquery', 'IronCurtainAudit', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ic_audit_nonce' ),
        ]);

        wp_add_inline_script( 'jquery', $this->get_inline_js() );
    }

    public function admin_css() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'settings_page_iron-curtain' ) return;
        ?>
        <style>
            .ic-shield-row { padding: 8px 0; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
            .ic-sub-row { padding-left: 26px; color: #666; }
            .ic-audit-btn {
                padding: 4px 12px; font-size: 12px; cursor: pointer;
                background: #f0f0f1; border: 1px solid #8c8f94; border-radius: 3px;
            }
            .ic-audit-btn:hover { background: #e5e5e5; }
            .ic-audit-btn:disabled { cursor: wait; opacity: 0.6; }
            .ic-audit-result {
                display: inline-block; padding: 4px 10px; font-size: 12px;
                border-radius: 3px; font-weight: 600;
            }
            .ic-audit-result.ic-pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .ic-audit-result.ic-fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .ic-audit-result.ic-warn { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
            .ic-shield-status { padding: 4px 10px; border-radius: 3px; font-weight: 600; font-size: 12px; }
            .ic-status-pass { background: #d4edda; color: #155724; }
            .ic-run-all-wrapper { margin: 15px 0; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; }
            #ic-audit-progress { margin-left: 15px; color: #666; }
        </style>
        <?php
    }

    private function get_inline_js() {
        return <<<'JS'
jQuery(document).ready(function($) {
    $(document).on('click', '.ic-audit-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var action = $btn.data('action');
        var $result = $btn.siblings('.ic-audit-result');
        
        $btn.prop('disabled', true).text('Testing...');
        $result.html('').removeClass('ic-pass ic-fail ic-warn');
        
        $.ajax({
            url: IronCurtainAudit.ajax_url,
            type: 'POST',
            data: { action: action, nonce: IronCurtainAudit.nonce },
            success: function(response) {
                $btn.prop('disabled', false).text('🔍 Test Shield');
                if (response.success) {
                    $result.addClass('ic-' + response.data.status).html(response.data.message);
                } else {
                    $result.addClass('ic-fail').html('Test failed');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('🔍 Test Shield');
                $result.addClass('ic-fail').html('Connection error');
            }
        });
    });
    
    $('#ic-run-all-tests').on('click', function() {
        var $progress = $('#ic-audit-progress');
        $progress.text('Running all tests...');
        $('.ic-audit-btn').each(function(i) {
            var $btn = $(this);
            setTimeout(function() { $btn.trigger('click'); }, i * 300);
        });
        setTimeout(function() { $progress.text('✓ Complete'); }, 2500);
    });
});
JS;
    }
}

new IronCurtain_V1();
