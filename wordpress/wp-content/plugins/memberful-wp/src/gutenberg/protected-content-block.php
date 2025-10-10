<?php

/**
 * Memberful Protected Content Block
 * 
 * A Gutenberg block that wraps other blocks with Memberful protection
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Memberful_Protected_Content_Block {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
    }
    
    /**
     * Register the Protected Content block
     */
    public function register_block() {
        register_block_type('memberful/protected-content', array(
            'attributes' => array(
                'memberfulProtection' => array(
                    'type' => 'object',
                    'default' => array(
                        'enabled' => false,
                        'accessType' => 'subscription',
                        'requiredItems' => array(),
                        'unauthorizedAction' => 'hide',
                        'customMessage' => ''
                    )
                ),
                'className' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array($this, 'render_block'),
            'supports' => array(
                'align' => true,
                'alignWide' => true,
                'className' => true,
                'customClassName' => true
            )
        ));
    }
    
    /**
     * Render the Protected Content block
     */
    public function render_block($attributes, $content) {
        // If protection is not enabled, return the content
        if (!isset($attributes['memberfulProtection']) || 
            !$attributes['memberfulProtection']['enabled']) {
            return $content;
        }
        
        $protection_settings = $attributes['memberfulProtection'];
        $user_id = get_current_user_id();
        
        // Check if user has access
        $can_access = $this->check_access($user_id, $protection_settings);
        
        // Allow developers to override access check
        $can_access = apply_filters(
            'memberful_protected_content_block_access',
            $can_access,
            $user_id,
            $protection_settings,
            get_the_ID()
        );
        
        if ($can_access) {
            // Fire access granted event
            do_action('memberful_protected_content_access_granted', $user_id, $protection_settings, get_the_ID());
            return $content;
        }
        
        // Fire access denied event
        do_action('memberful_protected_content_access_denied', $user_id, $protection_settings, get_the_ID());
        
        // Handle unauthorized access
        return $this->handle_unauthorized_access($content, $protection_settings, $user_id);
    }
    
    /**
     * Check if user has access to the protected content
     */
    private function check_access($user_id, $protection_settings) {
        $access_type = $protection_settings['accessType'] ?? 'subscription';
        $required_items = $protection_settings['requiredItems'] ?? array();
        
        switch ($access_type) {
            case 'subscription':
                if (empty($required_items)) {
                    return !empty(memberful_wp_user_plans_subscribed_to($user_id));
                }
                return memberful_wp_user_has_subscription_to_plans($user_id, $required_items);
                
            case 'product':
                if (empty($required_items)) {
                    return !empty(memberful_wp_user_downloads($user_id));
                }
                return memberful_wp_user_has_downloads($user_id, $required_items);
                
            case 'registered':
                return $user_id > 0;
                
            case 'public':
                return true;
                
            default:
                // Allow developers to add custom access types
                return apply_filters(
                    'memberful_custom_protected_content_access_type_' . $access_type,
                    false,
                    $user_id,
                    $required_items
                );
        }
    }
    
    /**
     * Handle unauthorized access to protected content
     */
    private function handle_unauthorized_access($content, $protection_settings, $user_id) {
        $unauthorized_action = $protection_settings['unauthorizedAction'] ?? 'hide';
        $custom_message = $protection_settings['customMessage'] ?? '';
        
        // Allow developers to provide custom unauthorized content
        $unauthorized_content = apply_filters(
            'memberful_protected_content_unauthorized_content',
            '',
            $content,
            $protection_settings,
            $user_id
        );
        
        if (!empty($unauthorized_content)) {
            return $unauthorized_content;
        }
        
        switch ($unauthorized_action) {
            case 'hide':
                return '';
                
            case 'message':
                return $this->render_unauthorized_message($custom_message);
                
            case 'login_form':
                return $this->render_login_form();
                
            case 'login_form_and_message':
                return $this->render_login_form() . $this->render_unauthorized_message($custom_message);
                
            default:
                // Allow developers to add custom unauthorized actions
                return apply_filters(
                    'memberful_custom_protected_content_unauthorized_action_' . $unauthorized_action,
                    $content,
                    $protection_settings,
                    $user_id
                );
        }
    }
    
    /**
     * Render unauthorized access message
     */
    private function render_unauthorized_message($message) {
        if (empty($message)) {
            $message = __('This content is available to members only.', 'memberful');
        }
        
        $message = apply_filters('memberful_protected_content_unauthorized_message', $message);
        
        return '<div class="memberful-protected-content-message">' . 
               esc_html($message) . 
               '</div>';
    }
    
    /**
     * Render login form
     */
    private function render_login_form() {
        $login_form = apply_filters('memberful_protected_content_login_form', '');
        
        if (!empty($login_form)) {
            return $login_form;
        }
        
        // Use existing Memberful login form or create a simple one
        return '<div class="memberful-protected-content-login">' . 
               do_shortcode('[memberful_sign_in]') . 
               '</div>';
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'memberful-protected-content-block',
            plugins_url('js/gutenberg-protected-content-block.js', MEMBERFUL_PLUGIN_FILE),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            MEMBERFUL_VERSION
        );
        
        wp_enqueue_style(
            'memberful-protected-content-block',
            plugins_url('stylesheets/gutenberg-protected-content-block.css', MEMBERFUL_PLUGIN_FILE),
            array(),
            MEMBERFUL_VERSION
        );
        
        // Localize script with Memberful data
        wp_localize_script('memberful-protected-content-block', 'memberfulProtectedContent', array(
            'nonce' => wp_create_nonce('memberful_protected_content'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'settings' => $this->get_default_settings(),
            'subscriptions' => $this->get_available_subscriptions(),
            'products' => $this->get_available_products()
        ));
    }
    
    /**
     * Get default settings for the block
     */
    private function get_default_settings() {
        return apply_filters('memberful_protected_content_default_settings', array(
            'accessTypes' => array(
                array('value' => 'public', 'label' => __('Public', 'memberful')),
                array('value' => 'registered', 'label' => __('Registered Users', 'memberful')),
                array('value' => 'subscription', 'label' => __('Subscribers', 'memberful')),
                array('value' => 'product', 'label' => __('Product Owners', 'memberful'))
            ),
            'unauthorizedActions' => array(
                array('value' => 'hide', 'label' => __('Hide Content', 'memberful')),
                array('value' => 'message', 'label' => __('Show Message', 'memberful')),
                array('value' => 'login_form', 'label' => __('Show Login Form', 'memberful')),
                array('value' => 'login_form_and_message', 'label' => __('Show Login Form & Message', 'memberful'))
            )
        ));
    }
    
    /**
     * Get available subscriptions
     */
    private function get_available_subscriptions() {
        $subscriptions = get_option('memberful_subscriptions', array());
        $formatted = array();
        
        foreach ($subscriptions as $id => $subscription) {
            $formatted[] = array(
                'id' => $id,
                'name' => $subscription['name'] ?? "Subscription {$id}"
            );
        }
        
        return apply_filters('memberful_protected_content_available_subscriptions', $formatted);
    }
    
    /**
     * Get available products
     */
    private function get_available_products() {
        $products = get_option('memberful_products', array());
        $formatted = array();
        
        foreach ($products as $id => $product) {
            $formatted[] = array(
                'id' => $id,
                'name' => $product['name'] ?? "Product {$id}"
            );
        }
        
        return apply_filters('memberful_protected_content_available_products', $formatted);
    }
}

// Initialize the Protected Content block
Memberful_Protected_Content_Block::instance();
