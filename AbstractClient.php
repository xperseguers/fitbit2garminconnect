<?php
namespace Causal\F2GC;

abstract class AbstractClient
{

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $userAgent;

    /**
     * @var string
     */
    protected $cookiePath;

    /**
     * @var string
     */
    protected $token;

    /**
     * FitbitClient constructor.
     *
     * @param string $username
     * @param string $password
     */
    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->userAgent = sprintf('Mozilla/5.0 (%s %s %s) ' . str_replace('\\', '-', get_class($this)), php_uname('s'), php_uname('r'), php_uname('m'));
        $this->cookiePath = sys_get_temp_dir();
        $this->token = $this->getTokenFromCookie();
    }

    public abstract function connect() : bool;

    public function disconnect() : bool
    {
        $cookieFileName = $this->getCookieFileName();
        if (file_exists($cookieFileName)) {
            return unlink($cookieFileName);
        }
        return false;
    }

    protected abstract function getTokenFromCookie() : ?string;

    /**
     * Returns the available cookies.
     *
     * @return array
     */
    protected function getCookies(): array
    {
        $cookies = [];
        $cookieFileName = $this->getCookieFileName();
        if (!file_exists($cookieFileName)) {
            return $cookies;
        }
        $contents = file_get_contents($cookieFileName);
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            if (empty($line) || $line{0} === '#') {
                continue;
            }
            $data = explode("\t", $line);
            $cookie = array_combine(
            /** @see http://www.cookiecentral.com/faq/#3.5 */
                ['domain', 'flag', 'path', 'secure', 'expiration', 'name', 'value'],
                $data
            );
            $cookies[$cookie['name']] = $cookie;
        }
        return $cookies;
    }

    /**
     * Returns the cookie file name.
     *
     * @return string
     */
    protected function getCookieFileName(): string
    {
        return $this->cookiePath . sha1($this->username . chr(0) . $this->password . chr(0) . $this->userAgent);
    }

}
