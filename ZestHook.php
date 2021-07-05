<?php

/**
 * Send a curl post request after each afterSurveyComplete event
 *
 * @author Stefan Verweij <stefan@evently.nl>
 * @copyright 2016 Evently <https://www.evently.nl>
 * @license GPL v3
 * @version 1.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class ZestHook extends PluginBase
{

  protected $storage = 'DbStorage';
  static protected $description = 'Webhook for Limesurvey: send a curl POST after every response submission.';
  static protected $name = 'ZestHook';
  protected $surveyId;

  public function init()
  {
    $this->subscribe('afterSurveyComplete');
    $this->subscribe('beforeSurveySettings');
    $this->subscribe('newSurveySettings');
  }


  protected $settings = array(
    'bUse' => array(
      'type' => 'select',
      'options' => array(
        0 => 'No',
        1 => 'Yes'
      ),
      'default' => 1,
      'label' => 'Send a hook for every survey by default?',
      'help' => 'Overwritable in each Survey setting'
    ),
    'sUrl' => array(
      'type' => 'string',
      'default' => 'https://zest.evently.nl/api/1/ping',
      'label' => 'The default address to send the webhook to',
      'help' => 'If you are using Zest, this should be https://zest.evently.nl/api/1/ping'
    ),
    'sAuthToken' => array(
      'type' => 'string',
      'label' => 'Zest Platform API Token',
      'help' => 'To get a token logon to your account and click on the Tokens tab'
    ),
    'sServerToken' => array(
      'type' => 'string',
      'label' => 'Zest server id token',
      'help' => 'To get a token logon to your account, go to your servers and copy the server id'
    )
  );

  /**
   * Add setting on survey level: send hook only for certain surveys / url setting per survey / auth code per survey / send user token / send question response
   */
  public function beforeSurveySettings()
  {
    $oEvent = $this->event;
    $oEvent->set("surveysettings.{$this->id}", array(
      'name' => get_class($this),
      'settings' => array(
        'bUse' => array(
          'type' => 'select',
          'label' => 'Send a hook for this survey',
          'options' => array(
            0 => 'No',
            1 => 'Yes',
            2 => 'Use site settings (default)'
          ),
          'default' => 2,
          'help' => 'Leave default to use global setting',
          'current' => $this->get('bUse', 'Survey', $oEvent->get('survey'))
        ),
        'bUrlOverwrite' => array(
          'type' => 'select',
          'label' => 'Overwrite the global Hook Url',
          'options' => array(
            0 => 'No',
            1 => 'Yes'
          ),
          'default' => 0,
          'help' => 'Set to Yes if you want to use a specific URL for this survey',
          'current' => $this->get('bUrlOverwrite', 'Survey', $oEvent->get('survey'))
        ),
        'sUrl' => array(
          'type' => 'string',
          'label' => 'The  address to send the hook for this survey to:',
          'help' => 'Leave blank to use global setting',
          'current' => $this->get('sUrl', 'Survey', $oEvent->get('survey'))
        ),
        'bAuthTokenOverwrite' => array(
          'type' => 'select',
          'label' => 'Overwrite the global authorization token',
          'options' => array(
            0 => 'No',
            1 => 'Yes'
          ),
          'default' => 0,
          'help' => 'Set to Yes if you want to use a specific zest API token for this survey',
          'current' => $this->get('bAuthTokenOverwrite', 'Survey', $oEvent->get('survey'))
        ),
        'sAuthToken' => array(
          'type' => 'string',
          'label' => 'Use a specific API Token for this survey (leave blank to use default)',
          'help' => 'Leave blank to use default',
          'current' => $this->get('sAuthToken', 'Survey', $oEvent->get('survey'))
        ),
        'bServerTokenOverwrite' => array(
          'type' => 'select',
          'label' => 'Overwrite the global server token',
          'options' => array(
            0 => 'No',
            1 => 'Yes'
          ),
          'default' => 0,
          'help' => 'Set to Yes if you want to use a specific Zest server-token for this survey',
          'current' => $this->get('bServerTokenOverwrite', 'Survey', $oEvent->get('survey'))
        ),
        'sServerToken' => array(
          'type' => 'string',
          'label' => 'Zest server-token',
          'help' => 'To get a token, log in to your account, go to your servers and copy the server token',
          'current' => $this->get('sServerToken', 'Survey', $oEvent->get('survey'))
        ),
        'bSendToken' => array(
          'type' => 'select',
          'label' => 'Send the users\' token to the hook',
          'options' => array(
            0 => 'No',
            1 => 'Yes'
          ),
          'default' => 0,
          'help' => 'Set to Yes if you want to pass the users token along in the request',
          'current' => $this->get('bSendToken', 'Survey', $oEvent->get('survey'))
        ),
        'sAnswersToSend' => array(
          'type' => 'string',
          'label' => 'Answers to send',
          'help' => 'Comma separated question codes of the answers you want to send along',
          'current' => $this->get('sAnswersToSend', 'Survey', $oEvent->get('survey'))
        ),
        'bSendAllAnswers' => array(
          'type' => 'select',
          'label' => 'Send all answers',
          'default' => 0,
          'options' => array(
            0 => 'No',
            1 => 'Yes'
          ),
          'current' => $this->get('bSendAllAnswers', 'Survey', $oEvent->get('survey'))
        ),
        'bRequestType' => array(
          'type' => 'select',
          'label' => 'Request Type',
          'default' => 0,
          'options' => array(
            0 => 'POST',
            1 => 'GET'
          ),
          'current' => $this->get('bRequestType', 'Survey', $oEvent->get('survey'))
        ),
        'sPostSignature' => array(
          'type' => 'string',
          'default' => '{"survey":"{surveyId}","token":"{token}","api_token":"{apiToken}","server_key":"{serverKey}","additionalFields":"{additionalFields}"}',
          'label' => 'JSON Signature',
          'help' => 'JSON signature with placeholders {surveyId},{token},{apiToken},{surverKey} and {additionalFields}, use {{fieldcode}} for additional specific fields values',
          'current' => $this->get('sPostSignature', 'Survey', $oEvent->get('survey'))
        ),
        'bDebugMode' => array(
          'type' => 'select',
          'options' => array(
            0 => 'No',
            1 => 'Yes'
          ),
          'default' => 0,
          'label' => 'Enable Debug Mode',
          'help' => 'Enable debugmode to see what data is transmitted. Respondents will see this as well so you should turn this off for live surveys',
          'current' => $this->get('bDebugMode', 'Survey', $oEvent->get('survey')),
        )
      )
    ));
  }

  /**
   * Save the settings
   */
  public function newSurveySettings()
  {
    $event = $this->event;
    foreach ($event->get('settings') as $name => $value) {
      /* In order use survey setting, if not set, use global, if not set use default */
      $default = $event->get($name, null, null, isset($this->settings[$name]['default']) ? $this->settings[$name]['default'] : NULL);
      $this->set($name, $value, 'Survey', $event->get('survey'), $default);
    }
  }


  /**
   * Send the webhook on completion of a survey
   * @return array | response
   */
  public function afterSurveyComplete()
  {
    $time_start = microtime(true);
    $oEvent     = $this->getEvent();
    $this->surveyId = $oEvent->get('surveyId');
    if ($this->isHookDisabled()) {
      return;
    }

    $url = ($this->get('bUrlOverwrite', 'Survey', $this->surveyId) === '1') ? $this->get('sUrl', 'Survey', $this->surveyId) : $this->get('sUrl', null, null, $this->settings['sUrl']);
    $auth = ($this->get('bAuthTokenOverwrite', 'Survey', $this->surveyId) === '1') ? $this->get('sAuthToken', 'Survey', $this->surveyId) : $this->get('sAuthToken', null, null, $this->settings['sAuthToken']);
    $serverToken = ($this->get('bServerTokenOverwrite', 'Survey', $this->surveyId) === '1') ? $this->get('sServerToken', 'Survey', $this->surveyId) : $this->get('sServerToken', null, null, $this->settings['sServerToken']);
    $postSignature = $this->get('sPostSignature', 'Survey', $this->surveyId);
    $requestType = $this->get('bRequestType', 'Survey', $this->surveyId);
    $additionalFields = $this->getAdditionalFields();

    if (($this->get('bSendToken', 'Survey', $this->surveyId) === '1') || (count($additionalFields) > 0)) {
      $responseId = $oEvent->get('responseId');
      $response = $this->api->getResponse($this->surveyId, $responseId);
      $sToken = $this->getTokenString($response);
      $additionalAnswers = $this->getAdditionalAnswers($additionalFields, $response);
    }
    if ($postSignature) {
      $mainFields = array("/{surveyId}/", "/{token}/", "/{apiToken}/", "/{serverKey}/", "/{additionalFields}/");
      $mainValues = array($this->surveyId, (isset($sToken)) ? $sToken : null, $auth, $serverToken, ($additionalFields) ? json_encode($additionalAnswers) : null);
      $parameters = preg_replace($mainFields, $mainValues, $postSignature);
      if ($additionalFields) {
        foreach ($additionalAnswers as $key => $val)
          $parameters = preg_replace("/{{" . $key . "}}/", $val, $parameters);
      }

      $parameters = json_decode($parameters, true);
    } else {
      if ($bSendAllAnswers) {
        $parameters = array(
        "survey" => $this->surveyId,
        "token" => (isset($sToken)) ? $sToken : null,
        "api_token" => $auth,
        "server_key" => $serverToken,
        "additionalFields" => ($response) ? json_encode($response) : null
        );
      }
      else {
        $parameters = array(
        "survey" => $this->surveyId,
        "token" => (isset($sToken)) ? $sToken : null,
        "api_token" => $auth,
        "server_key" => $serverToken,
        "additionalFields" => ($additionalFields) ? json_encode($additionalAnswers) : null
        );
      }
    }

    if ($requestType == 1)
      $hookSent = $this->httpGet($url, $parameters);
    else
      $hookSent = $this->httpPost($url, $parameters);

    $this->debug($parameters, $hookSent, $time_start,$response);

    return;
  }


  /**
   *   httpPost function http://hayageek.com/php-curl-post-get/
   *   creates and executes a POST request
   *   returns the output
   */
  private function httpPost($url, $params)
  {
    $fullUrl = $url . '?api_token=' . $params['api_token'];
    $postData = $params;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, count($postData));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }


  /**
   *   httpGet
   *   creates and executes a GET request
   *   returns the output
   */
  private function httpGet($url, $params)
  {
    $postData = http_build_query($params, '', '&');
    $fullUrl = $url . '?' . $postData;
    $fp = fopen(dirname(__FILE__) . '/errorlog.txt', 'w');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }


  /**
   *   check if the hook should be sent
   *   returns a boolean
   */

  private function isHookDisabled()
  {
    return ($this->get('bUse', 'Survey', $this->surveyId) == 0) || (($this->get('bUse', 'Survey', $this->surveyId) == 2) && ($this->get('bUse', null, null, $this->settings['bUse']) == 0));
  }


  /**
   *   check if the hook should be sent
   *   returns a boolean
   */
  private function getTokenString($response)
  {
    return ($this->get('bSendToken', 'Survey', $this->surveyId) === '1') ? $response['token'] : null;
  }

  /**
   *
   *
   */
  private function getAdditionalFields()
  {
    $additionalFieldsString = $this->get('sAnswersToSend', 'Survey', $this->surveyId);
    if ($additionalFieldsString != '' || $additionalFieldsString != null) {
      return explode(',', $this->get('sAnswersToSend', 'Survey', $this->surveyId));
    }
    return null;
  }

  private function getAdditionalAnswers($additionalFields = null, $response)
  {
    if ($additionalFields) {
      $additionalAnswers = array();
      foreach ($additionalFields as $field) {
        $additionalAnswers[$field] = htmlspecialchars($response[$field]);
      }
      return $additionalAnswers;
    }
    return null;
  }

  private function debug($parameters, $hookSent, $time_start, $response)
  {
    if ($this->get('bDebugMode', 'Survey', $this->surveyId) == 1) {
      $html =  '<pre>';
      $html .= print_r($parameters, true);
      $html .=  "<br><br> ----------------------------- <br><br>";
      $html .= print_r($hookSent, true);
      $html .=  "<br><br> ----------------------------- <br><br>";
      $html .= print_r($response, true);
      $html .=  "<br><br> ----------------------------- <br><br>";
      $html .=  'Total execution time in seconds: ' . (microtime(true) - $time_start);
      $html .=  '</pre>';
      $event = $this->getEvent();
      $event->getContent($this)->addContent($html);
    }
  }
}
