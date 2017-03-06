<?php

/**
* 
*/
class DBHelper
{
    
    private $manager;

    function __construct($config)
    {
        $this->connect($config);
    }

    private function connect($config)
    {
        try{
            if (!class_exists('MongoDB\Driver\Manager')){
                echo ("The MongoDB PECL extension has not been installed or enabled");
                return false;
            }
            $manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");/*
                $config['connection_string'],
                array('username' => $config['username'], 'password' => $config['password'])
            );*/
            return $this->manager = $manager;
        } catch(Exception $e) {
            echo $e;
            return false;
        }
    }

    function find($collection, $filter=[], $options=[])
    {
        $query = new MongoDB\Driver\Query($filter, $options);
        $dbCollection = 'applicationdb.' . $collection;
        return $this->manager->executeQuery($dbCollection, $query)->toArray();
    }

    function getByTitle($collection, $title)
    {
        $result = array();

        $cursor = $this->find($collection);
        foreach($cursor as $document) {
            foreach ($document as $key => $value) {
                if($key === $title) {
                    array_push($result, $value);
                }
            }
        }

        return $result;
    }

    function insert($collection, $article)
    {
        $bulk = new MongoDB\Driver\BulkWrite();

        $_article = array();
        foreach ($article as $title => $value) {
            $_article[$title] = $value;
        }
        $_article['createdAt'] = date_format(new DateTime(), 'Y-m-d H:i:s');
        $bulk->insert($_article);

        $dbCollection = 'applicationdb.' . $collection;
        $result = $this->manager->executeBulkWrite($dbCollection, $bulk);
    }
}