<?php

/**
 * Memberful Gutenberg Block Protection
 * 
 * Provides block-level content protection for Gutenberg blocks
 * with extensible hooks for third-party developers.
 */

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
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Frontend content filtering
        add_filter('render_block', array($this, 'filter_protected_blocks'), 10, 2);
        
        // Block registration is handled by protected-content-block.php
        
        // Admin scripts
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        
    }
    
    /**
     * Filter protected blocks on the frontend
     */
    public function filter_protected_blocks($block_content, $block) {
        // Skip if not in frontend or user is admin
        if (is_admin() || current_user_can('publish_posts')) {
            return $block_content;
        }
        
        
        // Check if block has Memberful protection attributes
        if (!isset($block['attrs']['memberfulProtection']) || 
            !$block['attrs']['memberfulProtection']['enabled']) {
            return $block_content;
        }
        
        $protection_settings = $block['attrs']['memberfulProtection'];
        $user_id = get_current_user_id();
        
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
        
        if ($can_access) {
            return $block_content;
        }
        
        // Handle unauthorized access
        return $this->handle_unauthorized_access($block_content, $protection_settings, $user_id);
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
     * Handle unauthorized access to blocks (V1: Only supports hiding)
     */
    private function handle_unauthorized_access($block_content, $protection_settings, $user_id) {
        // V1 only supports hiding content completely
        // Future versions can add more options
        
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
        
        // For v1, just hide the content
        return '';
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
Memberful_Block_Protection::instance();
