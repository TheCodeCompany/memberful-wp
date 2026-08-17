<?php if ( ! empty( $subscriptions ) || ! empty( $products ) ) : ?>
  <div class="memberful-restrict-access">
    <div class="memberful-restrict-access-options">
      <h4 style="font-size: 13px;"><?php _e( 'Who has access?', 'memberful' ); ?></h4>
      <?php memberful_wp_render( 'acl_selection', compact( 'subscriptions', 'products', 'viewable_by_any_registered_users', 'viewable_by_anybody_subscribed_to_a_plan' ) ); ?>
      <?php if ( isset( $wprm_recipe_cards_locked ) ) : ?>
        <div class="memberful-wprm-recipe-card-protection">
          <h4 style="font-size: 13px;"><?php _e( 'Recipe cards', 'memberful' ); ?></h4>
          <input type="hidden" name="memberful_wprm_lock_recipe_cards_present" value="1" />
          <label>
            <input type="checkbox" name="memberful_wprm_lock_recipe_cards" value="1" <?php checked( $wprm_recipe_cards_locked ); ?> />
            <?php _e( 'Lock WP Recipe Maker recipe cards in this post', 'memberful' ); ?>
          </label>
          <p class="description">
            <?php _e( 'When this post is protected by Memberful, show the recipe card layout while hiding protected recipe details from non-members.', 'memberful' ); ?>
          </p>
        </div>
      <?php endif; ?>
    </div>
    <div class="memberful-marketing-content">
      <?php

      $editor_id = 'memberful_marketing_content';
      $settings  = array();
      wp_editor( $marketing_content , $editor_id, $settings );

      ?>
      <div class="memberful-marketing-content-description">
        <a href="<?php echo admin_url('/options-general.php?page=memberful_options&subpage=global_marketing');?>">
          Click Here
        </a>
         to manage global marketing content.
      </div>
    </div>
  </div>
<?php else: ?>
  <div>
    <p><em><?php _e( "We couldn't find any products or subscriptions in your Memberful account. You'll need to add some before you can restrict access.", 'memberful' ); ?></em></p>
  </div>
<?php endif; ?>
