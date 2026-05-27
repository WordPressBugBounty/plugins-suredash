<?php
/**
 * SearchHighlighter — wraps matched search terms with <mark> tags.
 *
 * Used by the /suredash/v1/search endpoint to return pre-highlighted
 * titles and excerpts so the frontend can render them as raw HTML.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Services\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchHighlighter.
 *
 * @since 1.3.0
 */
class SearchHighlighter {
	/**
	 * Wrap occurrences of the search term with <mark> tags.
	 *
	 * Input is expected to be plain text (no HTML). If HTML is present,
	 * it will be stripped first so we never inject <mark> inside attribute
	 * values or tag names.
	 *
	 * @param string $text  Plain-text input.
	 * @param string $term  Search term.
	 * @return string Text with <mark> wrapping the term (case-insensitive).
	 */
	public static function highlight( $text, $term ) {
		$text = (string) $text;
		$term = trim( (string) $term );

		if ( $text === '' || $term === '' ) {
			return $text;
		}

		// Defensive — we want pre-highlighted plain text only.
		$text = wp_strip_all_tags( $text );

		$pattern = '/(' . preg_quote( $term, '/' ) . ')/iu';

		$result = preg_replace( $pattern, '<mark>$1</mark>', $text ); // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative -- Pattern is compile-time safe; no /e modifier; needed for case-insensitive highlight.

		// preg_replace returns null on error — fall back to the original text.
		return is_string( $result ) ? $result : $text;
	}
}
