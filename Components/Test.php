<?php

namespace Kaa\HttpClient\Components;
use Kaa\HttpClient\Contracts\HttpClientInterface;
use Kaa\HttpClient\Components\ExtractedHttpClient;

class Test extends ExtractedHttpClient implements HttpClientInterface
{
    public static function testing()
    {
        $url = "https://www.notion.so/35dedf7c4e4b4552becf52671ad53d85";
        //var_dump(self::prepareRequest("GET", $url, self::$defaultOptions, self::$defaultOptions));
//        var_dump(self::mergeQueryString('', [], true));
//        var_dump(self::parseUrl($url));
//        var_dump(self::normalizeHeaders(['smth']));
        (self::mergeDefaultOptions(new Options(), new Options()))->printOptions();
        [, $tOptions] = self::prepareRequest(null ,null, new Options(), new Options());
        $tOptions->printOptions();
    }

    public  static function getDefinedKPHP($filename = __DIR__.'/../../_functions.txt') {
        $constants = array();
        $lines = file($filename);
        foreach ($lines as $line) {
            if (preg_match('/^define\((\'|\")(\w+)(\'|\")/', $line, $matches)) {
                $constants[] = $matches[2];
            }
        }
        return $constants;
    }

    public static function phpPredefinedToFile($filename = __DIR__."/PredefinedConstants.php")
    {
        $constants = get_defined_constants(true);
//        $all = get_defined_constants(false);
//        $keys = array_keys($constants);
//        foreach ($keys as $key){
//            foreach ($constants[$key] as $name => $value){
//                if ($name == "ZEND_THREAD_SAFE"){
//                    echo $key;
//                }
//            }
//        }

        $kConstants = self::getDefinedKPHP();
        $content = "<?php\n";
        foreach (array_merge($constants['json'], $constants['curl'], $constants['standard'], $constants['Core']) as $name => $value) {
            if (in_array($name, $kConstants)) continue;
            if (is_string($value)){
                $content .= "define('$name', '$value');\n";
            }
            elseif (is_bool($value)){
                $content .= sprintf("define('$name', %b);\n", $value);
            }
            else{
                $content .= "define('$name', {$value});\n";
            }

        }
        file_put_contents($filename, $content);
    }
}