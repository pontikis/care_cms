<?php

/**
 * Converts current time for given timezone (considering DST) to 14-digit UTC timestamp (YYYYMMDDHHMMSS)
 *
 * DateTime requires PHP >= 5.2
 *
 * @param $str_user_timezone
 * @param string $str_server_timezone
 * @param string $str_server_dateformat
 * @return string
 */
function now($str_user_timezone,
			 $str_server_timezone = CONST_SERVER_TIMEZONE,
			 $str_server_dateformat = CONST_SERVER_DATEFORMAT) {

	// set timezone to user timezone
	date_default_timezone_set($str_user_timezone);

	$date = new DateTime('now');
	$date->setTimezone(new DateTimeZone($str_server_timezone));
	$str_server_now = $date->format($str_server_dateformat);

	// return timezone to server default
	date_default_timezone_set($str_server_timezone);

	return $str_server_now;
}


/**
 * Converts a UTC timestamp to date string of given timezone (considering DST) and given dateformat
 *
 * DateTime requires PHP >= 5.2
 *
 * @param $str_server_datetime
 *
 * <li>Normally is a 14-digit UTC timestamp (YYYYMMDDHHMMSS). It can also be 8-digit (date), 12-digit (datetime without seconds).
 * If given dateformat (<var>$str_user_dateformat</var>) is longer than <var>$str_server_datetime</var>,
 * the missing digits of input value are filled with zero,
 * so (YYYYMMDD is equivalent to YYYYMMDD000000 and YYYYMMDDHHMM is equivalent to YYYYMMDDHHMM00).
 *
 * <li>It can also be 'now', null or empty string. In this case returns the current time.
 *
 * <li>Other values (invalid datetime strings) throw an error. Milliseconds are not supported.
 *
 * @param string $str_user_timezone
 * @param $str_user_dateformat
 * @return string
 */
function date_decode($str_server_datetime,
					 $str_user_timezone,
					 $str_user_dateformat) {

	// create date object
	try {
		$date = new DateTime($str_server_datetime);
	} catch(Exception $e) {
		trigger_error('date_decode: Invalid datetime: ' . $e->getMessage(), E_USER_ERROR);
	}

	// convert to user timezone
	$userTimeZone = new DateTimeZone($str_user_timezone);
	$date->setTimeZone($userTimeZone);

	// convert to user dateformat
	$str_user_datetime = $date->format($str_user_dateformat);

	return $str_user_datetime;
}

/**
 * Converts a date string of given timezone (considering DST) and format to 14-digit UTC timestamp (YYYYMMDDHHMMSS)
 *
 * DateTime::createFromFormat requires PHP >= 5.3
 *
 * <li><b>Note about strtotime</b>: Dates in the m/d/y or d-m-y formats are disambiguated by looking at the separator between the various components:
 * if the separator is a slash (/), then the American m/d/y is assumed;
 * whereas if the separator is a dash (-) or a dot (.), then the European d-m-y format is assumed.
 *
 * To avoid potential ambiguity, it's best to use ISO 8601 (YYYY-MM-DD) dates or DateTime::createFromFormat() when possible.
 *
 * @param $str_user_datetime
 *
 * <li><var>$str_user_timezone</var> and <var>$str_user_dateformat</var> must match. Otherwise error occurs.
 *
 * <li>If <var>$str_server_dateformat</var> is longer than <var>$str_user_dateformat</var>,
 * the missing time digits filled with zero, but if all times digits are missing current time is returned.
 *
 * <li>Other values (invalid datetime strings) throw an error. Milliseconds are not supported.
 *
 * @param $str_user_timezone
 * @param $str_user_dateformat
 * @param string $str_server_timezone
 * @param string $str_server_dateformat
 * @param string $str_safe_dateformat_strtotime
 * @return string
 *
 * @link http://www.php.net/manual/en/function.strtotime.php
 * @link http://stackoverflow.com/questions/4163641/php-using-strtotime-with-a-uk-date-format-dd-mm-yy
 * @link http://derickrethans.nl/british-date-format-parsing.html
 */
function date_encode($str_user_datetime,
					 $str_user_timezone,
					 $str_user_dateformat,
					 $str_server_timezone = CONST_SERVER_TIMEZONE,
					 $str_server_dateformat = CONST_SERVER_DATEFORMAT,
					 $str_safe_dateformat_strtotime = CONST_SAFE_DATEFORMAT_STRTOTIME) {

	// set timezone to user timezone
	date_default_timezone_set($str_user_timezone);

	// create date object using any given format
	if($str_user_datetime == 'now' || !$str_user_datetime) {
		$date = new DateTime('', new DateTimeZone($str_user_timezone));
	} else {
		$date = DateTime::createFromFormat($str_user_dateformat, $str_user_datetime, new DateTimeZone($str_user_timezone));
		if($date === false) {
			trigger_error('date_encode: Invalid date', E_USER_ERROR);
		}
	}

	// convert given datetime to safe format for strtotime
	$str_user_datetime = $date->format($str_safe_dateformat_strtotime);

	// convert to UTC
	$str_server_datetime = gmdate($str_server_dateformat, strtotime($str_user_datetime));

	// return timezone to server default
	date_default_timezone_set($str_server_timezone);

	return $str_server_datetime;
}

/**
 * Return the offset (in seconds) from UTC of a given timezone timestring (considering DST)
 *
 * @param $str_datetime
 * @param $str_timezone
 * @return int
 */
function get_time_offset($str_datetime, $str_timezone) {
	$timezone = new DateTimeZone($str_timezone);
	$offset = $timezone->getOffset(new DateTime($str_datetime));
	return $offset;
}

/**
 * Timezones list with GMT offset
 *
 * @return array
 * @link http://stackoverflow.com/a/9328760
 */
function tz_list() {
	$zones_array = array();
	$timestamp = time();
	foreach(timezone_identifiers_list() as $key => $zone) {
		date_default_timezone_set($zone);
		$zones_array[$key]['zone'] = $zone;
		$zones_array[$key]['diff_from_GMT'] = 'UTC/GMT ' . date('P', $timestamp);
	}
	return $zones_array;
}

/**
 * Create dateformat array
 *
 * @param $a_df
 * @param $tz
 * @return array
 */
function df_list($a_df, $tz = CONST_SERVER_TIMEZONE) {
	$df_array = array();
	$tz = new DateTimeZone($tz);
	$date = new DateTime('');
	$date->setTimeZone($tz);
	foreach($a_df as $df_key => $df_val) {
		$df = $df_val['php_datetime'];
		$df_example = $date->format($df);
		$df_array[$df_key] = array('dateformat' => $df, 'example' => $df_example);
	}
	return $df_array;
}

/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param int|string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
 * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
 * @param bool|\boole $img True to return a complete IMG tag False for just the URL
 * @param array $atts Optional, additional key/value attributes to include in the IMG tag
 * @return String containing either just a URL or a complete image tag
 * @link http://gravatar.com/site/implement/images/php/
 */
function get_gravatar($email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array()) {
	$url = 'http://www.gravatar.com/avatar/';
	$url .= md5(strtolower(trim($email)));
	$url .= "?s=$s&d=$d&r=$r";
	if($img) {
		$url = '<img src="' . $url . '"';
		foreach($atts as $key => $val)
			$url .= ' ' . $key . '="' . $val . '"';
		$url .= ' />';
	}
	return $url;
}

/**
 * Get Gravatar profile url
 *
 * @param $email
 * @param bool $decode_url
 * @return string
 * @link https://en.gravatar.com/site/implement/profiles/php/
 */
function get_gravatar_profile($email, $decode_url = false) {
	$url_encoded = 'http://www.gravatar.com/' . md5(strtolower(trim($email)));
	if(!$decode_url) {
		return $url_encoded;
	} else {
		$url = $url_encoded . '.php';
		$str = file_get_contents($url);
		if($str === false) {
			return $url_encoded;
		} else {
			$profile = unserialize($str);
			if(is_array($profile) && isset($profile['entry'])) {
				return $profile['entry'][0]['profileUrl'];
			} else {
				return $url_encoded;
			}
		}
	}

}

/**
 * Check if a string is a valid date(time)
 *
 * @param $str_dt
 * @param $str_dateformat
 * @param $str_timezone
 * @return bool
 */
function isValidDateTimeString($str_dt, $str_dateformat, $str_timezone) {
	$date = DateTime::createFromFormat($str_dateformat, $str_dt, new DateTimeZone($str_timezone));
	return ($date === false ? false : true);
}

// -----------------------------------------------------------------------------

/**
 * Create greeklish URL from string containing mainly greek chars (topic title)
 * Result can contain only: non accented letters, digits and dash between words
 *
 * @param $url
 * @param int $len
 * @return mixed
 */
function greek_url($url, $len = 0) {
	$url = trim($url);
	$url = mb_strtolower($url);
	$url = removeAccents($url); // replace accented characters with non accented
	$url = to_greeklish($url);
	$url = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $url); // replace all characters except letters, digits and spaces with space
	$url = preg_replace('/\s+/', ' ', $url); // replace multiple spaces with one
	$url = trim($url);
	if($len > 0) {
		$url = mb_substr($url, 0, $len); // truncate to max length
		$url = trim($url);
	}
	$url = preg_replace('/ /', '-', $url); // replace space between words with dash
	return $url;
}

/**
 * Replace accented characters with non accented
 *
 * @param $str
 * @return mixed
 * @link http://myshadowself.com/coding/php-function-to-convert-accented-characters-to-their-non-accented-equivalant/
 */
function removeAccents($str) {
	$a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή');
	$b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η');
	return str_replace($a, $b, $str);
}

/**
 * Convert Greek string to grreklish
 * Based on http://code.loon.gr/snippet/php/μετατροπή-greek-σε-greeklish
 * It primary converts υ -> u (inside 'ου'), while all other υ -> y (thanks to Dimitris Giannitsaros)
 *
 * @param $string
 * @return string
 */
function to_greeklish($string) {
	return strtr($string, array(
		'ΟΥ' => 'OU', 'ΟΎ' => 'OU', 'ΌΥ' => 'OU',
		'ου' => 'ou', 'ού' => 'ou', 'όυ' => 'ou',
		'Α' => 'A', 'Β' => 'V', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'I', 'Θ' => 'TH', 'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L',
		'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'KS', 'Ο' => 'O', 'Π' => 'P', 'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F',
		'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'O',
		'α' => 'a', 'β' => 'v', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'i',
		'θ' => 'th', 'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'ks', 'ο' => 'o', 'π' => 'p', 'ρ' => 'r',
		'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'o', 'ς' => 's',
		'ά' => 'a', 'έ' => 'e', 'ή' => 'i', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ώ' => 'o',
		'ϊ' => 'i', 'ϋ' => 'y',
		'ΐ' => 'i', 'ΰ' => 'y'
	));
}

//
/**
 * TODO to be deleted
 *
 * @param $s
 * @return string
 */
function to_slug($s) {
	$array = array('ου' => 'ou', 'ού' => 'ou', 'όυ' => 'ou',
		'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε'
		=> 'e', 'ζ' => 'z', 'η' => 'i', 'θ' => 'th', 'ι' => 'i', 'κ' => 'k',
		'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο'
		=> 'o', 'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y',
		'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'o',
		'ς' => 's', 'έ' => 'e', 'ύ' => 'y', 'ί' => 'i', 'ό'
		=> 'o', 'ά' => 'a', 'ή' => 'i', 'ώ' => 'o', 'ϊ' => 'i', 'ϋ' => 'y');

	print $s . '     ';
	$s = mb_strtolower($s, 'UTF-8');
	$s = strtr($s, $array);
	$s = preg_replace('~[^a-zA-Z\d]~', ' ', $s);
	$s = preg_replace('~\s+~', '_', $s);
	$s = trim($s, '_');

	return $s;
}


// -----------------------------------------------------------------------------

/**
 * @param $needle
 * @param $replacement
 * @param $haystack
 * @return string
 */
function mb_str_replace($needle, $replacement, $haystack) {
	$needle_len = mb_strlen($needle);
	$replacement_len = mb_strlen($replacement);
	$pos = mb_strpos($haystack, $needle);
	while($pos !== false) {
		$haystack = mb_substr($haystack, 0, $pos) . $replacement
			. mb_substr($haystack, $pos + $needle_len);
		$pos = mb_strpos($haystack, $needle, $pos + $replacement_len);
	}
	return $haystack;
}

/**
 * * Multi-byte CASE INSENSITIVE str_replace
 *
 * @param $co
 * @param $naCo
 * @param $wCzym
 * @return string
 * @link http://www.php.net/manual/en/function.mb-ereg-replace.php#55659
 */
function mb_str_ireplace($co, $naCo, $wCzym) {
	$wCzymM = mb_strtolower($wCzym);
	$coM = mb_strtolower($co);
	$offset = 0;

	while(!is_bool($poz = mb_strpos($wCzymM, $coM, $offset))) {
		$offset = $poz + mb_strlen($naCo);
		$wCzym = mb_substr($wCzym, 0, $poz) . $naCo . mb_substr($wCzym, $poz + mb_strlen($co));
		$wCzymM = mb_strtolower($wCzym);
	}

	return $wCzym;
}

// -----------------------------------------------------------------------------

/**
 * Replaces double line-breaks with paragraph elements.
 *
 * http://ma.tt/scripts/autop/
 *
 * A group of regex replaces used to identify text formatted with newlines and
 * replace double line-breaks with HTML paragraph tags. The remaining
 * line-breaks after conversion become <<br />> tags, unless $br is set to '0'
 * or 'false'.
 *
 * @since 0.71
 *
 * @param string $pee The text which has to be formatted.
 * @param bool $br Optional. If set, this will convert all remaining line-breaks after paragraphing. Default true.
 * @return string Text which has been converted into correct paragraph tags.
 */
function wpautop($pee, $br = true) {
	$pre_tags = array();

	if(trim($pee) === '')
		return '';

	$pee = $pee . "\n"; // just to make things a little easier, pad the end

	if(strpos($pee, '<pre') !== false) {
		$pee_parts = explode('</pre>', $pee);
		$last_pee = array_pop($pee_parts);
		$pee = '';
		$i = 0;

		foreach($pee_parts as $pee_part) {
			$start = strpos($pee_part, '<pre');

			// Malformed html?
			if($start === false) {
				$pee .= $pee_part;
				continue;
			}

			$name = "<pre wp-pre-tag-$i></pre>";
			$pre_tags[$name] = substr($pee_part, $start) . '</pre>';

			$pee .= substr($pee_part, 0, $start) . $name;
			$i++;
		}

		$pee .= $last_pee;
	}

	$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
	// Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|noscript|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
	$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
	$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
	if(strpos($pee, '<object') !== false) {
		$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
		$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
	}
	$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
	// make paragraphs, including one at the end
	$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
	$pee = '';
	foreach($pees as $tinkle)
		$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
	$pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
	$pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
	if($br) {
		$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', '_autop_newline_preservation_helper', $pee);
		$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
		$pee = str_replace('<WPPreserveNewline />', "\n", $pee);
	}
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
	$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
	$pee = preg_replace("|\n</p>$|", '</p>', $pee);

	if(!empty($pre_tags))
		$pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);

	return $pee;
}


/**
 * Newline preservation help function for wpautop
 *
 * @since 3.1.0
 * @access private
 *
 * @param array $matches preg_replace_callback matches array
 * @return string
 */
function _autop_newline_preservation_helper($matches) {
	return str_replace("\n", "<WPPreserveNewline />", $matches[0]);
}

/**
 * http://stackoverflow.com/questions/2626079/convert-eregi-replace-to-preg-replace-in-php
 *
 * @param $text
 * @return mixed
 */
function linkify($text) {
	$text = preg_replace('/(((f|ht){1}tp:\/\/)[-a-zA-Z0-9@:%_\+.~#?&\/\/=]+)/i',
		'<a href="\\1">\\1</a>', $text);
	$text = preg_replace('/([[:space:]()[{}])(www.[-a-zA-Z0-9@:%_\+.~#?&\/\/=]+)/i',
		'\\1<a href="http://\\2">\\2</a>', $text);
	$text = preg_replace('/([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,3})/i',
		'<a href="mailto:\\1">\\1</a>', $text);
	return $text;
}

function linkify_new_win($text) {
	$text = preg_replace('/(((f|ht){1}tp:\/\/)[-a-zA-Z0-9@:%_\+.~#?&\/\/=]+)/i',
		'<a href="\\1" target="_blank">\\1</a>', $text);
	$text = preg_replace('/([[:space:]()[{}])(www.[-a-zA-Z0-9@:%_\+.~#?&\/\/=]+)/i',
		'\\1<a href="http://\\2" target="_blank">\\2</a>', $text);
	$text = preg_replace('/([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,3})/i',
		'<a href="mailto:\\1">\\1</a>', $text);
	return $text;
}


/**
 * @param $str
 * @return bool
 */
function is_positive_integer($str) {
	return (is_numeric($str) && $str > 0 && $str == round($str));
}

?>