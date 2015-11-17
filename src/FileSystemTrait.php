<?php
namespace IjorTengab\Browser;

/**
 * Trait berisi method untuk melakukan operasi terkait File System.
 * Diambil dari dokumentasi PHP berjudul "File System Related Extensions".
 * Sebagian code diambil dari fungsi drupal pada file includes/file.inc.
 *
 * Trait men-declare property:
 *   private $cwd = __DIR__;
 * Pastikan tidak bentrok dengan class yang menggunakan trait ini.
 * Kunjungi Conflict Resolution pada dokumentasi PHP.
 *
 * @link
 *   http://php.net/manual/en/ref.dir.php
 *   http://php.net/manual/en/language.oop5.traits.php#language.oop5.traits.properties.conflicts
 *
 */
trait FileSystemTrait {

    /**
     * Current Working Directory.
     * Direktori tempat menyimpan apapun untuk keperluan simpan file.
     * Untuk mengubah nilai ini, gunakan method setCwd().
     * Untuk mendapatkan nilai ini, gunakan method getCwd().
     */
    private $cwd = __DIR__;

    /**
     * Melakukan fungsi mkdir() dan diperkaya dengan penambahan informasi jika
     * terjadi kegagalan.
     *
     * @return
     *   true if success and string (sentence of reason) if failed.
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
            return $e->getMessage();
        }
    }

    /**
     * Melakukan perubahan pada property $cwd dan diperkaya dengan penambahan
     * informasi jika terjadi kegagalan.
     *
     * @return
     *   true if success and string (sentence of reason) if failed.
     */
    public function setCwd($dir, $autocreate = FALSE) {
        try {
            if (!is_dir($dir)) {
                if ($autocreate) {
                    // Trying to create.
                    $error = $this->mkdir($dir);
                    if (is_string($error)) {
                        throw new \Exception($error . ' ' . 'Set directory failed, trying to create but failed: "' . $dir . '".');
                    }
                }
                else {
                    throw new \Exception('Set directory failed, directory not exists: "' . $dir . '".');
                }
            }
            if (!is_writable($dir)) {
                throw new \Exception('Set directory failed, directory is not writable: "' . $dir . '".');
            }
            $this->cwd = $dir;
            return true;
        }
        catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Mendapatkan nilai dari property $cwd.
     */
    public function getCwd() {
        return $this->cwd;
    }

    /**
     * Mendapatkan nama file baru jika nama file pada argument $basename
     * sudah exists di dalam $directory. Nama file baru didapat dengan
     * auto increment dari $basename.
     */
    private function fileNameUniquify($basename, $directory) {
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
