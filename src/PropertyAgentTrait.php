<?php
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
