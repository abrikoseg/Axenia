<?php

class Util
{
    /**
     * Содержится ли элемент в списке
     * @param $enumString String с элементами через , (1, '23', 'test')
     * @param $value
     * @return true/false
     */
    public static function isInEnum($enumString, $value)
    {
        $enumArray = explode(',', $enumString);
        return in_array($value, $enumArray);
    }

    public static function posInEnum($enumString, $value)
    {
        $enumArray = explode(',', $enumString);
        $res = array_search($value, $enumArray);
        if ($res === false) {
            return -1;
        } else {
            return $res;
        }
    }

    public static function arrayInColumn($array)
    {
        $out = '';
        foreach ($array as $item) {
            $out .= $item;
            $out .= "\r\n";
        }

        return $out;
    }

    public static function isBetween($val, $min, $max)
    {
        return ($val >= $min && $val < $max);
    }

    public static function startsWith($haystack, $needle)
    {
        if ($needle === "") {
            return true;
        }
        if (is_array($needle)) {
            if (count($needle) > 0) {
                foreach ($needle as $key) {
                    if (substr($haystack, 0, strlen($key)) === $key) {
                        return true;
                    }
                }

                return false;
            } else {
                return true;
            }
        } else {
            return substr($haystack, 0, strlen($needle)) === $needle ? true : false;
        }
    }

    public static function endsWith($haystack, $needle)
    {
        if ($needle === "") {
            return true;
        }

        if (is_array($needle)) {
            if (count($needle) > 0) {
                foreach ($needle as $key) {
                    $length = strlen($key);
                    if ($length == 0) {
                        return true;
                    }
                    if (substr($haystack, -$length) === $key) {
                        return true;
                    }
                }
                return false;
            } else {
                return true;
            }
        } else {
            $length = strlen($needle);
            if ($length == 0) {
                return true;
            }
            return substr($haystack, -$length) === $needle ? true : false;
        }
    }

    public static function getFullNameUserId($user)
    {
        return self::getFullNameUser($user) . "[" . $user["id"] . "]";
    }

    public static function getFullNameUser($user, $isFull = true)
    {
        $username = isset($user['username']) ? $user['username'] : '';
        $first = isset($user['first_name']) ? $user['first_name'] : (isset($user['firstname']) ? $user['firstname'] : '');
        $last = isset($user['last_name']) ? $user['last_name'] : (isset($user['lastname']) ? $user['lastname'] : '');

        return self::getFullName($username, $first, $last, $isFull);
    }

    public static function getFullName($username, $first, $last, $isFull = true)
    {
        $map = array($username, $first, $last);

        $out = '';
        if (self::isNotEmpty($username)) {
            $out .= ':0';
            if ($isFull) {
                if (self::isNotEmpty($first) && self::isNotEmpty($last)) {
                    $out .= ' (:1 :2)';
                } else {
                    if (self::isNotEmpty($first)) {
                        $out .= ' (:1)';
                    } elseif (self::isNotEmpty($last)) {
                        $out .= ' (:2)';
                    }
                }
            }
        } else {
            if (self::isNotEmpty($first) && self::isNotEmpty($last)) {
                $out .= ':1 :2';
            } else {
                if (self::isNotEmpty($first)) {
                    $out .= ':1';
                } elseif (self::isNotEmpty($last)) {
                    $out .= ':2';
                }
            }
        }

        if (self::isNotEmpty($out)) {
            return self::insert($out, $map);
        }

        return false;
    }

    public static function getChatLink($chat)
    {
        if (isset($chat["username"])) {
            $out = "<a href='t.me/" . $chat["username"] . "'>" . $chat["title"] . "</a>";
        } else {
            $out = "<b>" . $chat["title"] . "</b>";
        }

        return $out;
    }

    public static function isNotEmpty($str)
    {
        return $str != null && $str != '';
    }

    /**
     * Replaces variable placeholders inside a $str with any given $data. Each key in the $data array
     * corresponds to a variable placeholder name in $str.
     * Example:
     * ```
     * Text::insert(':name is :age years old.', ['name' => 'Bob', 'age' => '65']);
     * ```
     * Returns: Bob is 65 years old.
     *
     * Available $options are:
     *
     * - before: The character or string in front of the name of the variable placeholder (Defaults to `:`)
     * - after: The character or string after the name of the variable placeholder (Defaults to null)
     * - escape: The character or string used to escape the before character / string (Defaults to `\`)
     * - format: A regex to use for matching variable placeholders. Default is: `/(?<!\\)\:%s/`
     *   (Overwrites before, after, breaks escape / clean)
     * - clean: A boolean or array with instructions for Text::cleanInsert
     *
     * @param string $str A string containing variable placeholders
     * @param array $data A key => val array where each key stands for a placeholder variable name
     *                        to be replaced with val
     * @param array $options An array of options, see description above
     * @return string
     */
    public static function insert($str, $data, array $options = array())
    {
        $defaults = array('before' => ':', 'after' => null, 'escape' => '\\', 'format' => null, 'clean' => false);
        $options += $defaults;
        $format = $options['format'];
        $data = (array)$data;
        if (empty($data)) {
            return ($options['clean']) ? static::cleanInsert($str, $options) : $str;
        }
        if (!isset($format)) {
            $format = sprintf(
                '/(?<!%s)%s%%s%s/',
                preg_quote($options['escape'], '/'),
                str_replace('%', '%%', preg_quote($options['before'], '/')),
                str_replace('%', '%%', preg_quote($options['after'], '/'))
            );
        }
        if (strpos($str, '?') !== false && is_numeric(key($data))) {
            $offset = 0;
            while (($pos = strpos($str, '?', $offset)) !== false) {
                $val = array_shift($data);
                $offset = $pos + strlen($val);
                $str = substr_replace($str, $val, $pos, 1);
            }

            return ($options['clean']) ? static::cleanInsert($str, $options) : $str;
        }
        asort($data);
        $dataKeys = array_keys($data);
        $hashKeys = array_map('crc32', $dataKeys);
        $tempData = array_combine($dataKeys, $hashKeys);
        krsort($tempData);
        foreach ($tempData as $key => $hashVal) {
            $key = sprintf($format, preg_quote($key, '/'));
            $str = preg_replace($key, $hashVal, $str);
        }
        $dataReplacements = array_combine($hashKeys, array_values($data));
        foreach ($dataReplacements as $tmpHash => $tmpValue) {
            $tmpValue = (is_array($tmpValue)) ? '' : $tmpValue;
            $str = str_replace($tmpHash, $tmpValue, $str);
        }
        if (!isset($options['format']) && isset($options['before'])) {
            $str = str_replace($options['escape'] . $options['before'], $options['before'], $str);
        }

        return ($options['clean']) ? static::cleanInsert($str, $options) : $str;
    }

    /**
     * Cleans up a Text::insert() formatted string with given $options depending on the 'clean' key in
     * $options. The default method used is text but html is also available. The goal of this function
     * is to replace all whitespace and unneeded markup around placeholders that did not get replaced
     * by Text::insert().
     *
     * @param string $str String to clean.
     * @param array $options Options list.
     * @return string
     * @see \Cake\Utility\Text::insert()
     */
    public static function cleanInsert($str, array $options)
    {
        $clean = $options['clean'];
        if (!$clean) {
            return $str;
        }
        if ($clean === true) {
            $clean = array('method' => 'text');
        }
        if (!is_array($clean)) {
            $clean = array('method' => $options['clean']);
        }
        switch ($clean['method']) {
            case 'html':
                $clean += array(
                    'word' => '[\w,.]+',
                    'andText' => true,
                    'replacement' => '',
                );
                $kleenex = sprintf(
                    '/[\s]*[a-z]+=(")(%s%s%s[\s]*)+\\1/i',
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);
                if ($clean['andText']) {
                    $options['clean'] = array('method' => 'text');
                    $str = static::cleanInsert($str, $options);
                }
                break;
            case 'text':
                $clean += array(
                    'word' => '[\w,.]+',
                    'gap' => '[\s]*(?:(?:and|or)[\s]*)?',
                    'replacement' => '',
                );
                $kleenex = sprintf(
                    '/(%s%s%s%s|%s%s%s%s)/',
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/'),
                    $clean['gap'],
                    $clean['gap'],
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);
                break;
        }

        return $str;
    }

    public static function wrapQuotes($obj, $quote = "'")
    {
        return $quote . $obj . $quote;
    }

    public static function html($str, $tag = NULL)
    {
        if ($tag == NULL) {
            return $str;
        } else {
            return "<" . $tag . ">" . $str . "</" . $tag . ">";
        }
    }

    public static function pre($str)
    {
        return self::html($str, "pre");
    }

    public static function b($str)
    {
        return self::html($str, "b");
    }

    public static function i($str)
    {
        return self::html($str, "i");
    }

    public static function code($str)
    {
        return self::html($str, "code");
    }
}