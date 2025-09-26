<?php

/**
 * Search Result Enhancements for Protected Content
 * 
 * This file adds visual indicators and warnings for protected content
 * that appears in search results when the option is enabled.
 */

// Add visual indicators to search results for protected content
add_filter( 'the_title', 'memberful_wp_add_protection_indicator_to_title', 10, 2 );
add_filter( 'get_the_excerpt', 'memberful_wp_add_protection_warning_to_excerpt', 10, 2 );

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
    $premium_label = get_option( 'memberful_search_premium_label', 'Premium' );
    $title .= ' <span class="memberful-protected-indicator" style="color: #d63384; font-size: 0.8em; font-weight: normal;">' . esc_html( $premium_label ) . '</span>';
  }

  return $title;
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

  // Check if this post is protected and user doesn't have access
  if ( ! memberful_can_user_access_post( get_current_user_id(), $post_id ) ) {
    // Check if disclaimer should be shown
    $show_disclaimer = get_option( 'memberful_show_search_disclaimer', TRUE );
    
    if ( $show_disclaimer ) {
      $link_destination = get_option( 'memberful_search_link_destination', 'post' );
      
      // Get customizable text options
      $disclaimer_text = get_option( 'memberful_search_disclaimer_text', 'This content requires a subscription to view.' );
      $signup_text = get_option( 'memberful_search_signup_text', 'Sign up to access →' );
      $login_text = get_option( 'memberful_search_login_text', 'Sign in to access →' );
      
      // Get custom URLs (these override the default behavior)
      $custom_signup_url = get_option( 'memberful_search_custom_signup_url', '' );
      $custom_login_url = get_option( 'memberful_search_custom_login_url', '' );
      
      $warning = '<div class="memberful-search-warning" style="background: #f8f9fa; border-left: 4px solid #d63384; padding: 8px 12px; margin: 8px 0; font-size: 0.9em; color: #6c757d;margin:auto">';
      $warning .= '<strong>Premium Content:</strong> ' . esc_html( $disclaimer_text );
      
      // Build links based on what's provided
      $links = array();
      
      // Check if signup label is provided (regardless of URL)
      if ( ! empty( $signup_text ) ) {
        $signup_url = ! empty( $custom_signup_url ) ? $custom_signup_url : memberful_registration_page_url();
        $links[] = '<a href="' . esc_url( $signup_url ) . '" style="color: #d63384; text-decoration: none;">' . esc_html( $signup_text ) . '</a>';
      }
      
      // Check if login label is provided (regardless of URL)
      if ( ! empty( $login_text ) ) {
        $login_url = ! empty( $custom_login_url ) ? $custom_login_url : memberful_sign_in_url();
        $links[] = '<a href="' . esc_url( $login_url ) . '" style="color: #d63384; text-decoration: none;">' . esc_html( $login_text ) . '</a>';
      }
      
      // Only add links if at least one label is provided
      if ( ! empty( $links ) ) {
        $warning .= ' ' . implode( ' | ', $links );
      }
      
      $warning .= '</div>';
      
      // Append warning to excerpt
      $excerpt .= $warning;
    }
  }

  return $excerpt;
}

/**
 * Add CSS styles for search result enhancements
 */
add_action( 'wp_head', 'memberful_wp_search_result_styles' );

function memberful_wp_search_result_styles() {
  // Only add styles on search pages when the option is enabled
  if ( ! is_search() || ! get_option( 'memberful_include_protected_in_search', FALSE ) ) {
    return;
  }
  ?>
  <style>
    .memberful-protected-indicator {
      display: inline-block;
      margin-left: 5px;
    }
    
    .memberful-search-warning {
      border-radius: 4px;
    }
    
    .memberful-search-warning a:hover {
      text-decoration: underline !important;
    }
    
    /* Ensure the warning doesn't break search result layout */
    .search-results .memberful-search-warning {
      clear: both;
    }
  </style>
  <?php
}
