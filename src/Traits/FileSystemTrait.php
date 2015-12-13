<?php
namespace IjorTengab\Traits;

/**
 * @file
 *   FileSystemTrait.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/tools
 *
 * @version
 *   0.0.1
 *
 * Trait berisi method untuk melakukan operasi terkait File System, dan membuat
 * Current Working Directory yang terpisah dari yang dimiliki oleh PHP.
 * Trait men-declare property $cwd, pastikan tidak bentrok dengan class parent.
 *
 * Trait ini tidak memiliki repository mandiri, hadir (shipped) bersama
 * project lain. Untuk melihat perkembangan dari trait ini bisa dilihat
 * pada @homepage.
 */
trait FileSystemTrait
{
    /**
     * Current Working Directory (cwd), dibuat terpisah dengan cwd milik PHP.
     */
    private $cwd;

    /**
     * Change directory of property $cwd. Similir with php function chdir().
     */
    public function chDir($cwd)
    {
        $this->cwd = $cwd;
    }

    /**
     * Gets the property of $cwd. Similir with php function getcwd().
     */
    public function getCwd()
    {
        return $this->cwd;
    }

    /**
     * Check and create directory of cwd if not exists.
     *
     * @return
     *   Return null if success or string as error massage
     */
    public function cwdInit()
    {
        try {
            // Jika null, maka gunakan getcwd-nya PHP.
            if (is_null($this->cwd)) {
                $this->chDir(getcwd());
                return;
            }
            $cwd = $this->getCwd();
            // Otomatis menghilangkan trailing slash.
            $cwd = rtrim($cwd, '\\/');
             // Check if exists.
            if (is_dir($cwd)) {
                if (!is_writable($cwd)) {
                    $error = 'Directory is not writable: @cwd.';
                    $error = str_replace(array('@cwd'), array($cwd), $error);
                    throw new \Exception(ucfirst($error));
                }
                return;
            }

            if (file_exists($cwd)) {
                $something = 'something';
                if (is_link($cwd)) {
                    $something = 'link';
                }
                elseif (is_file($cwd)) {
                    $something = 'file';
                }
                $error = 'Create directory cancelled, a @something has same name and exists: @cwd".';
                $error = str_replace(array('@something', '@cwd'), array($something, $cwd), $error);
                throw new \Exception(ucfirst($error));
            }

            if (@mkdir($cwd, 0775, true) === false) {
                $error = 'Create directory failed: @cwd.';
                $error = str_replace(array('@cwd'), array($cwd), $error);
                throw new \Exception(ucfirst($error));
            }
        }
        catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Jika parameter $filename belum full path, maka akan dijadikan
     * full path dengan penambahan dari property $cwd.
     */
    public function setFullPath($filename)
    {
        // Untuk $filename tanpa keterangan full path.
        if (dirname($filename) == '.') {
            $filename = $this->getCwd() . DIRECTORY_SEPARATOR . $filename;
        }
        return $filename;
    }

    /**
     * Mendapatkan nama file baru jika nama file pada argument $basename
     * sudah exists di dalam $directory. Nama file baru didapat dengan
     * auto increment dari $basename. Code bersumber dari drupal versi 7.
     */
    private function fileNameUniquify($basename, $directory)
    {
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
