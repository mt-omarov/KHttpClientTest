# KHttpClient
KHttpClient представляет собой компонент низкоуровнего HTTP клиента, использующий cURL.
## Требования
Библиотека требует PHP 7.4 и выше и последнюю версию [KPHP](https://github.com/VKCOM/kphp).
## Структура библиотеки
Ниже рассмотрены ключевые модули (классы) логики работы компонента:
- `CurlHttpClient` – модуль cURL реализации клиента,
- `CurlResponse` – класс единицы запроса,
- `CurlClientState` – класс состояний клиента,
- `ExtractedHttpClient` – портированный трейт функционала валидации запроса
## Синтаксис работы с KPHP 
При портировании библиотеки требуется учесть следующее:
- KPHP не поддерживает рефлексию => не доступны циклы по полям класса и другие распространённые PHP приёмы,
- KPHP не позволяет добавлять alias'ы в трейт-файлы => нужно преобразовывать трейты в классы или т.п.,
- многие типы, используемые в PHP, имеют другой тип в рантайме KPHP => для поддержки запуска библиотеки на KPHP и на PHP нужно обходить прямую типизацию и использовать PHPDoc,
- многие функции в PHP не доступны в KPHP, но имеют замену, не поддерживающуюся в PHP => разграничивать запуск библиотеки на KPHP от PHP с помощью конструкций `#ifndef KPHP` - `#endif` сокрытий кода от KPHP,
- KPHP не допускает хранение сложных данных вместе => требуется преобразовывать сложные структуры в соответствующие классы с полями строгой типизации для хранения данных различной сложности в одном объекте.
## Хранение параметров запросов
Для корректной работы библиотеки требуется хранить параметры запросов различной сложности. С данной целью был создан класс `Options`, хранящий следующие параметры:
- `string $authBasic` – строка с именем пользователя и паролем (ранее мог быть как массивом, так и строкой),
- `string $authBearer` – токен разрешения HTTP Bearer авторизации,
- `string[] $query` – ассоциативный массив значений строки запроса для объединения с URL-адресом запроса,
- `string[] $headers` – массив заголовков запроса (ранее мог быть массивом любой сложности),
- `array<string, array<string>> $normalizedHeaders` – нормализованный массив заголовков запроса,
- `string $proxy` – переменная для учёта обрабатываемых cURL переменных окружения сервера,
- `string $noProxy`
- `float $timeout` – таймаут простоя (в секундах),
- `string $bindTo`,
- `mixed $localCert`
- `mixed $localPk`
- `mixed $userData` – прикрепленные к запросу данные,
- `int $maxRedirects` – кол-во максимальных перенаправлений,
- `string $httpVersion` – версия используемого HTTP,
- `string $baseUri` – URI для разрешения относительных URL-адресов в соответствии с правилами RFC 3986, раздел 2,
- `mixed $buffer` – переменная буффера, пока не используется в портируемой библиотеке,
- `string[] $resolve` – массив сопоставлений хостов с IP-адресами,
- `mixed $body` – переменная хранения тела запроса,
- `string $json` – преобразованное тело запроса в json.
### Проверка установки параметра
В оригинальной библиотеке проверка установки осущеставлялась через базовую `isset()`, при этом в каждый из параметров по умолчанию можно было записывать `null`. После строгой типизации последнее невозможно и, вместе с этим, использование `isset()` на полях объекта класса приводит к конфликтам => требуется другой способ проверки установки параметра. \
В портированной библиотеке по умолчанию строковые параметры хранят пустые строки `''`, числоые данные – `-1`, смешанные типы – `null`, массивы – `[]`. \
Для удобства была реализована функция `Options::isset()`:
```PHP
public static function isset($option): bool
    {
        if (is_array($option)){
            return ($option !== []);
        }
        elseif (is_string($option)){
            return $option !== '';
        }
        elseif (is_int($option)){
            return $option !== -1;
        }
        else return ($option !== null);
    }
```
Пример использования:
```PHP
if (Options::isset($options->getJson())) {...}
```
### Работа с параметрами-массивами
Изменение / получение массива- / элемента массива какого-либо параметра запроса требует функцию с возможностью принятия ключа и проверки его существования (при необходимости).
Ниже приведён пример реализации функций геттеров двумерного массива:
```PHP
/**
     * @return ?array<string>
     */
    public function getNormalizedHeader(string $key): ?array
    {
        if (array_key_exists($key, $this->normalizedHeaders)) {
            return $this->normalizedHeaders[$key];
        }
        else return self::NULL;
    }

    public function getElementNormalizedHeader(string $headerKey, int $key): ?string
    {
        if ($header = $this->getNormalizedHeader($headerKey)){
            return array_key_exists($key, $header) ? $header[$key] : self::NULL;
        }
        else return self::NULL;
    }
```
> KPHP не позволяет возвращать null напрямую, для чего была выделена соответствующая константа.

Данный функционал позволяет упростить синтаксис проверки существования параметра по ключу и избежать ошибок обращения к несуществующему объекту.
Пример использования:
```PHP
$normalizedHeaders = $options->getNormalizedHeader($k) ?? [];
```
### Операция объединения параметров запроса
В оригинальной библиотеке параметры массива представлены массивами, потому операция объединения осущеставлялась арифметическим сложением `+`. Для возможности объединения двух объектов класса `Options` была реализована соответствуюшая функция `mergeOptions(self $lOptions, self $rOptions): self`. 
Имплементация основана на использовании switch-case конструкции.
> Как уже было сказано ранее KPHP не поддерживает рефлексию и потому подобные операции выполняются в лоб.

Пример использования:
```PHP
$options = Options::mergeOptions($options, $defaultOptions);
```
## Валидация параметров запроса
Функционал валидации параметров запроса содержится в классе `ExtractedHttpClient`.
> В оригинальной библиотеке представлен как трейт `HttpClientTrait`, использующий alias'ы.

Структура валидации запроса представлена ниже:
1. **вызов `prepareRequest()`**: \
*основной метод валидации, принимает массив опций* 
    1. **вызов `mergeDefaultOptions()`**: \
*валидирует и склеивает переданный массив опций с массивом опций по умол.*
        - **вызов `normalizeHeaders()`**
        - **вызов `parseUrl()`**: \
*разбивает и валидирует URL-адрес*
    2. **вызов `jsonEncode()` при необходимости**: \
*Декодирует тело запроса*
    3. **вызов `normalizePeerFingerprint()`**: \
*В соответствии с параметром запроса определяет алгоритм защиты*
    4. **вызов `resolveUrl()`**: \
*Вадириует и декодирует url-части запроса*

С целью возврата из функции валидации полученного url и объекта `Options` используется KPHP тип данных tuple, запоминаюший типы данных в своей структуре (отличается тем, что не позволяет изменять зранимые данные). \
В PHP отсутствует tuple, потому используется `#ifndef KPHP` - `#endif` конструкция для возврата списка при работе с PHP. 

Конструкции проверки параметра запроса тела вырезаны через `#ifndef KPHP` - `#endif` ввиду строгой типизации параметра и очевидности результата проверки. \
Ананлогичным образом вырезан функционал `dechunk()`, затруднительный к переносу из-за отсутствия поддержки в KPHP работы с временными потоками.

Пример использования функционала валидации параметров запроса:
```PHP
[$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions);
```
### Подключение недостающих констант
Подключение констант в обход их дублирования на PHP осуществляется через разделение KPHP и PHP с помощью инициализации константы `IS_PHP`.
Пример разделения исполнений:
```PHP
CurlHttpClient.php
...
// in order to understand whether KPHP or PHP is being used
#ifndef KPHP
define('IS_PHP', true);
#endif
...

ExtractedHttpClient.php
...
#ifndef KPHP
if (! defined('IS_PHP')) {
#endif
    require_once __DIR__.'/PredefinedConstants.php';
#ifndef KPHP
}
#endif
...
```
> Конструкция комментаривания от KPHP условния требуется для предотвращения ошибок 
## Создание запроса 
Единица запроса реализована через `CurlResponse`.
Класс содержит следущие поля:
- `CurlClientState $multi` – поле связи между запросом, клиентом и его хендлерами,
- `int $id` – идентификатор запроса,
- `string[][] $headers` – заголовки запроса, 
- `mixed $info` – информация по состоянию запроса,
- `CurlHandle $handle` – хендлер запроса (имлпементация resource-переменной),
- `float $timeout`,
- `int $offset`, 
- `callable(self): bool $initializer` – функция инициализации (определяется в конструкторе)
- `?int $content` – ресурс (в KPHP определяется целочисленным значением, в PHP – respurce типом, потому для PHP тип не указывается, используя PHPDoc), 
- `mixed $finalInfo` – результирующая информация по состоянию запроса

Пример создания запроса:
```PHP
return new CurlResponse($this->multi, $ch, null, $options, $method, $redirectResolverFunc);
```

### Имплементация resource-типов
С целью инкапсуляции кода была создана обёртка над resource-данными PHP `CurlHandle` и `CurlMultiHandle`.
Имплементриванный класс `CurlHandle` содержит один параметр хендлера, тип которого определяется в PHPDoc для обхода конфтиктов между PHP и KPHP:

```PHP
/** @var int $handle because in KPHP resources are just integers*/
protected $handle; // do not specify the type to avoid conflicts between KPHP and PHP
```

Ананлогичным образом выстроен конструктор, способный принимать готовый resource-объект:
```PHP
/**
* @param string|null $url
* @param int|null $handle again: do not specify the type
*/
public function __construct(?string $url = null, $handle = null)
{
    // creates a curl session with url or null or stores the transferred one
    $handle ? $this->handle = $handle : $this->handle = curl_init($url);
}
```

Для работы компонента CurlHttpClient на KPHP на данный момент были инкапсулированы следующие методы (в представлении KPHP):
`CurlHandle`:
- `curl_setopt(int, mixed): bool`,
- `curl_setopt_array(array): bool`,
- `curl_close(int)`,
- `curl_getinfo(int, int): mixed`

`CurlMultiHandle`:
- `curl_multi_setopt(int, int, int): bool`,
- `curl_multi_remove_handle(int, int): int|false`,
- `curl_multi_add_handle(int, int): int|false`,
- `curl_multi_select(int, float): int|false`,
- `curl_multi_close(int)`,
- `curl_multi_exec(int, &int): int|false`,
- `curl_multi_info_read(int, &int): int[], false`,
- `curl_multi_strerror(int): string|null`

Пример инкапсуляции новой функции:
```PHP
/**
* @param int $handle
* @return false|int
*/
public function curlMultiRemoveHandle($handle)
{
    return curl_multi_remove_handle($this->handle, $handle);
}
```

### Проблема использования обработчиков событий
Вырезан весь функционал с использованием автоматических обработчиков событий, реагирующих на ответы сервера в соответствии с переданными функциями.
> KPHP не поддерживает cURL функционал с обработчиками событий.

### Имплементация основного метода генератора
KPHP не поддерживает генераторные функции, потому функционал библиотеки `CurlResponse::stream(array<CurlResponse>, int)` подвергся переносу в лоб.
Для полноценного переноса генераторной функции был сформирован класс `StreamIterator`, хранящий промежуточные состояния после итерации по функции.

Пример использования и изменение синтаксиса:
```PHP
// было
foreach (self::stream([$this]) as [$chunk]) {
    doSmth($chunk);
}

// стало
$iterator = StreamIterator([$this]);
while ($iterator->hasResponses()) {
    $chunk = $iterator->stream();
    doSmth($chunk);
}
```
## Краткое описание проекта находится по [ссылке](https://git.miem.hse.ru/kaa/kaa/-/blob/CurlHttpClient/src/HttpClient/README.md)
