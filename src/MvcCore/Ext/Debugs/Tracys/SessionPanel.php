<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Debugs\Tracys;

/**
 * Responsibility - dump all session records as MvcCore session namespaces or just session PHP values.
 */
class SessionPanel implements \Tracy\IBarPanel
{
	/**
	 * MvcCore Extension - Debug - Tracy Panel - Session - version:
	 * Comparison by PHP function version_compare();
	 * @see http://php.net/manual/en/function.version-compare.php
	 */
	const VERSION = '5.0.0-alpha';

	/**
	 * Internal constants to recognize session record types in template.
	 */
	const _TYPE_PHP = 0;
	const _TYPE_NAMESPACE = 1;
	const _EXPIRATION_HOOPS = 1;
	const _EXPIRATION_TIME = 2;

	/**
	 * `\MvcCore\ISession` meta info store key in `$_SESSION`.
	 * @var string
	 */
	public static $MetaStoreKey = \MvcCore\ISession::SESSION_METADATA_KEY;

	/**
	 * Session store dumped data, rendered in template.
	 * @var array
	 */
	protected $session = [];

	/**
	 * Formatted maximum lifetime from `\MvcCore\Session` namespace.
	 * @var string
	 */
	protected $sessionMaxLifeTime = '';

	/**
	 * Now time completed in `\MvcCore\Ext\Debugs\SessionPanel::__construct();` in request begin
	 * @var int
	 */
	protected $now = 0;

	/**
	 * Create new panel instance, always called in request begin.
	 * @return SessionPanel
	 */
	public function __construct () {
		$sessionClass = \MvcCore\Application::GetInstance()->GetSessionClass();
		$this->now = $sessionClass::GetSessionStartTime();
	}

	/**
	 * Get unique `Tracy` debug bar panel id.
	 * @return string
	 */
	public function getId() {
		return 'session-panel';
	}

	/**
	 * Return rendered debug panel heading HTML code displayed all time in `Tracy` debug  bar.
	 * @return string
	 */
	public function getTab() {
		ob_start();
		include(__DIR__ . '/session.tab.phtml');
		return ob_get_clean();
	}

	/**
	 * Return rendered debug panel content window HTML code.
	 * @return string
	 */
	public function getPanel() {
		$this->prepareSessionData();
		if (!$this->session) return '';
		ob_start();
		include(__DIR__ . '/session.panel.phtml');
		return ob_get_clean();
	}

	/**
	 * Read and parse session data except MvcCore metadata session record.
	 * @return void
	 */
	protected function prepareSessionData () {
		if ($_SESSION === NULL) return;

		// read `\MvcCore\Session` storage
		$sessionClass = \MvcCore\Application::GetInstance()->GetSessionClass();
		$sessionRawMetaStore = $sessionClass::GetSessionMetadata();
		$sessionMetaStore = $sessionRawMetaStore instanceof \stdClass
			? $sessionRawMetaStore
			: (object) ['names' => []];
		$maxLifeTimes = (object) [
			'hoops'		=> 0,
			'seconds'	=> 0,
		];

		$standardRecords = [];
		$namespaceRecords = [];

		// look for each record in `$_SESSION`
		// if data are defined as session namespace
		// record in `\MvcCore\Session` meta store:
		foreach ($_SESSION as $sessionKey => $sessionData) {
			if ($sessionKey === self::$MetaStoreKey) continue;
			$item = new \stdClass;
			$item->key = $sessionKey;
			$item->value = \Tracy\Dumper::toHtml($sessionData);
			if (isset($sessionMetaStore->names[$sessionKey])) {
				if (count((array) $_SESSION[$sessionKey]) === 0)
					// this will be destroyed automatically by
					// \MvcCore\Session::Close();` before `session_write_close()`.
					continue;
				$item->type = self::_TYPE_NAMESPACE;
				$item->expirations = [];
				if (isset($sessionMetaStore->hoops[$sessionKey])) {
					$value = $sessionMetaStore->hoops[$sessionKey];
					$item->expirations[] = (object) [
						'type'	=> self::_EXPIRATION_HOOPS,
						'value'	=> $value,
						'text'	=> $value . ' hoops',
					];
					if ($value > $maxLifeTimes->hoops)
						$maxLifeTimes->hoops = $value;
				}
				if (isset($sessionMetaStore->expirations[$sessionKey])) {
					$value = $sessionMetaStore->expirations[$sessionKey] - $this->now;
					$item->expirations[] = (object) [
						'type'	=> self::_EXPIRATION_TIME,
						'value'	=> $value,
						'text'	=> $this->_formateMaxLifeTimestamp($value),
					];
					if ($value > $maxLifeTimes->seconds)
						$maxLifeTimes->seconds = $value;
				}
				$namespaceRecords[$sessionKey] = $item;
			} else {
				$item->type = self::_TYPE_PHP;
				$standardRecords[$sessionKey] = $item;
			}
		}

		ksort($standardRecords);
		ksort($namespaceRecords);
		$this->session = array_merge($namespaceRecords, $standardRecords);

		$maxLifeTimesItems = [];
		if ($maxLifeTimes->seconds > 0)
			$maxLifeTimesItems[] = $this->_formateMaxLifeTimestamp($maxLifeTimes->seconds);
		if ($maxLifeTimes->hoops > 0)
			$maxLifeTimesItems[] = $maxLifeTimes->hoops . ' hoops';
		$this->sessionMaxLifeTime = implode(', ', $maxLifeTimesItems);
	}

	/**
	 * Return expiration time in human readable format from seconds count.
	 * @param int $timestamp
	 * @return string
	 */
	private function _formateMaxLifeTimestamp ($timestamp = 0) {
		$result = [];
		if ($timestamp >= 31557600) {
			$localVal = floor($timestamp / 31557600);
			$result[] = $localVal . ' year' . (($localVal > 1) ? 's' : '');
			if ($localVal > 1) return 'more than ' . $result[0];
			$timestamp = $timestamp - (floor($timestamp / 31557600) * 31557600);
		}
		if ($timestamp >= 2592000) {
			$localVal = floor($timestamp / 2592000);
			$result[] = $localVal . ' month' . (($localVal > 1) ? 's' : '');
			if (count($result) == 1 && $localVal > 1) return 'more than ' . $result[0];
			$timestamp = $timestamp - (floor($timestamp / 2592000) * 2592000);
		}
		if ($timestamp >= 86400) {
			$localVal = floor($timestamp / 86400);
			$result[] = $localVal . ' day' . (($localVal > 1) ? 's' : '');
			if (count($result) == 1 && $localVal > 1) return 'more than ' . $result[0];
			$timestamp = $timestamp - (floor($timestamp / 86400) * 86400);
		}
		if ($timestamp >= 3600) {
			$localVal = floor($timestamp / 3600);
			$result[] = $localVal . ' hour' . (($localVal > 1) ? 's' : '');
			$timestamp = $timestamp - (floor($timestamp / 3600) * 3600);
		}
		if ($timestamp >= 60) {
			$localVal = floor($timestamp / 60);
			$result[] = $localVal . ' minute' . (($localVal > 1) ? 's' : '');
			$timestamp = $timestamp - (floor($timestamp / 60) * 60);
		}
		if ($timestamp > 0) {
			$localVal = floor($timestamp);
			if ($localVal > 1) $result[] = $localVal . ' seconds';
		}
		return implode(', ', $result);
	}
}
