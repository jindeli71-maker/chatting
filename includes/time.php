<?php
/**
 * App timezone helpers.
 * DB timestamps are stored in UTC; UI shows Asia/Kuala_Lumpur (Malaysia).
 */
declare(strict_types=1);

const APP_TIMEZONE = 'Asia/Kuala_Lumpur';

function app_timezone(): DateTimeZone {
	return new DateTimeZone(APP_TIMEZONE);
}

/** Current UTC datetime for SQL inserts */
function now_utc_sql(): string {
	return gmdate('Y-m-d H:i:s');
}

/**
 * Format a DB datetime (UTC / naive) for chat bubbles, e.g. "11:05 PM".
 */
function format_message_time(?string $dbTime): string {
	if ($dbTime === null || $dbTime === '') {
		return '';
	}
	try {
		$dt = new DateTimeImmutable(trim($dbTime), new DateTimeZone('UTC'));
		return $dt->setTimezone(app_timezone())->format('g:i A');
	} catch (Throwable $e) {
		$ts = strtotime($dbTime);
		if ($ts === false) {
			return '';
		}
		return (new DateTimeImmutable('@' . $ts))
			->setTimezone(app_timezone())
			->format('g:i A');
	}
}

/**
 * Format for longer displays (timeline, forums).
 */
function format_app_datetime(?string $dbTime, string $format = 'M j, Y g:i A'): string {
	if ($dbTime === null || $dbTime === '') {
		return '';
	}
	try {
		$dt = new DateTimeImmutable(trim($dbTime), new DateTimeZone('UTC'));
		return $dt->setTimezone(app_timezone())->format($format);
	} catch (Throwable $e) {
		$ts = strtotime($dbTime);
		if ($ts === false) {
			return '';
		}
		return (new DateTimeImmutable('@' . $ts))
			->setTimezone(app_timezone())
			->format($format);
	}
}
