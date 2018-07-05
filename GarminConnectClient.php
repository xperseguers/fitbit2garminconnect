<?php
namespace Causal\F2GC;

class GarminConnectClient extends AbstractClient
{

    /**
     * @return bool
     */
    public function connect(bool $force = false): bool
    {
        if ($this->token !== null && !$force) {
            return true;
        }

        $parameters = [
            'service' => 'https://connect.garmin.com/modern/',
            'webhost' => 'https://connect.garmin.com',
            'source'  => 'https://connect.garmin.com/en-US/signin',
            'redirectAfterAccountLoginUrl' => 'https://connect.garmin.com/modern/',
            'redirectAfterAccountCreationUrl' => 'https://connect.garmin.com/modern/',
            'gauthHost' => 'https://sso.garmin.com/sso',
            'locale' => 'en-US',
            'id' => 'gauth-widget',
            'privateStatementUrl' => '//connect.garmin.com/en-US/privacy/',
            'clientId' => 'GarminConnect',
            'rememberMeShown' => 'true',
            'rememberMeChecked' => 'false',
            'createAccountShown' => 'true',
            'openCreateAccount' => 'false',
            'displayNameShown' => 'false',
            'consumeServiceTicket' => 'false',
            'initialFocus' => 'true',
            'embedWidget' => 'false',
            'generateExtraServiceTicket' => 'false',
            'generateNoServiceTicket' => 'false',
            'globalOptInShown' => 'true',
            'globalOptInChecked' => 'false',
            'mobile' => 'false',
            'connectLegalTerms' => 'true',
            'locationPromptShown' => 'true',
        ];
        $loginUrl = 'https://sso.garmin.com/sso/login?' . http_build_query($parameters);

        $fields = [];
        $fields['username'] = $this->username;
        $fields['password'] = $this->password;
        $fields['embed'] = 'false';
        $fields['rememberme'] = 'on';

        // This will update internal cookies
        $html = $this->doPost($loginUrl, $fields);

        if (preg_match('/var response_url\\s*=.*\\?ticket=([^"]+)"/', $html, $matches)) {
            // We need this to get an actual session
            $this->doGet('/modern/', ['ticket' => $matches[1]]);
            return true;
        }

        return false;
    }

    /**
     * Returns the weight data points.
     *
     * @return array
     */
    public function getWeightValues() : array
    {
        $oneYearAgo = strtotime('-1 year') * 1000;  // 1000 because the Garmin Connect API is Java-based
        $now = time() * 1000;

        $weightValues = $this->doGet('/modern/proxy/userprofile-service/userprofile/personal-information/weightWithOutbound/filterByDay', [
            'from' => $oneYearAgo,
            'until' => $now,
        ]);
        return $weightValues;
    }

    /**
     * Adds a weight data point.
     *
     * @param string $date Format YYYY-MM-DD
     * @param float $weight Weight in kg
     */
    public function addWeight(string $date, float $weight)
    {
        $this->doPost('/modern/proxy/weight-service/user-weight', [
            'value' => $weight,
            'unitKey' => 'kg',
            'date' => $date,
        ]);
    }

    /**
     * @return null|string
     */
    protected function getTokenFromCookie(): ?string
    {
        $cookies = $this->getCookies();
        if (!empty($cookies['SESSIONID']) && $cookies['SESSIONID']['expiration'] > time()) {
            return $cookies['SESSIONID']['value'];
        }
        return null;
    }

    protected function doGet(string $relativeUrl, array $data = [])
    {
        return $this->doRequest('GET', $relativeUrl, $data);
    }

    protected function doPost(string $relativeUrl, array $data)
    {
        return $this->doRequest('POST', $relativeUrl, $data);
    }

    protected function doRequest(string $method, string $relativeUrl, array $data)
    {
        $url = (strpos($relativeUrl, 'https') === false ? 'https://connect.garmin.com' : '') . $relativeUrl;
        $cookieFileName = $this->getCookieFileName();

        $dataQuery = http_build_query($data);

        $ch = curl_init();

        switch ($method) {
            case 'GET':
                if (!empty($dataQuery)) {
                    $url .= '?' . $dataQuery;
                }
                break;

            case 'POST':
                if (strpos($relativeUrl, '/modern/') !== false) {
                    $dataQuery = json_encode($data);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'content-type: application/json',
                        'accept: application/json, text/javascript, */*; q=0.01',
                        'accept-encoding: gzip, deflate, br',
                        'accept-language: fr-FR,fr;q=0.9,en-GB;q=0.8,en;q=0.7,fr-CH;q=0.6,de-CH;q=0.5,de;q=0.4,it-CH;q=0.3,it;q=0.2,en-US;q=0.1',
                        'cache-control: no-cache',
                        'pragma: no-cache',
                        'authority: connect.garmin.com',
                        'adrum: isAjax:true',
                        'dnt: 1',
                        'nk: NT',
                        'origin: https://connect.garmin.com',
                        'referer: https://connect.garmin.com/modern/weight',
                        'x-app-ver: 4.8.0.12',
                        'x-lang: en-US',
                        'x-requested-with: XMLHttpRequest',
                    ]);
                }

                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $dataQuery);
                break;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFileName);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFileName);
        //curl_setopt($ch, CURLOPT_VERBOSE, true);
        //$verbose = fopen('php://temp', 'w+');
        //curl_setopt($ch, CURLOPT_STDERR, $verbose);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($result === FALSE) {
            printf("cUrl error (#%d): %s\n", curl_errno($ch), curl_error($ch));
            //rewind($verbose);
            //$verboseLog = stream_get_contents($verbose);
            //echo "Verbose information:\n", $verboseLog, "\n";
        }
        curl_close($ch);


        if (strpos($relativeUrl, '/modern/') !== false && substr($result, 0, 15) !== '<!DOCTYPE html>') {
            if ($info['http_code'] === 200) {
                return json_decode($result, true);
            }
        }

        return $result;
    }

}
