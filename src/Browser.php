<?php
namespace IjorTengab\Browser;

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
    use FileSystemTrait;

    /**
     * Property untuk menyimpan object dari Cookies.
     * Object ini adalah instance dari class ParseCSV.
     */
    protected $cookie_object;

    /**
     * Nama file untuk penyimpanan data cookie.
     */
    protected $cookie_filename = 'cookie.csv';

    /**
     * Reference of field of cookie.
     */
    protected $cookie_field = ['domain', 'path', 'name', 'value', 'expires', 'httponly', 'secure', 'created'];

    /**
     * Nama file untuk penyimpanan data history.
     */
    protected $history_filename = 'history.log';

    /**
     * Nama file referensi untuk penyimpanan "message body" hasil request.
     */
    protected $_cache_filename = 'cache.html';

    /**
     * Nama file saat ini hasil dari penyimpanan "message body" hasil request.
     */
    protected $cache_filename;

    /**
     * Construct.
     */
    function __construct($url = NULL) {
        // Execute Parent.
        parent::__construct($url);
        // Tambah nilai default dari property $options.
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

        // Set cwd with current working directory as default.
        $this->setCwd(getcwd());
    }

    /**
     * @inherit
     */
    protected function preExecute() {
        if ($this->options('cookie_send')) {
            $this->cookieRead();
        }
    }

    /**
     * @inherit
     */
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
            // agar tidak dilakukan parsing. Parsing hanya dilakukan saat
            // melakukan method get.
            $this->cookie_object = new \parseCSV;
            $this->cookie_object->file = $filename;

            // Wajib mengembalikan true.
            // lihat pada method cookieWrite dan cookieRead
            return true;
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
        if (empty($this->cookie_object) && !$this->cookieInit(true)) {
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
            // Default.
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
            // Inspired from drupal menu function.
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

    /**
     * Menyimpan hasil request http berupa "message body" kedalam file text.
     * Nama file hasil penyimpanan didapat dari property $_cache_filename
     * dan akan dilakukan "rename" otomatis dengan suffix angka serial, dan
     * "current filename" dapat diakses dari property $cache_filename.
     */
    protected function cacheSave() {
        if (empty($this->result->data)) {
            return;
        }
        $_filename = $this->_cache_filename;
        $directory = $this->getCwd();
        $filename = $this->fileNameUniquify($_filename, $directory);
        // Saving.
        $content = $this->result->data;
        try {
            if (@file_put_contents($filename, $content) === FALSE) {
                throw new Exception('Failed to write content to: "' . $this->filename . '".');
            }
            // Save current filename.
            $this->cache_filename = $filename;
        }
        catch (Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }

    /**
     * Menyimpan history request http. Berguna untuk "debugging". Mirip seperti
     * file access.log pada web server.
     */
    protected function historySave() {
        $filename = $this->getCwd() . DIRECTORY_SEPARATOR . $this->history_filename;
        $content = '';
        !isset($this->result->request) or $content .= 'REQUEST:' . "\t" . preg_replace("/\r\n|\n|\r/", "\t", $this->result->request) . PHP_EOL;
        !isset($this->result->headers_raw) or $content .= 'RESPONSE:' . "\t" . implode("\t", $this->result->headers_raw) . PHP_EOL;
        if ($this->options('cache_save')) {
            $content .= 'CACHE:' . "\t" . $this->cache_filename . PHP_EOL;
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
}
