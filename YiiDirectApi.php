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

	/**
	 * Curl
	 * @var Curl
	 */
	private $_ch;
	private $_curlOptions = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_AUTOREFERER    => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:5.0) Gecko/20110619 Firefox/5.0',
		CURLOPT_TIMEOUT => 0,
		CURLOPT_POST => true,
	);

	const AUTHORIZE_URL = 'https://oauth.yandex.ru/authorize';
	const TOKEN_URL = 'https://oauth.yandex.ru/token';
	const JSON_API_URL = 'https://api.direct.yandex.ru/v4/json/';

	# Названия методов
	# Методы для работы с кампаниями
	const M_ARCHIVE_CAMPAIGN = 'ArchiveCampaign';
	const M_CREATE_OR_UPDATE_CAMPAIGN = 'CreateOrUpdateCampaign';
	const M_DELETE_CAMPAIGN = 'DeleteCampaign';
	const M_GET_CAMPAIGN_PARAMS = 'GetCampaignParams';
	const M_GET_CAMPAIGNS_LIST = 'GetCampaignsList';
	const M_GET_CAMPAIGNS_LIST_FILTER = 'GetCampaignsListFilter';
	const M_GET_CAMPAIGNS_PARAMS = 'GetCampaignsParams';
	const M_RESUME_CAMPAIGN = 'ResumeCampaign';
	const M_STOP_CAMPAIGN = 'StopCampaign';
	const M_UN_ARCHIVE_CAMPAIGN = 'UnArchiveCampaign';
	
	# Методы для работы с объявлениями
	const M_ARCHIVE_BANNERS = 'ArchiveBanners';
	const M_CREATE_OR_UPDATE_BANNERS = 'CreateOrUpdateBanners';
	const M_DELETE_BANNERS = 'DeleteBanners';
	const M_GET_BANNERS = 'GetBanners';
	const M_GET_BANNER_PHRASES = 'GetBannerPhrases';
	const M_GET_BANNER_PHRASES_FILTER = 'GetBannerPhrasesFilter';
	const M_MODERATE_BANNERS = 'ModerateBanners';
	const M_RESUME_BANNERS = 'ResumeBanners';
	const M_STOP_BANNERS = 'StopBanners';
	const M_UN_ARCHIVE_BANNERS = 'UnArchiveBanners';

	# Методы для работы со ставками
	const M_SET_AUTO_PRICE = 'SetAutoPrice';
	const M_UPDATE_PRICES = 'UpdatePrices';

	# Методы для работы с отчетами
	const M_GET_BALANCE = 'GetBalance';
	const M_GET_SUMMARY_STAT = 'GetSummaryStat';
	const M_CREATE_NEW_REPORT = 'CreateNewReport';
	const M_DELETE_REPORT = 'DeleteReport';
	const M_GET_REPORT_LIST = 'GetReportList';
	const M_CREATE_NEW_WORDSTAT_REPORT = 'CreateNewWordstatReport';
	const M_DELETE_WORDSTAT_REPORT = 'DeleteWordstatReport';
	const M_GET_WORDSTAT_REPORT = 'GetWordstatReport';
	const M_GET_WORDSTAT_REPORT_LIST = 'GetWordstatReportList';
	const M_CREATE_NEW_FORECAST = 'CreateNewForecast';
	const M_DELETE_FORECAST_REPORT = 'DeleteForecastReport';
	const M_GET_FORECAST = 'GetForecast';
	const M_GET_FORECAST_LIST = 'GetForecastList';
	
	# Методы работы с клиентами
	const M_CREATE_NEW_SUBCLIENT = 'CreateNewSubclient';
	const M_GET_CLIENT_INFO = 'GetClientInfo';
	const M_GET_CLIENTS_LIST = 'GetClientsList';
	const M_GET_CLIENTS_UNITS = 'GetClientsUnits';
	const M_GET_SUB_CLIENTS = 'GetSubClients';
	const M_UPDATE_CLIENT_INFO = 'UpdateClientInfo';

	# Прочие методы
	const M_GET_AVAILABLE_VERSIONS = 'GetAvailableVersions';
	const M_GET_CHANGES = 'GetChanges';
	const M_GET_REGIONS = 'GetRegions';
	const M_GET_RUBRICS = 'GetRubrics';
	const M_GET_STAT_GOALS = 'GetStatGoals';
	const M_GET_TIME_ZONES = 'GetTimeZones';
	const M_GET_VERSION = 'GetVersion';
	const M_PING_API = 'PingAPI';

	public function init()
	{
		# Инициализируем CURL
		$this->_ch = curl_init();
		curl_setopt_array($this->_ch, $this->_curlOptions);
		curl_setopt($this->_ch, CURLOPT_URL, self::JSON_API_URL});

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
	 * Выполняем запрос
	 * @return array
	 */
	private function _execCurl($data) 
	{
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $data);
		$c = curl_exec($this->_ch);
		if (curl_errno($this->_ch)) {
			throw new CException(curl_error($this->_ch));
			$c = false;
		}

		return $c;
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

		$result = $this->_execCurl($params);
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