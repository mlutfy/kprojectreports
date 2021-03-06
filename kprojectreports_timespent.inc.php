<?php

function kprojectreports_timespent($start, $end, $report) {
  $output = '';

  $output .= kprojectreports_timespent_global($start, $end);
  $output .= kprojectreports_timespent_user($start, $end);

  return $output;
}

function kprojectreports_timespent_datehelp() {
  return t("If the date is 2012-02-20 and it is a monthly report, then report will be from 2012-01-01 to 2012-01-31. If you want to generate a partial monthly report for 2012-02, then enter 2012-03-01 as the run date.");
}

//
// ------------------------------- GLOBAL REPORT -------------------------
//

function kprojectreports_timespent_global($date_start, $date_end) {
  drupal_set_message('from ' . date('c', $date_start) . ' to ' . date('c', $date_end));

  $exclude_mode = 'incomes';
  $exclude_clients = array('Koumbit'); // HARDCODE XXX FIXME

  $report_lines = kprojectreports_timespent_get_summary_global($date_start, $date_end);

  //
  // Incomes
  //
  $totalincomes = 0;

  $outputincomes  = '<table>';
  $outputincomes .= '<tr>'
           . '<th>' . t('Client')     . '</th>'
           . '<th>' . t('Contract')   . '</th>'
           . '<th style="text-align: right;">' . t('Period work'). '</th>'
           . '<th style="text-align: right;">' . t('Total work') . '</th>'
           . '<th style="text-align: right;">' . t('Estimate') . '</th>'
           . '<th style="text-align: right;">' . t('% period') . '</th>'
           . '<th style="text-align: right;">' . t('% total') . '</th>'
           . '</tr>';

  if ($exclude_mode == 'incomes') {
    // List all, except those excluded
    foreach ($report_lines as $client_name => $tmp) {
      foreach ($tmp as $key => $val) {
        if (! in_array($val['client_title'], $exclude_clients)) {
          if ($val['estimate'] > 0) {
            $pct_period = $val['hours_period'] / $val['estimate'] * 100;
            $pct_total  = $val['hours_total'] / $val['estimate'] * 100;
          } else {
            $pct_period = 0;
            $pct_total = 0;
          }

          $color = '#000000';

          if ($pct_total > 100 || (! $val['estimate'])) {
            $color = '#FF0000';
          }
          elseif ($pct_total > 80) {
            $color = '#AAAA00;';
          }

          $outputincomes .= '<tr>'
                   . '<td>' . $val['client_title'] . '</td>'
                   . '<td>' . $val['contract_title'] . '</td>'
                   . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td>'
                   . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_total']) . '</td>'
                   . '<td style="text-align: right; color: ' . $color . ';">' . sprintf('%.2f', $val['estimate']) . '</td>'
                   . '<td style="text-align: right; color: ' . $color . ';">' . sprintf('%.2f', $pct_period) . '%' . '</td>'
                   . '<td style="text-align: right; color: ' . $color . ';">' . sprintf('%.2f', $pct_total) . '%' . '</td>'
                   . '</tr>';
          $totalincomes += $val['hours_period'];
        }
      }
    }
  }
  else {
    // List only excluded clients
    foreach ($exclude_clients as $client) {
      $tmp = $report_lines[$client];

      foreach ($tmp as $key => $val) {
        $outputincomes .= '<tr>'
                 . '<td>' . $val['client_title'] . '</td>'
                 . '<td>' . $val['contract_title'] . '</td>'
                 . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_total']) . '</td>'
                 . '</tr>';
        $totalincomes += $val['hours_total'];
      }
    }
  }


  //
  // Expenses
  //
  $totalexpenses = 0;
  $outputexpenses  = '<table>';
  $outputexpenses .= '<tr>'
           . '<th width="300">' . t('Client')     . '</th>'
           . '<th>' . t('Contract')   . '</th>'
           . '<th style="text-align: right;">' . t('Worked (h)') . '</th>'
           . '<th style="text-align: right;">' . t('% total') . '</th>'
           . '</tr>';

  if ($exclude_mode == 'expenses') {
    // List all, except those excluded (not tested! XXX)
    foreach ($report_lines as $client_name => $tmp) {
      foreach ($tmp as $key => $val) {
        if (! in_array($val['client_title'], $exclude_clients)) {
          $outputexpenses .= '<tr>'
                   . '<td>' . $val['client_title'] . '</td>'
                   . '<td>' . $val['contract_title'] . '</td>'
                   . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td>'
                   . '</tr>';
          $totalexpenses += $val['hours_total'];
        }
      }
    }
  } else {
    // List only excluded clients
    foreach ($exclude_clients as $client) {
      $tmp = $report_lines[$client];

      foreach ($tmp as $key => $val) {
        $outputexpenses .= '<tr style="background-color: #DDD;">'
                 . '<td>' . $val['client_title'] . '</td>'
                 . '<td>' . $val['contract_title'] . '</td>'
                 . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td>'
                 . '<td style="text-align: right;">' . sprintf('%.2f', $val['percent_work']) . '%</td>'
                 . '</tr>';
        $totalexpenses += $val['hours_period'];

        // task details
        foreach ($val['hours_bytask'] as $task => $hours) {
          $outputexpenses .= '<tr style="font-size: 80%;">'
                   . '<td>&nbsp;</td>'
                   . '<td style="padding-left: 3em;">' . $task . '</td>'
                   . '<td style="text-align: right;">' . sprintf('%.2f', $hours['hours_period']) . '</td>'
                   . '<td style="text-align: right;">' . sprintf('%.2f', $hours['percent_work']) . '%</td>'
                   . '</tr>';
        }
      }
    }
  }

  // Include and summarise the incomes:
  $pctincomes = $totalincomes / ($totalincomes + $totalexpenses) * 100;

  $output = $outputincomes;
  $output .= '<tr>'
           . '<td colspan="2" style="font-weight: bold;">' . "Total incomes:" . '</td>'
           . '<td style="font-weight: bold; text-align: right;">'
           .  '<span title="' . t('Total income hours worked during this period.') . '">' . sprintf('%.2f', $totalincomes) . '</span>'
           . '</td>'
           . '<td colspan="4" style="font-weight: bold; text-align: right;">'
           .  '<span title="' . t('Percentage of incomes part of the total hours worked.') . '">' . sprintf('%.2f', $pctincomes) . '%</span>'
           . '</td>'
           . '</tr>';
  $output .= '</table>';

  $output .= $outputexpenses;

  // Include and summarise the expenses:
  $pctexpenses = $totalexpenses / ($totalincomes + $totalexpenses) * 100;

  $output .= '<tr>'
           . '<td colspan="2" style="font-weight: bold;">' . "Total expenses:" . '</td>'
           . '<td style="text-align: right; font-weight: bold;">'
           .  '<span title="' . t('Total expense hours worked during this period.') . '">' . sprintf('%.2f', $totalexpenses) . '</span>'
           . '</td>'
           . '<td style="text-align: right; font-weight: bold;">'
           .  '<span title="' . t('Percentage of expenses part of the total hours worked.') . '">' . sprintf('%.2f', $pctexpenses) . '%</span>'
           . '</td>'
           . '</tr>';

  // Grand total:
  $output .= '<tr>'
          . '<td colspan="2"><strong>' . "TOTAL HOURS:" . '</td>'
          . '<td style="text-align: right; font-weight: bold;">'
          .  '<span title="' . t('Total hours worked during this period (incomes + expenses).') . '">' . sprintf('%.2f', $totalincomes + $totalexpenses) . '</span>'
          . '</td>'
          . '</tr>';
  $output .= '</table>';

  return $output;
}

function kprojectreports_timespent_get_summary_global($date_start, $date_end) {
  $report_lines = array();
  $hours_grand_total = 0;

  // Get hours worked, by contract (assumes you are punching in tasks)
  $sql = "SELECT ktask_kcontract_node.title, ktask_kcontract_node.nid, sum(kpunch.duration) / 60 / 60 as periodhours
          FROM {kpunch} kpunch
          LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
          LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
          LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
          WHERE kpunch.begin >= :begin
            AND kpunch.begin + kpunch.duration <= :end
          GROUP BY ktask_kcontract_node.nid";

  $result = db_query($sql, array(':begin' => $date_start, ':end' => $date_end));

  foreach ($result as $contract) {
    // Fetch client name and estimate for contract
    $res2  = db_query('SELECT kcid, parent, estimate FROM {kcontract} WHERE nid = :nid', array(':nid' => $contract->nid));
    $contract_total = 0;
    $contract_bytask = array();

    if (($kcontract = $res2->fetchObject())) {
      $client = db_query('SELECT title FROM {node} WHERE nid = :nid', array(':nid' => $kcontract->parent))->fetchField();

      // Fetch total number of hours worked on the project
      $sql3 = "SELECT sum(kpunch.duration) / 60 / 60 as totalhours
               FROM {kpunch} kpunch
               LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
               LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
               LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
               WHERE ktask_kcontract_node.nid = :nid";
  
      $contract_total = db_query($sql3, array(':nid' => $contract->nid))->fetchField();
    } else {
      $client = t("Error: Could not find the contract information!");
    }

    // Fetch details by task (i know, redundant.. but useful for expenses)
    $sql4 = "SELECT node_kpunch.title, sum(kpunch.duration) / 60 / 60 as periodhours
             FROM {kpunch} kpunch
             LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
             LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
             LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
             WHERE kpunch.begin >= :begin
               AND kpunch.begin + kpunch.duration <= :end
               AND ktask_kcontract_node.nid = :nid
             GROUP BY kpunch.nid";

    $res4 = db_query($sql4, array(':begin' => $date_start, ':end' => $date_end, ':nid' => $contract->nid));

    foreach ($res4 as $record4) {
      $contract_bytask[$record4->title] = array('hours_period' => $record4->periodhours);
    }

    $report_lines[$client][] = array(
      'contract_title' => $contract->title,
      'client_title'   => $client,
      'hours_period'   => $contract->periodhours,
      'hours_total'    => $contract_total,
      'hours_bytask'   => $contract_bytask,
      'estimate'       => $kcontract->estimate,
    );

    $hours_grand_total += $contract->periodhours;
  }

  // Calculate the % per contract/task
  if ($hours_grand_total) {
    foreach ($report_lines as $client => $clientdetails) {
      foreach ($clientdetails as $contract => $contractdetails) {
        $report_lines[$client][$contract]['percent_work'] = $contractdetails['hours_period'] / $hours_grand_total * 100;

        foreach ($contractdetails['hours_bytask'] as $task => $taskdetails) {
          $report_lines[$client][$contract]['hours_bytask'][$task]['percent_work'] = $taskdetails['hours_period'] / $hours_grand_total * 100;
        }
      }
    }
  }

  ksort($report_lines);
  return $report_lines;
}

//
// ------------------------------- REPORT BY USER -------------------------
//

function kprojectreports_timespent_user($date_start, $date_end) {
  $output = '';

  $exclude_mode = 'incomes';
  $exclude_clients = array('Koumbit');

  $result = db_query("select uid from users_roles where rid = 7"); // FIXME, HARDCODE XXX

  foreach ($result as $user) {
    $user = user_load($user->uid);
    $report_lines = kprojectreports_timespent_get_summary_by_user($user->uid, $date_start, $date_end);

    //
    // Incomes
    //
    $totalincomes = 0;
    $outputincomes = '<table>';
    $outputincomes .= '<tr>'
             . '<th width="300">' . t('Client')     . '</th>'
             . '<th>' . t('Contract')   . '</th>'
             . '<th style="text-align: right;">' . t('Worked (h)') . '</th>'
             . '<th style="text-align: right;">' . t('% total') . '</th>'
             . '</tr>';
  
    if ($exclude_mode == 'incomes') {
      // List all, except those excluded
      foreach ($report_lines as $client_name => $tmp) {
        foreach ($tmp as $key => $val) {
          if (! in_array($val['client_title'], $exclude_clients)) {
            $outputincomes .= '<tr>'
                     . '<td>' . $val['client_title'] . '</td>'
                     . '<td>' . $val['contract_title'] . '</td>'
                     . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td>'
                     . '<td style="text-align: right;">' . sprintf('%.2f', $val['percent_work']) . '%</td>'
                     . '</tr>';
            $totalincomes += $val['hours_period'];
          }
        }
      }
    } else {
      // List only excluded clients
      foreach ($exclude_clients as $client) {
        $tmp = $report_lines[$client];
  
        foreach ($tmp as $key => $val) {
          $outputincomes .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td></tr>';
          $totalincomes += $val['hours_period'];
        }
      }
    }
  
  
    //
    // Expenses
    //
    $totalexpenses = 0;
    $outputexpenses = '';
  
    if ($exclude_mode == 'expenses') {
      // List all, except those excluded
      foreach ($report_lines as $client_name => $tmp) {
        foreach ($tmp as $key => $val) {
          if (! in_array($val['client_title'], $exclude_clients)) {
            $outputexpenses .= '<tr>'
                     . '<td>' . $val['client_title'] . '</td>'
                     . '<td>' . $val['contract_title'] . '</td>'
                     . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td>'
                     . '<td style="text-align: right;">' . sprintf('%.2f', $val['percent_work']) . '</td>'
                     . '</tr>';
            $totalexpenses += $val['hours_period'];
          }
        }
      }
    } else {
      // List only excluded clients
      foreach ($exclude_clients as $client) {
        $tmp = $report_lines[$client];
  
        if (count($tmp)) {
          foreach ($tmp as $key => $val) {
            $outputexpenses .= '<tr style="background-color: #DDD;">'
                     . '<td>' . $val['client_title'] . '</td>'
                     . '<td>' . $val['contract_title'] . '</td>'
                     . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td>'
                     . '<td style="text-align: right;">' . sprintf('%.2f', $val['percent_work']) . '%</td>'
                     . '</tr>';
            $totalexpenses += $val['hours_period'];

            // task details
            foreach ($val['hours_bytask'] as $task => $hours) {
              $outputexpenses .= '<tr style="font-size: 80%;">'
                       . '<td>&nbsp;</td>'
                       . '<td style="padding-left: 3em;">' . $task . '</td>'
                       . '<td style="text-align: right;">' . sprintf('%.2f', $hours['hours_period']) . '</td>'
                       . '<td style="text-align: right;">' . sprintf('%.2f', $hours['percent_work']) . '%</td>'
                       . '</tr>';
            }
          }
        }
      }
    }

    // Summarise incomes:
    if ($totalincomes + $totalexpenses > 0) {
      $pctincomes  = $totalincomes  / ($totalincomes + $totalexpenses) * 100;
      $pctexpenses = $totalexpenses / ($totalincomes + $totalexpenses) * 100;
    } else {
      $pctincomes  = $pctexpenses = 0;
    }

    $output .= "<h1>" . $user->name . "</h1>";
    $output .= $outputincomes;
    $output .= '<tr>'
             . '<td colspan="2"><strong>' . "Total incomes:" . '</strong></td>'
             . '<td style="text-align: right;"><strong>' . sprintf('%.2f', $totalincomes) . '</strong></td>'
             . '<td style="text-align: right;"><strong>' . sprintf('%.2f', $pctincomes) . '%</strong></td>'
             . '</tr>';

    // Summarise expenses:
    $output .= $outputexpenses;
    $output .= '<tr>'
             . '<td colspan="2"><strong>' . "Total expenses:" . '</strong></td>'
             . '<td style="text-align: right;"><strong>' . sprintf('%.2f', $totalexpenses) . '</strong></td>'
             . '<td style="text-align: right;"><strong>' . sprintf('%.2f', $pctexpenses) . '</strong></td>'
             . '</tr>';

    // Grand total:
    $output .= '<tr>'
             . '<td colspan="2"><strong>' . "TOTAL HOURS:" . '</strong></td>'
             . '<td style="text-align: right;"><strong>' . sprintf('%.2f', $totalincomes + $totalexpenses) . '</strong></td>'
             . '</tr>';
    $output .= '</table>';
  }

  return $output;
}

function kprojectreports_timespent_get_summary_by_user($uid, $date_start, $date_end) {
  $report_lines = array();
  $hours_grand_total = 0;

  $sql = "SELECT ktask_kcontract_node.title, ktask_kcontract_node.nid, sum(kpunch.duration) / 60 / 60 as periodhours
          FROM {kpunch} kpunch
          LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
          INNER JOIN {users} users ON kpunch.uid = users.uid
          LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
          LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
          WHERE kpunch.uid = %d
            AND kpunch.begin >= %d
            AND kpunch.begin + kpunch.duration <= %d
          GROUP BY ktask_kcontract_node.nid";

  $result = db_query($sql, $uid, $date_start, $date_end);

  while ($contract = db_fetch_object($result)) {
    // Fetch client name for contract
    $clientid  = db_result(db_query('SELECT parent FROM {kcontract} WHERE nid = %d', $contract->nid));
    $client    = db_result(db_query('SELECT title FROM {node} WHERE nid = %d', $clientid));
    $contract_bytask = array();

    // Fetch details by task (useful for expenses)
    $sql4 = "SELECT node_kpunch.title, sum(kpunch.duration) / 60 / 60 as periodhours
             FROM {kpunch} kpunch
             LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
             LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
             LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
             WHERE kpunch.uid = %d
               AND kpunch.begin >= %d
               AND kpunch.begin + kpunch.duration <= %d
               AND ktask_kcontract_node.nid = %d
             GROUP BY kpunch.nid";

    $result4 = db_query($sql4, $uid, $date_start, $date_end, $contract->nid);

    while ($row4 = db_fetch_array($result4)) {
      $contract_bytask[$row4['title']] = array('hours_period' => $row4['periodhours']);
    }

    $report_lines[$client][] = array(
      'client_id'      => $clientid,
      'contract_title' => $contract->title,
      'client_title'   => $client,
      'hours_period'   => $contract->periodhours,
      'hours_bytask'   => $contract_bytask,
    );

    $hours_grand_total += $contract->periodhours;
  }

  // Calculate the % per contract/task
  if ($hours_grand_total) {
    foreach ($report_lines as $client => $clientdetails) {
      foreach ($clientdetails as $contract => $contractdetails) {
        $report_lines[$client][$contract]['percent_work'] = $contractdetails['hours_period'] / $hours_grand_total * 100;

        foreach ($contractdetails['hours_bytask'] as $task => $taskdetails) {
          $report_lines[$client][$contract]['hours_bytask'][$task]['percent_work'] = $taskdetails['hours_period'] / $hours_grand_total * 100;
        }
      }
    }
  }

  ksort($report_lines);
  return $report_lines;
}
