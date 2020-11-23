<?php

/**
 *  A 'weekly' provider, or 'hints' is designed to prompt the
 *  user to remember what they did in the last week, so they can
 *  fill out their weekly report more accurately.
 *
 *  The class name doesn't matter.. It's picked in the config.
 *
 *  Your constructor should accept the following variables:
 *  - $username: The username of the person the hints are for
 *  - $config: An array of the config options that came from config.php
 *  - $events_from: The beginning of the period to show hints for
 *  - $events_to: The end of the period to show hints for
 *
 *  Then, just create a public function 'printHints' that returns HTML to be
 *  inserted into the sidebar of the "add report" page.
 *
 **/

class JIRAHints {
    private $jira_api_url, $jira_url;
    private $events_from, $events_to;
    private $username;
    private $method, $days;

    private $jira_context;

    public function __construct($username, $config, $events_from, $events_to) {
        $jusername_fromdb = getJiraUsernameFromDb();
        if (!($jusername_fromdb == NULL)) {
            $this->username = str_replace('@', '\u0040', getJiraUsernameFromDb());
        } else {
            $this->username = $username;
        }
        $this->events_from = $events_from;
        $this->events_to = $events_to;
        $this->jira_api_url = $config['jira_api_url'];
        $this->jira_url = $config['jira_url'];
        $this->method = array_key_exists("method", $config) ? $config['method'] : "printJIRAForPeriod";
        $this->days = array_key_exists("days", $config) ? $config['days'] : 7;

        $this->jira_context = $this->_create_context_from_config($config);
    }

    public function _create_context_from_config($config) {
        // Should support both basic and digest/bearer authentication.
        // Return stream_context_create()
        $authorization = '';
        /// Prefer token auth
        if (!empty($config['token'])) {
            $authorization = sprintf('Basic %s', base64_encode("{$config['username']}:{$config['token']}")); 
        } elseif (!empty($config['username']) && !empty($config['password'])) {
            $authorization = sprintf('Basic %s', base64_encode("{$config['username']}:{$config['password']}"));
        } else {
            die('Failed to create Jira context. Ensure you have setup config properly');
        }

        return stream_context_create(array(
            'http' => array(
                'header' => sprintf("Authorization: %s\r\nAccept: */*", $authorization)
            )
        ));
    }


    public function printHints() {
      switch ($this->method) {
        case "printJIRAForPeriod":
          return $this->printJIRAForPeriod();
          break;
        case "printJiraForDays":
          return $this->printJiraForDays($this->days);
          break;
      }
    }

    public function getJIRALastPeriod($days) {
        $user = strtolower($this->username);
        $search = rawurlencode("assignee = {$user} AND Status changed DURING (-{$days}days,NOW()) AND Status != New ORDER BY status ASC, updated DESC");
        $json = file_get_contents("{$this->jira_api_url}/search?jql={$search}", false, $this->jira_context);
        $decoded = json_decode($json);
        return $decoded;
    }

    public function getJIRAForPeriod($start, $end) {
        $user = strtolower($this->username);
        $search = "assignee = {$user} AND Status changed AFTER {$start} AND Status changed BEFORE {$end} AND Status != New ORDER BY status ASC, updated DESC";
        $search = rawurlencode($search);
        $json = file_get_contents("{$this->jira_api_url}/search?jql={$search}", false, $this->jira_context);
        $decoded = json_decode($json);
        return $decoded;
    }

    public function printJIRAForDays($days) {
        $tickets = $this->getJIRALastPeriod($days);
        if ($tickets->total > 0) {
            $html = "<ul>";
            foreach ($tickets->issues as $issue) {
                $html .= '<li><a href="' . $this->jira_url . '/browse/' . $issue->key. '" target="_blank">';
                $html .= "{$issue->key}</a> - {$issue->fields->summary} ({$issue->fields->status->name})</li>";
            }
            $html .= "</ul>";
            return $html;
        } else {
            # No tickets found
            return insertNotify("error", "No JIRA activity in the last 7 days found");
        }

    }

    public function printJIRAForPeriod() {
        // JIRA wants milliseconds instead of seconds since epoch
        $range_start = $this->events_from * 1000;
        $range_end = $this->events_to * 1000;
        $tickets = $this->getJIRAForPeriod($range_start, $range_end);
        if ($tickets->total > 0) {
            $html = "<ul>";
            foreach ($tickets->issues as $issue) {
                $html .= '<li><a href="' . $this->jira_url . '/browse/' . $issue->key. '" target="_blank">';
                $html .= "{$issue->key}</a> - {$issue->fields->summary} ({$issue->fields->status->name})</li>";
            }
            $html .= "</ul>";
            return $html;
        } else {
            # No tickets found
            return insertNotify("error", "No JIRA activity for this period was found");
        }
    }
}

?>
