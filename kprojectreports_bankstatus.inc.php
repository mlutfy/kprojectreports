<?php

function kprojectreports_bankstatus_datehelp() {
  return t("If the date is 2012-02-20, the preview will generate a report from the beginning of the period (ex: month) up to that date.");
}

function kprojectreports_bankstatus($start, $end, $report) {
  $output = '';

  if (isset($_REQUEST['daterun'])) {
    $now = strtotime(check_plain($_REQUEST['daterun']));
  } else {
    $now = time();
  }

  // TODO:
  // - calculate if hours estimated
  // - check at what % we were supposed to send a warning

  $tmp = explode(' ', $report->options['bankstatus_contactid']);
  $contractid = $tmp[0];

  switch ($report->options['bankstatus_type']) {
    case 'weekly':
      // first day of the current week
      $report_first_day     = $report->options['week_firstday'];
      $current_day_of_week  = date('N', $now); // 1 = mon, 7 = sun

      // We substract 1 from current_day_of_week because $now is probably 1 AM on the next day.
      // Ex: if "now = 3 (wed), start = 7 (sunday), then delta = (3 - 7) = -4 => (7 - abs(-4)) = 3.
      //     if "now = 7 (sun), start = 7 (sunday), then delta = (7 - 7) = 0  => (7 - 0) = 7.
      $delta = $current_day_of_week - 1 - $report_first_day;
      $delta = ($delta < 0 ? 7 - abs($delta) : $delta);

      // Ex: if today is Sunday, and start of week is sunday, then report past week
      if ($delta == 0) {
        $delta = 7;
      }

      $date_start = mktime(0, 0, 0, date('n', $now), date('j', $now) - $delta, date('Y', $now));
      break;

    case 'monthly':
      // first day of current month (does not cover edge cases)
      $date_start = mktime(0, 0, 0, date('n', $now), 1, date('Y', $now));
      break;

    default:
      $date_start = kprojectreports_unixdatefromarray($report->options['bankstatus_startdate']);
  }

  $date_end = $now;
  $totalhours = 0;

  // Fetch contract name
  $contractname = db_query("SELECT node.title FROM {node} WHERE nid = :nid", array(':nid' => $contractid))->fetchField();

  // Show work summary (by task)
  $output .= "<h1>" . "Summary by task" . "</h1>";
  $output .= "<table>";
  $output .= '<tr>'
           . '<th>' . t('Task')     . '</th>'
           . '<th style="text-align: right;">' . t('Worked')   . '</th>'
           . '</tr>';

  $sql = "SELECT ktask_ktask_node.title as tasktitle, sum(kpunch.duration) / 60 / 60 as totalhours
          FROM {kpunch} kpunch
          LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
          LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
          LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
          LEFT JOIN {node} ktask_ktask_node ON node_kpunch__ktask.nid = ktask_ktask_node.nid
          WHERE kpunch.begin >= :begin
            AND kpunch.begin + kpunch.duration <= :end
            AND ktask_kcontract_node.nid = :nid
	  GROUP BY ktask_ktask_node.nid";

  $result = db_query($sql, array(':begin' => $date_start, ':end' => $date_end, ':nid' => $contractid));

  foreach ($result as $contract) {
    $output .= "<tr><td>" . $contract->tasktitle . '</td><td style="text-align: right;">' . sprintf('%.2f', $contract->totalhours) . " " . "h" ."</td></tr>";
    $totalhours += $contract->totalhours;
  }

  $output .= '<tr><td><strong>' . 'TOTAL:' . '</strong></td><td style="text-align: right;"><strong>' . sprintf('%.2f', $totalhours) . ' h' . '</strong></td></tr>';
  $output .= "</table>";

  // Show work summary (by user)
  if ($report->options['bankstatus_showpunches']) {
    $output .= "<h1>" . "Summary by user" . "</h1>";
    $output .= "<table>";
    $output .= '<tr>'
             . '<th>' . t('User') . '</th>'
             . '<th style="text-align: right;">' . t('Worked') . '</th>'
             . '</tr>';

    $sql = "SELECT ktask_ktask_node.title as tasktitle, sum(kpunch.duration) / 60 / 60 as totalhours, users.name as username
            FROM {kpunch} kpunch
            LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
  	  INNER JOIN {users} users ON kpunch.uid = users.uid
            LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
            LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
            LEFT JOIN {node} ktask_ktask_node ON node_kpunch__ktask.nid = ktask_ktask_node.nid
            WHERE kpunch.begin >= :begin
              AND kpunch.begin + kpunch.duration <= :end
              AND ktask_kcontract_node.nid = :nid
  	  GROUP BY users.uid";

    $result = db_query($sql, array(':begin' => $date_start, ':end' => $date_end, ':nid' => $contractid));

    foreach ($result as $contract) {
      $output .= "<tr><td>" . $contract->username . '</td>'
               . '<td style="text-align: right;">' . sprintf('%.2f', $contract->totalhours) . " " . "h" ."</td></tr>";
    }

    $output .= "</table>";

    // Show all punches
    $output .= "<h1>" . "All punches" . "</h1>";
    $output .= "<table>";
    $output .= '<tr>'
             . '<th>' . t('Date')     . '</th>'
             . '<th>' . t('Contract')   . '</th>'
             . '<th>' . t('User'). '</th>'
             . '<th style="text-align: right;">' . t('Worked') . '</th>'
             . '<th>' . t('Comment')   . '</th>'
             . '</tr>';

    $sql = "SELECT from_unixtime(kpunch.begin) as begin, kpunch.comment, ktask_ktask_node.title as tasktitle, users.name as username, kpunch.duration / 60 / 60 as totalhours
            FROM {kpunch} kpunch
            LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
            INNER JOIN {users} users ON kpunch.uid = users.uid
            LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
            LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
            LEFT JOIN {node} ktask_ktask_node ON node_kpunch__ktask.nid = ktask_ktask_node.nid
            WHERE kpunch.begin >= :begin
              AND kpunch.begin + kpunch.duration <= :end
              AND ktask_kcontract_node.nid = :nid";

    $result = db_query($sql, array(':begin' => $date_start, ':end' => $date_end, ':nid' => $contractid));

    foreach ($result as $contract) {
      $output .= "<tr><td>" . $contract->begin . '</td>'
               . '<td>' . $contract->tasktitle . '</td>'
               . '<td>' . $contract->username . '</td>'
               . '<td style="text-align: right;">' . sprintf('%.2f', $contract->totalhours) . " h" . '</td>'
               . '<td>' . $contract->comment . '</td></tr>';
    }

    $output .= "</table>";
  }

  // Prepend contract name and total hours
  $output = "<p>" . "Time tracker report: hours worked from " . date('Y-m-d', $date_start) . " to " . date('Y-m-d', $date_end) . "<br/>"
          . "Contract:" . ' ' . $contractname . "<br/>"
	  . 'Hours:' . ' ' . sprintf('%.2f', $totalhours) . ' of ' . $report->options['bankstatus_hours'] . '</p>'
          . $output;

  return $output;
}


function kprojectreports_bankstatus_editreport_addtoform(&$form_state, &$form, $data) {
  $form['option_bankstatus_contactid'] = array(
    '#type' => 'textfield',
    '#title' => t('Contract'),
    '#autocomplete_path' => 'kproject/autocomplete/kcontract',
    '#required' => TRUE,
    '#size' => 25,
    '#default_value' => $data->options['bankstatus_contactid'],
  );

  $form['option_bankstatus_type'] = array(
    '#type' => 'select',
    '#title' => t('Type'),
    '#required' => TRUE,
    '#default_value' => $data->options['bankstatus_type'],
    '#options' => array(
      ''        => '',
      'monthly' => 'Monthly',
      'weekly'  => 'Weekly',
      'bydate'  => 'Pre-paid open account',
    ),
  );

  $form['option_bankstatus_startdate'] = array(
    '#type' => 'date',
    '#title' => t('Start date'),
    '#required' => FALSE,
    '#default_value' => $data->options['bankstatus_startdate'],
    '#description' => 'Ignore this field if it is a "monthly" type of agreement, e.g. 10h/month',
  );

  $form['option_bankstatus_hours'] = array(
    '#type' => 'textfield',
    '#title' => t('Hours'),
    '#required' => TRUE,
    '#default_value' => $data->options['bankstatus_hours'],
  );

  $form['option_bankstatus_showpunches'] = array(
    '#type' => 'select',
    '#title' => t('Display punch details'),
    '#required' => TRUE,
    '#default_value' => $data->options['bankstatus_showpunches'],
    '#options' => array(
      0 => t("no"),
      1 => t("yes"),
    ),
  );

  $form['option_bankstatus_warnpercent'] = array(
    '#type' => 'select',
    '#title' => t('Report at'),
    '#required' => TRUE,
    '#default_value' => $data->options['bankstatus_warnpercent'],
    '#options' => array(
      0 => '',
      1 => 'Every run',
      25 => '25%, 50%, 75%, 95%, 100%',
      50 => '50%, 75%, 95%, 100%',
      75 => '75%, 95%, 100%',
      95 => '95%, 100%',
      100 => '100%',
    ),
  );
}

