<?php
/**
 * Uncacheable admin-ajax endpoint that releases a protected post's body to an anonymous visitor who is still within
 * their free allowance. admin-ajax is never edge-cached and still receives cookies, so the server-issued subject cookie
 * and its ledger work here even on hosts that strip cookies from cacheable page views.
 *
 * Release is decided server-side against the subject's ledger (never the client's self-reported count), the request is
 * limited to same-origin callers, and both per-IP and site-wide rate limits bound scripted enumeration/drain attempts.
 *
 * @package memberful-wp
 */

/**
 * Class Memberful_Metering_Sample.
 */
class Memberful_Metering_Sample {
  const ACTION = 'memberful_metering_sample';

  /**
   * The sole allowed value of the `op` request field.
   */
  const OPS = array( 'sample' );

  /**
   * Max endpoint calls per client IP per minute before returning 429.
   */
  const RATE_PER_IP = 30;

  /**
   * Max endpoint calls site-wide per minute before returning 429 (botnet circuit-breaker).
   */
  const RATE_GLOBAL = 600;

  /**
   * Max protected-body releases per client IP per hour for requests that arrive WITHOUT a valid subject cookie.
   *
   * A genuine reader is cookieless only on their first request (the response then sets the cookie); a scraper that
   * drops or rotates cookies is cookieless on every request. Capping cookieless releases therefore bounds catalogue
   * drain to roughly this many bodies per IP per hour while barely touching real visitors — including shared/CGNAT
   * IPs, where each real person still spends only one cookieless request. Filterable; 0 disables the cap.
   */
  const COOKIELESS_RELEASE_CAP = 20;

  /**
   * Register the logged-out handler only. Logged-in visitors bypass the cache and are decided on the page path.
   */
  public static function register(): void {
    add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
  }

  /**
   * Decide server-side (against the subject ledger) whether to release the post body, and return it when allowed.
   *
   * No nonce: a page-embedded nonce goes stale in cache. CSRF is mitigated by the same-origin check. Drain resistance
   * is layered: a cookie-bearing caller is capped by its own server-side ledger (anonymous_limit distinct posts); a
   * cookieless caller (a fresh ledger every request) is additionally capped per-IP (COOKIELESS_RELEASE_CAP) and by the
   * per-IP/global rate limits. This BOUNDS scripted extraction rather than eliminating it — a device-durable identity
   * (the Pro tier, via the subject-key filter) is what fully closes it. Responses are same-origin JSON.
   */
  public static function handle(): void {
    nocache_headers();

    if ( self::is_cross_origin() ) {
      wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
    }

    $op = (string) filter_input( INPUT_POST, 'op' );
    if ( ! in_array( $op, self::OPS, true ) ) {
      wp_send_json_error( array( 'code' => 'bad_request' ), 400 );
    }

    $post_id = (int) filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
    if ( $post_id <= 0 ) {
      wp_send_json_error( array( 'code' => 'bad_request' ), 400 );
    }

    if ( self::is_rate_limited() ) {
      wp_send_json_error( array( 'code' => 'rate_limited' ), 429 );
    }

    // Gate eligibility and the cookieless cap BEFORE evaluate_sample(), which has side effects (it
    // mints the subject cookie and records the view). Order matters:
    //   1. is_releasable_sample() is a pure check — ineligible posts are rejected without charging the cap.
    //   2. A caller WITHOUT an active ledger is the drain vector — a fresh visitor, or a replayed/reset/expired cookie
    //      whose ledger is empty. Cap-exemption keys off holding an active ledger, NOT merely presenting a cookie, so
    //      a replayed cookie cannot dodge the cap. If over the per-IP hourly cap we deny HERE, before any cookie is
    //      minted or view recorded — nothing for the caller to keep and retry with.
    // cookieless_over_cap() increments atomically, so concurrent such releases cannot all slip under the cap.
    if ( ! Memberful_Metering_Access::is_releasable_sample( $post_id ) ) {
      wp_send_json_success(
        array(
          'released'  => false,
          'remaining' => 0,
        )
      );
    }

    $subject    = Memberful_Metering_Storage::current_subject();
    $has_ledger = Memberful_Metering_Access::subject_has_active_views( $subject );

    if ( ! $has_ledger && self::cookieless_over_cap() ) {
      wp_send_json_success(
        array(
          'released'  => false,
          'remaining' => 0,
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

  /**
   * Whether the request carries an Origin/Referer that is present and does NOT match this site. A missing header is
   * not treated as cross-origin (some browsers/privacy modes omit it); the rate limits and ledger cover that case.
   *
   * @return bool
   */
  private static function is_cross_origin(): bool {
    $source = '';
    if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
      $source = (string) wp_unslash( $_SERVER['HTTP_ORIGIN'] );
    } elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
      $source = (string) wp_unslash( $_SERVER['HTTP_REFERER'] );
    }

    if ( '' === $source ) {
      return false;
    }

    $source_host = wp_parse_url( $source, PHP_URL_HOST );
    $site_host   = wp_parse_url( home_url(), PHP_URL_HOST );

    if ( ! is_string( $source_host ) || ! is_string( $site_host ) ) {
      return true;
    }

    return strtolower( $source_host ) !== strtolower( $site_host );
  }

  /**
   * Coarse per-IP and site-wide rate limiting via transient counters. Bounds scripted enumeration/drain of the
   * protected catalogue independently of the per-subject ledger cap.
   *
   * @return bool True when the caller should be rejected with 429.
   */
  private static function is_rate_limited(): bool {
    $ip_key     = 'mbf_mtr_rl_' . hash( 'sha256', self::client_ip() . wp_salt( 'auth' ) );
    $global_key = 'mbf_mtr_rl_global';

    // Increment first, then compare the returned value: concurrent requests get distinct atomic counts, so they cannot
    // all observe an under-limit value and slip through. Over-limit requests still increment, which keeps them blocked.
    $ip_hits     = Memberful_Metering_Storage::incr_counter( $ip_key, MINUTE_IN_SECONDS );
    $global_hits = Memberful_Metering_Storage::incr_counter( $global_key, MINUTE_IN_SECONDS );

    return ( $ip_hits > self::RATE_PER_IP || $global_hits > self::RATE_GLOBAL );
  }

  /**
   * The caller's IP from REMOTE_ADDR (the only source not attacker-spoofable without proxy access), validated.
   *
   * @return string
   */
  private static function client_ip(): string {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
  }

  /**
   * Atomically count this cookieless request against the client IP's hourly quota and report whether the quota is now
   * exceeded. Incrementing as part of the check (rather than checking, then incrementing only after release) means
   * concurrent cookieless requests get distinct counts and cannot all slip under the cap.
   *
   * @return bool
   */
  private static function cookieless_over_cap(): bool {
    /**
     * Filter the per-IP hourly cap on cookieless protected-body releases. Return 0 to disable the cap.
     *
     * @param int $cap Default COOKIELESS_RELEASE_CAP.
     */
    $cap = (int) apply_filters( 'memberful_metering_cookieless_release_cap', self::COOKIELESS_RELEASE_CAP );

    if ( $cap <= 0 ) {
      return false;
    }

    return Memberful_Metering_Storage::incr_counter( self::cookieless_key(), HOUR_IN_SECONDS ) > $cap;
  }

  /**
   * Transient key for the per-IP cookieless-release counter (IP hashed with a site salt).
   *
   * @return string
   */
  private static function cookieless_key(): string {
    return 'mbf_mtr_cl_' . hash( 'sha256', self::client_ip() . wp_salt( 'auth' ) );
  }
}
