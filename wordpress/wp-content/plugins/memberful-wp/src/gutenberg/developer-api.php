<?php

/**
 * Memberful Gutenberg Block Protection - Developer API
 * 
 * Provides comprehensive hooks and filters for third-party developers
 * to extend and customize the block protection system.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Developer API for Memberful Block Protection
 * 
 * This class provides a comprehensive set of hooks and filters
 * for developers to extend the block protection functionality.
 */
class Memberful_Block_Protection_API {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_developer_hooks();
    }
    
    /**
     * Initialize all developer hooks and filters
     */
    private function init_developer_hooks() {
        // Block Settings Hooks
        add_filter('memberful_block_protection_settings', array($this, 'filter_block_protection_settings'), 10, 2);
        add_filter('memberful_protectable_block_types', array($this, 'filter_protectable_block_types'), 10, 1);
        add_filter('memberful_block_editor_settings', array($this, 'filter_block_editor_settings'), 10, 1);
        
        // Access Control Hooks
        add_filter('memberful_can_user_access_block', array($this, 'filter_block_access_check'), 10, 4);
        add_filter('memberful_override_block_access_check', array($this, 'filter_override_access_check'), 10, 4);
        add_filter('memberful_custom_access_type_', array($this, 'filter_custom_access_types'), 10, 3);
        
        // Content Filtering Hooks
        add_filter('memberful_protected_block_content', array($this, 'filter_protected_block_content'), 10, 3);
        add_filter('memberful_unauthorized_block_content', array($this, 'filter_unauthorized_block_content'), 10, 4);
        add_filter('memberful_block_marketing_content', array($this, 'filter_block_marketing_content'), 10, 3);
        
        // Block Registration Hooks
        add_action('memberful_register_protected_blocks', array($this, 'register_custom_protected_blocks'));
        add_filter('memberful_available_subscriptions_for_blocks', array($this, 'filter_available_subscriptions'), 10, 1);
        add_filter('memberful_available_products_for_blocks', array($this, 'filter_available_products'), 10, 1);
        
        // Settings Sanitization Hooks
        add_filter('memberful_sanitize_block_protection_settings', array($this, 'filter_sanitize_settings'), 10, 2);
        add_filter('memberful_default_block_protection_settings', array($this, 'filter_default_settings'), 10, 1);
        
        // Unauthorized Actions Hooks
        add_filter('memberful_custom_unauthorized_action_', array($this, 'filter_custom_unauthorized_actions'), 10, 3);
        add_filter('memberful_unauthorized_block_actions', array($this, 'filter_unauthorized_actions'), 10, 2);
        
        // Event Hooks
        add_action('memberful_block_access_granted', array($this, 'handle_block_access_granted'), 10, 3);
        add_action('memberful_block_access_denied', array($this, 'handle_block_access_denied'), 10, 3);
        add_action('memberful_block_protection_saved', array($this, 'handle_block_protection_saved'), 10, 3);
    }
    
    /**
     * Filter block protection settings for specific block types
     * 
     * @param array $settings Default protection settings
     * @param string $block_type The block type name
     * @return array Modified settings
     */
    public function filter_block_protection_settings($settings, $block_type) {
        // Developers can modify settings based on block type
        return $settings;
    }
    
    /**
     * Filter which block types can be protected
     * 
     * @param array $block_types Array of protectable block types
     * @return array Modified array of block types
     */
    public function filter_protectable_block_types($block_types) {
        // Default protectable blocks
        $default_types = array(
            'core/paragraph',
            'core/heading',
            'core/image',
            'core/video',
            'core/audio',
            'core/embed',
            'core/gallery',
            'core/list',
            'core/quote',
            'core/code',
            'core/preformatted',
            'core/verse',
            'core/table',
            'core/columns',
            'core/group',
            'core/cover'
        );
        
        $block_types = array_merge($block_types, $default_types);
        
        // Allow developers to add/remove block types
        return apply_filters('memberful_custom_protectable_block_types', $block_types);
    }
    
    /**
     * Filter block editor settings
     * 
     * @param array $settings Block editor settings
     * @return array Modified settings
     */
    public function filter_block_editor_settings($settings) {
        // Allow developers to modify editor settings
        return $settings;
    }
    
    /**
     * Filter block access check
     * 
     * @param bool $can_access Current access decision
     * @param int $user_id User ID
     * @param array $protection_settings Protection settings
     * @param int $post_id Post ID
     * @return bool Modified access decision
     */
    public function filter_block_access_check($can_access, $user_id, $protection_settings, $post_id) {
        // Allow developers to modify access decisions
        return $can_access;
    }
    
    /**
     * Filter override access check
     * 
     * @param mixed $override Override value (null if no override)
     * @param int $user_id User ID
     * @param array $protection_settings Protection settings
     * @param int $post_id Post ID
     * @return mixed Override value or null
     */
    public function filter_override_access_check($override, $user_id, $protection_settings, $post_id) {
        // Allow developers to completely override access checking
        return $override;
    }
    
    /**
     * Filter custom access types
     * 
     * @param bool $can_access Access decision
     * @param int $user_id User ID
     * @param array $required_items Required items for access
     * @return bool Access decision
     */
    public function filter_custom_access_types($can_access, $user_id, $required_items) {
        // Allow developers to add custom access types
        return $can_access;
    }
    
    /**
     * Filter protected block content
     * 
     * @param string $content Block content
     * @param array $protection_settings Protection settings
     * @param int $user_id User ID
     * @return string Modified content
     */
    public function filter_protected_block_content($content, $protection_settings, $user_id) {
        // Allow developers to modify protected content
        return $content;
    }
    
    /**
     * Filter unauthorized block content
     * 
     * @param string $content Unauthorized content
     * @param string $original_content Original block content
     * @param array $protection_settings Protection settings
     * @param int $user_id User ID
     * @return string Modified content
     */
    public function filter_unauthorized_block_content($content, $original_content, $protection_settings, $user_id) {
        // Allow developers to customize unauthorized content
        return $content;
    }
    
    /**
     * Filter block marketing content
     * 
     * @param string $marketing_content Marketing content
     * @param array $protection_settings Protection settings
     * @param int $user_id User ID
     * @return string Modified marketing content
     */
    public function filter_block_marketing_content($marketing_content, $protection_settings, $user_id) {
        // Allow developers to customize marketing content
        return $marketing_content;
    }
    
    /**
     * Register custom protected blocks
     */
    public function register_custom_protected_blocks() {
        // Allow developers to register custom protected content blocks
        do_action('memberful_register_custom_protected_blocks');
    }
    
    /**
     * Filter available subscriptions
     * 
     * @param array $subscriptions Available subscriptions
     * @return array Modified subscriptions
     */
    public function filter_available_subscriptions($subscriptions) {
        // Allow developers to modify available subscriptions
        return $subscriptions;
    }
    
    /**
     * Filter available products
     * 
     * @param array $products Available products
     * @return array Modified products
     */
    public function filter_available_products($products) {
        // Allow developers to modify available products
        return $products;
    }
    
    /**
     * Filter settings sanitization
     * 
     * @param array $sanitized Sanitized settings
     * @param array $raw_settings Raw settings
     * @return array Modified sanitized settings
     */
    public function filter_sanitize_settings($sanitized, $raw_settings) {
        // Allow developers to add custom sanitization
        return $sanitized;
    }
    
    /**
     * Filter default protection settings
     * 
     * @param array $settings Default settings
     * @return array Modified settings
     */
    public function filter_default_settings($settings) {
        // Allow developers to modify default settings
        return $settings;
    }
    
    /**
     * Filter custom unauthorized actions
     * 
     * @param string $content Content to display
     * @param array $protection_settings Protection settings
     * @param int $user_id User ID
     * @return string Modified content
     */
    public function filter_custom_unauthorized_actions($content, $protection_settings, $user_id) {
        // Allow developers to add custom unauthorized actions
        return $content;
    }
    
    /**
     * Filter unauthorized actions
     * 
     * @param array $actions Available unauthorized actions
     * @param string $context Context of the filter
     * @return array Modified actions
     */
    public function filter_unauthorized_actions($actions, $context) {
        // Allow developers to add custom unauthorized actions
        return $actions;
    }
    
    /**
     * Handle block access granted event
     * 
     * @param int $user_id User ID
     * @param array $protection_settings Protection settings
     * @param int $post_id Post ID
     */
    public function handle_block_access_granted($user_id, $protection_settings, $post_id) {
        // Allow developers to react to access granted events
        do_action('memberful_custom_block_access_granted', $user_id, $protection_settings, $post_id);
    }
    
    /**
     * Handle block access denied event
     * 
     * @param int $user_id User ID
     * @param array $protection_settings Protection settings
     * @param int $post_id Post ID
     */
    public function handle_block_access_denied($user_id, $protection_settings, $post_id) {
        // Allow developers to react to access denied events
        do_action('memberful_custom_block_access_denied', $user_id, $protection_settings, $post_id);
    }
    
    /**
     * Handle block protection saved event
     * 
     * @param int $post_id Post ID
     * @param string $block_id Block ID
     * @param array $protection_settings Protection settings
     */
    public function handle_block_protection_saved($post_id, $block_id, $protection_settings) {
        // Allow developers to react to protection settings being saved
        do_action('memberful_custom_block_protection_saved', $post_id, $block_id, $protection_settings);
    }
}

/**
 * Helper functions for developers
 */

/**
 * Check if a block is protected
 * 
 * @param string $block_id Block ID
 * @param int $post_id Post ID
 * @return bool True if block is protected
 */
function memberful_is_block_protected($block_id, $post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $block_protections = get_post_meta($post_id, '_memberful_block_protections', true) ?: array();
    return isset($block_protections[$block_id]) && $block_protections[$block_id]['enabled'];
}

/**
 * Get block protection settings
 * 
 * @param string $block_id Block ID
 * @param int $post_id Post ID
 * @return array Protection settings
 */
function memberful_get_block_protection_settings($block_id, $post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $block_protections = get_post_meta($post_id, '_memberful_block_protections', true) ?: array();
    return $block_protections[$block_id] ?? array();
}

/**
 * Set block protection settings
 * 
 * @param string $block_id Block ID
 * @param array $settings Protection settings
 * @param int $post_id Post ID
 * @return bool True on success
 */
function memberful_set_block_protection_settings($block_id, $settings, $post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $block_protections = get_post_meta($post_id, '_memberful_block_protections', true) ?: array();
    $block_protections[$block_id] = $settings;
    
    $result = update_post_meta($post_id, '_memberful_block_protections', $block_protections);
    
    if ($result) {
        do_action('memberful_block_protection_saved', $post_id, $block_id, $settings);
    }
    
    return $result;
}

/**
 * Remove block protection
 * 
 * @param string $block_id Block ID
 * @param int $post_id Post ID
 * @return bool True on success
 */
function memberful_remove_block_protection($block_id, $post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $block_protections = get_post_meta($post_id, '_memberful_block_protections', true) ?: array();
    unset($block_protections[$block_id]);
    
    return update_post_meta($post_id, '_memberful_block_protections', $block_protections);
}

/**
 * Get all protected blocks for a post
 * 
 * @param int $post_id Post ID
 * @return array Array of protected block IDs and their settings
 */
function memberful_get_protected_blocks($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $block_protections = get_post_meta($post_id, '_memberful_block_protections', true) ?: array();
    return array_filter($block_protections, function($settings) {
        return $settings['enabled'] ?? false;
    });
}

// Initialize the developer API
Memberful_Block_Protection_API::instance();
