<?php
namespace IjorTengab\Browser;  

/**
 * Class dasar untuk melakukan request http.
 * Seluruh hasil disimpan dalam property $result.
 */
class HTTPRequester
{
    /**
     * Calling required traits.
     */
    use PropertyAgentTrait;

    /**
     * The main URL to request http.
     */
    private $url;

    /**
     * The $url property can change anytime because of redirect,
     * so we keep information about $original_url for reference.
     */
    public $original_url;

    /**
     * Property untuk menyimpan hasil fungsi parse_url() saat method setUrl()
     * dijalankan. Berguna sebagai referensi, tanpa perlu mengulang.
     */
    public $parse_url;

    /**
     * Property untuk menyimpan error yang terjadi.
     */
    public $error = array();

    /**
     * Property untuk menyimpan options.
     */
    public $options = array(
        'method' => 'GET',
        'data' => NULL,
        'max_redirects' => 3,
        'timeout' => 30.0,
        'context' => NULL,
        'follow_location' => FALSE,
        'proxy_server' => '',
        'proxy_exceptions' => array('localhost', '127.0.0.1'),
        'proxy_port' => 8080,
        'proxy_username' => '',
        'proxy_password' => '',
        'proxy_user_agent' => '',
    );

    public $headers = array();

    public $post = array();

    /**
     * Property untuk menyimpan object dari class Timer.
     * Object ini berguna untuk menghitung waktu request.
     */
    var $timer;

    /**
     * Property untuk menyimpan object dari class ParseHTTP.
     * Object ini adalah hasil dari browsing dari method execute();
     */
    var $result;

    /**
     * Init and prepare default value.
     */
    function __construct($url = NULL) {
        // Set url.
        if (!empty($url)) {
            $this->setUrl($url);
        }

        // Prefered using curl.
        $this->curl(TRUE);

        // Default value.
        $this->options('encoding', 'gzip, deflate');
    }

    /**
     * Method untuk set url kedalam class.
     * Verifikasi akan dilakukan saat set url.
     */
    public function setUrl($url) {
        try {
            $parse_url = parse_url($url);
            if (!isset($parse_url['scheme'])) {
                // Delete current url.
                $this->url = null;
                throw new \Exception('Scheme pada URL tidak diketahui: "' . $url . '".');
            }
            if (!in_array($parse_url['scheme'], array('http', 'https'))) {
                // Delete current url.
                $this->url = null;
                throw new \Exception('Scheme pada URL hanya mendukung http atau https: "' . $url . '".');
            }
            if (!isset($parse_url['host'])) {
                // Delete current url.
                $this->url = null;
                throw new \Exception('Host pada URL tidak diketahui: "' . $url . '".');
            }
            if (!isset($parse_url['path'])) {
                // Untuk mencocokkan info pada cookie, maka path perlu ada,
                // gunakan nilai default.
                $parse_url['path'] = '/';
            }
            // Set property $url and $original_url
            // for now, we must not edit $original_url again.
            $this->url = $url;
            if (!isset($this->original_url)) {
                $this->original_url = $url;
            }
            $this->parse_url = $parse_url;

            return $this;
        }
        catch (\Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }

    public function getUrl() {
        return $this->url;
    }

    /**
     * Switch if you want use curl library as driver to request HTTP.
     * Curl support to compressed response.
     */
    public function curl($switch = TRUE) {
        if ($switch && function_exists('curl_init')) {
            $this->curl = TRUE;
        }
        else {
            $this->curl = FALSE;
        }
        return $this;
    }

    /**
     * Method for retrieve and update property $options.
     */
    public function options() {
        return $this->propertyAgent('options', func_get_args());
    }

    /**
     * Method for retrieve and update property $options.
     */
    public function headers() {
        return $this->propertyAgent('headers', func_get_args());
    }

    /**
     * Method for retrieve and update property $options.
     */
    public function post() {
        return $this->propertyAgent('post', func_get_args());
    }

    /**
     * Main function to browse the URL setted.
     */
    public function execute($url = NULL) {

        /**
         * Mandatory property.
         */
        if (isset($url)) {
            $this->setUrl($url);
        }
        if (!isset($this->timer)) {
            $this->timer = new timer;
        }
        $url = $this->getUrl();
        if (empty($url)) {
            $this->error[] = 'URL not set yet, request canceled.';
            return $this;
        }

        // Browsing.
        $this->preExecute();
        $this->result = $this->_execute();
        $this->postExecute();

        // Operation Error.
        if (isset($this->result->error)) {
            $this->error[] = $this->result->error;
        }

        // Follow location.
        if ($this->options('follow_location')) {
            $this->followLocation();
        }


        return $this;

    }
    /**
     *
     */
    protected function _execute() {
        $method = $this->curl ? 'requesterCurl' : 'requesterStream';
        return $this->{$method}();
    }

    /**
     * Digunakan oleh class extends.
     */
    protected function preExecute() {

    }

    /**
     * Digunakan oleh class extends.
     */
    protected function postExecute() {

    }


    /**
     * Mengambil tugas jika ternyata opsi follow location diset true.
     */
    private function followLocation() {
        switch ($this->result->code) {
            case 301: // Moved permanently
            case 302: // Moved temporarily
            case 307: // Moved temporarily
                $location = $this->result->headers['location'];
                // Jika location baru hanya path, maka ubah menjadi full url.
                if (preg_match('/^\//',$location)) {
                    $parse_url = $this->parse_url;
                    $location = $parse_url['scheme'] . '://' . $parse_url['host'] . $location;
                }
                // Get all options.
                $options = $this->options();
                $options['timeout'] -= $this->timer->read() / 1000;
                if ($options['timeout'] <= 0) {
                    $this->result->code = -1;
                    $this->result->error = 'request timed out';
                }
                elseif ($options['max_redirects']) {
                    $options['max_redirects']--;
                    // We have changed and must renew options.
                    $this->options($options);
                    // We must clear cookie that set in header.
                    $this->headers('Cookie', NULL);
                    // Empty cache filename.
                    $this->cache = NULL;
                    // And last, we must replace an new URL.
                    $this->setUrl($location);
                    // Browse again.
                    return $this->execute();
                }
                break;
        }
    }

    /**
     * Request HTTP using curl library.
     * Curl set to not following redirect (location header response)
     * because we must handle set-cookie and save history.
     * Redirect is handle outside curl.
     */
    protected function requesterCurl() {
        $url = $this->getUrl();
        $uri = $this->parse_url;
        $options = $this->options();
        $headers = $this->headers();
        $post = $this->post();

        // Start curl.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);

        // Set post.
        if (!empty($post)) {
            // Add a new info of headers.
            $headers['Content-Type'] = 'multipart/form-data';
            // $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        // Support proxy.
        $proxy_server = $this->options('proxy_server');
        $proxy_exceptions = $this->options('proxy_exceptions');
        $is_host_not_proxy_exceptions = !in_array(strtolower($uri['host']), $proxy_exceptions, TRUE);
        if ($proxy_server && $is_host_not_proxy_exceptions) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_server);
            $proxy_port = $this->options('proxy_port');
            empty($proxy_port) or curl_setopt($ch, CURLOPT_PROXYPORT, $proxy_port);
            if ($proxy_username = $this->options('proxy_username')) {
                $proxy_password = $this->options('proxy_password');
                $auth = $proxy_username . (!empty($proxy_password) ? ":" . $proxy_password : '');
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
            }
            // Add a new info of headers.
            $headers['Expect'] = ''; // http://stackoverflow.com/questions/6244578/curl-post-file-behind-a-proxy-returns-error
        }

        // CURL Options.
        foreach ($options as $option => $value) {
            switch ($option) {
                case 'timeout':
                    curl_setopt($ch, CURLOPT_TIMEOUT, $value);
                    break;

                case 'referer':
                    curl_setopt($ch, CURLOPT_REFERER, $value);
                    break;

                case 'encoding':
                    curl_setopt($ch, CURLOPT_ENCODING, $value);
                    break;
            }
        }
        // HTTPS.
        if ($uri['scheme'] == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        // Set header.
        $_ = array();
        foreach ($headers as $header => $value) {
            $_[] = $header . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $_);
        // Set URL.
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        // echo "\r\n-----------------\r\n";
        // print_r($response);

        // $parse = preg_split("/\r\n\r\n|\n\n|\r\r/", $response);
        // if (count($parse) > 2) {
            // Kita asumsikan bahwa message HTTP adalah yang berada paling bawah
            // dan header yang paling faktual adalah header sebelumnya.
            // $this->data = array_pop($parse);
            // $response = array_pop($parse);
        // }
        // else {
            // list($response, $this->data) = $parse;
        // }
        // echo '$response';
        // print_r($response);
        // echo '$this->data';
        // print_r($this->data);


        // print_r($info);
        // $result_header = substr($response, 0, $info['header_size']);
        // $result_body = substr($response, $info['header_size']);
        // var_dump($result_header);
        // var_dump($result_body);
        // echo "\r\n-----------------\r\n";
        $error = curl_errno($ch);
        curl_close($ch);
        $result = new ParseHttp;
        // $info is passing by curl.
        if (isset($info['request_header'])) {
            $result->request = $info['request_header'];
        }
        if ($error === 0) {
            $result->parse($response);
        }
        else {
            $result->code = -1;
            switch ($error) {
                case 6:
                    $result->error = 'cannot resolve host';
                    break;

                case 28:
                    $result->error = 'request timed out';
                    break;

                default:
                    $result->error = 'error occured';
                    break;
            }
        }
        return $result;
    }

    /**
     * Request HTTP using stream.
     * This method is modified from function drupal_http_request in Drupal 7.
     */
    protected function requesterStream() {
        $result = new ParseHttp;
        $url = $this->getUrl();
        $uri = $this->parse_url;
        $options = $this->options();
        $headers = $this->headers();
        $post = $this->post();

        // Merge the default headers.
        $headers += array(
            'User-Agent' => 'Drupal (+http://drupal.org/)',
        );
        // stream_socket_client() requires timeout to be a float.
        $options['timeout'] = (float) $options['timeout'];

        // Set post.
        if (!empty($post)) {
            // $headers['Content-Type'] = 'multipart/form-data';
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $options['method'] = 'POST';
            // $options['data'] = http_build_query($post);
            $options['data'] = self::drupal_http_build_query($post);
        }

        // Support proxy.
        $proxy_server = $this->options('proxy_server');
        $proxy_exceptions = $this->options('proxy_exceptions');
        $is_host_not_proxy_exceptions = !in_array(strtolower($uri['host']), $proxy_exceptions, TRUE);
        if ($proxy_server && $is_host_not_proxy_exceptions) {
            // Set the scheme so we open a socket to the proxy server.
            $uri['scheme'] = 'proxy';
            // Set the path to be the full URL.
            $uri['path'] = $url;
            // Since the URL is passed as the path, we won't use the parsed query.
            unset($uri['query']);
            // Add in username and password to Proxy-Authorization header if needed.
            if ($proxy_username = $this->options('proxy_username')) {
                $proxy_password = $this->options('proxy_password');
                $headers['Proxy-Authorization'] = 'Basic ' . base64_encode($proxy_username . (!empty($proxy_password) ? ":" . $proxy_password : ''));
            }
            // Some proxies reject requests with any User-Agent headers, while others
            // require a specific one.
            $proxy_user_agent = $this->options('proxy_user_agent');
            // The default value matches neither condition.
            if ($proxy_user_agent === NULL) {
                unset($headers['User-Agent']);
            }
            elseif ($proxy_user_agent) {
                $headers['User-Agent'] = $proxy_user_agent;
            }
        }

        switch ($uri['scheme']) {
            case 'proxy':
                // Make the socket connection to a proxy server.
                $socket = 'tcp://' . $proxy_server . ':' . $this->options('proxy_port');
                // The Host header still needs to match the real request.
                $headers['Host'] = $uri['host'];
                $headers['Host'] .= isset($uri['port']) && $uri['port'] != 80 ? ':' . $uri['port'] : '';
                break;
            case 'http':
            case 'feed':
                $port = isset($uri['port']) ? $uri['port'] : 80;
                $socket = 'tcp://' . $uri['host'] . ':' . $port;
                // RFC 2616: "non-standard ports MUST, default ports MAY be included".
                // We don't add the standard port to prevent from breaking rewrite rules
                // checking the host that do not take into account the port number.
                $headers['Host'] = $uri['host'] . ($port != 80 ? ':' . $port : '');
                break;
            case 'https':
                // Note: Only works when PHP is compiled with OpenSSL support.
                $port = isset($uri['port']) ? $uri['port'] : 443;
                $socket = 'ssl://' . $uri['host'] . ':' . $port;
                $headers['Host'] = $uri['host'] . ($port != 443 ? ':' . $port : '');
                break;
            default:
                $result->error = 'invalid schema ' . $uri['scheme'];
                $result->code = -1003;
                return $result;
        }

        if (empty($options['context'])) {
            $fp = @stream_socket_client($socket, $errno, $errstr, $options['timeout']);
        }
        else {
            // Create a stream with context. Allows verification of a SSL certificate.
            $fp = @stream_socket_client($socket, $errno, $errstr, $options['timeout'], STREAM_CLIENT_CONNECT, $options['context']);
        }

        // Make sure the socket opened properly.
        if (!$fp) {
            // When a network error occurs, we use a negative number so it does not
            // clash with the HTTP status codes.
            $result->code = -$errno;
            $result->error = trim($errstr) ? trim($errstr) : t('Error opening socket @socket', array('@socket' => $socket));
            // Mark that this request failed. This will trigger a check of the web
            // server's ability to make outgoing HTTP requests the next time that
            // requirements checking is performed.
            // See system_requirements().
            // $this->setState('drupal_http_request_fails', TRUE);
            return $result;
        }
        // Construct the path to act on.
        $path = isset($uri['path']) ? $uri['path'] : '/';
        if (isset($uri['query'])) {
            $path .= '?' . $uri['query'];
        }
        // Only add Content-Length if we actually have any content or if it is a POST
        // or PUT request. Some non-standard servers get confused by Content-Length in
        // at least HEAD/GET requests, and Squid always requires Content-Length in
        // POST/PUT requests.
        $content_length = strlen($options['data']);
        if ($content_length > 0 || $options['method'] == 'POST' || $options['method'] == 'PUT') {
            $headers['Content-Length'] = $content_length;
        }
        // If the server URL has a user then attempt to use basic authentication.
        if (isset($uri['user'])) {
            $headers['Authorization'] = 'Basic ' . base64_encode($uri['user'] . (isset($uri['pass']) ? ':' . $uri['pass'] : ':'));
        }
        //
        $request = $options['method'] . ' ' . $path . " HTTP/1.0\r\n";
        foreach ($headers as $name => $value) {
            $request .= $name . ': ' . trim($value) . "\r\n";
        }
        $request .= "\r\n" . $options['data'];
        $result->request = $request;
        // Calculate how much time is left of the original timeout value.
        $timeout = $options['timeout'] - $this->timer->read() / 1000;
        if ($timeout > 0) {
            stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
            fwrite($fp, $request);
        }
        // Fetch response. Due to PHP bugs like http://bugs.php.net/bug.php?id=43782
        // and http://bugs.php.net/bug.php?id=46049 we can't rely on feof(), but
        // instead must invoke stream_get_meta_data() each iteration.
        $info = stream_get_meta_data($fp);
        $alive = !$info['eof'] && !$info['timed_out'];
        $response = '';
        while ($alive) {
            // Calculate how much time is left of the original timeout value.
            $timeout = $options['timeout'] - $this->timer->read() / 1000;
            if ($timeout <= 0) {
                $info['timed_out'] = TRUE;
                break;
            }
            stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
            $chunk = fread($fp, 1024);
            $response .= $chunk;
            $info = stream_get_meta_data($fp);
            $alive = !$info['eof'] && !$info['timed_out'] && $chunk;
        }
        fclose($fp);
        if ($info['timed_out']) {
            $result->code = -1;
            $result->error = 'request timed out';
            return $result;
        }
        // echo "\r\n-----------------\r\n";
        // print_r($response);
        // echo "\r\n-----------------\r\n";
        // Drupal code stop here, next we passing to ParseHttp::parse.
        $result->parse($response);
        return $result;
    }

    /**
     * Function from Drupal 7, drupal_http_build_query().
     */
    protected static function httpBuildQuery(array $query, $parent = '') {
        $params = array();

        foreach ($query as $key => $value) {
            $key = ($parent ? $parent . '[' . rawurlencode($key) . ']' : rawurlencode($key));

            // Recurse into children.
            if (is_array($value)) {
                $params[] = self::httpBuildQuery($value, $key);
            }
            // If a query parameter value is NULL, only append its key.
            elseif (!isset($value)) {
                $params[] = $key;
            }
            else {
                // For better readability of paths in query strings, we decode slashes.
                $params[] = $key . '=' . str_replace('%2F', '/', rawurlencode($value));
            }
        }

        return implode('&', $params);
    }

}
