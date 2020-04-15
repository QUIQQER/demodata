<?php

namespace QUI\Demodata\Parser\XML;

use QUI\Utils\Text\XML;

/**
 * Class EventParser
 *
 * @package QUI\Demodata\Parser\XML
 */
class EventParser
{
    /**
     * Parses the events from the given XML file
     *
     * @param $filePath
     *
     * @return array
     */
    public static function parseEvents($filePath)
    {
        $Dom    = XML::getDomFromXml($filePath);
        $Path   = new \DOMXPath($Dom);
        $events = $Path->query("//data/events/event");

        $eventList = [];

        foreach ($events as $Event) {
            $event = $Event->getAttribute('on');
            $fire  = $Event->getAttribute('fire');

            if (empty($event)) {
                continue;
            }

            if (!isset($eventList[$event])) {
                $eventList[$event] = [];
            }

            $eventList[$event][] = $fire;
        }

        return $eventList;
    }
}
