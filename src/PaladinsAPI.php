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

/**
 * PHP 7.1 compatibility
 * Additional API methods up to september 2021
 * Updated README file for Laravel 8 singletons compatibility + service providers
 * Added try/catchs for exceptions handling
 * Optimized buildUrl() method
 * New appendVars() method
 * Authored by another contributor
 * @author Aníbal Álvarez <anibalealvarezs@gmail.com>
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
        $this->devId = $devId;
        $this->authKey = $authKey;
        $this->cache = $cacheDriver;
        $this->languageId = 1;
        $this->apiUrl = 'https://api.paladins.com/paladinsapi.svc';
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
    public static function getInstance(
        string $devId = null,
        string $authKey = null,
        Cache $cacheDriver = null
    ): PaladinsAPI {
        if (!isset(self::$instance)) {
            self::$instance = new self($devId, $authKey, $cacheDriver);
        }

        return self::$instance;
    }

    // APIs - Connectivity, Development, & System Status

    /**
     * Get the current Hi-Rez Paladins server status.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function ping()
    {
        try {
            return $this->makeRequest($this->buildUrl('ping', false));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
    }

    /**
     * Get the current Hi-Rez Paladins server status.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function testSession()
    {
        try {
            return $this->makeRequest($this->buildUrl('testsession'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Show the current usage and usage limits for the API.
     *
     * @return mixed
     */
    public function getDataUsage()
    {
        try {
            return $this->makeRequest($this->buildUrl('getdataused'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the current Hi-Rez Paladins server status.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getServerStatus()
    {
        try {
            return $this->makeRequest($this->buildUrl('gethirezserverstatus'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the currnet patch information.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPatchInfo()
    {
        try {
            return $this->makeRequest($this->buildUrl('getpatchinfo'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    // APIs - Champions & Items

    /**
     * Get all the champions for the game.
     *
     * @return mixed
     */
    public function getChampions()
    {
        try {
            return $this->makeRequest($this->buildUrl('getchampions', true, [$this->languageId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the available cards for the requested champion.
     *
     * @param integer $championId
     * @return mixed
     */
    public function getChampionCards(int $championId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getchampioncards', true, [$championId, $this->languageId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
        try {
            return $this->makeRequest($this->buildUrl('getchampionleaderboard', true, [$championId, $queue]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the available skins for the requested champion.
     *
     * @param integer $championId
     * @return mixed
     */
    public function getChampionSkins(int $championId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getchampionskins', true, [$championId, $this->languageId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the available skins for the requested champion.
     *
     * @param integer $championId
     * @return mixed
     */
    public function getChampionRecommendedItems(int $championId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getchampionrecommendeditems', true,
                [$championId, $this->languageId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the available in game items.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getItems()
    {
        try {
            return $this->makeRequest($this->buildUrl('getitems', true, [$this->languageId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the available in game items.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getBountyItems()
    {
        try {
            return $this->makeRequest($this->buildUrl('getbountyitems'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    // APIs - Players & PlayerIds

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
        if (!is_string($player) && !is_int($player)) {
            throw new PaladinsException('The player must be either a name, string, or a player id, integer.');
        }

        if (is_string($player)) {
            $players = $this->getPlayerIdByName($player);

            if (!empty($players)) {
                $firstPlayer = Arr::first($players, function ($value) use ($platform) {
                    return $value['portal_id'] == $platform;
                });
            }

            if (!isset($firstPlayer)) {
                throw new PaladinsException('The requested player could not be found in the Paladins system.');
            } else {
                $playerId = $firstPlayer['player_id'];
            }
        }

        try {
            return $this->makeRequest($this->buildUrl('getplayer', true, [$playerId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the top 50 most watched/recent matches.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPlayerBatch($list)
    {
        try {
            return $this->makeRequest($this->buildUrl('getplayerbatch', true, [$list]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get an array of players with the requested name.
     *
     * @param string $name
     * @return mixed
     */
    public function getPlayerIdByName(string $name)
    {
        try {
            return $this->makeRequest($this->buildUrl('getplayeridbyname', true, [$name]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get a player from PC or PSN. Does not work with Xbox or Switch.
     *
     * @param string $name
     * @param integer $platform
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPlayerIdByPortalUserId(string $name, int $platform = 5)
    {
        try {
            return $this->makeRequest($this->buildUrl('getplayeridbyportaluserid', true, [$name, $platform]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get player ids by the gamertag.
     *
     * @param string $name
     * @param integer $platform
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getPlayerIdsByGamertag(string $name, int $platform = 5)
    {
        try {
            return $this->makeRequest($this->buildUrl('getplayeridsbygamertag', true, [$name, $platform]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
        try {
            return $this->makeRequest($this->buildUrl('getplayeridinfoforxboxandswitch', true, [$name]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    // APIs - PlayerId Info

    /**
     * Get all the friends for the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerFriends(int $playerId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getfriends', true, [$playerId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the champion ranks for the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getChampionRanks(int $playerId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getchampionranks', true, [$playerId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the champion ranks for the requested player. (DEPRECATED)
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerChampionRanks(int $playerId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getchampionranks', true, [$playerId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the champion loadouts for the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerLoadouts(int $playerId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getplayerloadouts', true, [$playerId, $this->languageId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the champion loadouts for the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerAchievements(int $playerId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getplayerachievements', true, [$playerId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the current status of the player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerStatus(int $playerId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getplayerstatus', true, [$playerId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the match history of the requested player.
     *
     * @param integer $playerId
     * @return mixed
     */
    public function getPlayerMatchHistory(int $playerId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getmatchhistory', true, [$playerId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
        try {
            return $this->makeRequest($this->buildUrl('getqueuestats', true, [$playerId, $queue]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the match history of the requested player.
     *
     * @param int $searchPlayer
     * @return mixed
     */
    public function searchPlayers(int $searchPlayer)
    {
        try {
            return $this->makeRequest($this->buildUrl('searchplayers', true, [$searchPlayer]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    // APIs - Match Info

    /**
     * Get the top 50 most watched/recent matches.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getDemoDetails(int $matchId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getdemodetails', true, [$matchId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
        try {
            return $this->makeRequest($this->buildUrl('getmatchdetails', true, [$matchId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get match details from an ended match.
     *
     * @param int $aMatchId
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getMatchDetailsBatch(int $aMatchId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getmatchdetails', true, [$aMatchId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
    public function getMatchIdsByQueue(int $queue = 424, string $date = '2021-01-01', string $hour = "1")
    {
        try {
            return $this->makeRequest($this->buildUrl('getmatchidsbyqueue', true, [$queue, $date, $hour]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
        try {
            return $this->makeRequest($this->buildUrl('getmatchplayerdetails', true, [$matchId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the top 50 most watched/recent matches.
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getTopMatches()
    {
        try {
            return $this->makeRequest($this->buildUrl('gettopmatches'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    // APIs - Leagues, Seasons & Rounds

    /**
     * Get the ranked leaderboard for a tier and a season
     *
     * @param integer $tier
     * @param integer $season
     * @param integer $queue
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getRankedLeaderboard(int $queue = 400, int $tier = 3, int $season = 5)
    {
        try {
            return $this->makeRequest($this->buildUrl('getleagueleaderboard', true, [$queue, $tier, $season]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the seasons and their state for ranked.
     *
     * @param integer $queue
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getRankedSeasons(int $queue = 400)
    {
        try {
            return $this->makeRequest($this->buildUrl('getleagueseasons', true, [$queue]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    // APIs - Team Info

    /**
     * Get all the seasons and their state for ranked.
     *
     * @param int $clanId
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getTeamDetails(int $clanId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getteamdetails', true, [$clanId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the seasons and their state for ranked.
     *
     * @param int $clanId
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getTeamPlayers(int $clanId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getteamplayers', true, [$clanId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get all the seasons and their state for ranked.
     *
     * @param int $searchTeam
     * @return mixed
     * @codeCoverageIgnore
     */
    public function searchTeams(int $searchTeam)
    {
        try {
            return $this->makeRequest($this->buildUrl('searchteams', true, [$searchTeam]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    // APIs - Other

    /**
     * Get the information for an ended match. (DEPRECATED)
     *
     * @param integer $matchId
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getMatchModeDetails(int $matchId)
    {
        try {
            return $this->makeRequest($this->buildUrl('getmodedetails', true, [$matchId]));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the information for an ended match. (DEPRECATED)
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getEsportsProLeagueDetails()
    {
        try {
            return $this->makeRequest($this->buildUrl('getesportsproleaguedetails'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get the information for an ended match. (DEPRECATED)
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function getMotd()
    {
        try {
            return $this->makeRequest($this->buildUrl('getmotd'));
        } catch (GuzzleException | NotFoundException | PaladinsException | SessionException $e) {
            die($e->getMessage());
        }
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
     * @param bool $defaultVars
     * @param array $vars
     * @return string
     *
     * @throws GuzzleException
     * @throws SessionException
     * @codeCoverageIgnore
     */
    private function buildUrl(string $method = null, bool $defaultVars = true, array $vars = []): string
    {
        $baseUrl = $this->apiUrl . '/' . $method . 'Json';
        $defaultVars ? ($baseUrl .= '/' . $this->devId . '/' . $this->getSignature($method) . '/' . $this->getSession() . '/' . $this->getTimestamp()) : null;
        $baseUrl = $this->appendVars($baseUrl, $vars);
        return $baseUrl;
    }

    private function appendVars($url, $vars)
    {
        foreach ($vars as $var) {
            if ($var) {
                $url .= '/' . $var;
            }
        }
        return $url;
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
