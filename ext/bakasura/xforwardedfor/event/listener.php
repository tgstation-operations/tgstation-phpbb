<?php


namespace bakasura\xforwardedfor\event;

use phpbb\config\db_text;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
    /** @var \phpbb\request\request */
    protected $request;

    /** @var db_text $config_text */
    protected $config_text;

    /**
     * Constructor
     *
     * @param \phpbb\request\request $request Request object
     * @param db_text $config_text
     * @access public
     */
    public function __construct(\phpbb\request\request $request, $config_text)
    {
        $this->request = $request;
        $this->config_text = $config_text;
    }

    /**
     * Assign functions defined in this class to event listeners in the core
     *
     * @return array
     * @static
     * @access public
     */
    public static function getSubscribedEvents()
    {
        return array(
            'core.session_ip_after' => 'core_session_ip_after',
        );
    }

    public static function ip_in_range($ip, $cidr)
    {
        // ensure both IPv6
        if (strpos($ip, ':') !== false && strpos($cidr, ':') !== false)
            return IpUtils::checkIp($ip, $cidr);
        // ensure both IPv4
        else if (strpos($ip, ':') === false && strpos($cidr, ':') === false)
            return IpUtils::checkIp($ip, $cidr);

        return false;
    }

    private function is_trusted_ip($ip)
    {
        $trusted_ips = $this->config_text->get('xff_trusted_ips');

        if (empty($trusted_ips)) {
            return false;
        }

        $trusted_ips = str_replace(' ', '', $trusted_ips);
        $trusted_ips = explode(',', $trusted_ips);

        // check by single IP
        if (in_array($ip, $trusted_ips)) {
            return true;
        }

        // check by IP range
        foreach ($trusted_ips as $trusted_ip) {
            if (strpos($trusted_ip, '/') !== false) {
                if (listener::ip_in_range($ip, $trusted_ip)) {
                    return true;
                }
            }
        }

        // no match
        return false;
    }

    /**
     * Use different IP when proxying through Cloudflare
     *
     * @param object $event The event object
     * @return null
     * @access public
     */
    public function core_session_ip_after($event)
    {
        $ip = $event['ip'];

        // exit if ip not is trusted
        if (!$this->is_trusted_ip($ip)) {
            return;
        }

        $cloudflare_ip = trim($this->request->header('Cf-Connecting-Ip'));

        // validate ip
        if (!empty($cloudflare_ip) && !filter_var($cloudflare_ip, FILTER_VALIDATE_IP)) {
            return;
        }

        $forwarded_for = trim($this->request->header('X-Forwarded-For'));
        if (!empty($forwarded_for)) {
            $forwarded_for = str_replace(' ', '', $forwarded_for);
            $forwarded_for = explode(',', $forwarded_for);
            // real IP ALWAYS is the first
            $forwarded_for = trim($forwarded_for[0]);
        }

        // validate ip
        if (!empty($forwarded_for) && !filter_var($forwarded_for, FILTER_VALIDATE_IP)) {
            return;
        }

        // if both headers are set, must be equal
        if (!empty($cloudflare_ip) && !empty($forwarded_for)) {
            if ($cloudflare_ip !== $forwarded_for)
                return;

            $real_ip = $cloudflare_ip;
        } else if (!empty($cloudflare_ip))
            $real_ip = $cloudflare_ip;
        else if (!empty($forwarded_for))
            $real_ip = $forwarded_for;
        else
            return;

        // set real ip
        $event['ip'] = $real_ip;
    }
}