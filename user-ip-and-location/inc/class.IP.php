<?php
/**
 * IP Class for getting the user's IP address and location.
 */

defined('ABSPATH') || exit; // Exit if accessed directly.

class User_IP_and_Location
{
    public function getIP(): string
    {
        return $this->getIPAddress();
    }

    private function getIPAddress(): string
    {
        $ipHeaders = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
        );
    
        foreach ($ipHeaders as $header) {
            if (isset($_SERVER[$header]) && filter_var($_SERVER[$header], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                $sanitized_value = sanitize_text_field( wp_unslash( $_SERVER[$header] ) );
                return $sanitized_value;
            }
        }
        
        $sanitized_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        return $sanitized_ip;
    }

    public function getContinent(): string
    {
        return $this->getIPField('continent');
    }

    public function getCountry(): string
    {
        return $this->getIPField('country');
    }

    public function getCountryCode(): string
    {
        return $this->getIPField('countryCode');
    }

    public function getRegion(): string
    {
        return $this->getIPField('region');
    }

    public function getRegionName(): string
    {
        return $this->getIPField('regionName');
    }

    public function getCity(): string
    {
        return $this->getIPField('city');
    }

    public function getLat(): string
    {
        return $this->getIPField('lat');
    }

    public function getLon(): string
    {
        return $this->getIPField('lon');
    }

    public function getTimezone(): string
    {
        return $this->getIPField('timezone');
    }

    public function getCurrency(): string
    {
        return $this->getIPField('currency');
    }

    public function getISP(): string
    {
        return $this->getIPField('isp');
    }

    public function getMobile(): string
    {
        return $this->getIpMobile('mobile');
    }

    public function getProxy(): string
    {
        return $this->getIpProxy('proxy');
    }

    public function getHosting(): string
    {
        return $this->getIpHosting('hosting');
    }

    private function getIPField(string $field): string
    {
        $ip = $this->getIPAddress();
        $url = 'http://ip-api.com/json/' . $ip . '?fields=status,continent,country,countryCode,region,regionName,city,lat,lon,timezone,currency,isp,mobile,proxy,hosting,query';
        $response = wp_remote_get($url);

        if (is_array($response)) {
            $response_body = json_decode($response['body'], true);
            if (isset($response_body[$field])) {
                return $response_body[$field];
            }
        }

        return '';
    }

    private function getIpMobile(string $ip): int
    {
        $ipMobile = 0;
        $ip = $this->getIPAddress();
        $url = 'http://ip-api.com/json/' . $ip . '?fields=status,continent,country,countryCode,region,regionName,city,lat,lon,timezone,currency,isp,mobile,proxy,hosting';
        $response = wp_remote_get($url);
        
        if (is_array($response)) {
            $responseBody = json_decode($response['body'], true);
            
            if (isset($responseBody['mobile'])) {
                if($responseBody['mobile'] == 0) {
                    $ipMobile = 0;
                } else if($responseBody['mobile'] == 1) 
                {
                    $ipMobile = 1;
                }
            }
        }   
        return $ipMobile;
    }

    private function getIpProxy(string $ip): int
    {
        $ipProxy = 0;
        $ip = $this->getIPAddress();
        $url = 'http://ip-api.com/json/' . $ip . '?fields=status,continent,country,countryCode,region,regionName,city,lat,lon,timezone,currency,isp,mobile,proxy,hosting';
        $response = wp_remote_get($url);

        if (is_array($response)) {
            $responseBody = json_decode($response['body'], true);
            
            if (isset($responseBody['proxy'])) {
                if($responseBody['proxy'] == 0) {
                    $ipProxy = 0;
                } else if($responseBody['proxy'] == 1) 
                {
                    $ipProxy = 1;
                }
            }
        }

        return $ipProxy;
    }

    private function getIpHosting(string $ip): int
    {
        $ipHosting = 0;
        $ip = $this->getIPAddress();
        $url = 'http://ip-api.com/json/' . $ip . '?fields=status,continent,country,countryCode,region,regionName,city,lat,lon,timezone,currency,isp,mobile,proxy,hosting';
        $response = wp_remote_get($url);
        
        if (is_array($response)) {
            $responseBody = json_decode($response['body'], true);
            
            if (isset($responseBody['hosting'])) {
                if($responseBody['hosting'] == 0) {
                    $ipHosting = 0;
                } else if($responseBody['hosting'] == 1) 
                {
                    $ipHosting = 1;
                }
            }
        }
        
        return $ipHosting;
    }

    public function getflag()
        {
            $flag_country = $this->getCountryCode();
            return $flag_country;
        }
}