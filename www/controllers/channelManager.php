<?php

namespace Ewetasker\Manager;

use Ewetasker\Manager\DBHelper;

include_once('DBHelper.php');
//require_once('./mongoconfig.php');

/**
* 
*/
class ChannelManager
{
    private $manager;

    function __construct($config)
    {
        $this->connect($config);
    }

    public function actionHasParameter($channel_title, $action_title)
    {
        $channel = $this->getChannel($channel_title);
        foreach ($channel['actions'] as $action) {
            if ($action['title'] === $action_title) {
                return $action['parameter'] !== '';
            }
        }
    }

    private function channelExists($title)
    {
        $filter = ['title' => $title];
        $cursor = $this->manager->find('channels', $filter);
        return !empty($cursor);
    }

    private function connect($config)
    {
        $this->manager = new DBHelper($config);
    }

    public function createNewChannel($title, $description, $nicename, $image, $events, $actions)
    {
        if ($this->channelExists($title)) {
            return false;
        }

        $events_aux = array();
        $actions_aux = array();

        $nEvents = 1;
        $nAux = 0;
        foreach ($events as $value) {
            if ($nAux === 0) {
                $events_aux[$nEvents]['title'] = $value;
                $nAux++;
            } elseif ($nAux === 1) {
                $events_aux[$nEvents]['rule'] = $value;
                if (!strpos($value, '#')) {
                    $events_aux[$nEvents]['parameter'] = '';
                } else {
                    $parameter = str_replace('#', '', strstr($value, '#'));
                    $events_aux[$nEvents]['parameter'] = trim($parameter);
                }
                $nAux++;
            } else {
                $events_aux[$nEvents]['prefix'] = $value;
                $nAux = 0;
                $nEvents++;
            }
        }

        $nActions = 1;
        $nAux = 0;
        foreach ($actions as $value) {
            if ($nAux === 0) {
                $actions_aux[$nActions]['title'] = $value;
                $nAux++;
            } elseif ($nAux === 1) {
                $actions_aux[$nActions]['rule'] = $value;
                if (!strpos($value, '#')) {
                    $actions_aux[$nActions]['parameter'] = '';
                } else {
                    $parameter = str_replace('#', '', strstr($value, '#'));
                    $actions_aux[$nActions]['parameter'] = trim($parameter);
                }
                $nAux++;
            } else {
                $actions_aux[$nActions]['prefix'] = $value;
                $nAux = 0;
                $nActions++;
            }
        }

        $channel = array(
            'title' => $title,
            'description' => $description,
            'nicename' => $nicename,
            'image' => $image,
            'events' => $events_aux,
            'actions' => $actions_aux
        );

        $this->manager->insert('channels', $channel);

        return true;
    }

    public function deleteChannel($channel_title)
    {
        $filter = ['title' => $channel_title];
        $options = ['projection' => ['image' => 1]];
        $image = $this->manager->find('channels', $filter, $options)[0]->image;
        $array_name = explode('/', $image);
        $name = end($array_name);
        if ($name !== 'channel.png') {
            $name = './img/' . $name;
            unlink($name);
        }

        return $this->manager->remove('channels', 'title', $channel_title);
    }

    public function editChannel($title, $description, $nicename, $events, $actions)
    {
        $events_aux = array();
        $actions_aux = array();

        $nEvents = 1;
        $nAux = 0;
        foreach ($events as $value) {
            if ($nAux === 0) {
                $events_aux[$nEvents]['title'] = $value;
                $nAux++;
            } elseif ($nAux === 1) {
                $events_aux[$nEvents]['rule'] = $value;
                $nAux++;
            } else {
                $events_aux[$nEvents]['prefix'] = $value;
                $nAux = 0;
                $nEvents++;
            }
        }

        $nActions = 1;
        $nAux = 0;
        foreach ($actions as $value) {
            if ($nAux === 0) {
                $actions_aux[$nActions]['title'] = $value;
                $nAux++;
            } elseif ($nAux === 1) {
                $actions_aux[$nActions]['rule'] = $value;
                $nAux++;
            } else {
                $actions_aux[$nActions]['prefix'] = $value;
                $nAux = 0;
                $nActions++;
            }
        }

        $channel = array(
            'description' => $description,
            'nicename' => $nicename,
            'events' => $events_aux,
            'actions' => $actions_aux
        );

        $this->manager->update('channels', 'title', $title, $channel);

        return true;
    }

    public function eventHasParameter($channel_title, $event_title)
    {
        $channel = $this->getChannel($channel_title);
        foreach ($channel['events'] as $event) {
            if ($event['title'] === $event_title) {
                return $event['parameter'] !== '';
            }
        }
    }

    public function getRulesAndPrefix($title)
    {
        $channel = $this->getChannel($title);
        $actions = array();
        $events = array();

        foreach ($channel['actions'] as $action) {
            $actions[$action['title']]['rule'] = $action['rule'];
            $actions[$action['title']]['prefix'] = $action['prefix'];
        }
        foreach ($channel['events'] as $event) {
            $events[$event['title']]['rule'] = $event['rule'];
            $events[$event['title']]['prefix'] = $event['prefix'];
        }

        return array('actions' => $actions, 'events' => $events);
    }

    public function getChannel($title)
    {
        $filter = ['title' => $title];
        $array_channel = $this->manager->find('channels', $filter)[0];

        $title = $array_channel->title;
        $description = $array_channel->description;
        $nicename = $array_channel->nicename;
        $image = $array_channel->image;
        $events = $array_channel->events;
        $actions = $array_channel->actions;

        $events_aux = array();
        $actions_aux = array();

        $nEvents = 1;
        foreach ($events as $event) {
            $events_aux[$nEvents]['title'] = $event->title;
            $events_aux[$nEvents]['rule'] = $event->rule;
            $events_aux[$nEvents]['parameter'] = $event->parameter;
            $events_aux[$nEvents]['prefix'] = $event->prefix;
            $nEvents++;
        }

        $nActions = 1;
        foreach ($actions as $action) {
            $actions_aux[$nActions]['title'] = $action->title;
            $actions_aux[$nActions]['rule'] = $action->rule;
            $actions_aux[$nActions]['parameter'] = $action->parameter;
            $actions_aux[$nActions]['prefix'] = $action->prefix;
            $nActions++;
        }

        $channel = array(
            'title' => $title,
            'description' => $description,
            'nicename' => $nicename,
            'image' => $image,
            'events' => $events_aux,
            'actions' => $actions_aux
        );

        return $channel;        
    }

    public function getChannelsList()
    {
        $channels = $this->manager->getByTitle('channels', 'title');
        $channels_list = array();
        foreach ($channels as $channel) {
            array_push($channels_list, $channel->title);
        }

        return $channels_list;
    }

    public function getEvents($title) {
        $filter = ['title' => $title];
        $options = ['projection' => ['events' => 1]];
        $events = $this->manager->find('channels', $filter, $options)[0]->events;
        $array_events = array();
        foreach ($events as $event) {
           array_push($array_events, $event->title);
        }
        return $array_events;        
    }

    public function getActions($title) {
        $filter = ['title' => $title];
        $options = ['projection' => ['actions' => 1]];
        $actions = $this->manager->find('channels', $filter, $options)[0]->actions;
        $array_actions = array();
        foreach ($actions as $action) {
           array_push($array_actions, $action->title);
        }
        return $array_actions;        
    }

    public function viewChannelsHTML()
    {
        $options = ['sort' => ['title' => 1]];
        $channels = $this->manager->find('channels', [], $options);

        foreach ($channels as $channel) {
            $image = $channel->image;
            $title = $channel->title;
            $description = $channel->description;
            $buttons = '';

            if (isset($_SESSION['user']) && $_SESSION['user'] === 'admin') {
                $buttons = '
                <!-- Channel buttons -->
                <div class="col-md-2 channel-fragment">
                    <button type="button" class="btn btn-info btn-rules-action" onclick="window.location=\'./editchannel.php?channelTitle=' . $title . '\'">Edit</button>
                    <button type="button" class="btn btn-danger btn-rules-action" onclick="window.location=\'./deletechannel.php?channelTitle=' . $title . '\'">Delete</button>
                </div>';
            }

            echo '
            <!-- Channel item -->
            <div class="row channel-item">
                <!-- Channel img -->
                <div class="col-md-2 col-md-offset-1 channel-fragment">
                    <img class="img img-circle img-responsive img-channel" src="' . $image . '" />
                </div>

                <!-- Channel description -->
                <div class="col-md-6 channel-fragment">
                    <p><strong>' . $title . '</strong><br>' . $description . '.</p>
                </div>

                ' . $buttons . '            
            </div>
            ';
        }
    }

    public function viewChannelsIconHTML()
    {
        $options = ['sort' => ['title' => 1]];
        $channels = $this->manager->find('channels', [], $options);

        foreach ($channels as $channel) {
            $image = $channel->image;
            $title = $channel->title;
            $hasAction = '';
            $hasEvent = '';
            if (!empty($channel->actions)) {
                $hasAction = 'hasAction';
            }
            if (!empty($channel->events)) {
                $hasEvent = 'hasEvent';
            }

            echo '
            <!-- Channel icon item -->
            <div class="col-md-2 channel-icon-item">
                <!-- Image -->
                <div class ="row">
                    <div class="col-md-12">
                        <img class="img img-circle img-responsive img-channel draggable ' . $hasAction . ' ' . $hasEvent . '" id="' . $title . '" src="' . $image . '" />
                    </div>
                </div>  <!-- Image -->

                <!-- Title -->
                <div class ="row">
                    <div class="col-md-12 rule-fragment rule-info">
                        <p>' . $title . '</p>
                    </div>
                </div>  <!-- Title -->
            </div>
            ';
        }
    }
}

?>