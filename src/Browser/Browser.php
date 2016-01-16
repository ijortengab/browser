<?php

namespace IjorTengab\Browser;

use IjorTengab\FileSystem\FileName;
use IjorTengab\FileSystem\WorkingDirectory;

/**
 * Class Browser. Extends dari class Engine dengan menambah fitur-fitur
 * seperti layaknya sebuah browser.
 *
 * Fitur tersedia:
 *  - Save HTML sebagai cache
 *  - Cookie
 *  - History Log
 *
 * Todo:
 *   - Method ::profile() dibuat dengan skema unparse User-Agent
 */
class Browser extends Engine
{

    /**
     * Current Working Directory (cwd), dibuat terpisah dengan cwd milik PHP.
     */
    public $cwd;

    /**
     * Property untuk menyimpan object dari Cookies.
     * Object ini adalah instance dari class ParseCSV.
     */
    protected $cookie_object;

    /**
     * Nama file untuk penyimpanan data cookie.
     */
    public $cookie_filename = 'cookie.csv';

    protected $cookie_file_has_changed = false;

    /**
     * Reference of field of cookie.
     */
    protected $cookie_field = ['domain', 'path', 'name', 'value', 'expires', 'httponly', 'secure', 'created'];

    /**
     * Nama file untuk penyimpanan data history.
     */
    public $history_filename = 'history.log';

    /**
     * Nama file referensi untuk penyimpanan "message body" hasil request.
     */
    public $_cache_filename = 'cache.html';

    /**
     * Nama file saat ini hasil dari penyimpanan "message body" hasil request.
     */
    protected $cache_filename;

    /**
     * Construct.
     */
    public function __construct($url = NULL)
    {
        // Execute Parent.
        parent::__construct($url);
        
        // Cwd must initialize when construct.
        $this->cwd = new WorkingDirectory;

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
    }

    /**
     * Create instance and setup with package browser style.
     */
    public static function profile($user_agent_scenario = null)
    {
        // Todo, buat dengan skema unparse User Agent.
        $instance = new Browser;
        $user_agent = self::getUserAgent($user_agent_scenario);
        $instance->options('cookie_receive', true)
                 ->options('cookie_send', true)
                 ->options('follow_location', true)
                 ->options('user_agent', $user_agent);
        return $instance;
    }

    public static function getUserAgent($user_agent_scenario)
    {
        $user_agent_scenario = trim($user_agent_scenario);
        if (empty($user_agent_scenario)) {
            return;
        }
        $user_agent_scenario = strtolower(trim($user_agent_scenario));
        $user_agent = '';
        switch ($user_agent_scenario) {
            // Todo, build random mobile, current Iphone 3.
            case 'mobile':
            case 'mobile browser':
                $user_agent = 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16';
                break;

            case 'mozilla firefox on windows 7':
                $user_agent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:42.0) Gecko/20100101 Firefox/42.0';
                break;

            // Todo, build random desktop.
            case 'desktop':
            default:
                // Default, google chrome on windows 7 64 bit.
                $user_agent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.80 Safari/537.36';default:
                break;
        }
        return $user_agent;
    }

    /**
     * @inherit
     */
    protected function preExecute()
    {
        parent::preExecute();
        if ($this->options('cookie_send')) {
            $this->cookieRead();
        }
    }

    /**
     * @inherit
     */
    protected function postExecute()
    {
        parent::postExecute();
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
    protected function cookieInit($autocreate = FALSE)
    {
        try {
            $filename = $this->cwd->getAbsolutePath($this->cookie_filename);
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
                }
            }
            if ($create) {
                $header = implode(',', $this->cookie_field);
                if (file_put_contents($filename, $header . PHP_EOL) === false) {
                    throw new \Exception('Failed to create cookie file, build Cookie canceled: "' . $filename . '".');
                }
                $this->cookie_file_has_changed = true;
            }

            // Build object.
            // Jangan masukkan $filename sebagai argument saat calling parseCSV,
            // agar tidak langsung dilakukan parsing. Parsing hanya dilakukan
            // saat melakukan method ::cookieRead().
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
    protected function cookieWrite()
    {
        if (!isset($this->result->headers['set-cookie'])) {
            return;
        }
        if (empty($this->cookie_object) && !$this->cookieInit(true)) {
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
        $this->cookie_file_has_changed = true;
    }

    /**
     * Untuk eksekusi berulang-ulang, maka untuk membaca kembali file cookie
     * perlu dilakukan clear cache pada filesize cookie karena class parseCSV
     * pada method ::_rfile() melakukan fungsi filesize() dimana jika cookie
     * ada perubahan karena penambahan data, maka return value pada filesize()
     * belum berubah karena fitur cache pada PHP.
     */
    protected function cookieReload()
    {
        if ($this->cookie_file_has_changed) {
            $filename = $this->cwd->getAbsolutePath($this->cookie_filename);
            clearstatcache(true, $filename);
            $this->cookie_file_has_changed = false;
        }
    }

    /**
     * Menghapus nilai cookie
     *
     * Todo. buat agar bisa clear tanpa perlu hapus file, untuk sementara dibuat
     * rename.
     */
    public function cookieClear($domain = null)
    {
        $cookie_filename = $this->cookie_filename;
        $cookie_filename = $this->cwd->getAbsolutePath($cookie_filename);
        if (file_exists($cookie_filename)) {
            $cookie_filename_new = FileName::createUnique(basename($cookie_filename), dirname($cookie_filename));
            rename($cookie_filename, $cookie_filename_new);
        }
    }

    /**
     * Read info cookie about domain from storage, and set to request heeader.
     * We use CSV file for storage.
     */
    protected function cookieRead()
    {
        if (empty($this->cookie_object) && !$this->cookieInit()) {
            return;
        }
        // Parse csv now.
        $this->cookieReload();
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
    protected function cacheSave()
    {
        if (empty($this->result->data)) {
            return;
        }
        $_filename = $this->cwd->getAbsolutePath($this->_cache_filename);
        $directory = dirname($_filename);
        $basename = basename($_filename);
        $filename = FileName::createUnique($basename, $directory);
        // Saving.
        $content = $this->result->data;
        try {
            if (@file_put_contents($filename, $content) === FALSE) {
                throw new \Exception('Failed to write content to: "' . $filename . '".');
            }
            // Save current filename.
            $this->cache_filename = $filename;
        }
        catch (\Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }

    /**
     * Menyimpan history request http. Berguna untuk "debugging". Mirip seperti
     * file access.log pada web server.
     */
    protected function historySave()
    {
        $filename = $this->cwd->getAbsolutePath($this->history_filename);
        $content = '';
        !isset($this->result->request) or $content .= 'REQUEST:' . "\t" . preg_replace("/\r\n|\n|\r/", "\t", $this->result->request) . PHP_EOL;
        !isset($this->result->headers_raw) or $content .= 'RESPONSE:' . "\t" . $this->result->protocol . ' ' .  $this->result->code . ' ' . $this->result->status_message . "\t\t" . implode("\t", $this->result->headers_raw) . PHP_EOL;
        !isset($this->cache_filename) or $content .= 'CACHE:' . "\t\t" . $this->cache_filename . PHP_EOL;
        if (empty($content)) {
            return;
        }
        $content = 'TIME:' . "\t\t" . date('c') . PHP_EOL . $content . PHP_EOL;
        try {
            if (@file_put_contents($filename, $content, FILE_APPEND) === FALSE) {
                throw new \Exception('Failed to write content to: "' . $filename . '".');
            }
        }
        catch (\Exception $e) {
            $this->error[] = $e->getMessage();
        }
    }

    /**
     * @inherit.
     */
    public function reset()
    {
        $this->cache_filename = null;
        return parent::reset();
    }
}
