<?php

namespace DivineOmega\PasswordExposed;

use function array_merge;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use rapidweb\RWFileCachePSR6\CacheItemPool;

class PasswordExposedChecker
{
    private $client;
    private $cache;

    const CACHE_EXPIRY_SECONDS = 60 * 60 * 24 * 30;

    public function __construct($cacheConfig=[], $clientConfig=[])
    {
		$defaultClientConfig = [
			'base_uri' => 'https://api.pwnedpasswords.com/',
			'timeout'  => 3.0,
		];
	
		$defaultCacheConfig = [
			'cacheDirectory' => '/tmp/password-exposed-cache/',
		];
		
        $this->client = new Client(array_merge($defaultClientConfig, $clientConfig));
        
        $this->cache = new CacheItemPool();
        $this->cache->changeConfig(array_merge($defaultCacheConfig, $cacheConfig));
    }

    public function passwordExposed($password)
    {
        $hash = sha1($password);
        unset($password);

        $cacheKey = substr($hash, 0, 2).'_'.substr($hash, 2, 3);

        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $responseBody = $cacheItem->get();
        } else {
            try {
                $response = $this->makeRequest($hash);
            } catch (ConnectException $e) {
                return PasswordStatus::UNKNOWN;
            }

            if ($response->getStatusCode() !== 200) {
                return PasswordStatus::UNKNOWN;
            }

            $responseBody = (string) $response->getBody();
        }

        $cacheItem->set($responseBody);
        $cacheItem->expiresAfter(self::CACHE_EXPIRY_SECONDS);
        $this->cache->save($cacheItem);

        return $this->getPasswordStatus($hash, $responseBody);
    }

    private function makeRequest($hash)
    {
        $options = [
            'exceptions' => false,
            'headers'    => [
                'User_Agent' => 'password_exposed - https://github.com/DivineOmega/password_exposed',
            ],
        ];

        return $this->client->request('GET', 'range/'.substr($hash, 0, 5), $options);
    }

    private function getPasswordStatus($hash, $responseBody)
    {
        $hash = strtoupper($hash);
        $hashSuffix = substr($hash, 5);

        $lines = explode("\r\n", $responseBody);

        foreach ($lines as $line) {
            list($exposedHashSuffix, $occurrences) = explode(':', $line);
            if (hash_equals($hashSuffix, $exposedHashSuffix)) {
                return PasswordStatus::EXPOSED;
            }
        }

        return PasswordStatus::NOT_EXPOSED;
    }
}
