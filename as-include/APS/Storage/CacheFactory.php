<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-include/APS/Storage/CacheManager.php
	Description: Handler for caching system.


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this online
*/

/**
 * Caches data (typically from database queries) to the filesystem.
 */
class APS_Storage_CacheFactory
{
	private static $cacheDriver = null;

	/**
	 * Get the appropriate cache handler.
	 * @return APS_Storage_CacheDriver The cache handler.
	 */
	public static function getCacheDriver()
	{
		if (self::$cacheDriver === null) {
			$config = array(
				'enabled' => (int) as_opt('caching_enabled') === 1,
				'keyprefix' => AS_FINAL_MYSQL_DATABASE . '.' . AS_MYSQL_TABLE_PREFIX . '.',
				'dir' => defined('AS_CACHE_DIRECTORY') ? AS_CACHE_DIRECTORY : null,
			);

			$driver = as_opt('caching_driver');

			switch($driver)
			{
				case 'memcached':
					self::$cacheDriver = new APS_Storage_MemcachedDriver($config);
					break;

				case 'filesystem':
				default:
					self::$cacheDriver = new APS_Storage_FileCacheDriver($config);
					break;
			}

		}

		return self::$cacheDriver;
	}
}
