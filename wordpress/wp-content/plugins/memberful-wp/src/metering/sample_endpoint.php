<?php
/**
 * Uncacheable admin-ajax endpoint that releases a protected post's body to an anonymous visitor who is still within
 * their free allowance. admin-ajax is never edge-cached and still receives cookies, so the signed meter cookie works
 * here even on hosts that strip it from cacheable page views.
 *
 * @package memberful-wp
 */

/**
 * Class Memberful_Metering_Sample.
 */
class Memberful_Metering_Sample {
  const ACTION = 'memberful_metering_sample';

  /**
   * Register the logged-out handler only. Logged-in visitors bypass the cache and are decided on the page path.
   */
  public static function register(): void {
    add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
  }

  /**
   * Decide server-side (against the signed cookie) whether to release the post body, and return it when allowed.
   *
   * No nonce: a page-embedded nonce goes stale in cache, and a forged request can at most advance the caller's own
   * meter and receive content already bounded server-side by the anonymous limit. The response is same-origin JSON.
   */
  public static function handle(): void {
    nocache_headers();

    $post_id = (int) filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
    if ( $post_id <= 0 ) {
      wp_send_json_error( array( 'code' => 'bad_request' ), 400 );
    }

    // Free posts mirror their view here so protected samples share the same allowance; they need no body back.
    if ( 'record' === filter_input( INPUT_POST, 'op' ) ) {
      $recorded = Memberful_Metering_Access::record_free_view( $post_id );

      wp_send_json_success(
        array(
          'recorded'  => ! empty( $recorded['recorded'] ),
          'remaining' => (int) $recorded['remaining'],
        )
      );
    }

    $result = Memberful_Metering_Access::evaluate_sample( $post_id );

    if ( empty( $result['released'] ) ) {
      wp_send_json_success(
        array(
          'released'  => false,
          'remaining' => 0,
        )
      );
    }

    wp_send_json_success(
      array(
        'released'  => true,
        'remaining' => (int) $result['remaining'],
        'html'      => memberful_wp_render_metered_body( get_post( $post_id ) ),
      )
    );
  }

  /**
   * AJAX args passed to the front-end runtime via wp_localize_script.
   *
   * @return array
   */
  public static function script_args(): array {
    return array(
      'ajaxUrl' => admin_url( 'admin-ajax.php' ),
      'action'  => self::ACTION,
    );
  }
}
