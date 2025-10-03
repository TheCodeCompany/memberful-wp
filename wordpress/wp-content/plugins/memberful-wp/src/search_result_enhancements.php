<?php

/**
 * Search Result Enhancements for Protected Content
 * 
 * This file adds visual indicators and warnings for protected content
 * that appears in search results when the option is enabled.
 * 
 * DEVELOPER HOOKS & FILTERS:
 * 
 * ACTIONS:
 * - memberful_search_enhancements_init - Fires when search enhancements are initialized
 * - memberful_search_before_title_indicator - Fires before adding title indicator (post_id)
 * - memberful_search_after_title_indicator - Fires after adding title indicator (post_id, indicator_html)
 * - memberful_search_before_disclaimer - Fires before processing disclaimer (post_id)
 * - memberful_search_after_disclaimer - Fires after adding disclaimer (post_id, warning_html)
 * - memberful_search_before_content_protection - Fires before adding content protection (post_id)
 * - memberful_search_after_content_protection - Fires after adding content protection (post_id, protection_html)
 * - memberful_search_before_styles - Fires before outputting default CSS styles
 * - memberful_search_after_styles - Fires after outputting default CSS styles
 * 
 * FILTERS:
 * - memberful_search_premium_label - Filter the premium label text (label, post_id)
 * - memberful_search_title_indicator_html - Customize title indicator HTML (html, post_id, label)
 * - memberful_search_protected_title - Override the complete protected title (title, post_id)
 * - memberful_search_show_disclaimer - Override disclaimer display (show, post_id)
 * - memberful_search_test_excerpt - Filter the excerpt used for testing (excerpt, post_id)
 * - memberful_search_disclaimer_html - Override complete disclaimer HTML (html, post_id, data_array)
 * - memberful_search_content_protection_html - Override content protection HTML (html, post_id, data_array)
 * - memberful_search_protected_excerpt - Override the complete protected excerpt (excerpt, post_id)
 * - memberful_search_include_default_styles - Disable default CSS styles (boolean)
 * - memberful_search_include_universal_styles - Disable universal disclaimer styles (boolean)
 */

// Add visual indicators to search results for protected content
add_filter( 'the_title', 'memberful_wp_add_protection_indicator_to_title', 10, 2 );
add_filter( 'get_the_excerpt', 'memberful_wp_add_protection_warning_to_excerpt', 20, 2 );

// Add support for block themes and various search result formats
add_filter( 'the_content', 'memberful_wp_add_protection_to_content', 5 );
add_filter( 'the_content', 'memberful_wp_add_search_disclaimer_to_content', 10 );
add_filter( 'wp_head', 'memberful_wp_add_search_enhancement_styles', 1 );

// Universal disclaimer system for all themes (replaced with PHP-based approach)
// add_action( 'wp_footer', 'memberful_wp_universal_disclaimer_script' );
// add_action( 'wp_head', 'memberful_wp_universal_disclaimer_styles' );

// Prevent duplicate disclaimers by tracking which posts have already been processed
global $memberful_processed_posts;
$memberful_processed_posts = array();

// Allow developers to hook into search enhancement initialization
add_action( 'init', 'memberful_wp_search_enhancements_init' );

function memberful_wp_search_enhancements_init() {
  // Allow developers to modify search enhancement behavior
  do_action( 'memberful_search_enhancements_init' );
}

/**
 * Add protection indicator to post titles in search results
 */
function memberful_wp_add_protection_indicator_to_title( $title, $post_id = null ) {
  // Only apply to search results and when the option is enabled
  if ( ! is_search() || ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return $title;
  }

  // Don't apply to admin users
  if ( current_user_can( 'publish_posts' ) ) {
    return $title;
  }

  // Validate post_id is numeric and exists
  if ( ! $post_id || ! is_numeric( $post_id ) || ! get_post( $post_id ) ) {
    return $title;
  }

  // Check if this post is protected and user doesn't have access
  if ( ! memberful_can_user_access_post( get_current_user_id(), $post_id ) ) {
    // Allow developers to hook before adding title indicator
    do_action( 'memberful_search_before_title_indicator', $post_id );
    
    $premium_label = get_option( 'memberful_search_premium_label', 'Premium' );
    
    // Allow developers to filter the premium label
    $premium_label = apply_filters( 'memberful_search_premium_label', $premium_label, $post_id );
    
    // Allow developers to customize the indicator HTML
    $indicator_html = apply_filters( 'memberful_search_title_indicator_html', 
      '<span class="memberful-protected-indicator" style="display: inline-block; margin-left: 8px; padding: 2px 8px; background: #d63384; color: white; font-size: 0.7em; font-weight: bold; border-radius: 3px; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2;">' . esc_html( $premium_label ) . '</span>',
      $post_id,
      $premium_label
    );
    
    $title .= ' ' . $indicator_html;
    
    // Add disclaimer after the title
    $show_disclaimer = get_option( 'memberful_search_show_disclaimer', 'yes' );
    if ( $show_disclaimer === 'yes' ) {
      // Get disclaimer text
      $disclaimer_text = get_option( 'memberful_search_disclaimer_text', 'This content is protected. Sign up to access it.' );
      $disclaimer_text = apply_filters( 'memberful_search_disclaimer_text', $disclaimer_text, $post_id );
      
      // Get signup/login URLs
      $signup_url = get_option( 'memberful_search_signup_url', '' );
      $login_url = get_option( 'memberful_search_login_url', '' );
      
      $signup_text = get_option( 'memberful_search_signup_text', 'Sign up for access' );
      $login_text = get_option( 'memberful_search_login_text', 'Login' );
      
      $signup_url = apply_filters( 'memberful_search_signup_url', 
        ! empty( $signup_url ) ? $signup_url : memberful_registration_page_url(), $post_id 
      );
      $login_url = apply_filters( 'memberful_search_login_url', 
        ! empty( $login_url ) ? $login_url : memberful_sign_in_url(), $post_id 
      );
      
      // Build disclaimer HTML
      $disclaimer_html = '<div class="memberful-search-disclaimer" style="background: #f8f9fa; border: 1px solid #e9ecef; border-left: 4px solid #d63384; border-radius: 4px; padding: 12px 16px; margin: 12px 0; font-size: 14px; line-height: 1.4;">';
      $disclaimer_html .= '<div style="display: flex; align-items: center; margin-bottom: 8px;">';
      $disclaimer_html .= '<span style="margin-right: 8px; font-size: 16px;">🔒</span>';
      $disclaimer_html .= '<span style="font-weight: 600; color: #495057;">' . esc_html( $disclaimer_text ) . '</span>';
      $disclaimer_html .= '</div>';
      
      $disclaimer_html .= '<div style="display: flex; gap: 8px; flex-wrap: wrap;">';
      if ( ! empty( $signup_text ) && ! empty( $signup_url ) ) {
        $disclaimer_html .= '<a href="' . esc_url( $signup_url ) . '" style="display: inline-block; padding: 6px 12px; background: #d63384; color: white; text-decoration: none; border-radius: 3px; font-size: 12px; font-weight: 500;">' . esc_html( $signup_text ) . '</a>';
      }
      if ( ! empty( $login_text ) && ! empty( $login_url ) ) {
        $disclaimer_html .= '<a href="' . esc_url( $login_url ) . '" style="display: inline-block; padding: 6px 12px; background: #d63384; color: white; text-decoration: none; border-radius: 3px; font-size: 12px; font-weight: 500;">' . esc_html( $login_text ) . '</a>';
      }
      $disclaimer_html .= '</div>';
      $disclaimer_html .= '</div>';
      
      // Allow developers to customize the disclaimer HTML
      $disclaimer_html = apply_filters( 'memberful_search_disclaimer_html', $disclaimer_html, $post_id, $disclaimer_text );
      
      $title .= $disclaimer_html;
    }
    
    // Allow developers to hook after adding title indicator
    do_action( 'memberful_search_after_title_indicator', $post_id, $indicator_html );
  }

  // Allow developers to completely override the title
  return apply_filters( 'memberful_search_protected_title', $title, $post_id );
}

/**
 * Add protection warning to excerpts in search results
 */
function memberful_wp_add_protection_warning_to_excerpt( $excerpt, $post = null ) {
  // Only apply to search results and when the option is enabled
  if ( ! is_search() || ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return $excerpt;
  }

  // Don't apply to admin users
  if ( current_user_can( 'publish_posts' ) ) {
    return $excerpt;
  }

  // Don't apply if we're in a content protection context to avoid conflicts
  if ( doing_filter( 'memberful_wp_protect_content' ) ) {
    return $excerpt;
  }

  // Check for duplicate processing
  global $memberful_processed_posts;

  // Get post ID
  $post_id = null;
  if ( $post && is_object( $post ) ) {
    $post_id = $post->ID;
  } elseif ( is_numeric( $post ) ) {
    $post_id = $post;
  } else {
    global $post;
    $post_id = $post ? $post->ID : null;
  }

  // Validate post_id is numeric and exists
  if ( ! $post_id || ! is_numeric( $post_id ) || ! get_post( $post_id ) ) {
    return $excerpt;
  }

  // Check if already processed to prevent duplicates
  if ( isset( $memberful_processed_posts[ $post_id ] ) ) {
    return $excerpt;
  }

  // Check if this post is protected and user doesn't have access
  if ( ! memberful_can_user_access_post( get_current_user_id(), $post_id ) ) {
    // Just return the excerpt without adding any disclaimer
    // Disclaimers are now handled by the title function
    return $excerpt;
  }

  // Allow developers to completely override the excerpt
  return apply_filters( 'memberful_search_protected_excerpt', $excerpt, $post_id );
}

/**
 * Add disclaimer to post content for search results
 */
function memberful_wp_add_search_disclaimer_to_content( $content ) {
  // Only add to search results
  if ( ! is_search() ) {
    return $content;
  }
  
  global $post;
  if ( ! $post ) {
    return $content;
  }
  
  
  // Check if this post is protected and user doesn't have access
  if ( memberful_can_user_access_post( get_current_user_id(), $post->ID ) ) {
    return $content;
  }
  
  // Check if search enhancements are enabled
  if ( ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return $content;
  }
  
  // Get settings
  $show_disclaimer = get_option( 'memberful_search_show_disclaimer', 'yes' );
  if ( $show_disclaimer !== 'yes' ) {
    return $content;
  }
  
  // Get disclaimer text
  $disclaimer_text = get_option( 'memberful_search_disclaimer_text', 'This content is protected. Sign up to access it.' );
  $disclaimer_text = apply_filters( 'memberful_search_disclaimer_text', $disclaimer_text, $post->ID );
  
  // Get signup/login URLs
  $signup_url = get_option( 'memberful_search_signup_url', '' );
  $login_url = get_option( 'memberful_search_login_url', '' );
  
  $signup_text = get_option( 'memberful_search_signup_text', 'Sign up for access' );
  $login_text = get_option( 'memberful_search_login_text', 'Login' );
  
  $signup_url = apply_filters( 'memberful_search_signup_url', 
    ! empty( $signup_url ) ? $signup_url : memberful_registration_page_url(), $post->ID 
  );
  $login_url = apply_filters( 'memberful_search_login_url', 
    ! empty( $login_url ) ? $login_url : memberful_sign_in_url(), $post->ID 
  );
  
  // Build disclaimer HTML
  $disclaimer_html = '<div class="memberful-search-disclaimer">';
  $disclaimer_html .= '<div class="disclaimer-header">';
  $disclaimer_html .= '<span class="disclaimer-icon">🔒</span>';
  $disclaimer_html .= '<span class="disclaimer-text">' . esc_html( $disclaimer_text ) . '</span>';
  $disclaimer_html .= '</div>';
  
  $disclaimer_html .= '<div class="disclaimer-actions">';
  if ( ! empty( $signup_text ) && ! empty( $signup_url ) ) {
    $disclaimer_html .= '<a href="' . esc_url( $signup_url ) . '" class="disclaimer-btn">' . esc_html( $signup_text ) . '</a>';
  }
  if ( ! empty( $login_text ) && ! empty( $login_url ) ) {
    $disclaimer_html .= '<a href="' . esc_url( $login_url ) . '" class="disclaimer-btn">' . esc_html( $login_text ) . '</a>';
  }
  $disclaimer_html .= '</div>';
  $disclaimer_html .= '</div>';
  
  // Allow developers to customize the disclaimer HTML
  $disclaimer_html = apply_filters( 'memberful_search_disclaimer_html', $disclaimer_html, $post->ID, $disclaimer_text );
  
  // Add disclaimer to content
  return $content . $disclaimer_html;
}


/**
 * Add protection indicators to content in search results (for block themes and other formats)
 */
function memberful_wp_add_protection_to_content( $content ) {
  // Only apply to search results and when the option is enabled
  if ( ! is_search() || ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return $content;
  }

  // Don't apply to admin users
  if ( current_user_can( 'publish_posts' ) ) {
    return $content;
  }

  global $post, $memberful_processed_posts;
  if ( ! $post || ! $post->ID ) {
    return $content;
  }

  // Check if already processed to prevent duplicates
  if ( isset( $memberful_processed_posts[ $post->ID ] ) ) {
    return $content;
  }

  // Check if this post is protected and user doesn't have access
  if ( ! memberful_can_user_access_post( get_current_user_id(), $post->ID ) ) {
    // Allow developers to hook before adding content protection
    do_action( 'memberful_search_before_content_protection', $post->ID );
    
    // Get the actual post excerpt for testing
    $post_excerpt = '';
    
    if ( ! empty( $post->post_excerpt ) ) {
      $post_excerpt = $post->post_excerpt;
    } else {
      // Generate excerpt from content (first 30 words)
      $post_excerpt = wp_trim_words( strip_tags( $post->post_content ), 30, '...' );
    }
    
    // Allow developers to filter the excerpt
    $post_excerpt = apply_filters( 'memberful_search_test_excerpt', $post_excerpt, $post->ID );
    
    if ( ! empty( $post_excerpt ) ) {
      $protection_html = '<div class="memberful-search-protection-wrapper" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-left: 4px solid #d63384; border-radius: 4px;">';
      $protection_html .= '<div class="memberful-search-protection-header" style="font-weight: bold; color: #d63384; margin-bottom: 8px;">🔒 Premium Content Preview</div>';
      $protection_html .= '<div class="memberful-search-protection-excerpt" style="font-style: italic; color: #495057; line-height: 1.4;">' . esc_html( $post_excerpt ) . '</div>';
      $protection_html .= '</div>';
      
      // Allow developers to completely customize the protection HTML
      $protection_html = apply_filters( 'memberful_search_content_protection_html', $protection_html, $post->ID, array(
        'excerpt' => $post_excerpt,
        'post_id' => $post->ID
      ));
      
      // Prepend protection notice to content
      $content = $protection_html . $content;
      
      // Mark as processed to prevent duplicates
      $memberful_processed_posts[ $post->ID ] = true;
      
      // Allow developers to hook after adding content protection
      do_action( 'memberful_search_after_content_protection', $post->ID, $protection_html );
    }
  }

  return $content;
}

/**
 * Add comprehensive styles for search enhancements that work with any theme
 */
function memberful_wp_add_search_enhancement_styles() {
  // Only add styles on search pages when the option is enabled
  if ( ! is_search() || ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return;
  }
  
  // Allow developers to disable default styles
  if ( ! apply_filters( 'memberful_search_include_default_styles', true ) ) {
    return;
  }
  
  // Allow developers to add custom styles before default ones
  do_action( 'memberful_search_before_styles' );
  ?>
  <style id="memberful-search-enhancements">
    /* Base styles for search result enhancements - High specificity */
    .memberful-protected-indicator,
    span.memberful-protected-indicator {
      display: inline-block !important;
      margin-left: 8px !important;
      padding: 2px 8px !important;
      background: #d63384 !important;
      color: white !important;
      font-size: 0.7em !important;
      font-weight: bold !important;
      border-radius: 3px !important;
      text-transform: uppercase !important;
      letter-spacing: 0.5px !important;
      line-height: 1.2 !important;
      text-decoration: none !important;
      border: none !important;
      box-shadow: none !important;
    }
    
    .memberful-search-protection-wrapper {
      border-radius: 4px;
      margin: 10px 0;
      clear: both;
    }
    
    .memberful-search-protection-wrapper a {
      color: #d63384;
      text-decoration: none;
      font-weight: bold;
    }
    
    .memberful-search-protection-wrapper a:hover {
      text-decoration: underline !important;
    }
    
    /* Ensure compatibility with various themes */
    .search-results .memberful-search-protection-wrapper,
    .wp-block-query .memberful-search-protection-wrapper,
    .wp-block-post-excerpt .memberful-search-protection-wrapper {
      clear: both;
      display: block;
      width: 100%;
      box-sizing: border-box;
    }
    
    /* Block theme compatibility */
    .wp-block-post-title + .memberful-search-protection-wrapper {
      margin-top: 0;
    }
    
    /* Ensure proper spacing in various layouts */
    .entry-content .memberful-search-protection-wrapper,
    .post-content .memberful-search-protection-wrapper {
      margin: 15px 0;
    }
    
    /* Ensure text visibility and override theme styles */
    .memberful-protected-indicator * {
      color: white !important;
      background: transparent !important;
    }
    
    /* Override any theme text hiding */
    .memberful-protected-indicator {
      visibility: visible !important;
      opacity: 1 !important;
      position: relative !important;
      z-index: 1 !important;
    }
    
    /* Responsive design */
    @media (max-width: 768px) {
      .memberful-search-protection-wrapper {
        margin: 8px 0;
        padding: 10px;
        font-size: 0.9em;
      }
      
      .memberful-protected-indicator {
        font-size: 0.6em !important;
        padding: 1px 4px !important;
      }
    }
  </style>
  <?php
  
  // Allow developers to add custom styles after default ones
  do_action( 'memberful_search_after_styles' );
}

/**
 * Universal disclaimer styles that work with any theme
 */
function memberful_wp_universal_disclaimer_styles() {
  // Only add styles on search pages when the option is enabled
  if ( ! is_search() || ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return;
  }
  
  // Allow developers to disable universal styles
  if ( ! apply_filters( 'memberful_search_include_universal_styles', true ) ) {
    return;
  }
  ?>
  <style id="memberful-universal-disclaimer">
    /* Universal disclaimer - inline with search results */
    .memberful-universal-disclaimer {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-left: 4px solid #d63384;
      border-radius: 4px;
      padding: 15px 20px;
      margin: 15px 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 14px;
      color: #495057;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      opacity: 0;
      transform: translateY(10px);
      transition: all 0.3s ease-in-out;
    }
    
    .memberful-universal-disclaimer.show {
      opacity: 1;
      transform: translateY(0);
    }
    
    .memberful-universal-disclaimer .disclaimer-content {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      flex-wrap: wrap;
    }
    
    .memberful-universal-disclaimer .disclaimer-icon {
      font-size: 16px;
      color: #d63384;
      margin-top: 2px;
    }
    
    .memberful-universal-disclaimer .disclaimer-text {
      flex: 1;
      min-width: 200px;
      font-weight: 500;
      color: #495057;
    }
    
    .memberful-universal-disclaimer .disclaimer-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }
    
    .memberful-universal-disclaimer .disclaimer-btn {
      background: #d63384;
      color: white;
      border: 1px solid #d63384;
      padding: 6px 12px;
      border-radius: 4px;
      text-decoration: none;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.2s ease;
      display: inline-block;
    }
    
    .memberful-universal-disclaimer .disclaimer-btn:hover {
      background: #c82333;
      border-color: #c82333;
      color: white;
      text-decoration: none;
    }
    
    .memberful-universal-disclaimer .disclaimer-close {
      background: none;
      border: none;
      color: #6c757d;
      font-size: 16px;
      cursor: pointer;
      padding: 0;
      margin-left: 8px;
      opacity: 0.7;
      transition: opacity 0.2s ease;
    }
    
    .memberful-universal-disclaimer .disclaimer-close:hover {
      opacity: 1;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
      .memberful-universal-disclaimer {
        padding: 12px 15px;
        font-size: 13px;
      }
      
      .memberful-universal-disclaimer .disclaimer-content {
        flex-direction: column;
        gap: 10px;
      }
      
      .memberful-universal-disclaimer .disclaimer-actions {
        flex-wrap: wrap;
        justify-content: flex-start;
      }
    }
    
    /* Hide on very small screens */
    @media (max-width: 480px) {
      .memberful-universal-disclaimer .disclaimer-text {
        font-size: 12px;
      }
      
      .memberful-universal-disclaimer {
        padding: 10px 12px;
      }
    }
  </style>
  <?php
}

/**
 * Universal disclaimer JavaScript that works with any theme
 */
function memberful_wp_universal_disclaimer_script() {
  // Only add script on search pages when the option is enabled
  if ( ! is_search() || ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return;
  }
  
  // Don't show to admin users
  if ( current_user_can( 'publish_posts' ) ) {
    return;
  }
  
  // Get settings
  $show_disclaimer = get_option( 'memberful_show_search_disclaimer', TRUE );
  if ( ! $show_disclaimer ) {
    return;
  }
  
  $disclaimer_text = get_option( 'memberful_search_disclaimer_text', 'This content requires a subscription to view.' );
  $signup_text = get_option( 'memberful_search_signup_text', 'Sign up to access →' );
  $login_text = get_option( 'memberful_search_login_text', 'Sign in to access →' );
  $signup_url = get_option( 'memberful_search_custom_signup_url', '' );
  $login_url = get_option( 'memberful_search_custom_login_url', '' );
  
  // Allow developers to filter the settings
  $disclaimer_text = apply_filters( 'memberful_search_disclaimer_text', $disclaimer_text, 0 );
  $signup_text = apply_filters( 'memberful_search_signup_text', $signup_text, 0 );
  $login_text = apply_filters( 'memberful_search_login_text', $login_text, 0 );
  $signup_url = apply_filters( 'memberful_search_signup_url', 
    ! empty( $signup_url ) ? $signup_url : memberful_registration_page_url(), 0 
  );
  $login_url = apply_filters( 'memberful_search_login_url', 
    ! empty( $login_url ) ? $login_url : memberful_sign_in_url(), 0 
  );
  ?>
  <!-- PHP-based disclaimer system for search results -->
  <style id="memberful-search-disclaimer-styles">
    .memberful-search-disclaimer {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-left: 4px solid #d63384;
      border-radius: 4px;
      padding: 12px 16px;
      margin: 12px 0;
      font-size: 14px;
      line-height: 1.4;
    }
    
    .memberful-search-disclaimer .disclaimer-header {
      display: flex;
      align-items: center;
      margin-bottom: 8px;
    }
    
    .memberful-search-disclaimer .disclaimer-icon {
      margin-right: 8px;
      font-size: 16px;
    }
    
    .memberful-search-disclaimer .disclaimer-text {
      font-weight: 600;
      color: #495057;
    }
    
    .memberful-search-disclaimer .disclaimer-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .memberful-search-disclaimer .disclaimer-btn {
      display: inline-block;
      padding: 6px 12px;
      background: #d63384;
      color: white;
      text-decoration: none;
      border-radius: 3px;
      font-size: 12px;
      font-weight: 500;
      transition: background-color 0.2s;
    }
    
    .memberful-search-disclaimer .disclaimer-btn:hover {
      background: #b02a5b;
      color: white;
    }
    
    .memberful-search-disclaimer .disclaimer-close {
      background: #6c757d;
      color: white;
      border: none;
      border-radius: 3px;
      padding: 6px 8px;
      cursor: pointer;
      font-size: 12px;
      margin-left: auto;
    }
    
    .memberful-search-disclaimer .disclaimer-close:hover {
      background: #5a6268;
    }
  </style>
  <?php
}
