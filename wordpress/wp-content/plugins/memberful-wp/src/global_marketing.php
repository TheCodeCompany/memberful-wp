<?php

if ( ! defined( 'MEMBERFUL_PARAGRAPH_COUNT' ) ) {
  define( 'MEMBERFUL_PARAGRAPH_COUNT', 2 );
}

if(get_option('memberful_use_global_snippets')){
  add_filter( 'memberful_wp_protect_content', 'memberful_apply_global_snippets_content_filter', 1, 1 );
} else {
  add_filter( 'memberful_wp_protect_content', 'memberful_get_global_replacement', 1, 1 );
}

/**
 * Identify Post specific or global marketing content
 *
 * @param string $marketing_content
 * @return string
 */
function memberful_get_global_replacement($marketing_content){
  $override = get_option( 'memberful_global_marketing_override' );
  $global_marketing_content = get_option( 'memberful_global_marketing_content' );

  if($override) {
    return $global_marketing_content;
  }

  if(empty(trim($marketing_content))){
    return $global_marketing_content;
  }

  return $marketing_content;
}

/**
 * Filter the paywall to return a "teaser".
 *
 * @param string $memberful_marketing_content
 *
 * @return string concat of teaser and memberful marketing content
 */
function memberful_apply_global_snippets_content_filter( $memberful_marketing_content ) {
  global $post;
  $replacement = memberful_get_global_replacement($memberful_marketing_content);

  $wrapped_global_marketing_content = "<div class='memberful-global-marketing-content'>$replacement</div>";

  // Prevent endless loop trap
  remove_action( 'the_content', 'memberful_wp_protect_content', -10 );

  $original_content = apply_filters( 'the_content', $post->post_content );

  // re-add the action for follow-on call
  add_action( 'the_content', 'memberful_wp_protect_content', -10 );

  // Use post excerpt instead of teaser
  $excerpt = '';
  $has_excerpt = false;

  if ( !empty( $post->post_excerpt ) ) {
    // Use the manual excerpt if available
    $excerpt = $post->post_excerpt;
    $has_excerpt = true;
  } else {
    // Generate excerpt from content (first 30 words)
    $excerpt = wp_trim_words( strip_tags( $post->post_content ), 30, '...' );
    $has_excerpt = !empty( $excerpt );
  }

  $wrapped_excerpt = "<div class='memberful-global-teaser-content'>$excerpt</div>";

  if ( $has_excerpt && ! did_action( 'memberful_teaser_css' ) ) {
    $wrapped_excerpt .= apply_filters( 'memberful_teaser_css', memberful_get_teaser_css() );
  }

  return $wrapped_excerpt . $wrapped_global_marketing_content;
}

function memberful_get_teaser_css(){
  $css = <<<CSS
    <style>
        .memberful-global-teaser-content p:last-child{
            -webkit-mask-image: linear-gradient(180deg, #000 0%, transparent);
            mask-image: linear-gradient(180deg, #000 0%, transparent);
        }
    </style>
CSS;

  return $css;
}
