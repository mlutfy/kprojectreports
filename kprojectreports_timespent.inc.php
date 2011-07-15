<?php

function kprojectreports_timespent($start, $end, $report) {
  $output = '';

  $output .= kprojectreports_timespent_global($start, $end);
  $output .= kprojectreports_timespent_user($start, $end);

  return $output;
}

//
// ------------------------------- GLOBAL REPORT -------------------------
//

function kprojectreports_timespent_global($date_start, $date_end) {
  $output = '';

  $exclude_mode = 'incomes';
  $exclude_clients = array('Koumbit'); // HARDCODE XXX FIXME

  $report_lines = kprojectreports_timespent_get_summary_global($date_start, $date_end);

  //
  // Incomes
  //
  $totalincomes = 0;

  $output .= '<table>';
  $output .= '<tr>'
           . '<th>' . t('Client')     . '</th>'
           . '<th>' . t('Contract')   . '</th>'
           . '<th>' . t('Period work'). '</th>'
           . '<th>' . t('Total work') . '</th>'
           . '<th>' . t('Estimate')   . '</th>'
           . '<th>' . t('Percent (period/total)')    . '</th>'
           . '</tr>';

  if ($exclude_mode == 'incomes') {
    // List all, except those excluded
    foreach ($report_lines as $client_name => $tmp) {
      foreach ($tmp as $key => $val) {
        if (! in_array($val['client_title'], $exclude_clients)) {
          $pct_period = $val['hours_period'] / $val['estimate'] * 100;
	  $pct_total  = $val['hours_total'] / $val['estimate'] * 100;

          $output .= '<tr>'
                   . '<td>' . $val['client_title'] . '</td>'
                   . '<td>' . $val['contract_title'] . '</td>'
                   . '<td>' . sprintf('%.2f', $val['hours_period']) . '</td>'
                   . '<td>' . sprintf('%.2f', $val['hours_total']) . '</td>'
                   . '<td ' . ($val['estimate'] ? '' : 'style="color: red;"') . '>' . sprintf('%.2f', $val['estimate']) . '</td>'
                   . '<td ' . ($pct_total > 100 ? 'style="color: red;"' : ($pct_total > 80 ? 'style="color: yellow;"' : '')) . '>'
                     . sprintf('%.2f', $pct_period) . '%'
                     . ' / '
                     . sprintf('%.2f', $pct_total) . '%' 
		   . '</td>'
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
        $output .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td>' . sprintf('%.2f', $val['hours_total']) . '</td></tr>';
        $totalincomes += $val['hours_total'];
      }
    }
  }

  $output .= '</table>';
  $output .= "<p>" . "TOTAL INCOMES: " . sprintf('%.2f', $totalincomes) . " h" . "</p>";

  //
  // Expenses
  //
  $totalexpenses = 0;
  $output .= '<table>';

  if ($exclude_mode == 'expenses') {
    // List all, except those excluded
    foreach ($report_lines as $client_name => $tmp) {
      foreach ($tmp as $key => $val) {
        if (! in_array($val['client_title'], $exclude_clients)) {
          $output .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td>' . sprintf('%.2f', $val['hours_period']) . '</td></tr>';
          $totalexpenses += $val['hours_total'];
        }
      }
    }
  } else {
    // List only excluded clients
    foreach ($exclude_clients as $client) {
      $tmp = $report_lines[$client];

      foreach ($tmp as $key => $val) {
        $output .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td>' . sprintf('%.2f', $val['hours_period']) . '</td></tr>';
        $totalexpenses += $val['hours_period'];
      }
    }
  }

  $output .= '</table>';
  $output .= "<p>" . "TOTAL EXPENSES: " . sprintf('%.2f', $totalexpenses) . " h" . "</p>";
  $output .= "<p>" . "TOTAL HOURS: " . sprintf('%.2f', $totalincomes + $totalexpenses) . " h" . "</p>";

  return $output;
}

function kprojectreports_timespent_get_summary_global($date_start, $date_end) {
  $report_lines = array();

  $sql = "SELECT ktask_kcontract_node.title, ktask_kcontract_node.nid, sum(kpunch.duration) / 60 / 60 as periodhours
          FROM {kpunch} kpunch
          LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
          INNER JOIN {users} users ON kpunch.uid = users.uid
          LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
          LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
          WHERE kpunch.begin >= %d
            AND kpunch.begin + kpunch.duration <= %d
          GROUP BY ktask_kcontract_node.nid";

  $result = db_query($sql, $date_start, $date_end);

  while ($contract = db_fetch_object($result)) {
    // Fetch client name and estimate for contract
    $res2  = db_query('SELECT kcid, parent, estimate FROM {kcontract} WHERE nid = %d', $contract->nid);
    $contract_total = 0;

    if (($kcontract = db_fetch_object($res2))) {
      $client = db_result(db_query('SELECT title FROM {node} WHERE nid = %d', $kcontract->parent));

      // Fetch total number of hours worked on the project
      $sql3 = "SELECT sum(kpunch.duration) / 60 / 60 as totalhours
               FROM {kpunch} kpunch
               LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
               LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
               LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
               WHERE ktask_kcontract_node.nid = %d";
  
      $contract_total = db_result(db_query($sql3, $contract->nid));
    } else {
      $client = t("Error: Could not find the contract information!");
    }

    $report_lines[$client][] = array(
      'client_id'      => $clientid,
      'project_id'     => $projectid,
      'contract_title' => $contract->title,
      'client_title'   => $client,
      'hours_period'   => $contract->periodhours,
      'hours_total'    => $contract_total,
      'estimate'       => $kcontract->estimate,
    );
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

  while ($user = db_fetch_object($result)) {
    $user = user_load($user->uid);

    $output .= "<h3>" . "********************** " . $user->name . " ******************************" . "</h3>";

    $report_lines = kprojectreports_timespent_get_summary_by_user($user->uid, $date_start, $date_end);

    //
    // Incomes
    //
    $totalincomes = 0;
    $output .= '<table>';
  
    if ($exclude_mode == 'incomes') {
      // List all, except those excluded
      foreach ($report_lines as $client_name => $tmp) {
        foreach ($tmp as $key => $val) {
          if (! in_array($val['client_title'], $exclude_clients)) {
            $output .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td>' . sprintf('%.2f', $val['hours_total']) . '</td></tr>';
            $totalincomes += $val['hours_total'];
          }
        }
      }
    } else {
      // List only excluded clients
      foreach ($exclude_clients as $client) {
        $tmp = $report_lines[$client];
  
        foreach ($tmp as $key => $val) {
          $output .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td>' . sprintf('%.2f', $val['hours_total']) . '</td></tr>';
          $totalincomes += $val['hours_total'];
        }
      }
    }
  
    $output .= '<tr><td>' . "TOTAL INCOMES:" . '</td><td>' . sprintf('%.2f', $totalincomes) . '</td></tr>';
    $output .= '</table>';
  
    //
    // Expenses
    //
    $totalexpenses = 0;
    $output .= '<table>';
  
    if ($exclude_mode == 'expenses') {
      // List all, except those excluded
      foreach ($report_lines as $client_name => $tmp) {
        foreach ($tmp as $key => $val) {
          if (! in_array($val['client_title'], $exclude_clients)) {
            $output .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td>' . sprintf('%.2f', $val['hours_total']) . '</td></tr>';
            $totalexpenses += $val['hours_total'];
          }
        }
      }
    } else {
      // List only excluded clients
      foreach ($exclude_clients as $client) {
        $tmp = $report_lines[$client];
  
        foreach ($tmp as $key => $val) {
          $output .= '<tr><td>' . $val['client_title'] . '</td><td>' . $val['contract_title'] . '</td><td>' . sprintf('%.2f', $val['hours_total']) . '</td></tr>';
          $totalexpenses += $val['hours_total'];
        }
      }
    }

    $output .= '<tr><td>' . "TOTAL EXPENSES:" . '</td><td>' . sprintf('%.2f', $totalexpenses) . '</td></tr>';
    $output .= '</table>';
    $output .= "<p>TOTAL HOURS: " . sprintf('%.2f', $totalincomes + $totalexpenses) . '</p>';
  }

  return $output;
}

function kprojectreports_timespent_get_summary_by_user($uid, $date_start, $date_end) {
  $report_lines = array();

  $sql = "SELECT ktask_kcontract_node.title, ktask_kcontract_node.nid, sum(kpunch.duration) / 60 / 60 as totalhours
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
    # $projectid = db_result(db_query('SELECT parent FROM {kcontract} WHERE nid = %d', $contract->nid));
    $clientid  = db_result(db_query('SELECT parent FROM {kcontract} WHERE nid = %d', $contract->nid));
    $client    = db_result(db_query('SELECT title FROM {node} WHERE nid = %d', $clientid));

    $report_lines[$client][] = array(
      'client_id'      => $clientid,
      'project_id'     => $projectid,
      'contract_title' => $contract->title,
      'client_title'   => $client,
      'hours_total'    => $contract->totalhours,
    );
  }

  ksort($report_lines);
  return $report_lines;
}
