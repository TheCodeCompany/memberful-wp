<?php

/**
 * Memberful Gutenberg Block Protection
 * 
 * Provides block-level content protection for Gutenberg blocks
 * with extensible hooks for third-party developers.
 */

error_log('=== MEMBERFUL BLOCK PROTECTION FILE LOADED ===');

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Memberful_Block_Protection {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        error_log('=== MEMBERFUL BLOCK PROTECTION CLASS INITIALIZED ===');
        $this->init_hooks();
    }
    
    private function init_hooks() {
        error_log('=== MEMBERFUL BLOCK PROTECTION HOOKS INITIALIZED ===');
        
        // Test hook to see if the class is working
        add_action('init', array($this, 'test_init'), 1);
        
        // Register block attributes on the server side
        add_action('init', array($this, 'register_block_attributes'), 999);
        
        // Frontend content filtering - use the_content filter as backup
        add_filter('render_block', array($this, 'filter_protected_blocks'), 10, 2);
        add_filter('the_content', array($this, 'filter_protected_content'), 10, 1);
        
        // Admin scripts
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        
        // Debug post saving
        add_action('save_post', array($this, 'debug_post_save'), 10, 2);
        
        error_log('=== MEMBERFUL BLOCK PROTECTION HOOKS ADDED ===');
    }
    
    public function test_init() {
        error_log('=== MEMBERFUL BLOCK PROTECTION INIT HOOK CALLED ===');
    }
    
    public function debug_post_save($post_id, $post) {
        error_log('=== POST SAVED ===');
        error_log('Post ID: ' . $post_id);
        error_log('Post content: ' . substr($post->post_content, 0, 500));
        
        // Parse blocks and check for memberfulProtection
        $blocks = parse_blocks($post->post_content);
        foreach ($blocks as $block) {
            if (isset($block['attrs']['memberfulProtection'])) {
                error_log('Found memberfulProtection in saved post: ' . print_r($block['attrs']['memberfulProtection'], true));
            }
        }
    }
    
    /**
     * Filter protected blocks on the frontend
     */
    public function filter_protected_blocks($block_content, $block) {
        // Debug: Log that the filter is being called
        error_log('=== RENDER_BLOCK FILTER CALLED ===');
        error_log('Block name: ' . $block['blockName']);
        error_log('Is admin: ' . (is_admin() ? 'YES' : 'NO'));
        error_log('Block attrs: ' . print_r($block['attrs'], true));
        
        // Skip if in admin area (but allow frontend for all users)
        if (is_admin()) {
            error_log('Skipping admin area');
            return $block_content;
        }
        
        
        // Debug: Log all block attributes
        error_log('=== MEMBERFUL BLOCK PROTECTION DEBUG ===');
        error_log('Block name: ' . $block['blockName']);
        error_log('Block attrs: ' . print_r($block['attrs'], true));
        
        // Check if block has Memberful protection attributes
        if (!isset($block['attrs']['memberfulProtection']) || 
            !$block['attrs']['memberfulProtection']['enabled']) {
            error_log('No protection enabled for this block');
            return $block_content;
        }
        
        $protection_settings = $block['attrs']['memberfulProtection'];
        $user_id = get_current_user_id();
        
        error_log('Protection settings: ' . print_r($protection_settings, true));
        error_log('User ID: ' . $user_id);
        
        // Allow developers to override access check
        $can_access = apply_filters(
            'memberful_override_block_access_check', 
            null, 
            $user_id, 
            $protection_settings, 
            get_the_ID()
        );
        
        if ($can_access === null) {
            $can_access = $this->check_block_access($user_id, $protection_settings);
        }
        
        // Allow developers to modify the access decision
        $can_access = apply_filters(
            'memberful_can_user_access_block', 
            $can_access, 
            $user_id, 
            $protection_settings, 
            get_the_ID()
        );
        
        error_log('Can access: ' . ($can_access ? 'YES' : 'NO'));
        
        if ($can_access) {
            error_log('User has access, showing content');
            return $block_content;
        }
        
        error_log('User does not have access, hiding content');
        // Handle unauthorized access
        return $this->handle_unauthorized_access($block_content, $protection_settings, $user_id);
    }
    
    /**
     * Filter protected content using the_content filter as backup
     */
    public function filter_protected_content($content) {
        // Skip if in admin area
        if (is_admin()) {
            return $content;
        }
        
        error_log('=== THE_CONTENT FILTER CALLED ===');
        
        // Parse blocks from content
        $blocks = parse_blocks($content);
        if (empty($blocks)) {
            return $content;
        }
        
        $filtered_content = '';
        foreach ($blocks as $block) {
            if (isset($block['blockName']) && isset($block['attrs']['memberfulProtection'])) {
                error_log('Found protected block in the_content: ' . $block['blockName']);
                $block_content = render_block($block);
                $filtered_content .= $this->filter_protected_blocks($block_content, $block);
            } else {
                $filtered_content .= render_block($block);
            }
        }
        
        return $filtered_content;
    }
    
    /**
     * Check if user has access to the block using Memberful's existing ACL system
     */
    private function check_block_access($user_id, $protection_settings) {
        $access_type = $protection_settings['accessType'] ?? 'subscription';
        $required_items = $protection_settings['requiredItems'] ?? array();
        
        // Use Memberful's existing access control logic
        switch ($access_type) {
            case 'subscription':
                if (empty($required_items)) {
                    // Any subscription - use Memberful's existing logic
                    $user_subs = $user_id ? array_keys(memberful_wp_user_plans_subscribed_to($user_id)) : array();
                    return !empty($user_subs);
                }
                // Specific plans - use Memberful's existing logic
                return memberful_wp_user_has_subscription_to_plans($user_id, $required_items);
                
            case 'product':
                if (empty($required_items)) {
                    // Any product - use Memberful's existing logic
                    $user_products = $user_id ? array_keys(memberful_wp_user_products($user_id)) : array();
                    return !empty($user_products);
                }
                // Specific products - use Memberful's existing logic
                return memberful_wp_user_has_downloads($user_id, $required_items);
                
            case 'registered':
                // Any registered user
                return $user_id > 0;
                
            default:
                // Allow developers to add custom access types
                return apply_filters(
                    'memberful_custom_access_type_' . $access_type,
                    false,
                    $user_id,
                    $required_items
                );
        }
    }
    
    /**
     * Handle unauthorized access to blocks
     */
    private function handle_unauthorized_access($block_content, $protection_settings, $user_id) {
        $unauthorized_action = $protection_settings['unauthorizedAction'] ?? 'hide';
        
        // Allow developers to modify unauthorized content
        $unauthorized_content = apply_filters(
            'memberful_unauthorized_block_content',
            '',
            $block_content,
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
                $message = apply_filters('memberful_unauthorized_message', 
                    __('This content is restricted. Please upgrade your membership to access this content.', 'memberful-wp'),
                    $block_content,
                    $protection_settings,
                    $user_id
                );
                return '<div class="memberful-unauthorized-message" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 10px 0; color: #856404; text-align: center;">' . esc_html($message) . '</div>';
                
            case 'login_form':
                $login_url = memberful_sign_in_url();
                $login_text = apply_filters('memberful_login_text', 
                    __('Please sign in to access this content.', 'memberful-wp'),
                    $block_content,
                    $protection_settings,
                    $user_id
                );
                return '<div class="memberful-login-prompt" style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 15px; margin: 10px 0; text-align: center;"><a href="' . esc_url($login_url) . '" style="color: #007cba; text-decoration: none; font-weight: 600; padding: 8px 16px; background: #007cba; color: white; border-radius: 4px; display: inline-block;">' . esc_html($login_text) . '</a></div>';
                
            default:
                return apply_filters('memberful_custom_unauthorized_action', '', $block_content, $protection_settings, $user_id, $unauthorized_action);
        }
    }
    
    
    
    
    
    /**
     * Register block attributes on the server side
     */
    public function register_block_attributes() {
        $block_types = $this->get_protectable_block_types();
        
        foreach ($block_types as $block_type) {
            if (WP_Block_Type_Registry::get_instance()->is_registered($block_type)) {
                $block = WP_Block_Type_Registry::get_instance()->get_registered($block_type);
                
                if (!isset($block->attributes)) {
                    $block->attributes = array();
                }
                
                $block->attributes['memberfulProtection'] = array(
                    'type' => 'object',
                    'default' => array(
                        'enabled' => false,
                        'accessType' => 'subscription',
                        'requiredItems' => array(),
                        'unauthorizedAction' => 'hide'
                    )
                );
                
                error_log('Registered memberfulProtection attribute for: ' . $block_type);
            }
        }
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'memberful-block-protection',
            plugins_url('js/gutenberg-block-protection-simple-v1.js', MEMBERFUL_PLUGIN_FILE),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-plugins', 'wp-block-editor'),
            MEMBERFUL_VERSION
        );
        
        wp_enqueue_style(
            'memberful-block-protection',
            plugins_url('stylesheets/gutenberg-block-protection.css', MEMBERFUL_PLUGIN_FILE),
            array(),
            MEMBERFUL_VERSION
        );
        
        // Localize script with Memberful data
        wp_localize_script('memberful-block-protection', 'memberfulBlockProtection', array(
            'subscriptions' => $this->get_available_subscriptions(),
            'products' => $this->get_available_products(),
            'protectableBlocks' => $this->get_protectable_block_types()
        ));
    }
    
    /**
     * Get all protectable block types dynamically
     */
    private function get_protectable_block_types() {
        $all_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $non_protectable_blocks = array(
            'core/button',
            'core/buttons', 
            'core/separator',
            'core/spacer',
            'core/more',
            'core/nextpage',
            'core/block',
            'core/legacy-widget',
            'core/widget-group',
            'core/navigation',
            'core/navigation-link',
            'core/navigation-submenu',
            'core/site-logo',
            'core/site-title',
            'core/site-tagline',
            'core/loginout',
            'core/home-link'
        );
        
        $protectable_blocks = array();
        foreach ($all_blocks as $block_name => $block_type) {
            if (!in_array($block_name, $non_protectable_blocks)) {
                $protectable_blocks[] = $block_name;
            }
        }
        
        // Allow developers to filter protectable blocks
        return apply_filters('memberful_protectable_block_types', $protectable_blocks);
    }
    
    /**
     * Get available subscriptions for block protection
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
        
        return apply_filters('memberful_available_subscriptions_for_blocks', $formatted);
    }
    
    /**
     * Get available products for block protection
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
        
        return apply_filters('memberful_available_products_for_blocks', $formatted);
    }
    
    /**
     * AJAX handler for saving block protection settings
     */
    public function save_block_protection() {
        check_ajax_referer('memberful_block_protection', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        
        $post_id = intval($_POST['post_id']);
        $block_id = sanitize_text_field($_POST['block_id']);
        $protection_settings = $_POST['protection_settings'];
        
        // Sanitize protection settings
        $protection_settings = $this->sanitize_protection_settings($protection_settings);
        
        // Save to post meta
        $block_protections = get_post_meta($post_id, '_memberful_block_protections', true) ?: array();
        $block_protections[$block_id] = $protection_settings;
        update_post_meta($post_id, '_memberful_block_protections', $block_protections);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for getting block protection settings
     */
    public function get_block_protection() {
        check_ajax_referer('memberful_block_protection', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        
        $post_id = intval($_GET['post_id']);
        $block_id = sanitize_text_field($_GET['block_id']);
        
        $block_protections = get_post_meta($post_id, '_memberful_block_protections', true) ?: array();
        $protection_settings = $block_protections[$block_id] ?? array();
        
        wp_send_json_success($protection_settings);
    }
    
    /**
     * Sanitize protection settings
     */
    private function sanitize_protection_settings($settings) {
        $sanitized = array();
        
        $sanitized['enabled'] = (bool) ($settings['enabled'] ?? false);
        $sanitized['accessType'] = sanitize_text_field($settings['accessType'] ?? 'subscription');
        $sanitized['requiredItems'] = array_map('intval', $settings['requiredItems'] ?? array());
        $sanitized['unauthorizedAction'] = sanitize_text_field($settings['unauthorizedAction'] ?? 'hide');
        $sanitized['customMessage'] = sanitize_textarea_field($settings['customMessage'] ?? '');
        
        return apply_filters('memberful_sanitize_block_protection_settings', $sanitized, $settings);
    }
}

// Initialize the block protection system
error_log('=== ABOUT TO INITIALIZE MEMBERFUL BLOCK PROTECTION ===');
Memberful_Block_Protection::instance();
error_log('=== MEMBERFUL BLOCK PROTECTION INSTANCE CREATED ===');
