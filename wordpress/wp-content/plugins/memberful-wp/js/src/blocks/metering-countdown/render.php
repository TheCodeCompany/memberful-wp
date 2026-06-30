<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

$post_id  = get_queried_object_id();
$template = isset( $attributes['template'] ) ? (string) $attributes['template'] : '';

if ( '' === trim( $template ) ) {
	return;
}

if ( Memberful_Metering_Access::RENDER_NONE !== Memberful_Metering_Access::current_anon_mode( $post_id ) ) {
	printf(
		'<p %s hidden>%s</p>',
		get_block_wrapper_attributes(
			array(
				'data-memberful-countdown' => '1',
				'data-memberful-template'  => $template,
			)
		),
		esc_html( str_replace( '{count}', '', $template ) )
	);
	return;
}

// Logged-in / server-known path: render the count directly.
if ( Memberful_Metering_Access::DECISION_ALLOW_SAMPLE !== Memberful_Metering_Access::get_current_decision( $post_id ) ) {
	return;
}

$message = str_replace(
	'{count}',
	number_format_i18n( Memberful_Metering_Access::get_current_remaining( $post_id ) ),
	$template
);

if ( '' === trim( $message ) ) {
	return;
}

printf(
	'<p %s>%s</p>',
	get_block_wrapper_attributes(),
	wp_kses_post( $message )
);
