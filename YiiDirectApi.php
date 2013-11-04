<?php
/**
 * Компонент для работы с API Yandex.Direct
 * @author Alexey Salnikov <me@iamsalnikov.ru>
 */

class YiiDirectApi extends CApplicationComponent
{

    /**
     * Id приложения
     * @var string $id
     */
    public $id;

    /**
     * Пароль приложения
     * @var string
     */
    public $password;

    /**
     * Тип ответа от сервера Яндекса
     * @var string
     */
    public $responseType = 'code';

    /**
     * На каком языке получать ответы из яндекса
     * @var string
     */
    public $locale = 'ru';

    /**
     * Ссылка для авторизации на директе
     * @var string
     */
    private $_authorizeLink;

    /**
     * Код при авторизации
     * @var string
     */
    private $_code;

    /**
     * Токен от директа
     * @var string
     */
    private $_token;

    /**
     * Здесь хранится код ошибки, если она произошла
     * @var string
     */
    private $_error;

    /**
     * Здесь строка ошибки при вызове методов API
     * @var string
     */
    private $_errorStr;

    /**
     * Здесь описание ошибки при вызове методов API
     * @var string
     */
    private $_errorDetail;

    /**
     * Логин пользователя, с данными которого мы работаем
     * @var string
     */
    private $_login;

    const AUTHORIZE_URL = 'https://oauth.yandex.ru/authorize';
    const TOKEN_URL = 'https://oauth.yandex.ru/token';
    const JSON_API_URL = 'https://api.direct.yandex.ru/v4/json/';

    # Названия методов
    const M_GET_CAMPAIGNS_LIST = 'GetCampaignsList';
    const M_GET_BANNERS = 'GetBanners';
    const M_CREATE_NEW_REPORT = 'CreateNewReport';
    const M_DELETE_REPORT = 'DeleteReport';
    const M_GET_REPORT_LIST = 'GetReportList';
    const M_GET_CAMPAIGN_PARAMS = 'GetCampaignParams';
    const M_CREATE_OR_UPDATE_BANNERS = 'CreateOrUpdateBanners';
    const M_MODERATE_BANNERS = 'ModerateBanners';
    const M_PING_API = 'PingAPI';
	const M_CREATE_OR_UPDATE_CAMPAIGN = 'CreateOrUpdateCampaign';

    public function init()
    {
        # Установим строку для авторизации
        $this->_authorizeLink = self::AUTHORIZE_URL . '?' . http_build_query(array(
                'response_type' => $this->responseType,
                'client_id' => $this->id,
            ));

        # Если язык не установлен, тогда возьмем его из установок приложения
        if (!$this->locale) {
            $this->locale = Yii::app()->language;
        }
    }


    /**
     * Получение ссылки для авторизации
     * @param string $state - произвольный параметр состояния
     * @return string
     */
    public function getAuthorizeUrl($state = '')
    {
        return $state ? $this->_authorizeLink . '&state=' . $state : $this->_authorizeLink;
    }

    /**
     * Получаем токен из директа
     * @param $code - код для авторизации
     * @return string|null В случае успешного получения токена возвращается токен, иначе null.
     * Значение ошибки можно получить из функции getError()
     */
    public function getDirectToken($code)
    {
        $this->clearErrors();
        $this->_code = $code;

        $result = Yii::app()->curl->post(self::TOKEN_URL, array(
            'grant_type' => 'authorization_code',
            'code' => $this->_code,
            'client_id' => $this->id,
            'client_secret' => $this->password
        ));

        $result = CJSON::decode($result);

        # Если все прошло без ошибки
        if (empty($result['error'])) {
            $this->_token = $result['access_token'];
        } else {
            $this->_error = $result['error'];
        }

        return $this->_token;
    }

    /**
     * Установка токена
     * @param string $token
     * @return YiiDirectApi
     */
    public function setToken($token)
    {
        $this->_token = $token;
        return $this;
    }

    /**
     * Получение ошибки
     * return null|string
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Установка ошибки
     * @param $error
     * @return $this
     */
    public function setError($error)
    {
        $this->_error = $error;
        return $this;
    }

    /**
     * Получаем логин пользователя, с которым мы работаем
     * @return string
     */
    public function getLogin()
    {
        return $this->_login;
    }

    /**
     * Установка логина пользователя, с которым будем работать
     * @param $login
     * @return YiiDirectApi
     */
    public function setLogin($login)
    {
        $this->_login = $login;
        return $this;
    }

    /**
     * Установка информации об ошибке
     * @param string $errorDetail
     * @return $this
     */
    public function setErrorDetail($errorDetail)
    {
        $this->_errorDetail = $errorDetail;
        return $this;
    }

    /**
     * Получение информации об ошибке
     * @return string
     */
    public function getErrorDetail()
    {
        return $this->_errorDetail;
    }

    /**
     * Установка заголовка ошибки
     * @param string $errorStr
     * @return $this
     */
    public function setErrorStr($errorStr)
    {
        $this->_errorStr = $errorStr;
        return $this;
    }

    /**
     * Получение заголовка ошибки
     * @return string
     */
    public function getErrorStr()
    {
        return $this->_errorStr;
    }

    /**
     * Очистка информации об ошибках
     * @return $this
     */
    public function clearErrors()
    {
        $this->_error = null;
        $this->_errorStr = null;
        $this->_errorDetail = null;
        return $this;
    }

    /**
     * Запрос к API
     * @param string $method
     * @param array $params
     * @return bool|array
     */
    public function apiQuery($method, $params = array())
    {
        $this->clearErrors();
        $params = array(
            'method' => $method,
            'param' => $params,
            'locale' => $this->locale,
            'login' => $this->_login,
            'application_id' => $this->id,
            'token' => $this->_token
        );

        $params = $this->utf8($params);
        $params = CJSON::encode($params);

        $result = Yii::app()->curl->setOption(CURLOPT_TIMEOUT, 0)->post(self::JSON_API_URL, $params);
        $result = CJSON::decode($result);

        # Если все прошло без ошибок
        if (!empty($result)) {
            if (isset($result['error_code']) && isset($result['error_str'])) {
                $this->setError($result['error_code'])->setErrorStr($result['error_str']);
                $result = false;
            }
            if (!empty($result['error_detail']))
                $this->setErrorDetail($result['error_detail']);
        }

        return $result;
    }

    /**
     * Перекодировка
     * @param $struct
     * @return mixed
     */
    public function utf8($struct) {
        foreach ($struct as $key => $value) {
            if (is_array($value)) {
                $struct[$key] = $this->utf8($value);
            }
            elseif (is_string($value)) {
                $struct[$key] = utf8_encode($value);
            }
        }
        return $struct;
    }
}