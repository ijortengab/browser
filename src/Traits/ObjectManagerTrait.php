<?php
namespace IjorTengab\Traits;

/**
 * @file
 *   ObjectManagerTrait.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/tools
 *
 * @version
 *   0.0.2
 *
 * Trait berisi kumpulan method terkait object, yakni:
 *   - ::propertyArrayManager(), untuk mengolah property bertipe array.
 *   - ::underScoreToCamelCase(), untuk mengubah string menjadi camelCase.
 *   - ::flatyArray(), mengubah array multidimensi menjadi satu dimensi.
 *
 * Trait ini tidak memiliki repository mandiri, hadir (shipped) bersama
 * project lain. Untuk melihat perkembangan dari trait ini bisa dilihat
 * pada @homepage.
 */
trait ObjectManagerTrait
{
    /**
     * Method untuk melakukan operasi update atau retrieve variable dalam
     * class (atau disebut juga property) bertipe array secara mudah dan
     * mengasyikkan (relative menurut saya sendiri :-).
     *
     * Mendukung array multidimensi, closure (anonymous function), dll.
     *
     * Method ini mengubah CRUD array dari langsung (melalui expresi) menjadi
     * melalui fungsi (method).
     *
     * Contoh Lengkap Penggunaan (diurut mulai dari sederhana sampai rumit):
     *
     *   <?php
     *
     *   $myClass = new MyClass;
     *
     *   // Retrieve melalui cara langsung akses property.
     *   $value = $myClass->option['foo'];
     *   // Retrieve melalui method yang mengasyikkan ini.
     *   $value = $myClass->option('foo');
     *
     *   // Retrieve melalui cara langsung akses property.
     *   $value = $myClass->option['foo']['bar'];
     *   // Retrieve melalui method yang mengasyikkan ini.
     *   $value = $myClass->option('foo][bar');
     *
     *   // Create/Update melalui cara langsung akses property.
     *   $myClass->option['foo'] = 'hallo';
     *   // Update melalui method yang mengasyikkan ini.
     *   $myClass->option('foo', 'hallo');
     *
     *   // Create/Update melalui cara langsung akses property.
     *   $myClass->option['foo'] = 'hallo';
     *   $myClass->option['bar'] = 'world';
     *   // Create/Update melalui method yang mengasyikkan ini.
     *   $myClass->option('foo', 'hallo')->option('bar', 'world');
     *
     *   // Create/Update melalui cara langsung akses property.
     *   $myClass->option['foo']['child'] = 'hallo';
     *   $myClass->option['bar']['child'] = 'world';
     *   // Create/Update melalui method yang mengasyikkan ini.
     *   $myClass->option('foo][child', 'hallo')
     *           ->option('bar][child', 'world');
     *
     *   // Create/Update kemudian Retrieve melalui cara langsung akses property.
     *   $myClass->option['foo']['child'] = 'hallo';
     *   $myClass->option['bar']['child'] = 'world';
     *   $value = $myClass->option['other']['child'];
     *   // Create/Update kemudian Retrieve melalui method yang mengasyikkan ini.
     *   $value = $myClass->option('foo][child', 'hallo')
     *                    ->option('bar][child', 'world')
     *                    ->option('other][child');
     *
     *   // Closure/Lambda function/Anonymous function.
     *   // Create/Update melalui cara langsung akses property.
     *   $say = function() {return 'I Love You';};
     *   $myClass->option['get']['merried'] = $say();
     *   // Create/Update melalui method yang mengasyikkan ini.
     *   $myClass->option('get][merried', function() {return 'I Love You';});
     *
     *   // Closure/Lambda function/Anonymous function with parameter.
     *   $say = function($lang) {
     *       switch ($lang) {
     *           case 'de' : return 'Ich Liebe Dich';
     *           case 'en' : return 'I Love You';
     *           case 'id' : return 'Aku Cinta Kamu';
     *       }
     *   };
     *   // Create/Update melalui cara langsung akses property.
     *   $myClass->option['get']['merried'] = $say('de');
     *   // Create/Update melalui method yang mengasyikkan ini.
     *   $myClass->option('get][merried', $say, 'de');
     *
     *   ?>
     *
     * Cara penggunaan method ini adalah dengan membuat method baru sebagai
     * pembungkus (wrapper). Seperti contoh dibawah ini:
     *
     *   <?php
     *
     *   class MyClass
     *   {
     *
     *       use ObjectManagerTrait;
     *
     *       // Property target.
     *       var $options = array(
     *           'homepage' => 'http://github.com/ijortengab',
     *           'email' => 'm_roji28@yahoo.com',
     *       );
     *
     *       // Method options() dibuat sebagai wrapper method propertyArrayManager().
     *       public function options()
     *       {
     *           return $this->propertyArrayManager('options', func_get_args());
     *       }
     *
     *       public function __construct()
     *       {
     *           // Retrieve semua nilai dari property $options.
     *           $array = $this->options();
     *
     *           // Retrieve value yang mempunyai key 'homepage'.
     *           $homepage = $this->options('homepage');
     *
     *           // Update value yang mempunyai key 'email'.
     *           $this->options('email', 'm.roji28@gmail.com');
     *
     *           // Update keseluruhan nilai dari property $options.
     *           $array = array('name' => 'IjorTengab');
     *           $this->options($array);
     *
     *           // Clear property $options (empty array)
     *           $this->options(NULL);
     *       }
     *   }
     *   ?>
     *
     */
    protected function propertyArrayManager($property, $args = array())
    {
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
                else {
                    // Terinspirasi dari fungsi di Drupal 7:
                    // drupal_array_get_nested_value().
                    $parents = explode('][', $variable);
                    $ref = &$this->{$property};
                    foreach ($parents as $parent) {
                        if (is_array($ref) && array_key_exists($parent, $ref)) {
                            $ref = &$ref[$parent];
                        }
                        else {
                            return;
                        }
                    }
                    return $ref;
                }
                break;

            case 2:
                // It means set info option.
                // Terinspirasi dari fungsi di Drupal 7:
                // drupal_array_set_nested_value().
                $key = array_shift($args);
                $value = array_shift($args);
                $parents = explode('][', $key);
                $ref = &$this->{$property};
                foreach ($parents as $parent) {
                    $ref_before = &$ref;
                    $ref = &$ref[$parent];
                }
                if (!is_string($value) && is_callable($value, true)) {
                    $value = call_user_func($value);
                }
                // Set value or unset if value is null.
                if (is_null($value)) {
                    unset($ref_before[$parent]);
                }
                else {
                    $ref = $value;
                }
                break;

            default:
                // Untuk argument lebih dari dua.
                $key = array_shift($args);
                $parents = explode('][', $key);
                $ref = &$this->{$property};
                foreach ($parents as $parent) {
                    $ref = &$ref[$parent];
                }
                // Argument kedua diasumsikan adalah fungsi, argument ketiga
                // dan seterusnya adalah argument untuk dimasukkan kedalam
                // fungsi.
                $callable = array_shift($args);
                if (is_callable($callable)) {
                    $ref = call_user_func_array($callable, $args);
                }
        }

        // Return object back.
        return $this;
    }

    /**
     * Mengubah string dari request_home_page_authenticated, menjadi
     * requestHomePageAuthenticated yang menjadi standard penamaan
     * method pada PSR-1.
     */
    public static function underScoreToCamelCase($string)
    {
        if (empty($string)) {
            return;
        }
        $string = strtolower($string);
        $explode = explode('_', $string);
        do {
            if (!isset($x)) {
                $x = 0;
                $method = $explode[$x];
            }
            else {
                $method .= ucfirst($explode[$x]);
            }
        } while(count($explode) > ++$x);

        return $method;
    }

    /**
     * Mengubah array multidimensi menjadi satu dimensi.
     *
     * Contoh:
     *
     * ```php
     * // Drupal 7 fields.
     * $array = [
     *     'field_file_1' => [
     *         'und' => [
     *             0 => [
     *                 '_weight' => 0,
     *                 'fid' => 0,
     *                 'display' => 1,
     *             ],
     *         ],
     *     ],
     *     'field_file_2' => [
     *         'und' => [
     *             0 => [
     *                 '_weight' => 0,
     *                 'fid' => 0,
     *                 'display' => 1,
     *             ],
     *         ],
     *     ],
     * ];
     * // Array diatas akan menjadi seperti ini:
     * $array = [
     *      ["field_file_1[und][0][_weight]"] => 0,
     *      ["field_file_1[und][0][fid]"] => 0,
     *      ["field_file_1[und][0][display]"] => 1,
     *      ["field_file_2[und][0][_weight]"] => 0,
     *      ["field_file_2[und][0][fid]"] => 0,
     *      ["field_file_2[und][0][display]"] => 1,
     *  ];
     * ```
     *
     * Array dalam bentuk flat ini digunakan khususnya untuk http request post.
     * Class IjorTengab\Browser\Browser menggunakan fungsi ini.
     *
     */
    public static function flatyArray($array)
    {
        $result = [];
        foreach ($array as $key_master => $value) {
            self::_flatyArray($result, $key_master, $value);
        }
        return $result;
    }

    /**
     * Do recursive.
     */
    private static function _flatyArray(&$result, &$key, $value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $_key = $key;
                $key .= '[' . $k . ']';
                self::_flatyArray($result, $key, $v);
                $key = $_key;
            }
        }
        else{
            $result[$key] = $value;
        }
    }
}
