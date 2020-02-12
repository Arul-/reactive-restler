<?php

namespace Luracast\Restler\Utils;

use Exception;
use Luracast\Restler\Exceptions\HttpException;

/**
 * Parses the PHPDoc comments for metadata. Inspired by `Documentor` code base.
 */
class CommentParser
{
    /**
     * name for the embedded data
     *
     * @var string
     */
    public static string $embeddedDataName = 'properties';
    /**
     * Regular Expression pattern for finding the embedded data and extract
     * the inner information. It is used with preg_match.
     *
     * @var string
     */
    public static string $embeddedDataPattern
        = '/```(\w*)[\s]*(([^`]*`{0,2}[^`]+)*)```/ms';
    /**
     * Pattern will have groups for the inner details of embedded data
     * this index is used to locate the data portion.
     *
     * @var int
     */
    public static int $embeddedDataIndex = 2;
    /**
     * Delimiter used to split the array data.
     *
     * When the name portion is of the embedded data is blank auto detection
     * will be used and if URLEncodedFormat is detected as the data format
     * the character specified will be used as the delimiter to find split
     * array data.
     *
     * @var string
     */
    public static string $arrayDelimiter = ',';

    /**
     * @var array annotations that support array value
     */
    public static array $allowsArrayValue = [
        'choice' => true,
        'select' => true,
        'properties' => true,
    ];

    /**
     * separator for type definitions
     */
    const TYPE_SEPARATOR = '|';

    /**
     * character sequence used to escape \@
     */
    const ESCAPE_SEQUENCE_START = '\\@';

    /**
     * character sequence used to escape end of comment
     */
    const ESCAPE_SEQUENCE_END = '{@*}';

    /**
     * Comment information is parsed and stored in to this array.
     *
     * @var array
     */
    private array $_data = [];

    /**
     * Parse the comment and extract the data.
     *
     * @static
     *
     * @param      $comment
     * @param bool $isPhpDoc
     *
     * @return array associative array with the extracted values
     * @throws Exception
     */
    public static function parse($comment, bool $isPhpDoc = true): array
    {
        $p = new self();
        if (empty($comment)) {
            return $p->_data;
        }

        if ($isPhpDoc) {
            $comment = self::removeCommentTags($comment);
        }

        $p->extractData($comment);
        return $p->_data;

    }

    /**
     * Removes the comment tags from each line of the comment.
     *
     * @static
     *
     * @param string $comment PhpDoc style comment
     *
     * @return string comments with out the tags
     */
    public static function removeCommentTags(string $comment): string
    {
        $pattern = '/(^\/\*\*)|(^\s*\**[ \/]?)|\s(?=@)|\s\*\//m';
        return preg_replace($pattern, '', $comment);
    }

    /**
     * Extracts description and long description, uses other methods to get
     * parameters.
     *
     * @param $comment
     *
     * @return array
     * @throws Exception
     */
    private function extractData(string $comment): array
    {
        //to use @ as part of comment we need to
        $comment = str_replace(
            [self::ESCAPE_SEQUENCE_END, self::ESCAPE_SEQUENCE_START],
            ['*/', '@'],
            $comment);

        $description = [];
        $longDescription = [];
        $params = [];

        $mode = 0; // extract short description;
        $comments = preg_split("/(\r?\n)/", $comment);
        // remove first blank line;
        if (empty($comments[0])) {
            array_shift($comments);
        }
        $addNewline = false;
        foreach ($comments as $line) {
            $line = trim($line);
            $newParam = false;
            if (empty ($line)) {
                if ($mode == 0) {
                    $mode++;
                } else {
                    $addNewline = true;
                }
                continue;
            } elseif ($line[0] == '@') {
                $mode = 2;
                $newParam = true;
            }
            switch ($mode) {
                case 0 :
                    $description[] = $line;
                    if (count($description) > 3) {
                        // if more than 3 lines take only first line
                        $longDescription = $description;
                        $description[] = array_shift($longDescription);
                        $mode = 1;
                    } elseif (substr($line, -1) == '.') {
                        $mode = 1;
                    }
                    break;
                case 1 :
                    if ($addNewline) {
                        $line = ' ' . $line;
                    }
                    $longDescription[] = $line;
                    break;
                case 2 :
                    $newParam
                        ? $params[] = $line
                        : $params[count($params) - 1] .= ' ' . $line;
            }
            $addNewline = false;
        }
        $description = implode(' ', $description);
        $longDescription = implode(' ', $longDescription);
        $description = preg_replace('/\s+/msu', ' ', $description);
        $longDescription = preg_replace('/\s+/msu', ' ', $longDescription);
        list($description, $d1)
            = $this->parseEmbeddedData($description);
        list($longDescription, $d2)
            = $this->parseEmbeddedData($longDescription);
        $this->_data = compact('description', 'longDescription');
        $d2 += $d1;
        if (!empty($d2)) {
            $this->_data[self::$embeddedDataName] = $d2;
        }
        foreach ($params as $key => $line) {
            list(, $param, $value) = preg_split('/@|\s/', $line, 3)
            + ['', '', ''];
            list($value, $embedded) = $this->parseEmbeddedData($value);
            $value = array_filter(preg_split('/\s+/msu', $value), 'strlen');
            $this->parseParam($param, $value, $embedded);
        }
        return $this->_data;
    }

    /**
     * Parse parameters that begin with (at)
     *
     * @param       $param
     * @param array $value
     * @param array $embedded
     */
    private function parseParam($param, array $value, array $embedded): void
    {
        $data = &$this->_data;
        $allowMultiple = false;
        switch ($param) {
            case 'param' :
            case 'property' :
            case 'property-read' :
            case 'property-write' :
                $value = $this->formatParam($value);
                $allowMultiple = true;
                break;
            case 'var' :
                $value = $this->formatVar($value);
                break;
            case 'return' :
                $value = $this->formatReturn($value);
                break;
            case 'class' :
                $data = &$data[$param];
                list ($param, $value) = $this->formatClass($value);
                break;
            case 'access' :
                $value = reset($value);
                break;
            case 'expires' :
            case 'status' :
                $value = intval(reset($value));
                break;
            case 'throws' :
                $value = $this->formatThrows($value);
                $allowMultiple = true;
                break;
            case 'author':
                $value = $this->formatAuthor($value);
                $allowMultiple = true;
                break;
            case 'header' :
            case 'link':
            case 'example':
                /** @noinspection PhpMissingBreakStatementInspection */
            case 'todo':
                $allowMultiple = true;
            //don't break, continue with code for default:
            default :
                $value = implode(' ', $value);
        }
        if (!empty($embedded)) {
            if (is_string($value)) {
                $value = ['description' => $value];
            }
            $value[self::$embeddedDataName] = $embedded;
        }
        if (empty ($data[$param])) {
            if ($allowMultiple) {
                $data[$param] = [
                    $value,
                ];
            } else {
                $data[$param] = $value;
            }
        } elseif ($allowMultiple) {
            $data[$param][] = $value;
        } elseif ($param == 'param') {
            $arr = [
                $data[$param],
                $value,
            ];
            $data[$param] = $arr;
        } else {
            if (!is_string($value) && isset($value[self::$embeddedDataName])
                && isset($data[$param][self::$embeddedDataName])
            ) {
                $value[self::$embeddedDataName]
                    += $data[$param][self::$embeddedDataName];
            }
            if (!is_array($data[$param])) {
                $data[$param] = ['description' => (string)$data[$param]];
            }
            if (is_array($value)) {
                $data[$param] = $value + $data[$param];
            }
        }
        if ('array' === $value['type'][0] && !empty($value[self::$embeddedDataName]['type'])) {
            $this->typeFix($data[$param][self::$embeddedDataName]['type']);
        }
    }

    /**
     * Parses the inline php doc comments and embedded data.
     *
     * @param string $subject
     *
     * @return array
     * @throws Exception
     */
    private function parseEmbeddedData(string $subject): array
    {
        $data = [];

        //parse {@pattern } tags specially
        while (preg_match('|(?s-m)({@pattern (/.+/[imsxuADSUXJ]*)})|', $subject, $matches)) {
            $subject = str_replace($matches[0], '', $subject);
            $data['pattern'] = $matches[2];
        }
        while (preg_match('/{@(\w+)\s?([^}]*)}/ms', $subject, $matches)) {
            $subject = str_replace($matches[0], '', $subject);
            if ($matches[1] == 'pattern') {
                throw new Exception('Inline pattern tag should follow {@pattern /REGEX_PATTERN_HERE/} format and can optionally include PCRE modifiers following the ending `/`');
            } elseif (isset(static::$allowsArrayValue[$matches[1]])) {
                $matches[2] = explode(static::$arrayDelimiter, $matches[2]);
            } elseif ($matches[2] == 'true' || $matches[2] == 'false') {
                $matches[2] = $matches[2] == 'true';
            } elseif ($matches[2] == '') {
                $matches[2] = true;
            } elseif ($matches[1] == 'required') {
                $matches[2] = explode(static::$arrayDelimiter, $matches[2]);
            } elseif ($matches[1] == 'type') {
                $matches[2] = explode(self::TYPE_SEPARATOR, $matches[2]);
            }
            $data[$matches[1]] = $matches[2];
        }

        while (preg_match(self::$embeddedDataPattern, $subject, $matches)) {
            $subject = str_replace($matches[0], '', $subject);
            $str = $matches[self::$embeddedDataIndex];
            // auto detect
            if ($str[0] == '{') {
                $d = json_decode($str, true);
                if (json_last_error() != JSON_ERROR_NONE) {
                    throw new Exception('Error parsing embedded JSON data'
                        . " $str");
                }
                $data = $d + $data;
            } else {
                parse_str($str, $d);
                //clean up
                $d = array_filter($d);
                foreach ($d as $key => $val) {
                    $kt = trim($key);
                    if ($kt != $key) {
                        unset($d[$key]);
                        $key = $kt;
                        $d[$key] = $val;
                    }
                    if (is_string($val)) {
                        if ($val == 'true' || $val == 'false') {
                            $d[$key] = $val == 'true' ? true : false;
                        } else {
                            $val = explode(self::$arrayDelimiter, $val);
                            if (count($val) > 1) {
                                $d[$key] = $val;
                            } else {
                                $d[$key] =
                                    preg_replace('/\s+/msu', ' ',
                                        $d[$key]);
                            }
                        }
                    }
                }
                $data = $d + $data;
            }

        }
        return [$subject, $data];
    }

    private function formatThrows(array $value): array
    {
        $code = 500;
        $exception = 'Exception';
        if (count($value) > 1) {
            $v1 = $value[0];
            $v2 = $value[1];
            if (is_numeric($v1)) {
                $code = $v1;
                $exception = $v2;
                array_shift($value);
                array_shift($value);
            } elseif (is_numeric($v2)) {
                $code = $v2;
                $exception = $v1;
                array_shift($value);
                array_shift($value);
            } else {
                $exception = $v1;
                array_shift($value);
            }
        } elseif (count($value) && is_numeric($value[0])) {
            $code = $value[0];
            array_shift($value);
        }
        $message = implode(' ', $value);
        if (!isset(HttpException::$codes[$code])) {
            $code = 500;
        } elseif (empty($message)) {
            $message = HttpException::$codes[$code];
        }
        return compact('code', 'message', 'exception');
    }

    private function formatClass(array $value): array
    {
        $param = array_shift($value);

        if (empty($param)) {
            $param = 'Unknown';
        }
        $value = implode(' ', $value);
        return [
            ltrim($param, '\\'),
            ['description' => $value],
        ];
    }

    private function formatAuthor(array $value): array
    {
        $r = [];
        $email = end($value);
        if ($email[0] == '<') {
            $email = substr($email, 1, -1);
            array_pop($value);
            $r['email'] = $email;
        }
        $r['name'] = implode(' ', $value);
        return $r;
    }

    private function formatReturn(array $value): array
    {
        $data = explode(self::TYPE_SEPARATOR, array_shift($value));
        $r = [
            'type' => $data,
        ];
        $r['description'] = implode(' ', $value);
        return $r;
    }

    private function formatParam(array $value): array
    {
        $r = [];
        $data = array_shift($value);
        if (empty($data)) {
            $r['type'] = ['mixed'];
        } elseif ($data[0] == '$') {
            $r['name'] = substr($data, 1);
            $r['type'] = ['mixed'];
        } else {
            $data = explode(self::TYPE_SEPARATOR, $data);
            $r['type'] = $data;

            $data = array_shift($value);
            if (!empty($data) && $data[0] == '$') {
                $r['name'] = substr($data, 1);
            }
        }
        $this->typeAndDescription($r, []);
        return $r;
    }

    private function formatVar(array $value): array
    {
        $r = [];
        $data = array_shift($value);
        if (empty($data)) {
            $r['type'] = ['mixed'];
        } elseif ($data[0] == '$') {
            $r['name'] = substr($data, 1);
            $r['type'] = ['mixed'];
        } else {
            $data = explode(self::TYPE_SEPARATOR, $data);
            $r['type'] = $data;
        }
        $this->typeAndDescription($r, []);
        return $r;
    }

    private function typeFix(array &$type, string $default = 'string')
    {
        $length = count($type);
        if ($length) {
            if ('null' === $type[0]) {
                if (1 == $length) {
                    array_unshift($type, $default);
                } else {
                    array_shift($type);
                    array_push($type, 'null');
                }
            }
        }
    }

    private function typeAndDescription(&$r, array $value, string $default = 'array'): void
    {
        if (count($r['type'])) {
            if (Text::endsWith($r['type'][0], '[]')) {
                $r[static::$embeddedDataName]['type'] = [substr($r['type'][0], 0, -2)];
                $r['type'] = ['array', ...$r['type']];
            } else {
                $this->typeFix($r['type'], $default);
            }
        }
        if ($value) {
            $r['description'] = implode(' ', $value);
        }
    }
}
