<div class="wrap">
  <?php memberful_wp_render('option_tabs', array('active' => 'settings')); ?>
  <?php memberful_wp_render('flash'); ?>
  <div id="memberful-wrap">
    <div id="memberful-registered" class="postbox">
      <h1><?php _e( 'Integration Active', 'memberful' ); ?></h1>
      <h2><?php printf( __( 'Syncing %d plans, %d downloads and %d podcasts.', 'memberful' ), count( $subscriptions ), count( $products ), count( $feeds ) ); ?></h2>
      <p><?php printf( __( '<a href="%s">Sign in to your Memberful account</a> to manage products, subscriptions, members, and orders.' ), memberful_url( 'admin' ) ) ?></p>
      <form method="POST" action="<?php echo esc_url(memberful_wp_plugin_settings_url(TRUE)); ?>">
        <?php memberful_wp_nonce_field( 'memberful_options' ); ?>
        <button type="submit" name="manual_sync" class="button action"><?php _e( 'Run manual sync', 'memberful' ); ?></button>
        <button type="submit" name="reset_plugin" class="memberful-red-button"><?php _e( 'Disconnect', 'memberful' ); ?></button>
      </form>
    </div>
    <div class="memberful-protect-help postbox">
      <?php _e( "To protect content, edit a post or page and look for the <em>Memberful: Restrict Access</em> box.", 'memberful' ); ?>
    </div>

    <div class="postbox memberful-postbox">
      <h1>Settings</h1>
      <p>Customize the appearance and behavior of the Memberful plugin.</p>

      <form method="POST" action="<?php echo esc_url(memberful_wp_plugin_settings_url(TRUE)); ?>">
        <?php memberful_wp_nonce_field( 'memberful_options' ); ?>
        <p>
          <label for="extended_login_period_checkbox">
            <input id="extended_login_period_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="extend_auth_cookie_expiration" <?php if( $extend_auth_cookie_expiration ): ?>checked="checked"<?php endif; ?>>
            <span class="memberful-label__text--multiline">Keep all WordPress users logged in for 1 year.</span>
          </label>
        </p>
        <p>
          <label for="hide_admin_toolbar_checkbox">
            <input id="hide_admin_toolbar_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="memberful_hide_admin_toolbar" <?php if( $hide_admin_toolbar): ?>checked="checked"<?php endif; ?>>
            <span class="memberful-label__text--multiline">Hide the WordPress admin toolbar from members.</span>
          </label>
        </p>
        <p>
          <label for="block_dashboard_access_checkbox">
            <input id="block_dashboard_access_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="memberful_block_dashboard_access" <?php if( $block_dashboard_access): ?>checked="checked"<?php endif; ?>>
            <span class="memberful-label__text--multiline">Block WordPress dashboard access from members.</span>
          </label>
        </p>
        <p>
          <label for="filter_account_menu_items_checkbox">
            <input id="filter_account_menu_items_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="memberful_filter_account_menu_items" <?php if( $filter_account_menu_items): ?>checked="checked"<?php endif; ?>>
            <span class="memberful-label__text--multiline">Conditionally show "Sign in," "Sign out," and "Account" menu items based on members' signed-in status.</span>
          </label>
        </p>
        <p>
          <label for="auto_sync_display_names_checkbox">
            <input id="auto_sync_display_names_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="memberful_auto_sync_display_names" <?php if( $auto_sync_display_names): ?>checked="checked"<?php endif; ?>>
            <span class="memberful-label__text--multiline">Update display names in WordPress when members change their full name in Memberful.</span>
          </label>
        </p>
        <div class="memberful-search-settings" style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">
          <h3>Search Settings</h3>
          <p>
            <label for="include_protected_in_search_checkbox">
              <input id="include_protected_in_search_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="memberful_include_protected_in_search" <?php if( $include_protected_in_search): ?>checked="checked"<?php endif; ?>>
              <span class="memberful-label__text--multiline">Include protected content in search results (with access warnings).</span>
            </label>
          </p>
          <div class="memberful-search-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>⚠️ Security Warning:</strong> Enabling this option will allow non-members to see protected content titles and excerpts in search results. While the actual content remains protected, this may expose sensitive information. Only enable this if you want to improve content discoverability for conversion purposes.
          </div>
          
            <div class="memberful-search-options" style="margin-top: 15px; padding-left: 20px; border-left: 3px solid #e0e0e0;">
              <h4 style="margin-top: 0;">Search Display Options</h4>
              <p>
                <label for="show_search_disclaimer_checkbox">
                  <input id="show_search_disclaimer_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="memberful_show_search_disclaimer" <?php if( $show_search_disclaimer): ?>checked="checked"<?php endif; ?>>
                  <span class="memberful-label__text--multiline">Show disclaimer warning in search result excerpts for protected content.</span>
                </label>
              </p>
              
              <div class="memberful-search-customization" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <h4 style="margin-top: 0;">Customize Search Messages</h4>
                <p>
                  <label for="premium_label">
                    <span class="memberful-label__text--multiline">Premium Label:</span><br>
                    <input type="text" id="premium_label" name="memberful_search_premium_label" value="<?php echo esc_attr( $search_premium_label ); ?>" style="width: 100%; margin-top: 5px;" placeholder="Premium">
                    <small style="color: #666;">This appears next to protected content titles in search results.</small>
                  </label>
                </p>
                <p>
                  <label for="disclaimer_text">
                    <span class="memberful-label__text--multiline">Disclaimer Text:</span><br>
                    <textarea id="disclaimer_text" name="memberful_search_disclaimer_text" style="width: 100%; margin-top: 5px; height: 60px;" placeholder="This content requires a subscription to view."><?php echo esc_textarea( $search_disclaimer_text ); ?></textarea>
                    <small style="color: #666;">This appears in the disclaimer box for protected content.</small>
                  </label>
                </p>
                <p>
                  <label for="signup_text">
                    <span class="memberful-label__text--multiline">Signup Link Text:</span><br>
                    <input type="text" id="signup_text" name="memberful_search_signup_text" value="<?php echo esc_attr( $search_signup_text ); ?>" style="width: 100%; margin-top: 5px;" placeholder="Sign up to access →">
                    <small style="color: #666;">Text for the signup link in disclaimers.</small>
                  </label>
                </p>
                <p>
                  <label for="login_text">
                    <span class="memberful-label__text--multiline">Login Link Text:</span><br>
                    <input type="text" id="login_text" name="memberful_search_login_text" value="<?php echo esc_attr( $search_login_text ); ?>" style="width: 100%; margin-top: 5px;" placeholder="Sign in to access →">
                    <small style="color: #666;">Text for the login link in disclaimers.</small>
                  </label>
                </p>
              </div>
            <p>
              <label for="search_link_destination">
                <span class="memberful-label__text--multiline">Where should the "Sign up to access" link go?</span><br>
                <select id="search_link_destination" name="memberful_search_link_destination" style="margin-top: 5px;">
                  <option value="post" <?php selected( $search_link_destination, 'post' ); ?>>To the protected post/page</option>
                  <option value="custom_signup" <?php selected( $search_link_destination, 'custom_signup' ); ?>>To custom signup page</option>
                  <option value="custom_login" <?php selected( $search_link_destination, 'custom_login' ); ?>>To custom login page</option>
                </select>
              </label>
            </p>
            <div id="custom-url-fields" style="margin-top: 10px; <?php echo (in_array($search_link_destination, ['custom_signup', 'custom_login'])) ? '' : 'display: none;'; ?>">
              <p>
                <label for="custom_signup_url">
                  <span class="memberful-label__text--multiline">Custom Signup URL:</span><br>
                  <input type="url" id="custom_signup_url" name="memberful_search_custom_signup_url" value="<?php echo esc_attr( $search_custom_signup_url ); ?>" style="width: 100%; margin-top: 5px;" placeholder="https://yoursite.com/signup">
                </label>
              </p>
              <p>
                <label for="custom_login_url">
                  <span class="memberful-label__text--multiline">Custom Login URL:</span><br>
                  <input type="url" id="custom_login_url" name="memberful_search_custom_login_url" value="<?php echo esc_attr( $search_custom_login_url ); ?>" style="width: 100%; margin-top: 5px;" placeholder="https://yoursite.com/login">
                </label>
              </p>
            </div>
          </div>
        </div>
        <button type="submit" name="save_changes" class="button button-primary">Save Changes</button>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const destinationSelect = document.getElementById('search_link_destination');
  const customUrlFields = document.getElementById('custom-url-fields');
  
  function toggleCustomFields() {
    const value = destinationSelect.value;
    if (value === 'custom_signup' || value === 'custom_login') {
      customUrlFields.style.display = 'block';
    } else {
      customUrlFields.style.display = 'none';
    }
  }
  
  destinationSelect.addEventListener('change', toggleCustomFields);
  toggleCustomFields(); // Run on page load
});
</script>
