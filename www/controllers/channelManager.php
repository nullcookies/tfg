<?php
require_once('DBHelper.php');
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

    public function editChannel($old_title, $title, $description, $nicename, $events, $actions)
    {
        if ($old_title !== $title && $this->channelExists($title)) {
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
            'title' => $title,
            'description' => $description,
            'nicename' => $nicename,
            'events' => $events_aux,
            'actions' => $actions_aux
        );

        $this->manager->update('channels', 'title', $old_title, $channel);

        return true;
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
            $events_aux[$nEvents]['prefix'] = $event->prefix;
            $nEvents++;
        }

        $nActions = 1;
        foreach ($actions as $action) {
            $actions_aux[$nActions]['title'] = $action->title;
            $actions_aux[$nActions]['rule'] = $action->rule;
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

    public function removeChannel($title)
    {
        $filter = ['title' => $title];
        $options = ['projection' => ['image' => 1]];
        $image = $this->manager->find('channels', $filter, $options)[0]->image;
        $array_name = explode('/', $image);
        $name = end($array_name);
        if ($name !== 'channel.png') {
            $name = './img/' . $name;
            unlink($name);
        }

        return $this->manager->remove('channels', 'title', $title);
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
}

?>