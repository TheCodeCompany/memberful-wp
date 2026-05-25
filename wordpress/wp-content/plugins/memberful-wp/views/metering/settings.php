<?php
/**
 * Metering settings view.
 *
 * @package memberful-wp
 *
 * @var array  $config
 * @var array  $fields
 * @var array  $operators
 * @var array  $post_type_options
 * @var string $form_target
 */

$rules = isset( $config['rules'] ) && is_array( $config['rules'] ) ? array_values( $config['rules'] ) : array();

?>
<div class="wrap">
  <?php memberful_wp_render( 'option_tabs', array( 'active' => 'metering' ) ); ?>
  <?php memberful_wp_render( 'flash' ); ?>

  <form method="POST" action="<?php echo esc_url( $form_target ); ?>">
    <?php memberful_wp_nonce_field( 'memberful_options' ); ?>

    <div class="memberful-bulk-apply-box memberful-bulk-apply-box--wide memberful-metering-settings">
      <h3><?php esc_html_e( 'Metering', 'memberful' ); ?></h3>
      <p><?php esc_html_e( 'Limit free access to matching posts for anonymous visitors and registered free members.', 'memberful' ); ?></p>

      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row"><?php esc_html_e( 'Enable metering', 'memberful' ); ?></th>
            <td>
              <label for="memberful_metering_enabled">
                <input
                  id="memberful_metering_enabled"
                  type="checkbox"
                  name="memberful_metering[enabled]"
                  value="1"
                  <?php checked( ! empty( $config['enabled'] ) ); ?>
                >
                <?php esc_html_e( 'Turn on metering for posts that match the rules below.', 'memberful' ); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="memberful_metering_period_days"><?php esc_html_e( 'Period', 'memberful' ); ?></label></th>
            <td>
              <input
                id="memberful_metering_period_days"
                type="number"
                min="1"
                class="small-text"
                name="memberful_metering[period_days]"
                value="<?php echo esc_attr( $config['period_days'] ); ?>"
              >
              <?php esc_html_e( 'days', 'memberful' ); ?>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="memberful_metering_anonymous_limit"><?php esc_html_e( 'Anonymous visitor limit', 'memberful' ); ?></label></th>
            <td>
              <input
                id="memberful_metering_anonymous_limit"
                type="number"
                min="0"
                max="<?php echo esc_attr( Memberful_Metering_Storage::MAX_VIEWS ); ?>"
                class="small-text"
                name="memberful_metering[anonymous_limit]"
                value="<?php echo esc_attr( $config['anonymous_limit'] ); ?>"
              >
              <p class="description"><?php esc_html_e( 'Number of matching posts anonymous visitors can read during the period.', 'memberful' ); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="memberful_metering_registered_limit"><?php esc_html_e( 'Registered free member limit', 'memberful' ); ?></label></th>
            <td>
              <input
                id="memberful_metering_registered_limit"
                type="number"
                min="0"
                max="<?php echo esc_attr( Memberful_Metering_Storage::MAX_VIEWS ); ?>"
                class="small-text"
                name="memberful_metering[registered_limit]"
                value="<?php echo esc_attr( $config['registered_limit'] ); ?>"
              >
              <p class="description"><?php esc_html_e( 'Number of matching posts registered free members can read during the period.', 'memberful' ); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php esc_html_e( 'Protected posts', 'memberful' ); ?></th>
            <td>
              <label for="memberful_metering_apply_to_protected_posts">
                <input
                  id="memberful_metering_apply_to_protected_posts"
                  type="checkbox"
                  name="memberful_metering[apply_to_protected_posts]"
                  value="1"
                  <?php checked( ! empty( $config['apply_to_protected_posts'] ) ); ?>
                >
                <?php esc_html_e( 'Allow matching protected posts to be sampled before the meter trips.', 'memberful' ); ?>
              </label>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="memberful-bulk-apply-box memberful-bulk-apply-box--wide memberful-metering-rules">
      <h3><?php esc_html_e( 'Metering rules', 'memberful' ); ?></h3>
      <p><?php esc_html_e( 'A post is metered when any rule group matches. Empty rules mean no posts are metered.', 'memberful' ); ?></p>

      <div id="memberful-metering-rule-groups" data-next-group-index="<?php echo esc_attr( count( $rules ) ); ?>">
        <p id="memberful-metering-empty-rules" class="description"<?php if ( ! empty( $rules ) ) echo ' style="display:none;"'; ?>>
          <?php esc_html_e( 'No rule groups configured.', 'memberful' ); ?>
        </p>

        <?php foreach ( $rules as $group_index => $rule_group ) : ?>
          <?php
          $conditions = isset( $rule_group['conditions'] ) && is_array( $rule_group['conditions'] ) ? array_values( $rule_group['conditions'] ) : array();
          $match      = isset( $rule_group['match'] ) && in_array( $rule_group['match'], Memberful_Metering_Config::MATCH_TYPES, true ) ? $rule_group['match'] : 'all';
          ?>
          <div class="memberful-metering-rule-group" data-group-index="<?php echo esc_attr( $group_index ); ?>" data-next-condition-index="<?php echo esc_attr( count( $conditions ) ); ?>">
            <h4>
              <?php esc_html_e( 'Rule group', 'memberful' ); ?>
              <button type="button" class="button-link memberful-metering-remove-group"><?php esc_html_e( 'Remove', 'memberful' ); ?></button>
            </h4>
            <p>
              <?php esc_html_e( 'Meter content when', 'memberful' ); ?>
              <select name="memberful_metering[rules][<?php echo esc_attr( $group_index ); ?>][match]">
                <option value="all" <?php selected( 'all', $match ); ?>><?php esc_html_e( 'all', 'memberful' ); ?></option>
                <option value="any" <?php selected( 'any', $match ); ?>><?php esc_html_e( 'any', 'memberful' ); ?></option>
              </select>
              <?php esc_html_e( 'of these conditions are true:', 'memberful' ); ?>
            </p>

            <table class="widefat striped memberful-metering-conditions">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Field', 'memberful' ); ?></th>
                  <th><?php esc_html_e( 'Operator', 'memberful' ); ?></th>
                  <th><?php esc_html_e( 'Value(s)', 'memberful' ); ?></th>
                  <th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'memberful' ); ?></span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $conditions as $condition_index => $condition ) : ?>
                  <?php
                  $field             = isset( $condition['field'] ) && isset( $fields[ $condition['field'] ] ) ? $condition['field'] : 'post_type';
                  $field_operators   = isset( $operators[ $field ] ) ? $operators[ $field ] : array();
                  $operator          = isset( $condition['operator'] ) && isset( $field_operators[ $condition['operator'] ] ) ? $condition['operator'] : key( $field_operators );
                  $values            = isset( $condition['values'] ) && is_array( $condition['values'] ) ? $condition['values'] : array();
                  $field_name_prefix = 'memberful_metering[rules][' . $group_index . '][conditions][' . $condition_index . ']';
                  ?>
                  <tr data-condition-index="<?php echo esc_attr( $condition_index ); ?>">
                    <td>
                      <select class="memberful-metering-condition-field" name="<?php echo esc_attr( $field_name_prefix ); ?>[field]">
                        <?php foreach ( $fields as $field_key => $field_label ) : ?>
                          <option value="<?php echo esc_attr( $field_key ); ?>" <?php selected( $field_key, $field ); ?>><?php echo esc_html( $field_label ); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="memberful-metering-condition-operator">
                      <select name="<?php echo esc_attr( $field_name_prefix ); ?>[operator]">
                        <?php foreach ( $field_operators as $operator_key => $operator_label ) : ?>
                          <option value="<?php echo esc_attr( $operator_key ); ?>" <?php selected( $operator_key, $operator ); ?>><?php echo esc_html( $operator_label ); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="memberful-metering-condition-values">
                      <?php if ( 'post_type' === $field ) : ?>
                        <select multiple="multiple" name="<?php echo esc_attr( $field_name_prefix ); ?>[values][]" class="memberful-metering-values--post-type">
                          <?php foreach ( $post_type_options as $post_type_name => $post_type_label ) : ?>
                            <option value="<?php echo esc_attr( $post_type_name ); ?>" <?php selected( in_array( $post_type_name, $values, true ) ); ?>><?php echo esc_html( $post_type_label ); ?></option>
                          <?php endforeach; ?>
                        </select>
                      <?php else : ?>
                        <input type="text" class="regular-text" name="<?php echo esc_attr( $field_name_prefix ); ?>[values]" value="<?php echo esc_attr( implode( ', ', $values ) ); ?>">
                      <?php endif; ?>
                    </td>
                    <td>
                      <button type="button" class="button-link-delete memberful-metering-remove-condition"><?php esc_html_e( 'Remove', 'memberful' ); ?></button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p><button type="button" class="button memberful-metering-add-condition"><?php esc_html_e( 'Add condition', 'memberful' ); ?></button></p>
          </div>
        <?php endforeach; ?>
      </div>

      <p><button type="button" class="button" id="memberful-metering-add-group"><?php esc_html_e( 'Add rule group', 'memberful' ); ?></button></p>
    </div>

    <?php submit_button( __( 'Save Changes', 'memberful' ), 'primary', 'save_metering' ); ?>
  </form>
</div>