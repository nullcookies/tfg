<?php

header('Content-Type: application/json');

use Ewetasker\Manager\AdministrationManager;
use Ewetasker\Manager\RuleManager;
use Ewetasker\Manager\UserManager;

include_once('administrationManager.php');
include_once('ruleManager.php');
include_once('userManager.php');

$rule_manager = new RuleManager();
$user_manager = new UserManager();

$input_event = $_POST['inputEvent'];
$input_event = preg_replace("/\.(\s+)/", ".\n", $input_event);
$user = $_POST['user'];

$user_manager->setEvent($input_event, $user);
$input_event = '';
foreach ($user_manager->getEvents($user) as $event) {
    $input_event .= $event['event'] . PHP_EOL;
}

$actionsJson = array('success' => 1);
$actionsJson['actions'] = array();

$imported_rules = $user_manager->getImportedRules('username', $user);
foreach ($imported_rules as $rule_title) {
    $rule = $rule_manager->getRule($rule_title);
    $placeURL = $rule_manager->getURLPlace($rule['place']);

    $response = evaluateEvent($input_event, $rule['rule']);
    $responseJSON = parseResponse($input_event, $response, $user, $placeURL);
    if (!empty($responseJSON['actions'])) {
        foreach ($responseJSON['actions'] as $actionJson) {
            array_push($actionsJson['actions'], $actionJson);
        }
    }
}

echo json_encode($actionsJson);

function deleteAllBetween($beginning, $end, $string)
{
    $beginning_pos = strpos($string, $beginning);
    $end_pos = strpos($string, $end);
    if ($beginning_pos === false || $end_pos === false) {
        return $string;
    }
    $text_to_delete = substr($string, $beginning_pos, $end_pos + strlen($end) - $beginning_pos);
    return str_replace($text_to_delete, '', $string);
}

function evaluateEvent($input, $rules)
{
    $data = array(
        'data' => array($rules, $input),
        'query' => '{ ?a ?b ?c. } => { ?a ?b ?c. }.'
    );

    $url = 'http://eye.restdesc.org/';

    $ch = curl_init($url);

    $postString = http_build_query($data, '', '&');

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function deleteInputFromResponse($input, $response)
{
    foreach ($input as $input_line) {
        $input_line = trim(substr($input_line, 0, strlen($input_line) - 1));
        foreach ($response as $key => $response_line) {
            $response_line = trim(substr($response_line, 0, strlen($response_line) - 1));
            if ($input_line === $response_line) {
                unset($response[$key]);
            }
        }
    }
    return $response;
}

function parseResponse($input, $response, $user, $placeURL)
{
    // REMOVE PREFIXES.
    while(strpos($response, 'PREFIX') !== false){
        $response = deleteAllBetween('PREFIX', '>', $response);
    }

    while(strpos($input, '@prefix') !== false){
        $input = deleteAllBetween('@prefix', '> .', $input);
        $input = deleteAllBetween('@prefix', '>.', $input);
    }

    $input = str_replace('\'', '"', $input);

    // REMOVE COMMENTS.
    while(strpos($input, '#C') !== false){
        $input = deleteAllBetween('#C', 'C#', $input);
    }

    // CHANGE RDF:TYPE BY A
    $input = str_replace('rdf:type', 'a', $input);

    // REMOVE BLANK SPACES AND BREAKPOINTS
    $input = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $input);
    $response = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $response);

    // SPLIT IN SENTENCES
    $splittedInput = array_filter(explode("\n", trim($input)));
    $splittedResponse = array_filter(explode("\n", trim($response)));

    $splittedResponse = deleteInputFromResponse($splittedInput, $splittedResponse);

    $splittedResponse = array_values($splittedResponse);
    $splittedResponse = array_filter($splittedResponse);

    // SPLIT ACTIONS AND PARAMETERS.
    $lines_with_parameters = array();
    $lines_with_actions = array();
    foreach ($splittedResponse as $line) {
        if (strpos($line, 'ov:')) {
            array_push($lines_with_parameters, $line);
            continue;
        }
        array_push($lines_with_actions, $line);
    }

    // SPLIT ACTIONS.
    $actionsJson = array('success' => 1);
    $actionsJson['actions'] = array();
    $parameters = array();
    foreach ($lines_with_parameters as $line) {
        $response = preg_split("/[\s]+/", trim($line));
        $key_param = $response[0];
        $parameter = '';
        for ($i = 2; $i < count($response); $i++) { 
            $parameter .= $response[$i] . ' ';  # It is neccesary if the parameter is a string with spaces.
        }
        $parameter = trim($parameter);
        $parameter = str_replace(array('".', '"'), '', strstr($parameter, '"'));
        if (array_key_exists($key_param, $parameters)) {
            array_push($parameters[$key_param], $parameter);
        } else {
            $parameters[$key_param] = array();
            array_push($parameters[$key_param], $parameter);
        }
    }
    foreach ($lines_with_actions as $line) {
        $response = preg_split("/[\s,]+/", trim($line));
        $action['channel'] = str_replace(':', '', strstr($response[0], ':'));
        $key_param = trim(substr($response[2], 0, strlen($response[2]) - 1));;
        $action['action'] = str_replace([':', '.'], '', strstr($response[2], ':'));
        $action['parameter'] = '';
        $admin_manager = new AdministrationManager();
        if (array_key_exists($key_param, $parameters)) {
            foreach ($parameters[$key_param] as $parameter) {
                $action['parameter'] = $parameter;
                postToActionTrigger($action['channel'], $action['action'], $action['parameter'], $user, $placeURL);
                array_push($actionsJson['actions'], $action);
                $admin_manager->runAction($action['channel'], $action['action']);
                $admin_manager->userRuns($user);
            }
        } else {
            postToActionTrigger($action['channel'], $action['action'], $action['parameter'], $user, $placeURL);
            array_push($actionsJson['actions'], $action);
            $admin_manager->runAction($action['channel'], $action['action']);
            $admin_manager->userRuns($user);
        }
        unset($admin_manager);
    }

    return $actionsJson;
}

function postToActionTrigger($channel, $action, $parameter, $user, $placeURL)
{
    switch ($channel) {
        case 'Telegram':
        case 'Twitter':
        case 'Chromecast':
            if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] === 'Apache-HttpClient/UNAVAILABLE (Java/0)')
                return;
        case 'HueLight':
        case 'apiai':
        case 'RobotMip':
            $url = $placeURL;
            break;
        
        default:
            return;
    }

    $ch = curl_init($url);

    $postString = 'channel=' . $channel . '&action=' . $action . '&parameter=' . $parameter . '&user=' . $user;

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    curl_close($ch);
}