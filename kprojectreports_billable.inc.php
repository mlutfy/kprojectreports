<?php

function kprojectreports_billable_datehelp() {
  return t("This date field is not used by this report. However, there are magic parameters you can add to the preview URL: ?uid=X or ?current_uid=1. The former will generate a report only for that user (you can find the uid by looking in admin/user/user. The latter will generate a report only for your contracts.");
}

function kprojectreports_billable($start, $end, $report) {
  $output = '';
  $exclude_clients = array('Koumbit'); // HARDCODE XXX FIXME

  $report_lines = kprojectreports_billable_get_summary_global($date_start, $date_end);

  $output .= '<table>';
  $output .= '<tr>'
           . '<th>' . t('Client') . '</th>'
           . '<th>' . t('Contract') . '</th>'
           . '<th>' . t('Lead') . '</th>'
           . '<th><span title="' . t('Number of hours that have not yet been billed.') . '">' . t('Billable'). '</span></th>'
           . '<th><span title="' . t('Total number of hours worked on the contract.') . '">' . t('Total work') . '</span></th>'
           . '<th><span title="' . t('Initial contract estimate (hours).') . '">' . t('Estimate') . '</span></th>'
           . '<th><span title="' . t('Percentage of hours that should be billed (billable / total * 100)') . '">' . t('% Billable') . '</span></th>'
           . '<th><span title="' . t('Percentage of hours worked on the project, compared to the initial contract estimate.') . '">' . t('% Worked') . '</span></th>'
           . '</tr>';

  foreach ($report_lines as $client_name => $tmp) {
    foreach ($tmp as $key => $val) {
      if (! in_array($val['client_title'], $exclude_clients)) {
        if ($val['estimate'] > 0) {
          $pct_billable = $val['hours_period'] / $val['estimate'] * 100;
          $pct_total  = $val['hours_total'] / $val['estimate'] * 100;
        } else {
          $pct_billable = 0;
          $pct_total = 0;
        }

        $output .= '<tr>'
                 . '<td>' . $val['client_title'] . '</td>'
                 . '<td>' . $val['contract_title'] . '</td>'
                 . '<td>' . $val['contract_lead'] . '</td>'
                 . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_period']) . '</td>'
                 . '<td style="text-align: right;">' . sprintf('%.2f', $val['hours_total']) . '</td>'
                 . '<td style="text-align: right;" ' . ($val['estimate'] ? '' : 'style="color: red;"') . '>' . sprintf('%.2f', $val['estimate']) . '</td>'
                 . '<td style="text-align: right;" ' . ($pct_total > 100 ? 'style="color: red;"' : ($pct_total > 80 ? 'style="color: #AAAA00;"' : '')) . '>'
                 .  sprintf('%.2f', $pct_billable) . '%'
                 . '</td>'
                 . '<td style="text-align: right;" ' . ($pct_total > 100 ? 'style="color: red;"' : ($pct_total > 80 ? 'style="color: #AAAA00;"' : '')) . '>'
                 .  sprintf('%.2f', $pct_total) . '%' 
                 . '</td>'
                 . '</tr>';

        $totalbillable += $val['hours_period'];
      }
    }
  }

  $output .= '<tr>'
           . '<td colspan="3"><strong>' . t('Total billable') . '</strong></td>'
           . '<td style="text-align: right;"><strong>' . sprintf('%.2f', $totalbillable) . '</strong></td>'
           . '</tr>';

  $output .= '</table>';

  return $output;
}

function kprojectreports_billable_get_summary_global($date_start, $date_end) {
  global $user;
  $report_lines = array();

  $sql = "SELECT ktask_kcontract_node.title, kcontract.lead, ktask_kcontract_node.nid, sum(kpunch.duration) / 60 / 60 as periodhours
          FROM {kpunch} kpunch
          LEFT JOIN {node} node_kpunch ON kpunch.nid = node_kpunch.nid
          INNER JOIN {users} users ON kpunch.uid = users.uid
          LEFT JOIN {ktask} node_kpunch__ktask ON node_kpunch.vid = node_kpunch__ktask.vid
          LEFT JOIN {node} ktask_kcontract_node ON node_kpunch__ktask.parent = ktask_kcontract_node.nid
          LEFT JOIN {kcontract} ON node_kpunch__ktask.parent = kcontract.nid
          WHERE kpunch.billable_client = 1
            AND kpunch.order_reference = 0
            AND kcontract.state NOT IN (" . KPROJECT_CONTRACT_STATE_CLOSED . ',' . KPROJECT_CONTRACT_STATE_LOST . ',' . KPROJECT_CONTRACT_STATE_CANCELED . ")
            " . ($_REQUEST['uid'] ? "AND kcontract.lead = " . intval($_REQUEST['uid']) : '') . "
            " . ($_REQUEST['uid_current'] ? " AND kcontract.lead = " . $user->uid : '') . "
          GROUP BY ktask_kcontract_node.nid";

  $result = db_query($sql);

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

    // Fetch lead username
    $contract->lead = kprojectreports_billable_get_username($contract->lead);

    $report_lines[$client][] = array(
      'client_id'      => $clientid,
      'project_id'     => $projectid,
      'contract_title' => $contract->title,
      'contract_lead'  => $contract->lead,
      'client_title'   => $client,
      'hours_period'   => $contract->periodhours,
      'hours_total'    => $contract_total,
      'estimate'       => $kcontract->estimate,
    );
  }

  ksort($report_lines);
  return $report_lines;
}

function kprojectreports_billable_get_username($uid) {
  static $usernames;

  if (isset($usernames[$uid])) {
    return $usernames[$uid];
  }

  $usernames[$uid] = db_result(db_query('SELECT name FROM {users} WHERE uid = %d', $uid));
  return $usernames[$uid];
}

