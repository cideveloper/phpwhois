<?php

/*
 * This file is part of the PHPWhois package.
 *
 * (c) Peter Kokot <peterkokot@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpWhois;

use Exception;

/**
 * Whois
 * @author Peter Kokot <peterkokot@gmail.com>
 */
class Whois
{
    const VERSION = "1.0-dev";

    public $domain;

    public $tld;

    public $ip = null;

    /**
     * Constructor.
     *
     * @param string $domain Domain name
     */
    public function __construct($domain)
    {
        $this->domain = $this->clean($domain);
        $validator = new Validator();

        // check if domain is ip
        if ($validator->validateIp($this->domain)) {
            $this->ip = $this->domain;
        } elseif ($validator->validateDomain($this->domain)) {
            $domainParts = explode(".", $this->domain);
            $this->tld = strtolower(array_pop($domainParts));
        } else {
            throw new Exception('Domain seems to be invalid.');
        }
    }

    /**
     * Cleans domain name of empty spaces, www, http and https.
     *
     * @param string $domain Domain name
     *
     * @return string
     */
    public function clean($domain)
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#', '', $domain);
        if (substr(strtolower($domain), 0, 4) == "www.") $domain = substr($domain, 4);

        return $domain;
    }

    /**
     * Looks up the current domain or IP.
     *
     * @return string Content of whois lookup.
     */
    public function lookup()
    {
        if ($this->ip) {
            $result = $this->lookupIp($this->ip);
        } else {
            $result = $this->lookupDomain($this->domain);
        }
        return $result;
    }

    /**
     * Domain lookup.
     *
     * @param string @domain Domain name
     *
     * @return string Domain lookup results.
     */
    public function lookupDomain($domain)
    {
        $serverObj = new Server();
        $server = $serverObj->getServerByTld($this->tld);
        if (!$server) {
            return "Error: No appropriate Whois server found for $domain domain!";
        }
        $result = $this->queryServer($server, $domain);
        if (!$result) {
            return "Error: No results retrieved from $server server for $domain domain!";
        } else {
            $primary_result = $result;
            while (strpos($result, "Whois Server:") !== false) {
                preg_match("/Whois Server: (.*)/", $result, $matches);
                $secondary = $matches[1];
                if ($secondary) {
                    $result = $this->queryServer($secondary, $domain);
                    $server = $secondary;
                }
            }
        }
        //return "$domain domain lookup results from $server server:\n\n" . $result;
        return "$primary_result\n\n=========================\n\n$result";
    }

    /**
     * IP lookup.
     *
     * @param string $ip
     *
     * @return string IP lookup results.
     */
    public function lookupIp($ip)
    {
        $results = array();

        $continentServer = new Server();
        foreach ($continentServer->getContinentServers() as $server) {
            $result = $this->queryServer($server, $ip);
                if ($result && !in_array($result, $results)) {
                    $results[$server]= $result;
                }
        }
        $res = "RESULTS FOUND: " . count($results);
        foreach ($results as $server => $result) {
            $res .= "Lookup results for " . $ip . " from " . $server . " server: " . $result;
        }
        return $res;
    }

    /**
     * Queries the whois server.
     *
     * @param string $server
     * @param string $domain
     *
     * @return string Information returned from whois server.
     */
    public function queryServer($server, $domain)
    {
        $port = 43;
        $timeout = 10;
        $fp = @fsockopen($server, $port, $errno, $errstr, $timeout) or die("Socket Error " . $errno . " - " . $errstr);
        // if($server == "whois.verisign-grs.com") $domain = "=".$domain; // whois.verisign-grs.com requires the equals sign ("=") or it returns any result containing the searched string.
        fputs($fp, $domain . "\r\n");
        $out = "";
        while (!feof($fp)) {
            $out .= fgets($fp);
        }
        fclose($fp);

        $res = "";
        if ((strpos(strtolower($out), "error") === false) && (strpos(strtolower($out), "not allocated") === false)) {
            $rows = explode("\n", $out);
            foreach ($rows as $row) {
                $row = trim($row);
                if (($row != '') && ($row{0} != '#') && ($row{0} != '%')) {
                    $res .= $row."\n";
                }
            }
        }
        return $res;
    }

    /**
     * Checks if domain is available or not.
     *
     * @return boolean
     */
    public function isAvailable()
    {
        if ( checkdnsrr($this->domain . '.', 'ANY') ) {
            return false;
        }

        return true;
    }
}
