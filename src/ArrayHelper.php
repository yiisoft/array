<?php

namespace Yiisoft\Arrays;

use InvalidArgumentException;
use Yiisoft\Strings\StringHelper;

/**
 * Yii array helper provides static methods allowing you to deal with arrays more efficiently.
 */
class ArrayHelper
{
    /**
     * Converts an object or an array of objects into an array.
     * @param object|array|string $object the object to be converted into an array
     * @param array $properties a mapping from object class names to the properties that need to put into the resulting arrays.
     * The properties specified for each class is an array of the following format:
     *
     * ```php
     * [
     *     'app\models\Post' => [
     *         'id',
     *         'title',
     *         // the key name in array result => property name
     *         'createTime' => 'created_at',
     *         // the key name in array result => anonymous function
     *         'length' => function ($post) {
     *             return strlen($post->content);
     *         },
     *     ],
     * ]
     * ```
     *
     * The result of `ArrayHelper::toArray($post, $properties)` could be like the following:
     *
     * ```php
     * [
     *     'id' => 123,
     *     'title' => 'test',
     *     'createTime' => '2013-01-01 12:00AM',
     *     'length' => 301,
     * ]
     * ```
     *
     * @param bool $recursive whether to recursively converts properties which are objects into arrays.
     * @return array the array representation of the object
     */
    public static function toArray($object, array $properties = [], bool $recursive = true): array
    {
        if (is_array($object)) {
            if ($recursive) {
                foreach ($object as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        $object[$key] = static::toArray($value, $properties, true);
                    }
                }
            }

            return $object;
        }

        if (is_object($object)) {
            if (!empty($properties)) {
                $className = get_class($object);
                if (!empty($properties[$className])) {
                    $result = [];
                    foreach ($properties[$className] as $key => $name) {
                        if (is_int($key)) {
                            $result[$name] = $object->$name;
                        } else {
                            $result[$key] = static::getValue($object, $name);
                        }
                    }

                    return $recursive ? static::toArray($result, $properties) : $result;
                }
            }
            if ($object instanceof ArrayableInterface) {
                $result = $object->toArray([], [], $recursive);
            } else {
                $result = [];
                foreach ($object as $key => $value) {
                    $result[$key] = $value;
                }
            }

            return $recursive ? static::toArray($result, $properties) : $result;
        }

        return [$object];
    }

    /**
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter
     * will overwrite the former (different from array_merge_recursive).
     * Recursive merging will be conducted if both arrays have an element of array
     * type and are having the same key.
     * For integer-keyed elements, the elements from the latter array will
     * be appended to the former array.
     * You can use [[UnsetArrayValue]] object to unset value from previous array or
     * [[ReplaceArrayValue]] to force replace former value instead of recursive merging.
     * @param array $args arrays to be merged
     * @return array the merged array (the original arrays are not changed.)
     */
    public static function merge(...$args): array
    {
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if ($v instanceof UnsetArrayValue) {
                    unset($res[$k]);
                } elseif ($v instanceof ReplaceArrayValue) {
                    $res[$k] = $v->value;
                } elseif (is_int($k)) {
                    if (array_key_exists($k, $res) && $res[$k] !== $v) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = static::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    /**
     * Retrieves the value of an array element or object property with the given key or property name.
     * If the key does not exist in the array or object, the default value will be returned instead.
     *
     * The key may be specified in a dot format to retrieve the value of a sub-array or the property
     * of an embedded object. In particular, if the key is `x.y.z`, then the returned value would
     * be `$array['x']['y']['z']` or `$array->x->y->z` (if `$array` is an object). If `$array['x']`
     * or `$array->x` is neither an array nor an object, the default value will be returned.
     * Note that if the array already has an element `x.y.z`, then its value will be returned
     * instead of going through the sub-arrays. So it is better to be done specifying an array of key names
     * like `['x', 'y', 'z']`.
     *
     * Below are some usage examples,
     *
     * ```php
     * // working with array
     * $username = \Yiisoft\Arrays\ArrayHelper::getValue($_POST, 'username');
     * // working with object
     * $username = \Yiisoft\Arrays\ArrayHelper::getValue($user, 'username');
     * // working with anonymous function
     * $fullName = \Yiisoft\Arrays\ArrayHelper::getValue($user, function ($user, $defaultValue) {
     *     return $user->firstName . ' ' . $user->lastName;
     * });
     * // using dot format to retrieve the property of embedded object
     * $street = \Yiisoft\Arrays\ArrayHelper::getValue($users, 'address.street');
     * // using an array of keys to retrieve the value
     * $value = \Yiisoft\Arrays\ArrayHelper::getValue($versions, ['1.0', 'date']);
     * ```
     *
     * @param array|object $array array or object to extract value from
     * @param string|\Closure|array $key key name of the array element, an array of keys or property name of the object,
     * or an anonymous function returning the value. The anonymous function signature should be:
     * `function($array, $defaultValue)`.
     * @param mixed $default the default value to be returned if the specified array key does not exist. Not used when
     * getting value from an object.
     * @return mixed the value of the element if found, default value otherwise
     */
    public static function getValue($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (!is_array($array) && !is_object($array)) {
            throw new \InvalidArgumentException('getValue() can not get value from ' . gettype($array) . '. Only array and object are supported.');
        }

        if (is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $array = static::getValue($array, $keyPart, $default);
            }
            return static::getValue($array, $lastKey, $default);
        }

        if (is_array($array) && array_key_exists((string)$key, $array)) {
            return $array[$key];
        }

        if (strpos($key, '.') !== false) {
            foreach (explode('.', $key) as $part) {
                if (is_array($array)) {
                    if (!array_key_exists($part, $array)) {
                        return $default;
                    }
                    $array = $array[$part];
                } elseif (is_object($array)) {
                    if (!property_exists($array, $part) && empty($array)) {
                        return $default;
                    }
                    $array = $array->$part;
                }
            }

            return $array;
        }

        if (is_object($array)) {
            // this is expected to fail if the property does not exist, or __get() is not implemented
            // it is not reliably possible to check whether a property is accessible beforehand
            return $array->$key;
        }

        return $default;
    }

    /**
     * Writes a value into an associative array at the key path specified.
     * If there is no such key path yet, it will be created recursively.
     * If the key exists, it will be overwritten.
     *
     * ```php
     *  $array = [
     *      'key' => [
     *          'in' => [
     *              'val1',
     *              'key' => 'val'
     *          ]
     *      ]
     *  ];
     * ```
     *
     * The result of `ArrayHelper::setValue($array, 'key.in.0', ['arr' => 'val']);` will be the following:
     *
     * ```php
     *  [
     *      'key' => [
     *          'in' => [
     *              ['arr' => 'val'],
     *              'key' => 'val'
     *          ]
     *      ]
     *  ]
     *
     * ```
     *
     * The result of
     * `ArrayHelper::setValue($array, 'key.in', ['arr' => 'val']);` or
     * `ArrayHelper::setValue($array, ['key', 'in'], ['arr' => 'val']);`
     * will be the following:
     *
     * ```php
     *  [
     *      'key' => [
     *          'in' => [
     *              'arr' => 'val'
     *          ]
     *      ]
     *  ]
     * ```
     *
     * @param array $array the array to write the value to
     * @param string|array|null $path the path of where do you want to write a value to `$array`
     * the path can be described by a string when each key should be separated by a dot
     * you can also describe the path as an array of keys
     * if the path is null then `$array` will be assigned the `$value`
     * @param mixed $value the value to be written
     */
    public static function setValue(array &$array, $path, $value): void
    {
        if ($path === null) {
            $array = $value;
            return;
        }

        $keys = is_array($path) ? $path : explode('.', $path);

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key])) {
                $array[$key] = [];
            }
            if (!is_array($array[$key])) {
                $array[$key] = [$array[$key]];
            }
            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }

    /**
     * Removes an item from an array and returns the value. If the key does not exist in the array, the default value
     * will be returned instead.
     *
     * Usage examples,
     *
     * ```php
     * // $array = ['type' => 'A', 'options' => [1, 2]];
     * // working with array
     * $type = \Yiisoft\Arrays\ArrayHelper::remove($array, 'type');
     * // $array content
     * // $array = ['options' => [1, 2]];
     * ```
     *
     * @param array $array the array to extract value from
     * @param string $key key name of the array element
     * @param mixed $default the default value to be returned if the specified key does not exist
     * @return mixed the value of the element if found, default value otherwise
     */
    public static function remove(array &$array, string $key, $default = null)
    {
        if (array_key_exists($key, $array)) {
            $value = $array[$key];
            unset($array[$key]);

            return $value;
        }

        return $default;
    }

    /**
     * Removes items with matching values from the array and returns the removed items.
     *
     * Example,
     *
     * ```php
     * $array = ['Bob' => 'Dylan', 'Michael' => 'Jackson', 'Mick' => 'Jagger', 'Janet' => 'Jackson'];
     * $removed = \Yiisoft\Arrays\ArrayHelper::removeValue($array, 'Jackson');
     * // result:
     * // $array = ['Bob' => 'Dylan', 'Mick' => 'Jagger'];
     * // $removed = ['Michael' => 'Jackson', 'Janet' => 'Jackson'];
     * ```
     *
     * @param array $array the array where to look the value from
     * @param mixed $value the value to remove from the array
     * @return array the items that were removed from the array
     */
    public static function removeValue(array &$array, $value): array
    {
        $result = [];
        foreach ($array as $key => $val) {
            if ($val === $value) {
                $result[$key] = $val;
                unset($array[$key]);
            }
        }

        return $result;
    }

    /**
     * Indexes and/or groups the array according to a specified key.
     * The input should be either multidimensional array or an array of objects.
     *
     * The $key can be either a key name of the sub-array, a property name of object, or an anonymous
     * function that must return the value that will be used as a key.
     *
     * $groups is an array of keys, that will be used to group the input array into one or more sub-arrays based
     * on keys specified.
     *
     * If the `$key` is specified as `null` or a value of an element corresponding to the key is `null` in addition
     * to `$groups` not specified then the element is discarded.
     *
     * For example:
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'data' => 'abc', 'device' => 'laptop'],
     *     ['id' => '345', 'data' => 'def', 'device' => 'tablet'],
     *     ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone'],
     * ];
     * $result = ArrayHelper::index($array, 'id');
     * ```
     *
     * The result will be an associative array, where the key is the value of `id` attribute
     *
     * ```php
     * [
     *     '123' => ['id' => '123', 'data' => 'abc', 'device' => 'laptop'],
     *     '345' => ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone']
     *     // The second element of an original array is overwritten by the last element because of the same id
     * ]
     * ```
     *
     * An anonymous function can be used in the grouping array as well.
     *
     * ```php
     * $result = ArrayHelper::index($array, function ($element) {
     *     return $element['id'];
     * });
     * ```
     *
     * Passing `id` as a third argument will group `$array` by `id`:
     *
     * ```php
     * $result = ArrayHelper::index($array, null, 'id');
     * ```
     *
     * The result will be a multidimensional array grouped by `id` on the first level, by `device` on the second level
     * and indexed by `data` on the third level:
     *
     * ```php
     * [
     *     '123' => [
     *         ['id' => '123', 'data' => 'abc', 'device' => 'laptop']
     *     ],
     *     '345' => [ // all elements with this index are present in the result array
     *         ['id' => '345', 'data' => 'def', 'device' => 'tablet'],
     *         ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone'],
     *     ]
     * ]
     * ```
     *
     * The anonymous function can be used in the array of grouping keys as well:
     *
     * ```php
     * $result = ArrayHelper::index($array, 'data', [function ($element) {
     *     return $element['id'];
     * }, 'device']);
     * ```
     *
     * The result will be a multidimensional array grouped by `id` on the first level, by the `device` on the second one
     * and indexed by the `data` on the third level:
     *
     * ```php
     * [
     *     '123' => [
     *         'laptop' => [
     *             'abc' => ['id' => '123', 'data' => 'abc', 'device' => 'laptop']
     *         ]
     *     ],
     *     '345' => [
     *         'tablet' => [
     *             'def' => ['id' => '345', 'data' => 'def', 'device' => 'tablet']
     *         ],
     *         'smartphone' => [
     *             'hgi' => ['id' => '345', 'data' => 'hgi', 'device' => 'smartphone']
     *         ]
     *     ]
     * ]
     * ```
     *
     * @param array $array the array that needs to be indexed or grouped
     * @param string|\Closure|null $key the column name or anonymous function which result will be used to index the array
     * @param string|string[]|\Closure[]|null $groups the array of keys, that will be used to group the input array
     * by one or more keys. If the $key attribute or its value for the particular element is null and $groups is not
     * defined, the array element will be discarded. Otherwise, if $groups is specified, array element will be added
     * to the result array without any key.
     * @return array the indexed and/or grouped array
     */
    public static function index(array $array, $key, $groups = []): array
    {
        $result = [];
        $groups = (array)$groups;

        foreach ($array as $element) {
            $lastArray = &$result;

            foreach ($groups as $group) {
                $value = static::getValue($element, $group);
                if (!array_key_exists($value, $lastArray)) {
                    $lastArray[$value] = [];
                }
                $lastArray = &$lastArray[$value];
            }

            if ($key === null) {
                if (!empty($groups)) {
                    $lastArray[] = $element;
                }
            } else {
                $value = static::getValue($element, $key);
                if ($value !== null) {
                    if (is_float($value)) {
                        $value = StringHelper::floatToString($value);
                    }
                    $lastArray[$value] = $element;
                }
            }
            unset($lastArray);
        }

        return $result;
    }

    /**
     * Returns the values of a specified column in an array.
     * The input array should be multidimensional or an array of objects.
     *
     * For example,
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'data' => 'abc'],
     *     ['id' => '345', 'data' => 'def'],
     * ];
     * $result = ArrayHelper::getColumn($array, 'id');
     * // the result is: ['123', '345']
     *
     * // using anonymous function
     * $result = ArrayHelper::getColumn($array, function ($element) {
     *     return $element['id'];
     * });
     * ```
     *
     * @param array $array
     * @param string|\Closure $name
     * @param bool $keepKeys whether to maintain the array keys. If false, the resulting array
     * will be re-indexed with integers.
     * @return array the list of column values
     */
    public static function getColumn(array $array, $name, bool $keepKeys = true): array
    {
        $result = [];
        if ($keepKeys) {
            foreach ($array as $k => $element) {
                $result[$k] = static::getValue($element, $name);
            }
        } else {
            foreach ($array as $element) {
                $result[] = static::getValue($element, $name);
            }
        }

        return $result;
    }

    /**
     * Builds a map (key-value pairs) from a multidimensional array or an array of objects.
     * The `$from` and `$to` parameters specify the key names or property names to set up the map.
     * Optionally, one can further group the map according to a grouping field `$group`.
     *
     * For example,
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'name' => 'aaa', 'class' => 'x'],
     *     ['id' => '124', 'name' => 'bbb', 'class' => 'x'],
     *     ['id' => '345', 'name' => 'ccc', 'class' => 'y'],
     * ];
     *
     * $result = ArrayHelper::map($array, 'id', 'name');
     * // the result is:
     * // [
     * //     '123' => 'aaa',
     * //     '124' => 'bbb',
     * //     '345' => 'ccc',
     * // ]
     *
     * $result = ArrayHelper::map($array, 'id', 'name', 'class');
     * // the result is:
     * // [
     * //     'x' => [
     * //         '123' => 'aaa',
     * //         '124' => 'bbb',
     * //     ],
     * //     'y' => [
     * //         '345' => 'ccc',
     * //     ],
     * // ]
     * ```
     *
     * @param array $array
     * @param string|\Closure $from
     * @param string|\Closure $to
     * @param string|\Closure $group
     * @return array
     */
    public static function map(array $array, $from, $to, $group = null): array
    {
        if ($group === null) {
            return array_column($array, $to, $from);
        }

        $result = [];
        foreach ($array as $element) {
            $key = static::getValue($element, $from);
            $result[static::getValue($element, $group)][$key] = static::getValue($element, $to);
        }

        return $result;
    }

    /**
     * Checks if the given array contains the specified key.
     * This method enhances the `array_key_exists()` function by supporting case-insensitive
     * key comparison.
     * @param array $array the array with keys to check
     * @param string $key the key to check
     * @param bool $caseSensitive whether the key comparison should be case-sensitive
     * @return bool whether the array contains the specified key
     */
    public static function keyExists(array $array, string $key, bool $caseSensitive = true): bool
    {
        if ($caseSensitive) {
            return array_key_exists($key, $array);
        }

        foreach (array_keys($array) as $k) {
            if (strcasecmp($key, $k) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sorts an array of objects or arrays (with the same structure) by one or several keys.
     * @param array $array the array to be sorted. The array will be modified after calling this method.
     * @param string|\Closure|array $key the key(s) to be sorted by. This refers to a key name of the sub-array
     * elements, a property name of the objects, or an anonymous function returning the values for comparison
     * purpose. The anonymous function signature should be: `function($item)`.
     * To sort by multiple keys, provide an array of keys here.
     * @param int|array $direction the sorting direction. It can be either `SORT_ASC` or `SORT_DESC`.
     * When sorting by multiple keys with different sorting directions, use an array of sorting directions.
     * @param int|array $sortFlag the PHP sort flag. Valid values include
     * `SORT_REGULAR`, `SORT_NUMERIC`, `SORT_STRING`, `SORT_LOCALE_STRING`, `SORT_NATURAL` and `SORT_FLAG_CASE`.
     * Please refer to [PHP manual](http://php.net/manual/en/function.sort.php)
     * for more details. When sorting by multiple keys with different sort flags, use an array of sort flags.
     * @throws InvalidArgumentException if the $direction or $sortFlag parameters do not have
     * correct number of elements as that of $key.
     */
    public static function multisort(array &$array, $key, $direction = SORT_ASC, $sortFlag = SORT_REGULAR): void
    {
        $keys = is_array($key) ? $key : [$key];
        if (empty($keys) || empty($array)) {
            return;
        }

        if (is_callable($key)) {
            $keysTemp = static::getColumn($array, $key);
            // Check if the array is multidimensional
            if (count($keysTemp) !== count($keysTemp, COUNT_RECURSIVE)) {
                // If it is multidimensional then unify array and get keys
                $keys = array_unique($keysTemp, SORT_REGULAR)[0];
            }
        }

        $n = count($keys);
        if (is_scalar($direction)) {
            $direction = array_fill(0, $n, $direction);
        } elseif (count($direction) !== $n) {
            throw new InvalidArgumentException('The length of $direction parameter must be the same as that of $keys.');
        }

        if (is_scalar($sortFlag)) {
            $sortFlag = array_fill(0, $n, $sortFlag);
        } elseif (count($sortFlag) !== $n) {
            throw new InvalidArgumentException('The length of $sortFlag parameter must be the same as that of $keys.');
        }

        $args = [];
        foreach ($keys as $i => $iKey) {
            $flag = $sortFlag[$i];
            $args[] = static::getColumn($array, $iKey);
            $args[] = $direction[$i];
            $args[] = $flag;
        }

        // This fix is used for cases when main sorting specified by columns has equal values
        // Without it it will lead to Fatal Error: Nesting level too deep - recursive dependency?
        $args[] = range(1, count($array));
        $args[] = SORT_ASC;
        $args[] = SORT_NUMERIC;

        $args[] = &$array;
        array_multisort(...$args);
    }

    /**
     * Encodes special characters in an array of strings into HTML entities.
     * Only array values will be encoded by default.
     * If a value is an array, this method will also encode it recursively.
     * Only string values will be encoded.
     * @param array $data data to be encoded
     * @param bool $valuesOnly whether to encode array values only. If false,
     * both the array keys and array values will be encoded.
     * @param string $charset the charset that the data is using. If not set,
     * [[\yii\base\Application::charset]] will be used.
     * @return array the encoded data
     * @see http://www.php.net/manual/en/function.htmlspecialchars.php
     */
    public static function htmlEncode(array $data, bool $valuesOnly = true, string $charset = null): array
    {
        $d = [];
        foreach ($data as $key => $value) {
            if (!$valuesOnly && is_string($key)) {
                $key = StringHelper::htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
            }
            if (is_string($value)) {
                $d[$key] = StringHelper::htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
            } elseif (is_array($value)) {
                $d[$key] = static::htmlEncode($value, $valuesOnly, $charset);
            } else {
                $d[$key] = $value;
            }
        }

        return $d;
    }

    /**
     * Decodes HTML entities into the corresponding characters in an array of strings.
     * Only array values will be decoded by default.
     * If a value is an array, this method will also decode it recursively.
     * Only string values will be decoded.
     * @param array $data data to be decoded
     * @param bool $valuesOnly whether to decode array values only. If false,
     * both the array keys and array values will be decoded.
     * @return array the decoded data
     * @see http://www.php.net/manual/en/function.htmlspecialchars-decode.php
     */
    public static function htmlDecode(array $data, bool $valuesOnly = true): array
    {
        $d = [];
        foreach ($data as $key => $value) {
            if (!$valuesOnly && is_string($key)) {
                $key = htmlspecialchars_decode($key, ENT_QUOTES);
            }
            if (is_string($value)) {
                $d[$key] = htmlspecialchars_decode($value, ENT_QUOTES);
            } elseif (is_array($value)) {
                $d[$key] = static::htmlDecode($value);
            } else {
                $d[$key] = $value;
            }
        }

        return $d;
    }

    /**
     * Returns a value indicating whether the given array is an associative array.
     *
     * An array is associative if all its keys are strings. If `$allStrings` is false,
     * then an array will be treated as associative if at least one of its keys is a string.
     *
     * Note that an empty array will NOT be considered associative.
     *
     * @param array $array the array being checked
     * @param bool $allStrings whether the array keys must be all strings in order for
     * the array to be treated as associative.
     * @return bool whether the array is associative
     */
    public static function isAssociative(array $array, bool $allStrings = true): bool
    {
        if ($array === []) {
            return false;
        }

        if ($allStrings) {
            foreach ($array as $key => $value) {
                if (!is_string($key)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($array as $key => $value) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a value indicating whether the given array is an indexed array.
     *
     * An array is indexed if all its keys are integers. If `$consecutive` is true,
     * then the array keys must be a consecutive sequence starting from 0.
     *
     * Note that an empty array will be considered indexed.
     *
     * @param array $array the array being checked
     * @param bool $consecutive whether the array keys must be a consecutive sequence
     * in order for the array to be treated as indexed.
     * @return bool whether the array is indexed
     */
    public static function isIndexed(array $array, bool $consecutive = false): bool
    {
        if ($array === []) {
            return true;
        }

        if ($consecutive) {
            return array_keys($array) === range(0, count($array) - 1);
        }

        foreach ($array as $key => $value) {
            if (!is_int($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether an array or [[\Traversable]] contains an element.
     *
     * This method does the same as the PHP function [in_array()](http://php.net/manual/en/function.in-array.php)
     * but additionally works for objects that implement the [[\Traversable]] interface.
     * @param mixed $needle The value to look for.
     * @param iterable $haystack The set of values to search.
     * @param bool $strict Whether to enable strict (`===`) comparison.
     * @return bool `true` if `$needle` was found in `$haystack`, `false` otherwise.
     * @throws InvalidArgumentException if `$haystack` is neither traversable nor an array.
     * @see http://php.net/manual/en/function.in-array.php
     */
    public static function isIn($needle, iterable $haystack, bool $strict = false): bool
    {
        if (is_array($haystack)) {
            return in_array($needle, $haystack, $strict);
        }

        foreach ($haystack as $value) {
            if ($needle == $value && (!$strict || $needle === $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether a variable is an array or [[\Traversable]].
     *
     * This method does the same as the PHP function [is_array()](http://php.net/manual/en/function.is-array.php)
     * but additionally works on objects that implement the [[\Traversable]] interface.
     * @param mixed $var The variable being evaluated.
     * @return bool whether $var is array-like
     * @see http://php.net/manual/en/function.is-array.php
     */
    public static function isTraversable($var): bool
    {
        return is_iterable($var);
    }

    /**
     * Checks whether an array or [[\Traversable]] is a subset of another array or [[\Traversable]].
     *
     * This method will return `true`, if all elements of `$needles` are contained in
     * `$haystack`. If at least one element is missing, `false` will be returned.
     * @param iterable $needles The values that must **all** be in `$haystack`.
     * @param iterable $haystack The set of value to search.
     * @param bool $strict Whether to enable strict (`===`) comparison.
     * @return bool `true` if `$needles` is a subset of `$haystack`, `false` otherwise.
     * @throws InvalidArgumentException if `$haystack` or `$needles` is neither traversable nor an array.
     */
    public static function isSubset(iterable $needles, iterable $haystack, bool $strict = false): bool
    {
        foreach ($needles as $needle) {
            if (!static::isIn($needle, $haystack, $strict)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filters array according to rules specified.
     *
     * For example:
     *
     * ```php
     * $array = [
     *     'A' => [1, 2],
     *     'B' => [
     *         'C' => 1,
     *         'D' => 2,
     *     ],
     *     'E' => 1,
     * ];
     *
     * $result = \Yiisoft\Arrays\ArrayHelper::filter($array, ['A']);
     * // $result will be:
     * // [
     * //     'A' => [1, 2],
     * // ]
     *
     * $result = \Yiisoft\Arrays\ArrayHelper::filter($array, ['A', 'B.C']);
     * // $result will be:
     * // [
     * //     'A' => [1, 2],
     * //     'B' => ['C' => 1],
     * // ]
     *
     * $result = \Yiisoft\Arrays\ArrayHelper::filter($array, ['B', '!B.C']);
     * // $result will be:
     * // [
     * //     'B' => ['D' => 2],
     * // ]
     * ```
     *
     * @param array $array Source array
     * @param array $filters Rules that define array keys which should be left or removed from results.
     * Each rule is:
     * - `var` - `$array['var']` will be left in result.
     * - `var.key` = only `$array['var']['key'] will be left in result.
     * - `!var.key` = `$array['var']['key'] will be removed from result.
     * @return array Filtered array
     */
    public static function filter(array $array, array $filters): array
    {
        $result = [];
        $excludeFilters = [];

        foreach ($filters as $filter) {
            if ($filter[0] === '!') {
                $excludeFilters[] = substr($filter, 1);
                continue;
            }

            $nodeValue = $array; //set $array as root node
            $keys = explode('.', $filter);
            foreach ($keys as $key) {
                if (!array_key_exists($key, $nodeValue)) {
                    continue 2; //Jump to next filter
                }
                $nodeValue = $nodeValue[$key];
            }

            //We've found a value now let's insert it
            $resultNode = &$result;
            foreach ($keys as $key) {
                if (!array_key_exists($key, $resultNode)) {
                    $resultNode[$key] = [];
                }
                $resultNode = &$resultNode[$key];
            }
            $resultNode = $nodeValue;
        }

        foreach ($excludeFilters as $filter) {
            $excludeNode = &$result;
            $keys = explode('.', $filter);
            $numNestedKeys = count($keys) - 1;
            foreach ($keys as $i => $key) {
                if (!array_key_exists($key, $excludeNode)) {
                    continue 2; //Jump to next filter
                }

                if ($i < $numNestedKeys) {
                    $excludeNode = &$excludeNode[$key];
                } else {
                    unset($excludeNode[$key]);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Returns the public member variables of an object.
     * This method is provided such that we can get the public member variables of an object.
     * It is different from "get_object_vars()" because the latter will return private
     * and protected variables if it is called within the object itself.
     * @param object $object the object to be handled
     * @return array|null the public member variables of the object or null if not object given
     */
    public static function getObjectVars(object $object): ?array
    {
        return get_object_vars($object);
    }
}
