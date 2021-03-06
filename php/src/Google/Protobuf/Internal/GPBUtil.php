<?php

// Protocol Buffers - Google's data interchange format
// Copyright 2008 Google Inc.  All rights reserved.
// https://developers.google.com/protocol-buffers/
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions are
// met:
//
//     * Redistributions of source code must retain the above copyright
// notice, this list of conditions and the following disclaimer.
//     * Redistributions in binary form must reproduce the above
// copyright notice, this list of conditions and the following disclaimer
// in the documentation and/or other materials provided with the
// distribution.
//     * Neither the name of Google Inc. nor the names of its
// contributors may be used to endorse or promote products derived from
// this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
// "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
// LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
// A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
// OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
// SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
// LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
// DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
// THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
// (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
// OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

namespace Google\Protobuf\Internal;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\MapField;

class GPBUtil
{
    const NANOS_PER_MILLISECOND = 1000000;
    const NANOS_PER_MICROSECOND = 1000;

    public static function divideInt64ToInt32($value, &$high, &$low, $trim = false)
    {
        $isNeg = (bccomp($value, 0) < 0);
        if ($isNeg) {
            $value = bcsub(0, $value);
        }

        $high = bcdiv($value, 4294967296);
        $low = bcmod($value, 4294967296);
        if (bccomp($high, 2147483647) > 0) {
            $high = (int) bcsub($high, 4294967296);
        } else {
            $high = (int) $high;
        }
        if (bccomp($low, 2147483647) > 0) {
            $low = (int) bcsub($low, 4294967296);
        } else {
            $low = (int) $low;
        }

        if ($isNeg) {
            $high = ~$high;
            $low = ~$low;
            $low++;
            if (!$low) {
                $high = (int)($high + 1);
            }
        }

        if ($trim) {
            $high = 0;
        }
    }

    public static function checkString(&$var, $check_utf8)
    {
        if (is_array($var) || is_object($var)) {
            throw new \InvalidArgumentException("Expect string.");
        }
        if (!is_string($var)) {
            $var = strval($var);
        }
        if ($check_utf8 && !preg_match('//u', $var)) {
            throw new \Exception("Expect utf-8 encoding.");
        }
    }

    public static function checkEnum(&$var)
    {
      static::checkInt32($var);
    }

    public static function checkInt32(&$var)
    {
        if (is_numeric($var)) {
            $var = intval($var);
        } else {
            throw new \Exception("Expect integer.");
        }
    }

    public static function checkUint32(&$var)
    {
        if (is_numeric($var)) {
            if (PHP_INT_SIZE === 8) {
                $var = intval($var);
                $var |= ((-(($var >> 31) & 0x1)) & ~0xFFFFFFFF);
            } else {
                if (bccomp($var, 0x7FFFFFFF) > 0) {
                    $var = bcsub($var, "4294967296");
                }
                $var = (int) $var;
            }
        } else {
            throw new \Exception("Expect integer.");
        }
    }

    public static function checkInt64(&$var)
    {
        if (is_numeric($var)) {
            if (PHP_INT_SIZE == 8) {
                $var = intval($var);
            } else {
                if (is_float($var) ||
                    is_integer($var) ||
                    (is_string($var) &&
                         bccomp($var, "9223372036854774784") < 0)) {
                    $var = number_format($var, 0, ".", "");
                }
            }
        } else {
            throw new \Exception("Expect integer.");
        }
    }

    public static function checkUint64(&$var)
    {
        if (is_numeric($var)) {
            if (PHP_INT_SIZE == 8) {
                $var = intval($var);
            } else {
                $var = number_format($var, 0, ".", "");
            }
        } else {
            throw new \Exception("Expect integer.");
        }
    }

    public static function checkFloat(&$var)
    {
        if (is_float($var) || is_numeric($var)) {
            $var = floatval($var);
        } else {
            throw new \Exception("Expect float.");
        }
    }

    public static function checkDouble(&$var)
    {
        if (is_float($var) || is_numeric($var)) {
            $var = floatval($var);
        } else {
            throw new \Exception("Expect float.");
        }
    }

    public static function checkBool(&$var)
    {
        if (is_array($var) || is_object($var)) {
            throw new \Exception("Expect boolean.");
        }
        $var = boolval($var);
    }

    public static function checkMessage(&$var, $klass)
    {
        if (!$var instanceof $klass && !is_null($var)) {
            throw new \Exception("Expect message.");
        }
    }

    public static function checkRepeatedField(&$var, $type, $klass = null)
    {
        if (!$var instanceof RepeatedField && !is_array($var)) {
            throw new \Exception("Expect array.");
        }
        if (is_array($var)) {
            $tmp = new RepeatedField($type, $klass);
            foreach ($var as $value) {
                $tmp[] = $value;
            }
            return $tmp;
        } else {
            if ($var->getType() != $type) {
                throw new \Exception(
                    "Expect repeated field of different type.");
            }
            if ($var->getType() === GPBType::MESSAGE &&
                $var->getClass() !== $klass) {
                throw new \Exception(
                    "Expect repeated field of different message.");
            }
            return $var;
        }
    }

    public static function checkMapField(&$var, $key_type, $value_type, $klass = null)
    {
        if (!$var instanceof MapField && !is_array($var)) {
            throw new \Exception("Expect dict.");
        }
        if (is_array($var)) {
            $tmp = new MapField($key_type, $value_type, $klass);
            foreach ($var as $key => $value) {
                $tmp[$key] = $value;
            }
            return $tmp;
        } else {
            if ($var->getKeyType() != $key_type) {
                throw new \Exception("Expect map field of key type.");
            }
            if ($var->getValueType() != $value_type) {
                throw new \Exception("Expect map field of value type.");
            }
            if ($var->getValueType() === GPBType::MESSAGE &&
                $var->getValueClass() !== $klass) {
                throw new \Exception(
                    "Expect map field of different value message.");
            }
            return $var;
        }
    }

    public static function Int64($value)
    {
        return new Int64($value);
    }

    public static function Uint64($value)
    {
        return new Uint64($value);
    }

    public static function getClassNamePrefix(
        $classname,
        $file_proto)
    {
        $option = $file_proto->getOptions();
        $prefix = is_null($option) ? "" : $option->getPhpClassPrefix();
        if ($prefix !== "") {
            return $prefix;
        }

        $reserved_words = array("Empty", "ECHO", "ARRAY");
        foreach ($reserved_words as $reserved_word) {
            if ($classname === $reserved_word) {
                if ($file_proto->getPackage() === "google.protobuf") {
                    return "GPB";
                } else {
                    return "PB";
                }
            }
        }

        return "";
    }

    public static function getClassNameWithoutPackage(
        $name,
        $file_proto)
    {
        $classname = implode('_', explode('.', $name));
        return static::getClassNamePrefix($classname, $file_proto) . $classname;
    }

    public static function getFullClassName(
        $proto,
        $containing,
        $file_proto,
        &$message_name_without_package,
        &$classname,
        &$fullname)
    {
        // Full name needs to start with '.'.
        $message_name_without_package = $proto->getName();
        if ($containing !== "") {
            $message_name_without_package =
                $containing . "." . $message_name_without_package;
        }

        $package = $file_proto->getPackage();
        if ($package === "") {
            $fullname = "." . $message_name_without_package;
        } else {
            $fullname = "." . $package . "." . $message_name_without_package;
        }

        $class_name_without_package =
            static::getClassNameWithoutPackage($message_name_without_package, $file_proto);

        $option = $file_proto->getOptions();
        if (!is_null($option) && $option->hasPhpNamespace()) {
            $namespace = $option->getPhpNamespace();
            if ($namespace !== "") {
                $classname = $namespace . "\\" . $class_name_without_package;
                return;
            } else {
                $classname = $class_name_without_package;
                return;
            }
        }

        if ($package === "") {
            $classname = $class_name_without_package;
        } else {
            $classname =
                implode('\\', array_map('ucwords', explode('.', $package))).
                "\\".$class_name_without_package;
        }
    }

    public static function combineInt32ToInt64($high, $low)
    {
        $isNeg = $high < 0;
        if ($isNeg) {
            $high = ~$high;
            $low = ~$low;
            $low++;
            if (!$low) {
                $high = (int) ($high + 1);
            }
        }
        $result = bcadd(bcmul($high, 4294967296), $low);
        if ($low < 0) {
            $result = bcadd($result, 4294967296);
        }
        if ($isNeg) {
          $result = bcsub(0, $result);
        }
        return $result;
    }
    
    public static function parseTimestamp($timestamp)
    {
        // prevent parsing timestamps containing with the non-existant year "0000"
        // DateTime::createFromFormat parses without failing but as a nonsensical date
        if (substr($timestamp, 0, 4) === "0000") {
            throw new \Exception("Year cannot be zero.");
        }
        // prevent parsing timestamps ending with a lowercase z
        if (substr($timestamp, -1, 1) === "z") {
            throw new \Exception("Timezone cannot be a lowercase z.");
        }
        
        $nanoseconds = 0;
        $periodIndex = strpos($timestamp, ".");
        if ($periodIndex !== false) {
            $nanosecondsLength = 0;
            // find the next non-numeric character in the timestamp to calculate
            // the length of the nanoseconds text
            for ($i = $periodIndex + 1, $length = strlen($timestamp); $i < $length; $i++) {
                if (!is_numeric($timestamp[$i])) {
                    $nanosecondsLength = $i - ($periodIndex + 1);
                    break;
                }
            }
            if ($nanosecondsLength % 3 !== 0) {
                throw new \Exception("Nanoseconds must be disible by 3.");
            }
            if ($nanosecondsLength > 9) {
                throw new \Exception("Nanoseconds must be in the range of 0 to 999,999,999 nanoseconds.");
            }
            if ($nanosecondsLength > 0) {
                $nanoseconds = substr($timestamp, $periodIndex + 1, $nanosecondsLength);
                $nanoseconds = intval($nanoseconds);

                // remove the nanoseconds and preceding period from the timestamp
                $date = substr($timestamp, 0, $periodIndex - 1);
                $timezone = substr($timestamp, $periodIndex + $nanosecondsLength);
                $timestamp = $date.$timezone;
            }
        }

        $date = \DateTime::createFromFormat(\DateTime::RFC3339, $timestamp, new \DateTimeZone("UTC"));
        if ($date === false) {
            throw new \Exception("Invalid RFC 3339 timestamp.");
        }

        $value = new \Google\Protobuf\Timestamp();
        $seconds = $date->format("U");
        $value->setSeconds($seconds);
        $value->setNanos($nanoseconds);
        return $value;
    }
    
    public static function formatTimestamp($value)
    {
        $nanoseconds = static::getNanosecondsForTimestamp($value->getNanos());
        if (!empty($nanoseconds)) {
            $nanoseconds = ".".$nanoseconds;
        }
        $date = new \DateTime('@'.$value->getSeconds(), new \DateTimeZone("UTC"));
        return $date->format("Y-m-d\TH:i:s".$nanoseconds."\Z");
    }

    public static function getNanosecondsForTimestamp($nanoseconds)
    {
        if ($nanoseconds == 0) {
            return '';
        }
        if ($nanoseconds % static::NANOS_PER_MILLISECOND == 0) {
            return sprintf('%03d', $nanoseconds / static::NANOS_PER_MILLISECOND);
        }
        if ($nanoseconds % static::NANOS_PER_MICROSECOND == 0) {
            return sprintf('%06d', $nanoseconds / static::NANOS_PER_MICROSECOND);
        }
        return sprintf('%09d', $nanoseconds);
    }
}
