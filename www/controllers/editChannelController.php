<?php

session_start();

use Ewetasker\Manager\ChannelManager;
include_once('channelManager.php');

$manager = new ChannelManager();

$title = htmlspecialchars($_POST['title']);
$description = htmlspecialchars($_POST['description']);
$nicename = htmlspecialchars($_POST['nicename']);

$events = array();
$actions = array();

foreach ($_POST as $key => $value) {
    if (fnmatch('event*', $key)) {
        $events[$key] = $value;
    } elseif (fnmatch('action*', $key)) {
        $actions[$key] = $value;
    }
}

if(empty($events) && empty($actions)) {
    header('Location: ../editchannel.php?error=neitherActionNorEvent');
} elseif ($manager->editChannel($title, $description, $nicename, $events, $actions)) {
    header('Location: ../channels.php');
} else {
    header('Location: ../index.php');
}