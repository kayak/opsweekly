<?php

function get_base_url() {
  return "https://api.opsgenie.com/v2";
}

/**
 * OpsGenie on call provider
 */

/** Plugin specific variables required
 * Global Config:
 *  - apikey: The API key to connect
 *
 * Team Config:
 *  - opsgenie_team_id: The service ID that this team uses for alerts to be collected
 *
 */


/**
 * getOnCallNotifications - Returns the notifications for a given time period and parameters
 *
 * Parameters:
 *   $on_call_name - The username of the user compiling this report
 *   $provider_global_config - All options from config.php in $oncall_providers - That is, global options.
 *   $provider_team_config - All options from config.php in $teams - That is, specific team configuration options
 *   $start - The unix timestamp of when to start looking for notifications
 *   $end - The unix timestamp of when to stop looking for notifications
 *
 * Returns 0 or more notifications as array()
 * - Each notification should have the following keys:
 *    - time: Unix timestamp of when the alert was sent to the user
 *    - hostname: Ideally contains the hostname of the problem. Must be populated but feel free to make bogus if not applicable.
 *    - service: Contains the service name or a description of the problem. Must be populated. Perhaps use "Host Check" for host alerts.
 *    - output: The plugin output, e.g. from Nagios, describing the issue so the user can reference easily/remember issue
 *    - state: The level of the problem. One of: CRITICAL, WARNING, UNKNOWN, DOWN
 */

function getOnCallNotifications($name, $global_config, $team_config, $start, $end) {
    if(isset($global_config['username'])) {
        $username = $global_config['username'];
    } else {
        $username = NULL;
    }
    if(isset($global_config['password'])) {
        $password = $global_config['password'];
    } else {
        $password = NULL;
    }
    $apikey = $global_config['apikey'];
    $team_id = $team_config['opsgenie_team_id'];
    if ($api_key !== '' && $team_id !== '') {
        // convert single OpsGenie service, to array construct in order to hold multiple services.
        if (!is_array($team_id)) {
            $team_id = array($team_id);
        }
        // loop through all OpsGenie services
        foreach ($team_id as $sid) {
            // check if the service id is formated correctly
            if (!sanitizeOpsGenieServiceId($sid)) {
                logline('Incorect format for OpsGenie Service ID: ' . $sid);
                // skip to the next Service ID in the array
            continue;
            }
            // loop through OpsGenie's maximum incidents count per API request.
            $running_total = 0;
            do {
            // Connect to the OpsGenie API and collect all incidents in the time period.
                //$start = date('c', $start);
                //$end = date('c', $end);
                $query = array(
                    "createdAt >= ${start}",
                    "lastOccurredAt <= ${end}",
                    "teams:${sid}"
                );
                $parameters = array(
                    "query" => urlencode(join(" ", $query)),
                    'offset' => $running_total,
                    'limit' => "100"
                );
                $incident_json = doOpsGenieAPICall('/alerts/', $parameters, $apikey);
                if (!$incidents = json_decode($incident_json)) {
                    return 'Could not retrieve incidents from OpsGenie! Please check your login details';
                }
                // skip if no incidents are recorded
                if (count($incidents->data) == 0) {
                    continue;
                }
                logline("Incidents on Service ID: " . $sid);
                logline("Total incidents: " . count($incidents->data));
                $running_total += count($incidents->data);
                logline("Running total: " . $running_total);
                foreach ($incidents->data as $incident) {
                    $time = strtotime($incident->createdAt);
                    $state = $incident->status;
                    // try to determine and set the service
                    if (isset($incident->integration->name)) {
                        $service = $incident->integration->name;
                    } else {
                        $service = "unknown";
                    }
                    $alert_json = doOpsGenieAPICall("/alerts/". $incident->id, [], $apikey);
                    if (!$alert = json_decode($alert_json)) {
                      return "Could not retrieve alert details from OpsGenie. Check logs.";
                    }
                    if (preg_match('/LogicMonitor/i',$service) {
                        if (!empty($alert->data->entity)){
                            $output = $alert->data->entity;
                        } else {
                            $output = 'OpsGenie';
                        }
                        $output .= "\n";
                        // Add to the output all the trigger_summary_data info
                    
                        $details_to_find = array('datasource', 'datapoint', 'threshold')
                        if (!empty((array)$alert->data->details)) {
                            $details = (array)$alert->data->details;
                            foreach($details_to_find as $detail_to_find){
                                if (array_key_exists($detail_to_find, $details){
                                    $output .= sprintf("%s: %s\n", ucfirst($detail_to_find), $details[$detail_to_find]);
                                }
                            }
                        }
                        /*if (!empty((array)$alert->data->details)) {
                          foreach ($alert->data->details as $key => $key_data) {
                            $output .= "{$key}: {$key_data}\n";
                          }
                        }*/
                    } else {
                        // We're not from logicmonitor
                        $output = $alert->data->message;
                        $output .= "\n";
                        $output .= $alert->data->description;
                    }

                    // try to determine the hostname
                    if (isset($alert->data->entity)) {
                        $hostname = $alert->data->entity;
                    } else {
                        // fallback is to just say it was OpsGenie that sent it in
                        $hostname = "OpsGenie";
                    }

                    $notifications[] = array("time" => $time, "hostname" => $hostname, "service" => $service, "output" => $output, "state" => $state);
                }
            } while ($running_total < count($incidents->data));
        }
        // if no incidents are reported, don't generate the table
        if (count($notifications) == 0 ) {
            return array();
        } else {
            return $notifications;
        }
    } else {
        return false;
    }
}

function doOpsGenieAPICall($path, $parameters, $opsgenie_apikey) {
    if (isset($opsgenie_apikey)) {
        $context = stream_context_create(array(
            'http' => array(
                'header'  => "Authorization: GenieKey $opsgenie_apikey"
            )
        ));
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'header'  => "Authorization: Basic " . base64_encode("$opsgenie_username:$opsgenie_password")
            )
        ));
    }
    $params = null;
    foreach ($parameters as $key => $value) {
        if (isset($params)) {
            $params .= '&';
        } else {
            $params = '?';
        }
        $params .= sprintf('%s=%s', $key, $value);
    }
    return file_get_contents(get_base_url() . $path . $params, false, $context);
}

function whoIsOnCall($schedule_id, $time = null) {
    $since = date('c', isset($time) ? $time : time());
    $parameters = array(
        'flat' => 'true',
        'date' => $since
    );
    $json = doOpsGenieAPICall("/schedules/{$schedule_id}/on-calls", $parameters);
    if (false === ($scheddata = json_decode($json))) {
        return false;
    }
    if (count($scheddata->data->onCallRecipients) == 0) {
        return false;
    }
    if ($scheddata->data->onCallRecipients[0] == "") {
        return false;
    }
    $oncalldetails = array();
    $oncalldetails['email'] = $scheddata->data->onCallRecipients[0];
    return $oncalldetails;
}

function sanitizeOpsGenieServiceId($service_id) {
    $pattern = '/^(\{{0,1}([0-9a-fA-F]){8}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){12}\}{0,1})$/';
    if (preg_match($pattern, $service_id)) {
        return true;
    } else {
        return false;
    }
}
?>
