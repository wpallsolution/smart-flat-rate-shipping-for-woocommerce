<?php
/**
 * Small, dependency-free helper functions shared by the rule engine and the admin.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Number of decimals used for shipping amounts (store setting, 2 as fallback).
 *
 * @return int
 */
function sfrs_price_decimals() {
	return function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
}

/**
 * Round an amount to the store's price precision.
 *
 * @param float|string $value Amount.
 * @return float
 */
function sfrs_round( $value ) {
	return round( (float) $value, sfrs_price_decimals() );
}

/**
 * Strictly parse an amount typed by an administrator.
 *
 * Only a plain "." decimal separator and no thousands separators are accepted, so
 * the value is never ambiguous regardless of store locale. Returns null for blank
 * or malformed input. Negative values are parsed (and rejected by the caller).
 *
 * @param mixed $raw Raw value.
 * @return float|null
 */
function sfrs_parse_amount( $raw ) {
	if ( is_int( $raw ) || is_float( $raw ) ) {
		return (float) $raw;
	}
	if ( ! is_string( $raw ) ) {
		return null;
	}
	$raw = trim( $raw );
	if ( ! preg_match( '/^-?(\d+(\.\d+)?|\.\d+)$/', $raw ) ) {
		return null;
	}
	return (float) $raw;
}

/**
 * Normalise a parsed amount to a compact, stable string for storage.
 *
 * @param float $amount Amount.
 * @return string
 */
function sfrs_store_amount( $amount ) {
	$out = rtrim( rtrim( number_format( (float) $amount, 6, '.', '' ), '0' ), '.' );
	return '' === $out ? '0' : $out;
}

/**
 * Plain-text money string for traces and summaries (no HTML).
 *
 * @param float|string $amount Amount.
 * @return string
 */
function sfrs_money_text( $amount ) {
	if ( function_exists( 'wc_price' ) ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
	return number_format( (float) $amount, sfrs_price_decimals(), '.', '' );
}

/**
 * Split a comma/newline separated list into trimmed, non-empty, unique entries.
 *
 * @param string $text Raw text.
 * @return string[]
 */
function sfrs_split_list( $text ) {
	$parts = preg_split( '/[\s,;]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
	return array_values( array_unique( array_map( 'trim', $parts ? $parts : array() ) ) );
}

/**
 * Normalise a postcode: trimmed, upper-case, spaces and dashes removed.
 * Mirrors the behaviour of wc_normalize_postcode().
 *
 * @param string $postcode Postcode.
 * @return string
 */
function sfrs_normalize_postcode( $postcode ) {
	return preg_replace( '/[\s\-]/', '', strtoupper( trim( (string) $postcode ) ) );
}
