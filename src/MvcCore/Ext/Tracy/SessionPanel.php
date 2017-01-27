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

namespace MvcCore\Ext\Debug\Tracy;

class SessionPanel implements \Tracy\IBarPanel
{
	const TYPE_PHP = 0;
	const TYPE_NAMESPACE = 1;

	const EXPIRATION_HOOPS = 1;
	const EXPIRATION_TIME = 2;

	/**
	 * \MvcCore\Session meta info store key in $_SESSION
	 * @var string 
	 */
	public static $MetaStoreKey = \MvcCore\Session::SESSION_METADATA_KEY;
	/**
	 * Readed session store as data printed in template
	 * @var array
	 */
	public static $Session = array();
	/**
	 * Formated max. lifetime from \MvcCore\Session namespace
	 * @var string
	 */
	public static $SessionMaxLifeTime = '';
	/**
	 * Debug panel id
	 * @var string
	 */
	public static $Id = 'session-panel';
	/**
	 * Now time at \MvcCore\Ext\Debug\SessionPanel static init - mostly in request begin
	 * @var int
	 */
	public static $Now = 0;

	public function __construct () {
		self::$Now = time();
	}
	public function getId() {
		return self::$Id;
	}
	public function getTab() {
		ob_start();
		require(__DIR__ . '/assets/Bar/session.tab.phtml');
		return ob_get_clean();
	}
	public function getPanel() {
		$this->readSession();
		if (!self::$Session) return '';
		ob_start();
		require(__DIR__ . '/assets/Bar/session.panel.phtml');
		return ob_get_clean();
	}
	protected function readSession () {
		if (is_null($_SESSION)) return;

		// read \MvcCore\Session storage
		$sessionRawMetaStore = isset($_SESSION[self::$MetaStoreKey]) ? $_SESSION[self::$MetaStoreKey] : '';
		$sessionMetaStore = $sessionRawMetaStore ? unserialize($sessionRawMetaStore) : (object) array('names' => array());

		$maxLifeTimes = (object) array(
			'hoops'		=> 0,
			'seconds'	=> 0,
		);

		$standardRecords = array();
		$namespaceRecords = array();

		// look for each record in $_SESSION if data are defined as namespace in \MvcCore\Session meta store
		foreach ($_SESSION as $sessionKey => $sessionData) {
			if ($sessionKey === self::$MetaStoreKey) continue;
			$item = new \stdClass;
			$item->key = $sessionKey;
			$item->value = $this->_clickableDump($sessionData);
			if (isset($sessionMetaStore->names[$sessionKey])) {
				$item->type = self::TYPE_NAMESPACE;
				$item->expirations = array();
				if (isset($sessionMetaStore->hoops[$sessionKey])) {
					$value = $sessionMetaStore->hoops[$sessionKey];
					$item->expirations[] = (object) array(
						'type'	=> self::EXPIRATION_HOOPS,
						'value'	=> $value,
						'text'	=> $value . ' hoops',
					);
					if ($value > $maxLifeTimes->hoops) $maxLifeTimes->hoops = $value;
				}
				if (isset($sessionMetaStore->expirations[$sessionKey])) {
					$value = $sessionMetaStore->expirations[$sessionKey] - self::$Now;
					$item->expirations[] = (object) array(
						'type'	=> self::EXPIRATION_TIME,
						'value'	=> $value,
						'text'	=> $this->_formateDate($value),
					);
					if ($value > $maxLifeTimes->seconds) $maxLifeTimes->seconds = $value;
				}
				$namespaceRecords[$sessionKey] = $item;
			} else {
				$item->type = self::TYPE_PHP;
				$standardRecords[$sessionKey] = $item;
			}
		}

		ksort($standardRecords);
		ksort($namespaceRecords);
		self::$Session = array_merge($namespaceRecords, $standardRecords);

		$maxLifeTimesItems = array();
		if ($maxLifeTimes->seconds > 0) $maxLifeTimesItems[] = $this->_formateDate($maxLifeTimes->seconds);
		if ($maxLifeTimes->hoops > 0) $maxLifeTimesItems[] = $maxLifeTimes->hoops . ' hoops';
		self::$SessionMaxLifeTime = implode(', ', $maxLifeTimesItems);
	}
	
	private function _formateDate ($timestamp = 0) {
		//$timeFormated = '';
		$result = array();
		if ($timestamp >= 31557600) {
			$localVal = floor($timestamp / 31557600);
			$result[] = $localVal . ' year' . (($localVal > 1) ? 's' : '');
			$timestamp = $timestamp - (floor($timestamp / 31557600) * 31557600);
		}
		if ($timestamp >= 2592000) {
			$localVal = floor($timestamp / 2592000);
			$result[] = $localVal . ' month' . (($localVal > 1) ? 's' : '');
			$timestamp = $timestamp - (floor($timestamp / 2592000) * 2592000);
		}
		if ($timestamp >= 86400) {
			$localVal = floor($timestamp / 86400);
			$result[] = $localVal . ' day' . (($localVal > 1) ? 's' : '');
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

	/**
	 * Dumps any variable to clickable html string reprezentation.
	 * @param  mixed  $dump
	 * @return string
	 */
	private function _clickableDump ($dump) {
		return '<pre class="nette-dump">' . preg_replace_callback(
			'#^( *)((?>[^(]{1,200}))\((\d+)\) <code>#m',
			function ($m) {
				return "$m[1]<a href='#' rel='next'>$m[2]($m[3]) " . (
					trim($m[1]) || $m[3] < 7 
						? 
							'<abbr>&#x25bc;</abbr> </a><code>'
						:
							'<abbr>&#x25ba;</abbr> </a><code class="nette-collapsed">'
					);
			},
			\Tracy\Dumper::toHtml($dump)
		) . '</pre>';
	}
}