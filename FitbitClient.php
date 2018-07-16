<?php
namespace Causal\F2GC;

class FitbitClient extends AbstractClient
{

    /**
     * @return bool
     */
    public function connect(bool $force = false): bool
    {
        if ($this->token !== null && !$force) {
            return true;
        }

        $response = $this->doGet('/login');

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($response);
        libxml_use_internal_errors(false);

        $form = $dom->getElementById('loginForm');

        $fields = [];
        foreach ($form->getElementsByTagName('input') as $item) {
            $fieldName = $item->getAttribute('name');
            $fields[$fieldName] = $item->getAttribute('value');
        }

        $fields['email'] = $this->username;
        $fields['password'] = $this->password;

        // This will update internal cookies
        $this->doPost('/login', $fields);

        // Update the token
        $this->token = $this->getTokenFromCookie();
        return $this->token !== null;
    }

    /**
     * @return null|string
     */
    protected function getTokenFromCookie(): ?string
    {
        $cookies = $this->getCookies();
        if (!empty($cookies['oauth_access_token']) && $cookies['oauth_access_token']['expiration'] > time()) {
            return $cookies['oauth_access_token']['value'];
        }
        return null;
    }

    /**
     * Returns the weight data points.
     *
     * @return array
     */
    public function getWeightValues() : array
    {
        $weightValues = $this->doGet('/1.1/user/-/body/log/weight/graph/all.json?durationType=all');
        return $weightValues['graphValues'];
    }

    protected function doGet(string $relativeUrl, array $data = [])
    {
        return $this->doRequest('GET', $relativeUrl, $data, $relativeUrl === '/login' ? 'https://www.fitbit.com' : null);
    }

    protected function doPost(string $relativeUrl, array $data)
    {
        return $this->doRequest('POST', $relativeUrl, $data, $relativeUrl === '/login' ? 'https://www.fitbit.com' : null);
    }

    protected function doRequest(string $method, string $relativeUrl, array $data, ?string $baseUrl = null)
    {
        $url = (empty($baseUrl) ? 'https://web-api.fitbit.com' : $baseUrl) . $relativeUrl;
        $cookieFileName = $this->getCookieFileName();

        $dataQuery = http_build_query($data);

        $ch = curl_init();

        switch ($method) {
            case 'GET':
                if (!empty($dataQuery)) {
                    $url .= '?' . $dataQuery;
                }
                if (empty($baseUrl)) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'authorization: Bearer ' . $this->token
                    ]);
                }
                break;

            case 'POST':
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
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        //$verbose = fopen('php://temp', 'wb+');
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

        if (empty($baseUrl)) {
            if ($info['http_code'] === 200) {
                return json_decode($result, true);
            }
        }

        return $result;
    }

}
