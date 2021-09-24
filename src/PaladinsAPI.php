<?php

namespace PaladinsDev\PHP;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Onoi\Cache\Cache;
use PaladinsDev\PHP\Exceptions\PaladinsException;
use PaladinsDev\PHP\Exceptions\SessionException;
use PaladinsDev\PHP\Exceptions\NotFoundException;

/**
 * Paladins API
 * 
 * This class is the entry point and the main class that you will use to interact with the Hi-Rez/Evil Mojo API.
 * 
 * @author Matthew Hatcher <matthewh@halfpetal.com>
 * @copyright 2018 Halfpetal LLC
 * @license Apache-2.0
 * @link https://github.com/PaladinsDev/PHP-API
 */
class PaladinsAPI
{
    /**
     * The developer id given to the developer upon approval.
     *
     * @var string
     */
    private $devId;
    
    /**
     * The auth key given to authorize the requests to the server.
     *
     * @var string
     */
    private $authKey;

    /**
     * Sets the language id for API usage.
     *
     * @var integer
     */
    private $languageId;

    /**
     * The API endpoint, never changed.
     * 
     * @var string
     */
    private $apiUrl;

    /**
     * The Guzzle client used to make requests.
     *
     * @var Client
     */
    private $guzzleClient;

    /**
     * The cache driver we interact with
     *
     * @var Cache
     */
    private $cache;

    /**
     * If you don't use a cache driver, we'll store locally.
     *
     * @var string
     */
    private $sessionId;

    /**
     * Session expiration time.
     *
     * @var [type]
     */
    private $sessionExpiresAt;

    /**
     * Holds the current instance of the Paladins API class.
     *
     * @var PaladinsAPI
     */
    private static $instance;

    public function __construct(string $devId, string $authKey, Cache $cacheDriver = null)
    {
        $this->devId        = $devId;
        $this->authKey      = $authKey;
        $this->cache        = $cacheDriver;
        $this->languageId   = 1;
        $this->apiUrl       = 'https://api.paladins.com/paladinsapi.svc';
        $this->guzzleClient = new Client;
    }

    /**
     * Get the current instance of the Paladins API. Useful for singleton based applications.
     *
     * @param string|null $devId
     * @param string|null $authKey
     * @param Cache|null $cacheDriver
     * @return PaladinsAPI
     */
    public static function getInstance(string $devId = null, string $authKey = null, Cache $cacheDriver = null): PaladinsAPI
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($devId, $authKey, $cacheDriver);
        }

        return self::$instance;
    }

    /**
     * Get the current Hi-Rez Paladins server status.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getServerStatus()
    {
        return $this->makeRequest($this->buildUrl('gethirezserverstatus'));
    }

    /**
     * Get the currnet patch information.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPatchInfo()
    {
        return $this->makeRequest($this->buildUrl('getpatchinfo'));
    }

    /**
     * Get the top 50 most watched/recent matches.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getTopMatches()
    {
        return $this->makeRequest($this->buildUrl('gettopmatches'));
    }

    /**
     * Get the ranked leaderboard for a tier and a season
     *
     * @param integer $tier
     * @param integer $season
     * @param integer $queue
     * @return mixed
     * @codeCoverageIgnore
     * @throws SessionException
     */
    public function getRankedLeaderboard(int $tier, int $season, int $queue)
    {
        $url = $this->buildUrl('getleagueleaderboard', null, null, null, null, $queue, $tier, $season);

        return $this->makeRequest($url);
    }

    /**
     * Get all the seasons and their state for ranked.
     *
     * @param integer $queue
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getRankedSeasons(int $queue)
    {
        return $this->makeRequest($this->buildUrl('getleagueseasons', null, null, null, null, $queue));
    }

    /**
     * Get all the match ids in a selected queue based on date and hours
     *
     * @param string $hour
     * @param mixed $date
     * @param integer $queue
     * @return mixed
     *
     * @codeCoverageIgnore
     */
    public function getMatchIdsByQueue(string $hour, $date, int $queue = 424)
    {
        return $this->makeRequest("$this->apiUrl/getmatchidsbyqueueJson/$this->devId/{$this->getSignature('getmatchidsbyqueue')}/{$this->getSession()}/{$this->getTimestamp()}/$queue/$date/$hour");
    }

    /**
     * Get all the champions for the game.
     *
     * @return mixed
     */
    public function getChampions()
    {
        return $this->makeRequest($this->buildUrl('getchampions', null, $this->languageId));
    }

    /**
     * Get all the available cards for the requested champion.
     *
     * @param integer $championId
     * @return mixed
     */
    public function getChampionCards(int $championId)
    {
        return $this->makeRequest($this->buildUrl('getchampioncards', null, $this->languageId, null, $championId));
    }

    /**
     * Get all the available skins for the requested champion.
     *
     * @param integer $championId
     * @return mixed
     */
    public function getChampionSkins(int $championId)
    {
        return $this->makeRequest($this->buildUrl('getchampionskins', null, $this->languageId, null, $championId));
    }

    /**
     * Get the top players from the leaderboard.
     * This should not reflect actual good players 
     * as it only requires more than 10 matches.
     * It also is based on win
     *
     * @param integer $championId
     * @param integer $queue
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getChampionLeaderboard(int $championId, int $queue)
    {
        return $this->makeRequest($this->buildUrl('getchampionleaderboard', null, null, null, $championId, $queue));
    }

    /**
     * Get all the available in game items.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getItems()
    {
        return $this->makeRequest($this->buildUrl('getitems', null, $this->languageId));
    }

    /**
     * Get a player and their details from the API.
     *
     * @param mixed $player
     * @param int $platform
     * @return mixed
     * @throws PaladinsException
     */
    public function getPlayer($player, int $platform = 5)
    {
        if (!is_string($player) && !is_int($player))
        {
            throw new PaladinsException('The player must be either a name, string, or a player id, integer.');
        }

        if (is_string($player)) {
            $players = $this->getPlayerIdByName($player);

            if (!empty($players)) {
                $firstPlayer = Arr::first($players, function($value) use ($platform) {
                    return $value['portal_id'] == $platform;
                });
            }

            if (!isset($firstPlayer)) {
                throw new PaladinsException('The requested player could not be found in the Paladins system.');
            } else {
                $player = $firstPlayer['player_id'];
            }
        }

        return $this->makeRequest($this->buildUrl('getplayer', $player));
    }

    /**
     * Get an array of players with the requested name.
     *
     * @param string $name
     * @return mixed
     */
    public function getPlayerIdByName(string $name)
    {
        return $this->makeRequest($this->buildUrl('getplayeridbyname', $name));
    }

    /**
     * Get a player from PC or PSN. Does not work with Xbox or Switch.
     *
     * @param string $name
     * @param integer $platform
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPlayerIdByPortalUserId(string $name, int $platform)
    {
        return $this->makeRequest($this->buildUrl('getplayeridbyportaluserid', $name, null, null, null, null, null, null, $platform));
    }

    /**
     * Get player ids by the gamertag.
     *
     * @param string $name
     * @param integer $platform
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPlayerIdsByGamertag(string $name, int $platform)
    {
        return $this->makeRequest($this->buildUrl('getplayeridsbygamertag', $name, null, null, null, null, null, null, $platform));
    }

    /**
     * Get player id info for Xbox and Switch.
     *
     * @param string $name
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPlayerIdInfoForXboxAndSwitch(string $name)
    {
        return $this->makeRequest($this->buildUrl('getplayeridinfoforxboxandswitch', $name));
    }

    /**
     * Get all the friends for the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerFriends(int $playerId)
    {
        return $this->makeRequest($this->buildUrl('getfriends', $playerId));
    }

    /**
     * Get all the champion ranks for the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerChampionRanks(int $playerId)
    {
        return $this->makeRequest($this->buildUrl('getchampionranks', $playerId));
    }

    /**
     * Get all the champion loadouts for the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerLoadouts(int $playerId)
    {
        return $this->makeRequest($this->buildUrl('getplayerloadouts', $playerId, $this->languageId));
    }

    /**
     * Get the current status of the player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerStatus(int $playerId)
    {
        return $this->makeRequest($this->buildUrl('getplayerstatus', $playerId));
    }

    /**
     * Get the match history of the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerMatchHistory(int $playerId)
    {
        return $this->makeRequest($this->buildUrl('getmatchhistory', $playerId));
    }

    /**
     * Get the queue specific stats for a player.
     *
     * @param integer $playerId
     * @param integer $queue
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPlayerQueueStats(int $playerId, int $queue)
    {
        return $this->makeRequest($this->buildUrl('getqueuestats', $playerId, null, null, null, $queue));
    }

    /**
     * Get the information for an ended match.
     *
     * @param integer $matchId
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getMatchModeDetails(int $matchId)
    {
        return $this->makeRequest($this->buildUrl('getmodedetails', $matchId));
    }

    /**
     * Get match details from an ended match.
     *
     * @param integer $matchId
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getMatchDetails(int $matchId)
    {
        return $this->makeRequest($this->buildUrl('getmatchdetails', null, null, $matchId));
    }

    /**
     * Get some basic info for a live/active match.
     *
     * @param integer $matchId
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getActiveMatchDetails(int $matchId)
    {
        return $this->makeRequest($this->buildUrl('getmatchplayerdetails', null, null, $matchId));
    }

    /**
     * Show the current usage and usage limits for the API.
     *
     * @return mixed
     */
    public function getDataUsage()
    {
        return $this->makeRequest($this->buildUrl('getdataused'));
    }

    /**
     * Get the current session id, or set it if it's not set.
     *
     * @return string
     *
     * @codeCoverageIgnore
     * @throws SessionException|GuzzleException
     */
    private function getSession(): string
    {
        if (isset($this->cache)) {
            $cacheId = 'paladinsdev.php-api.sessionId';
        
            if (!$this->cache->contains($cacheId) || $this->cache->fetch($cacheId) == null) {
                try {
                    $response = $this->guzzleClient->get("$this->apiUrl/createsessionJson/$this->devId/{$this->getSignature('createsession')}/{$this->getTimestamp()}");
                    $body = json_decode($response->getBody(), true);

                    if ($body['ret_msg'] != 'Approved' || !isset($body['session_id'])) {
                        throw new SessionException($body['ret_msg']);
                    } else {
                        $this->cache->save($cacheId, $body['session_id'], Carbon::now()->addMinutes(12));

                        return $this->cache->fetch($cacheId);
                    }
                } catch (Exception $e) {
                    throw new SessionException($e->getMessage());
                }
            } else {
                return $this->cache->fetch($cacheId);
            }
        } else {
            if (Carbon::now()->greaterThan($this->sessionExpiresAt) || !isset($this->sessionExpiresAt)) {
                try {
                    $response = $this->guzzleClient->get("$this->apiUrl/createsessionJson/$this->devId/{$this->getSignature('createsession')}/{$this->getTimestamp()}");
                    $body = json_decode($response->getBody(), true);

                    if ($body['ret_msg'] != 'Approved' || !isset($body['session_id'])) {
                        throw new SessionException($body['ret_msg']);
                    } else {
                        $this->sessionId = $body['session_id'];
                        $this->sessionExpiresAt = Carbon::now()->addMinutes(12);

                        return $this->sessionId;
                    }
                } catch (Exception $e) {
                    throw new SessionException($e->getMessage());
                }
            } else {
                return $this->sessionId;
            }
        }

        // if (!Cache::has($cacheId) || Cache::get($cacheId) == null) {
        //     try {
        //         $response = $this->guzzleClient->get("$this->apiUrl/createsessionJson/$this->devId/{$this->getSignature('createsession')}/{$this->getTimestamp()}");
        //         $body = json_decode($response->getBody(), true);

        //         if ($body['ret_msg'] != 'Approved' || !isset($body['session_id'])) {
        //             throw new PaladinsException($body['ret_msg']);
        //         } else {
        //             Cache::put($cacheId, $body['session_id'], Carbon::now()->addMinutes(12));

        //             return Cache::get($cacheId);
        //         }
        //     } catch (\Exception $e) {
        //         throw new PaladinsException($e->getMessage());
        //     }
        // } else {
        //     return Cache::get($cacheId);
        // }
    }

    /**
     * Get the current timestamp in a simple format for API calls.
     *
     * @return string
     * 
     * @codeCoverageIgnore
     */
    private function getTimestamp(): string
    {
        return Carbon::now('UTC')->format('YmdHi') . '00';
    }

    /**
     * Get the authorization signature for the API calls.
     *
     * @param string $method
     * @return string
     * 
     * @codeCoverageIgnore
     */
    private function getSignature(string $method): string
    {
        return md5($this->devId . $method . $this->authKey . $this->getTimestamp());
    }

    /**
     * Build the proper URL for a variety of methods.
     *
     * @param string|null $method
     * @param mixed $player
     * @param int|null $lang
     * @param int|null $match_id
     * @param int|null $champ_id
     * @param int|null $queue
     * @param int|null $tier
     * @param int|null $season
     * @param int|null $platform
     * @return string
     *
     * @throws SessionException|GuzzleException
     * @codeCoverageIgnore
     */
    private function buildUrl(string $method = null, $player = null, int $lang = null, int $match_id = null, int $champ_id = null, int $queue = null, int $tier = null, int $season = null, int $platform = null): string
    {
        $baseUrl = $this->apiUrl . '/' . $method . 'Json/' . $this->devId . '/' . $this->getSignature($method) . '/' . $this->getSession() . '/' . $this->getTimestamp();

        $platform ? ($baseUrl .= '/' . $platform) : null;
        $player ? ($baseUrl .= '/' . $player) : null;
        $champ_id ? ($baseUrl .= '/' . $champ_id) : null;
        $lang ? ($baseUrl .= '/' . $lang) : null;
        $match_id ? ($baseUrl .= '/' . $match_id) : null;
        $queue ? ($baseUrl .= '/' . $queue) : null;
        $tier ? ($baseUrl .= '/' . $tier) : null;
        $season ? ($baseUrl .= '/' . $season) : null;

        return $baseUrl;
    }

    /**
     * Makes the request to the API and error checks it as well.
     *
     * @param string $url
     * @param int|null $maxTries
     * @param int|null $tries
     * @return mixed
     *
     * @throws GuzzleException
     * @throws NotFoundException
     * @throws PaladinsException
     * @codeCoverageIgnore
     */
    private function makeRequest(string $url, int $maxTries = null, int $tries = null)
    {
        if (is_null($maxTries)) {
            $maxTries = 3;
        }

        if (is_null($tries)) {
            $tries = 1;
        }

        $response = $this->guzzleClient->get($url, [
            'request.options' => [
                'exceptions' => false,
            ]
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode == 404) {
            throw new NotFoundException('Resource was not found. URL - ' . $url);
            return;
        }

        if ($statusCode == 502) {
            throw new PaladinsException('Proxy error. URL - ' . $url);
            return;
        }

        $body = json_decode($response->getBody(), true);

        if (isset($body['ret_msg'])) {
            if ($tries < $maxTries) {
                $this->makeRequest($url, $maxTries, ($tries + 1));
            } else {
                throw new PaladinsException($body['ret_msg']);
                return;
            }
        }

        return $body;
    }
}
