<div class="wrap">
  <?php memberful_wp_render('option_tabs', array('active' => 'search_settings')); ?>
  <?php memberful_wp_render('flash'); ?>
  
  <div id="memberful-wrap">
    <div class="postbox memberful-postbox">
      <h1>Search Settings</h1>
      <p>Configure how protected content appears in WordPress search results.</p>

      <form method="POST" action="<?php echo esc_url(memberful_wp_plugin_search_settings_url(TRUE)); ?>">
        <?php memberful_wp_nonce_field( 'memberful_options' ); ?>
        
        <div class="memberful-search-main-setting">
          <h3>Search Protection</h3>
          <p>
            <label for="include_protected_in_search_checkbox">
              <input id="include_protected_in_search_checkbox" class="memberful-label__checkbox--multiline" type="checkbox" name="memberful_include_protected_in_search" <?php if( $include_protected_in_search): ?>checked="checked"<?php endif; ?>>
              <span class="memberful-label__text--multiline">Include protected content in search results (with access warnings).</span>
            </label>
          </p>
          <div class="memberful-search-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>⚠️ Security Warning:</strong> Enabling this option will allow non-members to see protected content titles and excerpts in search results. While the actual content remains protected, this may expose sensitive information. Only enable this if you want to improve content discoverability for conversion purposes.
          </div>
        </div>
        
        <div id="search-options" style="<?php echo $include_protected_in_search ? '' : 'display: none;'; ?>">
          <div class="memberful-search-options" style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">
            <h3>Search Display Options</h3>
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
              
            </div>
            
            <div class="memberful-search-link-settings" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
              <h4 style="margin-top: 0;">Link Destination Settings</h4>
              <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 10px; margin: 10px 0; border-radius: 4px;">
                <strong>Note:</strong> If the fields below are left blank, the system will use the default settings from Memberful.
              </div>
              
              <div style="display: flex; gap: 20px; margin-top: 15px;">
                <div style="flex: 1;">
                  <label for="signup_text">
                    <span class="memberful-label__text--multiline">Signup Label:</span><br>
                    <input type="text" id="signup_text" name="memberful_search_signup_text" value="<?php echo esc_attr( $search_signup_text ); ?>" style="width: 100%; margin-top: 5px;" placeholder="Sign up to access →">
                  </label>
                  <br><br>
                  <label for="custom_signup_url">
                    <span class="memberful-label__text--multiline">Signup URL:</span><br>
                    <input type="url" id="custom_signup_url" name="memberful_search_custom_signup_url" value="<?php echo esc_attr( $search_custom_signup_url ); ?>" style="width: 100%; margin-top: 5px;" placeholder="https://yoursite.com/signup">
                  </label>
                </div>
                <div style="flex: 1;">
                  <label for="login_text">
                    <span class="memberful-label__text--multiline">Login Label:</span><br>
                    <input type="text" id="login_text" name="memberful_search_login_text" value="<?php echo esc_attr( $search_login_text ); ?>" style="width: 100%; margin-top: 5px;" placeholder="Sign in to access →">
                  </label>
                  <br><br>
                  <label for="custom_login_url">
                    <span class="memberful-label__text--multiline">Login URL:</span><br>
                    <input type="url" id="custom_login_url" name="memberful_search_custom_login_url" value="<?php echo esc_attr( $search_custom_login_url ); ?>" style="width: 100%; margin-top: 5px;" placeholder="https://yoursite.com/login">
                  </label>
                </div>
              </div>
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
  const mainCheckbox = document.getElementById('include_protected_in_search_checkbox');
  const searchOptions = document.getElementById('search-options');
  
  function toggleSearchOptions() {
    if (mainCheckbox.checked) {
      searchOptions.style.display = 'block';
    } else {
      searchOptions.style.display = 'none';
    }
  }
  
  mainCheckbox.addEventListener('change', toggleSearchOptions);
  toggleSearchOptions(); // Run on page load
});
</script>
