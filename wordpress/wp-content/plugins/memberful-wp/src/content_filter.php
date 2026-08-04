<?php

add_action( 'the_content', 'memberful_wp_protect_content', 100 );

/**
 * Get the marker inserted by the paywall divider block.
 *
 * @return string
 */
function memberful_wp_get_paywall_divider_marker() {
  return '<!-- memberful-paywall-divider -->';
}

/**
 * Remove the paywall divider marker from rendered content.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function memberful_wp_strip_paywall_divider_marker( $content ) {
  return str_replace( memberful_wp_get_paywall_divider_marker(), '', (string) $content );
}

/**
 * Split rendered post content at the first paywall divider marker.
 *
 * @param string $content Rendered post content.
 * @return array{
 *   has_divider: bool,
 *   content_above_divider: string,
 *   content_below_divider: string
 * }
 */
function memberful_wp_split_post_content_at_paywall_divider( $content ) {
  $content = (string) $content;

  if ( '' === $content ) {
    return array(
      'has_divider'            => false,
      'content_above_divider'  => '',
      'content_below_divider'  => '',
    );
  }

  $content_parts = explode( memberful_wp_get_paywall_divider_marker(), $content, 2 );

  if ( ! is_array( $content_parts ) || 2 !== count( $content_parts ) ) {
    return array(
      'has_divider'            => false,
      'content_above_divider'  => $content,
      'content_below_divider'  => '',
    );
  }

  return array(
    'has_divider'            => true,
    'content_above_divider'  => $content_parts[0],
    'content_below_divider'  => $content_parts[1],
  );
}

/**
 * Apply teaser wrapper and CSS for divider content when snippets are enabled.
 *
 * @param string $content Rendered content above the paywall divider.
 * @return string Formatted teaser content.
 */
function memberful_wp_format_divider_teaser_content( $content ) {
  if ( '' === trim( (string) $content ) ) {
    return $content;
  }

  if ( ! get_option( 'memberful_use_global_snippets' ) ) {
    return $content;
  }

  $wrapped_content = "<div class='memberful-global-teaser-content'>$content</div>";

  if ( function_exists( 'memberful_get_teaser_css' ) && ! did_filter( 'memberful_teaser_css' ) ) {
    $wrapped_content .= apply_filters( 'memberful_teaser_css', memberful_get_teaser_css() );
  }

  return $wrapped_content;
}

function memberful_wp_protect_content( $content ) {
  global $post;

  $content_split = memberful_wp_split_post_content_at_paywall_divider( $content );

  if ( !isset( $post ) ) {
    # Return the content since we're not in the loop if `$post` is `NULL`
    # Temporary fix for Elasticpress' syncing issue
    return memberful_wp_strip_paywall_divider_marker( $content );
  }

  if(doing_filter('memberful_wp_protect_content')){
    return memberful_wp_strip_paywall_divider_marker( $content );
  }

  // Do not filter content for admins
  if ( current_user_can( 'publish_posts' ) ) {
    return memberful_wp_strip_paywall_divider_marker( $content );
  }

  if ( ! memberful_can_user_access_post( wp_get_current_user()->ID, $post->ID ) ) {
    // Disable Beaver Builder while the marketing content is built, so nested
    // `the_content` calls can't render the protected layout into it.
    $beaver_builder_priority = has_filter( 'the_content', 'FLBuilder::render_content' );

    if ( FALSE !== $beaver_builder_priority ) {
      remove_action( 'the_content', 'FLBuilder::render_content', $beaver_builder_priority );
    }

    // Remove Elementor action hook
    if (get_queried_object_id() === $post->ID) {
      remove_action("elementor/frontend/the_content", "memberful_wp_protect_content");
    }

    // Remove media enclosures from the RSS feed
    add_filter("rss_enclosure", "__return_empty_string");

    $memberful_marketing_content = memberful_marketing_content( $post->ID );

    if ( $content_split['has_divider'] ) {
      $content_above_divider = memberful_wp_format_divider_teaser_content( $content_split['content_above_divider'] );
      $rendered_marketing_content = apply_filters( 'memberful_wp_protect_content', $memberful_marketing_content );

      if ( '' !== trim( (string) $rendered_marketing_content ) ) {
        $protected_content = $content_above_divider . $rendered_marketing_content;
      } else {
        $protected_content = $content_above_divider;
      }
    } else {
      $protected_content = apply_filters( 'memberful_wp_protect_content', $memberful_marketing_content );
    }

    // Restore Beaver Builder after this `the_content` run finishes, so a
    // same-run callback at an earlier priority (e.g. Sensei's filter at -10)
    // cannot let Beaver Builder replace paywall output with the protected layout.
    if ( FALSE !== $beaver_builder_priority ) {
      if ( doing_filter( 'the_content' ) ) {
        $restore_beaver_builder = function( $content ) use ( $beaver_builder_priority, &$restore_beaver_builder ) {
          remove_filter( 'the_content', $restore_beaver_builder, 9999 );
          add_filter( 'the_content', 'FLBuilder::render_content', $beaver_builder_priority );
          return $content;
        };
        add_filter( 'the_content', $restore_beaver_builder, 9999 );
      } else {
        add_filter( 'the_content', 'FLBuilder::render_content', $beaver_builder_priority );
      }
    }

    return $protected_content;
  }

  if ( $content_split['has_divider'] ) {
    return $content_split['content_above_divider'] . $content_split['content_below_divider'];
  }

  return memberful_wp_strip_paywall_divider_marker( $content );
}

add_filter( 'memberful_wp_protect_content','wptexturize');
add_filter( 'memberful_wp_protect_content','convert_smilies');
add_filter( 'memberful_wp_protect_content','convert_chars');
add_filter( 'memberful_wp_protect_content','wpautop');
add_filter( 'memberful_wp_protect_content','shortcode_unautop');
add_filter( 'memberful_wp_protect_content','prepend_attachment');

// Match core ordering: blocks render before wpautop (10) and do_shortcode (11),
// so shortcodes emitted by blocks in marketing content still execute.
add_filter( 'memberful_wp_protect_content', 'do_blocks', 9 );
add_filter( 'memberful_wp_protect_content', 'do_shortcode', 11 );

if ( get_option( 'memberful_use_global_marketing' ) ) {
  include_once 'global_marketing.php';
}
