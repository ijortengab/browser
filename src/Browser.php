<?php
/**
 * @file
 *   browser.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/browser
 *
 * @version
 *   0.1
 *
 * @todo
 *   - auto remove cookie yang epxired.
 */

namespace IjorTengab\Browser;

/**
 * Trait berisi method untuk melakukan operasi update atau retrieve
 * value dari property bertipe array secara mudah dan mengasyikkan.
 */
trait PropertyAgent {

    /**
     * Method propertyAgent tidak disarankan untuk penggunaan secara langsung.
     * Sebaiknya ada method lain yang menggunakan method ini sebagai wrapper.
     * Seperti contoh dibawah ini:
     *
     *   <?php
     *
     *   // Code ini berada didalam sebuah class.
     *
     *   use PropertyAgent;
     *
     *   var $options = array(
     *     'homepage' => 'http://github.com/ijortengab',
     *     'email' => 'm_roji28@yahoo.com',
     *   );
     *
     *   // Method options() dibuat sebagai wrapper method propertyAgent().
     *   public function options() {
     *     return $this->propertyAgent('options', func_get_args());
     *   }
     *   ?>
     *
     * Sesuai contoh diatas, maka kita akan mudah untuk melakukan retrieve
     * atau update dari property $options. Property harus bertipe array.
     *
     *   <?php
     *
     *   // Retrieve semua nilai dari property $options.
     *   $array = $this->options();
     *
     *   // Retrieve value yang mempunyai key 'homepage'.
     *   $homepage = $this->options('homepage');
     *
     *   // Update value yang mempunyai key 'email'.
     *   $this->options('email', 'm.roji28@gmail.com');
     *
     *   // Update keseluruhan nilai dari property $options
     *   $this->options($array);
     *
     *   // Clear property $options (empty array)
     *   $this->options(NULL);
     *
     *   ?>
     *
     */
    protected function propertyAgent($property, $args = array()) {

        // Tidak menciptakan property baru.
        // Jika property tidak exists, kembalikan null.
        if (!property_exists(__CLASS__, $property)) {
            return;
        }
        switch (count($args)) {
            case 0:
                // Retrieve value from $property.
                return $this->{$property};

            case 1:
                $variable = array_shift($args);
                // If NULL, it means reset.
                if (is_null($variable)) {
                    $this->{$property} = array();
                }
                // If Array, it meanse replace all value with that array.
                elseif (is_array($variable)) {
                    $this->{$property} = $variable;
                }
                // Otherwise, it means get one info {$property} by key.
                elseif (isset($this->{$property}[$variable])) {
                    return $this->{$property}[$variable];
                }
                return NULL;
                break;

            case 2:
                // It means set info option.
                $key = array_shift($args);
                $value = array_shift($args);
                $this->{$property}[$key] = $value;
                break;
        }

        // Return object back.
        return $this;
    }
}

/**
 * Trait berisi method untuk melakukan operasi terkait File System.
 * Diambil dari dokumentasi PHP berjudul "File System Related Extensions".
 * Sebagian code diambil dari fungsi drupal pada file includes/file.inc.
 *
 * Trait men-declare property:
 *   public $error = array();
 *   private $cwd = __DIR__;
 *
 *
 * Pastikan tidak bentrok dengan class yang menggunakan trait ini.
 * Kunjungi Conflict Resolution pada dokumentasi PHP.
 *
 * @link
 *   http://php.net/manual/en/ref.dir.php
 *   http://php.net/manual/en/language.oop5.traits.php#language.oop5.traits.properties.conflicts
 *
 */
trait FileSystem {


    /**
     * Current Working Directory.
     * Direktori tempat menyimpan apapun untuk keperluan simpan file.
     * Untuk mengubah nilai ini, gunakan method setCwd().
     * Untuk mendapatkan nilai ini, gunakan method getCwd().
     */
    private $cwd = __DIR__;

    /**
     * Property penyimpanan error.
     */
    public $error = array();

    /**
     * Melakukan fungsi mkdir() dan diperkaya dengan penambahan informasi jika
     * terjadi kegagalan.
     */
    public function mkdir($uri, $mode = 0775) {
        try {
            if (is_dir($uri)) {
                return TRUE;
            }
            if (file_exists($uri)) {
                $something = 'something';
                if (is_file($uri)) {
                    $something = 'file';
                }
                if (is_link($uri)) {
                    $something = 'link';
                }
                throw new \Exception('Create directory cancelled, a ' . $something . ' has same name and exists: "' . $uri . '".');
            }
            if (@mkdir($uri, $mode, TRUE) === FALSE) {
                throw new \Exception('Create directory failed: "' . $uri . '".');
            }
            return TRUE;
        }
        catch (\Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }
    
    /**
     * Melakukan perubahan pada property $cwd dan diperkaya dengan penambahan
     * informasi jika terjadi kegagalan.
     */
    public function setCwd($dir, $autocreate = FALSE) {
        try {
            if (!is_dir($dir) && !$autocreate) {
                throw new \Exception('Set directory failed, directory not exists: "' . $dir . '".');
            }
            if (!is_dir($dir) && $autocreate && !$this->mkdir($dir)) {
                throw new \Exception('Set directory failed, trying to create but failed: "' . $dir . '".');
            }
            if (!is_writable($dir)) {
                throw new \Exception('Set directory failed, directory is not writable: "' . $dir . '".');
            }



            // Sebelum set.
            // Copy file-file yang berada di directory lama.
            // $old = $this->getCwd();
            // $new = $dir;
            // $files = array(
                // $this->state_filename,
                // $this->cookie_filename,
                // $this->history_filename,
            // );
            // foreach ($files as $file) {
                // if (file_exists($old . DIRECTORY_SEPARATOR . $file)) {
                    // rename($old . DIRECTORY_SEPARATOR . $file, $new . DIRECTORY_SEPARATOR . $file);
                // }
            // }

            // Directory sudah siap diset.
            $this->cwd = $dir;
            return true;
        }
        catch (\Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }

    /**
     * Mendapatkan nilai dari property $cwd.
     */
    public function getCwd() {
        return $this->cwd;
    }

    

    // Source from Drupal 7's function file_create_filename().
    /**
     * 
     */
    private function filenameUniquify($basename, $directory) {
        // Strip control characters (ASCII value < 32). Though these are allowed in
        // some filesystems, not many applications handle them well.
        $basename = preg_replace('/[\x00-\x1F]/u', '_', $basename);
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            // These characters are not allowed in Windows filenames
            $basename = str_replace(array(':', '*', '?', '"', '<', '>', '|'), '_', $basename);
        }

        // A URI or path may already have a trailing slash or look like "public://".
        if (substr($directory, -1) == DIRECTORY_SEPARATOR) {
            $separator = '';
        }
        else {
            $separator = DIRECTORY_SEPARATOR;
        }

        $destination = $directory . $separator . $basename;

        if (file_exists($destination)) {
            // Destination file already exists, generate an alternative.
            $pos = strrpos($basename, '.');
            if ($pos !== FALSE) {
                $name = substr($basename, 0, $pos);
                $ext = substr($basename, $pos);
            }
            else {
                $name = $basename;
                $ext = '';
            }

            $counter = 0;
            do {
                $destination = $directory . $separator . $name . '_' . $counter++ . $ext;
            } while (file_exists($destination));
        }
        return $destination;
    }

}


/**
 * Class dasar untuk melakukan request http.
 * Seluruh hasil disimpan dalam property $result.
 */
class HTTPRequester
{
    /**
     * Calling required traits.
     */
    use PropertyAgent;

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

/**
 * Class browser.
 *
 * Extends dari HTTPRequester dengan menambah fitur-fitur seperti layaknya
 * sebuah browser.
 *
 * Fitur tersedia:
 *  - Save HTML sebagai cache
 *  - Cookie
 *  - History Log
 *  - Berbagai fungsi static untuk keperluan cepat (shortcut).
 */
class Browser extends HTTPRequester
{

    /**
     * Load berbagai method terkait direktori.
     */
    use FileSystem;



    /**
     * Property untuk menyimpan object dari Cookies.
     * Object ini adalah instance dari class ParseCSV.
     */
    protected $cookie_object;

    /**
     * todo
     */
    protected $cookie_filename = 'cookie.csv';

    /**
     * Reference of field of cookie.
     */
    protected $cookie_field = ['domain', 'path', 'name', 'value', 'expires', 'httponly', 'secure', 'created'];
    
    protected $history_filename = 'history.log';
    
    protected $cache_filename = 'cache.html';
    
    protected $cache_filename_current;


    function __construct($url = NULL) {

        // Execute Parent.
        parent::__construct($url);

        // Tambah nilai default dari property $options
        $added_options = array(
            // Send cookie to the site when request.
            'cookie_send' => FALSE,
            // Accept delivery of site's cookie.
            'cookie_receive' => FALSE,
            // todo doc.
            'cache_save' => FALSE,
            // todo doc.
            'history_save' => FALSE,
        );
        $this->options($this->options() + $added_options);



    }

    protected function preExecute() {
        if ($this->options('cookie_send')) {
            $this->cookieRead();
        }
    }

    protected function postExecute() {
        if ($this->options('cookie_receive')) {
            $this->cookieWrite();
        }
        if ($this->options('cache_save')) {
            $this->cacheSave();
        }
        if ($this->options('history_save')) {
            $this->historySave();
        }
    }

    /**
     * Memulai melakukan instance dari class parseCSV.
     * Menyimpan hasilnya di property $cookie.
     */
    protected function cookieInit($autocreate = FALSE) {
        try {            
            if (!isset($this->cwd)) {
                throw new \Exception('Current Working Directory not set yet.');
            }            
            $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->cookie_filename;
            if (!file_exists($filename) && !$autocreate) {
                return FALSE;
            }
            
            $create = FALSE;
            if (!file_exists($filename)) {
                $create = TRUE;
            }
            else {
                $size = filesize($filename);
                if (empty($size)) {
                    $create = TRUE;
                    // Perlu di clear info filesize
                    // atau error saat eksekusi parent::_rfile().
                    // @see: http://php.net/filesize > Notes.
                    clearstatcache(TRUE, $filename);
                }
            }
            if ($create) {
                $header = implode(',', $this->cookie_field);
                file_put_contents($filename, $header . PHP_EOL);
            }
            if (!file_exists($filename)) {
                throw new \Exception('Failed to create cookie file, build Cookie canceled: "' . $filename . '".');
            }

            // Build object.
            // Jangan masukkan $filename sebagai argument saat calling parseCSV,
            // agar tidak dilakukan parsing. Parsing hanya dilakukan saat melakukan
            // method get.
            $this->cookie_object = new \parseCSV;
            $this->cookie_object->file = $filename;

            // Wajib mengembalikan TRUE.
            // lihat pada method cookieWrite dan cookieRead
            return TRUE;
        }
        catch (\Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }

    /**
     * Write info "Set-Cookie" from response header to storage of cookie.
     * We use CSV file for storage.
     */
    protected function cookieWrite() {
        if (empty($this->cookie_object) && !$this->cookieInit(TRUE)) {
            return;
        }
        if (!isset($this->result->headers['set-cookie'])) {
            return;
        }
        $url = $this->getUrl();
        $parse_url = $this->parse_url;
        $set_cookies = (array) $this->result->headers['set-cookie'];
        $rows = array();
        foreach ($set_cookies as $set_cookie) {
            preg_match_all('/(\w+)=([^;]*)/', $set_cookie, $parts, PREG_SET_ORDER);
            // print_r($parts);
            $first = array_shift($parts);
            $data = array(
                'name' => $first[1],
                'value' => $first[2],
                'created' => microtime(TRUE),
            );
            foreach ($parts as $part) {
                $key = strtolower($part[1]);
                $data[$key] = $part[2];
            }
            // default
            $data += array(
                'domain' => $parse_url['host'],
                'path' => '/',
                'expires' => NULL,
                'httponly' => preg_match('/HttpOnly/i', $set_cookie) ? TRUE : FALSE,
                'secure' => FALSE,
            );
            // Convert expires from Wdy, DD-Mon-YYY HH:MM:SS GMT
            // to unix time stamp
            if (!empty($data['expires'])) {
                $data['expires'] = $data['expires'];
            }
            // Update our original data with the default order.
            $order = array_flip($this->cookie_field);
            $data = array_merge($order, $data);
            $rows[] = $data;
        }
        // Save cookie, append to the file CSV.
        $this->cookie_object->save(NULL, $rows, true);
    }

    /**
     * Read info cookie about domain from storage, and set to request heeader.
     * We use CSV file for storage.
     */
    protected function cookieRead() {
        if (empty($this->cookie_object) && !$this->cookieInit()) {
            return;
        }
        // Parse csv now.
        $this->cookie_object->parse();
        // Get result.
        $data = $this->cookie_object->data;
        // $debugname = 'data'; echo 'var_dump(' . $debugname . '): '; var_dump($$debugname);

        $parse_url = $this->parse_url;
        // Lakukan filtering data.
        $storage = array();
        foreach($data as $key => $row) {
            // Filter domain.
            $domain_match = FALSE;
            if (substr($row['domain'], 0, 1) == '.') {
                $string = preg_quote(substr($row['domain'], 1), '/');
                if (preg_match('/.*' . $string . '/i', $parse_url['host'])) {
                  $domain_match = TRUE;
                }
            }
            elseif ($row['domain'] == $parse_url['host']) {
                $domain_match = TRUE;
            }
            if (!$domain_match) {
                continue;
            }
            // Filter path.
            $path_match = FALSE;
            $string = preg_quote($row['path'], '/');
            if (preg_match('/^' . $string . '/i', $parse_url['path'])) {
                $path_match = TRUE;
            }
            if (!$path_match) {
                continue;
            }
            // Filter expires.
            $is_expired = TRUE;
            if (empty($row['expires'])) {
                $is_expired = FALSE;
            }
            elseif (time() < strtotime($row['expires'])) {
                $is_expired = FALSE;
            }
            if ($is_expired) {
                continue;
            }
            // Filter duplikat dengan mengambil cookie yang paling baru.
            if (isset($storage[$row['name']]) && $storage[$row['name']]['created'] > $row['created']) {
                continue;
            }
            // Finish.
            $storage[$row['name']] = $row;
        }

        if (!empty($storage)) {
            $old = $this->headers('Cookie');
            isset($old) or $old = '';
            foreach($storage as $cookie) {
                if (!empty($old)) {
                    $old .= '; ';
                }
                $old .= $cookie['name'] . '=' . $cookie['value'];
            }
            $this->headers('Cookie', $old);
        }
    }

    protected function cacheSave() {
        if (empty($this->result->data)) {
            return;
        }
        $basename = $this->cache_filename;
        $directory = $this->getCwd();
        $filename = $this->filenameUniquify($basename, $directory);
        $debugvariable = 'basename'; $debugfile = 'debug.html'; ob_start(); echo "<pre>\r\n". 'var_dump(' . $debugvariable . '): '; var_dump($$debugvariable); echo "</pre>\r\n"; $debugoutput = ob_get_contents(); ob_end_clean(); file_put_contents($debugfile, $debugoutput, FILE_APPEND);
        $debugvariable = 'filename'; $debugfile = 'debug.html'; ob_start(); echo "<pre>\r\n". 'var_dump(' . $debugvariable . '): '; var_dump($$debugvariable); echo "</pre>\r\n"; $debugoutput = ob_get_contents(); ob_end_clean(); file_put_contents($debugfile, $debugoutput, FILE_APPEND);
        
        
        $content = $this->result->data;
        
        
        try {
            if (@file_put_contents($filename, $content) === FALSE) {
                throw new Exception('Failed to write content to: "' . $this->filename . '".');
            }
            // Set a new name.
            $this->cache_filename_current = $filename;
        }
        catch (Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }

    /**
     * todo.
     */
    protected function historySave() {
        $filename = $this->getCwd() . DIRECTORY_SEPARATOR . $this->history_filename;
        $content = '';
        !isset($this->result->request) or $content .= 'REQUEST:' . "\t" . preg_replace("/\r\n|\n|\r/", "\t", $this->result->request) . PHP_EOL;
        !isset($this->result->headers_raw) or $content .= 'RESPONSE:' . "\t" . implode("\t", $this->result->headers_raw) . PHP_EOL;
        if ($this->options('cache_save')) {
            $content .= 'CACHE:' . "\t" . $this->cache_filename_current . PHP_EOL;
        }
        $content .= PHP_EOL;
        try {
            if (@file_put_contents($filename, $content, FILE_APPEND) === FALSE) {
                throw new Exception('Failed to write content to: "' . $filename . '".');
            }
        }
        catch (Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }


    // function __destruct() {
        // $now = time();
        // file_put_contents($now, 'a');
    // }

}

class Timer
{

    var $count_down;

    function __construct($count_down = NULL) {
        if (is_int($count_down)) {
            $this->count_down = $count_down;
        }
        $this->start = microtime(TRUE);
    }
    function read() {
        $stop = microtime(TRUE);
        $diff = round(($stop - $this->start) * 1000, 2);
        if (isset($this->time)) {
            $diff += $this->time;
        }
        return $diff;
    }
    // check count down.
    // return sisa waktu
    function countdown() {
        return round($this->count_down - ($this->read() / 1000));
    }
}

/**
 * Class for parsing response of HTTP.
 * This class is modified from function drupal_http_request in Drupal 7.
 */
class ParseHttp
{

    // Property of this class is following the $result object,
    // that define by drupal_http_request().
    var $request;
    var $data;
    var $protocol;
    var $status_message;
    var $headers;
    var $code;
    var $error;

    function __construct($response = NULL) {
        if (isset($response)) {
            $this->parse($response);
        }
    }

    public function parse($response) {
        // Parse response headers from the response body.
        // Be tolerant of malformed HTTP responses that separate header and body with
        // \n\n or \r\r instead of \r\n\r\n.

        // Ada Response yang bentuknya seperti ini:
        /**
         * HTTP/1.1 100 Continue
         *
         * HTTP/1.1 200 OK
         * Date: Mon, 26 Jan 2015 02:40:50 GMT
         * Server: Apache/2.4.3 (Win32) OpenSSL/1.0.1c PHP/5.4.7
         * X-Powered-By: PHP/5.4.7
         * Content-Length: 5
         * Content-Type: text/html
         *
         * <!DOCTYPE><html><head><title></title></head><body></body></html>
         */
        // Sehingga perlu diantisipasi.
        list($header, $data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        // Antisipasi kasus diatas.
        if (strpos($data, 'HTTP') === 0) {
            list($header, $data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $data, 2);
        }
        $this->data = $data;
        $response = preg_split("/\r\n|\n|\r/", $header);
        // Parse the response status line.
        list($protocol, $code, $status_message) = explode(' ', trim(array_shift($response)), 3);
        $this->protocol = $protocol;
        $this->status_message = $status_message;

        // Parse the response headers.
        $this->headers = array();
        // Keep original response header.
        $this->headers_raw = $response;
        while ($line = trim(array_shift($response))) {
            list($name, $value) = explode(':', $line, 2);
            $name = strtolower($name);
            // Pada fungsi drupal drupal_http_request(), digunakan informasi
            // seperti ini:
            // RFC 2109: the Set-Cookie response header comprises the token Set-
            // Cookie:, followed by a comma-separated list of
            // one or more cookies.
            if (isset($this->headers[$name])) {
                if (is_array($this->headers[$name])) {
                    $this->headers[$name][] = trim($value);
                }
                else {
                    $this->headers[$name] = array(
                        $this->headers[$name],
                        trim($value),
                    );
                }
            }
            else {
                $this->headers[$name] = trim($value);
            }
            // if (isset($this->headers[$name]) && $name == 'set-cookie') {
                // $this->headers[$name] .= ',' . trim($value);
            // }
            // else {
                // $this->headers[$name] = trim($value);
            // }
        }

        $responses = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested range not satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
        );
        // RFC 2616 states that all unknown HTTP codes must be treated the same as the
        // base code in their class.
        if (!isset($responses[$code])) {
            $code = floor($code / 100) * 100;
        }
        $this->code = $code;
    }

    public function parse_curl() {

    }

    /**
     * Memecah informasi pada header yang mana menggunakan pola titik koma.
     */
    public function header_explode() {

    }
}

