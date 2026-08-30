<?php
/**
 * Beaver Builder integration.
 *
 * Adds Memberful visibility settings to the Advanced tab of Beaver Builder rows, columns, and modules, mirroring the
 * Gutenberg block visibility controls in src/block-editor.php.
 */

add_action( 'plugins_loaded', 'memberful_wp_beaver_builder_init' );

function memberful_wp_beaver_builder_init() {
  if ( ! class_exists( 'FLBuilderModel' ) ) {
    return;
  }

  add_filter( 'fl_builder_register_settings_form', 'memberful_wp_beaver_builder_add_visibility_section', 10, 2 );
  add_filter( 'fl_builder_is_node_visible', 'memberful_wp_beaver_builder_is_node_visible', 10, 2 );
  add_filter( 'fl_builder_render_module_html_content', 'memberful_wp_beaver_builder_filter_editor_export_content', 10, 3 );
}

/**
 * Add the Memberful Visibility section to the Advanced tab of every Beaver Builder row, column, and module settings form.
 *
 * @param array  $form The settings form config.
 * @param string $id   The form id ("row", "col", or "module_advanced").
 * @return array The settings form config.
 */
function memberful_wp_beaver_builder_add_visibility_section( array $form, string $id ): array {
  if ( 'row' === $id || 'col' === $id ) {
    $form['tabs']['advanced']['sections']['memberful_visibility'] = memberful_wp_beaver_builder_visibility_section();
  }

  if ( 'module_advanced' === $id ) {
    $form['sections']['memberful_visibility'] = memberful_wp_beaver_builder_visibility_section();
  }

  return $form;
}

/**
 * The Memberful Visibility settings section config.
 *
 * @return array The section config.
 */
function memberful_wp_beaver_builder_visibility_section(): array {
  $plan_options = array();

  foreach ( memberful_subscription_plans() as $plan_id => $plan ) {
    if ( isset( $plan['name'] ) ) {
      // Beaver Builder expects pre-escaped option labels.
      $plan_options[ (string) $plan_id ] = esc_html( $plan['name'] );
    }
  }

  return array(
    'title'  => __( 'Memberful Visibility', 'memberful' ),
    'fields' => array(
      'memberful_visibility'       => array(
        'type'    => 'select',
        'label'   => __( 'Applies to', 'memberful' ),
        'default' => '',
        'options' => array(
          ''          => __( 'Everyone', 'memberful' ),
          'logged_in' => __( 'Any logged in member', 'memberful' ),
          'specific'  => __( 'Members on specific plans', 'memberful' ),
        ),
        'toggle'  => array(
          'logged_in' => array( 'fields' => array( 'memberful_visibility_hide' ) ),
          'specific'  => array( 'fields' => array( 'memberful_visibility_hide', 'memberful_visibility_plans' ) ),
        ),
        'preview' => array( 'type' => 'none' ),
      ),
      'memberful_visibility_hide'  => array(
        'type'    => 'select',
        'label'   => __( 'Action', 'memberful' ),
        'default' => '',
        'options' => array(
          ''  => __( 'Show', 'memberful' ),
          '1' => __( 'Hide', 'memberful' ),
        ),
        'preview' => array( 'type' => 'none' ),
      ),
      'memberful_visibility_plans' => array(
        'type'         => 'select',
        'label'        => __( 'Plans', 'memberful' ),
        'options'      => $plan_options,
        'multi-select' => true,
        'help'         => __( 'Applies to members with an active subscription to at least one of the selected plans. If no plans are selected, the element stays visible to all logged in members.', 'memberful' ),
        'preview'      => array( 'type' => 'none' ),
      ),
    ),
  );
}

/**
 * Apply the Memberful visibility rule when Beaver Builder decides whether to render a row, column, or module.
 *
 * @param bool   $is_visible Whether Beaver Builder considers the node visible.
 * @param object $node       The node.
 * @return bool Whether the node should be rendered.
 */
function memberful_wp_beaver_builder_is_node_visible( bool $is_visible, object $node ): bool {
  if ( ! $is_visible ) {
    return $is_visible;
  }

  // Always show nodes while editing in the builder UI.
  if ( FLBuilderModel::is_builder_active() ) {
    return $is_visible;
  }

  $rule = isset( $node->settings->memberful_visibility ) ? $node->settings->memberful_visibility : '';

  if ( 'logged_in' === $rule ) {
    return memberful_wp_beaver_builder_logged_in_rule_allows( $node->settings );
  }

  if ( 'specific' === $rule ) {
    return memberful_wp_beaver_builder_specific_plans_rule_allows( $node->settings );
  }

  return $is_visible;
}

/**
 * Any logged in member rule, optionally reversed by the hide flag.
 *
 * @param object $settings The node settings.
 * @return bool Whether the node should be rendered.
 */
function memberful_wp_beaver_builder_logged_in_rule_allows( object $settings ): bool {
  if ( ! empty( $settings->memberful_visibility_hide ) ) {
    return ! is_user_logged_in();
  }

  return is_user_logged_in();
}

/**
 * Specific plans rule, optionally reversed by the hide flag.
 *
 * @param object $settings The node settings.
 * @return bool Whether the node should be rendered.
 */
function memberful_wp_beaver_builder_specific_plans_rule_allows( object $settings ): bool {
  if ( ! is_user_logged_in() ) {
    return FALSE;
  }

  $plans = isset( $settings->memberful_visibility_plans ) ? array_filter( (array) $settings->memberful_visibility_plans ) : array();

  // No plans configured - fall back to rendering the node unmodified.
  if ( empty( $plans ) ) {
    return TRUE;
  }

  $has_plan = memberful_wp_user_has_subscription_to_plans( wp_get_current_user()->ID, $plans );

  if ( ! empty( $settings->memberful_visibility_hide ) ) {
    // Hide the node if the user has any of the specific plans.
    return ! $has_plan;
  }

  return $has_plan;
}

/**
 * Keep restricted module content out of the plain-text fallback that Beaver Builder saves to `post_content` on publish.
 *
 * Beaver Builder skips nodes with its native visibility rules when building that fallback (FLBuilder::render_editor_content()), but it doesn't know
 * about our fields, so we drop any module carrying a Memberful rule (regardless of who is publishing) to keep restricted
 * text out of search, excerpts, and feeds.
 *
 * @param string $content  The rendered module HTML.
 * @param string $type     The module type.
 * @param object $settings The module settings.
 * @return string The module HTML, or an empty string during the editor export.
 */
function memberful_wp_beaver_builder_filter_editor_export_content( string $content, string $type, object $settings ): string {
  if ( ! memberful_wp_beaver_builder_doing_editor_export() ) {
    return $content;
  }

  if ( ! empty( $settings->memberful_visibility ) ) {
    return '';
  }

  /**
  * Modules nested inside a Memberful-restricted row or column also stay out
  * of the fallback. The exporter renders modules without their node ids, so
  * match them against the layout tree by their saved settings values.
  */
  foreach ( memberful_wp_beaver_builder_restricted_subtree_module_settings() as $module_settings ) {
    if ( memberful_wp_beaver_builder_settings_match( $module_settings, $settings ) ) {
      return '';
    }
  }

  return $content;
}

/**
 * The saved settings of every module nested inside a node that carries a Memberful visibility rule.
 *
 * @return array Arrays of module settings.
 */
function memberful_wp_beaver_builder_restricted_subtree_module_settings(): array {
  static $restricted = null;

  if ( null !== $restricted ) {
    return $restricted;
  }

  $restricted = array();

  // Walk the tree through get_nodes() rather than the raw layout data, so children of global template rows and columns are resolved too.
  foreach ( FLBuilderModel::get_nodes( 'row' ) as $row ) {
    memberful_wp_beaver_builder_collect_restricted_module_settings(
      $row,
      ! empty( $row->settings->memberful_visibility ),
      $restricted
    );
  }

  return $restricted;
}

/**
 * Collect the settings of modules below a node whenever any ancestor carries a Memberful visibility rule.
 *
 * @param object $parent              The node whose children to walk.
 * @param bool   $ancestor_restricted Whether any ancestor carries a Memberful rule.
 * @param array  $restricted          Collected module settings, by reference.
 * @return void
 */
function memberful_wp_beaver_builder_collect_restricted_module_settings( object $parent, bool $ancestor_restricted, array &$restricted ): void {
  foreach ( FLBuilderModel::get_nodes( null, $parent ) as $node ) {
    if ( $ancestor_restricted && 'module' === ( $node->type ?? '' ) ) {
      $restricted[] = (array) $node->settings;
    }

    memberful_wp_beaver_builder_collect_restricted_module_settings(
      $node,
      $ancestor_restricted || ! empty( $node->settings->memberful_visibility ),
      $restricted
    );
  }
}

/**
 * Whether a module's saved settings are a subset of the merged settings the exporter is rendering with.
 *
 * The exporter merges saved settings over the module defaults, so every saved value survives into the merged object.
 *
 * @param array  $saved  The module settings from the layout tree.
 * @param object $merged The merged settings passed to the render filter.
 * @return bool
 */
function memberful_wp_beaver_builder_settings_match( array $saved, object $merged ): bool {
  foreach ( $saved as $key => $value ) {
    // The exporter forces `crop` to false on Photo modules before rendering.
    if ( 'crop' === $key ) {
      continue;
    }

    if ( ! property_exists( $merged, $key ) || $merged->$key != $value ) {
      return FALSE;
    }
  }

  return TRUE;
}

/**
 * Whether the current request is a Beaver Builder save action, which renders the editor fallback content saved to
 * `post_content`.
 *
 * @return bool
 */
function memberful_wp_beaver_builder_doing_editor_export(): bool {
  $save_actions = array( 'save_layout', 'save_draft' );

  if ( ! empty( $_REQUEST['fl_action'] ) ) {
    return in_array( $_REQUEST['fl_action'], $save_actions, true );
  }

  $post_data = FLBuilderModel::get_post_data();

  return ! empty( $post_data['fl_action'] ) && in_array( $post_data['fl_action'], $save_actions, true );
}
