<?php
/**
 * SearchExcerptBuilder — builds a contextual excerpt around the first
 * match of a search term inside post/comment content.
 *
 * Strategy (see architecture doc §5.4):
 *   1. Strip shortcodes + HTML from the raw content.
 *   2. Find the first case-insensitive occurrence of the term.
 *   3. Extract ~30 words before + ~50 words after the match.
 *   4. Wrap all occurrences with <mark>...</mark>.
 *   5. Prepend "…" if the window doesn't start at the beginning and
 *      append "…" if it doesn't end at the end.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Services\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchExcerptBuilder.
 *
 * @since 1.3.0
 */
class SearchExcerptBuilder {
	public const WORDS_BEFORE = 30;
	public const WORDS_AFTER  = 50;

	/**
	 * Build a contextual excerpt with the term highlighted.
	 *
	 * If the term isn't found in the content (e.g., title-only matches),
	 * the first WORDS_BEFORE + WORDS_AFTER words are returned as a
	 * fallback so callers always get a non-empty string for non-empty
	 * content.
	 *
	 * @param string $content Raw post/comment content.
	 * @param string $term    Search term.
	 * @return string HTML-safe excerpt with <mark> tags.
	 */
	public static function build( $content, $term ) {
		$content = (string) $content;
		$term    = trim( (string) $term );

		if ( $content === '' ) {
			return '';
		}

		$plain = self::normalize( $content );

		if ( $plain === '' ) {
			return '';
		}

		$window_before = '';
		$window_after  = $plain;
		$found_pos     = false;

		if ( $term !== '' ) {
			$found_pos = mb_stripos( $plain, $term );
		}

		if ( $found_pos !== false ) {
			$window_before = mb_substr( $plain, 0, (int) $found_pos );
			$window_after  = mb_substr( $plain, (int) $found_pos );
		}

		$before_words = self::take_last_words( $window_before, self::WORDS_BEFORE );
		$after_words  = self::take_first_words( $window_after, self::WORDS_AFTER );

		$excerpt = trim( $before_words['text'] . $after_words['text'] );

		if ( $excerpt === '' ) {
			return '';
		}

		$excerpt = SearchHighlighter::highlight( $excerpt, $term );

		if ( $before_words['truncated'] ) {
			$excerpt = '… ' . $excerpt;
		}
		if ( $after_words['truncated'] ) {
			$excerpt .= ' …';
		}

		return $excerpt;
	}

	/**
	 * Strip shortcodes and HTML and collapse whitespace.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private static function normalize( $content ) {
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $content ) ); // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative -- Whitespace collapse; no /e modifier.
	}

	/**
	 * Take the last N words from a string.
	 *
	 * @param string $text  Source text.
	 * @param int    $count Word count.
	 * @return array{text:string,truncated:bool}
	 */
	private static function take_last_words( $text, $count ) {
		$text = trim( (string) $text );
		if ( $text === '' || $count <= 0 ) {
			return [
				'text'      => '',
				'truncated' => false,
			];
		}

		$words = preg_split( '/\s+/u', $text );
		if ( ! is_array( $words ) ) {
			return [
				'text'      => $text,
				'truncated' => false,
			];
		}

		$total = count( $words );
		if ( $total <= $count ) {
			return [
				'text'      => $text,
				'truncated' => false,
			];
		}

		$slice = array_slice( $words, -$count );
		return [
			'text'      => implode( ' ', $slice ),
			'truncated' => true,
		];
	}

	/**
	 * Take the first N words from a string.
	 *
	 * @param string $text  Source text.
	 * @param int    $count Word count.
	 * @return array{text:string,truncated:bool}
	 */
	private static function take_first_words( $text, $count ) {
		$text = trim( (string) $text );
		if ( $text === '' || $count <= 0 ) {
			return [
				'text'      => '',
				'truncated' => false,
			];
		}

		$words = preg_split( '/\s+/u', $text );
		if ( ! is_array( $words ) ) {
			return [
				'text'      => $text,
				'truncated' => false,
			];
		}

		$total = count( $words );
		if ( $total <= $count ) {
			return [
				'text'      => $text,
				'truncated' => false,
			];
		}

		$slice = array_slice( $words, 0, $count );
		return [
			'text'      => implode( ' ', $slice ),
			'truncated' => true,
		];
	}
}
